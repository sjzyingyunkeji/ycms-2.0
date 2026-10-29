<?php

/*
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


declare(strict_types=1);

namespace App;

use App\Core\Router;
use App\Core\Request;
use App\Core\Database;

/**
 * 应用引导：加载自动加载、注册路由、分发请求
 */
final class App
{
    public static function bootstrap(): void
    {
        date_default_timezone_set((string) config('app.timezone', 'Asia/Shanghai'));
        self::registerErrorHandlers();
        self::loadEnv();
        self::ensureDatabase();

        $request = new Request();

        // 媒体文件直出（/storage/*）：未命中 Web 服务器静态文件时作为兜底
        if (str_starts_with($request->path, '/storage/')) {
            self::serveStorageFile($request->path);
        }

        $router = self::registerRoutes();

        self::applySecurityHeaders($request);

        // CSRF 纵深防御：写请求拒绝跨站来源（Cookie 会话 + SameSite=Lax 之外的第二道锁）
        self::guardCrossSiteRequest($request);

        $router->dispatch($request);
    }

    /**
     * 安全响应头 + 同源 CORS
     *
     * 默认不开放跨域（前后台同源部署）。若确实需要分域部署，
     * 在 backend/.env 配置 CORS_ALLOWED_ORIGINS=https://a.com,https://b.com
     */
    private static function applySecurityHeaders(Request $request): void
    {
        header('Vary: Origin');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        if ((string) config('app.env') !== 'development') {
            header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");
        }

        $origin = (string) ($request->header('Origin') ?? '');
        if ($origin !== '' && in_array($origin, self::allowedOrigins(), true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 600');
        }
    }

    /**
     * 允许的跨域来源（同源 + env 白名单）
     * @return array<int, string>
     */
    private static function allowedOrigins(): array
    {
        $origins = [];
        foreach (self::requestHosts() as $host) {
            $origins[] = 'https://' . $host;
            $origins[] = 'http://' . $host;
        }
        foreach (explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')) as $item) {
            $item = rtrim(trim($item), '/');
            if ($item !== '' && str_contains($item, '://')) {
                $origins[] = $item;
            }
        }
        return array_values(array_unique($origins));
    }

    /**
     * 可信站点主机名（兼容反向代理）
     * @return array<int, string>
     */
    private static function requestHosts(): array
    {
        $candidates = [
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''),
            (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''),
        ];
        $hosts = [];
        foreach ($candidates as $value) {
            $host = strtolower(trim(explode(',', $value)[0]));
            $host = (string) preg_replace('/:\d+$/', '', $host);
            if ($host !== '') {
                $hosts[] = $host;
            }
        }
        return array_values(array_unique($hosts));
    }

    /**
     * 写操作来源校验：Origin 的主机必须是本站（或显式白名单）
     */
    private static function guardCrossSiteRequest(Request $request): void
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $origin = strtolower((string) ($request->header('Origin') ?? ''));
        if ($origin === '') {
            // 命令行、老浏览器或同源表单可能不带 Origin，交由 Cookie/SameSite 兜底
            return;
        }

        $allowed = array_map('strtolower', self::allowedOrigins());
        if (in_array($origin, $allowed, true)) {
            return;
        }

        $host = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));
        if ($host !== '' && in_array($host, self::requestHosts(), true)) {
            return;
        }

        \App\Core\Response::error('跨站请求已被拒绝', 403);
    }

    /**
     * 统一异常处理：对外只返回通用错误，详情写入 storage/logs
     */
    private static function registerErrorHandlers(): void
    {
        set_exception_handler(static function (\Throwable $e): void {
            self::logError($e);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'code' => 500,
                'message' => config('app.debug') ? $e->getMessage() : '服务器内部错误，请稍后重试',
                'errors' => null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });

        register_shutdown_function(static function (): void {
            $last = error_get_last();
            if ($last === null || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            self::logError(new \ErrorException(
                (string) $last['message'],
                0,
                (int) $last['type'],
                (string) $last['file'],
                (int) $last['line']
            ));
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'code' => 500,
                'message' => '服务器内部错误，请稍后重试',
                'errors' => null,
            ], JSON_UNESCAPED_UNICODE);
        });
    }

    private static function logError(\Throwable $e): void
    {
        $dir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        @file_put_contents($dir . '/app-' . date('Ymd') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 输出 storage 目录下的媒体文件（带目录穿越与可执行文件防护）
     */
    private static function serveStorageFile(string $uri): void
    {
        // 禁止通过 /storage 访问任何可执行/配置文件与隐藏文件
        if (
            preg_match('/\.(php|phar|phtml|php\d|htaccess|user\.ini|sqlite|log)$/i', $uri)
            || str_contains($uri, '..')
            || preg_match('#/\.[^/]#', $uri)
        ) {
            \App\Core\Response::error('禁止访问', 403);
        }

        $base = dirname(__DIR__, 2) . '/public/storage/';
        $relative = substr($uri, strlen('/storage/'));
        $file = realpath($base . $relative);

        if (
            $file === false
            || !str_starts_with($file, realpath($base) ?: $base)
            || !is_file($file)
        ) {
            \App\Core\Response::error('文件不存在', 404);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        header('Content-Type: ' . (string) $finfo->file($file));
        if (str_ends_with(strtolower($file), '.svg')) {
            // 历史遗留 SVG 可能内嵌脚本，禁止其加载任何外部资源与执行脚本
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:");
        }
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
        exit;
    }

    private static function loadEnv(): void
    {
        // .env 已在 env() 中惰性加载，这里只需确保目录存在
    }

    /**
     * 确保数据库结构存在（幂等）：文件缺失时建库；缺少新表时自动补齐
     */
    private static function ensureDatabase(): void
    {
        $path = (string) config('database.path');
        $needsInit = !is_file($path);

        if (!$needsInit) {
            // 旧版数据库增量升级：检查新表是否存在（schema.sql 全部为 CREATE TABLE IF NOT EXISTS，可重复执行）
            foreach (['login_nonces', 'visit_log'] as $table) {
                $row = Database::selectOne(
                    "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name",
                    ['name' => $table]
                );
                if ($row === null) {
                    $needsInit = true;
                    break;
                }
            }
        }

        if ($needsInit) {
            $schema = @file_get_contents(dirname(__DIR__) . '/database/schema.sql');
            if ($schema !== false) {
                Database::pdo()->exec($schema);
            }
        }
    }

    private static function registerRoutes(): Router
    {
        $router = new Router();

        // 健康检查
        $router->get('/api/health', static fn (Request $request) => \App\Core\Response::success([
            'status' => 'ok',
            'time' => now(),
            'php' => PHP_VERSION,
        ], '服务正常'));

        // 认证
        $router->post('/api/auth/login', [\App\Controllers\AuthController::class, 'login']);
        $router->post('/api/auth/logout', [\App\Controllers\AuthController::class, 'logout']);
        $router->get('/api/auth/me', [\App\Controllers\AuthController::class, 'me']);
        $router->get('/api/auth/setup-status', [\App\Controllers\AuthController::class, 'setupStatus']);
        $router->post('/api/auth/credentials', [\App\Controllers\AuthController::class, 'credentials']);
        $router->put('/api/auth/profile', [\App\Controllers\AuthController::class, 'updateProfile']);
        $router->put('/api/auth/password', [\App\Controllers\AuthController::class, 'updatePassword']);

        // 仪表盘
        $router->get('/api/dashboard/stats', [\App\Controllers\DashboardController::class, 'stats']);

        // 访问统计
        $router->post('/api/track', [\App\Controllers\StatsController::class, 'track']);
        $router->get('/api/stats/overview', [\App\Controllers\StatsController::class, 'overview']);
        $router->get('/api/stats/post/{id}', [\App\Controllers\StatsController::class, 'post']);

        // 文章
        $router->get('/api/posts', [\App\Controllers\PostController::class, 'index']);
        $router->get('/api/posts/{id}', [\App\Controllers\PostController::class, 'show']);
        $router->post('/api/posts', [\App\Controllers\PostController::class, 'store']);
        $router->put('/api/posts/{id}', [\App\Controllers\PostController::class, 'update']);
        $router->delete('/api/posts/{id}', [\App\Controllers\PostController::class, 'destroy']);
        $router->post('/api/posts/{id}/view', [\App\Controllers\PostController::class, 'addView']);
        $router->post('/api/posts/{id}/top', [\App\Controllers\PostController::class, 'toggleTop']);

        // 分类
        $router->get('/api/categories', [\App\Controllers\CategoryController::class, 'index']);
        $router->post('/api/categories', [\App\Controllers\CategoryController::class, 'store']);
        $router->put('/api/categories/{id}', [\App\Controllers\CategoryController::class, 'update']);
        $router->delete('/api/categories/{id}', [\App\Controllers\CategoryController::class, 'destroy']);

        // 标签
        $router->get('/api/tags', [\App\Controllers\TagController::class, 'index']);
        $router->post('/api/tags', [\App\Controllers\TagController::class, 'store']);
        $router->put('/api/tags/{id}', [\App\Controllers\TagController::class, 'update']);
        $router->delete('/api/tags/{id}', [\App\Controllers\TagController::class, 'destroy']);

        // 页面
        $router->get('/api/pages', [\App\Controllers\PageController::class, 'index']);
        $router->get('/api/pages/{id}', [\App\Controllers\PageController::class, 'show']);
        $router->post('/api/pages', [\App\Controllers\PageController::class, 'store']);
        $router->put('/api/pages/{id}', [\App\Controllers\PageController::class, 'update']);
        $router->delete('/api/pages/{id}', [\App\Controllers\PageController::class, 'destroy']);

        // 媒体库
        $router->get('/api/media', [\App\Controllers\MediaController::class, 'index']);
        $router->post('/api/media', [\App\Controllers\MediaController::class, 'store']);
        $router->delete('/api/media/{id}', [\App\Controllers\MediaController::class, 'destroy']);

        // 评论
        $router->get('/api/comments', [\App\Controllers\CommentController::class, 'index']);
        $router->post('/api/comments', [\App\Controllers\CommentController::class, 'store']);
        $router->put('/api/comments/{id}', [\App\Controllers\CommentController::class, 'update']);
        $router->delete('/api/comments/{id}', [\App\Controllers\CommentController::class, 'destroy']);

        // 友链
        $router->get('/api/links', [\App\Controllers\LinkController::class, 'index']);
        $router->post('/api/links', [\App\Controllers\LinkController::class, 'store']);
        $router->put('/api/links/{id}', [\App\Controllers\LinkController::class, 'update']);
        $router->delete('/api/links/{id}', [\App\Controllers\LinkController::class, 'destroy']);

        // 设置
        $router->get('/api/settings', [\App\Controllers\SettingController::class, 'index']);
        $router->put('/api/settings', [\App\Controllers\SettingController::class, 'update']);

        // ============ SEO（服务端渲染，供搜索引擎抓取） ============
        $router->get('/post/{slug}', [\App\Controllers\SeoController::class, 'post']);
        $router->get('/sitemap.xml', [\App\Controllers\SeoController::class, 'sitemap']);
        $router->get('/robots.txt', [\App\Controllers\SeoController::class, 'robots']);

        // 静态页重建（后台手动触发，零配置部署使用）
        $router->post('/api/system/rebuild-static', [\App\Controllers\SeoController::class, 'rebuildStatic']);

        // 主题信息（只读；在线切换为 Pro 能力）
        $router->get('/api/system/themes', [\App\Controllers\ThemeController::class, 'index']);

        // 系统更新（在线升级 / 离线包 / 回滚，仅管理员）
        $router->get('/api/system/update', [\App\Controllers\UpdateController::class, 'status']);
        $router->post('/api/system/update/check', [\App\Controllers\UpdateController::class, 'check']);
        $router->post('/api/system/update/run', [\App\Controllers\UpdateController::class, 'run']);
        $router->get('/api/system/update/backups', [\App\Controllers\UpdateController::class, 'backups']);
        $router->post('/api/system/update/rollback', [\App\Controllers\UpdateController::class, 'rollback']);
        $router->post('/api/system/update/upload', [\App\Controllers\UpdateController::class, 'upload']);

        self::registerModules($router);

        return $router;
    }

    /**
     * 挂载可选模块（Pro 能力通过 config/modules.php 注册，内核不引用其类型）
     */
    private static function registerModules(Router $router): void
    {
        foreach ((array) config('modules', []) as $module) {
            if (!is_string($module) || !class_exists($module)) {
                continue;
            }
            if (method_exists($module, 'register')) {
                $module::register($router);
            }
        }
    }
}

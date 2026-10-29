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

namespace App\Support;

use App\Core\Database;

/**
 * 访问统计：记录前台页面访问（含首页）并提供聚合查询
 *
 * 隐私说明：访客 IP 仅以 HMAC 哈希形式入库，不保存明文 IP。
 */
final class VisitLogger
{
    /** 同一访客对同一路径的去重窗口（秒） */
    private const DEDUPE_WINDOW = 1800;

    /** @var array<string, string>|null 设置缓存 */
    private static ?array $settings = null;

    /**
     * 记录一次访问
     *
     * @param array{path?:string, post_id?:int|null, referer?:string, ua?:string, ip?:string} $data
     */
    public static function record(array $data): void
    {
        if (self::setting('track_enabled', '1') !== '1') {
            return;
        }

        $path = self::sanitizePath((string) ($data['path'] ?? '/'));
        if ($path === '') {
            return;
        }

        $ua = mb_substr((string) ($data['ua'] ?? ''), 0, 255);
        $ip = (string) ($data['ip'] ?? self::clientIp());
        $ipHash = $ip === '' ? '' : hash_hmac('sha256', $ip, (string) config('jwt.secret', 'ycms'));
        $isBot = self::isBot($ua) ? 1 : 0;
        $device = self::device($ua);
        $referer = mb_substr((string) ($data['referer'] ?? ''), 0, 500);
        $postId = isset($data['post_id']) && (int) $data['post_id'] > 0 ? (int) $data['post_id'] : null;
        $pageType = self::pageType($path, $postId);

        // 去重：同访客同页面在窗口期内只记一次，防止刷新刷量
        if ($ipHash !== '') {
            $exists = Database::selectOne(
                "SELECT id FROM visit_log
                 WHERE ip_hash = :ip AND path = :path AND created_at > datetime('now', 'localtime', :window)",
                ['ip' => $ipHash, 'path' => $path, 'window' => '-' . self::DEDUPE_WINDOW . ' seconds']
            );
            if ($exists !== null) {
                return;
            }
        }

        Database::insert('visit_log', [
            'path' => $path,
            'post_id' => $postId,
            'page_type' => $pageType,
            'referer' => $referer,
            'ref_host' => self::refHost($referer),
            'ip_hash' => $ipHash,
            'ua' => $ua,
            'device' => $device,
            'is_bot' => $isBot,
            'created_at' => now(),
        ]);
    }

    /**
     * 总览统计
     *
     * @return array<string, mixed>
     */
    public static function overview(int $days = 30): array
    {
        $days = max(1, min(365, $days));

        $totalPv = (int) (Database::selectOne('SELECT COUNT(*) AS c FROM visit_log')['c'] ?? 0);
        $totalUv = (int) (Database::selectOne('SELECT COUNT(DISTINCT ip_hash) AS c FROM visit_log')['c'] ?? 0);

        $todayPv = (int) (Database::selectOne(
            "SELECT COUNT(*) AS c FROM visit_log WHERE date(created_at) = date('now', 'localtime')"
        )['c'] ?? 0);
        $todayUv = (int) (Database::selectOne(
            "SELECT COUNT(DISTINCT ip_hash) AS c FROM visit_log WHERE date(created_at) = date('now', 'localtime')"
        )['c'] ?? 0);

        $yesterdayPv = (int) (Database::selectOne(
            "SELECT COUNT(*) AS c FROM visit_log WHERE date(created_at) = date('now', 'localtime', '-1 day')"
        )['c'] ?? 0);
        $yesterdayUv = (int) (Database::selectOne(
            "SELECT COUNT(DISTINCT ip_hash) AS c FROM visit_log WHERE date(created_at) = date('now', 'localtime', '-1 day')"
        )['c'] ?? 0);

        // 访问趋势（补齐空白日期）
        $trendMap = [];
        $rows = Database::select(
            "SELECT date(created_at) AS date, COUNT(*) AS pv, COUNT(DISTINCT ip_hash) AS uv
             FROM visit_log
             WHERE created_at >= date('now', 'localtime', :days)
             GROUP BY date(created_at)",
            ['days' => '-' . ($days - 1) . ' days']
        );
        foreach ($rows as $row) {
            $trendMap[(string) $row['date']] = ['date' => (string) $row['date'], 'pv' => (int) $row['pv'], 'uv' => (int) $row['uv']];
        }
        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $trend[] = $trendMap[$date] ?? ['date' => $date, 'pv' => 0, 'uv' => 0];
        }

        // 热门文章
        $hotPosts = Database::select(
            "SELECT p.id, p.title, p.slug, p.views, p.cover_image, c.name AS category_name,
                    (SELECT COUNT(*) FROM visit_log v WHERE v.post_id = p.id) AS visits,
                    (SELECT COUNT(DISTINCT v.ip_hash) FROM visit_log v WHERE v.post_id = p.id) AS unique_visitors
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'published'
             ORDER BY visits DESC, p.views DESC, p.id DESC
             LIMIT 10"
        );

        // 热门页面
        $hotPages = Database::select(
            "SELECT path, page_type, COUNT(*) AS pv, COUNT(DISTINCT ip_hash) AS uv
             FROM visit_log
             GROUP BY path
             ORDER BY pv DESC
             LIMIT 10"
        );

        // 来源站点
        $referers = Database::select(
            "SELECT ref_host AS host, COUNT(*) AS pv
             FROM visit_log
             WHERE ref_host <> ''
             GROUP BY ref_host
             ORDER BY pv DESC
             LIMIT 10"
        );

        // 设备分布
        $devices = Database::select(
            'SELECT device, COUNT(*) AS pv FROM visit_log GROUP BY device ORDER BY pv DESC'
        );

        // 搜索引擎蜘蛛占比
        $botPv = (int) (Database::selectOne('SELECT COUNT(*) AS c FROM visit_log WHERE is_bot = 1')['c'] ?? 0);

        return [
            'total_pv' => $totalPv,
            'total_uv' => $totalUv,
            'today_pv' => $todayPv,
            'today_uv' => $todayUv,
            'yesterday_pv' => $yesterdayPv,
            'yesterday_uv' => $yesterdayUv,
            'bot_pv' => $botPv,
            'trend' => $trend,
            'hot_posts' => $hotPosts,
            'hot_pages' => $hotPages,
            'referers' => $referers,
            'devices' => $devices,
        ];
    }

    /**
     * 单篇文章的访问统计
     *
     * @return array<string, mixed>
     */
    public static function postStats(int $postId, int $days = 30): array
    {
        $days = max(1, min(365, $days));

        $pv = (int) (Database::selectOne(
            'SELECT COUNT(*) AS c FROM visit_log WHERE post_id = :pid',
            ['pid' => $postId]
        )['c'] ?? 0);
        $uv = (int) (Database::selectOne(
            'SELECT COUNT(DISTINCT ip_hash) AS c FROM visit_log WHERE post_id = :pid',
            ['pid' => $postId]
        )['c'] ?? 0);

        $rows = Database::select(
            "SELECT date(created_at) AS date, COUNT(*) AS pv, COUNT(DISTINCT ip_hash) AS uv
             FROM visit_log
             WHERE post_id = :pid AND created_at >= date('now', 'localtime', :days)
             GROUP BY date(created_at)",
            ['pid' => $postId, 'days' => '-' . ($days - 1) . ' days']
        );
        $trendMap = [];
        foreach ($rows as $row) {
            $trendMap[(string) $row['date']] = ['date' => (string) $row['date'], 'pv' => (int) $row['pv'], 'uv' => (int) $row['uv']];
        }
        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $trend[] = $trendMap[$date] ?? ['date' => $date, 'pv' => 0, 'uv' => 0];
        }

        $referers = Database::select(
            "SELECT ref_host AS host, COUNT(*) AS pv
             FROM visit_log
             WHERE post_id = :pid AND ref_host <> ''
             GROUP BY ref_host ORDER BY pv DESC LIMIT 8",
            ['pid' => $postId]
        );

        return [
            'pv' => $pv,
            'uv' => $uv,
            'trend' => $trend,
            'referers' => $referers,
        ];
    }

    /**
     * 读取站点设置（带内存缓存）
     */
    private static function setting(string $key, string $default = ''): string
    {
        if (self::$settings === null) {
            self::$settings = [];
            foreach (Database::select('SELECT key, value FROM settings') as $row) {
                self::$settings[(string) $row['key']] = (string) $row['value'];
            }
        }
        return self::$settings[$key] ?? $default;
    }

    /**
     * 清理路径中的查询串与潜在注入内容
     */
    private static function sanitizePath(string $path): string
    {
        $path = trim($path);
        $path = (string) (parse_url($path, PHP_URL_PATH) ?: '');
        $path = mb_substr($path, 0, 255);
        // 只保留安全的路径字符
        $path = (string) (preg_replace('/[^A-Za-z0-9\/\-_%\.\x{4e00}-\x{9fa5}]/u', '', $path) ?? '');
        return $path === '' ? '/' : $path;
    }

    /**
     * 页面类型推断
     */
    private static function pageType(string $path, ?int $postId): string
    {
        if ($postId !== null) {
            return 'post';
        }
        if ($path === '/' || $path === '') {
            return 'home';
        }
        if (str_starts_with($path, '/post') || str_starts_with($path, '/posts')) {
            return 'post';
        }
        if (str_starts_with($path, '/category') || str_starts_with($path, '/tag')) {
            return 'list';
        }
        if (str_starts_with($path, '/page')) {
            return 'page';
        }
        if (str_starts_with($path, '/archive') || str_starts_with($path, '/links')) {
            return 'list';
        }
        return 'other';
    }

    /**
     * 来源域名
     */
    private static function refHost(string $referer): string
    {
        if ($referer === '') {
            return '';
        }
        $host = parse_url($referer, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }
        // 站内跳转不算来源
        $self = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        if (is_string($self) && $self !== '' && $host === $self) {
            return '';
        }
        return mb_substr($host, 0, 120);
    }

    /**
     * 设备类型
     */
    private static function device(string $ua): string
    {
        $ua = strtolower($ua);
        if ($ua === '') {
            return 'unknown';
        }
        if (preg_match('/ipad|tablet|playbook|silk/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|ipod|android|windows phone|blackberry|opera mini/i', $ua)) {
            return 'mobile';
        }
        if (preg_match('/bot|crawler|spider|slurp|curl|wget|python-requests|headless/i', $ua)) {
            return 'bot';
        }
        return 'desktop';
    }

    /**
     * 是否搜索引擎蜘蛛
     */
    private static function isBot(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }
        return (bool) preg_match(
            '/bot|crawler|spider|slurp|baiduspider|bytespider|yandex|sogou|360|bingpreview|applebot|semrush|ahrefs|petalbot|headlesschrome/i',
            $ua
        );
    }

    /**
     * 客户端 IP
     */
    private static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $value = $_SERVER[$key] ?? '';
            if (!is_string($value) || $value === '') {
                continue;
            }
            $ip = trim(explode(',', $value)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '';
    }
}

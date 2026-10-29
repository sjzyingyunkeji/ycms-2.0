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

use App\Controllers\SeoController;
use App\Core\Database;
use App\Support\Theme\ThemeManager;

/**
 * 静态页生成器（零配置部署的核心）
 *
 * 把前台路由预先落成真实文件，因此服务器无需任何 URL 重写规则：
 *   /posts/index.html            列表页
 *   /archive/index.html          归档页
 *   /links/index.html            友链页
 *   /category/<id>/index.html    分类页
 *   /tag/<id>/index.html         标签页
 *   /post/<slug>/index.html      文章页（含 SEO 元信息与相关文章内链）
 *   /page/<slug>/index.html      独立页面
 *   /sitemap.xml  /robots.txt
 */
final class StaticBuilder
{
    /**
     * 重建全部静态页
     * @return array{ok:bool, files:int, message:string}
     */
    public static function rebuild(): array
    {
        $public = self::publicDir();
        $shellFile = $public . '/index.html';

        ThemeManager::protectThemesDir();

        if (!is_file($shellFile)) {
            return ['ok' => false, 'files' => 0, 'message' => '未找到当前主题外壳 public/index.html，请先构建主题并执行 theme.php activate'];
        }

        $shell = (string) file_get_contents($shellFile);
        $seo = new SeoController();
        $count = 0;

        // 1) 列表类页面（SPA 外壳 + 基础 SEO）
        foreach ([
            ['posts', '全部文章', '浏览全部文章列表'],
            ['archive', '归档', '按时间归档浏览全部文章'],
            ['links', '友链', '友情链接'],
        ] as [$seg, $title, $desc]) {
            if (self::write($public . '/' . $seg . '/index.html', self::shellWithMeta($shell, $title, $desc, '/' . $seg))) {
                $count++;
            }
        }

        // 2) 分类 / 标签
        foreach (Database::select('SELECT id, name FROM categories') as $row) {
            if (self::write(
                $public . '/category/' . (int) $row['id'] . '/index.html',
                self::shellWithMeta($shell, (string) $row['name'], '分类：' . $row['name'], '/category/' . (int) $row['id'])
            )) {
                $count++;
            }
        }
        foreach (Database::select('SELECT id, name FROM tags') as $row) {
            if (self::write(
                $public . '/tag/' . (int) $row['id'] . '/index.html',
                self::shellWithMeta($shell, (string) $row['name'], '标签：' . $row['name'], '/tag/' . (int) $row['id'])
            )) {
                $count++;
            }
        }

        // 3) 文章页（服务端渲染）
        foreach (Database::select("SELECT slug FROM posts WHERE status = 'published'") as $row) {
            $slug = (string) ($row['slug'] ?? '');
            // 纵深防御：即使库中存在历史脏数据，也不允许以路径穿越字符写文件
            if ($slug === '' || !preg_match('/^[\p{L}\p{N}\-_]+$/u', $slug)) {
                continue;
            }
            $html = $seo->buildPostPage($slug);
            if ($html !== null && self::write($public . '/post/' . $slug . '/index.html', $html)) {
                $count++;
            }
        }

        // 4) 独立页面
        foreach (Database::select("SELECT slug FROM pages WHERE status = 'published'") as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '' || !preg_match('/^[\p{L}\p{N}\-_]+$/u', $slug)) {
                continue;
            }
            $html = $seo->buildPagePage($slug);
            if ($html !== null && self::write($public . '/page/' . $slug . '/index.html', $html)) {
                $count++;
            }
        }

        // 5) 站点地图与 robots
        self::write($public . '/sitemap.xml', $seo->buildSitemapXml());
        self::write($public . '/robots.txt', $seo->buildRobotsTxt());
        $count += 2;

        return ['ok' => true, 'files' => $count, 'message' => '静态页生成完成'];
    }

    /**
     * 写入文件（自动建目录）
     */
    private static function write(string $file, string $content): bool
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        return file_put_contents($file, $content) !== false;
    }

    /**
     * 给 SPA 外壳注入标题/描述/canonical
     */
    private static function shellWithMeta(string $shell, string $title, string $description, string $path): string
    {
        $base = self::baseUrl();

        $html = preg_replace('/<title>.*?<\/title>/s', '', $shell, 1) ?? $shell;
        $html = preg_replace('/<meta name="description"[^>]*>/', '', $html, 1) ?? $html;

        $head = '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '" />'
            . '<link rel="canonical" href="' . htmlspecialchars($base . $path, ENT_QUOTES, 'UTF-8') . '" />';

        return str_replace('</head>', $head . '</head>', $html);
    }

    /**
     * 站点根地址
     */
    private static function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $scheme = $https ? 'https' : 'http';

        $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            $appUrl = (string) env('APP_URL', 'http://localhost');
            return rtrim($appUrl, '/');
        }
        $host = preg_replace('/[^A-Za-z0-9\.\-\:\[\]]/', '', $host) ?: 'localhost';
        return $scheme . '://' . $host;
    }

    /**
     * public 目录
     */
    public static function publicDir(): string
    {
        return dirname(__DIR__, 3) . '/public';
    }
}

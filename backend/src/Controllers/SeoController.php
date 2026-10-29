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

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Support\Markdown;
use App\Support\Theme\Template;
use App\Support\Theme\ThemeManager;
use App\Support\VisitLogger;

/**
 * SEO 控制器
 *
 * 提供搜索引擎友好的服务端渲染页面：
 *  - /post/{slug}  文章详情页（含 meta / JSON-LD / 相关文章内链）
 *  - /sitemap.xml  站点地图
 *  - /robots.txt   爬虫规则
 *
 * 说明：页面复用前端构建产物（frontend/dist/index.html），
 *      因此 SEO 页与 SPA 视觉完全一致，且 Vue 启动后会接管交互。
 */
final class SeoController
{
    /** @var string|null 模板缓存 */
    private static ?string $template = null;

    /**
     * 文章详情页（服务端渲染）
     */
    public function post(Request $request, string $slug): void
    {
        $slug = urldecode($slug);

        $post = Database::selectOne(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.nickname AS author_name
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.status = 'published'
             LIMIT 1",
            ['slug' => $slug]
        );

        if ($post === null) {
            // 找不到则回退到 SPA（前端会展示 404 页）
            http_response_code(404);
            $this->output($this->renderPage(
                $this->renderTemplate('404', ['site_name' => (string) ($this->settings()['site_name'] ?? 'ycms2.0')]),
                ['title' => '文章不存在', 'description' => '文章不存在或已下线', 'robots' => 'noindex, follow']
            ));
            return;
        }

        // 未设置 slug 的文章回退到 SPA 的 ID 路由
        if (trim((string) $post['slug']) === '') {
            header('Location: /posts/' . (int) $post['id'], true, 301);
            exit;
        }

        // 访问统计（服务端直接记录，携带文章 ID）
        VisitLogger::record([
            'path' => '/post/' . $post['slug'],
            'post_id' => (int) $post['id'],
            'referer' => (string) ($request->header('Referer') ?? ''),
            'ua' => (string) $request->header('User-Agent'),
        ]);

        $related = $this->relatedPosts((int) $post['id'], $post['category_id']);
        $prev = Database::selectOne(
            "SELECT id, title, slug FROM posts WHERE status = 'published'
             AND COALESCE(published_at, created_at) < COALESCE(:pa, :ca)
             ORDER BY COALESCE(published_at, created_at) DESC LIMIT 1",
            ['pa' => $post['published_at'], 'ca' => $post['created_at']]
        );
        $next = Database::selectOne(
            "SELECT id, title, slug FROM posts WHERE status = 'published'
             AND COALESCE(published_at, created_at) > COALESCE(:pa, :ca)
             ORDER BY COALESCE(published_at, created_at) ASC LIMIT 1",
            ['pa' => $post['published_at'], 'ca' => $post['created_at']]
        );
        $tags = Database::select(
            'SELECT t.id, t.name, t.slug FROM tags t
             JOIN post_tags pt ON pt.tag_id = t.id
             WHERE pt.post_id = :pid',
            ['pid' => $post['id']]
        );

        $settings = $this->settings();
        $base = $this->baseUrl();
        $canonical = $base . '/post/' . rawurlencode((string) $post['slug']);
        $siteName = (string) ($settings['site_name'] ?? 'ycms2.0');
        $description = mb_substr(trim((string) ($post['summary'] ?? '')), 0, 160);
        if ($description === '') {
            $description = mb_substr(strip_tags(Markdown::toHtml((string) $post['content'])), 0, 160);
        }

        $keywords = array_filter([
            (string) $post['title'],
            (string) ($post['category_name'] ?? ''),
            ...array_map(static fn (array $t): string => (string) $t['name'], $tags),
            (string) ($settings['site_keywords'] ?? ''),
        ]);

        // JSON-LD 结构化数据
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => (string) $post['title'],
            'description' => $description,
            'image' => $post['cover_image'] ? [$this->absoluteUrl((string) $post['cover_image'], $base)] : [],
            'datePublished' => (string) ($post['published_at'] ?: $post['created_at']),
            'dateModified' => (string) ($post['updated_at'] ?: $post['created_at']),
            'author' => ['@type' => 'Person', 'name' => (string) ($post['author_name'] ?: ($settings['owner_name'] ?? ''))],
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'articleSection' => (string) ($post['category_name'] ?? ''),
            'keywords' => implode(', ', $keywords),
            'wordCount' => mb_strlen(strip_tags((string) $post['content'])),
        ];

        $breadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $base . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => '文章', 'item' => $base . '/posts'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => (string) $post['title'], 'item' => $canonical],
            ],
        ];

        $content = $this->renderTemplate('post', $this->postTemplateData($post, $tags, $related, $prev, $next, $base));

        $this->output($this->renderPage($content, [
            'title' => (string) $post['title'] . ' · ' . $siteName,
            'description' => $description,
            'keywords' => implode(', ', $keywords),
            'canonical' => $canonical,
            'image' => $post['cover_image'] ? $this->absoluteUrl((string) $post['cover_image'], $base) : '',
            'type' => 'article',
            'publishedAt' => (string) ($post['published_at'] ?: $post['created_at']),
            'author' => (string) ($post['author_name'] ?: ($settings['owner_name'] ?? '')),
            'jsonLd' => [$jsonLd, $breadcrumb],
        ]));
    }

    /**
     * 生成文章页 HTML（供静态化使用，不输出）
     */
    public function buildPostPage(string $slug): ?string
    {
        $slug = urldecode($slug);

        $post = Database::selectOne(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.nickname AS author_name
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.status = 'published'
             LIMIT 1",
            ['slug' => $slug]
        );

        if ($post === null || trim((string) $post['slug']) === '') {
            return null;
        }

        $related = $this->relatedPosts((int) $post['id'], $post['category_id']);
        $prev = Database::selectOne(
            "SELECT id, title, slug FROM posts WHERE status = 'published'
             AND COALESCE(published_at, created_at) < COALESCE(:pa, :ca)
             ORDER BY COALESCE(published_at, created_at) DESC LIMIT 1",
            ['pa' => $post['published_at'], 'ca' => $post['created_at']]
        );
        $next = Database::selectOne(
            "SELECT id, title, slug FROM posts WHERE status = 'published'
             AND COALESCE(published_at, created_at) > COALESCE(:pa, :ca)
             ORDER BY COALESCE(published_at, created_at) ASC LIMIT 1",
            ['pa' => $post['published_at'], 'ca' => $post['created_at']]
        );
        $tags = Database::select(
            'SELECT t.id, t.name, t.slug FROM tags t
             JOIN post_tags pt ON pt.tag_id = t.id
             WHERE pt.post_id = :pid',
            ['pid' => $post['id']]
        );

        $settings = $this->settings();
        $base = $this->baseUrl();
        $canonical = $base . '/post/' . rawurlencode((string) $post['slug']);
        $siteName = (string) ($settings['site_name'] ?? 'ycms2.0');
        $description = mb_substr(trim((string) ($post['summary'] ?? '')), 0, 160);
        if ($description === '') {
            $description = mb_substr(strip_tags(Markdown::toHtml((string) $post['content'])), 0, 160);
        }

        $keywords = array_filter([
            (string) $post['title'],
            (string) ($post['category_name'] ?? ''),
            ...array_map(static fn (array $t): string => (string) $t['name'], $tags),
            (string) ($settings['site_keywords'] ?? ''),
        ]);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => (string) $post['title'],
            'description' => $description,
            'image' => $post['cover_image'] ? [$this->absoluteUrl((string) $post['cover_image'], $base)] : [],
            'datePublished' => (string) ($post['published_at'] ?: $post['created_at']),
            'dateModified' => (string) ($post['updated_at'] ?: $post['created_at']),
            'author' => ['@type' => 'Person', 'name' => (string) ($post['author_name'] ?: ($settings['owner_name'] ?? ''))],
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'articleSection' => (string) ($post['category_name'] ?? ''),
            'keywords' => implode(', ', $keywords),
            'wordCount' => mb_strlen(strip_tags((string) $post['content'])),
        ];

        $breadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $base . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => '文章', 'item' => $base . '/posts'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => (string) $post['title'], 'item' => $canonical],
            ],
        ];

        $content = $this->renderTemplate('post', $this->postTemplateData($post, $tags, $related, $prev, $next, $base));

        return $this->renderPage($content, [
            'title' => (string) $post['title'] . ' · ' . $siteName,
            'description' => $description,
            'keywords' => implode(', ', $keywords),
            'canonical' => $canonical,
            'image' => $post['cover_image'] ? $this->absoluteUrl((string) $post['cover_image'], $base) : '',
            'type' => 'article',
            'publishedAt' => (string) ($post['published_at'] ?: $post['created_at']),
            'author' => (string) ($post['author_name'] ?: ($settings['owner_name'] ?? '')),
            'jsonLd' => [$jsonLd, $breadcrumb],
        ]);
    }

    /**
     * 生成页面页 HTML（独立页面，如「关于我」）
     */
    public function buildPagePage(string $slug): ?string
    {
        $slug = urldecode($slug);
        $page = Database::selectOne(
            "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1",
            ['slug' => $slug]
        );
        if ($page === null) {
            return null;
        }

        $settings = $this->settings();
        $base = $this->baseUrl();
        $siteName = (string) ($settings['site_name'] ?? 'ycms2.0');
        $canonical = $base . '/page/' . rawurlencode((string) $page['slug']);
        $description = mb_substr(strip_tags(Markdown::toHtml((string) $page['content'])), 0, 160);

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $content = '<article class="max-w-3xl mx-auto px-5 pt-28 pb-10">'
            . '<header class="mb-10"><h1 class="font-serif text-3xl md:text-4xl font-bold text-slate-100">'
            . $e((string) $page['title']) . '</h1></header>'
            . '<div class="glass-medium p-7 md:p-10"><div class="markdown-body">'
            . Markdown::toHtml((string) $page['content']) . '</div></div></article>';

        return $this->renderPage($content, [
            'title' => (string) $page['title'] . ' · ' . $siteName,
            'description' => $description,
            'canonical' => $canonical,
            'type' => 'website',
        ]);
    }

    /**
     * 生成站点地图 XML 文本（供静态化使用）
     */
    public function buildSitemapXml(): string
    {
        $base = $this->baseUrl();
        $urls = [
            ['loc' => $base . '/', 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => date('Y-m-d')],
            ['loc' => $base . '/posts', 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => date('Y-m-d')],
            ['loc' => $base . '/archive', 'priority' => '0.6', 'changefreq' => 'weekly', 'lastmod' => date('Y-m-d')],
            ['loc' => $base . '/links', 'priority' => '0.5', 'changefreq' => 'monthly', 'lastmod' => date('Y-m-d')],
        ];

        foreach (Database::select("SELECT slug, updated_at, published_at, created_at FROM posts WHERE status = 'published' ORDER BY id DESC") as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $urls[] = [
                'loc' => $base . '/post/' . rawurlencode($slug),
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'lastmod' => substr((string) ($row['updated_at'] ?: ($row['published_at'] ?: $row['created_at'])), 0, 10),
            ];
        }

        foreach (Database::select("SELECT slug FROM pages WHERE status = 'published'") as $row) {
            if ((string) ($row['slug'] ?? '') === '') {
                continue;
            }
            $urls[] = [
                'loc' => $base . '/page/' . rawurlencode((string) $row['slug']),
                'priority' => '0.5',
                'changefreq' => 'monthly',
                'lastmod' => date('Y-m-d'),
            ];
        }

        foreach (Database::select('SELECT id FROM categories') as $row) {
            $urls[] = ['loc' => $base . '/category/' . (int) $row['id'], 'priority' => '0.6', 'changefreq' => 'weekly', 'lastmod' => date('Y-m-d')];
        }
        foreach (Database::select('SELECT id FROM tags') as $row) {
            $urls[] = ['loc' => $base . '/tag/' . (int) $row['id'], 'priority' => '0.5', 'changefreq' => 'weekly', 'lastmod' => date('Y-m-d')];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return $xml;
    }

    /**
     * 生成 robots.txt 文本（供静态化使用）
     */
    public function buildRobotsTxt(): string
    {
        $base = $this->baseUrl();
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api.php',
            'Disallow: /api/',
            'Disallow: /storage/',
            'Disallow: /install.php',
            '',
            'Sitemap: ' . $base . '/sitemap.xml',
            '',
        ]);
    }

    /**
     * 站点地图
     */
    public function sitemap(): void
    {
        $base = $this->baseUrl();
        $settings = $this->settings();

        $urls = [];
        $urls[] = [
            'loc' => $base . '/',
            'priority' => '1.0',
            'changefreq' => 'daily',
            'lastmod' => date('Y-m-d'),
        ];
        $urls[] = [
            'loc' => $base . '/posts',
            'priority' => '0.9',
            'changefreq' => 'daily',
            'lastmod' => date('Y-m-d'),
        ];
        $urls[] = [
            'loc' => $base . '/archive',
            'priority' => '0.6',
            'changefreq' => 'weekly',
            'lastmod' => date('Y-m-d'),
        ];
        $urls[] = [
            'loc' => $base . '/links',
            'priority' => '0.5',
            'changefreq' => 'monthly',
            'lastmod' => date('Y-m-d'),
        ];

        foreach (Database::select("SELECT slug, updated_at, published_at, created_at FROM posts WHERE status = 'published' ORDER BY id DESC") as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $urls[] = [
                'loc' => $base . '/post/' . rawurlencode($slug),
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'lastmod' => substr((string) ($row['updated_at'] ?: ($row['published_at'] ?: $row['created_at'])), 0, 10),
            ];
        }

        foreach (Database::select('SELECT id, name FROM categories') as $row) {
            $urls[] = [
                'loc' => $base . '/category/' . (int) $row['id'],
                'priority' => '0.6',
                'changefreq' => 'weekly',
                'lastmod' => date('Y-m-d'),
            ];
        }

        foreach (Database::select('SELECT id, name FROM tags') as $row) {
            $urls[] = [
                'loc' => $base . '/tag/' . (int) $row['id'],
                'priority' => '0.5',
                'changefreq' => 'weekly',
                'lastmod' => date('Y-m-d'),
            ];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $xml;
        exit;
    }

    /**
     * 重建静态页（后台手动触发）
     */
    public function rebuildStatic(\App\Core\Request $request): void
    {
        \App\Support\Auth::requireAdmin($request);
        $result = \App\Support\StaticBuilder::rebuild();
        if ($result['ok']) {
            \App\Core\Response::success($result, $result['message']);
        }
        \App\Core\Response::error($result['message'], 422);
    }

    /**
     * robots.txt
     */
    public function robots(): void
    {
        $base = $this->baseUrl();
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api/',
            'Disallow: /storage/',
            '',
            'Sitemap: ' . $base . '/sitemap.xml',
            '',
        ];
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo implode("\n", $lines);
        exit;
    }

    /* ==================== 内部方法 ==================== */

    /**
     * 组装页面（复用前端构建产物模板，注入 SEO 信息与内容）
     * @param array<string, mixed> $meta
     */
    private function renderPage(string $content, array $meta): string
    {
        $template = $this->template();
        $head = [];

        $title = (string) ($meta['title'] ?? 'ycms2.0');
        $description = (string) ($meta['description'] ?? '');
        $keywords = (string) ($meta['keywords'] ?? '');
        $canonical = (string) ($meta['canonical'] ?? '');
        $image = (string) ($meta['image'] ?? '');
        $type = (string) ($meta['type'] ?? 'website');
        $robots = (string) ($meta['robots'] ?? 'index, follow');
        $publishedAt = (string) ($meta['publishedAt'] ?? '');
        $author = (string) ($meta['author'] ?? '');

        $head[] = '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        $head[] = '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '" />';
        if ($keywords !== '') {
            $head[] = '<meta name="keywords" content="' . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . '" />';
        }
        $head[] = '<meta name="robots" content="' . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . '" />';
        if ($author !== '') {
            $head[] = '<meta name="author" content="' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '" />';
        }
        $head[] = '<meta property="og:type" content="' . $type . '" />';
        $head[] = '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" />';
        $head[] = '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '" />';
        if ($canonical !== '') {
            $head[] = '<meta property="og:url" content="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '" />';
            $head[] = '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '" />';
        }
        if ($image !== '') {
            $head[] = '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" />';
        }
        $head[] = '<meta name="twitter:card" content="summary_large_image" />';
        $head[] = '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" />';
        $head[] = '<meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '" />';
        if ($publishedAt !== '') {
            $head[] = '<meta property="article:published_time" content="' . htmlspecialchars($publishedAt, ENT_QUOTES, 'UTF-8') . '" />';
        }

        foreach (($meta['jsonLd'] ?? []) as $data) {
            $head[] = '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
        }

        $headHtml = implode("\n    ", $head);

        // 替换模板 head 中的默认 title / description
        $html = preg_replace('/<title>.*?<\/title>/s', '', $template, 1) ?? $template;
        $html = preg_replace('/<meta name="description"[^>]*>/', '', $html, 1) ?? $html;
        $html = str_replace('</head>', '    ' . $headHtml . "\n  </head>", $html);

        // 注入服务端渲染内容（Vue 启动后会被接管）
        $html = str_replace('<div id="app"></div>', '<div id="app">' . $content . '</div>', $html);

        return $html;
    }

    /**
     * 文章模板数据（严格对齐主题契约，模板只读取这些字段）
     *
     * @param array<string, mixed> $post
     * @param array<int, array<string, mixed>> $tags
     * @param array<int, array<string, mixed>> $related
     * @param array<string, mixed>|null $prev
     * @param array<string, mixed>|null $next
     * @return array<string, mixed>
     */
    private function postTemplateData(
        array $post,
        array $tags,
        array $related,
        ?array $prev,
        ?array $next,
        string $base
    ): array {
        $hasCategory = !empty($post['category_id']);
        $summary = trim((string) ($post['summary'] ?? ''));

        $tagItems = [];
        foreach ($tags as $tag) {
            $tagItems[] = [
                'name' => (string) $tag['name'],
                'url' => '/tag/' . (int) $tag['id'],
            ];
        }

        $relatedItems = [];
        foreach ($related as $item) {
            $itemSummary = trim((string) ($item['summary'] ?? ''));
            $relatedItems[] = [
                'title' => (string) $item['title'],
                'url' => '/post/' . rawurlencode((string) $item['slug']),
                'summary' => $itemSummary,
                'has_summary' => $itemSummary !== '',
                'category_name' => (string) ($item['category_name'] ?? '未分类'),
                'views' => (int) $item['views'],
            ];
        }

        return [
            'site_name' => (string) ($this->settings()['site_name'] ?? 'ycms2.0'),
            'title' => (string) $post['title'],
            'summary' => $summary,
            'has_summary' => $summary !== '',
            'content' => Markdown::toHtml((string) $post['content']),
            'published_at' => substr((string) ($post['published_at'] ?: $post['created_at']), 0, 10),
            'views' => (int) $post['views'],
            'author' => (string) ($post['author_name'] ?? ''),
            'has_author' => (string) ($post['author_name'] ?? '') !== '',
            'category_name' => (string) ($post['category_name'] ?? '未分类'),
            'category_url' => $hasCategory ? '/category/' . (int) $post['category_id'] : '',
            'has_category' => $hasCategory,
            'tags' => $tagItems,
            'has_tags' => $tagItems !== [],
            'cover_url' => (string) ($post['cover_image'] ?? ''),
            'has_cover' => (string) ($post['cover_image'] ?? '') !== '',
            'prev_title' => (string) ($prev['title'] ?? ''),
            'prev_url' => $prev !== null ? '/post/' . rawurlencode((string) $prev['slug']) : '',
            'has_prev' => $prev !== null,
            'next_title' => (string) ($next['title'] ?? ''),
            'next_url' => $next !== null ? '/post/' . rawurlencode((string) $next['slug']) : '',
            'has_next' => $next !== null,
            'has_prev_next' => $prev !== null || $next !== null,
            'related' => $relatedItems,
            'has_related' => $relatedItems !== [],
            'base_url' => $base,
        ];
    }

    /**
     * 渲染主题模板：主题缺失模板时回退到 backend/resources/fallback-templates
     *
     * @param array<string, mixed> $data
     */
    private function renderTemplate(string $name, array $data): string
    {
        $template = ThemeManager::template($name);
        if ($template === null) {
            return '';
        }
        return Template::render($template, $data);
    }

    /**
     * 相关文章查询
     *
     * @return array<int, array<string, mixed>>
     */
    private function relatedPosts(int $postId, mixed $categoryId, int $limit = 6): array
    {
        return Database::select(
            "SELECT p.id, p.title, p.slug, p.summary, p.cover_image, p.views, c.name AS category_name,
                    (SELECT COUNT(*) FROM post_tags pt2
                     WHERE pt2.post_id = p.id
                       AND pt2.tag_id IN (SELECT tag_id FROM post_tags WHERE post_id = :pid)
                    ) AS tag_score
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'published' AND p.id <> :pid
             ORDER BY tag_score DESC, (CASE WHEN p.category_id = :cat THEN 1 ELSE 0 END) DESC,
                      p.views DESC, COALESCE(p.published_at, p.created_at) DESC
             LIMIT :limit",
            ['pid' => $postId, 'cat' => $categoryId, 'limit' => $limit]
        );
    }

    /**
     * 站点设置
     *
     * @return array<string, string>
     */
    private function settings(): array
    {
        $map = [];
        foreach (Database::select('SELECT key, value FROM settings') as $row) {
            $map[(string) $row['key']] = (string) $row['value'];
        }
        return $map;
    }

    /**
     * 站点根地址（用于 canonical / sitemap）
     */
    private function baseUrl(): string
    {
        $https = ($_SERVER['HTTPS'] ?? 'off') !== 'off'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $scheme = $https ? 'https' : 'http';

        $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            $host = (string) (parse_url((string) config('app.url', 'http://localhost:8080'), PHP_URL_HOST) ?: 'localhost');
        }
        $host = preg_replace('/[^A-Za-z0-9\.\-\:\[\]]/', '', $host) ?: 'localhost';

        return $scheme . '://' . $host;
    }

    /**
     * 绝对地址
     */
    private function absoluteUrl(string $path, string $base): string
    {
        if ($path === '') {
            return $base;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * 读取当前主题外壳（激活时已复制为 public/index.html）
     */
    private function template(): string
    {
        if (self::$template !== null) {
            return self::$template;
        }

        $shell = ThemeManager::shell();
        if ($shell !== null) {
            return self::$template = $shell;
        }

        // 兜底模板：主题缺失时保证接口不 500
        return self::$template = '<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0" />'
            . '<title>ycms2.0</title></head><body><div id="app"></div></body></html>';
    }

    /**
     * 输出 HTML
     */
    private function output(string $html): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        echo $html;
        exit;
    }
}

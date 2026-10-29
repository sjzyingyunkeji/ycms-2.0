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

/**
 * 站点入口（网站根目录）
 *
 * 零配置设计：
 *  - /api.php/xxx  → 真实文件，不需要任何 Nginx 重写规则
 *  - /admin/       → 静态目录（后台 hash 路由）
 *  - /post/xxx/    → 发布文章时生成的静态页（含 SEO 元信息）
 *  - 其余路径      → 输出前台 SPA 外壳
 */

$root = dirname(__DIR__);
$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

// 未安装 → 引导到安装向导
if (!is_file($root . '/backend/storage/install.lock')) {
    if ($uri !== '/install.php') {
        header('Location: /install.php', true, 302);
        exit;
    }
}

// 兼容 /api/xxx 形式（需服务器支持重写；未配置时前端使用 /api.php/xxx）
if ($uri === '/api' || str_starts_with($uri, '/api/')) {
    require $root . '/backend/public/index.php';
    return;
}

// 前台 SPA 外壳
$shell = __DIR__ . '/index.html';
if (!is_file($shell)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>前端资源缺失</h1><p>请先在 frontend 目录执行 <code>npm run build</code> 生成静态资源。</p>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
readfile($shell);

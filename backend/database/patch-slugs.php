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
 * 补丁：为缺少 slug 的文章补全可读地址（SEO 友好 URL，幂等）
 */

require_once dirname(__DIR__) . '/src/Support/helpers.php';
require_once dirname(__DIR__) . '/src/Support/env.php';
require_once dirname(__DIR__) . '/src/Core/Database.php';

use App\Core\Database;

$rows = Database::select("SELECT id, title FROM posts WHERE slug IS NULL OR slug = ''");
foreach ($rows as $row) {
    $title = (string) $row['title'];
    $base = slugify($title);
    if ($base === 'untitled') {
        $base = 'post-' . (int) $row['id'];
    }
    $slug = $base . '-' . (int) $row['id'];
    Database::update('posts', ['slug' => $slug, 'updated_at' => now()], 'id = :id', ['id' => (int) $row['id']]);
    echo '补全 slug: ' . $slug . PHP_EOL;
}

echo '共处理 ' . count($rows) . ' 篇文章' . PHP_EOL;

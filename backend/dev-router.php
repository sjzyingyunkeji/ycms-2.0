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
 * PHP 内置服务器路由脚本（仅开发环境使用）
 *
 * 用法：php -S localhost:8080 -t public dev-router.php
 * 作用：静态文件直出，其余请求（含 /post/xxx、/sitemap.xml、/robots.txt）统一交给 index.php
 */

// 以 -t 指定的站点根目录为准，避免脚本位置影响路径解析
$publicDir = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
if ($publicDir === '') {
    $publicDir = dirname(__DIR__) . '/public';
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = $publicDir . $path;

// API 请求（/api.php/xxx 依赖 PATH_INFO）交给 api.php
if ($path === '/api.php' || str_starts_with($path, '/api.php/')) {
    require $publicDir . '/api.php';
    return true;
}

// 存在的静态文件直接返回（交给内置服务器处理）
if ($path !== '/' && is_file($file)) {
    return false;
}

// 目录请求（如 /admin/）交给内置服务器返回其 index.html
if ($path !== '/' && is_file(rtrim($file, '/') . '/index.html')) {
    return false;
}

require $publicDir . '/index.php';

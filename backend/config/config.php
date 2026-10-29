<?php

/*
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


/**
 * 应用配置
 */
return [
    'app' => [
        'name' => env('APP_NAME', 'ycms2.0'),
        'env' => env('APP_ENV', 'production'),
        'debug' => env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost:8080'),
        'timezone' => 'Asia/Shanghai',
    ],
    'database' => [
        'driver' => 'sqlite',
        'path' => dirname(__DIR__) . '/storage/database.sqlite',
    ],
    'jwt' => [
        'secret' => env('APP_KEY', 'stardust-cms-secret-key-change-me'),
        'ttl' => 60 * 60 * 24, // 24 小时
    ],
    'upload' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        // SVG 可内嵌脚本、同源直出会造成 XSS，默认不接收（如需图标请用 PNG/WebP）
        'allowed_mimes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain', 'text/markdown',
        ],
    ],
    // 可选模块（能力分层）：开源版为空数组，Pro 发行包写入商业模块
    'modules' => require __DIR__ . '/modules.php',
];

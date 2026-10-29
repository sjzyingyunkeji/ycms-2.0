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
 * 前端控制器：所有请求入口
 */

use App\App;

// 自动加载：优先使用 composer 生成的，否则使用简易 autoloader
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = dirname(__DIR__) . '/src/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    require dirname(__DIR__) . '/src/Support/helpers.php';
    require dirname(__DIR__) . '/src/Support/env.php';
}

App::bootstrap();

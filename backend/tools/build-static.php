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
 * 命令行重建静态页
 * 用法：php tools/build-static.php
 */

$backend = dirname(__DIR__);

require_once $backend . '/src/Support/helpers.php';
require_once $backend . '/src/Support/env.php';
spl_autoload_register(static function (string $class) use ($backend): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = $backend . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// CLI 环境下补齐站点地址
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

$result = \App\Support\StaticBuilder::rebuild();
echo ($result['ok'] ? 'OK' : 'FAIL') . ' - ' . $result['message'] . ' (' . $result['files'] . ' 个文件)' . PHP_EOL;

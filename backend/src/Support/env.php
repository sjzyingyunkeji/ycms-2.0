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
 * 环境变量读取辅助函数
 */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        static $loaded = false;
        static $values = [];

        if (!$loaded) {
            $path = dirname(__DIR__) . '/.env';
            if (is_file($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
                    $values[trim($name)] = trim($value, " \t\"'");
                }
            }
            $loaded = true;
        }

        return $values[$key] ?? $default;
    }
}

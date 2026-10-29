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
 * 主题安装与切换（Pro 专属，不随开源发行包分发）
 *
 * 用法：
 *   php tools/theme-pro.php verify <theme.zip>
 *   php tools/theme-pro.php install <theme.zip>
 *   php tools/theme-pro.php activate <slug>
 *   php tools/theme-pro.php list
 */

use App\Pro\Theme\ThemeInstaller;
use App\Pro\Theme\ThemeSwitcher;
use App\Support\Theme\ThemeManager;

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

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

$command = $argv[1] ?? 'list';
$argument = $argv[2] ?? '';
$line = static fn (string $text = ''): string => $text . PHP_EOL;

switch ($command) {
    case 'list':
        $active = ThemeManager::active();
        echo $line('已安装主题（* 为当前主题）：');
        foreach (ThemeManager::installed() as $slug => $meta) {
            echo $line(sprintf(
                '  %s %-12s %-16s v%s',
                $slug === $active ? '*' : ' ',
                $slug,
                (string) ($meta['name'] ?? ''),
                (string) ($meta['version'] ?? '')
            ));
        }
        break;

    case 'verify':
        if ($argument === '') {
            echo $line('用法：php tools/theme-pro.php verify <theme.zip>');
            exit(1);
        }
        $result = ThemeInstaller::verify($argument);
        echo $line(($result['ok'] ? 'OK' : 'FAIL') . ' - ' . $result['message']);
        if ($result['ok'] && is_array($result['meta'])) {
            echo $line('slug: ' . (string) $result['slug']);
            echo $line('version: ' . (string) ($result['meta']['version'] ?? ''));
        }
        exit($result['ok'] ? 0 : 1);

    case 'install':
        if ($argument === '') {
            echo $line('用法：php tools/theme-pro.php install <theme.zip>');
            exit(1);
        }
        $result = ThemeInstaller::install($argument);
        echo $line(($result['ok'] ? 'OK' : 'FAIL') . ' - ' . $result['message']);
        exit($result['ok'] ? 0 : 1);

    case 'activate':
        if ($argument === '') {
            echo $line('用法：php tools/theme-pro.php activate <slug>');
            exit(1);
        }
        $result = ThemeSwitcher::activate($argument);
        echo $line(($result['ok'] ? 'OK' : 'FAIL') . ' - ' . $result['message']);
        exit($result['ok'] ? 0 : 1);

    default:
        echo $line('可用命令：list | verify <zip> | install <zip> | activate <slug>');
        exit(1);
}

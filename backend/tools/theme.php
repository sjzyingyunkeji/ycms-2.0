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
 * 主题基础命令行（内核能力，永久保留）
 *
 * 只有「读取 / 校验 / 构建」：主题包安装与运行时切换属于 Pro 能力。
 *
 * 用法：
 *   php tools/theme.php list
 *   php tools/theme.php current
 *   php tools/theme.php build          重建全部静态页
 *   php tools/theme.php migrate [slug] 把老版本平铺产物包装为 public/themes/<slug>/（默认 default）
 */

use App\Support\StaticBuilder;
use App\Support\Theme\LegacyMigrator;
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
$line = static fn (string $text = ''): string => $text . PHP_EOL;

switch ($command) {
    case 'list':
        $active = ThemeManager::active();
        echo $line('已安装主题（* 为当前主题）：');
        $installed = ThemeManager::installed();
        if ($installed === []) {
            echo $line('  （未发现 public/themes/<slug>/theme.json）');
            break;
        }
        foreach ($installed as $slug => $meta) {
            echo $line(sprintf(
                '  %s %-12s %-16s v%s',
                $slug === $active ? '*' : ' ',
                $slug,
                (string) ($meta['name'] ?? ''),
                (string) ($meta['version'] ?? '')
            ));
        }
        break;

    case 'current':
        $state = ThemeManager::state();
        echo $line('当前主题：' . ThemeManager::active());
        echo $line('上一主题：' . ($state['previous'] ?? '无'));
        echo $line('记录时间：' . ($state['activated_at'] !== '' ? $state['activated_at'] : '未知'));
        echo $line('Core 版本：' . ThemeManager::coreVersion());
        break;

    case 'build':
        $result = StaticBuilder::rebuild();
        echo $line(($result['ok'] ? 'OK' : 'FAIL') . ' - ' . $result['message']
            . ' (' . (int) ($result['files'] ?? 0) . ' 个文件)');
        exit($result['ok'] ? 0 : 1);

    case 'migrate':
        $slug = $argv[2] ?? 'default';
        echo $line('迁移目标 slug：' . $slug);
        if (!LegacyMigrator::needed()) {
            echo $line('当前已有可用主题目录，无需迁移。');
            exit(0);
        }
        $report = LegacyMigrator::run($slug, true);
        echo $line(($report['ok'] ? 'OK' : 'FAIL') . ' - ' . $report['message']);
        foreach ($report['notes'] as $note) {
            echo $line('  · ' . $note);
        }
        exit($report['ok'] ? 0 : 1);

    default:
        echo $line('可用命令：list | current | build | migrate');
        echo $line('主题包安装与运行时切换属于 Pro 能力，不包含在开源发行版中。');
        exit(1);
}

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

namespace App\Pro\Theme;

use App\Support\StaticBuilder;
use App\Support\Theme\ThemeManager;

/**
 * 主题运行时切换（Pro 能力，不随开源发行包分发）
 *
 * 流程：写状态 → 复制主题外壳为 public/index.html → 全量重建静态页，失败自动回滚。
 */
final class ThemeSwitcher
{
    /**
     * @return array{ok:bool, message:string}
     */
    public static function activate(string $slug): array
    {
        if (!ThemeManager::isInstalled($slug)) {
            return ['ok' => false, 'message' => '主题未安装或缺少 theme.json / index.html：' . $slug];
        }

        $themeShell = ThemeManager::themesDir() . '/' . $slug . '/index.html';
        $target = ThemeManager::publicDir() . '/index.html';
        $previousState = ThemeManager::state();
        $backup = is_file($target) ? (string) @file_get_contents($target) : null;

        if (!@copy($themeShell, $target)) {
            return ['ok' => false, 'message' => '无法写入站点入口 index.html，请检查 public/ 目录权限'];
        }

        self::writeState([
            'active' => $slug,
            'previous' => $previousState['active'],
            'activated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = StaticBuilder::rebuild();
        if (($result['ok'] ?? false) !== true) {
            if ($backup !== null) {
                @file_put_contents($target, $backup);
            }
            self::writeState($previousState);
            return ['ok' => false, 'message' => '静态页重建失败，已回滚：' . (string) ($result['message'] ?? '')];
        }

        return ['ok' => true, 'message' => '主题已切换：' . $slug];
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function writeState(array $state): void
    {
        $file = ThemeManager::storageFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $file,
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}

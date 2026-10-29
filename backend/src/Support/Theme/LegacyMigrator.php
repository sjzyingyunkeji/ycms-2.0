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

namespace App\Support\Theme;

use App\Support\StaticBuilder;
use Throwable;

/**
 * 老站点产物迁移（内核能力，永久保留）
 *
 * 背景：2.0.x 早期版本把前台构建产物直接平铺在 public/ 下
 *       （public/index.html + public/assets/），没有主题目录。
 *       主题化之后运行时只认 public/themes/<slug>/，平铺结构会白屏。
 *
 * 本类做的事（幂等，可重复执行）：
 *   1. 把 public/index.html 与平铺的资源目录搬进 public/themes/<slug>/
 *   2. 重写外壳里的绝对资源路径 /assets/ → /themes/<slug>/assets/
 *   3. 补齐 theme.json 与 SSR 模板（缺失时从内核兜底模板复制）
 *   4. 把外壳复制回 public/index.html，保证老 URL 立即可用
 *   5. 写入 backend/storage/theme.json 激活状态
 *   6. 可选：全量重建静态页
 *
 * 不涉及主题包安装/切换（那是 Pro 能力），只在「没有主题目录」时做一次性包装。
 */
final class LegacyMigrator
{
    /** 平铺资源目录候选（老构建产物可能用到的名字） */
    private const LEGACY_DIRS = ['assets', 'static', 'js', 'css', 'images', 'img', 'fonts'];

    /**
     * 是否需要迁移：public/themes/ 下没有任何可用主题，但存在老的 public/index.html
     */
    public static function needed(): bool
    {
        if (ThemeManager::installed() !== []) {
            return false;
        }
        return is_file(ThemeManager::publicDir() . '/index.html');
    }

    /**
     * 执行迁移
     *
     * @return array{ok:bool, migrated:bool, message:string, slug:string, moved:string[], rewritten:int, notes:string[]}
     */
    public static function run(string $slug = 'default', bool $rebuild = true): array
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,31}$/', $slug)) {
            return self::result(false, false, '主题 slug 不合法：' . $slug, '', [], 0, []);
        }

        ThemeManager::protectThemesDir();

        $public = ThemeManager::publicDir();
        $themesDir = ThemeManager::themesDir();
        $target = $themesDir . '/' . $slug;
        $shellFile = $public . '/index.html';

        // 幂等：已有主题目录则直接退出
        $installed = ThemeManager::installed();
        if ($installed !== []) {
            return self::result(true, false, '已存在主题目录（' . implode('、', array_keys($installed)) . '），无需迁移', $slug, [], 0, []);
        }

        if (!is_file($shellFile)) {
            return self::result(false, false, '未找到 public/index.html，无法迁移', $slug, [], 0, []);
        }

        $html = (string) @file_get_contents($shellFile);
        if ($html === '') {
            return self::result(false, false, 'public/index.html 为空，无法迁移', $slug, [], 0, []);
        }

        $notes = [];
        $moved = [];

        if (!is_dir($target) && !@mkdir($target, 0775, true) && !is_dir($target)) {
            return self::result(false, false, '无法创建主题目录 public/themes/' . $slug, $slug, [], 0, []);
        }

        // 1) 平铺资源目录 → 主题目录
        foreach (self::LEGACY_DIRS as $dir) {
            $from = $public . '/' . $dir;
            if (!is_dir($from)) {
                continue;
            }
            if (self::moveDir($from, $target . '/' . $dir)) {
                $moved[] = $dir;
            }
        }

        // 2) 外壳 → 主题目录，并重写绝对资源路径
        $rewritten = 0;
        foreach ($moved as $dir) {
            $count = 0;
            $html = str_replace(
                ['"/' . $dir . '/', "'/" . $dir . '/', 'url(/' . $dir . '/'],
                ['"/themes/' . $slug . '/' . $dir . '/', "'/themes/" . $slug . '/' . $dir . '/', 'url(/themes/' . $slug . '/' . $dir . '/'],
                $html,
                $count
            );
            $rewritten += $count;
        }
        if (!@file_put_contents($target . '/index.html', $html)) {
            return self::result(false, false, '写入主题外壳失败：public/themes/' . $slug . '/index.html', $slug, $moved, $rewritten, $notes);
        }

        // 3) theme.json
        $meta = [
            'slug' => $slug,
            'name' => '默认主题',
            'version' => ThemeManager::coreVersion(),
            'author' => '影云网络科技（石家庄）有限公司',
            'description' => '由老版本平铺产物自动迁移生成的主题包结构',
            'license' => 'AGPL-3.0',
            'requires' => ['core' => '>=' . ThemeManager::coreVersion()],
            'entry' => 'index.html',
            'templates' => 'templates',
            'preview' => 'preview.jpg',
            'supports' => ['dark_mode' => false, 'custom_css' => false],
            'settings_schema' => [],
        ];
        @file_put_contents($target . '/theme.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $notes[] = '已生成 public/themes/' . $slug . '/theme.json';

        // 4) SSR 模板：主题缺模板时复制内核兜底模板，保证主题包自包含
        $tplTarget = $target . '/templates';
        $fallback = ThemeManager::fallbackTemplatesDir();
        if (!is_dir($tplTarget) && is_dir($fallback) && @mkdir($tplTarget, 0775, true)) {
            $copied = 0;
            foreach (['post', 'page', '404'] as $name) {
                $src = $fallback . '/' . $name . '.html';
                if (is_file($src) && @copy($src, $tplTarget . '/' . $name . '.html')) {
                    $copied++;
                }
            }
            if ($copied > 0) {
                $notes[] = '已从内核兜底模板复制 ' . $copied . ' 个 SSR 模板';
            }
        }

        // 5) 外壳回写站点根入口（老 URL 立即生效）
        if (!@file_put_contents($shellFile, $html)) {
            $notes[] = '警告：public/index.html 回写失败，请检查目录权限';
        }

        // 6) 激活状态
        @file_put_contents(ThemeManager::storageFile(), json_encode([
            'active' => $slug,
            'activated_at' => date('Y-m-d H:i:s'),
            'previous' => null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        // 7) 重建静态页
        if ($rebuild) {
            try {
                $result = StaticBuilder::rebuild();
                $notes[] = ($result['ok'] ? '静态页已重建' : '静态页重建失败：' . (string) ($result['message'] ?? ''))
                    . '（' . (int) ($result['files'] ?? 0) . ' 个文件）';
            } catch (Throwable $e) {
                $notes[] = '静态页重建异常：' . $e->getMessage();
            }
        }

        $message = '已把老版本平铺产物迁移为主题目录 public/themes/' . $slug
            . ($moved === [] ? '（未发现平铺资源目录）' : '（搬运目录：' . implode('、', $moved) . '）')
            . ($rewritten > 0 ? '，重写资源路径 ' . $rewritten . ' 处' : '');

        return self::result(true, true, $message, $slug, $moved, $rewritten, $notes);
    }

    /**
     * @param string[] $moved
     * @param string[] $notes
     * @return array{ok:bool, migrated:bool, message:string, slug:string, moved:string[], rewritten:int, notes:string[]}
     */
    private static function result(bool $ok, bool $migrated, string $message, string $slug, array $moved, int $rewritten, array $notes): array
    {
        return [
            'ok' => $ok,
            'migrated' => $migrated,
            'message' => $message,
            'slug' => $slug,
            'moved' => $moved,
            'rewritten' => $rewritten,
            'notes' => $notes,
        ];
    }

    /**
     * 移动目录（跨盘失败时退化为递归复制 + 删除）
     */
    private static function moveDir(string $from, string $to): bool
    {
        if (is_dir($to)) {
            return false;
        }
        if (@rename($from, $to)) {
            return true;
        }
        if (!self::copyDir($from, $to)) {
            return false;
        }
        self::removeDir($from);
        return true;
    }

    private static function copyDir(string $from, string $to): bool
    {
        if (!is_dir($to) && !@mkdir($to, 0775, true) && !is_dir($to)) {
            return false;
        }
        $ok = true;
        foreach (scandir($from) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $src = $from . '/' . $name;
            $dst = $to . '/' . $name;
            $ok = is_dir($src) ? self::copyDir($src, $dst) : (bool) @copy($src, $dst);
            if (!$ok) {
                return false;
            }
        }
        return $ok;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            is_dir($path) ? self::removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}

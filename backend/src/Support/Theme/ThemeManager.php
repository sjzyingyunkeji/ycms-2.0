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

/**
 * 主题管理器 —— 内核基础能力（永久保留）
 *
 * 职责边界（能力分层，勿混用）：
 *  - 本类：读取「指定主题」的元数据 / 外壳 / SSR 模板，为渲染与静态构建提供输入
 *  - Pro（App\Pro\Theme）：主题包 zip 安装、运行时切换、失败回滚
 *
 * 开源发行产物物理上不含 App\Pro，因此本类不得引用任何 Pro 类型。
 *
 * 目录约定：
 *  - 主题产物安装目录：public/themes/<slug>/
 *  - 当前主题记录：backend/storage/theme.json（只读解析；写入由 Pro 完成）
 *  - 站点入口：public/index.html（构建期或 Pro 切换时写入）
 */
final class ThemeManager
{
    /** 缓存激活主题，避免单次请求重复读文件 */
    private static ?string $active = null;

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    public static function publicDir(): string
    {
        return self::projectRoot() . '/public';
    }

    public static function themesDir(): string
    {
        return self::publicDir() . '/themes';
    }

    public static function storageFile(): string
    {
        return self::projectRoot() . '/backend/storage/theme.json';
    }

    public static function fallbackTemplatesDir(): string
    {
        return self::projectRoot() . '/backend/resources/fallback-templates';
    }

    /**
     * 当前 Core 版本（读取根目录 VERSION）
     */
    public static function coreVersion(): string
    {
        $file = self::projectRoot() . '/VERSION';
        $version = is_file($file) ? trim((string) @file_get_contents($file)) : '';
        return $version !== '' ? $version : '0.0.0';
    }

    /**
     * 激活状态（含 previous，便于回滚）
     * @return array{active:string, previous:?string, activated_at:string}
     */
    public static function state(): array
    {
        $file = self::storageFile();
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data) && isset($data['active']) && is_string($data['active'])) {
                return [
                    'active' => $data['active'],
                    'previous' => isset($data['previous']) && is_string($data['previous']) ? $data['previous'] : null,
                    'activated_at' => (string) ($data['activated_at'] ?? ''),
                ];
            }
        }
        return ['active' => 'default', 'previous' => null, 'activated_at' => ''];
    }

    /**
     * 当前激活主题（目录不存在时回退到任意已安装主题）
     */
    public static function active(): string
    {
        if (self::$active !== null) {
            return self::$active;
        }

        $slug = self::state()['active'];
        if (!self::isInstalled($slug)) {
            $installed = array_keys(self::installed());
            $slug = $installed[0] ?? 'default';
        }

        return self::$active = $slug;
    }

    public static function isInstalled(string $slug): bool
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,31}$/', $slug)) {
            return false;
        }
        return is_file(self::themesDir() . '/' . $slug . '/theme.json')
            && is_file(self::themesDir() . '/' . $slug . '/index.html');
    }

    /**
     * 已安装主题
     * @return array<string, array<string, mixed>>
     */
    public static function installed(): array
    {
        $dir = self::themesDir();
        if (!is_dir($dir)) {
            return [];
        }

        $list = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || !self::isInstalled($name)) {
                continue;
            }
            $meta = self::metadata($name);
            if ($meta !== null) {
                $list[$name] = $meta;
            }
        }
        ksort($list);
        return $list;
    }

    /**
     * 读取主题元数据（theme.json）
     * @return array<string, mixed>|null
     */
    public static function metadata(string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,31}$/', $slug)) {
            return null;
        }
        $file = self::themesDir() . '/' . $slug . '/theme.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || (string) ($data['slug'] ?? '') !== $slug) {
            return null;
        }
        return $data;
    }

    /**
     * 主题外壳：优先取完成激活后复制到站点根入口的副本
     */
    public static function shell(): ?string
    {
        $file = self::publicDir() . '/index.html';
        if (is_file($file)) {
            $html = @file_get_contents($file);
            if (is_string($html) && $html !== '') {
                return $html;
            }
        }
        $themeShell = self::themesDir() . '/' . self::active() . '/index.html';
        if (is_file($themeShell)) {
            $html = @file_get_contents($themeShell);
            if (is_string($html) && $html !== '') {
                return $html;
            }
        }
        return null;
    }

    /**
     * 读取 SSR 模板内容（主题优先，缺失时回退内置模板）
     */
    public static function template(string $name): ?string
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            return null;
        }
        $candidates = [
            self::themesDir() . '/' . self::active() . '/templates/' . $name . '.html',
            self::fallbackTemplatesDir() . '/' . $name . '.html',
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) {
                $content = @file_get_contents($file);
                if (is_string($content) && $content !== '') {
                    return $content;
                }
            }
        }
        return null;
    }

    /**
     * 主题包解压目录防护：禁止目录浏览与脚本执行
     */
    public static function protectThemesDir(): void
    {
        $dir = self::themesDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (is_file($htaccess)) {
            return;
        }
        $rules = "Options -Indexes\n"
            . "<FilesMatch \"\\.(php|phtml|phar|php\\d)$\">\n  Require all denied\n</FilesMatch>\n";
        @file_put_contents($htaccess, $rules);
    }
}

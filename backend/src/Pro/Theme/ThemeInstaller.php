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

use App\Support\Theme\ThemeManager;
use ZipArchive;

/**
 * 主题包安装器
 *
 * 安全约束（主题包来源不可信）：
 *  - 拒绝路径穿越、绝对路径、反斜杠、符号链接
 *  - 拒绝任何可执行/配置文件（php / phtml / phar / cgi / htaccess / env ...）
 *  - 限制解压文件数与总大小
 *  - 必含 theme.json 与 index.html，slug 必须合法且与元数据一致
 */
final class ThemeInstaller
{
    private const MAX_FILES = 3000;
    private const MAX_BYTES = 30 * 1024 * 1024;

    private const FORBIDDEN_EXT = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'cgi', 'pl', 'py', 'sh', 'bat', 'cmd', 'exe', 'dll', 'so',
        'htaccess', 'htpasswd', 'env', 'sqlite', 'lock',
    ];

    /**
     * 校验主题包（不落地）
     * @return array{ok:bool, message:string, meta:?array<string, mixed>, slug:?string}
     */
    public static function verify(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            return self::fail('服务器未启用 PHP zip 扩展（ZipArchive），无法读取主题包');
        }
        if (!is_file($zipPath)) {
            return self::fail('主题包不存在');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return self::fail('无法打开主题包（需要 zip 格式）');
        }

        try {
            $scan = self::scan($zip);
            if (!$scan['ok']) {
                return self::fail((string) $scan['message']);
            }

            $raw = $zip->getFromName('theme.json');
            if (!is_string($raw) || $raw === '') {
                return self::fail('主题包根目录缺少 theme.json');
            }
            $meta = json_decode($raw, true);
            if (!is_array($meta)) {
                return self::fail('theme.json 不是合法的 JSON');
            }

            $slug = (string) ($meta['slug'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,31}$/', $slug)) {
                return self::fail('theme.json 的 slug 不合法（仅小写字母、数字、连字符，2-32 位）');
            }

            $entry = (string) ($meta['entry'] ?? 'index.html');
            if ($entry !== 'index.html' || $zip->locateName('index.html') === false) {
                return self::fail('主题包缺少构建产物 index.html');
            }

            $required = (string) ($meta['requires']['core'] ?? '');
            if ($required !== '' && !self::satisfies(ThemeManager::coreVersion(), $required)) {
                return self::fail('主题要求 Core ' . $required . '，当前版本 ' . ThemeManager::coreVersion());
            }

            return ['ok' => true, 'message' => '校验通过', 'meta' => $meta, 'slug' => $slug];
        } finally {
            $zip->close();
        }
    }

    /**
     * 安装主题包到 public/themes/<slug>/
     * @return array{ok:bool, message:string, slug:?string}
     */
    public static function install(string $zipPath): array
    {
        $check = self::verify($zipPath);
        if ($check['ok'] !== true) {
            return ['ok' => false, 'message' => (string) $check['message'], 'slug' => null];
        }

        $slug = (string) $check['slug'];
        $themesDir = ThemeManager::themesDir();
        ThemeManager::protectThemesDir();

        $tmp = $themesDir . '/.installing-' . bin2hex(random_bytes(4));
        if (!@mkdir($tmp, 0775, true)) {
            return ['ok' => false, 'message' => '无法创建临时目录，请检查 public/themes 权限', 'slug' => null];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            self::removeDir($tmp);
            return ['ok' => false, 'message' => '无法打开主题包', 'slug' => null];
        }

        $scan = self::scan($zip);
        if (!$scan['ok']) {
            $zip->close();
            self::removeDir($tmp);
            return ['ok' => false, 'message' => (string) $scan['message'], 'slug' => null];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with($name, '/')) {
                @mkdir($tmp . '/' . rtrim($name, '/'), 0775, true);
                continue;
            }
            $target = $tmp . '/' . $name;
            if (!is_dir(dirname($target))) {
                @mkdir(dirname($target), 0775, true);
            }
            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            $out = @fopen($target, 'wb');
            if ($out !== false) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
            }
            fclose($stream);
        }
        $zip->close();

        // 解压后二次确认
        if (!is_file($tmp . '/theme.json') || !is_file($tmp . '/index.html')) {
            self::removeDir($tmp);
            return ['ok' => false, 'message' => '主题包结构不完整（需要 theme.json 与 index.html）', 'slug' => null];
        }

        $target = $themesDir . '/' . $slug;
        if (is_dir($target)) {
            $backup = $target . '.bak-' . date('YmdHis');
            if (!@rename($target, $backup)) {
                self::removeDir($tmp);
                return ['ok' => false, 'message' => '旧版本主题无法备份，请检查目录权限', 'slug' => null];
            }
        }

        if (!@rename($tmp, $target)) {
            self::removeDir($tmp);
            return ['ok' => false, 'message' => '主题落盘失败，请检查 public/themes 权限', 'slug' => null];
        }

        return ['ok' => true, 'message' => '主题已安装：' . $slug, 'slug' => $slug];
    }

    /**
     * 扫描压缩包条目
     * @return array{ok:bool, message:string}
     */
    private static function scan(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_FILES) {
            return ['ok' => false, 'message' => '主题包文件数量超出限制（最多 ' . self::MAX_FILES . ' 个）'];
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) {
                return ['ok' => false, 'message' => '主题包条目损坏'];
            }
            $name = (string) ($stat['name'] ?? '');
            $total += (int) ($stat['size'] ?? 0);

            if ($total > self::MAX_BYTES) {
                return ['ok' => false, 'message' => '主题包解压后体积超出限制（最大 30MB）'];
            }
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                return ['ok' => false, 'message' => '主题包含非法路径：' . $name];
            }
            if (preg_match('#(^|/)\.#', $name)) {
                return ['ok' => false, 'message' => '主题包不允许包含隐藏文件/目录：' . $name];
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== '' && in_array($ext, self::FORBIDDEN_EXT, true)) {
                return ['ok' => false, 'message' => '主题包包含不允许的文件类型：' . $name];
            }

            // 符号链接（Unix 权限高位为 0120000）
            $mode = (int) (($stat['external_attributes'] ?? 0) >> 16);
            if (($mode & 0170000) === 0120000) {
                return ['ok' => false, 'message' => '主题包不允许包含符号链接：' . $name];
            }
        }

        return ['ok' => true, 'message' => 'ok'];
    }

    /**
     * 极简版本约束：支持 ">=2.1.0" / "2.1.0" / "^2.1.0"
     */
    private static function satisfies(string $current, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '') {
            return true;
        }

        if (preg_match('/^(>=|>|<=|<|=)?\s*([0-9]+(?:\.[0-9]+){1,2})$/', $constraint, $m) !== 1) {
            return true; // 无法识别的约束不阻塞安装，交由人工判断
        }

        $op = $m[1] !== '' ? $m[1] : '>=';
        if ($constraint[0] === '^') {
            $op = '>=';
        }

        return version_compare($current, $m[2], $op === '' ? '>=' : $op);
    }

    /**
     * @return array{ok:false, message:string, meta:null, slug:null}
     */
    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'meta' => null, 'slug' => null];
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

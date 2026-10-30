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
 * 发布打包工具：生成升级包 + update.json 清单（sha256 + Ed25519 签名）
 *
 * 用法：
 *   # 1) 首次发布前生成密钥对（公钥写入被升级站点的 backend/.env）
 *   php backend/tools/build-update.php --keygen
 *
 *   # 2) 打包新版本
 *   php backend/tools/build-update.php --version=2.0.2 --notes="新增功能A|修复问题B"
 *
 * 可选参数：
 *   --version=2.0.2        版本号（默认读取根目录 VERSION）
 *   --key=路径              Ed25519 私钥（默认 backend/storage/update-secret.key）
 *   --out=路径              产物目录（默认 release/）
 *   --channel=stable       更新渠道
 *   --min_version=2.0.0    允许直接升级的最低版本
 *   --breaking=1           标记为重要变更
 *   --url=地址              升级包下载地址（默认按 release-source.json 的仓库推导）
 *   --flavor=default|star  发行风味（默认读 release-source.json，决定包名与清单标记）
 *   --mirrors=a,b           额外镜像前缀，逗号分隔
 *   --notes=文本            更新日志，用 | 分隔多条（默认读取 update-notes.txt）
 *   --full=1               同时生成可全新安装的包（保留 install.php）
 *
 * 产物：
 *   release/ycms-{version}.zip         升级包（含 manifest.json，不含任何用户数据）
 *   release/ycms-{version}-install.zip 全新安装包（--full 时生成）
 *   release/update.json                更新清单，上传到仓库根目录 / Release 附件
 */

if (PHP_SAPI !== 'cli') {
    exit("仅支持命令行执行\n");
}

date_default_timezone_set('Asia/Shanghai');

$root = dirname(__DIR__, 2);
$args = parse_args($argv);

if (isset($args['help'])) {
    echo "用法：php backend/tools/build-update.php --version=2.0.2 [--key=私钥路径] [--notes=\"条目1|条目2\"]\n";
    echo "      php backend/tools/build-update.php --keygen\n";
    exit(0);
}

if (isset($args['keygen'])) {
    generate_keypair($root);
    exit(0);
}

build($root, $args);

/* ==================== 实现 ==================== */

/**
 * @return array<string, string>
 */
function parse_args(array $argv): array
{
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $pair = explode('=', substr($arg, 2), 2);
        $args[$pair[0]] = $pair[1] ?? '1';
    }
    return $args;
}

function fail(string $message): never
{
    fwrite(STDERR, "✖ {$message}\n");
    exit(1);
}

/**
 * 读取发布源配置（仓库 / 分支 / 风味）
 *
 * 与 UpdateService::source() 同一份文件、同一套派生规则：
 * 主仓库与主题仓库各持一份 release-source.json，其余逻辑完全共用。
 *
 * @return array{repository: string, branch: string, channel: string, flavor: string}
 */
function source_config(string $root): array
{
    $defaults = [
        'repository' => 'sjzyingyunkeji/ycms-2.0',
        'branch' => 'main',
        'channel' => 'stable',
        'flavor' => 'default',
    ];

    $file = $root . '/release-source.json';
    $raw = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($raw)) {
        return $defaults;
    }

    foreach ($defaults as $key => $value) {
        $current = isset($raw[$key]) ? trim((string) $raw[$key]) : '';
        $defaults[$key] = $current !== '' ? $current : $value;
    }

    if (preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $defaults['repository']) !== 1) {
        $defaults['repository'] = 'sjzyingyunkeji/ycms-2.0';
    }
    if (preg_match('#^[A-Za-z0-9_.-]+$#', $defaults['branch']) !== 1) {
        $defaults['branch'] = 'main';
    }
    if (!in_array($defaults['flavor'], ['default', 'star'], true)) {
        $defaults['flavor'] = 'default';
    }

    return $defaults;
}

function generate_keypair(string $root): void
{
    if (!function_exists('sodium_crypto_sign_keypair')) {
        fail('当前 PHP 未启用 libsodium，无法生成 Ed25519 密钥');
    }

    $pair = sodium_crypto_sign_keypair();
    $publicKey = sodium_crypto_sign_publickey($pair);
    $secretKey = sodium_crypto_sign_secretkey($pair);

    $dir = $root . '/backend/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $publicFile = $dir . '/update-public.key';
    $secretFile = $dir . '/update-secret.key';

    file_put_contents($publicFile, base64_encode($publicKey) . "\n");
    file_put_contents($secretFile, base64_encode($secretKey) . "\n");
    @chmod($secretFile, 0600);

    echo "✓ 已生成 Ed25519 密钥对\n";
    echo "  公钥：{$publicFile}\n";
    echo "  私钥：{$secretFile}（请备份，不要提交到仓库或随包发布）\n\n";
    echo "在被升级站点的 backend/.env 中加入：\n";
    echo 'UPDATE_PUBLIC_KEY=' . base64_encode($publicKey) . "\n";
}

/**
 * @param array<string, string> $args
 */
function build(string $root, array $args): void
{
    if (!class_exists(ZipArchive::class)) {
        fail('当前 PHP 未启用 ZipArchive 扩展');
    }

    $version = trim((string) ($args['version'] ?? ''));
    if ($version === '') {
        $version = trim((string) @file_get_contents($root . '/VERSION'));
    }
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        fail('版本号格式应为 x.y.z，例如 2.0.2');
    }

    $channel = (string) ($args['channel'] ?? 'stable');
    $outDir = rtrim(str_replace('\\', '/', (string) ($args['out'] ?? $root . '/release')), '/');
    if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        fail('无法创建产物目录：' . $outDir);
    }

    $source = source_config($root);
    $flavor = (string) ($args['flavor'] ?? $source['flavor']);
    $zipName = 'ycms-' . $version . '-' . $flavor . '.zip';
    if ($flavor === 'default') {
        // 默认版沿用历史包名，避免破坏既有下载链接
        $zipName = 'ycms-' . $version . '.zip';
    }
    $zipPath = $outDir . '/' . $zipName;
    $count = build_archive($root, $zipPath, $version, $channel, $flavor, false);

    $sha256 = (string) hash_file('sha256', $zipPath);
    $size = (int) filesize($zipPath);

    // 签名（未配置私钥时仅输出 sha256，站点侧也不强制签名）
    $signature = '';
    $publicKey = '';
    $keyPath = (string) ($args['key'] ?? '');
    if ($keyPath === '' && is_file($root . '/backend/storage/update-secret.key')) {
        $keyPath = $root . '/backend/storage/update-secret.key';
    }

    if ($keyPath !== '') {
        if (!function_exists('sodium_crypto_sign_detached')) {
            fail('当前 PHP 未启用 libsodium，无法签名');
        }
        if (!is_file($keyPath) && is_file($root . '/' . $keyPath)) {
            $keyPath = $root . '/' . $keyPath;
        }
        if (!is_file($keyPath)) {
            fail('私钥文件不存在：' . $keyPath);
        }

        $raw = (string) file_get_contents($keyPath);
        $decoded = base64_decode(trim($raw), true);
        $secretKey = ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) ? $decoded : $raw;
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            fail('私钥格式不正确，需要 64 字节 Ed25519 私钥或其 base64 编码');
        }

        // 与被升级端一致：对 sha256 十六进制字符串本身签名
        $signature = base64_encode(sodium_crypto_sign_detached($sha256, $secretKey));
        $publicKey = base64_encode(sodium_crypto_sign_publickey_from_secretkey($secretKey));
    }

    // 下载地址默认由 release-source.json 推导（主仓库 / 主题仓库各自指向自己的 releases）
    $url = (string) ($args['url']
        ?? 'https://github.com/' . $source['repository'] . '/releases/download/v' . $version . '/' . $zipName);
    $mirrors = array_values(array_filter(array_map(
        'trim',
        explode(',', (string) ($args['mirrors'] ?? ''))
    )));

    $release = [
        'latest' => $version,
        'flavor' => $flavor,
        'channel' => $channel,
        'min_version' => (string) ($args['min_version'] ?? '2.0.0'),
        'min_php' => '8.2',
        'published_at' => date('c'),
        'changelog' => notes($root, $args, $version),
        'breaking' => (string) ($args['breaking'] ?? '0') === '1',
        'changelog_url' => (string) ($args['changelog_url'] ?? ''),
        'package' => [
            'url' => $url,
            'mirrors' => $mirrors,
            'size' => $size,
            'sha256' => $sha256,
            'signature' => $signature,
        ],
    ];

    $manifestPath = $outDir . '/update.json';
    file_put_contents($manifestPath, (string) json_encode(
        $release,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ) . "\n");

    echo "✓ 版本 {$version} 打包完成\n";
    echo '  升级包：' . $zipPath . '（' . round($size / 1048576, 2) . " MB，{$count} 个文件）\n";
    echo '  sha256：' . $sha256 . "\n";
    echo '  清单：' . $manifestPath . "\n";

    $migrations = glob($root . '/backend/database/migrations/*.sql') ?: [];
    sort($migrations);
    echo '  迁移脚本：' . count($migrations) . " 个（升级时会按文件名顺序执行未执行过的）\n";

    // 全新安装包：额外保留 install.php，供新站点从零安装
    if ((string) ($args['full'] ?? '0') === '1') {
        $installName = 'ycms-' . $version . ($flavor === 'default' ? '' : '-' . $flavor) . '-install.zip';
        $installPath = $outDir . '/' . $installName;
        $installCount = build_archive($root, $installPath, $version, $channel, $flavor, true);
        echo '  安装包：' . $installPath . '（' . round((int) filesize($installPath) / 1048576, 2)
            . " MB，{$installCount} 个文件，含 install.php）\n";
    }

    if ($signature !== '') {
        echo "  签名：已生成（Ed25519），站点需配置 UPDATE_PUBLIC_KEY\n";
        echo '  公钥：' . $publicKey . "\n";
    } else {
        echo "  签名：未生成（未找到私钥，站点仅校验 sha256）\n";
    }
    echo "\n下一步：把 {$zipName} 上传到 GitHub Release，把 update.json 覆盖到仓库根目录。\n";
}

/**
 * 生成压缩包（升级包 / 全新安装包）
 *
 * @return int 文件数
 */
function build_archive(string $root, string $path, string $version, string $channel, string $flavor, bool $full): int
{
    @unlink($path);

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail('无法创建压缩包：' . $path);
    }

    // 包内版本信息（UpdateService 解压后必须能找到 manifest.json）
    $zip->addFromString('manifest.json', (string) json_encode([
        'version' => $version,
        'flavor' => $flavor,
        'channel' => $channel,
        'type' => $full ? 'full' : 'update',
        'php' => '>=8.2',
        'built_at' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $count = 1;
    // 升级包只带 VERSION；README（含截图）仅随全新安装包
    // release-source.json 必须随包：升级后站点要按新仓库继续检查更新
    // docs/ 不进包：站点运行不需要它，只会让安装包变大
    $rootFiles = ['VERSION', 'release-source.json'];
    if ($full) {
        $rootFiles[] = 'README.md';
    }
    foreach ($rootFiles as $file) {
        if (is_file($root . '/' . $file)) {
            $zip->addFile($root . '/' . $file, $file);
            $count++;
        }
    }

    foreach (['backend', 'public'] as $dir) {
        $count += add_tree($zip, $root, $dir, $full);
    }

    $zip->close();

    if (!is_file($path) || filesize($path) < 1024) {
        fail('压缩包生成失败：' . $path);
    }

    return $count;
}

/**
 * @return array<int, string>
 */
function notes(string $root, array $args, string $version): array
{
    $raw = (string) ($args['notes'] ?? '');
    if ($raw === '' && is_file($root . '/update-notes.txt')) {
        $raw = (string) file_get_contents($root . '/update-notes.txt');
    }
    $items = array_values(array_filter(array_map('trim', preg_split('/[|\r\n]+/', $raw) ?: [])));
    return $items === [] ? ['版本 ' . $version] : $items;
}

/**
 * 递归加入目录，返回文件数
 */
function add_tree(ZipArchive $zip, string $root, string $rel, bool $full = false): int
{
    $dirPath = $root . '/' . $rel;
    if (!is_dir($dirPath)) {
        return 0;
    }

    $count = 0;
    $base = str_replace('\\', '/', $dirPath);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        $relative = $rel . substr($path, strlen($base));

        if (skip($relative, $full)) {
            continue;
        }
        $zip->addFile($path, $relative);
        $count++;
    }
    return $count;
}

/**
 * 打包排除规则（用户数据 / 开发文件 / 敏感文件）
 */
function skip(string $relative, bool $full = false): bool
{
    $relative = ltrim($relative, '/');

    // 安装向导：升级包剔除（站点安装后应删除），全新安装包保留
    if ($relative === 'public/install.php') {
        return !$full;
    }

    // 文档目录整枝不随包分发（站点运行不需要）
    if (str_starts_with($relative, 'docs/')) {
        return true;
    }

    // 含测试账号/示例数据的开发脚本，任何包都不带
    if ($relative === 'backend/database/seed.php') {
        return true;
    }
    if (preg_match('#^backend/database/patch-[^/]+\.php$#', $relative) === 1) {
        return true;
    }

    $prefixes = [
        'backend/storage/',
        'backend/vendor/',
        // tools/ 含 build-update.php 与 reset-password.php：高危重置工具绝不随包
        'backend/tools/',
        'backend/.env',
        'backend/dev-router.php',
        'backend/public/reset-password.php',
        'public/storage/',
        'public/install.lock',
        'public/post/',
        'public/page/',
        'public/category/',
        'public/tag/',
        'public/posts/',
        'public/archive/',
        'public/links/',
        'public/sitemap.xml',
        'public/robots.txt',
        'public/.htaccess.bak',
    ];
    foreach ($prefixes as $prefix) {
        if (str_ends_with($prefix, '/') ? str_starts_with($relative, $prefix) : $relative === $prefix) {
            return true;
        }
    }

    $basename = basename($relative);
    if (in_array($basename, ['.DS_Store', 'Thumbs.db', '.gitignore', '.gitkeep'], true)) {
        return true;
    }
    foreach (['node_modules/', '.git/', '__MACOSX/'] as $segment) {
        if (str_contains($relative, '/' . $segment) || str_starts_with($relative, $segment)) {
            return true;
        }
    }

    return false;
}

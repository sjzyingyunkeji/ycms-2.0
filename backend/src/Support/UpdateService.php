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

namespace App\Support;

use App\Core\Database;
use ZipArchive;

/**
 * 在线升级服务
 *
 * 能力：
 *  - 每日定时检查 GitHub 清单（懒触发，无需配置 cron）
 *  - 多镜像并发/顺序下载 + sha256 校验 + Ed25519 签名校验
 *  - 分步执行升级（下载 → 解压 → 备份 → 覆盖 → 迁移 → 收尾），避免请求超时
 *  - 代码备份与回滚、离线包上传
 */
final class UpdateService
{
    /**
     * 兜底清单地址
     *
     * 实际地址由 release-source.json 推导（见 manifestUrl()）；
     * 该常量仅用于兼容旧调用方与「配置文件缺失」时的默认仓库标识。
     */
    public const MANIFEST_URL = 'https://raw.githubusercontent.com/sjzyingyunkeji/ycms-2.0/main/update.json';

    /** 发布源配置文件（随包分发，决定去哪个仓库检查更新） */
    private const SOURCE_FILE = '/release-source.json';

    /** 允许的发行风味 */
    private const FLAVORS = ['default', 'star'];

    /** 发布源缓存 */
    private static ?array $sourceCache = null;

    /** 最近一次拉取清单的失败原因（用于后台提示） */
    private static string $fetchError = '';

    /** 检查间隔：24 小时 */
    public const CHECK_INTERVAL = 86400;

    /** 内置镜像前缀（空字符串代表官方直连） */
    public const MIRRORS = [
        '',
        'https://ghfast.top/',
        'https://ghproxy.net/',
        'https://gh-proxy.com/',
    ];

    /** 下载允许的域名白名单 */
    private const ALLOWED_HOSTS = [
        'github.com',
        'objects.githubusercontent.com',
        'codeload.github.com',
        'raw.githubusercontent.com',
        'ghfast.top',
        'ghproxy.net',
        'gh-proxy.com',
        'mirror.ghproxy.com',
    ];

    /**
     * 当前程序版本
     */
    public static function currentVersion(): string
    {
        $file = self::rootDir() . '/VERSION';
        if (is_file($file)) {
            $v = trim((string) file_get_contents($file));
            if ($v !== '') {
                return $v;
            }
        }
        return '2.0.1';
    }

    /**
     * 项目根目录
     */
    public static function rootDir(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * 发布源配置（release-source.json）
     *
     * 每个仓库各自持有一份：主仓库指向 ycms-2.0，主题仓库指向自己的仓库。
     * 同一份后端代码因此不用为「两个仓库」写两套升级逻辑，只换地址。
     *
     * @return array<string, string>
     */
    public static function source(): array
    {
        if (self::$sourceCache !== null) {
            return self::$sourceCache;
        }

        $defaults = [
            'repository' => 'sjzyingyunkeji/ycms-2.0',
            'branch' => 'main',
            'channel' => 'stable',
            'flavor' => 'default',
        ];

        $file = self::rootDir() . self::SOURCE_FILE;
        $raw = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
        if (!is_array($raw)) {
            return self::$sourceCache = $defaults;
        }

        foreach ($defaults as $key => $value) {
            $current = isset($raw[$key]) ? trim((string) $raw[$key]) : '';
            $defaults[$key] = $current !== '' ? $current : $value;
        }

        // 仓库标识必须形如 owner/name，分支只允许安全字符，避免被拼出奇怪的 URL
        if (preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $defaults['repository']) !== 1) {
            $defaults['repository'] = 'sjzyingyunkeji/ycms-2.0';
        }
        if (preg_match('#^[A-Za-z0-9_.-]+$#', $defaults['branch']) !== 1) {
            $defaults['branch'] = 'main';
        }
        if (!in_array($defaults['flavor'], self::FLAVORS, true)) {
            $defaults['flavor'] = 'default';
        }

        return self::$sourceCache = $defaults;
    }

    /**
     * 当前发行风味（default / star）
     */
    public static function flavor(): string
    {
        return self::source()['flavor'];
    }

    /**
     * 当前仓库标识（owner/name）
     */
    public static function repository(): string
    {
        return self::source()['repository'];
    }

    /**
     * 更新清单地址（raw 直链，免 API 限流）
     */
    public static function manifestUrl(): string
    {
        $source = self::source();
        return 'https://raw.githubusercontent.com/' . $source['repository']
            . '/' . $source['branch'] . '/update.json';
    }

    /**
     * 发行风味的中文名称（用于提示文案）
     */
    public static function flavorLabel(string $flavor): string
    {
        return match ($flavor) {
            'star' => '星空版',
            'default' => '默认版',
            default => $flavor !== '' ? $flavor . ' 版' : '未知版本',
        };
    }

    /**
     * 状态文件路径
     */
    private static function stateFile(): string
    {
        return self::rootDir() . '/backend/storage/update-state.json';
    }

    /**
     * 读取状态
     * @return array<string, mixed>
     */
    public static function state(): array
    {
        $file = self::stateFile();
        if (!is_file($file)) {
            return ['last_checked' => 0, 'last_result' => '', 'manifest' => null, 'progress' => null];
        }
        $raw = json_decode((string) file_get_contents($file), true);
        return is_array($raw) ? $raw : ['last_checked' => 0, 'last_result' => '', 'manifest' => null, 'progress' => null];
    }

    /**
     * 保存状态
     * @param array<string, mixed> $state
     */
    public static function saveState(array $state): void
    {
        @file_put_contents(self::stateFile(), json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * 是否已到检查时间
     */
    public static function shouldCheck(): bool
    {
        $state = self::state();
        return (time() - (int) ($state['last_checked'] ?? 0)) >= self::CHECK_INTERVAL;
    }

    /**
     * 检查更新
     *
     * @param bool $force 忽略 24h 缓存
     * @return array<string, mixed>
     */
    public static function check(bool $force = false): array
    {
        $state = self::state();

        if (!$force && !self::shouldCheck()) {
            return self::summary($state);
        }

        // 单飞锁：避免并发检查
        $lock = self::rootDir() . '/backend/storage/update-check.lock';
        $fp = @fopen($lock, 'c');
        if ($fp === false || !flock($fp, LOCK_EX | LOCK_NB)) {
            return self::summary($state);
        }

        try {
            $manifest = self::fetchManifest();

            if ($manifest === null) {
                // 失败后 30 分钟再试，避免网络抖动导致一整天不再检查
                $state['last_checked'] = time() - self::CHECK_INTERVAL + 1800;
                $state['last_result'] = 'fail';
                $state['last_error'] = self::$fetchError !== ''
                    ? self::$fetchError
                    : '无法获取更新清单（网络受限或仓库不可达）';
                self::saveState($state);
                return self::summary($state);
            }

            $state['last_checked'] = time();
            $state['last_result'] = 'ok';
            $state['last_error'] = '';
            $state['manifest'] = $manifest;
            self::saveState($state);

            return self::summary($state);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 读取缓存的检查结果（不发起网络请求）
     * @return array<string, mixed>
     */
    public static function cached(): array
    {
        return self::summary(self::state());
    }

    /**
     * 汇总给前端的检查结果
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function summary(array $state): array
    {
        $current = self::currentVersion();
        $manifest = $state['manifest'] ?? null;
        $latest = is_array($manifest) ? (string) ($manifest['latest'] ?? $current) : $current;
        $minVersion = is_array($manifest) ? (string) ($manifest['min_version'] ?? '') : '';
        // 跨代升级护栏：当前版本低于升级包要求的最低版本时禁止直接升级
        $blocked = $minVersion !== '' && version_compare($current, $minVersion, '<');

        return [
            'current' => $current,
            'flavor' => self::flavor(),
            'flavor_label' => self::flavorLabel(self::flavor()),
            'repository' => self::repository(),
            'latest' => $latest,
            'has_update' => version_compare($latest, $current, '>'),
            'channel' => is_array($manifest) ? (string) ($manifest['channel'] ?? 'stable') : 'stable',
            'published_at' => is_array($manifest) ? (string) ($manifest['published_at'] ?? '') : '',
            'changelog' => is_array($manifest) ? ($manifest['changelog'] ?? []) : [],
            'breaking' => is_array($manifest) ? (bool) ($manifest['breaking'] ?? false) : false,
            'package' => is_array($manifest) ? ($manifest['package'] ?? []) : [],
            'changelog_url' => is_array($manifest) ? (string) ($manifest['changelog_url'] ?? '') : '',
            'min_version' => $minVersion,
            'min_php' => is_array($manifest) ? (string) ($manifest['min_php'] ?? '') : '',
            'blocked' => $blocked,
            'block_reason' => $blocked
                ? "当前版本 {$current} 低于该升级包要求的最低版本 {$minVersion}，请先升级到 {$minVersion}"
                : '',
            'offline' => is_array($manifest) ? (bool) ($manifest['offline'] ?? false) : false,
            'needs_check' => self::shouldCheck(),
            'last_checked' => (int) ($state['last_checked'] ?? 0),
            'last_checked_text' => (int) ($state['last_checked'] ?? 0) > 0
                ? date('Y-m-d H:i', (int) $state['last_checked'])
                : '从未检查',
            'last_result' => (string) ($state['last_result'] ?? ''),
            'last_error' => (string) ($state['last_error'] ?? ''),
            'signing' => self::publicKey() !== '',
            'progress' => $state['progress'] ?? null,
        ];
    }

    /**
     * 拉取清单（多镜像尝试）
     * @return array<string, mixed>|null
     */
    private static function fetchManifest(): ?array
    {
        self::$fetchError = '';
        $urls = self::mirrorUrls(self::manifestUrl());
        foreach ($urls as $url) {
            $body = self::httpGet($url, 8);
            if ($body === null) {
                continue;
            }
            $data = json_decode($body, true);
            if (!is_array($data) || !isset($data['latest'])) {
                continue;
            }

            // 风味校验：默认版站点不能升级成星空版包，反之亦然
            $flavor = trim((string) ($data['flavor'] ?? ''));
            if ($flavor !== '' && $flavor !== self::flavor()) {
                self::$fetchError = '更新清单属于' . self::flavorLabel($flavor)
                    . '，当前站点是' . self::flavorLabel(self::flavor()) . '，已拒绝跨风味升级';
                continue;
            }

            return $data;
        }
        return null;
    }

    /**
     * 生成镜像 URL 列表（官方优先）
     * @return array<int, string>
     */
    public static function mirrorUrls(string $url, array $extra = []): array
    {
        $mirrors = array_merge(self::MIRRORS, $extra);
        $mirrors = array_values(array_unique(array_filter($mirrors, static fn ($m) => is_string($m))));

        $urls = [];
        foreach ($mirrors as $prefix) {
            if ($prefix === '') {
                $urls[] = $url;
                continue;
            }
            $urls[] = rtrim($prefix, '/') . '/' . $url;
        }
        return $urls;
    }

    /**
     * HTTP GET
     */
    private static function httpGet(string $url, int $timeout = 10): ?string
    {
        if (!self::hostAllowed($url)) {
            return null;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout * 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'ycms-updater/' . self::currentVersion(),
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
        }

        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'follow_location' => 1]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }

    /**
     * 域名白名单校验
     */
    private static function hostAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 更新公钥（Ed25519，base64）
     */
    public static function publicKey(): string
    {
        $key = (string) env('UPDATE_PUBLIC_KEY', '');
        return trim($key);
    }

    /* ==================== 升级流程（分步） ==================== */

    /**
     * 分步执行
     * @return array{ok:bool, step:string, percent:int, message:string}
     */
    public static function runStep(string $step): array
    {
        $state = self::state();
        $manifest = $state['manifest'] ?? null;

        if (!is_array($manifest)) {
            return self::fail('尚未获取更新清单，请先检查更新');
        }

        // 跨代升级护栏：低于最低版本时必须逐级升级，避免跳过中间版本的结构变更
        $minVersion = (string) ($manifest['min_version'] ?? '');
        $current = self::currentVersion();
        if ($minVersion !== '' && version_compare($current, $minVersion, '<')) {
            return self::fail("当前版本 {$current} 低于该升级包要求的最低版本 {$minVersion}，请先升级到 {$minVersion} 后再升级");
        }

        // 升级单飞锁：同一时间只允许一个步骤在执行
        $lock = self::rootDir() . '/backend/storage/upgrade.lock';
        $fp = @fopen($lock, 'c');
        if ($fp === false) {
            return self::fail('无法创建升级锁文件，请检查 backend/storage 写入权限');
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return self::fail('已有升级任务正在执行，请稍候再试');
        }

        try {
            try {
                $result = match ($step) {
                    'download' => self::stepDownload($manifest),
                    'extract' => self::stepExtract(),
                    'backup' => self::stepBackup(),
                    'apply' => self::stepApply(),
                    'migrate' => self::stepMigrate(),
                    'finalize' => self::stepFinalize($manifest),
                    default => self::fail('未知的升级步骤：' . $step),
                };
            } catch (\Throwable $e) {
                // 扩展缺失、磁盘/权限异常等统一转为可读错误，避免前端收到 500 空白
                $result = self::fail('执行失败：' . $e->getMessage());
            }

            // 记录进度（finalize 内部已重置状态）
            if ($step !== 'finalize') {
                $state = self::state();
                $ok = ($result['ok'] ?? false) === true;
                $state['progress'] = [
                    'step' => $step,
                    'percent' => (int) ($result['percent'] ?? 0),
                    'message' => (string) ($result['message'] ?? ''),
                    'ok' => $ok,
                    'time' => now(),
                ];
                if (!$ok) {
                    $state['last_error'] = (string) ($result['message'] ?? '升级步骤失败');
                }
                self::saveState($state);
            }

            return $result;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 导入离线升级包（后台手动上传，适用于服务器无法访问 GitHub 的场景）
     *
     * 包内必须含 manifest.json（版本信息），导入后等同于在线清单，
     * 后续只需依次执行 extract → backup → apply → migrate → finalize。
     */
    public static function importPackage(string $uploadedPath, string $filename = ''): array
    {
        if (!class_exists(ZipArchive::class)) {
            return self::fail('服务器缺少 ZipArchive 扩展，无法处理离线包');
        }
        if (!is_file($uploadedPath)) {
            return self::fail('未收到上传文件');
        }

        $zip = new ZipArchive();
        if ($zip->open($uploadedPath) !== true) {
            return self::fail('离线包无法打开，请确认是 zip 格式');
        }

        $count = $zip->numFiles;
        $manifestIndex = -1;
        for ($i = 0; $i < $count; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!self::entryAllowed($name)) {
                $zip->close();
                return self::fail('离线包包含非法路径：' . $name);
            }
            if ($manifestIndex < 0 && preg_match('#^(?:[^/]+/)?manifest\.json$#', str_replace('\\', '/', $name))) {
                $manifestIndex = $i;
            }
        }

        $info = $manifestIndex >= 0 ? json_decode((string) $zip->getFromIndex($manifestIndex), true) : null;
        $zip->close();

        if (!is_array($info)) {
            return self::fail('离线包缺少 manifest.json 或格式错误，请联系发布方');
        }

        // 风味校验：两条发行线互不覆盖（默认版 / 星空版）
        $packageFlavor = trim((string) ($info['flavor'] ?? ''));
        if ($packageFlavor !== '' && $packageFlavor !== self::flavor()) {
            return self::fail('这是' . self::flavorLabel($packageFlavor) . '的升级包，当前站点是'
                . self::flavorLabel(self::flavor()) . '，请下载对应版本的升级包');
        }

        $target = self::tmpDir() . '/package.zip';
        @unlink($target);
        if (!@rename($uploadedPath, $target) && !@copy($uploadedPath, $target)) {
            return self::fail('离线包写入失败，请检查 backend/storage 权限');
        }

        $sha = (string) hash_file('sha256', $target);
        $version = trim((string) ($info['version'] ?? ''));

        $state = self::state();
        $state['manifest'] = [
            'latest' => $version !== '' ? $version : self::currentVersion(),
            'flavor' => self::flavor(),
            'channel' => 'offline',
            'published_at' => date('c'),
            'changelog' => ['离线包：' . ($filename !== '' ? $filename : basename($uploadedPath))],
            'breaking' => false,
            'offline' => true,
            'package' => [
                'url' => 'offline://' . ($filename !== '' ? $filename : basename($uploadedPath)),
                'mirrors' => [],
                'size' => (int) filesize($target),
                'sha256' => $sha,
                'signature' => '',
            ],
        ];
        $state['last_checked'] = time();
        $state['last_result'] = 'ok';
        $state['last_error'] = '';
        $state['progress'] = null;
        self::saveState($state);

        return [
            'ok' => true,
            'step' => 'import',
            'percent' => 20,
            'message' => '离线包已导入（版本 ' . ($version !== '' ? $version : '未知') . '，'
                . self::humanSize((int) filesize($target)) . '）',
        ];
    }

    private static function tmpDir(): string
    {
        $dir = self::rootDir() . '/backend/storage/tmp';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function backupDir(): string
    {
        $dir = self::rootDir() . '/backend/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * 步骤 1：下载（多镜像 + 校验）
     */
    private static function stepDownload(array $manifest): array
    {
        $pkg = $manifest['package'] ?? [];
        $url = (string) ($pkg['url'] ?? '');
        $sha = strtolower((string) ($pkg['sha256'] ?? ''));

        if ($url === '') {
            return self::fail('清单中缺少升级包地址');
        }

        // 离线包：跳过下载，直接复用已导入的 package.zip
        if (($manifest['offline'] ?? false) === true) {
            $target = self::tmpDir() . '/package.zip';
            if (!is_file($target)) {
                return self::fail('离线包不存在，请重新上传');
            }
            return ['ok' => true, 'step' => 'download', 'percent' => 25,
                'message' => '使用已上传的离线包（' . self::humanSize((int) filesize($target)) . '）'];
        }

        $extra = is_array($pkg['mirrors'] ?? null) ? $pkg['mirrors'] : [];
        $target = self::tmpDir() . '/package.zip';
        @unlink($target);

        $errors = [];
        foreach (self::mirrorUrls($url, $extra) as $candidate) {
            $result = self::downloadTo($candidate, $target);
            if ($result === null) {
                $errors[] = '连接失败';
                continue;
            }

            $actual = hash_file('sha256', $target);
            if ($sha !== '' && !hash_equals($sha, (string) $actual)) {
                $errors[] = '校验不符';
                @unlink($target);
                continue;
            }

            // 签名校验（配置了公钥则强制）
            if (self::publicKey() !== '') {
                $sig = (string) ($pkg['signature'] ?? '');
                if ($sig === '' || !self::verifySignature((string) $sha, $sig)) {
                    @unlink($target);
                    return self::fail('升级包签名校验失败，已中止（可能存在篡改）');
                }
            }

            return ['ok' => true, 'step' => 'download', 'percent' => 25,
                'message' => '下载完成，校验通过（' . self::humanSize((int) filesize($target)) . '）'];
        }

        return self::fail('所有下载源均失败：' . implode('、', array_unique($errors)));
    }

    /**
     * 单源下载
     */
    private static function downloadTo(string $url, string $target): ?string
    {
        if (!self::hostAllowed($url)) {
            return null;
        }

        if (function_exists('curl_init')) {
            $fp = @fopen($target, 'wb');
            if ($fp === false) {
                return null;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'ycms-updater/' . self::currentVersion(),
            ]);
            $ok = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($ok === false || $code < 200 || $code >= 300 || !is_file($target) || filesize($target) < 1024) {
                @unlink($target);
                return null;
            }
            return $target;
        }

        $body = self::httpGet($url, 30);
        if ($body === null) {
            return null;
        }
        file_put_contents($target, $body);
        return $target;
    }

    /**
     * Ed25519 签名校验
     */
    public static function verifySignature(string $message, string $signatureB64): bool
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }
        $pk = base64_decode(self::publicKey(), true);
        $sig = base64_decode($signatureB64, true);
        if ($pk === false || $sig === false || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($sig, $message, $pk);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 步骤 2：解压并校验结构
     */
    private static function stepExtract(): array
    {
        if (!class_exists(ZipArchive::class)) {
            return self::fail('服务器缺少 ZipArchive 扩展，无法解压升级包');
        }

        $zipPath = self::tmpDir() . '/package.zip';
        if (!is_file($zipPath)) {
            return self::fail('升级包不存在，请重新下载');
        }

        $dir = self::tmpDir() . '/upgrade';
        self::removeDir($dir);
        mkdir($dir, 0775, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return self::fail('升级包损坏，无法解压');
        }

        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!self::entryAllowed($name)) {
                $zip->close();
                self::removeDir($dir);
                return self::fail('升级包包含非法路径：' . $name);
            }
            $count++;
        }

        if ($zip->extractTo($dir) !== true) {
            $zip->close();
            return self::fail('解压失败（磁盘空间不足或权限问题）');
        }
        $zip->close();

        // GitHub Release 常见结构为 ycms-2.0.1/backend/...，这里统一提升一层
        $dir = self::normalizeRoot($dir);

        if (!is_file($dir . '/manifest.json')) {
            return self::fail('升级包缺少 manifest.json');
        }

        return ['ok' => true, 'step' => 'extract', 'percent' => 40, 'message' => '解压完成，共 ' . $count . ' 个文件'];
    }

    /**
     * 归一化升级包根目录：若只有单一顶层目录，则把其内容提升到根
     */
    private static function normalizeRoot(string $dir): string
    {
        if (is_dir($dir . '/backend') || is_file($dir . '/manifest.json')) {
            return $dir;
        }

        $entries = array_values(array_filter(
            scandir($dir) ?: [],
            static fn (string $item): bool => $item !== '.' && $item !== '..'
        ));
        if (count($entries) !== 1 || !is_dir($dir . '/' . $entries[0])) {
            return $dir;
        }

        $inner = $dir . '/' . $entries[0];
        if (!is_dir($inner . '/backend') && !is_file($inner . '/manifest.json')) {
            return $dir;
        }

        foreach (scandir($inner) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            @rename($inner . '/' . $item, $dir . '/' . $item);
        }
        @rmdir($inner);

        return $dir;
    }

    /**
     * zip 条目安全检查（zip-slip）
     */
    private static function entryAllowed(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0")) {
            return false;
        }
        $normalized = str_replace('\\', '/', $name);
        if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:#', $normalized)) {
            return false;
        }
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            return false;
        }
        return true;
    }

    /**
     * 步骤 3：备份代码与数据库
     */
    private static function stepBackup(): array
    {
        if (!class_exists(ZipArchive::class)) {
            return self::fail('服务器缺少 ZipArchive 扩展，无法创建代码备份');
        }

        $root = self::rootDir();
        $version = self::currentVersion();
        $stamp = date('Ymd-His');
        $zipPath = self::backupDir() . "/code-{$version}-{$stamp}.zip";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return self::fail('无法创建备份文件');
        }

        $targets = [
            'backend/src',
            'backend/config',
            'backend/database',
            'backend/composer.json',
            'backend/public',
            'public/index.php',
            'public/api.php',
            'public/index.html',
            'public/.htaccess',
            'public/logo.svg',
            'public/favicon.svg',
            'public/admin',
            'public/assets',
            'VERSION',
        ];

        foreach ($targets as $rel) {
            self::addPath($zip, $root . '/' . $rel, $rel);
        }
        $zip->close();

        // 数据库快照：优先 VACUUM INTO（WAL 模式下也能得到一致副本）
        $db = $root . '/backend/storage/database.sqlite';
        $snapshot = self::backupDir() . "/db-{$stamp}.sqlite";
        if (is_file($db)) {
            $ok = false;
            try {
                Database::pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $snapshot) . "'");
                $ok = is_file($snapshot);
            } catch (\Throwable $e) {
                $ok = false;
            }
            if (!$ok) {
                @copy($db, $snapshot);
            }
        }

        // 只保留最近 3 份代码备份
        $files = glob(self::backupDir() . '/code-*.zip') ?: [];
        sort($files);
        while (count($files) > 3) {
            @unlink((string) array_shift($files));
        }

        return ['ok' => true, 'step' => 'backup', 'percent' => 55,
            'message' => '已备份当前版本（' . basename($zipPath) . '）'];
    }

    /**
     * 递归打包
     */
    private static function addPath(ZipArchive $zip, string $full, string $rel): void
    {
        if (is_file($full)) {
            $zip->addFile($full, $rel);
            return;
        }
        if (!is_dir($full)) {
            return;
        }
        $items = scandir($full) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::addPath($zip, $full . '/' . $item, $rel . '/' . $item);
        }
    }

    /**
     * 步骤 4：覆盖文件（白名单 + 原子替换）
     */
    private static function stepApply(): array
    {
        $src = self::tmpDir() . '/upgrade';
        if (!is_dir($src)) {
            return self::fail('暂存目录不存在，请重新解压');
        }

        $copied = 0;
        $failed = 0;

        foreach (['backend', 'public'] as $top) {
            $copied += self::copyTree($src . '/' . $top, $top, $failed);
        }

        if ($failed > 0) {
            return self::fail("有 {$failed} 个文件写入失败（目录不可写），已更新 {$copied} 个文件；请检查权限后回滚重试");
        }

        return ['ok' => true, 'step' => 'apply', 'percent' => 75, 'message' => '已更新 ' . $copied . ' 个文件'];
    }

    /**
     * 递归复制（跳过黑名单，写临时文件后 rename 原子替换）
     *
     * @param int $failed 写入失败计数（引用传出）
     */
    private static function copyTree(string $src, string $top, int &$failed): int
    {
        if (!is_dir($src)) {
            return 0;
        }
        $count = 0;
        $base = str_replace('\\', '/', $src);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $full = str_replace('\\', '/', $file->getPathname());
            $rel = $top . '/' . substr($full, strlen($base) + 1);

            if (!self::targetAllowed($rel)) {
                continue;
            }

            $target = self::rootDir() . '/' . $rel;
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                $failed++;
                continue;
            }

            $tmp = $target . '.new';
            if (@copy($full, $tmp) && @rename($tmp, $target)) {
                $count++;
            } else {
                @unlink($tmp);
                $failed++;
            }
        }
        return $count;
    }

    /**
     * 覆盖白名单（永不动用户数据）
     */
    private static function targetAllowed(string $rel): bool
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');

        $blacklist = [
            'backend/.env',
            'backend/storage/',
            'public/storage/',
            'public/install.php',
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
        ];
        foreach ($blacklist as $bad) {
            if (str_ends_with($bad, '/')) {
                if (str_starts_with($rel, $bad)) {
                    return false;
                }
            } elseif ($rel === $bad) {
                return false;
            }
        }

        return str_starts_with($rel, 'backend/') || str_starts_with($rel, 'public/');
    }

    /**
     * 步骤 5：数据库迁移
     */
    private static function stepMigrate(): array
    {
        $root = self::rootDir();

        // 1) 基础结构（幂等）
        $schema = $root . '/backend/database/schema.sql';
        if (is_file($schema)) {
            Database::pdo()->exec((string) file_get_contents($schema));
        }

        // 2) 增量迁移
        Database::execute('CREATE TABLE IF NOT EXISTS schema_migrations (
            name TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL
        )');

        $applied = [];
        foreach (Database::select('SELECT name FROM schema_migrations') as $row) {
            $applied[(string) $row['name']] = true;
        }

        $dir = $root . '/backend/database/migrations';
        $ran = 0;
        if (is_dir($dir)) {
            $files = glob($dir . '/*.sql') ?: [];
            sort($files);
            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$name])) {
                    continue;
                }
                // 单个迁移独立事务：失败即回滚，避免留下半截结构
                try {
                    Database::beginTransaction();
                    Database::pdo()->exec((string) file_get_contents($file));
                    Database::insert('schema_migrations', ['name' => $name, 'applied_at' => now()]);
                    Database::commit();
                    $ran++;
                } catch (\Throwable $e) {
                    if (Database::pdo()->inTransaction()) {
                        Database::rollBack();
                    }
                    return self::fail("数据库迁移 {$name} 执行失败：" . $e->getMessage());
                }
            }
        }

        return ['ok' => true, 'step' => 'migrate', 'percent' => 88,
            'message' => $ran > 0 ? "已执行 {$ran} 个数据库迁移" : '数据库结构已是最新'];
    }

    /**
     * 步骤 6：收尾
     */
    private static function stepFinalize(array $manifest): array
    {
        $root = self::rootDir();

        // 老站点兼容：2.0.x 平铺产物升级到主题化结构
        $migration = '';
        try {
            if (Theme\LegacyMigrator::needed()) {
                $report = Theme\LegacyMigrator::run('default', false);
                $migration = $report['ok'] ? '；老产物已迁移到 public/themes/' . $report['slug'] : '；老产物迁移失败：' . $report['message'];
            }
        } catch (\Throwable $e) {
            $migration = '；老产物迁移异常：' . $e->getMessage();
        }

        // 重建静态页（新架构必需）
        $static = '静态页待手动重建';
        try {
            $result = StaticBuilder::rebuild();
            $static = $result['ok'] ? '静态页已重建' : ('静态页重建失败：' . $result['message']);
        } catch (\Throwable $e) {
            $static = '静态页重建失败：' . $e->getMessage();
        }

        // 更新版本号
        $version = (string) ($manifest['latest'] ?? self::currentVersion());
        @file_put_contents($root . '/VERSION', $version . "\n");

        // 清理临时文件
        self::removeDir(self::tmpDir() . '/upgrade');
        @unlink(self::tmpDir() . '/package.zip');

        // 重置状态
        $state = self::state();
        $state['progress'] = null;
        $state['manifest'] = null;
        $state['last_checked'] = 0;
        $state['installed_at'] = date('Y-m-d H:i:s');
        $state['installed_version'] = $version;
        self::saveState($state);

        // 清 opcache
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return ['ok' => true, 'step' => 'finalize', 'percent' => 100,
            'message' => '升级完成，当前版本 ' . $version . '（' . $static . $migration . '）'];
    }

    /* ==================== 回滚 ==================== */

    /**
     * 备份列表
     * @return array<int, array{name:string, size:int, time:string, version:string, has_db:bool}>
     */
    public static function backups(): array
    {
        $list = [];
        foreach (glob(self::backupDir() . '/code-*.zip') ?: [] as $file) {
            $name = basename($file);
            preg_match('#^code-(.+)-(\d{8}-\d{6})\.zip$#', $name, $m);
            $list[] = [
                'name' => $name,
                'version' => $m[1] ?? '',
                'stamp' => $m[2] ?? '',
                'size' => (int) filesize($file),
                'time' => date('Y-m-d H:i:s', (int) filemtime($file)),
                'has_db' => isset($m[2]) && is_file(self::backupDir() . '/db-' . $m[2] . '.sqlite'),
            ];
        }
        usort($list, static fn ($a, $b) => strcmp($b['name'], $a['name']));
        return $list;
    }

    /**
     * 回滚到指定备份
     *
     * @param bool $restoreDb 是否一并恢复当时的数据库快照
     */
    public static function rollback(string $name, bool $restoreDb = false): array
    {
        $name = basename($name);
        $file = self::backupDir() . '/' . $name;
        if (!is_file($file) || !str_starts_with($name, 'code-')) {
            return ['ok' => false, 'message' => '备份不存在'];
        }
        if (!class_exists(ZipArchive::class)) {
            return ['ok' => false, 'message' => '服务器缺少 ZipArchive 扩展，无法回滚'];
        }

        // 升级过程中禁止回滚
        $lock = self::rootDir() . '/backend/storage/upgrade.lock';
        $fp = @fopen($lock, 'c');
        if ($fp !== false && !flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return ['ok' => false, 'message' => '升级任务正在执行，暂不能回滚'];
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($file) !== true) {
                return ['ok' => false, 'message' => '备份文件损坏'];
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (!self::entryAllowed((string) $zip->getNameIndex($i))) {
                    $zip->close();
                    return ['ok' => false, 'message' => '备份文件包含非法路径，已中止'];
                }
            }
            if ($zip->extractTo(self::rootDir()) !== true) {
                $zip->close();
                return ['ok' => false, 'message' => '回滚失败（权限不足）'];
            }
            $zip->close();

            $dbFound = false;
            $dbRestored = false;
            if ($restoreDb) {
                preg_match('#^code-(.+)-(\d{8}-\d{6})\.zip$#', $name, $m);
                $snapshot = self::backupDir() . '/db-' . ($m[2] ?? '') . '.sqlite';
                $dbFile = self::rootDir() . '/backend/storage/database.sqlite';
                if (($m[2] ?? '') !== '' && is_file($snapshot)) {
                    $dbFound = true;
                    @unlink($dbFile . '-wal');
                    @unlink($dbFile . '-shm');
                    $dbRestored = @copy($snapshot, $dbFile);
                }
            }

            $dbNote = '';
            if ($restoreDb) {
                $dbNote = $dbRestored ? '（含数据库快照）' : ($dbFound ? '（数据库快照恢复失败）' : '（未找到数据库快照）');
            }

            if ($fp !== false) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            try {
                StaticBuilder::rebuild();
            } catch (\Throwable $e) {
                // 忽略静态页重建失败
            }

            return [
                'ok' => true,
                'message' => '已回滚到 ' . $name . $dbNote,
            ];
        } finally {
            if (is_resource($fp)) {
                @fclose($fp);
            }
        }
    }

    /* ==================== 工具 ==================== */

    private static function fail(string $message): array
    {
        return ['ok' => false, 'step' => '', 'percent' => 0, 'message' => $message];
    }

    private static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 2) . ' MB';
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
            is_dir($path) ? self::removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}

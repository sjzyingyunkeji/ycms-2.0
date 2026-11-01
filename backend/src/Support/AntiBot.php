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
use App\Core\Request;
use App\Core\Response;

/**
 * 爬虫防护：公开接口按 IP 限流，超阈值临时封禁
 *
 * - 只统计匿名访问（带令牌的后台请求不参与，避免误伤编辑器）
 * - 只覆盖公开读接口与埋点接口，写接口另有鉴权与 CSRF 校验
 * - 阈值与封禁时长可在后台「站点设置 → 安全防护」调整
 */
final class AntiBot
{
    /** 统计窗口（秒） */
    private const WINDOW = 60;

    private const DEFAULT_LIMIT = 60;
    private const DEFAULT_BAN_MINUTES = 10;

    /** 参与限流的公开接口前缀 */
    private const PUBLIC_PATHS = [
        '/api/posts',
        '/api/categories',
        '/api/tags',
        '/api/pages',
        '/api/comments',
        '/api/links',
        '/api/settings',
        '/api/track',
        '/api/health',
        '/api/auth/setup-status',
    ];

    /**
     * @var array<string, string>|null
     */
    private static ?array $settings = null;

    /**
     * 入口守卫：命中封禁或超限直接 429 终止请求
     */
    public static function guard(Request $request): void
    {
        if ($request->method === 'OPTIONS') {
            return;
        }
        if (!self::isPublicApi($request->path)) {
            return;
        }
        if (self::isSwitchedOff()) {
            return;
        }
        // 后台登录态不参与限流
        if (($_COOKIE['ycms_token'] ?? '') !== '' || $request->bearerToken() !== null) {
            return;
        }

        $ip = self::clientIp();
        if ($ip === '') {
            return;
        }

        $now = time();
        $row = Database::selectOne('SELECT * FROM api_throttle WHERE ip = :ip', ['ip' => $ip]);

        if ($row !== null && (int) $row['banned_until'] > $now) {
            self::reject((int) $row['banned_until'] - $now);
        }

        $limit = self::limit();
        $windowStart = (int) ($row['window_start'] ?? 0);
        $hits = (int) ($row['hits'] ?? 0);

        if ($row === null || $now - $windowStart >= self::WINDOW) {
            $hits = 1;
            $windowStart = $now;
            Database::execute(
                'INSERT INTO api_throttle (ip, hits, window_start, banned_until, updated_at)
                 VALUES (:ip, :hits, :start, 0, :now)
                 ON CONFLICT(ip) DO UPDATE SET hits = :hits, window_start = :start, updated_at = :now',
                ['ip' => $ip, 'hits' => $hits, 'start' => $windowStart, 'now' => now()]
            );
        } else {
            $hits++;
            Database::execute(
                'UPDATE api_throttle SET hits = :hits, updated_at = :now WHERE ip = :ip',
                ['hits' => $hits, 'now' => now(), 'ip' => $ip]
            );
        }

        if ($hits > $limit) {
            $banUntil = $now + self::banMinutes() * 60;
            Database::execute(
                'UPDATE api_throttle SET banned_until = :until, hits = 0, window_start = :start WHERE ip = :ip',
                ['until' => $banUntil, 'start' => $now, 'ip' => $ip]
            );
            self::reject($banUntil - $now);
        }

        // 低频清理过期记录（约 1% 的请求触发）
        if ($hits === 1 && random_int(1, 100) === 1) {
            Database::execute('DELETE FROM api_throttle WHERE banned_until < :before', ['before' => $now - 3600]);
        }
    }

    /**
     * 后台可直接清空所有封禁
     */
    public static function clearAll(): int
    {
        return Database::execute('DELETE FROM api_throttle');
    }

    /**
     * 封禁列表（后台展示用）
     * @return array<int, array<string, mixed>>
     */
    public static function bannedList(int $limit = 50): array
    {
        return Database::select(
            'SELECT ip, hits, window_start, banned_until FROM api_throttle
             WHERE banned_until > :now ORDER BY banned_until DESC LIMIT :limit',
            ['now' => time(), 'limit' => $limit]
        );
    }

    private static function isPublicApi(string $path): bool
    {
        foreach (self::PUBLIC_PATHS as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    private static function isSwitchedOff(): bool
    {
        return self::setting('antibot_enabled', '1') === '0';
    }

    private static function limit(): int
    {
        $value = (int) self::setting('antibot_limit', (string) self::DEFAULT_LIMIT);
        return max(10, min(600, $value));
    }

    private static function banMinutes(): int
    {
        $value = (int) self::setting('antibot_ban_minutes', (string) self::DEFAULT_BAN_MINUTES);
        return max(1, min(1440, $value));
    }

    /**
     * 读取设置（单次请求内缓存）
     */
    private static function setting(string $key, string $default): string
    {
        if (self::$settings === null) {
            self::$settings = [];
            foreach (Database::select("SELECT key, value FROM settings WHERE key LIKE 'antibot_%'") as $row) {
                self::$settings[(string) $row['key']] = (string) $row['value'];
            }
        }
        return self::$settings[$key] ?? $default;
    }

    /**
     * 客户端 IP：优先 REMOTE_ADDR，缺失时回退 X-Forwarded-For 首段
     */
    private static function clientIp(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP)) {
            return substr($remote, 0, 45);
        }
        $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $ip = trim(explode(',', $forwarded)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return substr($ip, 0, 45);
            }
        }
        return '';
    }

    private static function reject(int $retryAfter): void
    {
        $minutes = max(1, (int) ceil($retryAfter / 60));
        header('Retry-After: ' . max(1, $retryAfter));
        Response::error("请求过于频繁，已临时封禁，请 {$minutes} 分钟后再试", 429);
    }
}

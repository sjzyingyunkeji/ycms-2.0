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

/**
 * 登录安全防护：弱口令/默认名校验、暴力破解限流、防重放 nonce
 */
final class LoginGuard
{
    /** 禁用的默认/高危用户名（不区分大小写） */
    private const USERNAME_BLACKLIST = [
        'admin', 'administrator', 'root', 'system', 'sysadmin', 'superadmin',
        'test', 'testing', 'guest', 'user', 'operator', 'manager', 'webmaster',
    ];

    /** 常见弱口令黑名单（不区分大小写） */
    private const PASSWORD_BLACKLIST = [
        '123456', '1234567', '12345678', '123456789', '1234567890',
        'password', 'password1', 'passw0rd', 'admin123', 'admin1234',
        'qwerty', 'qwerty123', 'abc123', 'abc12345', 'abcd1234',
        '111111', '11111111', '000000', '00000000', '666666', '88888888',
        'iloveyou', 'letmein', 'monkey', 'dragon', 'sunshine', 'princess',
        'welcome', 'welcome1', 'freedom', 'whatever', 'qazwsx', '1qaz2wsx',
        'p@ssw0rd', 'passw0rd!', 'ycms123', 'cms123', 'cmsadmin',
    ];

    /** 限流参数 */
    private const MAX_FAILURES = 5;          // 允许的连续失败次数
    private const LOCK_SECONDS = 900;        // 锁定时长（15 分钟）
    private const NONCE_TTL = 300;           // nonce 有效期（5 分钟，防重放窗口）

    /**
     * 校验用户名：3-30 位字母/数字/下划线/连字符/中文，禁用默认名
     * @return array{ok: bool, message: string}
     */
    public static function validateUsername(string $username): array
    {
        $username = trim($username);
        if (mb_strlen($username) < 3 || mb_strlen($username) > 30) {
            return ['ok' => false, 'message' => '用户名长度需为 3-30 个字符'];
        }
        if (!preg_match('/^[\p{L}\p{N}_\-]+$/u', $username)) {
            return ['ok' => false, 'message' => '用户名只能包含字母、数字、下划线和连字符'];
        }
        if (in_array(strtolower($username), self::USERNAME_BLACKLIST, true)) {
            return ['ok' => false, 'message' => '该用户名为系统保留的默认名称，禁止使用，请更换'];
        }
        return ['ok' => true, 'message' => ''];
    }

    /**
     * 校验密码强度：≥8 位，须含字母与数字，禁止弱口令与包含用户名
     * @return array{ok: bool, message: string}
     */
    public static function validatePassword(string $password, string $username = ''): array
    {
        if (mb_strlen($password) < 8) {
            return ['ok' => false, 'message' => '密码长度至少 8 位'];
        }
        if (mb_strlen($password) > 72) {
            return ['ok' => false, 'message' => '密码长度不能超过 72 位'];
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return ['ok' => false, 'message' => '密码必须同时包含字母和数字'];
        }
        if (in_array(strtolower($password), self::PASSWORD_BLACKLIST, true)) {
            return ['ok' => false, 'message' => '该密码为常见弱口令，禁止使用'];
        }
        // 禁止纯重复/连续字符（如 aaaaaaaa、12341234）
        if (preg_match('/^(.)\\1+$/', $password) || preg_match('/(0123|1234|2345|3456|4567|5678|6789|abcd|qwer)/i', $password)) {
            return ['ok' => false, 'message' => '密码过于简单（连续字符），请更换'];
        }
        if ($username !== '' && stripos($password, $username) !== false) {
            return ['ok' => false, 'message' => '密码不能包含用户名'];
        }
        return ['ok' => true, 'message' => ''];
    }

    /**
     * 是否仍为出厂默认账号（admin + admin123）
     * @param array<string, mixed> $user
     */
    public static function isDefaultAccount(array $user): bool
    {
        return ($user['username'] ?? '') === 'admin'
            && password_verify('admin123', (string) ($user['password_hash'] ?? ''));
    }

    /**
     * 检查是否处于锁定状态
     */
    public static function isLocked(string $ip, string $username): bool
    {
        $row = Database::selectOne(
            'SELECT locked_until FROM login_throttle WHERE ip = :ip AND username = :username',
            ['ip' => $ip, 'username' => $username]
        );
        if ($row === null || $row['locked_until'] === null) {
            return false;
        }
        return strtotime((string) $row['locked_until']) > time();
    }

    /**
     * 记录一次登录失败（达到阈值则锁定）
     */
    public static function recordFailure(string $ip, string $username): void
    {
        Database::execute(
            'INSERT INTO login_throttle (ip, username, failures, last_attempt)
             VALUES (:ip, :username, 1, :now)
             ON CONFLICT(ip, username) DO UPDATE SET
                failures = failures + 1,
                last_attempt = excluded.last_attempt',
            ['ip' => $ip, 'username' => $username, 'now' => now()]
        );

        $row = Database::selectOne(
            'SELECT failures FROM login_throttle WHERE ip = :ip AND username = :username',
            ['ip' => $ip, 'username' => $username]
        );
        if ($row !== null && (int) $row['failures'] >= self::MAX_FAILURES) {
            Database::execute(
                'UPDATE login_throttle SET locked_until = :locked WHERE ip = :ip AND username = :username',
                [
                    'locked' => date('Y-m-d H:i:s', time() + self::LOCK_SECONDS),
                    'ip' => $ip,
                    'username' => $username,
                ]
            );
        }
    }

    /**
     * 登录成功，清除失败计数
     */
    public static function clearFailures(string $ip, string $username): void
    {
        Database::execute(
            'DELETE FROM login_throttle WHERE ip = :ip AND username = :username',
            ['ip' => $ip, 'username' => $username]
        );
    }

    /**
     * 校验并消费一次性 nonce（防重放）。未提供 nonce 时跳过（向后兼容）。
     * @return array{ok: bool, message: string}
     */
    public static function verifyNonce(mixed $nonce, mixed $ts): array
    {
        $nonce = is_string($nonce) ? trim($nonce) : '';
        $ts = is_numeric($ts) ? (int) $ts : 0;

        if ($nonce === '' && $ts === 0) {
            return ['ok' => true, 'message' => '']; // 旧客户端兼容
        }
        if ($nonce === '' || !preg_match('/^[a-f0-9]{16,64}$/i', $nonce)) {
            return ['ok' => false, 'message' => '非法请求签名'];
        }
        if ($ts <= 0 || abs(time() - $ts) > self::NONCE_TTL) {
            return ['ok' => false, 'message' => '请求已过期，请刷新页面后重试'];
        }

        $exists = Database::selectOne('SELECT nonce FROM login_nonces WHERE nonce = :nonce', ['nonce' => $nonce]);
        if ($exists !== null) {
            return ['ok' => false, 'message' => '检测到重复提交（重放攻击），请刷新页面后重试'];
        }

        Database::execute(
            'INSERT OR IGNORE INTO login_nonces (nonce, expires_at) VALUES (:nonce, :expires)',
            ['nonce' => $nonce, 'expires' => date('Y-m-d H:i:s', time() + self::NONCE_TTL)]
        );

        // 顺带清理过期 nonce
        Database::execute('DELETE FROM login_nonces WHERE expires_at < :now', ['now' => now()]);

        return ['ok' => true, 'message' => ''];
    }

    /**
     * 重置密码文件是否仍存在于运行目录（backend/public/reset-password.php）
     */
    public static function resetFileExists(): bool
    {
        return is_file(dirname(__DIR__, 2) . '/public/reset-password.php');
    }
}

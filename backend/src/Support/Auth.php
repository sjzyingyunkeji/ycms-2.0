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
 * 令牌认证辅助（基于数据库的 Bearer Token）
 */
final class Auth
{
    /**
     * 默认账号门禁放行的路径（未修改默认用户名/密码前仅允许这些操作）
     */
    private const SETUP_ALLOWLIST = [
        '/api/health',
        '/api/auth/me',
        '/api/auth/logout',
        '/api/auth/credentials',
        '/api/settings', // GET 公开；PUT 会被门禁拦截
    ];

    /**
     * 为用户签发令牌（先清理该用户旧令牌）
     */
    public static function issueToken(int $userId): string
    {
        Database::execute('DELETE FROM api_tokens WHERE user_id = :user_id', ['user_id' => $userId]);
        $token = bin2hex(random_bytes(32));
        Database::insert('api_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + (int) config('jwt.ttl', 604800)),
            'created_at' => now(),
        ]);
        return $token;
    }

    /**
     * 通过令牌解析当前用户（优先 Cookie，兼容 Authorization 头）
     * @return array<string, mixed>|null
     */
    public static function user(Request $request): ?array
    {
        $token = $_COOKIE['ycms_token'] ?? null;
        if (!is_string($token) || $token === '') {
            $token = $request->bearerToken();
        }
        if ($token === null) {
            return null;
        }
        $row = Database::selectOne(
            'SELECT u.* FROM api_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token = :token AND t.expires_at > :now
             LIMIT 1',
            ['token' => $token, 'now' => now()]
        );
        return $row;
    }

    /**
     * 签发登录 Cookie（HttpOnly，24 小时）
     */
    public static function setLoginCookie(string $token): void
    {
        setcookie('ycms_token', $token, [
            'expires' => time() + (int) config('jwt.ttl', 86400),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? 'off') !== 'off'),
        ]);
    }

    /**
     * 清除登录 Cookie
     */
    public static function clearLoginCookie(): void
    {
        setcookie('ycms_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? 'off') !== 'off'),
        ]);
    }

    /**
     * 要求登录，否则 401；默认账号未初始化时拦截写操作
     * @return array<string, mixed>
     */
    public static function requireUser(Request $request): array
    {
        $user = self::user($request);
        if ($user === null) {
            Response::error('未登录或登录已过期', 401);
        }

        // 默认账号门禁：修改用户名/密码前禁止其他操作
        if (LoginGuard::isDefaultAccount($user)) {
            $method = $request->method;
            $path = $request->path;
            $allowed = in_array($path, self::SETUP_ALLOWLIST, true)
                && ($path !== '/api/settings' || $method === 'GET');
            if (!$allowed) {
                Response::error('请先修改默认用户名和密码后再使用系统', 403, ['code' => 'must_change_credentials']);
            }
        }

        return $user;
    }

    /**
     * 要求管理员角色
     * @return array<string, mixed>
     */
    public static function requireAdmin(Request $request): array
    {
        $user = self::requireUser($request);
        if (($user['role'] ?? '') !== 'admin') {
            Response::error('需要管理员权限', 403);
        }
        return $user;
    }
}

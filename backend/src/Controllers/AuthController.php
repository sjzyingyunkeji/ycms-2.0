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

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Support\LoginGuard;

/**
 * 认证控制器
 */
final class AuthController
{
    /**
     * 登录（限流 + 防重放 + 默认账号门禁标记）
     */
    public function login(Request $request): void
    {
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $ip = $this->clientIp($request);

        if ($username === '' || $password === '') {
            Response::error('请输入用户名和密码', 422);
        }
        if (mb_strlen($username) > 30 || mb_strlen($password) > 72) {
            Response::error('用户名或密码长度不合法', 422);
        }

        // 防重放：nonce + 时间戳
        $nonceCheck = LoginGuard::verifyNonce($request->input('nonce'), $request->input('ts'));
        if (!$nonceCheck['ok']) {
            Response::error($nonceCheck['message'], 429);
        }

        // 暴力破解锁定
        if (LoginGuard::isLocked($ip, $username)) {
            Response::error('失败次数过多，账号已锁定 15 分钟，请稍后再试', 429);
        }

        $user = Database::selectOne('SELECT * FROM users WHERE username = :username', ['username' => $username]);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            LoginGuard::recordFailure($ip, $username);
            Response::error('用户名或密码错误', 401);
        }

        // 自动升级旧哈希
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_BCRYPT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_BCRYPT)], 'id = :id', ['id' => $user['id']]);
        }

        LoginGuard::clearFailures($ip, $username);
        $token = Auth::issueToken((int) $user['id']);
        Auth::setLoginCookie($token);

        // 令牌只写入 HttpOnly Cookie，不回传前端，避免被脚本读取
        Response::success([
            'user' => $this->publicUser($user),
            'must_change_credentials' => LoginGuard::isDefaultAccount($user),
            'reset_file_exists' => LoginGuard::resetFileExists(),
        ], '登录成功');
    }

    /**
     * 退出登录
     */
    public function logout(Request $request): void
    {
        $token = $_COOKIE['ycms_token'] ?? $request->bearerToken();
        if (is_string($token) && $token !== '') {
            Database::delete('api_tokens', 'token = :token', ['token' => $token]);
        }
        Auth::clearLoginCookie();
        Response::success(null, '已退出登录');
    }

    /**
     * 当前用户信息（含门禁与重置文件标记）
     */
    public function me(Request $request): void
    {
        $user = Auth::requireUser($request);
        Response::success([
            ...$this->publicUser($user),
            'must_change_credentials' => LoginGuard::isDefaultAccount($user),
            'reset_file_exists' => LoginGuard::resetFileExists(),
        ]);
    }

    /**
     * 公开：站点初始化状态（是否仍为默认账号，供登录页决定是否提示默认账号）
     */
    public function setupStatus(): void
    {
        $user = Database::selectOne("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
        $isDefault = $user !== null && LoginGuard::isDefaultAccount($user);
        Response::success([
            'default_credentials_active' => $isDefault,
        ]);
    }

    /**
     * 强制初始化：修改用户名与密码（默认账号门禁放行）
     */
    public function credentials(Request $request): void
    {
        $user = Auth::requireUser($request);

        // 仅默认账号需要通过此接口初始化；非默认账号走个人资料/修改密码
        if (!LoginGuard::isDefaultAccount($user)) {
            Response::error('当前账号已完成初始化，请在个人资料中修改', 422);
        }

        $newUsername = trim((string) $request->input('username', ''));
        $newPassword = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirmation', '');

        $usernameCheck = LoginGuard::validateUsername($newUsername);
        if (!$usernameCheck['ok']) {
            Response::error($usernameCheck['message'], 422);
        }
        if ($newPassword !== $confirm) {
            Response::error('两次输入的密码不一致', 422);
        }
        $passwordCheck = LoginGuard::validatePassword($newPassword, $newUsername);
        if (!$passwordCheck['ok']) {
            Response::error($passwordCheck['message'], 422);
        }

        $duplicate = Database::selectOne(
            'SELECT id FROM users WHERE username = :username AND id != :id',
            ['username' => $newUsername, 'id' => $user['id']]
        );
        if ($duplicate !== null) {
            Response::error('该用户名已被占用', 422);
        }

        Database::update('users', [
            'username' => $newUsername,
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        ], 'id = :id', ['id' => $user['id']]);

        // 吊销旧令牌，强制使用新凭证重新登录
        Database::delete('api_tokens', 'user_id = :uid', ['uid' => $user['id']]);

        Response::success(null, '账号初始化完成，请使用新用户名和密码重新登录');
    }

    /**
     * 更新个人资料
     *
     * 博主信息（昵称/头像/简介/社交账号）统一在此维护，
     * 公开设置接口会把它们派生为 owner_* / social_* 供前台展示。
     */
    public function updateProfile(Request $request): void
    {
        $user = Auth::requireUser($request);

        $email = mb_substr(trim((string) $request->input('email', (string) $user['email'])), 0, 120);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('邮箱格式不正确', 422);
        }

        $data = [
            'nickname' => mb_substr(trim((string) $request->input('nickname', (string) $user['nickname'])), 0, 50),
            'email' => $email,
            'bio' => mb_substr((string) $request->input('bio', (string) $user['bio']), 0, 500),
            'avatar' => $this->sanitizeUrl($request->input('avatar', (string) $user['avatar']), true),
        ];

        // 社交账号：链接类只接受 http(s) 或站内绝对路径，二维码允许站内上传路径
        foreach (['social_github', 'social_weibo', 'social_zhihu', 'social_csdn', 'social_wechat'] as $key) {
            $data[$key] = $this->sanitizeUrl($request->input($key, (string) ($user[$key] ?? '')), false);
        }
        $data['social_wechat_qr'] = $this->sanitizeUrl($request->input('social_wechat_qr', (string) ($user['social_wechat_qr'] ?? '')), true);
        $socialEmail = trim((string) $request->input('social_email', (string) ($user['social_email'] ?? '')));
        $data['social_email'] = mb_substr($socialEmail, 0, 120);

        Database::update('users', $data, 'id = :id', ['id' => $user['id']]);
        Response::success($this->publicUser(Database::selectOne('SELECT * FROM users WHERE id = :id', ['id' => $user['id']]) ?? $user), '资料已更新');
    }

    /**
     * 站外链接只放行 http(s)，站内资源放行 / 开头的绝对路径
     */
    private function sanitizeUrl(mixed $value, bool $allowLocalPath): string
    {
        $value = trim(mb_substr((string) $value, 0, 500));
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if ($allowLocalPath && str_starts_with($value, '/')) {
            return $value;
        }
        return '';
    }

    /**
     * 修改密码（需旧密码；同样执行弱口令校验）
     */
    public function updatePassword(Request $request): void
    {
        $user = Auth::requireUser($request);
        $oldPassword = (string) $request->input('old_password', '');
        $newPassword = (string) $request->input('new_password', '');

        if ($oldPassword === '' || $newPassword === '') {
            Response::error('请输入旧密码和新密码', 422);
        }
        if (!password_verify($oldPassword, (string) $user['password_hash'])) {
            Response::error('旧密码错误', 401);
        }
        $passwordCheck = LoginGuard::validatePassword($newPassword, (string) $user['username']);
        if (!$passwordCheck['ok']) {
            Response::error($passwordCheck['message'], 422);
        }

        Database::update('users', ['password_hash' => password_hash($newPassword, PASSWORD_BCRYPT)], 'id = :id', ['id' => $user['id']]);
        Response::success(null, '密码修改成功，请重新登录');
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function publicUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }

    private function clientIp(Request $request): string
    {
        $forwarded = (string) ($request->header('X-Forwarded-For') ?? '');
        if ($forwarded !== '') {
            $ip = trim(explode(',', $forwarded)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return substr($ip, 0, 45);
            }
        }
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return substr($remote, 0, 45);
    }
}

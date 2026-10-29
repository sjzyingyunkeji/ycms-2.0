<?php

/*
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


/**
 * ============================================================
 * ycms2.0 应急密码重置工具（高危文件）
 * ============================================================
 *
 * 【用途】忘记管理员密码时，将本文件上传至 backend/public/ 目录，
 *         浏览器访问 https://你的域名/reset-password.php 即可重置。
 *
 * 【重置结果】首个管理员账号恢复为：
 *         用户名：admin
 *         密码：admin123
 *
 * 【安全警告】⚠️⚠️⚠️
 *   1. 本文件是“后门”级工具，任何能访问它的人都能重置你的密码！
 *   2. 重置完成后必须立即删除本文件（见页面大红字提示）。
 *   3. 平时请将此文件保存在项目 tools/ 目录中，不要上传到运行目录。
 *   4. ycms2.0 后台在登录成功后会检测运行目录中是否存在本文件并提醒删除。
 *
 * 【可选参数】?username=新用户名&password=新密码  可指定重置目标
 * ============================================================
 */

declare(strict_types=1);

// —— 独立引导（不依赖 composer / 自动加载） ——
require_once dirname(__DIR__) . '/src/Support/helpers.php';
require_once dirname(__DIR__) . '/src/Support/env.php';
require_once dirname(__DIR__) . '/src/Core/Database.php';

use App\Core\Database;

date_default_timezone_set('Asia/Shanghai');

header('X-Robots-Tag: noindex, nofollow');

$step = 'confirm'; // confirm → done
$message = '';
$messageType = 'info';

$customUsername = isset($_GET['username']) ? trim((string) $_GET['username']) : '';
$customPassword = isset($_GET['password']) ? (string) $_GET['password'] : '';

if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    try {
        // 目标账号：优先按 username 参数，否则取 ID 最小的管理员
        if ($customUsername !== '') {
            $user = Database::selectOne('SELECT * FROM users WHERE username = :u LIMIT 1', ['u' => $customUsername]);
            if ($user === null) {
                throw new RuntimeException('未找到用户名为 ' . htmlspecialchars($customUsername) . ' 的账号');
            }
        } else {
            $user = Database::selectOne("SELECT * FROM users ORDER BY id ASC LIMIT 1");
            if ($user === null) {
                throw new RuntimeException('数据库中没有任何用户账号');
            }
        }

        $newUsername = 'admin';
        if ($customUsername !== '') {
            $newUsername = $customUsername;
        } else {
            // 默认重置为 admin；若 admin 被其他账号占用则保留原用户名
            $taken = Database::selectOne(
                'SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1',
                ['u' => 'admin', 'id' => $user['id']]
            );
            if ($taken !== null) {
                $newUsername = (string) $user['username'];
            }
        }

        $newPassword = $customPassword !== '' ? $customPassword : 'admin123';

        Database::update('users', [
            'username' => $newUsername,
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        ], 'id = :id', ['id' => $user['id']]);

        // 吊销全部登录令牌，强制重新登录
        Database::execute('DELETE FROM api_tokens');

        $step = 'done';
        $message = '重置成功！账号「' . htmlspecialchars((string) $user['username']) . '」已恢复为：用户名 '
            . htmlspecialchars($newUsername) . ' / 密码 ' . htmlspecialchars($newPassword)
            . '。所有已登录会话已被吊销。';
        $messageType = 'success';
    } catch (\Throwable $e) {
        $step = 'done';
        $message = '重置失败：' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex, nofollow" />
    <title>ycms2.0 应急密码重置（高危）</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "PingFang SC", "Microsoft YaHei", system-ui, sans-serif;
            background: #0b0d1a; color: #e2e8f0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 24px;
        }
        .card {
            width: 100%; max-width: 640px; background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 40px;
            backdrop-filter: blur(8px);
        }
        h1 { font-size: 22px; margin-bottom: 8px; color: #f1f5f9; }
        .sub { color: #94a3b8; font-size: 13px; margin-bottom: 24px; }
        .warn {
            background: rgba(239, 68, 68, 0.12); border: 2px solid rgba(239, 68, 68, 0.55);
            border-radius: 12px; padding: 20px 24px; margin-bottom: 24px;
        }
        .warn .big {
            color: #fca5a5; font-size: 26px; font-weight: 800; letter-spacing: 1px;
            margin-bottom: 10px; line-height: 1.4;
        }
        .warn p { color: #fecaca; font-size: 14px; line-height: 1.9; }
        .warn code { background: rgba(0,0,0,0.35); padding: 2px 8px; border-radius: 6px; color: #fda4af; }
        .ok {
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.45);
            border-radius: 12px; padding: 16px 20px; color: #a7f3d0; font-size: 14px;
            line-height: 1.8; margin-bottom: 20px;
        }
        .err {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.45);
            border-radius: 12px; padding: 16px 20px; color: #fca5a5; font-size: 14px;
            line-height: 1.8; margin-bottom: 20px;
        }
        .actions { display: flex; gap: 12px; margin-top: 8px; }
        .btn {
            display: inline-block; padding: 12px 28px; border-radius: 10px; font-size: 15px;
            font-weight: 600; text-decoration: none; cursor: pointer; border: none;
        }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-ghost { background: rgba(255,255,255,0.06); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.15); }
        ul { margin: 12px 0 0 20px; color: #cbd5e1; font-size: 14px; line-height: 2; }
        .foot { margin-top: 24px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.08); color: #64748b; font-size: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>ycms2.0 应急密码重置</h1>
        <div class="sub">高危工具 · 仅限站长在服务器上临时使用</div>

        <?php if ($step === 'done'): ?>
            <?php if ($messageType === 'success'): ?>
                <div class="ok"><?= $message ?></div>
            <?php else: ?>
                <div class="err"><?= $message ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="warn">
            <div class="big">⚠️ 重置完成后，请立即删除本文件！⚠️</div>
            <p>
                本文件等同于一把“万能钥匙”，任何能访问此地址的人都能重置你的密码。<br />
                删除方法：连接服务器，进入 <code>backend/public/</code> 目录，删除 <code>reset-password.php</code>。<br />
                删除前请确认已能用新密码正常登录后台。
            </p>
        </div>

        <?php if ($step === 'confirm'): ?>
            <p style="color:#cbd5e1;font-size:14px;line-height:1.9;margin-bottom:16px;">
                点击下方按钮将执行以下操作：
            </p>
            <ul>
                <li>把<strong>首个管理员账号</strong>重置为 <strong>admin / admin123</strong></li>
                <li>吊销全部已登录会话（所有人需重新登录）</li>
                <li>登录后系统会<strong>强制要求修改默认用户名和密码</strong></li>
            </ul>
            <div class="actions">
                <a class="btn btn-danger" href="?confirm=yes">确认重置密码</a>
                <a class="btn btn-ghost" href="/admin/login">返回登录页</a>
            </div>
        <?php else: ?>
            <div class="actions">
                <a class="btn btn-ghost" href="/admin/login">前往后台登录</a>
            </div>
        <?php endif; ?>

        <div class="foot">
            ycms2.0 v2.0.1 · 本文件应存放于项目 tools/ 目录，仅在需要时临时放入运行目录。
        </div>
    </div>
</body>
</html>

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
 * 一键安装向导
 *
 * 部署步骤（无需修改 Nginx 配置）：
 *   1. 上传整个源码目录到服务器
 *   2. 站点「网站根目录」指向 public/
 *   3. 浏览器访问 /install.php → 填写站点与管理账号 → 完成
 */

$root = dirname(__DIR__);
$backend = $root . '/backend';
$storage = $backend . '/storage';
$dbFile = $storage . '/database.sqlite';
$lockFile = $storage . '/install.lock';
$envFile = $backend . '/.env';

define('INSTALLED', is_file($lockFile));

/* ==================== 环境检测 ==================== */
$checks = [
    ['PHP 版本 ≥ 8.2', PHP_VERSION_ID >= 80200, '当前 ' . PHP_VERSION],
    ['PDO SQLite 扩展', extension_loaded('pdo_sqlite'), '用于存储数据'],
    ['mbstring 扩展', extension_loaded('mbstring'), '用于中文处理'],
    ['fileinfo 扩展', extension_loaded('fileinfo'), '用于媒体类型识别'],
    ['JSON 扩展', extension_loaded('json'), '用于接口数据'],
    ['backend 目录可写', is_writable($backend), $backend],
    ['backend/storage 可写', is_dir($storage) ? is_writable($storage) : is_writable($backend), $storage],
    ['public 目录可写', is_writable(__DIR__), __DIR__],
];
$envOk = true;
foreach ($checks as $c) {
    if (!$c[1]) {
        $envOk = false;
    }
}

/* ==================== 工具函数 ==================== */
function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function nowStr(): string
{
    return date('Y-m-d H:i:s');
}

function slugifyText(string $text): string
{
    $text = trim(strip_tags($text));
    $text = preg_replace('/[\s\x{3000}]+/u', '-', $text) ?? $text;
    $text = preg_replace('/[^\p{L}\p{N}\-_]/u', '', $text) ?? $text;
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'untitled';
    }
    return mb_strtolower(mb_substr($text, 0, 60));
}

/* ==================== 处理提交 ==================== */
$error = '';
$done = false;

if (INSTALLED) {
    $error = '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim((string) ($_POST['site_name'] ?? ''));
    $ownerName = trim((string) ($_POST['owner_name'] ?? '站长'));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    if (!$envOk) {
        $error = '环境检测未通过，请先解决上方标红项。';
    } elseif ($siteName === '' || mb_strlen($siteName) > 40) {
        $error = '请填写站点名称（1-40 个字符）。';
    } elseif (!preg_match('/^[\p{L}\p{N}_\-]{3,30}$/u', $username)) {
        $error = '管理员账号需 3-30 位，仅限中英文、数字、下划线与连字符。';
    } elseif (in_array(strtolower($username), ['admin', 'root', 'administrator', 'test', 'guest', 'webmaster'], true)) {
        $error = '管理员账号不能使用 admin / root / test 等默认名称，请更换。';
    } elseif (mb_strlen($password) < 8) {
        $error = '密码至少 8 位。';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = '密码需同时包含字母和数字。';
    } elseif (stripos($password, $username) !== false) {
        $error = '密码不能包含账号名。';
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致。';
    } else {
        try {
            // 1) 目录与数据库
            foreach ([$storage, $storage . '/uploads', __DIR__ . '/storage/uploads'] as $dir) {
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new RuntimeException('无法创建目录：' . $dir);
                }
            }

            $pdo = new PDO('sqlite:' . $dbFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $schema = (string) file_get_contents($backend . '/database/schema.sql');
            $pdo->exec($schema);

            // 全新安装即为最新结构：把随包的增量迁移记为"已执行"，
            // 避免日后升级时把历史迁移重复跑一遍（如 ALTER TABLE 重复加列）
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                name TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )');
            $migrations = glob($backend . '/database/migrations/*.sql') ?: [];
            sort($migrations);
            $mark = $pdo->prepare('INSERT OR IGNORE INTO schema_migrations (name, applied_at) VALUES (:n, :t)');
            foreach ($migrations as $file) {
                $mark->execute(['n' => basename($file), 't' => nowStr()]);
            }

            // 2) 站点设置
            $settings = [
                'site_name' => $siteName,
                'site_title' => $siteName . ' · 在代码与星空之间',
                'site_description' => '一个记录技术、生活与星空的个人站点。',
                'site_keywords' => 'ycms,技术博客,星空',
                'site_icp' => '',
                'owner_name' => $ownerName !== '' ? $ownerName : '站长',
                'owner_bio' => '热爱技术的开发者，白天写代码，夜晚拍星星。',
                'owner_avatar' => '',
                'logo_type' => 'image',
                'logo_image' => '/logo.svg',
                'hero_suffix' => '的数字星空',
                'social_github' => '',
                'social_email' => '',
                'social_weibo' => '',
                'social_zhihu' => '',
                'social_csdn' => '',
                'social_wechat' => '',
                'social_wechat_qr' => '',
                'nav_links' => '[]',
                'site_closed' => '0',
                'site_closed_message' => '站点正在维护升级中，暂时关闭访问，请稍后再来。',
                'comment_audit' => '1',
                'posts_per_page' => '10',
                'home_post_sort' => 'hot',
                'track_enabled' => '1',
            ];
            $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)
                ON CONFLICT(key) DO UPDATE SET value = excluded.value');
            foreach ($settings as $k => $v) {
                $stmt->execute(['k' => $k, 'v' => $v]);
            }

            // 3) 管理员
            $pdo->prepare('DELETE FROM users')->execute();
            $pdo->prepare('INSERT INTO users (username, password_hash, nickname, role, created_at)
                VALUES (:u, :p, :n, :r, :c)')->execute([
                'u' => $username,
                'p' => password_hash($password, PASSWORD_BCRYPT),
                'n' => $ownerName !== '' ? $ownerName : $username,
                'r' => 'admin',
                'c' => nowStr(),
            ]);

            // 4) 初始分类与欢迎文章
            $adminId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO categories (name, slug, description, sort_order) VALUES (:n, :s, :d, 1)')
                ->execute(['n' => '随笔', 's' => 'essay', 'd' => '记录生活与思考']);
            $categoryId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO posts (title, slug, summary, content, category_id, author_id, status, is_top, views, published_at, created_at, updated_at)
                VALUES (:t, :s, :m, :c, :cat, :aid, :st, 1, 0, :p, :cr, :up)')->execute([
                't' => '欢迎来到 ' . $siteName,
                's' => 'welcome',
                'm' => '站点已安装完成，这是一篇示例文章，可在后台修改或删除。',
                'c' => "## 安装成功\n\n你的站点已经可以正常访问了。\n\n- 后台地址：`/admin/`\n- 修改内容：登录后台 → 文章管理\n- 站点名称、Logo、社交账号：后台 → 站点设置\n\n> 愿你的代码如星空般璀璨。",
                'cat' => $categoryId,
                'aid' => $adminId,
                'st' => 'published',
                'p' => nowStr(),
                'cr' => nowStr(),
                'up' => nowStr(),
            ]);

            $pdo->prepare('INSERT INTO pages (title, slug, content, status, sort_order, created_at, updated_at)
                VALUES (:t, :s, :c, :st, 0, :cr, :up)')->execute([
                't' => '关于我',
                's' => 'about',
                'c' => "## 你好\n\n这里可以写关于你自己的介绍，在后台「页面管理」中编辑。",
                'st' => 'published',
                'cr' => nowStr(),
                'up' => nowStr(),
            ]);

            // 5) 写入 .env
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $env = "APP_NAME=\"{$siteName}\"\n"
                . "APP_ENV=production\n"
                . "APP_URL={$scheme}://{$host}\n"
                . "APP_TIMEZONE=Asia/Shanghai\n"
                . "DB_PATH=storage/database.sqlite\n"
                . "JWT_SECRET=" . bin2hex(random_bytes(32)) . "\n"
                . "JWT_TTL=86400\n"
                . "UPLOAD_MAX_SIZE=10485760\n";
            file_put_contents($envFile, $env);

            // 6) 安装锁
            file_put_contents($lockFile, 'installed at ' . nowStr() . PHP_EOL);

            // 7) 生成静态页（零配置部署：无需服务器重写规则）
            try {
                require_once $backend . '/src/Support/helpers.php';
                require_once $backend . '/src/Support/env.php';
                spl_autoload_register(static function (string $class) use ($backend): void {
                    if (!str_starts_with($class, 'App\\')) {
                        return;
                    }
                    $file = $backend . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                    if (is_file($file)) {
                        require $file;
                    }
                });
                // 主题目录防护 + 当前主题兜底（发布包已内置 public/themes/default）
                \App\Support\Theme\ThemeManager::protectThemesDir();
                if (\App\Support\Theme\LegacyMigrator::needed()) {
                    // 老版本平铺产物：自动包装为 public/themes/<slug>/（幂等）
                    \App\Support\Theme\LegacyMigrator::run('default', false);
                }
                \App\Support\StaticBuilder::rebuild();
            } catch (Throwable $e) {
                // 静态化失败不影响安装结果，可在后台手动重建
            }

            $done = true;
        } catch (Throwable $ex) {
            $error = '安装失败：' . $ex->getMessage();
            @unlink($lockFile);
        }
    }
}

$pageTitle = INSTALLED ? '已安装' : '安装向导';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="robots" content="noindex, nofollow" />
<title><?= e($pageTitle) ?> · YCMS 2.0</title>
<style>
:root{--bg:#04061a;--card:rgba(255,255,255,.04);--line:rgba(255,255,255,.10);--text:#dbe2f3;--dim:#8b93ab;--violet:#8f83c9;--cyan:#6fa9bd;--ok:#5ec08a;--err:#e0708a}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:radial-gradient(1200px 600px at 50% -10%,rgba(143,131,201,.18),transparent 60%),var(--bg);color:var(--text);font-family:"PingFang SC","Microsoft YaHei",system-ui,sans-serif;display:flex;align-items:center;justify-content:center;padding:40px 16px}
.wrap{width:100%;max-width:680px}
.brand{text-align:center;margin-bottom:28px}
.brand h1{margin:0;font-size:26px;letter-spacing:2px;font-weight:700;background:linear-gradient(90deg,var(--violet),var(--cyan));-webkit-background-clip:text;background-clip:text;color:transparent}
.brand p{margin:8px 0 0;color:var(--dim);font-size:13px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px;backdrop-filter:blur(12px);margin-bottom:18px}
.card h2{margin:0 0 16px;font-size:15px;font-weight:600;color:#e8edfa}
table{width:100%;border-collapse:collapse;font-size:13px}
td{padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)}
td:last-child{text-align:right;color:var(--dim);font-size:12px}
.ok{color:var(--ok)} .bad{color:var(--err)}
label{display:block;font-size:12px;color:var(--dim);margin:0 0 6px}
input{width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:rgba(0,0,0,.25);color:var(--text);font-size:14px;outline:none;transition:.2s}
input:focus{border-color:rgba(143,131,201,.6);box-shadow:0 0 0 3px rgba(143,131,201,.12)}
.field{margin-bottom:14px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
button{width:100%;padding:12px;border:none;border-radius:10px;background:linear-gradient(90deg,var(--violet),var(--cyan));color:#0b0e20;font-weight:700;font-size:14px;cursor:pointer;transition:.2s}
button:hover{filter:brightness(1.1)}
button:disabled{opacity:.5;cursor:not-allowed}
.alert{padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert-err{background:rgba(224,112,138,.12);border:1px solid rgba(224,112,138,.4);color:#f3a9bb}
.alert-ok{background:rgba(94,192,138,.12);border:1px solid rgba(94,192,138,.4);color:#9fe3bd}
.tip{font-size:12px;color:var(--dim);line-height:1.8}
.tip code{background:rgba(255,255,255,.08);padding:1px 6px;border-radius:4px;font-size:11px}
a{color:#b9c6ee}
.step{font-size:11px;color:var(--dim);text-align:center;margin-top:14px}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <h1>YCMS 2.0</h1>
    <p>轻量内容管理系统 · 安装向导</p>
  </div>

<?php if (INSTALLED): ?>
  <div class="card">
    <div class="alert alert-ok">站点已完成安装。</div>
    <p class="tip">
      为安全起见，请删除 <code>public/install.php</code>（或将其改名），避免被他人重装。<br>
      如需重新安装，删除 <code>backend/storage/install.lock</code> 后刷新本页。
    </p>
    <p style="margin-top:18px"><a href="/admin/">→ 进入后台管理</a> &nbsp;·&nbsp; <a href="/">→ 访问前台</a></p>
  </div>
<?php elseif ($done): ?>
  <div class="card">
    <div class="alert alert-ok">安装成功！</div>
    <p class="tip">
      <b>重要：</b>请立即删除 <code>public/install.php</code>，防止站点被他人重新安装。<br><br>
      后台地址：<a href="/admin/">/admin/</a><br>
      前台首页：<a href="/">/</a><br><br>
      如果前台提示「前端资源缺失」，请在 frontend 目录执行 <code>npm install &amp;&amp; npm run build</code>。
    </p>
  </div>
<?php else: ?>
  <div class="card">
    <h2>环境检测</h2>
    <table>
      <?php foreach ($checks as $c): ?>
        <tr>
          <td class="<?= $c[1] ? 'ok' : 'bad' ?>"><?= $c[1] ? '✓' : '✗' ?> <?= e($c[0]) ?></td>
          <td><?= e((string) $c[2]) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-err"><?= e($error) ?></div>
  <?php endif; ?>

  <form class="card" method="post" autocomplete="off">
    <h2>站点信息</h2>
    <div class="field">
      <label>站点名称</label>
      <input name="site_name" value="<?= e((string) ($_POST['site_name'] ?? '我的星空')) ?>" placeholder="例如：我的星空" required maxlength="40" />
    </div>
    <div class="field">
      <label>站长昵称</label>
      <input name="owner_name" value="<?= e((string) ($_POST['owner_name'] ?? '星尘')) ?>" placeholder="展示在页脚的署名" maxlength="30" />
    </div>

    <h2 style="margin-top:22px">管理员账号</h2>
    <div class="field">
      <label>账号（不能用 admin / root 等默认名）</label>
      <input name="username" value="<?= e((string) ($_POST['username'] ?? '')) ?>" placeholder="3-30 位中英文/数字/下划线" required />
    </div>
    <div class="grid">
      <div class="field">
        <label>密码（≥8 位，含字母与数字）</label>
        <input type="password" name="password" required />
      </div>
      <div class="field">
        <label>确认密码</label>
        <input type="password" name="password2" required />
      </div>
    </div>

    <button type="submit" <?= $envOk ? '' : 'disabled' ?>>开始安装</button>
    <div class="step">安装会创建数据库、管理员与示例内容，不会覆盖已有站点数据（已安装时自动锁定）</div>
  </form>
<?php endif; ?>
</div>
</body>
</html>

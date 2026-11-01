-- ycms2.0 数据库结构（SQLite）

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    email TEXT DEFAULT '',
    nickname TEXT DEFAULT '',
    avatar TEXT DEFAULT '',
    bio TEXT DEFAULT '',
    social_github TEXT DEFAULT '',
    social_email TEXT DEFAULT '',
    social_weibo TEXT DEFAULT '',
    social_zhihu TEXT DEFAULT '',
    social_csdn TEXT DEFAULT '',
    social_wechat TEXT DEFAULT '',
    social_wechat_qr TEXT DEFAULT '',
    role TEXT NOT NULL DEFAULT 'admin',
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL DEFAULT '',
    description TEXT DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL DEFAULT '',
    summary TEXT DEFAULT '',
    content TEXT DEFAULT '',
    cover_image TEXT DEFAULT '',
    category_id INTEGER DEFAULT NULL,
    author_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    views INTEGER NOT NULL DEFAULT 0,
    is_top INTEGER NOT NULL DEFAULT 0,
    allow_comment INTEGER NOT NULL DEFAULT 1,
    published_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status);
CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category_id);

CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS post_tags (
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL DEFAULT '',
    content TEXT DEFAULT '',
    status TEXT NOT NULL DEFAULT 'published',
    views INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    original_name TEXT DEFAULT '',
    mime TEXT DEFAULT '',
    size INTEGER NOT NULL DEFAULT 0,
    path TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    nickname TEXT DEFAULT '',
    email TEXT DEFAULT '',
    content TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    ip TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_comments_post ON comments(post_id);

CREATE TABLE IF NOT EXISTS links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    description TEXT DEFAULT '',
    avatar TEXT DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT DEFAULT '',
    group_name TEXT DEFAULT 'site',
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- 登录限流（防暴力破解）：按 IP + 用户名记录失败次数与锁定
CREATE TABLE IF NOT EXISTS login_throttle (
    ip TEXT NOT NULL,
    username TEXT NOT NULL,
    failures INTEGER NOT NULL DEFAULT 0,
    last_attempt TEXT NOT NULL,
    locked_until TEXT DEFAULT NULL,
    PRIMARY KEY (ip, username)
);

-- 登录防重放：一次性 nonce（带过期时间）
CREATE TABLE IF NOT EXISTS login_nonces (
    nonce TEXT PRIMARY KEY,
    expires_at TEXT NOT NULL
);

-- 爬虫防护：公开接口按 IP 的分钟级计数与临时封禁
CREATE TABLE IF NOT EXISTS api_throttle (
    ip TEXT PRIMARY KEY,
    hits INTEGER NOT NULL DEFAULT 0,
    window_start INTEGER NOT NULL DEFAULT 0,
    banned_until INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT DEFAULT ''
);

-- 访问统计日志（全站页面 + 文章详情，含首页）
-- IP 仅以哈希形式存储，不落明文，保护访客隐私
CREATE TABLE IF NOT EXISTS visit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT NOT NULL DEFAULT '',
    post_id INTEGER DEFAULT NULL,
    page_type TEXT NOT NULL DEFAULT 'other',
    referer TEXT DEFAULT '',
    ref_host TEXT DEFAULT '',
    ip_hash TEXT DEFAULT '',
    ua TEXT DEFAULT '',
    device TEXT NOT NULL DEFAULT 'desktop',
    is_bot INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS idx_visit_log_created ON visit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_visit_log_post ON visit_log(post_id);
CREATE INDEX IF NOT EXISTS idx_visit_log_type ON visit_log(page_type);
CREATE INDEX IF NOT EXISTS idx_visit_log_ip ON visit_log(ip_hash);

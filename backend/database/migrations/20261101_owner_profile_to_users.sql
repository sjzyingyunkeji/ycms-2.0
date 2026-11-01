-- 博主资料并入用户表：站点设置中的 owner_* 与 social_* 迁到 users
-- 迁移后站点设置不再保存博主信息，公开设置接口改为从用户表派生（保持前台兼容）

ALTER TABLE users ADD COLUMN social_github TEXT DEFAULT '';
ALTER TABLE users ADD COLUMN social_email TEXT DEFAULT '';
ALTER TABLE users ADD COLUMN social_weibo TEXT DEFAULT '';
ALTER TABLE users ADD COLUMN social_zhihu TEXT DEFAULT '';
ALTER TABLE users ADD COLUMN social_csdn TEXT DEFAULT '';
ALTER TABLE users ADD COLUMN social_wechat TEXT DEFAULT '';
ALTER TABLE users ADD COLUMN social_wechat_qr TEXT DEFAULT '';

-- 历史数据搬迁：以最早创建的管理员为目标（单作者站点的实际使用者）
UPDATE users SET
    nickname = CASE
        WHEN TRIM(COALESCE(nickname, '')) = '' THEN COALESCE((SELECT value FROM settings WHERE key = 'owner_name'), nickname)
        ELSE nickname
    END,
    bio = CASE
        WHEN TRIM(COALESCE(bio, '')) = '' THEN COALESCE((SELECT value FROM settings WHERE key = 'owner_bio'), bio)
        ELSE bio
    END,
    avatar = CASE
        WHEN TRIM(COALESCE(avatar, '')) = '' THEN COALESCE((SELECT value FROM settings WHERE key = 'owner_avatar'), avatar)
        ELSE avatar
    END,
    social_github = COALESCE(NULLIF(social_github, ''), (SELECT value FROM settings WHERE key = 'social_github'), ''),
    social_email = COALESCE(NULLIF(social_email, ''), (SELECT value FROM settings WHERE key = 'social_email'), ''),
    social_weibo = COALESCE(NULLIF(social_weibo, ''), (SELECT value FROM settings WHERE key = 'social_weibo'), ''),
    social_zhihu = COALESCE(NULLIF(social_zhihu, ''), (SELECT value FROM settings WHERE key = 'social_zhihu'), ''),
    social_csdn = COALESCE(NULLIF(social_csdn, ''), (SELECT value FROM settings WHERE key = 'social_csdn'), ''),
    social_wechat = COALESCE(NULLIF(social_wechat, ''), (SELECT value FROM settings WHERE key = 'social_wechat'), ''),
    social_wechat_qr = COALESCE(NULLIF(social_wechat_qr, ''), (SELECT value FROM settings WHERE key = 'social_wechat_qr'), '')
WHERE id = (SELECT id FROM users ORDER BY id ASC LIMIT 1);

-- 邮箱：设置里的 social_email 若像邮箱则补进资料（旧版资料页邮箱常为空）
UPDATE users SET email = (SELECT value FROM settings WHERE key = 'social_email')
WHERE id = (SELECT id FROM users ORDER BY id ASC LIMIT 1)
  AND TRIM(COALESCE(email, '')) = ''
  AND (SELECT value FROM settings WHERE key = 'social_email') LIKE '%@%';

DELETE FROM settings WHERE key IN (
    'owner_name', 'owner_bio', 'owner_avatar',
    'social_github', 'social_email', 'social_weibo', 'social_zhihu',
    'social_csdn', 'social_wechat', 'social_wechat_qr'
);

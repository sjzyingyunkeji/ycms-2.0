# YCMS 2.0 API 文档

> 适用版本：**v2.0.2** · 最后更新：2026-10-29
> 后端实现：`backend/src/App.php`（路由）+ `backend/src/Controllers/`（控制器）
> 前端封装：`frontend/src/api/index.js`

YCMS 采用「前端静态文件 + PHP JSON 接口」的同源架构，接口路径统一为 `/api/...`，零配置部署下通过真实文件 `/api.php/...` 访问。

---

## 1. 接入说明

### 1.1 Base URL

| 部署方式 | 示例 |
| --- | --- |
| 零配置（默认） | `https://your-domain.com/api.php` |
| 配置了 URL 重写 | `https://your-domain.com/api` |
| 本地开发 | `http://127.0.0.1:8080/api.php` |

> **路径如何读**：下文章节中的 `/api/xxx` 是**路由路径**，真实请求地址 = Base URL + `/xxx`。
>
> | 文档中的路由 | 零配置部署（默认） | 配置了 `/api` 重写 |
> | --- | --- | --- |
> | `/api/track` | `POST /api.php/track` | `POST /api/track` |
> | `/api/posts` | `GET /api.php/posts` | `GET /api/posts` |
> | `/api/auth/login` | `POST /api.php/auth/login` | `POST /api/auth/login` |
>
> 也就是说：**没配重写时直接访问 `/api/track` 会 404**，请改用 `/api.php/track`；需要短地址请按 `docs/deployment.md` 的可选重写片段配置（Nginx 用 `location ^~ /api/`，Apache 用 `.htaccess`）。前端已统一走 `API_BASE`（默认 `/api.php`），无需改动。

### 1.2 请求格式

- 普通请求：`Content-Type: application/json`
- 文件上传：`Content-Type: multipart/form-data`
- 字符集：UTF-8
- 时间字段：`YYYY-MM-DD HH:MM:SS`，时区跟随 `app.timezone`（默认 `Asia/Shanghai`）

### 1.3 响应包络

成功：

```json
{
  "code": 0,
  "message": "操作成功",
  "data": {}
}
```

失败：

```json
{
  "code": 401,
  "message": "用户名或密码错误",
  "errors": null
}
```

分页：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "items": [],
    "total": 42,
    "page": 1,
    "size": 10,
    "pages": 5
  }
}
```

`code` 为 `0` 表示成功，非 0 时与 HTTP 状态码一致。前端拦截器以此判断成功与否。

---

## 2. 认证与安全

### 2.1 会话方式

登录成功后服务端写入 Cookie：

```
ycms_token=<token>; Path=/; HttpOnly; SameSite=Lax; Max-Age=86400
```

- 令牌保存在 `api_tokens` 表，有效期 24 小时
- 明文令牌**不会**出现在响应体中，正常使用只需让浏览器自动携带 Cookie
- 服务端同时兼容 `Authorization: Bearer <token>`，便于脚本/移动端调用
- 修改密码、重置密码会删除全部令牌，所有设备强制重新登录

### 2.2 权限分级

| 级别 | 说明 |
| --- | --- |
| 公开 | 无需登录，只返回已发布/已审核内容与白名单字段 |
| 登录 | 任意有效会话（当前系统只有 `admin` 角色） |
| 管理员 | `role = admin`，后台全部写操作 |

各接口所需级别见下表“权限”列：`公开` / `登录` / `管理员`。

### 2.3 CSRF 与 CORS

- 写请求（POST/PUT/DELETE）若携带 `Origin`，其主机必须与站点一致（含 `X-Forwarded-Host`、`APP_URL`），否则返回 **403**
- 默认不开放跨域；前后端分域部署时在 `backend/.env` 配置：

```env
CORS_ALLOWED_ORIGINS=https://front.example.com,https://admin.example.com
```

- 浏览器端跨域请求需带凭据：`fetch(url, { credentials: 'include' })`

### 2.4 登录保护

| 机制 | 规则 |
| --- | --- |
| 防重放 | 每次登录带一次性 `nonce`（32 位 hex）+ `ts`（秒级时间戳），5 分钟窗口，重复提交拒绝（429） |
| 限流 | 同 IP + 同用户名连续失败 5 次锁定 15 分钟（429） |
| 默认账号门禁 | 仍为 `admin/admin123` 时，除白名单接口外一律 403，`errors.code = must_change_credentials` |
| 密码存储 | `password_hash()` bcrypt，登录时自动升级旧哈希 |

---

## 3. 错误码

| HTTP | code | 场景示例 | 前端处理建议 |
| --- | --- | --- | --- |
| 200/201 | 0 | 成功 | 取 `data` |
| 400 | 400 | 参数格式错误 | 展示 `message` |
| 401 | 401 | 未登录、令牌过期、账号密码错误 | 登录页展示 `message`；其他页面跳登录 |
| 403 | 403 | 跨站来源、权限不足、默认账号门禁 | 展示 `message`；`errors.code=must_change_credentials` 跳初始化页 |
| 404 | 404 | 资源不存在 / 未发布 | 展示空状态 |
| 405 | 405 | 方法不允许 | 检查请求方法 |
| 422 | 422 | 校验失败（必填、长度、文件类型） | 展示 `message`，定位到字段 |
| 429 | 429 | 登录失败过多、nonce 重复、评论过频 | 展示 `message`，提示稍后再试 |
| 500 | 500 | 服务端异常（详情写日志） | 通用错误提示 |

服务端未捕获异常统一返回：

```json
{ "code": 500, "message": "服务器内部错误，请稍后重试", "errors": null }
```

详情见 `backend/storage/logs/app-YYYYMMDD.log`。

---

## 4. 分页与排序

| 参数 | 默认 | 范围 | 说明 |
| --- | --- | --- | --- |
| `page` | 1 | ≥1 | 页码 |
| `size` | 10（评论 20，媒体 24） | 1–50（评论/媒体 100） | 每页条数 |
| `sort` | `latest` | `latest` / `hot` / `top` | 仅文章列表；`top` 只返回置顶 |

排序规则：置顶文章永远优先；`latest` 按发布时间，`hot` 按阅读量。

---

## 5. 接口清单

### 5.1 健康检查

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/health` | 公开 | 服务存活探测 |

```json
{ "code": 0, "message": "服务正常", "data": { "status": "ok", "time": "2026-10-29 10:00:00", "php": "8.3.11" } }
```

### 5.2 认证

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| POST | `/api/auth/login` | 公开 | 登录 |
| POST | `/api/auth/logout` | 公开 | 退出并吊销当前令牌 |
| GET | `/api/auth/me` | 登录 | 当前登录用户 |
| GET | `/api/auth/setup-status` | 公开 | 是否仍为默认账号 |
| POST | `/api/auth/credentials` | 登录 | 修改用户名/密码（强制初始化） |
| PUT | `/api/auth/profile` | 登录 | 修改昵称、邮箱、简介、头像 |
| PUT | `/api/auth/password` | 登录 | 修改密码（需旧密码） |

**POST /api/auth/login**

```json
{
  "username": "holytreasure",
  "password": "your-password",
  "nonce": "9f2c4a7d1b3e5f60718293a4b5c6d7e8",
  "ts": 1793260800
}
```

响应：

```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "user": { "id": 1, "username": "holytreasure", "nickname": "星尘管理员", "email": "admin@stardust.dev", "avatar": "", "bio": "", "role": "admin", "created_at": "2026-10-27 15:57:53" },
    "must_change_credentials": false,
    "reset_file_exists": false
  }
}
```

注意：`user` 为 `users` 表去除 `password_hash` 后的字段；`must_change_credentials` 为 `true` 时前端应跳转 `/admin/#/setup`。

失败：`401 用户名或密码错误`、`429 失败次数过多，账号已锁定 15 分钟`。

**POST /api/auth/credentials**

```json
{ "username": "new-admin", "password": "Passw0rd!", "password_confirmation": "Passw0rd!" }
```

用户名不得为 `admin/root/test`，密码 ≥8 位且须含字母与数字，不得包含用户名。

**PUT /api/auth/profile**

```json
{
  "nickname": "星尘",
  "email": "me@example.com",
  "bio": "写代码，也拍星星",
  "avatar": "/storage/uploads/202610/avatar.jpg",
  "social_github": "https://github.com/xxx",
  "social_email": "me@example.com",
  "social_weibo": "https://weibo.com/xxx",
  "social_zhihu": "https://zhihu.com/people/xxx",
  "social_csdn": "https://blog.csdn.net/xxx",
  "social_wechat": "https://mp.weixin.qq.com/xxx",
  "social_wechat_qr": "/storage/uploads/202610/wechat-qr.png"
}
```

博主信息（昵称 / 头像 / 简介 / 7 个社交账号）统一在此维护：链接类只接受 `http(s)://`，头像与二维码额外接受 `/` 开头的站内路径，其余值会被清空。`GET /api/settings` 会把这些字段派生为 `owner_*` / `social_*` 输出，前台无需改动。

**PUT /api/auth/password**

```json
{ "old_password": "old", "new_password": "new-pass-123" }
```

修改成功会吊销全部令牌，需重新登录。

### 5.3 仪表盘

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/dashboard/stats` | 登录 | 后台首页统计 |

响应 `data`：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `cards` | array | 四张主卡：文章总数、总浏览量、评论数、媒体文件（含 `label/value/hint`） |
| `extras` | array | 分类、标签、页面数量 |
| `recent_posts` | array | 最近 5 篇文章 |
| `recent_comments` | array | 最近 5 条评论 |
| `publish_trend` | array | 近 30 天发布量 `[{date,count}]` |

### 5.4 访问统计

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| POST | `/api/track` | 公开 | 埋点上报（前端自动调用） |
| GET | `/api/stats/overview?days=30` | 管理员 | 全站统计 |
| GET | `/api/stats/post/{id}?days=30` | 管理员 | 单篇统计 |

**POST /api/track**

```json
{ "path": "/posts/1", "post_id": 1, "referer": "https://www.google.com/" }
```

服务端从请求头读取 UA 计算设备类型，IP 仅存哈希；同一访客同一页面 30 分钟内去重。

**GET /api/stats/overview** 响应 `data`：

```json
{
  "total_pv": 1280, "total_uv": 342,
  "today_pv": 96, "today_uv": 61,
  "yesterday_pv": 88, "yesterday_uv": 54,
  "bot_pv": 12,
  "trend": [{ "date": "2026-10-29", "pv": 96, "uv": 61 }],
  "hot_posts": [{ "id": 1, "title": "…", "views": 128, "visits": 210, "unique_visitors": 88 }],
  "hot_pages": [{ "path": "/", "page_type": "home", "pv": 320, "uv": 120 }],
  "referers": [{ "host": "www.google.com", "pv": 42 }],
  "devices": [{ "device": "desktop", "pv": 900 }]
}
```

`days` 取值 1–365，默认 30，趋势数组会补齐空缺日期。

**GET /api/stats/post/{id}** 返回该文章的 `pv/uv/trend/referers`。

### 5.5 文章

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/posts` | 公开 | 列表（未登录只返回已发布） |
| GET | `/api/posts/{id}` | 公开 | 详情，`{id}` 可为数字 ID 或 slug；草稿需登录 |
| POST | `/api/posts` | 管理员 | 新建 |
| PUT | `/api/posts/{id}` | 管理员 | 更新 |
| DELETE | `/api/posts/{id}` | 管理员 | 删除（同时删除评论与标签关联） |
| POST | `/api/posts/{id}/view` | 公开 | 阅读量 +1 |
| POST | `/api/posts/{id}/top` | 管理员 | 置顶切换 |

**GET /api/posts** 查询参数：

| 参数 | 说明 |
| --- | --- |
| `page` / `size` | 分页，`size` ≤50 |
| `status` | 仅登录后有效：`draft` / `published` |
| `category_id` | 分类筛选 |
| `tag_id` | 标签筛选 |
| `keyword` | 标题/摘要/正文模糊搜索 |
| `sort` | `latest` / `hot` / `top` |

列表项在 `posts` 字段基础上追加：

- `category_name`：分类名
- `author_name`：作者昵称
- `tags`：`[{id,name,slug}]`
- `comment_count`：已审核评论数

> **列表不含正文**：`GET /api/posts` 只返回 `id/title/slug/summary/cover_image/category_id/author_id/status/is_top/allow_comment/views/published_at/created_at/updated_at` 与上述追加字段，`content` 仅在 `GET /api/posts/{id}` 返回，避免列表把整库正文一次性吐给前端。
>
> **摘要自动生成**：`summary` 留空时后端会从正文剥离 Markdown 语法后截取 160 字写入；历史文章在首次被列表/详情读取时自动补齐一次。

**POST /api/posts** / **PUT /api/posts/{id}**

```json
{
  "title": "在代码与星空之间",
  "slug": "between-code-and-stars",
  "summary": "一句话摘要",
  "content": "# 支持 Markdown\n\n正文…",
  "cover_image": "/storage/uploads/202610/cover-1.jpg",
  "category_id": 1,
  "status": "published",
  "is_top": false,
  "allow_comment": true,
  "tag_ids": [1, 3]
}
```

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `title` | 是 | 1–200 字 |
| `slug` | 否 | 仅允许字母、数字、中文、`-_`；留空自动生成；重复返回 422 |
| `content` | 否 | Markdown 源文 |
| `status` | 否 | `draft`（默认）/ `published`；首次发布自动写 `published_at` |
| `tag_ids` | 否 | 标签 ID 数组，覆盖式更新 |

**POST /api/posts/{id}/top**

```json
{ "is_top": true }
```

省略 `is_top` 则切换当前状态。

### 5.6 分类

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/categories` | 公开 | 列表，含 `post_count` |
| POST | `/api/categories` | 管理员 | 新建 |
| PUT | `/api/categories/{id}` | 管理员 | 更新 |
| DELETE | `/api/categories/{id}` | 管理员 | 删除，文章分类置空 |

```json
{ "name": "技术", "slug": "tech", "description": "技术笔记", "sort_order": 10 }
```

### 5.7 标签

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/tags` | 公开 | 列表，含 `post_count` |
| POST | `/api/tags` | 管理员 | 新建 |
| PUT | `/api/tags/{id}` | 管理员 | 更新 |
| DELETE | `/api/tags/{id}` | 管理员 | 删除，解除文章关联 |

```json
{ "name": "Vue", "slug": "vue" }
```

### 5.8 页面

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/pages` | 公开 | 列表；未登录只返回已发布，按 `sort_order` 升序 |
| GET | `/api/pages/{id}` | 公开 | 详情，支持 ID 或 slug |
| POST | `/api/pages` | 管理员 | 新建 |
| PUT | `/api/pages/{id}` | 管理员 | 更新 |
| DELETE | `/api/pages/{id}` | 管理员 | 删除 |

```json
{ "title": "关于", "slug": "about", "content": "…", "status": "published", "sort_order": 1 }
```

### 5.9 媒体

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/media?page=1&size=24` | 管理员 | 媒体列表 |
| POST | `/api/media` | 管理员 | 上传（multipart，字段名 `file`） |
| DELETE | `/api/media/{id}` | 管理员 | 删除记录与文件 |

上传限制：

- 扩展名：`jpg jpeg png gif webp pdf txt md`（**不支持 SVG**）
- MIME 与扩展名双向校验，图片额外用 `getimagesize()` 校验内容
- 单文件 ≤10MB（`upload.max_size`，前端会先压缩至 800KB 内）
- 文件名随机生成，存储于 `public/storage/uploads/YYYYMM/`
- 返回的 `path` 可直接用于 `cover_image`、`avatar`、文章正文图片

```json
{ "id": 7, "filename": "20261029_a1b2c3d4.jpg", "original_name": "cover.jpg", "mime": "image/jpeg", "size": 182344, "path": "/storage/uploads/202610/20261029_a1b2c3d4.jpg" }
```

### 5.10 评论

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/comments?post_id=1` | 公开 | 该文章已审核评论 |
| GET | `/api/comments?page=1&size=20&status=pending` | 管理员 | 后台分页（含邮箱、待审核） |
| POST | `/api/comments` | 公开 | 提交评论 |
| PUT | `/api/comments/{id}` | 管理员 | 审核 |
| DELETE | `/api/comments/{id}` | 管理员 | 删除 |

**POST /api/comments**

```json
{ "post_id": 1, "nickname": "林间有风", "email": "lin@example.com", "content": "写得很好" }
```

- `nickname` 1–30 字，`content` 1–1000 字
- 需要文章 `allow_comment = 1`
- 审核开关 `comment_audit=1` 时状态为 `pending`，否则直接 `approved`
- 频率限制：同 IP 同文章 60 秒 1 条，24 小时最多 20 条（429）

响应：`{ "id": 12, "status": "pending" }`

**PUT /api/comments/{id}**

```json
{ "status": "approved" }
```

`status` 仅接受 `pending` / `approved` / `spam`。

### 5.11 友链

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/links` | 公开 | 列表；未登录只返回 `active` |
| POST | `/api/links` | 管理员 | 新建 |
| PUT | `/api/links/{id}` | 管理员 | 更新 |
| DELETE | `/api/links/{id}` | 管理员 | 删除 |

```json
{ "name": "示例站点", "url": "https://example.com", "description": "一个示例", "avatar": "https://example.com/logo.png", "sort_order": 1, "status": "active" }
```

`url` 必须为 `http(s)://` 开头，`status` 为 `active` / `inactive`。

### 5.12 站点设置

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/settings` | 公开 | 前台所需设置（白名单） |
| PUT | `/api/settings` | 管理员 | 批量保存 |

**GET /api/settings** 返回字段：

```json
{
  "site_name": "ycms2.0",
  "site_title": "ycms2.0 · 在代码与星空之间",
  "site_description": "…",
  "site_keywords": "…",
  "site_icp": "",
  "owner_name": "星尘",
  "owner_bio": "…",
  "owner_avatar": "",
  "logo_type": "image",
  "logo_image": "/logo.svg",
  "hero_suffix": "的数字星空",
  "social_github": "", "social_email": "", "social_weibo": "", "social_zhihu": "", "social_csdn": "",
  "social_wechat": "", "social_wechat_qr": "",
  "nav_links": [{ "name": "友站", "url": "https://example.com" }],
  "site_closed": "0",
  "site_closed_message": "…",
  "comment_audit": "1",
  "posts_per_page": "10",
  "home_post_sort": "hot",
  "track_enabled": "1"
}
```

**PUT /api/settings**

```json
{
  "settings": {
    "site_name": "我的站点",
    "posts_per_page": "12",
    "comment_audit": "0"
  }
}
```

仅以下键可写（其余忽略）：

```
site_name site_title site_description site_keywords site_icp
logo_type logo_image hero_suffix
nav_links site_closed site_closed_message comment_audit posts_per_page home_post_sort track_enabled
antibot_enabled antibot_limit antibot_ban_minutes
```

`nav_links` 接受数组或 JSON 字符串。

> **注意**：`owner_name` / `owner_bio` / `owner_avatar` 与 7 个 `social_*` 已从站点设置迁入**用户表**，`GET /api/settings` 仍会派生输出这些键（前台无需改动），但 `PUT /api/settings` 不再接受它们——请改用 `PUT /api/auth/profile` 维护博主信息。

### 5.13 爬虫防护

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/settings/antibot` | 管理员 | 当前开关、阈值与封禁列表 |
| DELETE | `/api/settings/antibot` | 管理员 | 解除全部封禁 |

规则：只统计**匿名**访问的公开接口（`posts` / `categories` / `tags` / `pages` / `comments` / `links` / `settings` / `track` / `health` / `auth/setup-status`），按 IP 每分钟计数，超过 `antibot_limit`（默认 60）后封禁 `antibot_ban_minutes`（默认 10）分钟，返回 `429` 并带 `Retry-After`。后台登录态不参与统计，避免误伤编辑器。

**GET /api/settings/antibot** 响应：

```json
{
  "enabled": true,
  "limit": 60,
  "ban_minutes": 10,
  "banned": [{ "ip": "1.2.3.4", "hits": 0, "window_start": 1793537600, "banned_until": 1793538200 }]
}
```

### 5.13 系统维护与更新

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| POST | `/api/system/rebuild-static` | 管理员 | 重建全部静态页 |
| GET | `/api/system/update` | 管理员 | 更新状态（缓存，不发网络请求） |
| POST | `/api/system/update/check` | 管理员 | 检查更新 |
| POST | `/api/system/update/run` | 管理员 | 执行单个升级步骤 |
| GET | `/api/system/update/backups` | 管理员 | 备份列表 |
| POST | `/api/system/update/rollback` | 管理员 | 回滚 |
| POST | `/api/system/update/upload` | 管理员 | 上传离线包（multipart，字段名 `package`） |

**POST /api/system/update/check**

```json
{ "force": true }
```

响应 `data` 关键字段：`current`、`latest`、`has_update`、`channel`、`changelog[]`、`published_at`、`breaking`、`min_version`、`blocked`、`block_reason`、`offline`、`last_checked_text`、`needs_check`、`package`。

**POST /api/system/update/run**

```json
{ "step": "download" }
```

步骤顺序：`download` → `extract` → `backup` → `apply` → `migrate` → `finalize`，每步返回：

```json
{ "code": 0, "message": "已更新 84 个文件", "data": { "ok": true, "step": "apply", "percent": 75, "message": "已更新 84 个文件" } }
```

- `percent` 依次为 25 / 40 / 55 / 75 / 88 / 100
- 前端需按顺序串行调用，禁止并发
- 跨代升级时若当前版本低于 `min_version`，返回 500 并提示先升级到中间版本
- 离线包模式会跳过 `download`

**POST /api/system/update/rollback**

```json
{ "name": "code-2.0.1-20261029-101500.zip", "with_db": false }
```

`with_db=true` 时一并恢复当时的数据库快照。

**GET /api/system/update** 额外返回环境信息：`php`、`zip`（ZipArchive 是否可用）、`sodium`、`storage_writable`、`root_writable`、`max_upload`、`backups[]`、`steps[]`。

### 5.14 主题

| 方法 | 路径 | 权限 | 说明 |
| --- | --- | --- | --- |
| GET | `/api/system/themes` | 管理员 | 当前主题、已安装列表、`switch_supported` |
| POST | `/api/system/themes/install` | 管理员（**Pro**） | 安装主题包，multipart 字段 `package` |
| POST | `/api/system/themes/activate` | 管理员（**Pro**） | 切换主题 `{ "slug": "star" }`，自动重建静态页 |

- 开源发行产物**不包含**后两个接口：路由由 `backend/config/modules.php` 挂载，Pro 发行才会注册；开源版调用返回 404。
- `switch_supported=false` 表示当前为单主题部署，主题随发行产物确定。
- 主题包需含 `theme.json` 与构建后的 `index.html`，安装到 `public/themes/<slug>/`，单包上限 30MB，禁止 PHP/隐藏文件/符号链接（依赖 PHP `zip` 扩展）。

### 5.15 SEO / 服务端渲染路由

| 方法 | 路径 | 权限 | 返回 |
| --- | --- | --- | --- |
| GET | `/post/{slug}` | 公开 | 完整 HTML 文章页（meta/OG/canonical/JSON-LD） |
| GET | `/sitemap.xml` | 公开 | XML 站点地图 |
| GET | `/robots.txt` | 公开 | 纯文本 |

这三个路由输出 HTML/XML/纯文本，不是 JSON。站点已安装时，`/post/{slug}` 通常由发布时生成的静态文件直接命中，PHP 路由仅作兜底。

---

## 6. 数据模型

| 表 | 关键字段 |
| --- | --- |
| `users` | id, username(唯一), password_hash, email, nickname, avatar, bio, role, created_at |
| `api_tokens` | id, user_id, token(唯一), expires_at, created_at |
| `posts` | id, title, slug, summary, content, cover_image, category_id, author_id, status, views, is_top, allow_comment, published_at, created_at, updated_at |
| `categories` | id, name, slug, description, sort_order, created_at |
| `tags` | id, name, slug, created_at |
| `post_tags` | post_id, tag_id（联合主键） |
| `pages` | id, title, slug, content, status, views, sort_order, created_at, updated_at |
| `media` | id, filename, original_name, mime, size, path, created_at |
| `comments` | id, post_id, nickname, email, content, status, ip, created_at |
| `links` | id, name, url, description, avatar, sort_order, status, created_at |
| `settings` | key(主键), value, group_name, updated_at |
| `visit_log` | id, path, post_id, page_type, referer, ref_host, ip_hash, ua, device, is_bot, created_at |
| `login_throttle` | ip, username, failures, last_attempt, locked_until |
| `login_nonces` | nonce, expires_at |
| `schema_migrations` | name(主键), applied_at |

完整定义见 `backend/database/schema.sql`。

---

## 7. 调用示例

### 7.1 登录并保持会话（浏览器）

```js
await fetch('/api.php/auth/login', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    username: 'holytreasure',
    password: 'your-password',
    nonce: crypto.randomUUID().replace(/-/g, ''),
    ts: Math.floor(Date.now() / 1000),
  }),
})
```

### 7.2 创建文章

```js
await fetch('/api.php/posts', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    title: '在代码与星空之间',
    content: '# 你好\n\n这是正文。',
    status: 'published',
    tag_ids: [1, 3],
  }),
})
```

### 7.3 脚本调用（Bearer）

```bash
curl -X POST https://your-domain.com/api.php/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"***","nonce":"<32位hex>","ts":1793260800}' \
  -c cookie.txt

curl https://your-domain.com/api.php/auth/me -b cookie.txt
```

---

## 8. 变更记录

| 日期 | 变更 |
| --- | --- |
| 2026-10-29 | 首次整理完整 API 文档；补充更新/回滚、离线包上传、统计字段说明 |
| 2026-10-28 | 登录响应不再返回明文令牌；写接口统一管理员权限；新增同源 CSRF 校验 |
| 2026-10-28 | 媒体接口移除 SVG 支持；评论提交增加频率限制 |

---

## 9. 相关文档

- 功能与模块说明：[features.md](features.md)
- 部署指南：[deployment.md](deployment.md)
- 项目总览：[../README.md](../README.md)

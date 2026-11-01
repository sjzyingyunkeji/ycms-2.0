# YCMS 2.0 功能与模块说明

> 适用版本：**v2.0.2** · 最后更新：2026-10-29
> 后端实现：`backend/src/App.php`、`backend/src/Controllers/`、`backend/src/Support/`
> 前端实现：`frontend/site/`（内置主题）、`frontend/admin/`（后台）、`frontend/core/`（共享逻辑）
> 接口细节见 [api.md](api.md)，部署步骤见 [deployment.md](deployment.md)

本文按「角色 → 前台 → 后台 → 数据 → 机制」的顺序说明系统实际具备的能力，所有描述均对应仓库中的实现，不包含规划中功能。

---

## 1. 定位与技术架构

YCMS 是一个以「个人站点」为默认形态的轻量内容管理系统：前台是可静态化的展示站，后台是单管理员的内容管理面板。

| 层次 | 技术选型 | 说明 |
| --- | --- | --- |
| 前台/后台 | Vue 3 + Vite + Vue Router + Pinia + Tailwind CSS | 两个独立构建入口，后台产物在 `/admin/`，与前台解耦 |
| 后端 | PHP 8.2+，无框架自研 MVC | 严格类型、构造器属性提升、`match` 等现代语法 |
| 数据库 | SQLite（PDO + WAL + 外键） | 单文件 `backend/storage/database.sqlite`，备份即复制文件 |
| 接口 | JSON，统一包络 `{ code, message, data }` | 路由集中注册于 `App::registerRoutes()` |
| 部署 | 前端静态文件 + PHP 同源 | 零配置：API 走真实文件 `/api.php/...`，无需伪静态 |

**零配置部署的关键设计**：

1. 前台列表页/文章页/独立页面在发布时**预生成真实 HTML 文件**（`public/posts/index.html`、`public/post/<slug>/index.html` 等），Web 服务器直接命中静态文件，不依赖重写规则。
2. 后台使用 **hash 路由**（`/admin/#/posts`），整个目录是纯静态资源。
3. 媒体文件存放于 `public/storage/`，由 Web 服务器直接托管；PHP 侧另有 `/storage/*` 兜底与安全检查。

---

## 2. 角色与权限

系统只有「未登录访客」和「管理员」两类身份，没有多角色/多作者协作模型。

| 能力 | 访客 | 管理员 |
| --- | --- | --- |
| 浏览已发布文章 / 页面 / 归档 / 友链 | 是 | 是 |
| 查看已审核评论 | 是 | 是（并可查看待审核、垃圾评论与邮箱） |
| 提交评论 | 是（受频率限制） | 是 |
| 上报访问埋点 | 是 | 是 |
| 读取公开站点设置 | 是（白名单字段） | 是（完整设置） |
| 草稿/文章增删改、分类标签页面媒体友链管理 | 否 | 是 |
| 评论审核、系统更新、静态页重建 | 否 | 是 |

**鉴权方式**：登录成功后服务端签发 24 小时有效的 `HttpOnly + SameSite=Lax` Cookie `ycms_token`，同时兼容 `Authorization: Bearer <token>`（便于脚本/CLI 调用）。写接口由 `Auth::requireAdmin()` 强制校验；公开接口只输出白名单字段。

**默认账号门禁**：账号仍为 `admin / admin123` 时，除白名单接口外一律返回 403，`errors.code = must_change_credentials`；后台路由守卫会把所有页面重定向到 `/admin/#/setup`，必须同时修改用户名与密码后才能继续使用。

---

## 3. 前台功能

### 3.1 页面与路由

| 路径 | 视图组件 | 说明 |
| --- | --- | --- |
| `/` | `site/HomeView.vue` | Hero、站点数据、关于卡片、热门/最新文章、友链、友站 |
| `/posts` | `site/PostListView.vue` | 文章列表：分类筛选、分页、最新/热门排序 |
| `/posts/:id` | `site/PostDetailView.vue` | 按 ID 打开文章详情 |
| `/post/:slug` | `site/PostDetailView.vue` | 可读地址，服务端渲染版本同 URL |
| `/archive` | `site/ArchiveView.vue` | 按年份分组的时间轴归档 |
| `/links` | `site/LinksView.vue` | 友链列表 |
| `/page/:slug` | `site/PageView.vue` | 独立页面（关于我、留言板等） |
| `/category/:id` | `site/PostListView.vue` | 分类文章列表 |
| `/tag/:id` | `site/PostListView.vue` | 标签文章列表 |
| 其他 | `site/NotFoundView.vue` | 404 页 |
| 关站时 | `site/SiteClosedView.vue` | 一键关站后的提示页（后台仍可登录） |

### 3.2 文章列表与详情

- **列表**：支持 `category_id`、`tag_id`、`keyword`、`sort`（`latest` / `hot` / `top`），置顶文章始终优先，组内再按所选方式排序。
- **详情**：Markdown 渲染（DOMPurify 净化）、封面、分类与标签、浏览量、上下篇导航、相关文章（同标签优先 → 同分类 → 阅读量）。
- **评论**：昵称/邮箱/内容；昵称留空记为「匿名旅人」，内容上限 1000 字符；是否审核由站点设置 `comment_audit` 控制；单篇文章可单独关闭评论（`allow_comment`）。
- **浏览计数**：`POST /api/posts/{id}/view`，与服务端渲染页面的 `visit_log` 记录相互独立（前者写 `posts.views`，后者写访问明细）。

### 3.3 站点级展示能力

| 功能 | 行为 |
| --- | --- |
| 站点头部 | 文字 Logo 或图片 Logo（`logo_type` = `text` / `image`），Hero 后缀文案 `hero_suffix` |
| 社交账号 | GitHub、邮箱、微博、知乎、CSDN、微信公众号（支持二维码图片 `social_wechat_qr`） |
| 友站下拉 | `nav_links` 为 `[{name,url}]` JSON，最多 10 条，URL 需通过 `FILTER_VALIDATE_URL` |
| 备案信息 | `site_icp` 显示于页脚 |
| 关站模式 | `site_closed=1` 时前台展示关站文案 `site_closed_message`，后台不受影响 |
| 首页排序 | `home_post_sort` = `hot`（按阅读量）或 `latest`（按发布时间） |
| 访问统计开关 | `track_enabled=0` 时前后台均停止写入 `visit_log` |

---

## 4. 后台功能

后台入口 `/admin/`，登录页 `/admin/#/login`，初始化页 `/admin/#/setup`。

| 模块 | 路由 | 核心能力 |
| --- | --- | --- |
| 登录 | `#/login` | 用户名密码、nonce 防重放、失败锁定提示、默认账号提示 |
| 账号初始化 | `#/setup` | 强制修改用户名与密码，校验弱口令与用户名包含关系 |
| 仪表盘 | `#/` | 统计卡片、30 天发布趋势、最近文章与评论 |
| 访问统计 | `#/stats` | 全站 PV/UV、今日/昨日对比、PV+UV 双线趋势、热门文章 Top10、热门页面、来源站点、设备分布 |
| 文章管理 | `#/posts` | 搜索、状态/分类筛选、置顶一键切换、删除确认、分页 |
| 文章编辑 | `#/posts/new`、`#/posts/:id/edit` | Markdown 编辑/预览、封面、摘要、别名、分类、标签、置顶、评论开关、草稿/发布；保存后自动重建静态页 |
| 分类管理 | `#/categories` | 增删改、文章数统计、排序；有文章的分类禁止删除 |
| 标签管理 | `#/tags` | 增删改、文章数统计 |
| 页面管理 | `#/pages` | 增删改、别名、排序、预览；保存后重建静态页 |
| 评论管理 | `#/comments` | 待审核/已通过/垃圾筛选、通过、标垃圾、删除 |
| 媒体库 | `#/media` | 拖拽/点击上传、进度条、图片上传前压缩、网格预览、复制链接、删除 |
| 友链管理 | `#/links` | 增删改、排序、启用/停用 |
| 站点设置 | `#/settings` | 站点信息、博主信息、Logo、社交账号、友站、关站、评论审核、每页条数、首页排序、统计开关 |
| 系统更新 | `#/update` | 检查更新、分步升级、备份列表、回滚、离线包上传 |
| 个人资料 | `#/profile` | 昵称/邮箱/头像/简介、修改密码（改密后旧令牌失效需重新登录） |

### 4.1 编辑器与内容字段

文章与页面共用 Markdown 编辑体验（编辑/预览切换）。文章关键字段：

| 字段 | 说明 |
| --- | --- |
| `title` | 标题 |
| `slug` | 别名，用于 `/post/{slug}`；仅字母、数字、中文、`-_`，空则回退 ID 路由 |
| `summary` | 摘要，用于列表与 SEO description（超长自动截断 160 字） |
| `content` | Markdown 正文 |
| `cover_image` | 封面图路径（媒体库返回值） |
| `category_id` | 单分类归属 |
| `status` | `draft` / `published` |
| `is_top` | 置顶 |
| `allow_comment` | 是否允许评论 |

### 4.2 评论治理

- 默认开启审核（`comment_audit=1`），新评论进入 `pending`。
- 状态流转：`pending` → `approved` / `spam`，也可删除。
- 频率限制：同 IP 同文章 60 秒 1 条；同 IP 24 小时最多 20 条；邮箱格式非法直接拒绝。
- 访客侧仅能读取 `approved` 评论，且只返回 `id / post_id / nickname / content / created_at`，不暴露邮箱。

---

## 5. 内容模型

数据表定义位于 `backend/database/schema.sql`，SQLite 通过 `App::ensureDatabase()` 幂等建表/补表。

| 表 | 作用 | 关键点 |
| --- | --- | --- |
| `users` | 管理员账号 | `password_hash`（bcrypt）、`role`、`nickname`、`avatar`、`bio` |
| `api_tokens` | 会话令牌 | 24 小时过期，登出即删除 |
| `posts` | 文章 | `slug` 唯一、`status`、`is_top`、`views`、`allow_comment` |
| `categories` | 分类 | 单层结构，`sort` 排序 |
| `tags` | 标签 | 多对多 |
| `post_tags` | 文章-标签关联 | 联合主键，删除文章时清理 |
| `pages` | 独立页面 | `slug`、`status`、`sort` |
| `media` | 媒体记录 | 文件名、原始名、MIME、大小、访问路径 |
| `comments` | 评论 | `status` = `pending` / `approved` / `spam` |
| `links` | 友链 | `sort`、`is_active` |
| `settings` | 键值配置 | 值统一存字符串，长度上限 2000 |
| `visit_log` | 访问明细 | 路径、文章 ID、页面类型、来源域名、IP 哈希、UA、设备、蜘蛛标记 |
| `login_throttle` | 登录失败计数 | IP + 用户名维度，5 次锁 15 分钟 |
| `login_nonces` | 一次性 nonce | 5 分钟窗口，重复提交拒绝 |
| `schema_migrations` | 结构版本 | 升级脚本记录 |

**关系概览**：`posts.category_id → categories.id`；`posts.author_id → users.id`；`post_tags` 关联 `posts` 与 `tags`；`comments.post_id → posts.id`；`visit_log.post_id → posts.id`（可空，首页等无文章归属）。

---

## 6. SEO 与静态化

### 6.1 服务端渲染页面

| 地址 | 返回 | 内容 |
| --- | --- | --- |
| `/post/{slug}` | HTML | 复用前端构建模板，注入 title/description/keywords/canonical/OG/TwitterCard、`BlogPosting` + `BreadcrumbList` JSON-LD，正文与相关文章已直出 |
| `/sitemap.xml` | XML | 首页、列表、归档、友链、全部已发布文章、分类、标签 |
| `/robots.txt` | 纯文本 | 允许抓取，禁止 `/admin`、`/api/`、`/storage/`，声明 Sitemap |

文章不存在时返回 404 页面并标记 `noindex, follow`；未设置 `slug` 的文章会 301 跳转到 `/posts/{id}`。

### 6.2 静态文件产物

`StaticBuilder::rebuild()` 会生成：

```
public/posts/index.html            列表页
public/archive/index.html          归档页
public/links/index.html            友链页
public/category/<id>/index.html    分类页
public/tag/<id>/index.html         标签页
public/post/<slug>/index.html      文章页（含 SEO 元信息与内链）
public/page/<slug>/index.html      独立页面
public/sitemap.xml  public/robots.txt
```

**触发时机**：

1. 文章/页面保存后自动重建（失败不阻断保存）；
2. 后台「系统更新」完成后自动重建；
3. 手动调用 `POST /api/system/rebuild-static` 或命令行 `php backend/tools/build-static.php`。

**安全约束**：写入前用 `/^[\p{L}\p{N}\-_]+$/u` 校验 slug，历史脏数据不会造成路径穿越；重建依赖 `public/index.html` 存在，缺失时返回「请先执行 npm run build」。

---

## 7. 访问统计

数据来源为 `visit_log`，`VisitLogger` 负责写入与聚合。

### 7.1 采集规则

- 前台路由切换上报 `POST /api/track`（文章详情页携带 `post_id`）；`/post/{slug}` 由服务端渲染时直接记录，避免漏计。
- **去重窗口 30 分钟**：同一 IP 哈希 + 同一路径在窗口内只记一次，降低刷新刷量。
- **隐私**：访客 IP 仅以 `hash_hmac('sha256', ip, app_key)` 形式入库，不保存明文。
- 自动识别设备（`desktop` / `mobile` / `tablet` / `bot` / `unknown`）与搜索引擎蜘蛛（`is_bot`）。
- 来源域名取自 `Referer`，站内跳转不计为来源；路径做字符白名单清洗并截断 255 字符。
- `track_enabled=0` 时整个采集流程直接返回。

### 7.2 聚合指标

| 指标 | 说明 |
| --- | --- |
| 总 PV / 总 UV | UV = `COUNT(DISTINCT ip_hash)` |
| 今日 / 昨日 PV、UV | 用于环比展示 |
| 趋势 | 按天聚合，自动补齐空白日期，窗口 1–365 天（默认 30） |
| 热门文章 Top10 | 按 `visit_log` 访问量排序，附 UV 与 `posts.views` |
| 热门页面 Top10 | 按路径聚合 PV/UV |
| 来源站点 Top10 | 按 `ref_host` 聚合 |
| 设备分布 / 蜘蛛 PV | 全量统计 |

---

## 8. 媒体库

| 项目 | 规则 |
| --- | --- |
| 上传字段 | multipart `file` |
| 大小上限 | 10MB（`upload.max_size`） |
| 扩展名白名单 | `jpg jpeg png gif webp pdf txt md`，**不接收 SVG** |
| MIME 校验 | `finfo` 白名单，扩展名与 MIME 双向匹配 |
| 内容校验 | 图片类额外调用 `getimagesize()`，拦截伪造图片 |
| 存储位置 | `public/storage/uploads/YYYYMM/`，文件名 `His_随机8位hex.ext` |
| 访问路径 | `/storage/uploads/YYYYMM/<filename>`，可直接用于封面、头像、正文图片 |
| 前端优化 | 上传前将图片压缩至 800KB 以内（可开关），减少流量与失败率 |
| 目录防护 | 自动写入 `.htaccess` 关闭脚本执行与目录浏览；Nginx 需自行在站点配置禁用上传目录 PHP |
| 直出防护 | `/storage/*` 拦截 `.php/.phar/.phtml/.sqlite/.log/.htaccess` 与隐藏文件，SVG 附加 `CSP: default-src 'none'` |

---

## 9. 系统更新

仅管理员可用，代码位于 `UpdateController` + `Support/UpdateService.php`。

**步骤链（必须按序串行执行）**：

```
download → extract → backup → apply → migrate → finalize
```

| 步骤 | 作用 |
| --- | --- |
| `download` | 下载升级包（支持签名校验；离线场景改用上传包） |
| `extract` | 解压并校验包结构 |
| `backup` | 备份当前代码/数据，生成可回滚快照 |
| `apply` | 覆盖文件（保护 `storage`、`.env` 等运行数据） |
| `migrate` | 执行数据库迁移脚本 |
| `finalize` | 清理缓存/OPcache、老产物迁移、重建静态页、写入版本状态 |

**能力与约束**：

- 状态接口返回环境自检：PHP 版本、`ZipArchive`、`sodium`、`storage` 与根目录可写、上传上限、备份列表、步骤列表。
- 检查更新结果带 24 小时缓存，`force=1` 可强制刷新；返回 `has_update`、`changelog[]`、`breaking`、`min_version`、`blocked` 等字段。
- 回滚可指定备份名，`with_db=true` 时一并恢复数据库快照。
- 离线升级包通过 multipart 字段 `package` 上传，大小受服务器 `upload_max_filesize` 与内部上限共同限制。
- 升级过程设置较长执行时间并忽略客户端中断，避免半途终止；失败时返回具体步骤错误，可回滚。

---

## 10. 站点设置项

`PUT /api/settings` 仅接受以下键（其余键静默忽略），值统一截断至 2000 字符：

| 分组 | 键 |
| --- | --- |
| 站点信息 | `site_name`、`site_title`、`site_description`、`site_keywords`、`site_icp` |
| 品牌 | `logo_type`（`text`/`image`）、`logo_image`、`hero_suffix` |
| 博主 | `owner_name`、`owner_bio`、`owner_avatar` |
| 社交 | `social_github`、`social_email`、`social_weibo`、`social_zhihu`、`social_csdn`、`social_wechat`、`social_wechat_qr` |
| 导航 | `nav_links`（JSON 数组，最多 10 条，非法 URL 丢弃） |
| 运营 | `site_closed`（`0`/`1`）、`site_closed_message`、`comment_audit`、`posts_per_page`、`home_post_sort`（`hot`/`latest`）、`track_enabled`（`0`/`1`） |

未登录访问 `GET /api/settings` 只返回上表中的公开子集，`site_closed_message`、`posts_per_page` 等运营参数同样公开（前台关站页需要）。

---

## 11. 主题与视觉

### 11.1 两套独立 UI

系统同时维护两套互不混用的设计体系，通过「引入哪一个样式文件」切换，组件代码不重复：

| UI | 样式文件 | 外观 | 使用位置 |
| --- | --- | --- | --- |
| 通用 UI（默认） | `frontend/shared/styles/ui.css` | 浅色中性灰阶 + 单一主色靛蓝，白底细描边分层，圆角统一 | 默认主题 `frontend/site`、后台、登录页、安装页 |
| 星空 UI | `frontend/shared/styles/star.css` | 深色底、玻璃磨砂、紫青极光、星光发光 | 星空主题 `frontend/star` |

共享组件（`Pagination` / `EmptyState` / `LoadingSpinner` / `MarkdownView` / `BaseModal`）通过 `variant` 属性切换外观，默认主题显式传 `flat`，星空主题传 `star`。

两套前台主题源码各自独立（`frontend/site` 与 `frontend/star`），构建产物分别落到 `public/themes/default/` 与 `public/themes/star/`；仅默认主题接管站点根入口，星空主题不覆盖默认 UI。

### 11.2 主题目录契约

前台构建产物落在 `public/themes/<slug>/`，站点根入口 `public/index.html` 只是它的一份副本：

```
public/
├── index.html                  ← 主题外壳副本（老 URL 兼容）
└── themes/default/
    ├── theme.json              ← 主题元数据
    ├── index.html              ← 主题外壳
    ├── assets/                 ← 打包后的 JS/CSS
    ├── templates/              ← SSR 模板（post / page / 404）
    └── preview.jpg             ← 预览图（可选）
```

`theme.json` 字段：

| 字段 | 说明 |
| --- | --- |
| `slug` | 主题唯一标识，必须与目录名一致 |
| `name` / `version` / `author` / `description` / `license` | 展示与分发信息 |
| `requires.core` | 兼容的核心版本范围（如 `>=2.0.1`） |
| `entry` / `templates` | 外壳与 SSR 模板目录 |
| `preview` | 预览图文件名（可选） |
| `supports` | 能力声明（`dark_mode`、`custom_css`） |
| `settings_schema` | 主题配置项声明 |

### 11.3 主题打包与清单

```bash
cd frontend
npm run pack:theme     # 执行 build:site 后打包，零第三方依赖
```

产出 `release/themes/ycms-theme-<slug>-<version>.zip`，并同步：

- 根目录 `themes.json`：主题清单（版本、包名、体积、sha256、下载地址）
- `update.json` 的 `themes` 字段：供在线更新读取的主题摘要（旧客户端忽略未知字段）

包内文件顺序与时间戳固定，内容相同则 sha256 稳定，便于校验与差分。

### 11.4 老站点自动迁移

2.0.x 早期版本把产物平铺在 `public/`（`index.html` + `assets/`），主题化后会白屏。以下入口会自动调用 `Support/Theme/LegacyMigrator`（幂等）：

- `install.php` 安装收尾
- 在线升级 `finalize` 步骤
- 手动命令 `php backend/tools/theme.php migrate [slug]`

迁移动作：搬运平铺资源目录 → 生成 `theme.json` → 重写外壳中的绝对资源路径 → 补齐 SSR 模板 → 回写 `public/index.html` → 重建静态页。

---

## 12. 边界与已知限制

- 单站点、单管理员：无多用户协作、无角色细分、无工作流。
- 分类为单层结构，文章只能归属一个分类。
- 评论为单层平铺，无嵌套回复与验证码（依赖频率限制 + 审核）。
- 搜索为 `LIKE` 模糊匹配，不提供分词与全文索引。
- 静态页为全量重建，文章量很大时重建耗时线性增长。
- 系统更新依赖 `ZipArchive`；离线包需要 PHP 上传上限足够大。
- 上传目录的 PHP 禁用规则在 Nginx 下不生效，需在站点配置层补齐。
- 前台与后台为两套独立构建，修改后台样式不会影响前台产物，但共享 `shared/styles/ui.css` 中的设计 token。
- 主题包只做「打包 + 清单」，主题在线安装/切换属于商业版能力；开源版仅内置 `default` 一个主题。

---

## 13. 相关文档

- 接口清单与错误码：[api.md](api.md)
- 宝塔面板部署：[deployment.md](deployment.md)
- 项目总览与设计系统：[../README.md](../README.md)

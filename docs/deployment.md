# ycms2.0 宝塔面板单站部署指南

> 适用版本：**ycms2.0 v2.0.2** · 前后端 + SQLite 部署在**同一个宝塔站点**下

---

## 一、部署架构

一个宝塔站点同时承载三部分，Nginx 按路径分流：

```
                    ┌────────────────────────────┐
   浏览器 ─────────▶│  Nginx（宝塔站点）           │
                    │                            │
                    │  /            → 前端 dist   │  Vue SPA（静态文件）
                    │  /api/*       → PHP 入口    │  backend/public/index.php
                    │  /storage/*   → 媒体目录    │  backend/storage/uploads
                    └──────────┬─────────────────┘
                               │
                    ┌──────────▼─────────────────┐
                    │  PHP ≥ 8.2 + SQLite        │
                    │  数据库：backend/storage/    │
                    │  database.sqlite（单文件）  │
                    └────────────────────────────┘
```

**服务器上的目录布局**（本文以此为准）：

```
/www/wwwroot/ycms/
├── backend/                    # PHP 后端（整个目录上传）
│   ├── config/
│   ├── database/
│   ├── public/index.php        # API 唯一入口
│   ├── src/
│   └── storage/                # 数据库 + 上传文件，必须可写！
└── frontend/
    └── dist/                   # 前端构建产物（只需这个目录）
```

---

## 二、环境要求

| 项目 | 要求 |
| --- | --- |
| 面板 | 宝塔面板（aaPanel）7.x 及以上 |
| Web 服务 | Nginx 1.20+ |
| PHP | **≥ 8.2**（推荐 8.2 / 8.3） |
| PHP 扩展 | `pdo_sqlite`、`sqlite3`、`fileinfo`、`mbstring`（默认安装基本都带） |
| Node.js | ≥ 18（**仅构建前端需要**，可在本地构建后上传 `dist`） |
| 磁盘 | ≥ 200MB |

---

## 三、部署步骤

### 步骤 1：安装 PHP 8.2+ 及扩展

1. 宝塔面板 → **软件商店 → 已安装 → PHP** → 安装 **8.2** 或 **8.3**
2. 点击对应 PHP 的「设置」→ **扩展安装** → 确认 `pdo_sqlite`、`sqlite3`、`fileinfo`、`mbstring` 已安装

### 步骤 2：上传代码

**方式 A：直接上传编译好的包（推荐，服务器无需 Node）**

1. 本地执行一次构建（只需构建，不需上传源码）：
   ```bash
   cd frontend && npm install && npm run build
   ```
   产物已输出到项目根目录的 `public/`（前台）与 `public/admin/`（后台）。
2. 用宝塔 **文件** 菜单（或 FTP/SFTP）把整个项目目录上传到 `/www/wwwroot/ycms/`：

   ```
   /www/wwwroot/ycms/
   ├── public/        ← 网站根目录（必须完整上传）
   ├── backend/
   └── frontend/      （可只保留源码，也可整个上传）
   ```

   > `node_modules` 不需要上传。

**方式 B：Git 拉取（服务器需装 Node）**

```bash
cd /www/wwwroot/ycms
git clone <你的仓库地址> .
cd frontend && npm install && npm run build
```

### 步骤 3：创建站点（关键一步）

宝塔面板 → **网站 → 添加站点**：

| 配置项 | 值 |
| --- | --- |
| 域名 | 你的域名（如 `cms.example.com`） |
| 根目录 | `/www/wwwroot/ycms/public` ← **注意是 public 目录** |
| PHP 版本 | 选择步骤 1 安装的 8.2+ |
| 数据库 | **不需要创建**（使用 SQLite） |
| FTP | 不需要 |

创建后记录站点的 **PHP 版本号**（如 83）。

### 步骤 4：访问 /install.php 完成安装

浏览器打开 `https://你的域名/install.php`：

1. 页面会自动检测 PHP 版本、扩展与目录权限；
2. 填写 **站点名称**、**站长昵称**、**管理员账号与密码**；
3. 点击「开始安装」——系统会自动完成：
   - 创建 SQLite 数据库与全部表结构
   - 写入站点设置与管理员账号（密码 bcrypt 加密）
   - 生成示例文章与「关于我」页面
   - 生成全部静态页（`/posts/`、`/post/文章别名/`、`/page/关于我/`、`sitemap.xml`、`robots.txt`）
4. 安装成功页会提示 **删除 `public/install.php`**，请务必照做。

安装完成后：

- 前台：`https://你的域名/`
- 后台：`https://你的域名/admin/`

> **为什么不需要配置 Nginx？** 本站采用「静态文件 + 真实 PHP 文件」结构：
> - API 走真实文件 `/api.php/...`，Nginx 默认规则即可命中
> - 后台是静态目录 `/admin/`（hash 路由）
> - 文章/列表页在发布时已生成真实 HTML 文件
> 因此 **不需要 `try_files`、不需要伪静态、不需要改任何 Nginx 配置**。

### 步骤 5（可选）：如需更短的 API 地址

只有想用 `/api/xxx` 这种短地址时，才需要加一段重写规则（不加也能正常使用）：

宝塔 → **网站 → 设置 → 配置文件**，在 `server { }` 内任意位置加入：

```nginx
# 可选：让 /api/xxx 与 /post/xxx 也能直达 PHP（不配置则使用 /api.php/xxx 与静态文件）
location ^~ /api/ {
    include fastcgi_params;
    fastcgi_pass unix:/tmp/php-cgi-83.sock;
    fastcgi_param SCRIPT_FILENAME /www/wwwroot/ycms/public/api.php;
}

location ^~ /post/ {
    include fastcgi_params;
    fastcgi_pass unix:/tmp/php-cgi-83.sock;
    fastcgi_param SCRIPT_FILENAME /www/wwwroot/ycms/backend/public/index.php;
}

location = /sitemap.xml {
    include fastcgi_params;
    fastcgi_pass unix:/tmp/php-cgi-83.sock;
    fastcgi_param SCRIPT_FILENAME /www/wwwroot/ycms/backend/public/index.php;
}

location = /robots.txt {
    include fastcgi_params;
    fastcgi_pass unix:/tmp/php-cgi-83.sock;
    fastcgi_param SCRIPT_FILENAME /www/wwwroot/ycms/backend/public/index.php;
}
```

**完整配置参考**（如需自定义，可整体替换，修改三处：域名、路径、PHP socket）：

```nginx
server {
    listen 80;
    server_name cms.example.com;                          # ← 改成你的域名

    root /www/wwwroot/ycms/public;                        # ← 站点根目录
    index index.html;

    client_max_body_size 20m;                             # 上传大小限制

    # ========== 1. 媒体文件：后端上传目录直出 ==========
    # ^~ 优先级高于静态资源正则，避免 /storage 下的图片被误判
    location ^~ /storage/ {
        alias /www/wwwroot/ycms/backend/storage/;         # ← 后端存储目录
        add_header Cache-Control "public, max-age=31536000";
        # 媒体目录禁止执行 PHP（安全）
        location ~ \.(php|phar|phtml)$ { deny all; }
    }

    # ========== 2. API：转发到 PHP 入口 ==========
    location /api/ {
        include fastcgi_params;
        fastcgi_pass unix:/tmp/php-cgi-83.sock;           # ← 按实际 PHP 版本改（82/83）
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/ycms/backend/public/index.php;
        # SCRIPT_FILENAME 必须写在 include 之后，覆盖 fastcgi_params 里的同名参数
    }

    # ========== 3. SEO 服务端渲染：文章页 / 站点地图 / robots ==========
    # 搜索引擎直接抓取 PHP 渲染的 HTML（含 meta、JSON-LD、相关文章内链）
    location ^~ /post/ {
        include fastcgi_params;
        fastcgi_pass unix:/tmp/php-cgi-83.sock;           # ← 按实际 PHP 版本改（82/83）
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/ycms/backend/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
    }

    location = /sitemap.xml {
        include fastcgi_params;
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/ycms/backend/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
    }

    location = /robots.txt {
        include fastcgi_params;
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/ycms/backend/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
    }

    # ========== 4. 前端 SPA：history 模式刷新不 404 ==========
    location / {
        try_files $uri $uri/ /index.html;
    }

    # ========== 5. 静态资源缓存 ==========
    location ~* \.(js|css|png|jpg|jpeg|gif|webp|svg|ico|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
```

**PHP socket 名称查询**（SSH 执行）：

```bash
ls /tmp/php-cgi-*.sock
# 输出如 /tmp/php-cgi-83.sock，把配置里的 83 换成实际版本号
```

保存后 **重载 Nginx**（宝塔配置文件页直接点「保存」会自动重载）。

### 步骤 6：设置目录权限

SSH 执行（把 `www` 换成你实际的运行用户，宝塔默认 `www`）：

```bash
chown -R www:www /www/wwwroot/ycms/backend/storage
chmod -R 755 /www/wwwroot/ycms/backend/storage
```

> `storage/` 必须可写：SQLite 数据库文件（`database.sqlite`）和上传的图片都在里面。
> 若仍报 500/无法写入，可临时 `chmod -R 777 storage` 排查，定位后再收紧为 755。

### 步骤 6：初始化数据库

SSH 进入后端目录：

```bash
cd /www/wwwroot/ycms/backend

# 1. 建表（必需）
php database/migrate.php

# 2. 导入演示数据（可选：5 篇文章 / 分类 / 标签 / 页面 / 友链 / 设置）
php database/seed.php
```

> 不执行 `seed.php` 也可以：首次访问站点时系统会**自动建表**，得到空站点。
> 默认管理员账号：`admin` / `admin123`，登录后请立即修改（后台 → 个人资料 → 修改密码）。

### 步骤 7：访问验证

| 页面 | 地址 |
| --- | --- |
| 前台首页 | `http://你的域名/` |
| 后台登录 | `http://你的域名/admin/login` |
| 接口自检 | `http://你的域名/api/health`（应返回 JSON） |

---

## 四、开启 HTTPS（推荐）

1. 宝塔 → **网站 → 设置 → SSL → Let's Encrypt** → 申请证书（需域名已解析到服务器）
2. 开启「强制 HTTPS」
3. 宝塔会自动改配置，之后如需自定义 Nginx 规则，在 443 的 server 块中补上第三、四步的 `/api/` 与 `/storage/` 两段即可

---

## 五、常见问题

**Q1：首页 500 错误**
- PHP 版本低于 8.2 → 到「软件商店 → PHP 设置」切换版本
- `storage/` 不可写 → 重做步骤 5
- 缺少扩展 → PHP 设置 → 扩展安装 → `pdo_sqlite` / `fileinfo` / `mbstring`

**Q2：后台能登录，但上传的图片不显示（404）**
- 检查 Nginx 的 `/storage/` 段 `alias` 路径是否指向 `backend/storage/`
- 检查文件是否真的上传成功：`ls -l /www/wwwroot/ycms/backend/storage/uploads/`

**Q3：前台刷新页面 404**
- SPA 需要 `location / { try_files $uri $uri/ /index.html; }`，检查是否被其他规则覆盖

**Q4：上传大图失败（超过 2M）**
- PHP 设置 → 配置文件修改：`upload_max_filesize = 20M`、`post_max_size = 20M`
- Nginx 已含 `client_max_body_size 20m`，如需更大同步改大两边

**Q5：如何备份数据**
- SQLite 是单文件，直接复制即完成备份：
  ```bash
  cp /www/wwwroot/ycms/backend/storage/database.sqlite ~/backup-$(date +%F).sqlite
  ```
- 建议同时打包 `storage/uploads/`（媒体文件）

**Q6：如何迁移到另一台服务器**
- 打包 `backend/storage/`（数据库 + 媒体）+ `public/themes/`（前台主题产物）上传到新站，重复步骤 4/5 即可，无需改配置

**Q7：从 2.0.x 老版本升级后首页白屏**
- 老版本把产物平铺在 `public/`（`index.html` + `assets/`），新版只认 `public/themes/<slug>/`
- 安装页与在线升级会自动迁移；若手动覆盖过代码，执行一次：
  ```bash
  php backend/tools/theme.php migrate default
  ```
- 命令会搬运资源、生成 `theme.json`、重写外壳路径并重建静态页，可重复执行

---

## 六、安全加固建议

1. **完成首次初始化**：用 `admin/admin123` 首次登录后，系统会**强制**修改用户名和密码（禁止 admin/root 等默认名，密码须 ≥8 位含字母+数字），完成后才能使用后台
2. **删除演示数据脚本**：上线后删除 `backend/database/seed.php`（会被重新覆盖数据）
3. **媒体目录禁 PHP**：Nginx 配置已含（步骤 4 第 1 段），请勿删除；Apache 环境由系统自动写入 `public/storage/.htaccess`
4. **不要放行 SVG 上传**：系统已默认拒绝 SVG（可内嵌脚本，同源直出会造成 XSS），如需图标请用 PNG/WebP
5. **前后端分域才配 CORS**：同源部署无需任何配置；若前后端分域，在 `backend/.env` 设置 `CORS_ALLOWED_ORIGINS=https://你的前端域名`，不要使用 `*`
6. **关闭 DEBUG**：生产环境无需 `.env`，保持默认即可
7. **异常日志**：500 错误详情写入 `backend/storage/logs/app-*.log`，排查后可删除；该目录不要映射到 web 根目录
8. **定期备份**：见 Q5，可配合宝塔「计划任务」定时备份 `storage/` 目录

### 忘记密码怎么办（应急重置）

`backend/tools/reset-password.php` 是独立的高危重置文件，**不随安装包/升级包分发**，忘记密码时从仓库下载：[reset-password.php](https://raw.githubusercontent.com/sjzyingyunkeji/ycms-2.0/main/backend/tools/reset-password.php)。

1. 下载后通过宝塔「文件」或 SFTP 将其上传到 `backend/public/` 目录
2. 访问 `https://你的域名/reset-password.php`，点击「确认重置密码」
3. 账号恢复为 `admin / admin123`，重新登录并完成强制初始化
4. **重置后立即删除该文件**——后台登录成功后也会在顶部显示红色警告提醒你删除

> 该文件只能由你本人通过服务器上传：网站上传接口有扩展名白名单 + 图片内容校验，访客无法通过前台/登录页把它写进运行目录。

---

## 七、主题包与目录结构

前台产物以「主题」为单位组织，`public/index.html` 只是 `public/themes/default/index.html` 的副本：

```
public/
├── index.html                 ← 站点根入口（主题外壳副本）
├── admin/                     ← 后台静态产物
├── api.php / install.php
└── themes/default/
    ├── theme.json             ← slug / version / requires.core 等契约字段
    ├── index.html
    ├── assets/
    ├── templates/             ← SSR 模板（post / page / 404）
    └── preview.jpg
```

**打包主题**（发布或做主题市场分发时）：

```bash
cd frontend
npm run pack:theme        # 构建默认主题后打包，零第三方依赖
npm run pack:star         # 构建星空主题后打包
npm run pack:all          # 打包本仓库全部主题
```

产出：

| 产物 | 说明 |
| --- | --- |
| `release/themes/ycms-theme-<slug>-<version>.zip` | 主题包本体，含 `theme.json` / 外壳 / 资源 / 模板 / 预览图 |
| `themes.json` | 主题清单：版本、包名、体积、sha256、下载地址（按 slug 合并写入） |
| `update.json` 的 `themes` 字段 | 在线更新读取的主题摘要 |

**打包单主题发行包**（各自带全套后端，用于「一主题一安装包」分发）：

```bash
node scripts/build-release.mjs --edition=oss --theme=star   # → release/ycms-<version>-oss-star/
node scripts/build-release.mjs --edition=oss --theme=default
```

脚本会裁剪 `public/themes/` 到单一主题、让该主题外壳接管 `public/index.html`、重写 `backend/storage/theme.json` 为 `active=<slug>`，并按该主题重建静态页；构建用的数据库是临时拷入、结束后立即删除，不会进入发行包。

**发布源与发行风味**（`release-source.json`）：

站点检查更新、下载升级包所用的仓库地址由根目录 `release-source.json` 决定，该文件随包分发：

```json
{ "repository": "sjzyingyunkeji/ycms-2.0", "branch": "main", "channel": "stable", "flavor": "default" }
```

- 清单地址：`https://raw.githubusercontent.com/<repository>/<branch>/update.json`
- 包地址：`https://github.com/<repository>/releases/download/v<version>/<file>`
- `flavor` 为发行风味（`default` / `star`）。站点只接受同风味清单与升级包，两条发行线不会互相覆盖；离线包导入时同样校验。
- 星空主题独立仓库只需把该文件改为自身仓库，升级逻辑与主仓库完全一致。
- 单仓库出星空包时显式指定仓库：`node scripts/build-release.mjs --theme=star --repository=holytreasure/ycms-star`。

> 主题包内文件顺序与时间戳固定，同内容 sha256 稳定，可用于完整性校验。
> 主题源码按仓库拆分：主仓库只含默认主题 `frontend/site`，星空版仓库只含 `frontend/star`；站点根入口由本仓库唯一主题接管，主题在线安装/切换属于商业版能力。

---

*文档对应版本 ycms2.0 v2.0.2 · 技术栈 PHP 8.2+ / Vue 3 / SQLite*

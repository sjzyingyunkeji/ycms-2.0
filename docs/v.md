<h1 align="center">
ycms2.0
</h1>

<p align="center">

![License](https://img.shields.io/badge/License-AGPLv3-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb3.svg)
![Vue](https://img.shields.io/badge/Vue-3-4fc08d.svg)

</p>

轻量内容管理系统，基于 **PHP 8.2+ / Vue 3 / SQLite**，开箱即用的个人博客与企业站点基座。

- ✅ **零配置部署**：站点根目录指向 `public/`，访问 `/install.php` 即可完成安装，无需伪静态与URL重写
- ✅ **前后台独立构建**：前台主题与后台管理面板相互解耦，一方编译异常不会影响另一方正常运行
- ✅ **主题化架构**：前台构建产物输出至 `public/themes/<slug>/`，通过 `theme.json` 声明主题元数据，支持主题打包分发

### 前台（通用 UI）

<table>
  <tr>
    <td><img src="https://raw.githubusercontent.com/sjzyingyunkeji/ycms-2.0/main/docs/screenshots/site-home.jpg" alt="首页"></td>
    <td><img src="https://raw.githubusercontent.com/sjzyingyunkeji/ycms-2.0/main/docs/screenshots/site-posts.jpg" alt="文章列表"></td>
  </tr>
  <tr>
    <td><img src="https://raw.githubusercontent.com/sjzyingyunkeji/ycms-2.0/main/docs/screenshots/site-post-detail.jpg" alt="文章详情"></td>
    <td><img src="https://raw.githubusercontent.com/sjzyingyunkeji/ycms-2.0/main/docs/screenshots/site-archive.jpg" alt="归档"></td>
  </tr>
</table>

### 后台（登录页）

<table>
  <tr>
    <td><img src="https://raw.githubusercontent.com/sjzyingyunkeji/ycms-2.0/main/docs/screenshots/admin-login.jpg" alt="后台登录"></td>
    <td><img src="https://raw.githubusercontent.com/sjzyingyunkeji/ycms-2.0/main/docs/screenshots/site-page-about.jpg" alt="独立页面"></td>
  </tr>
</table>

## 项目目录

```
ycms/
├── backend/                  # PHP后端
│   ├── public/index.php      # 应用入口
│   ├── src/
│   │   ├── App.php           # 应用引导与路由注册
│   │   ├── Core/             # 路由/请求/响应/数据库核心
│   │   ├── Controllers/      # REST接口控制器
│   │   └── Support/          # 工具函数、鉴权、静态构建、更新服务、主题管理器
│   ├── database/             # 数据表结构与迁移脚本
│   ├── tools/                # 命令行辅助脚本
│   └── storage/              # SQLite数据库、上传文件（不纳入版本管理）
├── frontend/                 # Vue3前端
│   ├── core/                 # 与主题无关的基础模块：接口、状态、工具、路由
│   ├── shared/               # 通用组件与UI样式
│   ├── admin/                # 后台管理面板源码
│   ├── site/                 # 默认主题源码
│   └── scripts/              # 构建与主题打包脚本
├── public/                   # Web访问根目录，前端构建输出目录
│   ├── index.html            # 站点入口
│   ├── admin/                # 后台静态资源
│   └── themes/               # 主题存放目录
├── docs/                     # 项目文档与截图
├── themes.json               # 主题清单
├── update.json               # 在线更新清单
├── release-source.json       # 更新源配置
└── VERSION                   # 版本号文件
```

## 快速开始

### 生产环境部署

1. 将项目上传至服务器，设置网站根目录为 `public/`
2. 浏览器访问 `https://你的域名/install.php`
3. 填写站点信息与管理员账号，完成安装

- 前台访问：`/`
- 后台管理：`/admin/`


> 完整部署文档：[deployment.md](https://github.com/sjzyingyunkeji/ycms-2.0/blob/main/docs/deployment.md)

### 本地开发

**环境要求**

- PHP >= 8.2
- Node.js >= 18

```
# 启动PHP后端服务
cd backend
php -S localhost:8080 -t public dev-router.php

# 启动Vite开发服务
cd frontend
npm install
npm run dev
# 前台：[http://localhost:5173/](http://localhost:5173/)
# 后台：[http://localhost:5173/admin/](http://localhost:5173/admin/)
```

### 生产构建

```
cd frontend
npm run build          # 构建默认主题 + 后台面板
npm run build:site     # 仅构建前台主题
npm run build:admin    # 仅构建后台面板
npm run pack:theme     # 将默认主题打包为zip压缩包
```


## 功能列表

### 前台站点

- 首页：数据统计、关于卡片、热门/最新文章切换、友链展示
- 文章列表：分类标签筛选、分页、排序切换、置顶标识、阅读量与评论数
- 文章详情：Markdown渲染、上下篇跳转、同标签相关文章（SEO内链）、浏览统计
- 评论系统：昵称/邮箱/内容提交，支持评论审核开关
- 按年份归档时间轴
- 自定义独立页面、友链页、404页面、站点关闭页
- SEO能力：友好文章URL、服务端渲染meta/OG/JSON‑LD、`sitemap.xml`、`robots.txt`
- 移动端响应式适配

### 后台管理面板

- 仪表盘：数据统计图表、最近文章与评论
- 访问统计：全站PV/UV、访问趋势、热门文章排行、来源与设备分布（访客IP仅做哈希存储）
- 文章管理：新增编辑、草稿/发布、置顶、Markdown编辑器、评论开关
- 分类标签管理：增删改查，附带文章数量统计
- 自定义页面管理
- 评论审核与处理
- 媒体库：文件上传预览、图片自动压缩
- 友情链接管理
- 站点全局配置、社交账号设置、站点关闭开关
- 个人资料修改、密码重置
- 程序在线更新、备份与回滚

### REST API

鉴权使用 `HttpOnly + SameSite=Lax` 会话Cookie，跨域可通过 `.env` 文件配置。

| 模块 | 接口 |
| --- | --- |
| 认证 | `POST /auth/login` `POST /auth/logout` `GET /auth/me` `PUT /auth/profile` `PUT /auth/password` |
| 仪表盘 | `GET /dashboard/stats` |
| 文章 | `GET/POST /posts` `GET/PUT/DELETE /posts/{id}` `POST /posts/{id}/view` |
| 分类 | `GET/POST /categories` `PUT/DELETE /categories/{id}` |
| 标签 | `GET/POST /tags` `PUT/DELETE /tags/{id}` |
| 页面 | `GET/POST /pages` `GET/PUT/DELETE /pages/{id}` |
| 媒体 | `GET/POST /media` `DELETE /media/{id}` |
| 评论 | `GET/POST /comments` `PUT/DELETE /comments/{id}` |
| 友链 | `GET/POST /links` `PUT/DELETE /links/{id}` |
| 设置 | `GET /settings` `PUT /settings` |

## 主题系统

支持自定义主题，主题元数据由主题目录内的 `theme.json` 定义。

`theme.json` 关键字段：

- `slug`：主题唯一标识，必须和目录名保持一致
- `version`、`name`、`author`、`license`、`description`：主题基础信息
- `requires.core`：兼容的程序核心版本范围
- `entry`、`templates`：入口文件与SSR模板文件
- `supports`：能力标记，例如 `dark_mode`、`custom_css`
- `settings_schema`：主题配置项结构

修改启用主题，编辑 `backend/storage/theme.json`，执行命令重建静态页面：

```
php backend/tools/build-static.php
```

## 安全特性

- 首次登录强制修改默认账号密码
- 密码强度校验，内置弱口令黑名单
- 登录频率限制，登录请求nonce防重放
- HttpOnly会话Cookie，SameSite+来源校验抵御CSRF
- 全站PDO预处理语句，防范SQL注入
- Markdown内容DOMPurify净化，链接协议白名单
- 文件写入路径穿越防护
- 严格上传校验，上传目录禁止脚本执行
- 评论提交频率限制，防止恶意灌水
- 安全HTTP响应头配置
- 生产环境隐藏程序堆栈报错信息



## 技术栈

- **后端**：PHP 8.2+，SQLite
- **前端**：Vue3 + Vite
- **渲染方案**：静态构建 + PHP服务端渲染混合模式
- **构建工具**：Vite多模式构建

## 项目文档

- [features.md](https://github.com/sjzyingyunkeji/ycms-2.0/blob/main/docs/features.md)：功能说明、数据模型、主题契约
- [api.md](https://github.com/sjzyingyunkeji/ycms-2.0/blob/main/docs/api.md)：接口完整参考文档
- [deployment.md](https://github.com/sjzyingyunkeji/ycms-2.0/blob/main/docs/deployment.md)：部署操作指南

## 致谢

本项目的后台管理面板基于字节跳动开源的 [Arco Design Vue](https://arco.design) 组件库构建。
感谢 Arco Design 团队提供的高质量 Vue 3 企业级组件与设计规范，让 ycms2.0 的后台在交互体验
与视觉一致性上开箱即用，也让自定义 `ui.css` 得以与组件库主色变量同步覆盖、保持统一观感。

- **Arco Design Vue**：<https://arco.design> —— 字节跳动开源的企业级 UI 组件库（MIT 协议）
- 同时感谢 [Vue](https://vuejs.org)、[Vite](https://vitejs.dev)、[PHP](https://www.php.net)
  与 [SQLite](https://www.sqlite.org) 等优秀开源项目奠定的技术底座。

## 开源协议

本项目使用 **GNU Affero General Public License v3.0 (AGPL‑3.0)** 协议开源。


商业授权与定制开发，请联系：[info@sjzyingyun.cn](mailto:info@sjzyingyun.cn)

Copyright © 2026 影云网络科技（石家庄）有限公司

## 常见问题

### Q：忘记后台管理员密码

请前往源码目录 `backend/tools/reset‑password.php`，上传至 `backend/public/`，访问 `/reset‑password.php`，账号重置为 `admin/admin123`。
**重置完成后立刻删除该文件。**

### Q：在线更新提示无法获取更新清单

服务器无法访问 `raw.githubusercontent.com`，请手动下载发行包，在后台执行离线升级。

### Q：旧版本站点迁移

```
php backend/tools/theme.php migrate default
```

迁移脚本具备幂等性，可重复执行。

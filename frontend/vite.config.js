/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { readFile, copyFile, cp, mkdir } from 'node:fs/promises'
import { existsSync, readFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const PUBLIC_DIR = path.resolve(__dirname, '../public')
const ADMIN_DIR = path.resolve(__dirname, 'admin')
const SITE_DIR = path.resolve(__dirname, 'site')
const BACKEND_STORAGE = path.resolve(__dirname, '../backend/storage')

// 内置主题元数据：slug 决定产物目录 /themes/<slug>/ 与资源前缀
const themeMeta = JSON.parse(readFileSync(path.join(SITE_DIR, 'theme.json'), 'utf8'))
const THEME_SLUG = String(themeMeta.slug || 'default')
const THEME_OUT_DIR = path.join(PUBLIC_DIR, 'themes', THEME_SLUG)

// 发行版本（条件编译）：oss = 开源产物，pro = 商业产物
// 构建时通过常量替换，未命中分支会被 Rollup 静态消除，产物中不留 Pro 代码
const EDITION = (process.env.YCMS_EDITION || 'oss').toLowerCase() === 'pro' ? 'pro' : 'oss'

const MIME_MAP = {
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.pdf': 'application/pdf',
  '.txt': 'text/plain',
  '.md': 'text/markdown',
}

// 开发环境直接读取 backend/storage 下的媒体文件（绕过 PHP 内置服务器对扩展名路径的限制）
function storageMiddleware(req, res, next) {
  if (!req.url || !req.url.startsWith('/storage/')) return next()
  const pathname = decodeURIComponent(req.url.split('?')[0])
  const filePath = path.normalize(path.join(BACKEND_STORAGE, pathname.replace('/storage/', '')))
  if (!filePath.startsWith(BACKEND_STORAGE) || !existsSync(filePath)) {
    res.statusCode = 404
    return res.end('Not Found')
  }
  const ext = path.extname(filePath).toLowerCase()
  res.setHeader('Content-Type', MIME_MAP[ext] || 'application/octet-stream')
  res.setHeader('Cache-Control', 'public, max-age=31536000')
  readFile(filePath)
    .then((data) => res.end(data))
    .catch(() => {
      res.statusCode = 500
      res.end('Internal Error')
    })
}

const storagePlugin = {
  name: 'ycms-storage-middleware',
  configureServer(server) {
    server.middlewares.use(storageMiddleware)
  },
}

/**
 * 主题构建收尾（零配置部署的关键）：
 *   1. 把主题外壳 index.html 复制为站点根入口 public/index.html
 *   2. 把 theme.json 与 SSR 模板一并放进主题目录，形成可独立安装的主题包结构
 */
function themeActivatePlugin(slug) {
  return {
    name: 'ycms-theme-activate',
    apply: 'build',
    async closeBundle() {
      const shell = path.join(THEME_OUT_DIR, 'index.html')
      if (!existsSync(shell)) {
        this.warn('未找到主题外壳 index.html，跳过激活复制')
        return
      }
      await mkdir(PUBLIC_DIR, { recursive: true })
      await copyFile(shell, path.join(PUBLIC_DIR, 'index.html'))
      await copyFile(path.join(SITE_DIR, 'theme.json'), path.join(THEME_OUT_DIR, 'theme.json'))
      const tplDir = path.join(SITE_DIR, 'templates')
      if (existsSync(tplDir)) {
        await cp(tplDir, path.join(THEME_OUT_DIR, 'templates'), { recursive: true })
      }
      // 主题预览图（主题包/主题市场展示用，可选）
      const preview = path.join(SITE_DIR, 'preview.jpg')
      if (existsSync(preview)) {
        await copyFile(preview, path.join(THEME_OUT_DIR, 'preview.jpg'))
      }
      console.log(`theme [${slug}] → ${path.relative(path.resolve(__dirname, '..'), THEME_OUT_DIR)}`)
    },
  }
}

/**
 * 三个独立构建目标：
 *   前台主题：site/  → public/themes/<slug>/（并复制为 public/index.html）
 *   后台：    admin/ → public/admin/（hash 路由，纯静态目录）
 *   任意一方编译失败都不影响另一方产物
 */
export default defineConfig(({ mode, command }) => {
  const isAdmin = mode === 'admin'
  const isDev = command === 'serve'
  const root = isAdmin ? ADMIN_DIR : SITE_DIR
  const themeBase = `/themes/${THEME_SLUG}/`

  return {
    root,
    publicDir: path.resolve(__dirname, 'public'),
    base: isAdmin ? '/admin/' : isDev ? '/' : themeBase,
    plugins: [vue(), storagePlugin, ...(isAdmin ? [] : [themeActivatePlugin(THEME_SLUG)])],
    define: {
      __YCMS_EDITION__: JSON.stringify(EDITION),
    },
    css: {
      // root 指向 site/ 或 admin/，PostCSS/Tailwind 配置仍从 frontend/ 读取
      postcss: __dirname,
    },
    server: {
      port: 5173,
      fs: {
        // 允许引用 core/ 与 shared/（位于 root 之外）
        allow: [__dirname, path.resolve(__dirname, '..')],
      },
      proxy: {
        '/api': { target: 'http://localhost:8080', changeOrigin: true },
        '/api.php': { target: 'http://localhost:8080', changeOrigin: true },
        // SEO 服务端渲染路由交由 PHP 处理（^ 表示正则，避免误伤 /posts）
        '^/post/': { target: 'http://localhost:8080', changeOrigin: true },
        '/sitemap.xml': { target: 'http://localhost:8080', changeOrigin: true },
        '/robots.txt': { target: 'http://localhost:8080', changeOrigin: true },
      },
    },
    build: {
      outDir: isAdmin ? path.join(PUBLIC_DIR, 'admin') : THEME_OUT_DIR,
      emptyOutDir: true,
      chunkSizeWarningLimit: 1500,
      rollupOptions: {
        input: path.join(root, 'index.html'),
      },
    },
  }
})

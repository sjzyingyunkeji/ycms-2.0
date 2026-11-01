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
const BACKEND_STORAGE = path.resolve(__dirname, '../backend/storage')

// 主题源码注册表：目录名即构建模式名（site → 默认主题，star → 星空主题）
// 只注册本仓库真实存在的主题，因此主仓库与星空仓库共用同一份构建配置，无需分叉
const THEME_DIRS = ['site', 'star']
const THEME_SOURCES = Object.fromEntries(
  THEME_DIRS
    .map((dir) => [dir, path.resolve(__dirname, dir)])
    .filter(([, srcDir]) => existsSync(path.join(srcDir, 'theme.json'))),
)
const DEFAULT_THEME_MODE = THEME_SOURCES.site ? 'site' : THEME_SOURCES.star ? 'star' : 'site'

/**
 * 解析主题元数据：slug 决定产物目录 /themes/<slug>/ 与资源前缀
 * @param {string} mode vite 构建模式（site / star），未命中时回退到本仓库的默认主题
 */
function resolveTheme(mode) {
  const key = THEME_SOURCES[mode] ? mode : DEFAULT_THEME_MODE
  const srcDir = THEME_SOURCES[key]
  if (!srcDir) {
    throw new Error('未找到任何主题源码：frontend/site 或 frontend/star 至少存在一个')
  }
  const meta = JSON.parse(readFileSync(path.join(srcDir, 'theme.json'), 'utf8'))
  const slug = String(meta.slug || 'default')
  return { srcDir, slug, outDir: path.join(PUBLIC_DIR, 'themes', slug) }
}

// 发行版本（条件编译）：oss = 开源产物，pro = 商业产物
// 构建时通过常量替换，未命中分支会被 Rollup 静态消除，产物中不留 Pro 代码
const EDITION = (process.env.YCMS_EDITION || 'oss').toLowerCase() === 'pro' ? 'pro' : 'oss'

// 后台皮肤：星空仓库（只含 star 主题）用星空后台，主仓库用通用扁平 UI。
// 皮肤差异只体现在 CSS token，后台组件源码全仓共用一份。
const ADMIN_SKIN = (process.env.YCMS_ADMIN_SKIN || (THEME_SOURCES.star && !THEME_SOURCES.site ? 'star' : 'default')) === 'star'
  ? 'star'
  : 'default'
const ADMIN_SKIN_CSS = path.resolve(
  __dirname,
  ADMIN_SKIN === 'star' ? 'shared/styles/admin-star.css' : 'shared/styles/ui.css'
)

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
 *   1. 把 theme.json 与 SSR 模板、预览图一并放进主题目录，形成可独立安装的主题包结构
 *   2. 默认主题（default）额外把外壳复制为站点根入口 public/index.html；
 *      其他主题（如 star）只落到 public/themes/<slug>/，不覆盖站点根入口
 */
function themeActivatePlugin({ srcDir, slug, outDir }) {
  return {
    name: 'ycms-theme-activate',
    apply: 'build',
    async closeBundle() {
      const shell = path.join(outDir, 'index.html')
      if (!existsSync(shell)) {
        this.warn('未找到主题外壳 index.html，跳过激活复制')
        return
      }
      await copyFile(path.join(srcDir, 'theme.json'), path.join(outDir, 'theme.json'))

      const tplDir = path.join(srcDir, 'templates')
      if (existsSync(tplDir)) {
        await cp(tplDir, path.join(outDir, 'templates'), { recursive: true })
      }

      // 主题预览图（主题包/主题市场展示用，可选）
      const preview = path.join(srcDir, 'preview.jpg')
      if (existsSync(preview)) {
        await copyFile(preview, path.join(outDir, 'preview.jpg'))
      }

      // 仅默认主题接管站点根入口，避免多主题构建互相覆盖
      if (slug === 'default') {
        await mkdir(PUBLIC_DIR, { recursive: true })
        await copyFile(shell, path.join(PUBLIC_DIR, 'index.html'))
      }

      console.log(`theme [${slug}] → ${path.relative(path.resolve(__dirname, '..'), outDir)}`)
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
  // 前台构建按模式选择主题源码：site → 默认主题，star → 星空主题
  const theme = isAdmin ? null : resolveTheme(mode)
  const root = isAdmin ? ADMIN_DIR : theme.srcDir
  const themeBase = theme ? `/themes/${theme.slug}/` : '/admin/'

  return {
    root,
    publicDir: path.resolve(__dirname, 'public'),
    base: isAdmin ? '/admin/' : isDev ? '/' : themeBase,
    plugins: [vue(), storagePlugin, ...(isAdmin ? [] : [themeActivatePlugin(theme)])],
    define: {
      __YCMS_EDITION__: JSON.stringify(EDITION),
      __YCMS_ADMIN_SKIN__: JSON.stringify(ADMIN_SKIN),
    },
    resolve: {
      alias: {
        // 后台皮肤样式：默认 → ui.css，星空 → admin-star.css（两者都基于同一套组件类）
        '@admin-skin': ADMIN_SKIN_CSS,
      },
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
      outDir: isAdmin ? path.join(PUBLIC_DIR, 'admin') : theme.outDir,
      emptyOutDir: true,
      chunkSizeWarningLimit: 1500,
      rollupOptions: {
        input: path.join(root, 'index.html'),
      },
    },
  }
})

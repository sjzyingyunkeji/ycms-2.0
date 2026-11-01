/**
 * 发行产物构建（能力分层的“条件编译”落地 + 单主题裁剪）
 *
 * PHP 没有预处理器，等价做法是物理裁剪：
 *   - oss 发行：删除 backend/src/Pro 与 theme-pro.php，modules.php 重写为空数组
 *   - pro 发行：保留 Pro 目录，modules.php 写入 Pro 模块类名
 *   - --theme=<slug>：只保留该主题，并让它的外壳接管站点根入口、按该主题重建静态页
 *
 * 用法：
 *   node scripts/build-release.mjs                          # 开源发行（内置全部主题）
 *   node scripts/build-release.mjs --edition=pro            # 商业发行
 *   node scripts/build-release.mjs --theme=star             # 只带星空主题的全套安装包
 *   node scripts/build-release.mjs --theme=default          # 只带默认主题（输出名不带后缀）
 *   node scripts/build-release.mjs --theme=star --out=dist/x  # 指定输出目录（调试用）
 *   node scripts/build-release.mjs --theme=star --repository=holytreasure/ycms-star
 *                                                          # 单仓库出星空包时指定其更新源仓库
 *   node scripts/build-release.mjs --flavor=star             # 显式覆盖清单里的发行风味
 *
 * 说明：--theme 只做「一份源码 → 多个发行包」，后端代码不做任何复制或双写。
 *       --repository / --flavor 会写入包内 release-source.json，决定该站点日后
 *       去哪个仓库检查更新；风味（default / star）必须与包内主题一致。
 */
import { execFileSync } from 'node:child_process'
import {
  copyFileSync,
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  rmSync,
  statSync,
  writeFileSync,
} from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')

const args = process.argv.slice(2)
const argOf = (name, fallback = '') => {
  const hit = args.find((a) => a.startsWith(`--${name}=`))
  return hit ? hit.split('=').slice(1).join('=') : fallback
}

const edition = (argOf('edition', 'oss') || 'oss').toLowerCase()
if (!['oss', 'pro'].includes(edition)) {
  console.error('edition 只能是 oss 或 pro')
  process.exit(1)
}

const themeSlug = argOf('theme').toLowerCase()
if (themeSlug && !/^[a-z0-9][a-z0-9-]{1,31}$/.test(themeSlug)) {
  console.error('--theme 需为小写 slug（如 default / star）')
  process.exit(1)
}

const version = readFileSync(path.join(root, 'VERSION'), 'utf8').trim()

/* ==================== 发布源（release-source.json） ==================== */

const releaseSource = (() => {
  const fallback = { repository: 'sjzyingyunkeji/ycms-2.0', branch: 'main', channel: 'stable', flavor: 'default' }
  const file = path.join(root, 'release-source.json')
  if (!existsSync(file)) return fallback
  try {
    const raw = JSON.parse(readFileSync(file, 'utf8'))
    const merged = { ...fallback }
    for (const key of Object.keys(fallback)) {
      const value = typeof raw[key] === 'string' ? raw[key].trim() : ''
      if (value) merged[key] = value
    }
    if (!/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(merged.repository)) merged.repository = fallback.repository
    if (!['default', 'star'].includes(merged.flavor)) merged.flavor = fallback.flavor
    return merged
  } catch {
    console.error('release-source.json 解析失败，请检查 JSON 格式')
    process.exit(1)
  }
})()

const repositoryOverride = argOf('repository').trim()
if (repositoryOverride && !/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(repositoryOverride)) {
  console.error('--repository 需为 owner/name 形式，例如 holytreasure/ycms-star')
  process.exit(1)
}

// 风味跟随主题：星空包的清单必须标记 flavor=star，否则站点会拉到默认版升级包
const flavor = argOf('flavor').trim() || themeSlug || releaseSource.flavor
if (!['default', 'star'].includes(flavor)) {
  console.error(`未知风味：${flavor}（只支持 default / star）`)
  process.exit(1)
}
if (themeSlug && argOf('flavor').trim() && flavor !== themeSlug) {
  console.error(`--flavor=${flavor} 与 --theme=${themeSlug} 不一致：包的清单标记必须与包内主题对应`)
  process.exit(1)
}

const sourceRepository = repositoryOverride || releaseSource.repository

// 单主题包必须有对应的更新仓库，否则装完会去拉另一条发行线的清单
if (themeSlug && flavor !== 'default' && sourceRepository === releaseSource.repository
  && releaseSource.flavor !== flavor) {
  console.error(`构建 ${flavor} 发行包时，release-source.json 的 flavor 仍是 ${releaseSource.flavor}。`)
  console.error(`请在 ${flavor} 仓库里把 release-source.json 改为自身仓库，或显式传 --repository=<owner>/<name>`)
  process.exit(1)
}

const defaultName = `ycms-${version}-${edition}`
// 指定主题时，非默认主题追加后缀，避免覆盖已有发行目录
const suffix = themeSlug && themeSlug !== 'default' ? `-${themeSlug}` : ''
const outArg = argOf('out')
const outDir = outArg
  ? path.resolve(root, outArg)
  : path.join(root, 'release', defaultName + suffix)

// 主题合法性校验：必须有 theme.json 与构建后的主题外壳
if (themeSlug) {
  const themeDir = path.join(root, 'public/themes', themeSlug)
  const shell = path.join(themeDir, 'index.html')
  if (!existsSync(path.join(themeDir, 'theme.json')) || !existsSync(shell)) {
    console.error(`主题 ${themeSlug} 未构建或不存在：先执行 cd frontend && npm run build:${themeSlug === 'star' ? 'star' : 'site'}`)
    process.exit(1)
  }
  const meta = JSON.parse(readFileSync(path.join(themeDir, 'theme.json'), 'utf8'))
  if (String(meta.slug || '') !== themeSlug) {
    console.error(`public/themes/${themeSlug}/theme.json 的 slug 为 "${meta.slug}"，与目录名不一致`)
    process.exit(1)
  }
}

// 每次全量重建，避免残留上一次的 Pro / 主题文件
rmSync(outDir, { recursive: true, force: true })
mkdirSync(outDir, { recursive: true })

const ROOT_FILES = ['README.md', 'VERSION', 'release-source.json', 'update.json', 'update-notes.txt', 'themes.json']
// docs/ 不进发行包：站点用不到，只会让安装包变大
const COPY_DIRS = ['backend', 'public']

const isProFile = (src) => {
  const rel = path.relative(root, src).split(path.sep).join('/')
  return rel === 'backend/src/Pro'
    || rel.startsWith('backend/src/Pro/')
    || rel === 'backend/tools/theme-pro.php'
    || rel === 'backend/config/modules.php'
}

/**
 * 主题裁剪：--theme 指定时，public/themes 下只保留目标主题
 * 目录本身与点文件（.htaccess）保留，其余主题目录整枝跳过
 */
const isOtherTheme = (rel) => {
  if (!themeSlug || !rel.startsWith('public/themes/')) return false
  const parts = rel.split('/')
  if (parts.length < 3) return false
  if (parts[2].startsWith('.')) return false
  return parts[2] !== themeSlug
}

const skip = (src) => {
  const rel = path.relative(root, src).split(path.sep).join('/')
  if (rel === '') return false
  if (rel.startsWith('release')) return true
  if (rel.includes('node_modules')) return true
  if (rel.startsWith('frontend')) return true
  if (rel.startsWith('themes/')) return true
  if (rel.startsWith('docs/')) return true
  // 目录本身也要跳过：storage 在工作副本里可能是 junction/符号链接，复制会因 EPERM 失败
  if (rel === 'backend/storage' || rel.startsWith('backend/storage/')) return true
  if (rel === 'backend/vendor' || rel.startsWith('backend/vendor/')) return true
  if (rel === 'public/storage' || rel.startsWith('public/storage/')) return true
  if (rel.endsWith('.log')) return true
  if (rel === 'backend/.env') return true
  // 高危重置工具不随包分发，忘记密码请在仓库下载（见 README 底部 Q&A）
  if (rel === 'backend/tools/reset-password.php') return true
  if (edition === 'oss' && isProFile(src)) return true
  if (isOtherTheme(rel)) return true
  return false
}

for (const file of ROOT_FILES) {
  const src = path.join(root, file)
  if (existsSync(src)) cpSync(src, path.join(outDir, file))
}

for (const dir of COPY_DIRS) {
  const src = path.join(root, dir)
  if (!existsSync(src)) continue
  cpSync(src, path.join(outDir, dir), {
    recursive: true,
    filter: (from) => !skip(from),
  })
}

// 发布源随包落盘：站点升级后仍按自己的仓库检查更新
writeFileSync(
  path.join(outDir, 'release-source.json'),
  JSON.stringify({
    ...releaseSource,
    repository: sourceRepository,
    flavor,
  }, null, 4) + '\n',
  'utf8',
)

// 运行时目录占位（不可写会导致安装失败，但不应打包历史数据）
mkdirSync(path.join(outDir, 'backend/storage'), { recursive: true })
mkdirSync(path.join(outDir, 'public/storage/uploads'), { recursive: true })

/* ==================== 单主题发行：根入口 / 激活状态 / 静态页 ==================== */

const outPublic = path.join(outDir, 'public')
const outThemes = path.join(outPublic, 'themes')

if (themeSlug) {
  // 兜底：过滤器之外再显式清一次，确保包内只有一个主题
  if (existsSync(outThemes)) {
    for (const name of readdirSync(outThemes)) {
      const full = path.join(outThemes, name)
      if (!statSync(full).isDirectory()) continue
      if (name !== themeSlug) rmSync(full, { recursive: true, force: true })
    }
  }

  // 根入口必须是该主题外壳副本，否则删掉 default 后站点直接 404
  copyFileSync(path.join(outThemes, themeSlug, 'index.html'), path.join(outPublic, 'index.html'))

  // 激活状态：即使旧版本升级保留原 theme.json，也会被这份覆盖
  writeFileSync(
    path.join(outDir, 'backend/storage/theme.json'),
    JSON.stringify({ active: themeSlug, previous: null, activated_at: new Date().toISOString() }, null, 4) + '\n',
    'utf8',
  )
}

/**
 * 静态页按主题重建：列表/详情页里硬编码了 /themes/<slug>/assets/… 与主题类名，
 * 不重建就会出现「包能装、页面样式全丢」。
 * 依赖本机 PHP 与项目数据库（临时拷入，构建后立即删除）。
 */
const rebuildStatic = () => {
  if (!themeSlug) return { rebuilt: false, reason: '未指定 --theme' }
  const phpBin = process.env.PHP_BIN || 'php'
  try {
    execFileSync(phpBin, ['-v'], { stdio: 'ignore' })
  } catch {
    return { rebuilt: false, reason: '未找到 php，跳过静态页重建（安装/升级流程会重新生成）' }
  }

  const dbSrc = path.join(root, 'backend/storage/database.sqlite')
  if (!existsSync(dbSrc)) {
    return { rebuilt: false, reason: '缺少 database.sqlite，跳过静态页重建（安装/升级流程会重新生成）' }
  }

  const dbDst = path.join(outDir, 'backend/storage/database.sqlite')
  copyFileSync(dbSrc, dbDst)
  try {
    const out = execFileSync(phpBin, ['backend/tools/build-static.php'], {
      cwd: outDir,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe'],
    })
    return { rebuilt: true, reason: out.trim() }
  } catch (error) {
    console.error('静态页重建失败，发行包不可用：')
    console.error(String(error.stderr || error.message).trim())
    process.exit(1)
  } finally {
    rmSync(dbDst, { force: true })
    for (const extra of ['-wal', '-shm']) {
      rmSync(dbDst + extra, { force: true })
    }
  }
}

/* ==================== 模块清单（能力开关） ==================== */

const modulesFile = path.join(outDir, 'backend/config/modules.php')
const header = `<?php

/*
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 */

declare(strict_types=1);

`
if (edition === 'pro') {
  writeFileSync(modulesFile, header + `return [\n    \\App\\Pro\\Theme\\ThemeModule::class,\n];\n`, 'utf8')
} else {
  writeFileSync(modulesFile, header + `return [];\n`, 'utf8')
}

// 静态页重建依赖 modules.php 已就位（config 会 require 它），必须放在其后
const staticResult = rebuildStatic()

/* ==================== 清单：只保留包内实际存在的主题 ==================== */

if (themeSlug) {
  const themesManifest = path.join(outDir, 'themes.json')
  if (existsSync(themesManifest)) {
    const data = JSON.parse(readFileSync(themesManifest, 'utf8'))
    if (data.themes && data.themes[themeSlug]) {
      data.themes = { [themeSlug]: data.themes[themeSlug] }
      writeFileSync(themesManifest, JSON.stringify(data, null, 4) + '\n', 'utf8')
    } else {
      console.warn(`! themes.json 中没有 ${themeSlug} 条目（先执行 npm run pack:${themeSlug === 'star' ? 'star' : 'theme'}）`)
    }
  }

  const updateManifest = path.join(outDir, 'update.json')
  if (existsSync(updateManifest)) {
    const data = JSON.parse(readFileSync(updateManifest, 'utf8'))
    if (data.themes && data.themes[themeSlug]) {
      data.themes = { [themeSlug]: data.themes[themeSlug] }
      writeFileSync(updateManifest, JSON.stringify(data, null, 4) + '\n', 'utf8')
    }
  }
}

/* ==================== 断言 ==================== */

const malformed = []

if (edition === 'oss') {
  const forbidden = ['App\\\\Pro\\\\', 'ThemeInstaller', 'ThemeSwitcher', 'theme-pro.php', '/api/system/themes/install']
  const hits = []
  const walk = (dir) => {
    for (const name of readdirSync(dir)) {
      const full = path.join(dir, name)
      const stat = statSync(full)
      if (stat.isDirectory()) {
        walk(full)
        continue
      }
      if (!/\.(php|js|mjs|json|md|html|css|txt)$/i.test(name)) continue
      const text = readFileSync(full, 'utf8')
      for (const term of forbidden) {
        if (text.includes(term)) {
          hits.push(`${path.relative(outDir, full)} → ${term}`)
        }
      }
    }
  }
  // 只扫描运行时代码；docs/ 与 README 允许说明 Pro 能力
  walk(path.join(outDir, 'backend'))
  walk(path.join(outDir, 'public'))

  if (hits.length > 0) {
    console.error('开源产物中仍存在 Pro 能力符号：')
    hits.slice(0, 20).forEach((h) => console.error('  ' + h))
    process.exit(1)
  }
  console.log('✓ 开源产物已剔除 Pro 能力（物理裁剪 + 符号断言）')
}

if (themeSlug) {
  const themesLeft = existsSync(outThemes)
    ? readdirSync(outThemes).filter((n) => statSync(path.join(outThemes, n)).isDirectory())
    : []
  if (themesLeft.length !== 1 || themesLeft[0] !== themeSlug) {
    malformed.push(`public/themes 内应只有 ${themeSlug}，实际：${themesLeft.join(', ') || '空'}`)
  }
  const shellText = readFileSync(path.join(outPublic, 'index.html'), 'utf8')
  if (!shellText.includes(`/themes/${themeSlug}/`)) {
    malformed.push(`public/index.html 未指向 /themes/${themeSlug}/`)
  }
  const state = JSON.parse(readFileSync(path.join(outDir, 'backend/storage/theme.json'), 'utf8'))
  if (state.active !== themeSlug) {
    malformed.push(`backend/storage/theme.json 的 active 应为 ${themeSlug}，实际 ${state.active}`)
  }
  // 更新源必须与包内主题一致，否则装完会去拉另一条发行线的清单
  const sourceOut = JSON.parse(readFileSync(path.join(outDir, 'release-source.json'), 'utf8'))
  if (sourceOut.flavor !== themeSlug) {
    malformed.push(`release-source.json 的 flavor 应为 ${themeSlug}，实际 ${sourceOut.flavor}`)
  }
  if (!sourceOut.repository) {
    malformed.push('release-source.json 缺少 repository')
  }
  // 静态页（存在时）不得残留其它主题前缀
  const postsIndex = path.join(outPublic, 'posts/index.html')
  if (existsSync(postsIndex)) {
    const page = readFileSync(postsIndex, 'utf8')
    const refs = [...new Set(page.match(/\/themes\/[a-z0-9-]+\//g) || [])]
    const bad = refs.filter((r) => !r.includes(`/${themeSlug}/`))
    if (bad.length > 0) {
      malformed.push(`posts/index.html 仍引用其它主题：${bad.join(', ')}（静态页未按主题重建）`)
    }
  }
  if (malformed.length > 0) {
    console.error('单主题发行包校验失败：')
    malformed.forEach((m) => console.error('  ' + m))
    process.exit(1)
  }
  console.log(`✓ 单主题发行包校验通过：themes=[${themeSlug}]、根入口与静态页均已对齐`)
} else if (!existsSync(path.join(outDir, 'release-source.json'))) {
  console.error('发行包缺少 release-source.json：站点将回退到主仓库清单，请检查 ROOT_FILES')
  process.exit(1)
}

const countFiles = (dir) => {
  let total = 0
  for (const name of readdirSync(dir)) {
    const full = path.join(dir, name)
    total += statSync(full).isDirectory() ? countFiles(full) : 1
  }
  return total
}

if (staticResult.rebuilt) {
  console.log(`✓ 静态页已按 ${themeSlug} 重建：${staticResult.reason}`)
} else if (themeSlug) {
  console.warn(`! ${staticResult.reason}`)
}

console.log(`✓ 更新源：${sourceRepository}（flavor=${flavor}，分支 ${releaseSource.branch}）`)
console.log(`✓ ${edition} 发行产物：${path.relative(root, outDir)}（${countFiles(outDir)} 个文件）`)

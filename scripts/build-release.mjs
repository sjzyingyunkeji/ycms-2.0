/**
 * 发行产物构建（能力分层的“条件编译”落地）
 *
 * PHP 没有预处理器，等价做法是物理裁剪：
 *   - oss 发行：删除 backend/src/Pro 与 theme-pro.php，modules.php 重写为空数组
 *   - pro 发行：保留 Pro 目录，modules.php 写入 Pro 模块类名
 *
 * 用法：
 *   node scripts/build-release.mjs                # 开源发行
 *   node scripts/build-release.mjs --edition=pro  # 商业发行
 */
import { cpSync, existsSync, mkdirSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const edition = (process.argv.find((a) => a.startsWith('--edition='))?.split('=')[1] || 'oss').toLowerCase()
if (!['oss', 'pro'].includes(edition)) {
  console.error('edition 只能是 oss 或 pro')
  process.exit(1)
}

const version = readFileSync(path.join(root, 'VERSION'), 'utf8').trim()
const outDir = path.join(root, 'release', `ycms-${version}-${edition}`)

// 每次全量重建，避免残留上一次的 Pro 文件
rmSync(outDir, { recursive: true, force: true })
mkdirSync(outDir, { recursive: true })

const ROOT_FILES = ['README.md', 'VERSION', 'update.json', 'update-notes.txt', 'themes.json']
const COPY_DIRS = ['backend', 'public', 'docs']

const isProFile = (src) => {
  const rel = path.relative(root, src).split(path.sep).join('/')
  return rel === 'backend/src/Pro'
    || rel.startsWith('backend/src/Pro/')
    || rel === 'backend/tools/theme-pro.php'
    || rel === 'backend/config/modules.php'
}

const skip = (src) => {
  const rel = path.relative(root, src).split(path.sep).join('/')
  if (rel === '') return false
  if (rel.startsWith('release')) return true
  if (rel.includes('node_modules')) return true
  if (rel.startsWith('frontend')) return true
  if (rel.startsWith('themes/')) return true
  if (rel.startsWith('docs/private')) return true
  if (rel.startsWith('backend/storage/')) return true
  if (rel.startsWith('backend/vendor/')) return true
  if (rel.startsWith('public/storage/')) return true
  if (rel.endsWith('.log')) return true
  if (rel === 'backend/.env') return true
  if (edition === 'oss' && isProFile(src)) return true
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

// 运行时目录占位（不可写会导致安装失败，但不应打包历史数据）
mkdirSync(path.join(outDir, 'backend/storage'), { recursive: true })
mkdirSync(path.join(outDir, 'public/storage/uploads'), { recursive: true })

// 重写模块清单：这是“产物里有没有该能力”的开关
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

// 开源产物断言：不得出现任何 Pro 能力符号
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

const countFiles = (dir) => {
  let total = 0
  for (const name of readdirSync(dir)) {
    const full = path.join(dir, name)
    total += statSync(full).isDirectory() ? countFiles(full) : 1
  }
  return total
}

console.log(`✓ ${edition} 发行产物：${path.relative(root, outDir)}（${countFiles(outDir)} 个文件）`)

/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 前台主题批量构建
 *
 *   node scripts/build-themes.mjs      # 构建本仓库存在的全部主题
 *
 * 主仓库只有 frontend/site（默认主题），星空仓库只有 frontend/star（星空主题），
 * 因此构建链不需要分叉：谁在仓库里就构建谁。
 */

import { execFileSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { build } from 'vite'

const frontend = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')

/** 主题源码目录名即 vite 构建模式名 */
const THEMES = ['site', 'star'].filter((dir) => existsSync(path.join(frontend, dir, 'theme.json')))

if (THEMES.length === 0) {
  console.error('✖ 未找到主题源码：frontend/site 或 frontend/star 至少存在一个')
  process.exit(1)
}

for (const dir of THEMES) {
  console.log(`\n>>> 构建主题：${dir}`)
  // 先清理该主题的旧产物，避免残留上一次构建的 chunk
  execFileSync(process.execPath, [path.join(frontend, 'scripts/clean-site-assets.mjs'), `--src=${dir}`], {
    stdio: 'inherit',
    cwd: frontend,
  })
  // 只传 mode：root / base / outDir 全部由 vite.config.js 按主题推导
  await build({ mode: dir, configFile: path.join(frontend, 'vite.config.js') })
}

console.log(`\n✓ 主题构建完成：${THEMES.join(' + ')}`)

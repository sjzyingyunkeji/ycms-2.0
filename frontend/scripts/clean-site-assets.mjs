/**
 * 前台构建前置清理：只删除「本次构建主题」的产物，
 * 保留 public/ 下的 PHP 入口文件、静态页、其他主题与上传目录。
 *
 * 用法：
 *   node scripts/clean-site-assets.mjs            # 清理默认主题（site/）
 *   node scripts/clean-site-assets.mjs --src=star # 清理星空主题（star/）
 *
 * 注意：只有默认主题（default）才拥有站点根入口 public/index.html，
 *       其他主题不得删除它，否则会打断默认主题的部署。
 */
import { existsSync, readFileSync, rmSync } from 'node:fs'
import path from 'node:path'

const publicDir = path.resolve('../public')

const srcArg = process.argv.slice(2).find((a) => a.startsWith('--src='))
const srcDir = srcArg ? srcArg.split('=')[1] : 'site'

const meta = JSON.parse(readFileSync(path.resolve(srcDir, 'theme.json'), 'utf8'))
const slug = String(meta.slug || 'default')

const targets = [path.join(publicDir, 'themes', slug)]

// 站点根入口与旧版平铺目录只归属默认主题
if (slug === 'default') {
  targets.push(
    path.join(publicDir, 'index.html'),
    // 旧版单主题构建遗留目录（迁移后不再使用）
    path.join(publicDir, 'assets')
  )
}

for (const full of targets) {
  if (existsSync(full)) {
    rmSync(full, { recursive: true, force: true })
    console.log('removed:', path.relative(process.cwd(), full))
  }
}

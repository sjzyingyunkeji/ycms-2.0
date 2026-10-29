/**
 * 前台构建前置清理：只删除当前主题的产物与站点入口副本，
 * 保留 public/ 下的 PHP 入口文件、静态页、其他主题与上传目录。
 */
import { existsSync, readFileSync, rmSync } from 'node:fs'
import path from 'node:path'

const publicDir = path.resolve('../public')
const meta = JSON.parse(readFileSync(path.resolve('site/theme.json'), 'utf8'))
const slug = String(meta.slug || 'default')

const targets = [
  path.join(publicDir, 'themes', slug),
  path.join(publicDir, 'index.html'),
  // 旧版单主题构建遗留目录（迁移后不再使用）
  path.join(publicDir, 'assets'),
]

for (const full of targets) {
  if (existsSync(full)) {
    rmSync(full, { recursive: true, force: true })
    console.log('removed:', path.relative(process.cwd(), full))
  }
}

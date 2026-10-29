/**
 * 后台构建收尾：把 dist/admin/admin.html 重命名为 index.html，
 * 使其成为纯静态目录入口（访问 /admin/ 即可，无需任何重写规则）
 */
import { existsSync, renameSync } from 'node:fs'
import path from 'node:path'

const dir = path.resolve('../public/admin')
const from = path.join(dir, 'admin.html')
const to = path.join(dir, 'index.html')

if (existsSync(from)) {
  renameSync(from, to)
  console.log('admin entry → dist/admin/index.html')
} else if (existsSync(to)) {
  console.log('admin entry already at dist/admin/index.html')
} else {
  console.warn('warn: dist/admin/admin.html not found')
}

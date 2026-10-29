/**
 * 生成 backend/.env（随机 JWT 密钥）
 */
import { writeFileSync, existsSync } from 'node:fs'
import { randomBytes } from 'node:crypto'
import path from 'node:path'

const envPath = path.resolve('../backend/.env')
if (existsSync(envPath)) {
  console.log('.env 已存在，跳过')
  process.exit(0)
}

const content = [
  'APP_NAME="ycms2.0"',
  'APP_ENV=production',
  'APP_URL=http://localhost:8080',
  'APP_TIMEZONE=Asia/Shanghai',
  'DB_PATH=storage/database.sqlite',
  `JWT_SECRET=${randomBytes(32).toString('hex')}`,
  'JWT_TTL=86400',
  'UPLOAD_MAX_SIZE=10485760',
  '',
].join('\n')

writeFileSync(envPath, content, 'utf8')
console.log('.env 已生成')

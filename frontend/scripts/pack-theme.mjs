/**
 * 主题打包（P6）
 *
 * 把已安装的主题目录 public/themes/<slug>/ 打成可分发主题包：
 *
 *   release/themes/ycms-theme-<slug>-<version>.zip
 *   ├── theme.json
 *   ├── index.html
 *   ├── assets/
 *   ├── templates/
 *   └── preview.jpg（存在才打）
 *
 * 同时产出：
 *   - 根目录 themes.json（主题清单：版本 / 包名 / sha256 / 体积；按 slug 合并写入）
 *   - 合并写入 update.json 的 themes 字段（旧客户端忽略未知字段，向后兼容）
 *
 * 用法：
 *   node scripts/pack-theme.mjs                    # 打包全部已安装主题
 *   node scripts/pack-theme.mjs --slug=default     # 只打包指定主题
 *   node scripts/pack-theme.mjs --no-manifest      # 只出包，不写清单
 *   node scripts/pack-theme.mjs --url-base=https://cdn.example.com/themes
 *
 * 说明：不依赖任何第三方库，内置 store 之外的 deflate 打包实现；
 *       包内文件顺序与时间戳固定（1980-01-01），内容相同则 sha256 相同，便于做差分与校验。
 */
import { createHash } from 'node:crypto'
import { existsSync, mkdirSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { deflateRawSync } from 'node:zlib'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const themesDir = path.join(root, 'public/themes')
const outDir = path.join(root, 'release/themes')

const args = process.argv.slice(2)
const argOf = (name, fallback = '') => {
  const hit = args.find((a) => a.startsWith(`--${name}=`))
  return hit ? hit.split('=').slice(1).join('=') : fallback
}
const onlySlug = argOf('slug')
const writeManifest = !args.includes('--no-manifest')
const urlBase = argOf('url-base').replace(/\/+$/, '')

const coreVersion = readFileSync(path.join(root, 'VERSION'), 'utf8').trim()

/**
 * 发布源（release-source.json）
 * 主仓库与主题仓库各持一份，下载地址据此推导，不再写死仓库名。
 */
const source = (() => {
  const fallback = { repository: 'sjzyingyunkeji/ycms-2.0', branch: 'main', flavor: 'default' }
  const file = path.join(root, 'release-source.json')
  if (!existsSync(file)) return fallback
  try {
    const raw = JSON.parse(readFileSync(file, 'utf8'))
    const merged = { ...fallback }
    for (const key of Object.keys(fallback)) {
      const value = typeof raw[key] === 'string' ? raw[key].trim() : ''
      if (value) merged[key] = value
    }
    if (!/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(merged.repository)) {
      merged.repository = fallback.repository
    }
    if (!['default', 'star'].includes(merged.flavor)) merged.flavor = fallback.flavor
    return merged
  } catch {
    console.warn('! release-source.json 解析失败，回退到主仓库地址')
    return fallback
  }
})()

/* ==================== 极简 zip 写入（deflate，无依赖） ==================== */

const CRC_TABLE = (() => {
  const table = new Int32Array(256)
  for (let i = 0; i < 256; i++) {
    let c = i
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
    table[i] = c
  }
  return table
})()

function crc32(buf) {
  let c = -1
  for (let i = 0; i < buf.length; i++) c = (c >>> 8) ^ CRC_TABLE[(c ^ buf[i]) & 0xff]
  return (c ^ -1) >>> 0
}

// 固定 DOS 时间戳：1980-01-01 00:00，保证同内容同哈希
const DOS_TIME = 0
const DOS_DATE = ((1980 - 1980) << 9) | (1 << 5) | 1

/**
 * @param {{name:string, data:Buffer}[]} files
 * @returns {Buffer}
 */
function buildZip(files) {
  const locals = []
  const centrals = []
  let offset = 0

  for (const file of files) {
    const nameBuf = Buffer.from(file.name, 'utf8')
    const deflated = deflateRawSync(file.data, { level: 9 })
    const compressed = deflated.length < file.data.length ? deflated : file.data
    const method = compressed === deflated ? 8 : 0
    const crc = crc32(file.data)

    const local = Buffer.alloc(30 + nameBuf.length)
    local.writeUInt32LE(0x04034b50, 0)
    local.writeUInt16LE(20, 4)
    local.writeUInt16LE(0x0800, 6)
    local.writeUInt16LE(method, 8)
    local.writeUInt16LE(DOS_TIME, 10)
    local.writeUInt16LE(DOS_DATE, 12)
    local.writeUInt32LE(crc, 14)
    local.writeUInt32LE(compressed.length, 18)
    local.writeUInt32LE(file.data.length, 22)
    local.writeUInt16LE(nameBuf.length, 26)
    local.writeUInt16LE(0, 28)
    nameBuf.copy(local, 30)

    const central = Buffer.alloc(46 + nameBuf.length)
    central.writeUInt32LE(0x02014b50, 0)
    central.writeUInt16LE(20, 4)
    central.writeUInt16LE(20, 6)
    central.writeUInt16LE(0x0800, 8)
    central.writeUInt16LE(method, 10)
    central.writeUInt16LE(DOS_TIME, 12)
    central.writeUInt16LE(DOS_DATE, 14)
    central.writeUInt32LE(crc, 16)
    central.writeUInt32LE(compressed.length, 20)
    central.writeUInt32LE(file.data.length, 24)
    central.writeUInt16LE(nameBuf.length, 28)
    central.writeUInt16LE(0, 30)
    central.writeUInt16LE(0, 32)
    central.writeUInt16LE(0, 34)
    central.writeUInt16LE(0, 36)
    central.writeUInt32LE(0, 38)
    central.writeUInt32LE(offset, 42)
    nameBuf.copy(central, 46)

    locals.push(local, compressed)
    centrals.push(central)
    offset += local.length + compressed.length
  }

  const centralBuf = Buffer.concat(centrals)
  const eocd = Buffer.alloc(22)
  eocd.writeUInt32LE(0x06054b50, 0)
  eocd.writeUInt16LE(0, 4)
  eocd.writeUInt16LE(0, 6)
  eocd.writeUInt16LE(files.length, 8)
  eocd.writeUInt16LE(files.length, 10)
  eocd.writeUInt32LE(centralBuf.length, 12)
  eocd.writeUInt32LE(offset, 16)
  eocd.writeUInt16LE(0, 20)

  return Buffer.concat([...locals, centralBuf, eocd])
}

/* ==================== 收集主题文件 ==================== */

const SKIP_NAMES = new Set(['.DS_Store', 'Thumbs.db', '.gitkeep'])

function collect(dir, base = '') {
  const out = []
  for (const name of readdirSync(dir).sort()) {
    if (SKIP_NAMES.has(name) || name.startsWith('._')) continue
    const full = path.join(dir, name)
    const rel = base ? `${base}/${name}` : name
    if (statSync(full).isDirectory()) out.push(...collect(full, rel))
    else out.push({ name: rel, full })
  }
  return out
}

function listThemes() {
  if (!existsSync(themesDir)) return []
  return readdirSync(themesDir)
    .filter((name) => !name.startsWith('.') && statSync(path.join(themesDir, name)).isDirectory())
    .filter((name) => existsSync(path.join(themesDir, name, 'theme.json')))
    .filter((name) => (onlySlug ? name === onlySlug : true))
}

/* ==================== 打包 ==================== */

mkdirSync(outDir, { recursive: true })
const manifestPath = path.join(root, 'themes.json')
const updatePath = path.join(root, 'update.json')

const manifest = existsSync(manifestPath)
  ? JSON.parse(readFileSync(manifestPath, 'utf8'))
  : { generated_at: '', themes: {} }
manifest.themes = manifest.themes || {}

const packed = []

for (const slug of listThemes()) {
  const dir = path.join(themesDir, slug)
  const meta = JSON.parse(readFileSync(path.join(dir, 'theme.json'), 'utf8'))

  if (String(meta.slug || '') !== slug) {
    console.warn(`! 跳过 ${slug}：theme.json 的 slug 为 "${meta.slug}"，与目录名不一致`)
    continue
  }
  if (!existsSync(path.join(dir, 'index.html'))) {
    console.warn(`! 跳过 ${slug}：缺少主题外壳 index.html（先执行 npm run build:site）`)
    continue
  }
  if (meta.preview && !existsSync(path.join(dir, meta.preview))) {
    console.warn(`! ${slug}：theme.json 声明了 ${meta.preview}，但主题目录内不存在（包内将缺少预览图）`)
  }
  if (!existsSync(path.join(dir, 'templates'))) {
    console.warn(`! ${slug}：缺少 templates/，SSR 页面将回退到内核兜底模板`)
  }

  const files = collect(dir)
  const zip = buildZip(files.map((f) => ({ name: f.name, data: readFileSync(f.full) })))
  const version = String(meta.version || coreVersion)
  const filename = `ycms-theme-${slug}-${version}.zip`
  const target = path.join(outDir, filename)
  writeFileSync(target, zip)

  const sha256 = createHash('sha256').update(zip).digest('hex')
  packed.push({ slug, version, filename, size: zip.length, sha256, files: files.length, meta })
  console.log(`✓ ${filename}  ${(zip.length / 1024).toFixed(1)}KB  ${files.length} 个文件  sha256=${sha256.slice(0, 16)}…`)
}

if (packed.length === 0) {
  console.warn('没有可打包的主题（检查 public/themes/<slug>/theme.json）')
  process.exit(1)
}

/* ==================== 清单 ==================== */

if (writeManifest) {
  // 合并语义：只覆盖本次打包的主题，其它主题条目保持原样
  // （否则 pack:star 会把 default 从清单里抹掉，反之亦然）
  const themes = { ...manifest.themes }
  for (const item of packed) {
    themes[item.slug] = {
      slug: item.slug,
      name: item.meta.name || item.slug,
      version: item.version,
      author: item.meta.author || '',
      description: item.meta.description || '',
      license: item.meta.license || '',
      requires: item.meta.requires || {},
      preview: item.meta.preview || '',
      package: item.filename,
      size: item.size,
      sha256: item.sha256,
      url: urlBase
        ? `${urlBase}/${item.filename}`
        : `https://github.com/${source.repository}/releases/download/v${coreVersion}/${item.filename}`,
    }
  }
  writeFileSync(manifestPath, JSON.stringify({
    generated_at: new Date().toISOString(),
    core_version: coreVersion,
    repository: source.repository,
    flavor: source.flavor,
    themes,
  }, null, 4) + '\n', 'utf8')
  console.log(`✓ 主题清单 → themes.json（${Object.keys(themes).length} 个主题）`)

  // 兼容在线更新：update.json 增加 themes 摘要，旧客户端忽略未知字段
  if (existsSync(updatePath)) {
    const update = JSON.parse(readFileSync(updatePath, 'utf8'))
    update.themes = update.themes || {}
    for (const item of packed) {
      update.themes[item.slug] = {
        latest: item.version,
        package: item.filename,
        size: item.size,
        sha256: item.sha256,
        requires: item.meta.requires || {},
      }
    }
    writeFileSync(updatePath, JSON.stringify(update, null, 4) + '\n', 'utf8')
    console.log('✓ 主题摘要已合并到 update.json → themes')
  }
}

console.log(`\n主题包输出目录：${path.relative(root, outDir)}`)

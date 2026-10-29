/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 图片压缩工具
 * 将上传图片压缩到固定 800KB 以内（不可调节）
 * 策略：canvas 重绘 → 质量迭代下降（0.9 → 0.5）→ 尺寸逐级缩放（×0.88）→ 直至达标
 * 说明：GIF（动画）与 SVG（矢量）不参与压缩；PNG 压缩时转 WebP 以保留透明通道
 */

const MAX_BYTES = 800 * 1024 // 800KB（固定值，不可调节）

const COMPRESSIBLE = ['image/jpeg', 'image/png', 'image/webp']

/** 是否可压缩的图片类型 */
export function isCompressible(file) {
  return COMPRESSIBLE.includes(file.type)
}

/** 人类可读的文件大小 */
export function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1024 / 1024).toFixed(2) + ' MB'
}

function loadImage(file) {
  return new Promise((resolve, reject) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve(img)
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('图片加载失败'))
    }
    img.src = url
  })
}

function canvasToBlob(canvas, mime, quality) {
  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob), mime, quality)
  })
}

/**
 * 压缩单张图片至 800KB 以内
 * @returns {Promise<{file: File, originalSize: number, compressedSize: number, compressed: boolean}>}
 */
export async function compressImage(file) {
  const originalSize = file.size
  if (!isCompressible(file) || file.size <= MAX_BYTES) {
    return { file, originalSize, compressedSize: originalSize, compressed: false }
  }

  try {
    const img = await loadImage(file)
    if (!img.naturalWidth || !img.naturalHeight) {
      return { file, originalSize, compressedSize: originalSize, compressed: false }
    }

    // 输出格式：PNG 转 WebP（保留透明且体积更小）；JPEG/WebP 保持原格式
    const outMime = file.type === 'image/png' ? 'image/webp' : file.type
    const outExt = outMime === 'image/webp' ? 'webp' : outMime === 'image/png' ? 'png' : 'jpg'

    let width = img.naturalWidth
    let height = img.naturalHeight
    let quality = 0.9
    let blob = null

    // 迭代压缩：先降质量，后缩尺寸
    for (let attempt = 0; attempt < 14; attempt++) {
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      const ctx = canvas.getContext('2d')
      ctx.imageSmoothingEnabled = true
      ctx.imageSmoothingQuality = 'high'
      ctx.drawImage(img, 0, 0, width, height)

      blob = await canvasToBlob(canvas, outMime, quality)
      if (blob && blob.size <= MAX_BYTES) break

      if (quality > 0.5) {
        quality = Math.max(0.5, quality - 0.15)
      } else {
        width = Math.max(Math.round(width * 0.88), 64)
        height = Math.max(Math.round(height * 0.88), 64)
        quality = 0.7
      }
    }

    if (!blob) {
      return { file, originalSize, compressedSize: originalSize, compressed: false }
    }

    const baseName = file.name.replace(/\.[^.]+$/, '')
    const newFile = new File([blob], `${baseName}.${outExt}`, {
      type: outMime,
      lastModified: Date.now(),
    })
    return { file: newFile, originalSize, compressedSize: newFile.size, compressed: true }
  } catch {
    // 压缩失败时回退原文件（后端仍有 10MB 限制兜底）
    return { file, originalSize, compressedSize: originalSize, compressed: false }
  }
}

/**
 * 批量压缩
 * @param {FileList|File[]} files
 * @param {boolean} enabled 是否启用压缩
 */
export async function compressImages(files, enabled = true) {
  const list = Array.from(files)
  const results = []
  for (const file of list) {
    if (enabled && isCompressible(file)) {
      results.push(await compressImage(file))
    } else {
      results.push({ file, originalSize: file.size, compressedSize: file.size, compressed: false })
    }
  }
  return results
}

export const COMPRESS_LIMIT = MAX_BYTES

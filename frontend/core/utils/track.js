/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 前台访问统计上报
 * 使用 sendBeacon 上报，避免阻塞页面渲染；上报失败静默忽略
 */

import { API_BASE } from '../api/index.js'

// 与统一接口前缀保持一致：零配置部署下是 /api.php，配置了重写时才是 /api
const TRACK_URL = `${API_BASE.replace(/\/$/, '')}/track`

const lastAt = new Map()

/**
 * 上报一次页面访问
 * @param {string} path 访问路径
 * @param {number|null} postId 文章 ID（文章详情页）
 */
export function trackPage(path, postId = null) {
  if (typeof window === 'undefined') return

  const target = path || window.location.pathname
  const now = Date.now()
  const prev = lastAt.get(target) || 0
  // 同路径 2 秒内只上报一次（避免路由抖动重复统计）
  if (now - prev < 2000) return
  lastAt.set(target, now)

  const payload = JSON.stringify({
    path: target,
    post_id: postId,
    referer: document.referrer || '',
  })

  try {
    if (typeof navigator.sendBeacon === 'function') {
      navigator.sendBeacon(
        TRACK_URL,
        new Blob([payload], { type: 'application/json' })
      )
      return
    }
  } catch {
    /* 回退到 fetch */
  }

  try {
    fetch(TRACK_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: payload,
      keepalive: true,
    }).catch(() => {})
  } catch {
    /* 静默忽略统计失败 */
  }
}

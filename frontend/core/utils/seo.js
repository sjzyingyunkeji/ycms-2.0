/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * SEO 元信息管理：title / description / keywords / canonical / Open Graph
 */

function upsertMeta(attr, key, content) {
  if (!content) return
  let el = document.head.querySelector(`meta[${attr}="${key}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function upsertLink(rel, href) {
  if (!href) return
  let el = document.head.querySelector(`link[rel="${rel}"]`)
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', rel)
    document.head.appendChild(el)
  }
  el.setAttribute('href', href)
}

/**
 * 设置页面 SEO 信息
 * @param {{title?:string, description?:string, keywords?:string, image?:string, url?:string, type?:string, publishedAt?:string, author?:string}} options
 */
export function applySeo(options = {}) {
  const {
    title = '',
    description = '',
    keywords = '',
    image = '',
    url = '',
    type = 'website',
    publishedAt = '',
    author = '',
  } = options

  if (title) document.title = title

  upsertMeta('name', 'description', description)
  upsertMeta('name', 'keywords', keywords)
  upsertMeta('name', 'author', author)

  upsertMeta('property', 'og:type', type)
  upsertMeta('property', 'og:title', title)
  upsertMeta('property', 'og:description', description)
  upsertMeta('property', 'og:url', url)
  upsertMeta('property', 'og:image', image)
  upsertMeta('property', 'og:site_name', title.split('·')[0].trim() || title)
  if (publishedAt) {
    upsertMeta('property', 'article:published_time', publishedAt)
    upsertMeta('property', 'article:author', author)
  }

  upsertMeta('name', 'twitter:card', 'summary_large_image')
  upsertMeta('name', 'twitter:title', title)
  upsertMeta('name', 'twitter:description', description)
  upsertMeta('name', 'twitter:image', image)

  if (url) upsertLink('canonical', url)
}

/**
 * 注入结构化数据（JSON-LD）
 * @param {object} data
 */
export function applyJsonLd(data) {
  let el = document.getElementById('ycms-jsonld')
  if (!el) {
    el = document.createElement('script')
    el.type = 'application/ld+json'
    el.id = 'ycms-jsonld'
    document.head.appendChild(el)
  }
  el.textContent = JSON.stringify(data)
}

/**
 * 站点根地址
 */
export function siteOrigin() {
  return typeof window !== 'undefined' ? window.location.origin : ''
}

/**
 * 拼接绝对 URL
 * @param {string} path
 */
export function absoluteUrl(path) {
  if (!path) return siteOrigin()
  if (/^https?:\/\//i.test(path)) return path
  return siteOrigin() + (path.startsWith('/') ? path : '/' + path)
}

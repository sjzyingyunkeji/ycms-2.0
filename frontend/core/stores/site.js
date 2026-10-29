/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { settingApi } from '../api/index.js'

export const useSiteStore = defineStore('site', () => {
  const settings = ref({
    site_name: 'ycms2.0',
    site_title: 'ycms2.0 · 在代码与星空之间',
    logo_type: 'image',
    logo_image: '/logo.svg',
    hero_suffix: '的数字星空',
    site_description: '',
    site_icp: '',
    owner_name: '星尘',
    owner_bio: '',
    owner_avatar: '',
    social_github: '',
    social_email: '',
    social_weibo: '',
    social_zhihu: '',
    social_csdn: '',
    social_wechat: '',
    social_wechat_qr: '',
    nav_links: [],
    site_closed: '0',
    site_closed_message: '',
    posts_per_page: '10',
    home_post_sort: 'hot',
    track_enabled: '1',
  })
  const loaded = ref(false)

  async function fetchSettings() {
    try {
      const data = await settingApi.get()
      // nav_links 兼容字符串 / 数组两种返回
      if (typeof data.nav_links === 'string') {
        try {
          data.nav_links = JSON.parse(data.nav_links || '[]')
        } catch {
          data.nav_links = []
        }
      }
      if (!Array.isArray(data.nav_links)) data.nav_links = []
      settings.value = { ...settings.value, ...data }
      loaded.value = true
      document.title = settings.value.site_title || settings.value.site_name || 'ycms2.0'
    } catch {
      /* 使用默认值 */
    }
  }

  return { settings, loaded, fetchSettings }
})

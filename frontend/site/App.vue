<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSiteStore } from '../core/stores/site.js'
import { useAuthStore } from '../core/stores/auth.js'
import ToastHost from '../shared/components/ToastHost.vue'

const site = useSiteStore()
const auth = useAuthStore()
const router = useRouter()

onMounted(async () => {
  await site.fetchSettings()
  // Cookie 会话：静默检查登录态（24 小时内无需重新登录）
  await auth.checkSession()
  // 前台默认标题
  if (!router.currentRoute.value.meta.title) {
    document.title = site.settings.site_title || 'ycms2.0'
  }
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-[var(--y-bg)]">
    <router-view />
    <ToastHost />
  </div>
</template>

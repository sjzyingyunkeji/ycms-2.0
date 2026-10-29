<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 后台根组件（独立构建入口）
 * 与前台 App.vue 互不依赖：前台 UI 重构不会影响后台编译
 */

import { onMounted } from 'vue'
import { useSiteStore } from '../core/stores/site.js'
import { useAuthStore } from '../core/stores/auth.js'
import ToastHost from '../shared/components/ToastHost.vue'

const site = useSiteStore()
const auth = useAuthStore()

onMounted(async () => {
  await site.fetchSettings()
  // Cookie 会话：静默检查登录态
  await auth.checkSession()
})
</script>

<template>
  <div class="min-h-screen bg-[var(--y-bg)]">
    <router-view />
    <ToastHost />
  </div>
</template>

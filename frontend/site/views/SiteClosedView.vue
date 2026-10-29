<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { useSiteStore } from '../../core/stores/site.js'
import BrandLogo from '../../shared/components/BrandLogo.vue'

const site = useSiteStore()
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-5 bg-[var(--y-bg)]">
    <div class="w-full max-w-md text-center">
      <div class="inline-flex items-center justify-center mb-8 opacity-70">
        <BrandLogo size="lg" />
      </div>

      <h1 class="text-2xl font-bold text-[var(--y-text)] mb-5">站点暂时关闭</h1>

      <div class="y-card p-6 md:p-7 mb-6">
        <p class="text-sm text-[var(--y-text-secondary)] leading-relaxed whitespace-pre-wrap">
          {{ site.settings.site_closed_message || '站点正在维护升级中，暂时关闭访问，请稍后再来。' }}
        </p>
      </div>

      <a href="/admin/" class="text-xs text-[var(--y-text-muted)] hover:text-[var(--y-primary)] transition-colors duration-200">管理员登录</a>
    </div>
  </div>
</template>

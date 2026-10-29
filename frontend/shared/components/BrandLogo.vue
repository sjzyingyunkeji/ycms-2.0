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

/**
 * 品牌 Logo 组件（文字 / 图片二选一，后台「站点设置」控制）
 *  image 模式：仅显示图片
 *  text  模式：仅显示站点名（版本号仅在后台展示，前台不显示）
 */
defineProps({
  /** 显示尺寸：sm / md / lg */
  size: { type: String, default: 'md' },
})

const site = useSiteStore()

const imgSizes = { sm: 'w-8 h-8 rounded-lg', md: 'w-9 h-9 rounded-xl', lg: 'w-12 h-12 rounded-2xl' }
</script>

<template>
  <!-- 图片 Logo -->
  <img
    v-if="site.settings.logo_type === 'image' && site.settings.logo_image"
    :src="site.settings.logo_image"
    :alt="site.settings.site_name || 'ycms2.0'"
    :class="[imgSizes[size] || imgSizes.md, 'object-cover flex-shrink-0']"
  />
  <!-- 文字 Logo -->
  <div v-else class="leading-tight min-w-0 flex items-center gap-2">
    <div class="w-1.5 h-1.5 rounded-full bg-[var(--y-primary)] flex-shrink-0" />
    <div
      class="font-semibold text-[var(--y-text)] truncate"
      :class="size === 'lg' ? 'text-xl' : size === 'sm' ? 'text-sm' : 'text-base'"
    >
      {{ site.settings.site_name || 'ycms2.0' }}
    </div>
  </div>
</template>

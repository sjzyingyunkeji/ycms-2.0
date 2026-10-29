<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


defineProps({
  text: { type: String, default: '暂无数据' },
  icon: { type: String, default: 'star' },
  /** 风格：star（星空，后台默认）/ flat（扁平商务，前台使用） */
  variant: { type: String, default: 'star' },
})

const icons = {
  star: 'M12 2l2.4 7.4H22l-6 4.5 2.3 7.1-6.3-4.6L5.7 21l2.3-7.1-6-4.5h7.6z',
  file: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z M14 2v6h6',
  comment: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z',
  image: 'M19 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2z M8.5 10a1.5 1.5 0 100-3 1.5 1.5 0 000 3z M21 15l-5-5L5 21',
  link: 'M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71 M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71',
  users: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8z M23 21v-2a4 4 0 00-3-3.87 M16 3.13a4 4 0 010 7.75',
}
</script>

<template>
  <div class="flex flex-col items-center justify-center py-16" :class="variant === 'star' ? 'text-star-dim' : 'text-[var(--y-text-muted)]'">
    <svg class="w-12 h-12 mb-4" :class="variant === 'star' ? 'text-star-violet/40' : 'text-[var(--y-text-disabled)]'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <path :d="icons[icon] || icons.star" />
    </svg>
    <p class="text-sm">{{ text }}</p>
  </div>
</template>

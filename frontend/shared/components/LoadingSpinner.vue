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
  text: { type: String, default: '' },
  size: { type: String, default: 'w-8 h-8' },
  /** 风格：star（星空，后台默认）/ flat（扁平商务，前台使用） */
  variant: { type: String, default: 'star' },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 py-12">
    <div v-if="variant === 'star'" class="relative">
      <div class="animate-spin rounded-full border-2 border-white/10 border-t-star-violet border-r-star-cyan" :class="size" />
      <div class="absolute inset-0 rounded-full blur-md bg-star-violet/20 animate-pulse" />
    </div>
    <div v-else class="w-6 h-6 rounded-full border-2 border-[var(--y-border)] border-t-[var(--y-primary)] animate-spin" />
    <p v-if="text" class="text-sm" :class="variant === 'star' ? 'text-slate-400' : 'text-[var(--y-text-muted)]'">{{ text }}</p>
  </div>
</template>

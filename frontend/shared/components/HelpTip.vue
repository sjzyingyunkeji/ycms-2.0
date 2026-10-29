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
 * 圆圈问号提示：鼠标悬停显示说明文字
 */
defineProps({
  text: { type: String, required: true },
  width: { type: String, default: '15rem' },
})
</script>

<template>
  <span class="relative inline-flex items-center align-middle group/help">
    <span
      class="inline-flex h-4 w-4 cursor-help select-none items-center justify-center rounded-full border border-white/25 text-[10px] leading-none text-slate-400 transition-colors duration-200 group-hover/help:border-star-glow/60 group-hover/help:text-star-glow"
    >?</span>
    <span
      class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 rounded-lg border border-white/10 bg-space-900/95 px-3 py-2 text-[11px] font-normal leading-relaxed text-slate-300 opacity-0 shadow-xl backdrop-blur-sm transition-opacity duration-150 group-hover/help:opacity-100"
      :style="{ width }"
    >
      {{ text }}
    </span>
  </span>
</template>

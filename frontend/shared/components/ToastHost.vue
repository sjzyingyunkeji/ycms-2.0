<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { toastState } from '../../core/utils/toast.js'

const icons = {
  success: 'M20 6L9 17l-5-5',
  error: 'M18 6L6 18M6 6l12 12',
  warning: 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
  info: 'M12 16v-4m0-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
}

const iconColors = {
  success: 'text-emerald-400',
  error: 'text-rose-400',
  warning: 'text-amber-400',
  info: 'text-star-cyan',
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed top-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toastState.toasts"
          :key="toast.id"
          class="glass-strong pointer-events-auto flex items-center gap-3 px-4 py-3 min-w-[260px] max-w-[400px]"
          :class="iconColors[toast.type]"
        >
          <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path :d="icons[toast.type]" />
          </svg>
          <span class="text-sm text-slate-200">{{ toast.message }}</span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active {
  transition: all 0.4s cubic-bezier(0.21, 1.02, 0.73, 1);
}
.toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(40px) scale(0.9);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}
</style>

<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  width: { type: String, default: 'max-w-lg' },
  /** 风格：star（星空，后台默认）/ flat（扁平商务，前台使用） */
  variant: { type: String, default: 'star' },
})

const emit = defineEmits(['update:modelValue', 'close'])

watch(
  () => props.modelValue,
  (val) => {
    document.body.style.overflow = val ? 'hidden' : ''
  }
)

function close() {
  emit('update:modelValue', false)
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
        <!-- 遮罩必须铺满视口，否则毛玻璃没有可模糊的背景 -->
        <div
          class="absolute inset-0"
          :class="variant === 'star' ? 'bg-space-950/70 backdrop-blur-md' : 'bg-black/40 backdrop-blur-[2px]'"
          @click="close"
        />
        <div
          class="relative w-full p-6"
          :class="[
            width,
            variant === 'star'
              ? 'glass-medium page-enter shadow-[0_18px_60px_-20px_rgba(4,6,26,0.85)]'
              : 'bg-[var(--y-surface)] rounded-[var(--y-radius-card)] border border-[var(--y-border)] shadow-xl',
          ]"
        >
          <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold" :class="variant === 'star' ? 'font-serif text-slate-100' : 'text-[var(--y-text)]'">{{ title }}</h3>
            <button :class="variant === 'star' ? 'text-slate-400 hover:text-white' : 'text-[var(--y-text-muted)] hover:text-[var(--y-text)]'" class="transition-colors p-1" @click="close">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
            </button>
          </div>
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>

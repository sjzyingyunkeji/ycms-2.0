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
  page: { type: Number, default: 1 },
  pages: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
  /** 风格：star（星空，后台默认）/ flat（扁平商务，前台使用） */
  variant: { type: String, default: 'star' },
})

const emit = defineEmits(['change'])
function go(p) {
  if (p < 1) return
  emit('change', p)
}

function pageNumbers(page, pages) {
  const items = []
  if (pages <= 7) {
    for (let i = 1; i <= pages; i++) items.push(i)
    return items
  }
  items.push(1)
  let start = Math.max(2, page - 1)
  let end = Math.min(pages - 1, page + 1)
  if (page <= 3) { start = 2; end = 4 }
  if (page >= pages - 2) { start = pages - 3; end = pages - 1 }
  if (start > 2) items.push('...')
  for (let i = start; i <= end; i++) items.push(i)
  if (end < pages - 1) items.push('...')
  items.push(pages)
  return items
}
</script>

<template>
  <div v-if="pages > 1" class="flex items-center justify-center gap-2 select-none">
    <!-- 扁平商务（前台） -->
    <template v-if="variant !== 'star'">
      <button
        class="inline-flex items-center gap-1 h-8 px-3 rounded-[var(--y-radius-sm)] border border-[var(--y-border)] text-[13px] text-[var(--y-text-secondary)] bg-[var(--y-surface)] hover:text-[var(--y-primary)] hover:border-[var(--y-primary)] transition-colors duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:text-[var(--y-text-secondary)] disabled:hover:border-[var(--y-border)]"
        :disabled="page <= 1"
        @click="go(page - 1)"
      >
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
        上一页
      </button>

      <template v-for="(p, i) in pageNumbers(page, pages)" :key="i">
        <span v-if="p === '...'" class="text-[var(--y-text-disabled)] px-1">…</span>
        <button
          v-else
          class="inline-flex items-center justify-center h-8 min-w-8 px-2 rounded-[var(--y-radius-sm)] border text-[13px] transition-colors duration-200"
          :class="p === page
            ? 'bg-[var(--y-primary)] border-[var(--y-primary)] text-white font-medium'
            : 'border-[var(--y-border)] bg-[var(--y-surface)] text-[var(--y-text-secondary)] hover:text-[var(--y-primary)] hover:border-[var(--y-primary)]'"
          @click="go(p)"
        >
          {{ p }}
        </button>
      </template>

      <button
        class="inline-flex items-center gap-1 h-8 px-3 rounded-[var(--y-radius-sm)] border border-[var(--y-border)] text-[13px] text-[var(--y-text-secondary)] bg-[var(--y-surface)] hover:text-[var(--y-primary)] hover:border-[var(--y-primary)] transition-colors duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:text-[var(--y-text-secondary)] disabled:hover:border-[var(--y-border)]"
        :disabled="page >= pages"
        @click="go(page + 1)"
      >
        下一页
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
      </button>

      <span class="ml-2 text-xs text-[var(--y-text-muted)]">共 {{ total }} 篇</span>
    </template>

    <!-- 星空（后台/旧视图） -->
    <template v-else>
      <button
        class="chip !px-3 !py-1.5 disabled:opacity-40 disabled:cursor-not-allowed"
        :disabled="page <= 1"
        @click="go(page - 1)"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
        上一页
      </button>

      <template v-for="(p, i) in pageNumbers(page, pages)" :key="i">
        <span v-if="p === '...'" class="text-slate-500 px-1">…</span>
        <button
          v-else
          class="chip !px-3 !py-1.5 min-w-[36px] justify-center"
          :class="p === page ? '!bg-star-violet/25 !border-star-violet/60 !text-white' : ''"
          @click="go(p)"
        >
          {{ p }}
        </button>
      </template>

      <button
        class="chip !px-3 !py-1.5 disabled:opacity-40 disabled:cursor-not-allowed"
        :disabled="page >= pages"
        @click="go(page + 1)"
      >
        下一页
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
      </button>

      <span class="ml-2 text-xs text-star-dim font-mono">共 {{ total }} 篇</span>
    </template>
  </div>
</template>

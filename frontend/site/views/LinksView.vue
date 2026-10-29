<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { onMounted, ref } from 'vue'
import { linkApi } from '../../core/api/index.js'
import EmptyState from '../../shared/components/EmptyState.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'

const links = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const data = await linkApi.list()
    links.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="y-container py-12 page-enter">
    <div class="mb-10">
      <h1 class="text-2xl md:text-3xl font-bold text-[var(--y-text)]">友情链接</h1>
      <p class="text-sm text-[var(--y-text-muted)] mt-2">感谢这些站点的陪伴</p>
    </div>

    <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

    <div v-else-if="links.length" class="grid sm:grid-cols-2 gap-3">
      <a
        v-for="link in links"
        :key="link.id"
        :href="link.url"
        target="_blank"
        rel="noopener"
        class="y-card y-card-hover p-5 flex items-center gap-4 group"
      >
        <div class="w-11 h-11 rounded-[var(--y-radius-sm)] bg-[var(--y-primary-light)] text-[var(--y-primary-active)] flex items-center justify-center text-base font-semibold flex-shrink-0">
          {{ link.name.charAt(0) }}
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-sm font-medium text-[var(--y-text)] group-hover:text-[var(--y-primary)] transition-colors duration-200 truncate">{{ link.name }}</div>
          <div class="text-xs text-[var(--y-text-muted)] mt-0.5 truncate">{{ link.description || link.url }}</div>
        </div>
        <svg class="w-4 h-4 text-[var(--y-text-disabled)] group-hover:text-[var(--y-primary)] transition-colors duration-200 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3" /></svg>
      </a>
    </div>

    <div v-else class="y-card">
      <EmptyState variant="flat" icon="link" text="还没有添加友链" />
    </div>
  </div>
</template>

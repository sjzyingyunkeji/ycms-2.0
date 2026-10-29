<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { pageApi } from '../../core/api/index.js'
import MarkdownView from '../../shared/components/MarkdownView.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import EmptyState from '../../shared/components/EmptyState.vue'

const route = useRoute()
const page = ref(null)
const loading = ref(true)

async function loadPage() {
  loading.value = true
  page.value = null
  try {
    page.value = await pageApi.detail(route.params.slug)
  } catch {
    page.value = null
  } finally {
    loading.value = false
  }
}

onMounted(loadPage)

// 同一路由组件切换不同 slug（如「关于我」↔「留言板」）时重新加载
watch(() => route.params.slug, (slug) => {
  if (slug) loadPage()
})
</script>

<template>
  <div class="y-container-narrow py-12 page-enter">
    <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

    <template v-else-if="page">
      <header class="mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-[var(--y-text)]">{{ page.title }}</h1>
      </header>
      <article class="y-card p-6 md:p-9">
        <MarkdownView variant="flat" :content="page.content" />
      </article>
    </template>

    <div v-else class="y-card">
      <EmptyState variant="flat" icon="file" text="页面不存在或已被移除" />
    </div>
  </div>
</template>

<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { postApi } from '../../core/api/index.js'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import EmptyState from '../../shared/components/EmptyState.vue'

const router = useRouter()
const posts = ref([])
const loading = ref(true)

// 按年份分组
const grouped = computed(() => {
  const groups = []
  const map = new Map()
  for (const post of posts.value) {
    const dateStr = post.published_at || post.created_at || ''
    const year = dateStr.slice(0, 4) || '未知年份'
    if (!map.has(year)) {
      map.set(year, [])
      groups.push({ year, items: map.get(year) })
    }
    map.get(year).push(post)
  }
  return groups.sort((a, b) => b.year.localeCompare(a.year))
})

const totalPosts = computed(() => posts.value.length)

function formatDate(dateStr) {
  if (!dateStr) return ''
  return dateStr.slice(5, 10)
}

function goPost(post) {
  if (post.slug) {
    router.push({ name: 'post-detail-slug', params: { slug: post.slug } })
  } else {
    router.push({ name: 'post-detail', params: { id: post.id } })
  }
}

onMounted(async () => {
  try {
    const data = await postApi.list({ page: 1, size: 100 })
    posts.value = data.items || []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="y-container-narrow py-12 page-enter">
    <div class="mb-10">
      <h1 class="text-2xl md:text-3xl font-bold text-[var(--y-text)]">时间归档</h1>
      <p class="text-sm text-[var(--y-text-muted)] mt-2">共 {{ totalPosts }} 篇文章，按发布时间倒序</p>
    </div>

    <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

    <div v-else-if="grouped.length" class="relative">
      <!-- 时间轴主线 -->
      <div class="absolute left-[5px] top-2 bottom-2 w-px bg-[var(--y-border)]" />

      <div v-for="group in grouped" :key="group.year" class="relative pl-8 mb-10">
        <!-- 年份节点 -->
        <div class="absolute left-0 top-1.5 w-[11px] h-[11px] rounded-full bg-white border-2 border-[var(--y-primary)]" />
        <h2 class="text-lg font-bold text-[var(--y-text)] mb-4">{{ group.year }}</h2>

        <div class="space-y-0.5">
          <div
            v-for="post in group.items"
            :key="post.id"
            class="group flex items-baseline gap-4 py-2 px-3 -mx-3 rounded-[var(--y-radius-sm)] cursor-pointer hover:bg-[var(--y-surface-hover)] transition-colors duration-200"
            @click="goPost(post)"
          >
            <span class="text-xs text-[var(--y-text-muted)] w-12 flex-shrink-0 tabular-nums">{{ formatDate(post.published_at || post.created_at) }}</span>
            <span class="text-sm text-[var(--y-text-secondary)] group-hover:text-[var(--y-primary)] transition-colors duration-200 truncate">{{ post.title }}</span>
            <span v-if="post.category_name" class="ml-auto text-xs text-[var(--y-text-muted)] hidden sm:block flex-shrink-0">{{ post.category_name }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="y-card">
      <EmptyState variant="flat" icon="file" text="还没有文章" />
    </div>
  </div>
</template>

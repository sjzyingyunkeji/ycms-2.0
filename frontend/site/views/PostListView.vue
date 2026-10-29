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
import { useRoute, useRouter } from 'vue-router'
import { postApi, categoryApi } from '../../core/api/index.js'
import Pagination from '../../shared/components/Pagination.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'

const route = useRoute()
const router = useRouter()

const posts = ref([])
const categories = ref([])
const loading = ref(true)
const page = ref(1)
const pages = ref(1)
const total = ref(0)

// 排序方式：latest 按发布时间 / hot 按热度
const sort = ref('latest')
const sortOptions = [
  { value: 'latest', label: '最新发布' },
  { value: 'hot', label: '热门阅读' },
]

const isCategory = () => route.name === 'category-posts'
const isTag = () => route.name === 'tag-posts'

const title = ref('全部文章')

async function loadPosts(p = 1) {
  loading.value = true
  try {
    const params = { page: p, size: 10, sort: sort.value }
    if (isCategory()) {
      params.category_id = route.params.id
    } else if (isTag()) {
      params.tag_id = route.params.id
    }
    const data = await postApi.list(params)
    posts.value = data.items || []
    page.value = data.page || p
    pages.value = data.pages || 1
    total.value = data.total || 0

    if (isCategory()) {
      const cat = categories.value.find((c) => c.id === Number(route.params.id))
      title.value = cat ? `分类 · ${cat.name}` : '分类文章'
    } else if (isTag()) {
      title.value = '标签文章'
    } else {
      title.value = '全部文章'
    }
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return dateStr.slice(0, 10)
}

function onPageChange(p) {
  loadPosts(p)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// a-radio-group 的 v-model 先更新，change 拿到的是新值，此处直接重新加载
function changeSort() {
  loadPosts(1)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(async () => {
  try {
    const cats = await categoryApi.list()
    categories.value = Array.isArray(cats) ? cats : []
  } finally { /* ignore */ }
  await loadPosts(1)
})

watch(
  () => route.params.id,
  () => loadPosts(1)
)
</script>

<template>
  <div class="y-container py-12 page-enter">
    <!-- 标题区 -->
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-[var(--y-text)]">{{ title }}</h1>
        <p class="text-sm text-[var(--y-text-muted)] mt-2">共 {{ total }} 篇文章</p>
      </div>
      <!-- 排序切换 -->
      <a-radio-group v-model="sort" type="button" size="small" @change="changeSort">
        <a-radio v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </a-radio>
      </a-radio-group>
    </div>

    <!-- 分类筛选条（胶囊样式） -->
    <div class="flex flex-wrap gap-2 mb-8">
      <button
        class="h-8 px-4 rounded-[var(--y-radius-pill)] text-sm transition-all duration-200"
        :class="!isCategory() && !isTag()
          ? 'bg-[var(--y-primary)] text-white font-medium'
          : 'bg-[var(--y-surface)] border border-[var(--y-border)] text-[var(--y-text-secondary)] hover:text-[var(--y-primary)] hover:border-[var(--y-primary)]'"
        @click="router.push({ name: 'posts' })"
      >
        全部
      </button>
      <button
        v-for="cat in categories"
        :key="cat.id"
        class="h-8 px-4 rounded-[var(--y-radius-pill)] text-sm transition-all duration-200 inline-flex items-center gap-1.5"
        :class="isCategory() && route.params.id == cat.id
          ? 'bg-[var(--y-primary)] text-white font-medium'
          : 'bg-[var(--y-surface)] border border-[var(--y-border)] text-[var(--y-text-secondary)] hover:text-[var(--y-primary)] hover:border-[var(--y-primary)]'"
        @click="router.push({ name: 'category-posts', params: { id: cat.id } })"
      >
        {{ cat.name }}
        <span :class="isCategory() && route.params.id == cat.id ? 'text-white/70' : 'text-[var(--y-text-muted)]'" class="text-xs">{{ cat.post_count }}</span>
      </button>
    </div>

    <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

    <template v-else>
      <div v-if="posts.length" class="space-y-3">
        <router-link
          v-for="post in posts"
          :key="post.id"
          :to="post.slug ? { name: 'post-detail-slug', params: { slug: post.slug } } : { name: 'post-detail', params: { id: post.id } }"
          class="block group"
        >
          <div class="y-card y-card-hover p-5 md:p-6 flex gap-5">
            <!-- 封面 -->
            <div v-if="post.cover_image" class="hidden sm:block w-32 h-24 rounded-[var(--y-radius-sm)] overflow-hidden flex-shrink-0 border border-[var(--y-border)]">
              <img :src="post.cover_image" :alt="post.title" class="w-full h-full object-cover" loading="lazy" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2.5 text-xs mb-2">
                <span class="y-tag y-tag-primary">{{ post.category_name || '未分类' }}</span>
                <span v-if="post.is_top" class="y-tag y-tag-top">置顶</span>
                <span class="text-[var(--y-text-muted)]">{{ formatDate(post.published_at || post.created_at) }}</span>
              </div>
              <h2 class="text-base md:text-lg font-semibold text-[var(--y-text)] group-hover:text-[var(--y-primary)] transition-colors duration-200 mb-1.5 leading-snug">
                {{ post.title }}
              </h2>
              <p class="text-sm text-[var(--y-text-secondary)] leading-relaxed line-clamp-2">{{ post.summary || '暂无摘要。' }}</p>
              <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-[var(--y-text-muted)]">
                <span class="flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                  {{ post.views }}
                </span>
                <span class="flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" /></svg>
                  {{ post.comment_count }}
                </span>
                <span v-for="tag in post.tags" :key="tag.id" class="px-2 h-5 inline-flex items-center rounded bg-[var(--y-surface-hover)] text-[var(--y-text-secondary)]">
                  #{{ tag.name }}
                </span>
              </div>
            </div>
          </div>
        </router-link>
      </div>

      <div v-else class="y-card">
        <EmptyState variant="flat" icon="file" text="暂无文章" />
      </div>

      <div class="mt-10">
        <Pagination variant="flat" :page="page" :pages="pages" :total="total" @change="onPageChange" />
      </div>
    </template>
  </div>
</template>

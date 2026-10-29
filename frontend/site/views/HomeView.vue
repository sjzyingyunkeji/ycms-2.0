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
import { useSiteStore } from '../../core/stores/site.js'
import { postApi, categoryApi, pageApi, linkApi } from '../../core/api/index.js'
import MarkdownView from '../../shared/components/MarkdownView.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import EmptyState from '../../shared/components/EmptyState.vue'

const router = useRouter()
const site = useSiteStore()

const posts = ref([])
const categories = ref([])
const aboutPage = ref(null)
const links = ref([])
const loading = ref(true)

const latestPosts = computed(() => posts.value.slice(0, 6))

// 首页排序方式：后台「站点设置 → 首页文章排序」可切换 热门 / 最新
const homeSort = computed(() => (site.settings.home_post_sort === 'latest' ? 'latest' : 'hot'))
const homeSortLabel = computed(() => (homeSort.value === 'hot' ? '热门文章' : '最新文章'))

// 导航友站（后台「站点设置 → 导航友站」配置，与顶部导航「友站」下拉同源）
const navLinks = computed(() => (Array.isArray(site.settings.nav_links) ? site.settings.nav_links : []))

onMounted(async () => {
  try {
    const [postsData, catsData, linksData] = await Promise.all([
      postApi.list({ page: 1, size: 6, sort: homeSort.value }),
      categoryApi.list(),
      linkApi.list(),
    ])
    posts.value = postsData.items || []
    categories.value = Array.isArray(catsData) ? catsData : []
    links.value = Array.isArray(linksData) ? linksData : []

    // 尝试获取关于页面
    const pages = await pageApi.list()
    const list = Array.isArray(pages) ? pages : []
    aboutPage.value = list.find((p) => p.slug === 'about') || list[0] || null
  } finally {
    loading.value = false
  }
})

function formatDate(dateStr) {
  if (!dateStr) return ''
  return dateStr.slice(0, 10)
}

function goPost(post) {
  if (!post) return
  if (post.slug) {
    router.push({ name: 'post-detail-slug', params: { slug: post.slug } })
  } else {
    router.push({ name: 'post-detail', params: { id: post.id } })
  }
}
</script>

<template>
  <div class="page-enter">
    <!-- ============ Hero 首屏（扁平，浅色区块） ============ -->
    <section class="border-b border-[var(--y-border)] bg-[var(--y-surface)]">
      <div class="y-container py-16 md:py-24">
        <div class="max-w-3xl">
          <h1 class="text-3xl md:text-5xl font-bold leading-tight text-[var(--y-text)] mb-5">
            {{ site.settings.owner_name || '星尘' }}
            <span class="text-[var(--y-primary)]">{{ site.settings.hero_suffix || '的数字星空' }}</span>
          </h1>

          <p class="text-base md:text-lg text-[var(--y-text-secondary)] leading-relaxed mb-8 max-w-2xl">
            {{ site.settings.site_description || '在这里记录代码、生活与思考。' }}
          </p>

          <div class="flex flex-wrap items-center gap-3">
            <a-button type="primary" size="large" @click="router.push({ name: 'posts' })">
              <template #icon>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" /></svg>
              </template>
              开始阅读
            </a-button>
            <a-button size="large" @click="router.push({ name: 'archive' })">
              <template #icon>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M18.7 8l-5.1 5.2-2.8-2.7L7 14.3" /></svg>
              </template>
              时间归档
            </a-button>
          </div>
        </div>

        <!-- 统计条：扁平分栏 -->
        <div class="mt-12 grid grid-cols-3 max-w-lg rounded-[var(--y-radius-card)] border border-[var(--y-border)] overflow-hidden">
          <div class="py-4 px-3 text-center border-r border-[var(--y-border)]">
            <div class="text-2xl font-bold text-[var(--y-text)]">{{ posts.length }}<span class="text-[var(--y-primary)]">+</span></div>
            <div class="text-xs text-[var(--y-text-muted)] mt-1">篇文章</div>
          </div>
          <div class="py-4 px-3 text-center border-r border-[var(--y-border)]">
            <div class="text-2xl font-bold text-[var(--y-text)]">{{ categories.length }}</div>
            <div class="text-xs text-[var(--y-text-muted)] mt-1">个分类</div>
          </div>
          <div class="py-4 px-3 text-center">
            <div class="text-2xl font-bold text-[var(--y-text)]">{{ links.length }}</div>
            <div class="text-xs text-[var(--y-text-muted)] mt-1">位友邻</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ 关于 ============ -->
    <section v-if="aboutPage" class="y-container mt-14">
      <div class="y-card y-card-hover p-6 md:p-8">
        <div class="flex items-start gap-5">
          <div class="hidden sm:flex w-12 h-12 rounded-[var(--y-radius-sm)] bg-[var(--y-primary-light)] items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-[var(--y-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4" /><path d="M20 21a8 8 0 10-16 0" /></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="y-section-title !mb-4">关于我</div>
            <MarkdownView variant="flat" :content="aboutPage.content.slice(0, 400)" />
            <a-button type="text" class="!px-0 !text-[var(--y-primary)] mt-3" @click="router.push({ name: 'page', params: { slug: aboutPage.slug } })">
              了解更多
              <svg class="w-3.5 h-3.5 ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7" /></svg>
            </a-button>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ 热门 / 最新文章 ============ -->
    <section class="y-container mt-14">
      <div class="flex items-center justify-between mb-6">
        <div class="y-section-title !mb-0">{{ homeSortLabel }}</div>
        <button class="text-sm text-[var(--y-text-muted)] hover:text-[var(--y-primary)] transition-colors duration-200" @click="router.push({ name: 'posts' })">
          查看全部 →
        </button>
      </div>

      <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

      <div v-else-if="latestPosts.length" class="grid md:grid-cols-2 gap-4">
        <router-link
          v-for="(post, i) in latestPosts"
          :key="post.id"
          :to="post.slug ? { name: 'post-detail-slug', params: { slug: post.slug } } : { name: 'post-detail', params: { id: post.id } }"
          class="block group"
          :class="i === 0 ? 'md:col-span-2' : ''"
        >
          <div class="y-card y-card-hover h-full p-6" :class="i === 0 ? 'md:p-8' : ''">
            <div class="flex items-center gap-2.5 mb-3">
              <span class="y-tag y-tag-primary">{{ post.category_name || '未分类' }}</span>
              <span v-if="post.is_top" class="y-tag y-tag-top">置顶</span>
              <span class="text-xs text-[var(--y-text-muted)]">{{ formatDate(post.published_at || post.created_at) }}</span>
              <span class="text-xs text-[var(--y-text-muted)] flex items-center gap-1 ml-auto">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                {{ post.views }}
              </span>
            </div>
            <h3
              class="font-semibold text-[var(--y-text)] group-hover:text-[var(--y-primary)] transition-colors duration-200 mb-2 leading-snug"
              :class="i === 0 ? 'text-xl md:text-2xl' : 'text-base md:text-lg'"
            >
              {{ post.title }}
            </h3>
            <p class="text-sm text-[var(--y-text-secondary)] leading-relaxed" :class="i === 0 ? 'line-clamp-3' : 'line-clamp-2'">
              {{ post.summary || '暂无摘要。' }}
            </p>
          </div>
        </router-link>
      </div>

      <div v-else class="y-card">
        <EmptyState variant="flat" icon="file" text="还没有文章，去后台发布第一篇吧" />
      </div>
    </section>

    <!-- ============ 友链 ============ -->
    <section v-if="links.length" class="y-container mt-14">
      <div class="y-section-title">友情链接</div>
      <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-3">
        <a
          v-for="link in links"
          :key="link.id"
          :href="link.url"
          target="_blank"
          rel="noopener"
          class="y-card y-card-hover p-4 flex items-center gap-3 group"
        >
          <div class="w-9 h-9 rounded-[var(--y-radius-sm)] bg-[var(--y-surface-hover)] flex items-center justify-center text-[var(--y-primary)] font-semibold flex-shrink-0">
            {{ link.name.charAt(0) }}
          </div>
          <div class="min-w-0">
            <div class="text-sm font-medium text-[var(--y-text)] group-hover:text-[var(--y-primary)] transition-colors duration-200 truncate">{{ link.name }}</div>
            <div class="text-xs text-[var(--y-text-muted)] truncate">{{ link.description || link.url }}</div>
          </div>
        </a>
      </div>
    </section>

    <!-- ============ 友站（导航栏「友站」同源，后台可配置） ============ -->
    <section v-if="navLinks.length" class="y-container mt-14">
      <div class="y-section-title">其他站点</div>
      <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-3">
        <a
          v-for="navSite in navLinks"
          :key="navSite.url"
          :href="navSite.url"
          target="_blank"
          rel="noopener"
          class="y-card y-card-hover p-4 flex items-center gap-3 group"
        >
          <div class="w-9 h-9 rounded-[var(--y-radius-sm)] bg-[var(--y-surface-hover)] flex items-center justify-center text-[var(--y-primary)] font-semibold flex-shrink-0">
            {{ navSite.name.charAt(0) }}
          </div>
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-[var(--y-text)] group-hover:text-[var(--y-primary)] transition-colors duration-200 truncate">{{ navSite.name }}</div>
            <div class="text-xs text-[var(--y-text-muted)] truncate">{{ navSite.url }}</div>
          </div>
          <svg class="w-3.5 h-3.5 text-[var(--y-text-disabled)] group-hover:text-[var(--y-primary)] transition-colors duration-200 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3" /></svg>
        </a>
      </div>
    </section>
  </div>
</template>

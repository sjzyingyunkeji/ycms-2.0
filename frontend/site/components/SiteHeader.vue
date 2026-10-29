<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSiteStore } from '../../core/stores/site.js'
import { pageApi } from '../../core/api/index.js'
import BrandLogo from '../../shared/components/BrandLogo.vue'

const site = useSiteStore()
const route = useRoute()
const router = useRouter()

const pages = ref([])
const mobileOpen = ref(false)
const linksOpen = ref(false)
const scrolled = ref(false)

const navItems = [
  { name: 'home', label: '首页' },
  { name: 'posts', label: '文章' },
  { name: 'archive', label: '归档' },
  { name: 'links', label: '友链' },
]

// 导航栏友站链接（后台「站点设置」中配置，新标签页打开）
const navLinks = computed(() => (Array.isArray(site.settings.nav_links) ? site.settings.nav_links : []))

// 导航项是否高亮（含分类/标签/页面路由的归属判断）
function isActive(name) {
  if (name === 'posts') {
    return ['posts', 'post-detail', 'post-detail-slug', 'category-posts', 'tag-posts'].includes(route.name)
  }
  if (name === 'home') {
    return route.name === 'home'
  }
  return route.name === name
}

function isPageActive(slug) {
  return route.name === 'page' && route.params.slug === slug
}

onMounted(async () => {
  try {
    const data = await pageApi.list()
    pages.value = Array.isArray(data) ? data : []
  } catch { /* ignore */ }
  window.addEventListener('scroll', handleScroll, { passive: true })
})

function handleScroll() {
  scrolled.value = window.scrollY > 8
}

function go(name) {
  router.push({ name })
  mobileOpen.value = false
}
</script>

<template>
  <header
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
    :class="scrolled ? 'bg-white/90 backdrop-blur border-b border-[var(--y-border)]' : 'bg-white border-b border-transparent'"
  >
    <div class="y-container h-[72px] flex items-center justify-between gap-4">
      <!-- Logo（文字 / 图片，后台可切换） -->
      <router-link :to="{ name: 'home' }" class="flex items-center gap-2.5 flex-shrink-0">
        <BrandLogo size="md" />
      </router-link>

      <!-- 桌面导航：胶囊样式 -->
      <nav class="hidden md:flex items-center gap-1 p-1 rounded-[var(--y-radius-pill)] bg-[var(--y-surface-hover)]">
        <button
          v-for="item in navItems"
          :key="item.name"
          class="px-4 h-8 rounded-[var(--y-radius-pill)] text-sm transition-all duration-200"
          :class="isActive(item.name)
            ? 'bg-[var(--y-surface)] text-[var(--y-primary)] font-medium shadow-sm'
            : 'text-[var(--y-text-secondary)] hover:text-[var(--y-text)]'"
          @click="go(item.name)"
        >
          {{ item.label }}
        </button>
        <button
          v-for="p in pages"
          :key="p.id"
          class="px-4 h-8 rounded-[var(--y-radius-pill)] text-sm transition-all duration-200"
          :class="isPageActive(p.slug)
            ? 'bg-[var(--y-surface)] text-[var(--y-primary)] font-medium shadow-sm'
            : 'text-[var(--y-text-secondary)] hover:text-[var(--y-text)]'"
          @click="router.push({ name: 'page', params: { slug: p.slug } })"
        >
          {{ p.title }}
        </button>
      </nav>

      <div class="flex items-center gap-2">
        <!-- 友站（桌面端下拉，外部站点导航） -->
        <div v-if="navLinks.length" class="relative hidden md:block" @mouseenter="linksOpen = true" @mouseleave="linksOpen = false">
          <button class="inline-flex items-center gap-1 px-3 h-8 rounded-[var(--y-radius-pill)] text-sm text-[var(--y-text-secondary)] hover:text-[var(--y-text)] hover:bg-[var(--y-surface-hover)] transition-colors duration-200">
            友站
            <svg class="w-3 h-3 transition-transform duration-200" :class="linksOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          <transition name="page">
            <div v-if="linksOpen" class="absolute right-0 top-full pt-2 w-52 z-50">
              <div class="y-card shadow-lg p-1.5">
                <div class="px-2.5 py-1.5 text-[11px] tracking-wider text-[var(--y-text-muted)]">其他站点</div>
                <a
                  v-for="link in navLinks"
                  :key="link.url"
                  :href="link.url"
                  target="_blank"
                  rel="noopener"
                  class="flex items-center justify-between gap-2 px-2.5 py-2 text-sm text-[var(--y-text-secondary)] hover:text-[var(--y-primary)] hover:bg-[var(--y-primary-light)] rounded-[var(--y-radius-sm)] transition-colors duration-200"
                >
                  <span class="truncate">{{ link.name }}</span>
                  <svg class="w-3 h-3 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3" /></svg>
                </a>
              </div>
            </div>
          </transition>
        </div>

        <a href="/admin/" class="hidden sm:inline-flex items-center gap-1.5 px-3.5 h-8 rounded-[var(--y-radius-pill)] text-xs font-medium text-white bg-[var(--y-primary)] hover:bg-[var(--y-primary-hover)] transition-colors duration-200">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3" /></svg>
          后台
        </a>

        <!-- 移动端菜单按钮 -->
        <button class="md:hidden p-2 text-[var(--y-text-secondary)]" @click="mobileOpen = !mobileOpen">
          <svg v-if="!mobileOpen" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
          <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
        </button>
      </div>
    </div>

    <!-- 移动端导航 -->
    <Transition name="page">
      <nav v-if="mobileOpen" class="md:hidden border-t border-[var(--y-border)] bg-white px-5 py-3">
        <div class="flex flex-wrap gap-2">
          <button
            v-for="item in navItems"
            :key="item.name"
            class="px-4 h-8 rounded-[var(--y-radius-pill)] text-sm transition-colors duration-200"
            :class="isActive(item.name)
              ? 'bg-[var(--y-primary-light)] text-[var(--y-primary-active)] font-medium'
              : 'bg-[var(--y-surface-hover)] text-[var(--y-text-secondary)]'"
            @click="go(item.name)"
          >
            {{ item.label }}
          </button>
          <button
            v-for="p in pages"
            :key="p.id"
            class="px-4 h-8 rounded-[var(--y-radius-pill)] text-sm transition-colors duration-200"
            :class="isPageActive(p.slug)
              ? 'bg-[var(--y-primary-light)] text-[var(--y-primary-active)] font-medium'
              : 'bg-[var(--y-surface-hover)] text-[var(--y-text-secondary)]'"
            @click="router.push({ name: 'page', params: { slug: p.slug } })"
          >
            {{ p.title }}
          </button>
        </div>

        <div v-if="navLinks.length" class="mt-3 pt-3 border-t border-[var(--y-border)]">
          <div class="text-[11px] tracking-wider text-[var(--y-text-muted)] mb-2">其他站点</div>
          <div class="flex flex-wrap gap-2">
            <a
              v-for="link in navLinks"
              :key="link.url"
              :href="link.url"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center gap-1 px-3 h-8 rounded-[var(--y-radius-pill)] bg-[var(--y-surface-hover)] text-[var(--y-text-secondary)] text-sm"
              @click="mobileOpen = false"
            >
              {{ link.name }}
              <icon-launch :size="12" />
            </a>
          </div>
        </div>

        <a href="/admin/" class="mt-3 inline-flex items-center gap-1.5 px-3.5 h-8 rounded-[var(--y-radius-pill)] text-xs font-medium text-white bg-[var(--y-primary)]">
          <icon-user />
          进入后台
        </a>
      </nav>
    </Transition>
  </header>
</template>

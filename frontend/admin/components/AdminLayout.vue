<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../core/stores/auth.js'
import { useSiteStore } from '../../core/stores/site.js'
import BrandLogo from '../../shared/components/BrandLogo.vue'


const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const site = useSiteStore()

const sidebarOpen = ref(false)
const userMenuOpen = ref(false)

const menuGroups = [
  {
    label: '概览',
    items: [
      { name: 'dashboard', label: '仪表盘', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
      { name: 'admin-stats', label: '访问统计', icon: 'M3 3v18h18M7 15l4-5 3 3 5-7' },
    ],
  },
  {
    label: '内容',
    items: [
      { name: 'admin-posts', label: '文章管理', icon: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z M14 2v6h6 M16 13H8 M16 17H8 M10 9H8' },
      { name: 'admin-categories', label: '分类管理', icon: 'M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z' },
      { name: 'admin-tags', label: '标签管理', icon: 'M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z M7 7h.01' },
      { name: 'admin-pages', label: '页面管理', icon: 'M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2z M9 7h6M9 11h6M9 15h4' },
      { name: 'admin-comments', label: '评论管理', icon: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z' },
    ],
  },
  {
    label: '资源',
    items: [
      { name: 'admin-media', label: '媒体库', icon: 'M19 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2z M8.5 10a1.5 1.5 0 100-3 1.5 1.5 0 000 3z M21 15l-5-5L5 21' },
      { name: 'admin-links', label: '友链管理', icon: 'M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71 M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71' },
    ],
  },
  {
    label: '系统',
    items: [
      { name: 'admin-settings', label: '站点设置', icon: 'M12 15a3 3 0 100-6 3 3 0 000 6z M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z' },
      { name: 'admin-update', label: '系统更新', icon: 'M21 12a9 9 0 11-2.64-6.36 M21 3v6h-6 M12 7v5l3.5 2' },
      { name: 'admin-profile', label: '个人资料', icon: 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2 M12 3a4 4 0 100 8 4 4 0 000-8z' },
    ],
  },
]

const currentPageTitle = computed(() => {
  for (const group of menuGroups) {
    for (const item of group.items) {
      if (item.name === route.name) return item.label
    }
  }
  if (route.name === 'post-new' || route.name === 'post-edit') return '文章编辑'
  if (route.name === 'page-new' || route.name === 'page-edit') return '页面编辑'
  return '仪表盘'
})

function isActive(name) {
  return route.name === name
}

function navigate(name) {
  router.push({ name })
  sidebarOpen.value = false
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'admin-login' })
}

/** 返回前台（后台与前台是独立构建，跨应用需整页跳转） */
function goSite() {
  window.location.href = '/'
}

onMounted(() => {
  auth.checkSession()
})
</script>

<template>
  <div class="min-h-screen flex relative">

    <!-- ============ 侧边栏（全尺寸固定，页面滚动时不动） ============ -->
    <aside
      class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col transform transition-transform duration-300 border-r border-[var(--y-border)] bg-[var(--y-surface)]"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
      <!-- Logo（文字 / 图片，后台可切换） -->
      <div class="h-16 flex items-center gap-2.5 px-5 border-b border-[var(--y-border)]">
        <BrandLogo size="sm" />
        <span class="y-tag y-tag-plain !h-[18px] !text-[10px] hidden lg:inline-flex">管理后台</span>
      </div>

      <!-- 菜单 -->
      <nav class="flex-1 overflow-y-auto py-4 px-3">
        <div v-for="group in menuGroups" :key="group.label" class="mb-5">
          <div class="y-nav-group-label">{{ group.label }}</div>
          <button
            v-for="item in group.items"
            :key="item.name"
            class="y-nav-item"
            :class="{ 'is-active': isActive(item.name) }"
            @click="navigate(item.name)"
          >
            <svg class="w-[17px] h-[17px] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path :d="item.icon" />
            </svg>
            {{ item.label }}
          </button>
        </div>
      </nav>

      <!-- 侧栏底部 -->
      <div class="p-4 border-t border-[var(--y-border)]">
        <button class="w-full y-btn y-btn-secondary !py-2 !text-xs" @click="goSite">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z M9 22V12h6v10"/></svg>
          访问前台
        </button>
      </div>
    </aside>

    <!-- 遮罩 -->
    <div v-if="sidebarOpen" class="fixed inset-0 bg-black/40 z-30 lg:hidden" @click="sidebarOpen = false" />

    <!-- ============ 主区域（桌面端为固定侧边栏留出宽度） ============ -->
    <div class="relative z-10 flex-1 min-w-0 flex flex-col lg:ml-64">
      <!-- 高危重置文件警告（登录成功后检测） -->
      <div
        v-if="auth.resetFileExists"
        class="flex items-center gap-3 px-5 py-2.5 bg-[var(--y-danger-light)] border-b border-[#ffd2cc] text-xs text-[var(--y-danger)]"
      >
        <svg class="w-4 h-4 flex-shrink-0 text-[var(--y-danger)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
        <span>
          检测到高危重置密码文件 <code class="font-mono bg-[var(--y-surface-hover)] px-1.5 py-0.5 rounded">reset-password.php</code> 仍存在于运行目录，任何人访问它都能重置你的密码，
          <strong class="text-[var(--y-danger)]">请立即删除！</strong>
        </span>
        <span class="ml-auto hidden md:block text-[var(--y-danger)] opacity-70 flex-shrink-0">路径：backend/public/reset-password.php</span>
      </div>

      <!-- 顶栏 -->
      <header class="sticky top-0 z-20 h-16 flex items-center justify-between px-5 border-b border-[var(--y-border)] bg-[var(--y-surface)]">
        <div class="flex items-center gap-4">
          <button class="lg:hidden text-[var(--y-text-secondary)] hover:text-[var(--y-text)] p-2 -ml-2" @click="sidebarOpen = true">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <h2 class="text-base font-semibold text-[var(--y-text)]">{{ currentPageTitle }}</h2>
          <!-- 关站状态指示 -->
          <button
            v-if="String(site.settings.site_closed) === '1'"
            class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-medium border border-[#ffd2cc] text-[var(--y-danger)] bg-[var(--y-danger-light)] hover:bg-[var(--y-danger-light)] transition-colors duration-300"
            title="站点已关闭，点击前往设置"
            @click="navigate('admin-settings')"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-[var(--y-danger)]" />
            站点已关闭
          </button>
        </div>

        <div class="relative">
          <button class="flex items-center gap-3 pl-3 pr-2 py-1.5 rounded-xl hover:bg-[var(--y-surface-hover)] transition-colors" @click="userMenuOpen = !userMenuOpen">
            <div class="text-right hidden sm:block">
              <div class="text-sm text-[var(--y-text)] leading-tight">{{ auth.user?.nickname || auth.user?.username || '管理员' }}</div>
              <div class="text-[10px] text-[var(--y-text-muted)]">管理员</div>
            </div>
            <!-- 头像：个人资料保存后 store 同步更新，头像立即刷新 -->
            <div class="w-9 h-9 rounded-full overflow-hidden bg-[var(--y-primary)] border border-[var(--y-border)] flex items-center justify-center text-white font-bold text-sm">
              <img
                v-if="auth.user?.avatar"
                :src="auth.user.avatar"
                :alt="auth.user?.nickname || '头像'"
                class="w-full h-full object-cover"
              />
              <span v-else>{{ (auth.user?.nickname || auth.user?.username || 'A').charAt(0) }}</span>
            </div>
          </button>

          <Transition name="page">
            <div v-if="userMenuOpen" class="absolute right-0 top-12 w-44 y-card shadow-sm p-2 z-30">
              <button class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-[var(--y-text-secondary)] hover:bg-[var(--y-surface-hover)] hover:text-[var(--y-text)] transition-colors" @click="navigate('admin-profile'); userMenuOpen = false">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2 M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg>
                个人资料
              </button>
              <button class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-[var(--y-danger)] hover:bg-[var(--y-danger-light)] transition-colors" @click="handleLogout">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4 M16 17l5-5-5-5 M21 12H9"/></svg>
                退出登录
              </button>
            </div>
          </Transition>
        </div>
      </header>

      <!-- 内容 -->
      <main class="flex-1 p-5 lg:p-8">
        <router-view v-slot="{ Component }">
          <transition name="page" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

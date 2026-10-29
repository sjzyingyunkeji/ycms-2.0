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
import { dashboardApi } from '../../core/api/index.js'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'

const router = useRouter()
const stats = ref(null)
const loading = ref(true)

const iconPaths = {
  'file-text': 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z M14 2v6h6 M16 13H8 M16 17H8 M10 9H8',
  eye: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z M12 15a3 3 0 100-6 3 3 0 000 6z',
  'message-square': 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z',
  image: 'M19 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2z M8.5 10a1.5 1.5 0 100-3 1.5 1.5 0 000 3z M21 15l-5-5L5 21',
}

const statusLabels = {
  published: ['已发布', 'border-[#aff0b5] text-[var(--y-success)] bg-[var(--y-success-light)]'],
  draft: ['草稿', 'border-[var(--y-border)] text-[var(--y-text-secondary)] bg-[var(--y-surface-hover)]'],
  pending: ['待审核', 'border-[#ffdfb8] text-[var(--y-warning)] bg-[var(--y-warning-light)]'],
  approved: ['已通过', 'border-[#aff0b5] text-[var(--y-success)] bg-[var(--y-success-light)]'],
  spam: ['垃圾', 'border-[#ffd2cc] text-[var(--y-danger)] bg-[var(--y-danger-light)]'],
}

// 趋势图（SVG 折线）
const trendPoints = computed(() => {
  const trend = stats.value?.publish_trend || []
  if (!trend.length) return ''
  const max = Math.max(...trend.map((t) => t.count), 1)
  const w = 100
  const h = 28
  return trend
    .map((t, i) => {
      const x = (i / Math.max(trend.length - 1, 1)) * w
      const y = h - (t.count / max) * (h - 4) - 2
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
})

onMounted(async () => {
  try {
    stats.value = await dashboardApi.stats()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-enter">
    <LoadingSpinner variant="flat" v-if="loading" text="正在加载数据…" />

    <template v-else-if="stats">
      <!-- 欢迎语 -->
      <div class="y-card shadow-sm p-6 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h3 class="text-xl text-[var(--y-text)] mb-1">欢迎回来</h3>
          <p class="text-sm text-[var(--y-text-muted)]">以下是站点最近的数据概览。</p>
        </div>
        <button class="y-btn y-btn-primary" @click="router.push({ name: 'post-new' })">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          写新文章
        </button>
      </div>

      <!-- 统计卡片（极简：hover 才浮现微光） -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div v-for="card in stats.cards" :key="card.label" class="y-stat p-5 group">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-[var(--y-primary-light)] border border-[var(--y-border)] flex items-center justify-center">
              <svg class="w-5 h-5 text-[var(--y-primary)]/85" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path :d="iconPaths[card.icon] || iconPaths['file-text']" />
              </svg>
            </div>
            <span class="text-[10px] text-[var(--y-text-muted)] group-hover:text-[var(--y-primary)]/70 transition-colors duration-300 tracking-widest">{{ card.label }}</span>
          </div>
          <div class="text-3xl font-bold text-[var(--y-text)] mb-1">{{ card.value }}</div>
          <div class="text-xs text-[var(--y-text-muted)]">{{ card.hint }}</div>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-4 mb-6">
        <!-- 发布趋势 -->
        <div class="y-card p-6 lg:col-span-2">
          <div class="flex items-center justify-between mb-5">
            <h4 class="text-base text-[var(--y-text)]">近 30 天发布趋势</h4>
            <span class="text-xs text-[var(--y-text-muted)]">共 {{ stats.publish_trend?.length || 0 }} 天有更新</span>
          </div>
          <div v-if="stats.publish_trend?.length" class="relative h-36">
            <svg viewBox="0 0 100 28" preserveAspectRatio="none" class="w-full h-full">
              <defs>
                <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="rgba(78, 110, 242, 0.22)" />
                  <stop offset="100%" stop-color="rgba(78, 110, 242, 0)" />
                </linearGradient>
              </defs>
              <polygon :points="`0,28 ${trendPoints} 100,28`" fill="url(#trendFill)" />
              <polyline :points="trendPoints" fill="none" stroke="#4e6ef2" stroke-width="0.6" stroke-linejoin="round" />
            </svg>
            <div class="absolute bottom-0 left-0 right-0 flex justify-between text-[10px] text-[var(--y-text-muted)]">
              <span>{{ stats.publish_trend[0]?.date }}</span>
              <span>{{ stats.publish_trend[stats.publish_trend.length - 1]?.date }}</span>
            </div>
          </div>
          <div v-else class="h-36 flex items-center justify-center text-sm text-[var(--y-text-muted)]">近 30 天暂无发布记录</div>
        </div>

        <!-- 内容构成 -->
        <div class="y-card p-6">
          <h4 class="text-base text-[var(--y-text)] mb-5">内容构成</h4>
          <div class="space-y-4">
            <div v-for="item in stats.extras" :key="item.label" class="flex items-center justify-between">
              <span class="text-sm text-[var(--y-text-secondary)]">{{ item.label }}</span>
              <span class="text-xl font-bold text-[var(--y-text)]">{{ item.value }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 最近动态 -->
      <div class="grid lg:grid-cols-2 gap-4">
        <div class="y-card p-6">
          <div class="flex items-center justify-between mb-5">
            <h4 class="text-base text-[var(--y-text)]">最近文章</h4>
            <button class="text-xs text-[var(--y-primary)] hover:text-[var(--y-text)] transition-colors" @click="router.push({ name: 'admin-posts' })">查看全部 →</button>
          </div>
          <div class="space-y-3">
            <div v-for="post in stats.recent_posts" :key="post.id" class="flex items-center justify-between gap-3 py-2 border-b border-[var(--y-border)] last:border-0">
              <span class="text-sm text-[var(--y-text-secondary)] truncate flex-1">{{ post.title }}</span>
              <span class="text-xs px-2 py-0.5 rounded-full border" :class="(statusLabels[post.status] || statusLabels.draft)[1]">
                {{ (statusLabels[post.status] || statusLabels.draft)[0] }}
              </span>
            </div>
          </div>
        </div>

        <div class="y-card p-6">
          <div class="flex items-center justify-between mb-5">
            <h4 class="text-base text-[var(--y-text)]">最新评论</h4>
            <button class="text-xs text-[var(--y-primary)] hover:text-[var(--y-text)] transition-colors" @click="router.push({ name: 'admin-comments' })">去审核 →</button>
          </div>
          <div v-if="stats.recent_comments?.length" class="space-y-3">
            <div v-for="c in stats.recent_comments" :key="c.id" class="py-2 border-b border-[var(--y-border)] last:border-0">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-[var(--y-text-secondary)]">{{ c.nickname || '匿名访客' }}</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full border" :class="(statusLabels[c.status] || statusLabels.pending)[1]">
                  {{ (statusLabels[c.status] || statusLabels.pending)[0] }}
                </span>
              </div>
              <p class="text-xs text-[var(--y-text-muted)] truncate">{{ c.content }}</p>
            </div>
          </div>
          <div v-else class="py-8 text-center text-sm text-[var(--y-text-muted)]">暂无评论</div>
        </div>
      </div>
    </template>
  </div>
</template>

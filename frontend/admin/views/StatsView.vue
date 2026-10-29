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
import { statsApi } from '../../core/api/index.js'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import EmptyState from '../../shared/components/EmptyState.vue'

const router = useRouter()

const data = ref(null)
const loading = ref(true)
const days = ref(30)
const dayOptions = [
  { value: 7, label: '近 7 天' },
  { value: 30, label: '近 30 天' },
  { value: 90, label: '近 90 天' },
]

const deviceLabels = {
  desktop: '桌面端',
  mobile: '移动端',
  tablet: '平板',
  bot: '爬虫/蜘蛛',
  unknown: '未知',
}

async function load() {
  loading.value = true
  try {
    data.value = await statsApi.overview({ days: days.value })
  } finally {
    loading.value = false
  }
}

function changeDays(v) {
  if (days.value === v) return
  days.value = v
  load()
}

/** 同比昨日（今日 vs 昨日） */
function delta(today, yesterday) {
  if (!yesterday) return today > 0 ? 100 : 0
  return Math.round(((today - yesterday) / yesterday) * 100)
}

const cards = computed(() => {
  if (!data.value) return []
  const d = data.value
  return [
    {
      label: '总访问量',
      value: d.total_pv,
      icon: 'eye',
      hint: `PV · 独立访客 ${d.total_uv}`,
    },
    {
      label: '今日访问量',
      value: d.today_pv,
      icon: 'activity',
      hint: `UV ${d.today_uv} · 较昨日 ${delta(d.today_pv, d.yesterday_pv) >= 0 ? '+' : ''}${delta(d.today_pv, d.yesterday_pv)}%`,
      up: delta(d.today_pv, d.yesterday_pv) >= 0,
    },
    {
      label: '今日访客',
      value: d.today_uv,
      icon: 'users',
      hint: `昨日 ${d.yesterday_uv} 人`,
    },
    {
      label: '蜘蛛抓取',
      value: d.bot_pv,
      icon: 'search',
      hint: '搜索引擎爬虫访问次数',
    },
  ]
})

const trendMax = computed(() => {
  const trend = data.value?.trend || []
  return Math.max(1, ...trend.map((t) => Math.max(t.pv, t.uv)))
})

/** 生成折线坐标 */
function linePoints(key) {
  const trend = data.value?.trend || []
  if (!trend.length) return ''
  return trend
    .map((t, i) => {
      const x = (i / Math.max(trend.length - 1, 1)) * 100
      const y = 100 - (t[key] / trendMax.value) * 92 - 4
      return `${x.toFixed(2)},${y.toFixed(2)}`
    })
    .join(' ')
}

const pvPoints = computed(() => linePoints('pv'))
const uvPoints = computed(() => linePoints('uv'))

const maxHotPv = computed(() => Math.max(1, ...(data.value?.hot_posts || []).map((p) => p.visits)))
const maxHotPagePv = computed(() => Math.max(1, ...(data.value?.hot_pages || []).map((p) => p.pv)))
const maxRefPv = computed(() => Math.max(1, ...(data.value?.referers || []).map((r) => r.pv)))
const maxDevicePv = computed(() => Math.max(1, ...(data.value?.devices || []).map((d) => d.pv)))

onMounted(load)
</script>

<template>
  <div class="page-enter">
    <!-- 标题栏 -->
    <div class="y-card p-6 mb-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h3 class="text-xl text-[var(--y-text)] mb-1">访问统计</h3>
        <p class="text-sm text-[var(--y-text-muted)]">全站访问数据（含首页）· 每 30 分钟同访客同页面只计一次</p>
      </div>
      <div class="flex gap-2">
        <button
          v-for="opt in dayOptions"
          :key="opt.value"
          class="y-tag y-tag-plain"
          :class="days === opt.value ? '!bg-[var(--y-primary-light)] !border-[var(--y-primary)] !text-[var(--y-text)]' : ''"
          @click="changeDays(opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>

    <LoadingSpinner variant="flat" v-if="loading" text="正在加载数据…" />

    <template v-else-if="data">
      <!-- 概览卡片 -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div v-for="card in cards" :key="card.label" class="y-stat p-5">
          <div class="text-[10px] text-[var(--y-text-muted)] tracking-widest mb-2">{{ card.label }}</div>
          <div class="text-3xl font-bold text-[var(--y-text)] mb-1">{{ card.value }}</div>
          <div class="text-xs text-[var(--y-text-muted)]">{{ card.hint }}</div>
        </div>
      </div>

      <!-- 访问趋势 -->
      <div class="y-card p-6 mb-5">
        <div class="flex items-center justify-between mb-5">
          <h4 class="text-base text-[var(--y-text)]">访问趋势</h4>
          <div class="flex items-center gap-4 text-[11px] text-[var(--y-text-muted)]">
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-[var(--y-primary)]" /> 浏览量 PV
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-[var(--y-warning)]" /> 访客 UV
            </span>
          </div>
        </div>

        <div v-if="data.trend?.length" class="relative h-48">
          <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="w-full h-full">
            <defs>
              <linearGradient id="pvFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="rgba(139, 92, 246, 0.35)" />
                <stop offset="100%" stop-color="rgba(139, 92, 246, 0)" />
              </linearGradient>
            </defs>
            <polygon :points="`0,100 ${pvPoints} 100,100`" fill="url(#pvFill)" />
            <polyline :points="pvPoints" fill="none" stroke="#a78bfa" stroke-width="0.8" stroke-linejoin="round" stroke-linecap="round" />
            <polyline :points="uvPoints" fill="none" stroke="#67e8f9" stroke-width="0.8" stroke-linejoin="round" stroke-linecap="round" />
          </svg>
          <div class="absolute bottom-0 left-0 right-0 flex justify-between text-[10px] text-[var(--y-text-muted)]">
            <span>{{ data.trend[0]?.date }}</span>
            <span>{{ data.trend[data.trend.length - 1]?.date }}</span>
          </div>
        </div>
        <div v-else class="h-48 flex items-center justify-center text-sm text-[var(--y-text-muted)]">暂无访问数据</div>
      </div>

      <div class="grid lg:grid-cols-2 gap-5 mb-5">
        <!-- 热门文章 -->
        <div class="y-card p-6">
          <div class="flex items-center justify-between mb-5">
            <h4 class="text-base text-[var(--y-text)]">热门文章</h4>
            <button class="text-xs text-[var(--y-primary)] hover:text-[var(--y-text)] transition-colors" @click="router.push({ name: 'admin-posts' })">管理文章 →</button>
          </div>

          <div v-if="data.hot_posts?.length" class="space-y-3">
            <div
              v-for="(post, i) in data.hot_posts"
              :key="post.id"
              class="p-3 rounded-xl border border-[var(--y-border)] hover:border-[var(--y-border)] transition-colors cursor-pointer"
              @click="router.push({ name: 'post-edit', params: { id: post.id } })"
            >
              <div class="flex items-center gap-3">
                <span
                  class="w-6 h-6 rounded-lg flex items-center justify-center text-[11px] font-bold flex-shrink-0"
                  :class="i < 3 ? 'bg-[var(--y-primary-light)] text-[var(--y-primary)]' : 'bg-[var(--y-surface-hover)] text-[var(--y-text-muted)]'"
                >
                  {{ i + 1 }}
                </span>
                <div class="flex-1 min-w-0">
                  <div class="text-sm text-[var(--y-text)] truncate">{{ post.title }}</div>
                  <div class="text-[11px] text-[var(--y-text-muted)] mt-0.5">
                    {{ post.category_name || '未分类' }} · 阅读 {{ post.views }} · 访客 {{ post.unique_visitors }}
                  </div>
                </div>
                <div class="text-right flex-shrink-0">
                  <div class="text-lg font-bold text-[var(--y-text)]">{{ post.visits }}</div>
                  <div class="text-[10px] text-[var(--y-text-muted)]">访问</div>
                </div>
              </div>
              <div class="mt-2 h-1 rounded-full bg-[var(--y-surface-hover)] overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{ width: (post.visits / maxHotPv) * 100 + '%' }"
                />
              </div>
            </div>
          </div>
          <EmptyState variant="flat" v-else icon="file" text="还没有访问数据" />
        </div>

        <!-- 热门页面 -->
        <div class="y-card p-6">
          <h4 class="text-base text-[var(--y-text)] mb-5">热门页面</h4>
          <div v-if="data.hot_pages?.length" class="space-y-3">
            <div v-for="p in data.hot_pages" :key="p.path" class="flex items-center gap-3">
              <span class="text-xs text-[var(--y-text-secondary)] font-mono truncate flex-1" :title="p.path">{{ p.path }}</span>
              <span class="text-[10px] text-[var(--y-text-muted)] px-1.5 py-0.5 rounded border border-[var(--y-border)]">{{ p.page_type }}</span>
              <div class="w-24 h-1.5 rounded-full bg-[var(--y-surface-hover)] overflow-hidden flex-shrink-0">
                <div class="h-full rounded-full bg-[var(--y-primary)]" :style="{ width: (p.pv / maxHotPagePv) * 100 + '%' }" />
              </div>
              <span class="text-xs text-[var(--y-text-secondary)] w-12 text-right">{{ p.pv }}</span>
            </div>
          </div>
          <EmptyState variant="flat" v-else icon="file" text="还没有访问数据" />
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-5">
        <!-- 来源站点 -->
        <div class="y-card p-6">
          <h4 class="text-base text-[var(--y-text)] mb-5">来源站点</h4>
          <div v-if="data.referers?.length" class="space-y-3">
            <div v-for="r in data.referers" :key="r.host" class="flex items-center gap-3">
              <span class="text-xs text-[var(--y-text-secondary)] truncate flex-1">{{ r.host }}</span>
              <div class="w-28 h-1.5 rounded-full bg-[var(--y-surface-hover)] overflow-hidden flex-shrink-0">
                <div class="h-full rounded-full bg-[var(--y-warning)]" :style="{ width: (r.pv / maxRefPv) * 100 + '%' }" />
              </div>
              <span class="text-xs text-[var(--y-text-secondary)] w-12 text-right">{{ r.pv }}</span>
            </div>
          </div>
          <EmptyState variant="flat" v-else icon="link" text="暂无外部来源记录" />
        </div>

        <!-- 设备分布 -->
        <div class="y-card p-6">
          <h4 class="text-base text-[var(--y-text)] mb-5">设备分布</h4>
          <div v-if="data.devices?.length" class="space-y-3">
            <div v-for="d in data.devices" :key="d.device" class="flex items-center gap-3">
              <span class="text-xs text-[var(--y-text-secondary)] w-20 flex-shrink-0">{{ deviceLabels[d.device] || d.device }}</span>
              <div class="flex-1 h-1.5 rounded-full bg-[var(--y-surface-hover)] overflow-hidden">
                <div class="h-full rounded-full bg-[var(--y-primary)]" :style="{ width: (d.pv / maxDevicePv) * 100 + '%' }" />
              </div>
              <span class="text-xs text-[var(--y-text-secondary)] w-12 text-right">{{ d.pv }}</span>
            </div>
          </div>
          <EmptyState variant="flat" v-else icon="users" text="暂无设备数据" />
        </div>
      </div>
    </template>
  </div>
</template>

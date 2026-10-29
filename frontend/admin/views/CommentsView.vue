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
import { commentApi } from '../../core/api/index.js'
import BaseModal from '../../shared/components/BaseModal.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const comments = ref([])
const loading = ref(true)
const filter = ref('')
const page = ref(1)
const pages = ref(1)
const total = ref(0)
const deleteTarget = ref(null)

const statusLabels = {
  pending: ['待审核', 'border-[#ffdfb8] text-[var(--y-warning)] bg-[var(--y-warning-light)]'],
  approved: ['已通过', 'border-[#aff0b5] text-[var(--y-success)] bg-[var(--y-success-light)]'],
  spam: ['垃圾', 'border-[#ffd2cc] text-[var(--y-danger)] bg-[var(--y-danger-light)]'],
}

async function load(p = 1) {
  loading.value = true
  try {
    const params = { page: p, size: 15 }
    if (filter.value) params.status = filter.value
    const data = await commentApi.list(params)
    comments.value = data.items || []
    page.value = data.page || p
    pages.value = data.pages || 1
    total.value = data.total || 0
  } finally {
    loading.value = false
  }
}

async function setStatus(comment, status) {
  try {
    await commentApi.update(comment.id, { status })
    showToast(status === 'approved' ? '评论已通过' : '状态已更新', 'success')
    await load(page.value)
  } catch { /* ignore */ }
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  try {
    await commentApi.remove(deleteTarget.value.id)
    showToast('评论已删除', 'success')
    deleteTarget.value = null
    await load(page.value)
  } catch { /* ignore */ }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return dateStr.slice(0, 16)
}

onMounted(() => load(1))
</script>

<template>
  <div class="page-enter">
    <div class="y-card p-5 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="text-sm text-[var(--y-text-secondary)]">共 {{ total }} 条评论</div>
      <div class="flex gap-2">
        <button
          v-for="s in [{ v: '', l: '全部' }, { v: 'pending', l: '待审核' }, { v: 'approved', l: '已通过' }, { v: 'spam', l: '垃圾' }]"
          :key="s.v"
          class="y-tag y-tag-plain"
          :class="filter === s.v ? '!bg-[var(--y-primary)] !border-[var(--y-primary)] !text-white' : ''"
          @click="filter = s.v; load(1)"
        >
          {{ s.l }}
        </button>
      </div>
    </div>

    <div class="y-card overflow-hidden">
      <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

      <template v-else>
        <div v-if="comments.length" class="divide-y divide-[var(--y-border)]">
          <div v-for="c in comments" :key="c.id" class="p-5 hover:bg-[var(--y-surface-hover)] transition-colors">
            <div class="flex items-start justify-between gap-4 flex-wrap">
              <div class="flex items-center gap-3 mb-2">
                <div class="w-9 h-9 rounded-full bg-[var(--y-primary)] border border-[var(--y-primary)] flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                  {{ (c.nickname || '匿').charAt(0) }}
                </div>
                <div>
                  <div class="text-sm text-[var(--y-text)]">{{ c.nickname || '匿名访客' }}</div>
                  <div class="text-[10px] text-[var(--y-text-muted)]">{{ formatDate(c.created_at) }} · IP {{ c.ip || '未知' }}</div>
                </div>
              </div>
              <span class="text-xs px-2.5 py-1 rounded-full border" :class="(statusLabels[c.status] || statusLabels.pending)[1]">
                {{ (statusLabels[c.status] || statusLabels.pending)[0] }}
              </span>
            </div>

            <p class="text-sm text-[var(--y-text-secondary)] leading-relaxed whitespace-pre-wrap mb-3">{{ c.content }}</p>

            <div class="flex items-center justify-between flex-wrap gap-2">
              <span class="text-xs text-[var(--y-text-muted)]">评论于文章 #{{ c.post_id }}《{{ c.post_title || '未知文章' }}》</span>
              <div class="flex items-center gap-2">
                <button v-if="c.status !== 'approved'" class="y-btn-text" @click="setStatus(c, 'approved')">通过</button>
                <button v-if="c.status !== 'spam'" class="y-btn-text" @click="setStatus(c, 'spam')">标为垃圾</button>
                <button class="y-btn-text y-btn-text-danger" @click="deleteTarget = c">删除</button>
              </div>
            </div>
          </div>
        </div>

        <div v-else>
          <EmptyState variant="flat" icon="comment" text="暂无评论" />
        </div>

        <div v-if="pages > 1" class="flex justify-center gap-2 p-4 border-t border-[var(--y-border)]">
          <button class="y-tag y-tag-plain !px-3 !py-1.5" :disabled="page <= 1" @click="load(page - 1)">上一页</button>
          <span class="y-tag y-tag-plain !px-3 !py-1.5">{{ page }} / {{ pages }}</span>
          <button class="y-tag y-tag-plain !px-3 !py-1.5" :disabled="page >= pages" @click="load(page + 1)">下一页</button>
        </div>
      </template>
    </div>

    <BaseModal variant="flat" v-model="deleteTarget" title="删除评论" width="max-w-md">
      <template v-if="deleteTarget">
        <p class="text-sm text-[var(--y-text-secondary)] mb-6">确定要删除这条评论吗？</p>
        <div class="flex justify-end gap-3">
          <button class="y-btn y-btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="y-btn y-btn-danger" @click="confirmDelete">确认删除</button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

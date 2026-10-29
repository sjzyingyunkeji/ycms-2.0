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
import { useRouter } from 'vue-router'
import { pageApi } from '../../core/api/index.js'
import BaseModal from '../../shared/components/BaseModal.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const router = useRouter()
const pages = ref([])
const loading = ref(true)
const deleteTarget = ref(null)

async function load() {
  loading.value = true
  try {
    const data = await pageApi.list()
    pages.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  try {
    await pageApi.remove(deleteTarget.value.id)
    showToast('页面已删除', 'success')
    deleteTarget.value = null
    await load()
  } catch { /* ignore */ }
}

function preview(page) {
  const url = router.resolve({ name: 'page', params: { slug: page.slug } }).href
  window.open(url, '_blank')
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return dateStr.slice(0, 16)
}

onMounted(load)
</script>

<template>
  <div class="page-enter">
    <div class="y-card p-5 mb-5 flex items-center justify-between">
      <div class="text-sm text-[var(--y-text-secondary)]">共 {{ pages.length }} 个页面</div>
      <button class="y-btn y-btn-primary !py-2.5" @click="router.push({ name: 'page-new' })">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        新建页面
      </button>
    </div>

    <div class="y-card overflow-hidden">
      <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

      <template v-else>
        <div v-if="pages.length" class="overflow-x-auto">
          <table class="y-table">
            <thead>
              <tr class="border-b border-[var(--y-border)]">
                <th class="text-left">标题</th>
                <th class="text-left hidden md:table-cell">别名</th>
                <th class="text-center">状态</th>
                <th class="text-left hidden md:table-cell">更新时间</th>
                <th class="text-right">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in pages" :key="p.id" class="border-b border-[var(--y-border)] hover:bg-[var(--y-surface-hover)] transition-colors">
                <td class="text-sm text-[var(--y-text)]">{{ p.title }}</td>
                <td class="hidden md:table-cell text-xs text-[var(--y-text-muted)] font-mono">/page/{{ p.slug }}</td>
                <td class="text-center">
                  <span class="text-xs px-2.5 py-1 rounded-full border" :class="p.status === 'published' ? 'border-[#aff0b5] text-[var(--y-success)] bg-[var(--y-success-light)]' : 'border-[var(--y-border)] text-[var(--y-text-secondary)] bg-[var(--y-surface-hover)]'">
                    {{ p.status === 'published' ? '已发布' : '草稿' }}
                  </span>
                </td>
                <td class="hidden md:table-cell text-xs text-[var(--y-text-muted)]">{{ formatDate(p.updated_at || p.created_at) }}</td>
                <td class="text-right whitespace-nowrap">
                  <button class="y-btn-text" @click="router.push({ name: 'page-edit', params: { id: p.id } })">编辑</button>
                  <button class="y-btn-text" @click="preview(p)">查看</button>
                  <button class="y-btn-text y-btn-text-danger" @click="deleteTarget = p">删除</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else>
          <EmptyState variant="flat" text="还没有页面" />
        </div>
      </template>
    </div>

    <!-- 删除确认 -->
    <BaseModal variant="flat" v-model="deleteTarget" title="删除页面" width="max-w-md">
      <template v-if="deleteTarget">
        <p class="text-sm text-[var(--y-text-secondary)] mb-6">
          确定要删除页面「<span class="text-[var(--y-text)]">{{ deleteTarget.title }}</span>」吗？删除后无法恢复。
        </p>
        <div class="flex justify-end gap-3">
          <button class="y-btn y-btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="y-btn y-btn-danger" @click="confirmDelete">确认删除</button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

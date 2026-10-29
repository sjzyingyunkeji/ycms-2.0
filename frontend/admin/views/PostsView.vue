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
import { postApi, categoryApi, tagApi } from '../../core/api/index.js'
import BaseModal from '../../shared/components/BaseModal.vue'
import Pagination from '../../shared/components/Pagination.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const router = useRouter()

const posts = ref([])
const categories = ref([])
const tags = ref([])
const loading = ref(true)

const filters = ref({ status: '', category_id: '', keyword: '' })
const page = ref(1)
const pages = ref(1)
const total = ref(0)

const deleteTarget = ref(null)
const deleting = ref(false)

const statusLabels = {
  published: ['已发布', 'border-[#aff0b5] text-[var(--y-success)] bg-[var(--y-success-light)]'],
  draft: ['草稿', 'border-[var(--y-border)] text-[var(--y-text-secondary)] bg-[var(--y-surface-hover)]'],
}

async function loadPosts(p = 1) {
  loading.value = true
  try {
    const params = { page: p, size: 10 }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.category_id) params.category_id = filters.value.category_id
    if (filters.value.keyword.trim()) params.keyword = filters.value.keyword.trim()
    const data = await postApi.list(params)
    posts.value = data.items || []
    page.value = data.page || p
    pages.value = data.pages || 1
    total.value = data.total || 0
  } finally {
    loading.value = false
  }
}

function search() {
  loadPosts(1)
}

function resetFilters() {
  filters.value = { status: '', category_id: '', keyword: '' }
  loadPosts(1)
}

function onPageChange(p) {
  loadPosts(p)
}

function editPost(post) {
  router.push({ name: 'post-edit', params: { id: post.id } })
}

function askDelete(post) {
  deleteTarget.value = post
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await postApi.remove(deleteTarget.value.id)
    showToast('文章已删除', 'success')
    deleteTarget.value = null
    loadPosts(page.value)
  } finally {
    deleting.value = false
  }
}

async function toggleStatus(post) {
  const newStatus = post.status === 'published' ? 'draft' : 'published'
  try {
    await postApi.update(post.id, { status: newStatus })
    showToast(newStatus === 'published' ? '文章已发布' : '文章已转为草稿', 'success')
    loadPosts(page.value)
  } catch { /* ignore */ }
}

/** 置顶 / 取消置顶（列表内快捷操作） */
async function toggleTop(post) {
  const next = post.is_top ? 0 : 1
  try {
    const data = await postApi.toggleTop(post.id, next)
    post.is_top = data?.is_top ?? next
    showToast(post.is_top ? '已置顶，前台将优先展示' : '已取消置顶', 'success')
  } catch { /* ignore */ }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return dateStr.slice(0, 16)
}

onMounted(async () => {
  try {
    const [cats, tagsData] = await Promise.all([categoryApi.list(), tagApi.list()])
    categories.value = Array.isArray(cats) ? cats : []
    tags.value = Array.isArray(tagsData) ? tagsData : []
  } finally { /* ignore */ }
  await loadPosts(1)
})
</script>

<template>
  <div class="page-enter">
    <!-- 工具栏 -->
    <div class="y-card p-5 mb-5">
      <div class="flex flex-col lg:flex-row lg:items-center gap-4">
        <div class="flex-1">
          <input
            v-model="filters.keyword"
            class="y-input"
            placeholder="搜索文章标题、摘要或内容…"
            @keyup.enter="search"
          />
        </div>
        <select v-model="filters.status" class="y-input lg:w-36" @change="search">
          <option value="">全部状态</option>
          <option value="published">已发布</option>
          <option value="draft">草稿</option>
        </select>
        <select v-model="filters.category_id" class="y-input lg:w-40" @change="search">
          <option value="">全部分类</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <div class="flex gap-2">
          <button class="y-btn y-btn-primary !py-2.5" @click="search">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            搜索
          </button>
          <button class="y-btn y-btn-secondary !py-2.5" @click="resetFilters">重置</button>
          <button class="y-btn y-btn-primary !py-2.5" @click="router.push({ name: 'post-new' })">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            新建
          </button>
        </div>
      </div>
    </div>

    <!-- 表格 -->
    <div class="y-card overflow-hidden">
      <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

      <template v-else>
        <div v-if="posts.length" class="overflow-x-auto">
          <table class="y-table">
            <thead>
              <tr class="border-b border-[var(--y-border)]">
                <th class="text-left">文章</th>
                <th class="text-left hidden md:table-cell">分类</th>
                <th class="text-left hidden lg:table-cell">标签</th>
                <th class="text-center">状态</th>
                <th class="text-center hidden sm:table-cell">阅读</th>
                <th class="text-left hidden md:table-cell">更新时间</th>
                <th class="text-right">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="post in posts" :key="post.id" class="border-b border-[var(--y-border)] hover:bg-[var(--y-surface-hover)] transition-colors">
                <td>
                  <div class="flex items-center gap-2">
                    <span v-if="post.is_top" class="text-[var(--y-warning)]" title="置顶">
                      <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6 4.5 2.3 7.1-6.3-4.6L5.7 21l2.3-7.1-6-4.5h7.6z"/></svg>
                    </span>
                    <span class="text-sm text-[var(--y-text)] truncate max-w-[220px]" :title="post.title">{{ post.title }}</span>
                  </div>
                </td>
                <td class="hidden md:table-cell">
                  <span class="text-xs text-[var(--y-text-secondary)]">{{ post.category_name || '未分类' }}</span>
                </td>
                <td class="hidden lg:table-cell">
                  <span v-for="tag in post.tags" :key="tag.id" class="y-tag y-tag-plain !text-[10px] !py-0.5 mr-1">{{ tag.name }}</span>
                  <span v-if="!post.tags?.length" class="text-xs text-[var(--y-text-muted)]">-</span>
                </td>
                <td class="text-center">
                  <button
                    class="text-xs px-2.5 py-1 rounded-full border transition-all"
                    :class="(statusLabels[post.status] || statusLabels.draft)[1]"
                    @click="toggleStatus(post)"
                  >
                    {{ (statusLabels[post.status] || statusLabels.draft)[0] }}
                  </button>
                </td>
                <td class="text-center hidden sm:table-cell text-sm text-[var(--y-text-secondary)]">{{ post.views }}</td>
                <td class="hidden md:table-cell text-xs text-[var(--y-text-muted)]">{{ formatDate(post.updated_at || post.created_at) }}</td>
                <td class="text-right whitespace-nowrap">
                  <button
                    class="y-btn-text"
                    :class="post.is_top ? '!text-[var(--y-warning)]' : ''"
                    :title="post.is_top ? '取消置顶' : '置顶到首页/列表顶部'"
                    @click="toggleTop(post)"
                  >
                    {{ post.is_top ? '取消置顶' : '置顶' }}
                  </button>
                  <button class="y-btn-text" @click="editPost(post)">编辑</button>
                  <button class="y-btn-text y-btn-text-danger" @click="askDelete(post)">删除</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else>
          <EmptyState variant="flat" icon="file" text="还没有文章，点击右上角「新建」开始创作吧" />
        </div>

        <div v-if="total > 0" class="p-4 border-t border-[var(--y-border)]">
          <Pagination variant="flat" :page="page" :pages="pages" :total="total" @change="onPageChange" />
        </div>
      </template>
    </div>

    <!-- 删除确认 -->
    <BaseModal variant="flat" v-model="deleteTarget" title="删除文章" width="max-w-md">
      <template v-if="deleteTarget">
        <p class="text-sm text-[var(--y-text-secondary)] mb-6">
          确定要删除文章「<span class="text-[var(--y-text)]">{{ deleteTarget.title }}</span>」吗？删除后无法恢复，相关评论也会一并删除。
        </p>
        <div class="flex justify-end gap-3">
          <button class="y-btn y-btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="y-btn y-btn-danger" :disabled="deleting" @click="confirmDelete">
            <span v-if="deleting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            确认删除
          </button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

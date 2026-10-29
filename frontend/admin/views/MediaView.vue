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
import { mediaApi } from '../../core/api/index.js'
import BaseModal from '../../shared/components/BaseModal.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import HelpTip from '../../shared/components/HelpTip.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'
import { compressImages, formatFileSize, COMPRESS_LIMIT } from '../../core/utils/imageCompress.js'

const items = ref([])
const loading = ref(true)
const page = ref(1)
const pages = ref(1)
const total = ref(0)

const uploading = ref(false)
const uploadProgress = ref(0)
const deleteTarget = ref(null)

// 图片压缩开关（默认开启）：上传时压缩至 800KB 以内（固定，不可调节）
const compressEnabled = ref(true)

function formatSize(bytes) {
  return formatFileSize(bytes)
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return String(dateStr).slice(0, 16)
}

async function load(p = 1) {
  loading.value = true
  try {
    const data = await mediaApi.list({ page: p, size: 24 })
    items.value = data.items || []
    page.value = data.page || p
    pages.value = data.pages || 1
    total.value = data.total || 0
  } finally {
    loading.value = false
  }
}

async function handleUpload(e) {
  const files = Array.from(e.target.files || [])
  if (!files.length) return
  uploading.value = true
  try {
    // 1. 图片压缩（≤800KB）
    const results = await compressImages(files, compressEnabled.value)
    const savedBytes = results.reduce((sum, r) => sum + (r.originalSize - r.compressedSize), 0)
    const compressedCount = results.filter((r) => r.compressed).length

    // 2. 逐个上传
    for (const result of results) {
      const formData = new FormData()
      formData.append('file', result.file)
      uploadProgress.value = 0
      await mediaApi.upload(formData, (progressEvent) => {
        uploadProgress.value = Math.round((progressEvent.loaded * 100) / (progressEvent.total || 1))
      })
    }

    if (compressedCount > 0) {
      showToast(`上传成功，${compressedCount} 张图片已压缩至 ${formatFileSize(COMPRESS_LIMIT)} 以内，共节省 ${formatFileSize(savedBytes)}`, 'success', 5000)
    } else {
      showToast(`成功上传 ${files.length} 个文件`, 'success')
    }
    await load(page.value)
  } catch { /* ignore */ } finally {
    uploading.value = false
    uploadProgress.value = 0
    e.target.value = ''
  }
}

function copyUrl(item) {
  navigator.clipboard.writeText(location.origin + item.path).then(() => {
    showToast('链接已复制到剪贴板', 'success')
  })
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  try {
    await mediaApi.remove(deleteTarget.value.id)
    showToast('文件已删除', 'success')
    deleteTarget.value = null
    await load(page.value)
  } catch { /* ignore */ }
}

onMounted(() => load(1))
</script>

<template>
  <div class="page-enter">
    <!-- 上传区 -->
    <div class="y-card p-6 mb-5">
      <!-- 压缩开关 -->
      <div class="flex items-center justify-between mb-4 pb-4 border-b border-[var(--y-border)]">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-[var(--y-primary-light)] border border-[var(--y-border)] flex items-center justify-center">
            <svg class="w-4 h-4 text-[var(--y-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v6h6M20 10V4h-6M14 10l6-6M10 14l-6 6"/></svg>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="text-sm text-[var(--y-text)]">图片压缩</span>
            <HelpTip text="上传时自动将图片压缩至 800KB 以内" />
          </div>
        </div>
        <button
          type="button"
          class="relative w-11 h-6 rounded-full transition-colors duration-300 flex-shrink-0"
          :class="compressEnabled ? 'bg-[var(--y-primary)]' : 'bg-[var(--y-surface-hover)]'"
          :disabled="uploading"
          @click="compressEnabled = !compressEnabled"
        >
          <span
            class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white transition-transform duration-300"
            :class="compressEnabled ? 'translate-x-5' : 'translate-x-0'"
          />
        </button>
      </div>

      <label class="block border-2 border-dashed border-[var(--y-border)] rounded-2xl p-10 text-center cursor-pointer hover:border-[var(--y-primary)] hover:bg-[var(--y-surface-hover)] transition-all">
        <input type="file" class="hidden" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" @change="handleUpload" :disabled="uploading" />
        <div v-if="uploading">
          <LoadingSpinner variant="flat" text="上传中…" />
          <div class="w-48 mx-auto h-1.5 rounded-full bg-[var(--y-surface-hover)] overflow-hidden">
            <div class="h-full bg-[var(--y-primary)] transition-all" :style="{ width: uploadProgress + '%' }" />
          </div>
        </div>
        <div v-else>
          <div class="w-12 h-12 mx-auto rounded-xl bg-[var(--y-primary-light)] border border-[var(--y-border)] flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-[var(--y-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4 M17 8l-5-5-5 5 M12 3v12"/></svg>
          </div>
          <p class="text-sm text-[var(--y-text-secondary)] mb-1">点击或拖拽上传图片</p>
          <p class="text-xs text-[var(--y-text-muted)]">
            支持 JPG / PNG / GIF / WebP / PDF，单个最大 10MB
            <span v-if="compressEnabled" class="text-[var(--y-primary)]/80">· 压缩已开启（≤800KB）</span>
          </p>
        </div>
      </label>
    </div>

    <!-- 文件列表 -->
    <div class="y-card p-5">
      <div class="flex items-center justify-between mb-5">
        <span class="text-sm text-[var(--y-text-secondary)]">共 {{ total }} 个文件</span>
      </div>

      <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

      <div v-else-if="items.length" class="overflow-x-auto">
        <table class="y-table">
          <thead>
            <tr class="border-b border-[var(--y-border)]">
              <th class="text-left w-px">预览</th>
              <th class="text-left">文件名</th>
              <th class="text-left hidden md:table-cell">类型</th>
              <th class="text-left hidden sm:table-cell">大小</th>
              <th class="text-left hidden lg:table-cell">上传时间</th>
              <th class="text-right">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in items"
              :key="item.id"
              class="border-b border-[var(--y-border)] hover:bg-[var(--y-surface-hover)] transition-colors"
            >
              <td class="w-px">
                <div class="w-10 h-10 rounded-lg overflow-hidden border border-[var(--y-border)] bg-[var(--y-surface)]/60 flex items-center justify-center flex-shrink-0">
                  <img v-if="item.mime.startsWith('image')" :src="item.path" :alt="item.original_name" class="w-full h-full object-cover" loading="lazy" />
                  <svg v-else class="w-5 h-5 text-[var(--y-text-muted)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z M14 2v6h6"/></svg>
                </div>
              </td>
              <td>
                <div class="text-sm text-[var(--y-text)] truncate max-w-[280px]" :title="item.original_name">{{ item.original_name }}</div>
                <div class="text-[11px] text-[var(--y-text-muted)] font-mono truncate max-w-[280px]" :title="item.path">{{ item.path }}</div>
              </td>
              <td class="hidden md:table-cell">
                <span class="y-tag y-tag-plain !text-[10px] !py-0.5">{{ item.mime }}</span>
              </td>
              <td class="hidden sm:table-cell text-xs text-[var(--y-text-secondary)]">{{ formatSize(item.size) }}</td>
              <td class="hidden lg:table-cell text-xs text-[var(--y-text-muted)]">{{ formatDate(item.created_at) }}</td>
              <td class="text-right whitespace-nowrap">
                <button class="y-btn-text" @click="copyUrl(item)">复制链接</button>
                <a :href="item.path" target="_blank" rel="noopener" class="y-btn-text">查看</a>
                <button class="y-btn-text y-btn-text-danger" @click="deleteTarget = item">删除</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else>
        <EmptyState variant="flat" icon="image" text="媒体库还是空的，上传第一张图片吧" />
      </div>

      <div v-if="pages > 1" class="flex justify-center gap-2 mt-6">
        <button class="y-tag y-tag-plain !px-3 !py-1.5" :disabled="page <= 1" @click="load(page - 1)">上一页</button>
        <span class="y-tag y-tag-plain !px-3 !py-1.5">{{ page }} / {{ pages }}</span>
        <button class="y-tag y-tag-plain !px-3 !py-1.5" :disabled="page >= pages" @click="load(page + 1)">下一页</button>
      </div>
    </div>

    <BaseModal variant="flat" v-model="deleteTarget" title="删除文件" width="max-w-md">
      <template v-if="deleteTarget">
        <p class="text-sm text-[var(--y-text-secondary)] mb-6">
          确定要删除文件「<span class="text-[var(--y-text)]">{{ deleteTarget.original_name }}</span>」吗？
        </p>
        <div class="flex justify-end gap-3">
          <button class="y-btn y-btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="y-btn y-btn-danger" @click="confirmDelete">确认删除</button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

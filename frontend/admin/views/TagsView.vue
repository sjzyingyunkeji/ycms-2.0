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
import { tagApi } from '../../core/api/index.js'
import BaseModal from '../../shared/components/BaseModal.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const tags = ref([])
const loading = ref(true)

const modalOpen = ref(false)
const saving = ref(false)
const editing = ref(null)
const deleteTarget = ref(null)

const form = ref({ name: '', slug: '' })

function openCreate() {
  editing.value = null
  form.value = { name: '', slug: '' }
  modalOpen.value = true
}

function openEdit(tag) {
  editing.value = tag
  form.value = { name: tag.name, slug: tag.slug || '' }
  modalOpen.value = true
}

async function save() {
  if (!form.value.name.trim()) {
    showToast('请输入标签名称', 'warning')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await tagApi.update(editing.value.id, form.value)
      showToast('标签已更新', 'success')
    } else {
      await tagApi.create(form.value)
      showToast('标签已创建', 'success')
    }
    modalOpen.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  try {
    await tagApi.remove(deleteTarget.value.id)
    showToast('标签已删除', 'success')
    deleteTarget.value = null
    await load()
  } catch { /* ignore */ }
}

async function load() {
  loading.value = true
  try {
    const data = await tagApi.list()
    tags.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-enter">
    <div class="y-card p-5 mb-5 flex items-center justify-between">
      <div class="text-sm text-[var(--y-text-secondary)]">共 {{ tags.length }} 个标签</div>
      <button class="y-btn y-btn-primary !py-2.5" @click="openCreate">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        新建标签
      </button>
    </div>

    <div class="y-card p-6">
      <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

      <div v-else-if="tags.length" class="flex flex-wrap gap-3">
        <div
          v-for="tag in tags"
          :key="tag.id"
          class="group y-card !rounded-xl px-4 py-2.5 flex items-center gap-3 hover:border-[var(--y-primary)] transition-all"
        >
          <svg class="w-3.5 h-3.5 text-[var(--y-primary)]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6 4.5 2.3 7.1-6.3-4.6L5.7 21l2.3-7.1-6-4.5h7.6z"/></svg>
          <span class="text-sm text-[var(--y-text)]">{{ tag.name }}</span>
          <span class="text-xs text-[var(--y-text-muted)]">{{ tag.post_count }} 篇</span>
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button class="text-[var(--y-primary)] hover:text-[var(--y-text)] p-0.5" title="编辑" @click="openEdit(tag)">
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7 M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="text-[var(--y-danger)] hover:text-[var(--y-danger)] p-0.5" title="删除" @click="deleteTarget = tag">
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div v-else>
        <EmptyState variant="flat" text="还没有标签" />
      </div>
    </div>

    <!-- 新建/编辑弹窗 -->
    <BaseModal variant="flat" v-model="modalOpen" :title="editing ? '编辑标签' : '新建标签'" width="max-w-md">
      <div class="space-y-4">
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">名称 <span class="text-[var(--y-danger)]">*</span></label>
          <input v-model="form.name" class="y-input" placeholder="标签名称" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">别名（选填）</label>
          <input v-model="form.slug" class="y-input" placeholder="留空将自动生成" />
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button class="y-btn y-btn-secondary" @click="modalOpen = false">取消</button>
          <button class="y-btn y-btn-primary" :disabled="saving" @click="save">
            <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            保存
          </button>
        </div>
      </div>
    </BaseModal>

    <!-- 删除确认 -->
    <BaseModal variant="flat" v-model="deleteTarget" title="删除标签" width="max-w-md">
      <template v-if="deleteTarget">
        <p class="text-sm text-[var(--y-text-secondary)] mb-6">
          确定要删除标签「<span class="text-[var(--y-text)]">{{ deleteTarget.name }}</span>」吗？
        </p>
        <div class="flex justify-end gap-3">
          <button class="y-btn y-btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="y-btn y-btn-danger" @click="confirmDelete">确认删除</button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

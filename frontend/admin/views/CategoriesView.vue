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
import { categoryApi } from '../../core/api/index.js'
import BaseModal from '../../shared/components/BaseModal.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const categories = ref([])
const loading = ref(true)

const modalOpen = ref(false)
const saving = ref(false)
const editing = ref(null)
const deleteTarget = ref(null)

const form = ref({ name: '', slug: '', description: '', sort_order: 0 })

function openCreate() {
  editing.value = null
  form.value = { name: '', slug: '', description: '', sort_order: 0 }
  modalOpen.value = true
}

function openEdit(cat) {
  editing.value = cat
  form.value = {
    name: cat.name,
    slug: cat.slug || '',
    description: cat.description || '',
    sort_order: cat.sort_order || 0,
  }
  modalOpen.value = true
}

async function save() {
  if (!form.value.name.trim()) {
    showToast('请输入分类名称', 'warning')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await categoryApi.update(editing.value.id, form.value)
      showToast('分类已更新', 'success')
    } else {
      await categoryApi.create(form.value)
      showToast('分类已创建', 'success')
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
    await categoryApi.remove(deleteTarget.value.id)
    showToast('分类已删除', 'success')
    deleteTarget.value = null
    await load()
  } catch { /* ignore */ }
}

async function load() {
  loading.value = true
  try {
    const data = await categoryApi.list()
    categories.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-enter">
    <div class="y-card p-5 mb-5 flex items-center justify-between">
      <div class="text-sm text-[var(--y-text-secondary)]">共 {{ categories.length }} 个分类</div>
      <button class="y-btn y-btn-primary !py-2.5" @click="openCreate">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        新建分类
      </button>
    </div>

    <div class="y-card overflow-hidden">
      <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

      <template v-else>
        <div v-if="categories.length" class="overflow-x-auto">
          <table class="y-table">
            <thead>
              <tr class="border-b border-[var(--y-border)]">
                <th class="text-left">名称</th>
                <th class="text-left hidden md:table-cell">别名</th>
                <th class="text-left hidden lg:table-cell">描述</th>
                <th class="text-center">排序</th>
                <th class="text-center">文章数</th>
                <th class="text-right">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in categories" :key="cat.id" class="border-b border-[var(--y-border)] hover:bg-[var(--y-surface-hover)] transition-colors">
                <td class="text-sm text-[var(--y-text)]">{{ cat.name }}</td>
                <td class="hidden md:table-cell text-xs text-[var(--y-text-muted)] font-mono">{{ cat.slug }}</td>
                <td class="hidden lg:table-cell text-xs text-[var(--y-text-muted)] max-w-[200px] truncate">{{ cat.description || '-' }}</td>
                <td class="text-center text-sm text-[var(--y-text-secondary)]">{{ cat.sort_order }}</td>
                <td class="text-center">
                  <span class="y-tag y-tag-plain !text-xs">{{ cat.post_count }}</span>
                  </td>
                <td class="text-right whitespace-nowrap">
                  <button class="y-btn-text" @click="openEdit(cat)">编辑</button>
                  <button class="y-btn-text y-btn-text-danger" @click="deleteTarget = cat">删除</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else>
          <EmptyState variant="flat" text="还没有分类" />
        </div>
      </template>
    </div>

    <!-- 新建/编辑弹窗 -->
    <BaseModal variant="flat" v-model="modalOpen" :title="editing ? '编辑分类' : '新建分类'">
      <div class="space-y-4">
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">名称 <span class="text-[var(--y-danger)]">*</span></label>
          <input v-model="form.name" class="y-input" placeholder="分类名称" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">别名（选填）</label>
          <input v-model="form.slug" class="y-input" placeholder="留空将自动生成" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">描述（选填）</label>
          <input v-model="form.description" class="y-input" placeholder="分类描述" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">排序</label>
          <input v-model.number="form.sort_order" type="number" class="y-input" placeholder="数字越小越靠前" />
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
    <BaseModal variant="flat" v-model="deleteTarget" title="删除分类" width="max-w-md">
      <template v-if="deleteTarget">
        <p class="text-sm text-[var(--y-text-secondary)] mb-6">
          确定要删除分类「<span class="text-[var(--y-text)]">{{ deleteTarget.name }}</span>」吗？该分类下的文章将变为未分类。
        </p>
        <div class="flex justify-end gap-3">
          <button class="y-btn y-btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="y-btn y-btn-danger" @click="confirmDelete">确认删除</button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

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
import { linkApi } from '../../core/api/index.js'
import BaseModal from '../../shared/components/BaseModal.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const links = ref([])
const loading = ref(true)

const modalOpen = ref(false)
const saving = ref(false)
const editing = ref(null)
const deleteTarget = ref(null)

const form = ref({ name: '', url: '', description: '', avatar: '', sort_order: 0, status: 'active' })

function openCreate() {
  editing.value = null
  form.value = { name: '', url: '', description: '', avatar: '', sort_order: 0, status: 'active' }
  modalOpen.value = true
}

function openEdit(link) {
  editing.value = link
  form.value = {
    name: link.name,
    url: link.url,
    description: link.description || '',
    avatar: link.avatar || '',
    sort_order: link.sort_order || 0,
    status: link.status || 'active',
  }
  modalOpen.value = true
}

async function save() {
  if (!form.value.name.trim()) {
    showToast('请输入友链名称', 'warning')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await linkApi.update(editing.value.id, form.value)
      showToast('友链已更新', 'success')
    } else {
      await linkApi.create(form.value)
      showToast('友链已创建', 'success')
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
    await linkApi.remove(deleteTarget.value.id)
    showToast('友链已删除', 'success')
    deleteTarget.value = null
    await load()
  } catch { /* ignore */ }
}

async function load() {
  loading.value = true
  try {
    const data = await linkApi.list()
    links.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-enter">
    <div class="y-card p-5 mb-5 flex items-center justify-between">
      <div class="text-sm text-[var(--y-text-secondary)]">共 {{ links.length }} 个友链</div>
      <button class="y-btn y-btn-primary !py-2.5" @click="openCreate">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        新建友链
      </button>
    </div>

    <div class="y-card overflow-hidden">
      <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

      <template v-else>
        <div v-if="links.length" class="overflow-x-auto">
          <table class="y-table">
            <thead>
              <tr class="border-b border-[var(--y-border)]">
                <th class="text-left">名称</th>
                <th class="text-left hidden md:table-cell">地址</th>
                <th class="text-left hidden lg:table-cell">描述</th>
                <th class="text-center">排序</th>
                <th class="text-center">状态</th>
                <th class="text-right">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="link in links" :key="link.id" class="border-b border-[var(--y-border)] hover:bg-[var(--y-surface-hover)] transition-colors">
                <td class="text-sm text-[var(--y-text)]">{{ link.name }}</td>
                <td class="hidden md:table-cell text-xs text-[var(--y-text-muted)] max-w-[200px] truncate">
                  <a :href="link.url" target="_blank" rel="noopener" class="hover:text-[var(--y-primary)] transition-colors">{{ link.url }}</a>
                </td>
                <td class="hidden lg:table-cell text-xs text-[var(--y-text-muted)] max-w-[180px] truncate">{{ link.description || '-' }}</td>
                <td class="text-center text-sm text-[var(--y-text-secondary)]">{{ link.sort_order }}</td>
                <td class="text-center">
                  <span class="text-xs px-2.5 py-1 rounded-full border" :class="link.status === 'active' ? 'border-[#aff0b5] text-[var(--y-success)] bg-[var(--y-success-light)]' : 'border-[var(--y-border)] text-[var(--y-text-secondary)] bg-[var(--y-surface-hover)]'">
                    {{ link.status === 'active' ? '启用' : '停用' }}
                  </span>
                </td>
                <td class="text-right whitespace-nowrap">
                  <button class="y-btn-text" @click="openEdit(link)">编辑</button>
                  <button class="y-btn-text y-btn-text-danger" @click="deleteTarget = link">删除</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else>
          <EmptyState variant="flat" text="还没有友链" />
        </div>
      </template>
    </div>

    <BaseModal variant="flat" v-model="modalOpen" :title="editing ? '编辑友链' : '新建友链'">
      <div class="space-y-4">
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">名称 <span class="text-[var(--y-danger)]">*</span></label>
          <input v-model="form.name" class="y-input" placeholder="友链名称" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">地址 <span class="text-[var(--y-danger)]">*</span></label>
          <input v-model="form.url" class="y-input" placeholder="https://example.com" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">描述（选填）</label>
          <input v-model="form.description" class="y-input" placeholder="一句话描述" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">头像 URL（选填）</label>
          <input v-model="form.avatar" class="y-input" placeholder="头像图片地址" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">排序</label>
            <input v-model.number="form.sort_order" type="number" class="y-input" />
          </div>
          <div>
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">状态</label>
            <select v-model="form.status" class="y-input">
              <option value="active">启用</option>
              <option value="inactive">停用</option>
            </select>
          </div>
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

    <BaseModal variant="flat" v-model="deleteTarget" title="删除友链" width="max-w-md">
      <template v-if="deleteTarget">
        <p class="text-sm text-[var(--y-text-secondary)] mb-6">
          确定要删除友链「<span class="text-[var(--y-text)]">{{ deleteTarget.name }}</span>」吗？
        </p>
        <div class="flex justify-end gap-3">
          <button class="y-btn y-btn-secondary" @click="deleteTarget = null">取消</button>
          <button class="y-btn y-btn-danger" @click="confirmDelete">确认删除</button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

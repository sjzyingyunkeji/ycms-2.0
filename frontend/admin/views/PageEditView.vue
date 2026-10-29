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
import { useRoute, useRouter } from 'vue-router'
import { pageApi } from '../../core/api/index.js'
import MarkdownView from '../../shared/components/MarkdownView.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const route = useRoute()
const router = useRouter()

const isEdit = computed(() => route.name === 'page-edit')
const loading = ref(true)
const saving = ref(false)
const activeTab = ref('write')

const form = ref({
  title: '',
  slug: '',
  content: '',
  status: 'published',
  sort_order: 0,
})

async function load() {
  loading.value = true
  try {
    const data = await pageApi.detail(route.params.id)
    form.value = {
      title: data.title || '',
      slug: data.slug || '',
      content: data.content || '',
      status: data.status || 'published',
      sort_order: data.sort_order || 0,
    }
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!form.value.title.trim()) {
    showToast('请填写页面标题', 'warning')
    return
  }
  saving.value = true
  try {
    if (isEdit.value) {
      await pageApi.update(route.params.id, form.value)
      showToast('页面已保存', 'success')
    } else {
      await pageApi.create(form.value)
      showToast('页面已创建', 'success')
    }
    router.push({ name: 'admin-pages' })
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (isEdit.value) {
    await load()
  } else {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-enter">
    <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

    <template v-else>
      <div class="y-card p-4 mb-5 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
          <button class="y-btn y-btn-secondary !px-3 !py-2" @click="router.push({ name: 'admin-pages' })">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            返回
          </button>
          <span class="text-sm text-[var(--y-text-secondary)]">{{ isEdit ? '编辑页面' : '新建页面' }}</span>
        </div>
        <div class="flex items-center gap-3">
          <select v-model="form.status" class="y-input !w-28 !py-2">
            <option value="published">发布</option>
            <option value="draft">草稿</option>
          </select>
          <button class="y-btn y-btn-primary" :disabled="saving" @click="save">
            <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            <span v-else>保存</span>
          </button>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
          <div class="y-card p-6">
            <input
              v-model="form.title"
              class="w-full bg-transparent border-0 outline-none text-2xl font-bold text-[var(--y-text)] placeholder:text-[var(--y-text-muted)] mb-4"
              placeholder="在这里输入页面标题…"
            />
            <div class="flex items-center gap-2 text-sm">
              <span class="text-[var(--y-text-muted)]">访问地址：</span>
              <span class="font-mono text-[var(--y-primary)]/80">/page/</span>
              <input
                v-model="form.slug"
                class="bg-transparent border-0 outline-none font-mono text-[var(--y-primary)]/80 w-40 placeholder:text-[var(--y-text-muted)]"
                placeholder="page-slug"
              />
            </div>
          </div>

          <div class="y-card overflow-hidden">
            <div class="flex items-center border-b border-[var(--y-border)] px-4 py-2 gap-2">
              <button
                class="px-3 py-1.5 text-xs rounded-lg transition-colors"
                :class="activeTab === 'write' ? 'bg-[var(--y-primary)] text-white' : 'text-[var(--y-text-secondary)] hover:text-[var(--y-text)]'"
                @click="activeTab = 'write'"
              >
                编辑
              </button>
              <button
                class="px-3 py-1.5 text-xs rounded-lg transition-colors"
                :class="activeTab === 'preview' ? 'bg-[var(--y-primary)] text-white' : 'text-[var(--y-text-secondary)] hover:text-[var(--y-text)]'"
                @click="activeTab = 'preview'"
              >
                预览
              </button>
            </div>
            <textarea
              v-if="activeTab === 'write'"
              v-model="form.content"
              class="w-full min-h-[420px] bg-transparent p-6 outline-none resize-y font-mono text-sm leading-relaxed text-[var(--y-text)] placeholder:text-[var(--y-text-muted)]"
              placeholder="# 开始编辑页面内容…"
            />
            <div v-else class="p-6 min-h-[420px]">
              <MarkdownView v-if="form.content" :content="form.content" variant="flat" />
              <p v-else class="text-sm text-[var(--y-text-muted)]">暂无内容可预览</p>
            </div>
          </div>
        </div>

        <div class="space-y-5">
          <div class="y-card p-5">
            <h4 class="text-sm text-[var(--y-text)] mb-4">排序</h4>
            <input v-model.number="form.sort_order" type="number" class="y-input" placeholder="数字越小越靠前" />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

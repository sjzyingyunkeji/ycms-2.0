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
import { postApi, categoryApi, tagApi } from '../../core/api/index.js'
import MarkdownView from '../../shared/components/MarkdownView.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const route = useRoute()
const router = useRouter()

const isEdit = computed(() => route.name === 'post-edit')
const loading = ref(true)
const saving = ref(false)
const activeTab = ref('write')

const categories = ref([])
const tags = ref([])

const form = ref({
  title: '',
  slug: '',
  summary: '',
  content: '',
  cover_image: '',
  category_id: '',
  tag_ids: [],
  status: 'draft',
  is_top: false,
  allow_comment: true,
})

const wordCount = computed(() => {
  const text = form.value.content || ''
  return text.replace(/\s/g, '').length
})

function toggleTag(tagId) {
  const index = form.value.tag_ids.indexOf(tagId)
  if (index > -1) {
    form.value.tag_ids.splice(index, 1)
  } else {
    form.value.tag_ids.push(tagId)
  }
}

async function loadPost() {
  loading.value = true
  try {
    const data = await postApi.detail(route.params.id)
    form.value = {
      title: data.title || '',
      slug: data.slug || '',
      summary: data.summary || '',
      content: data.content || '',
      cover_image: data.cover_image || '',
      category_id: data.category_id || '',
      tag_ids: (data.tags || []).map((t) => t.id),
      status: data.status || 'draft',
      is_top: !!data.is_top,
      allow_comment: data.allow_comment !== undefined ? !!data.allow_comment : true,
    }
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!form.value.title.trim()) {
    showToast('请填写文章标题', 'warning')
    return
  }
  saving.value = true
  try {
    const payload = {
      title: form.value.title,
      slug: form.value.slug,
      summary: form.value.summary,
      content: form.value.content,
      cover_image: form.value.cover_image,
      category_id: form.value.category_id || null,
      tag_ids: form.value.tag_ids,
      status: form.value.status,
      is_top: form.value.is_top,
      allow_comment: form.value.allow_comment,
    }
    if (isEdit.value) {
      await postApi.update(route.params.id, payload)
      showToast('文章已保存', 'success')
    } else {
      await postApi.create(payload)
      showToast('文章已创建', 'success')
    }
    router.push({ name: 'admin-posts' })
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const [cats, tagsData] = await Promise.all([categoryApi.list(), tagApi.list()])
    categories.value = Array.isArray(cats) ? cats : []
    tags.value = Array.isArray(tagsData) ? tagsData : []
  } finally { /* ignore */ }
  if (isEdit.value) {
    await loadPost()
  } else {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-enter">
    <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

    <template v-else>
      <!-- 顶栏 -->
      <div class="y-card p-4 mb-5 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
          <button class="y-btn y-btn-secondary !px-3 !py-2" @click="router.push({ name: 'admin-posts' })">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            返回
          </button>
          <span class="text-sm text-[var(--y-text-secondary)]">{{ isEdit ? '编辑文章' : '新建文章' }} · {{ wordCount }} 字</span>
        </div>
        <div class="flex items-center gap-3">
          <select v-model="form.status" class="y-input !w-28 !py-2">
            <option value="draft">草稿</option>
            <option value="published">发布</option>
          </select>
          <button class="y-btn y-btn-primary" :disabled="saving" @click="save">
            <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            <span v-else>保存</span>
          </button>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-5">
        <!-- ============ 编辑区 ============ -->
        <div class="lg:col-span-2 space-y-5">
          <div class="y-card p-6">
            <input
              v-model="form.title"
              class="w-full bg-transparent border-0 outline-none text-2xl font-bold text-[var(--y-text)] placeholder:text-[var(--y-text-muted)] mb-4"
              placeholder="在这里输入文章标题…"
            />
            <input
              v-model="form.summary"
              class="w-full bg-transparent border-0 outline-none text-sm text-[var(--y-text-secondary)] placeholder:text-[var(--y-text-muted)]"
              placeholder="文章摘要（选填，将显示在列表页）"
            />
          </div>

          <div class="y-card overflow-hidden">
            <!-- 标签切换 -->
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
              <span class="ml-auto text-[10px] text-[var(--y-text-muted)]">支持 Markdown 语法</span>
            </div>

            <textarea
              v-if="activeTab === 'write'"
              v-model="form.content"
              class="w-full min-h-[480px] bg-transparent p-6 outline-none resize-y font-mono text-sm leading-relaxed text-[var(--y-text)] placeholder:text-[var(--y-text-muted)]"
              placeholder="# 开始你的创作…&#10;&#10;支持 Markdown：标题、列表、代码块、引用等"
            />
            <div v-else class="p-6 min-h-[480px]">
              <MarkdownView v-if="form.content" :content="form.content" variant="flat" />
              <p v-else class="text-sm text-[var(--y-text-muted)]">暂无内容可预览</p>
            </div>
          </div>
        </div>

        <!-- ============ 侧边设置 ============ -->
        <div class="space-y-5">
          <!-- 分类 -->
          <div class="y-card p-5">
            <h4 class="text-sm text-[var(--y-text)] mb-4">分类</h4>
            <select v-model="form.category_id" class="y-input">
              <option value="">未分类</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <!-- 标签 -->
          <div class="y-card p-5">
            <h4 class="text-sm text-[var(--y-text)] mb-4">标签</h4>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="tag in tags"
                :key="tag.id"
                class="y-tag y-tag-plain"
                :class="form.tag_ids.includes(tag.id) ? '!bg-[var(--y-primary)] !border-[var(--y-primary)] !text-white' : ''"
                @click="toggleTag(tag.id)"
              >
                {{ tag.name }}
              </button>
              <span v-if="!tags.length" class="text-xs text-[var(--y-text-muted)]">暂无标签，请先在标签管理中创建</span>
            </div>
          </div>

          <!-- 封面 -->
          <div class="y-card p-5">
            <h4 class="text-sm text-[var(--y-text)] mb-4">封面图</h4>
            <input v-model="form.cover_image" class="y-input" placeholder="封面图片 URL（选填）" />
            <div v-if="form.cover_image" class="mt-3 rounded-xl overflow-hidden border border-[var(--y-border)]">
              <img :src="form.cover_image" alt="封面预览" class="w-full h-32 object-cover" />
            </div>
          </div>

          <!-- 选项 -->
          <div class="y-card p-5 space-y-4">
            <h4 class="text-sm text-[var(--y-text)]">选项</h4>
            <label class="flex items-center justify-between cursor-pointer group">
              <span class="text-sm text-[var(--y-text-secondary)] group-hover:text-[var(--y-text)] transition-colors">置顶文章</span>
              <button
                class="relative w-11 h-6 rounded-full transition-colors duration-300 flex-shrink-0"
                :class="form.is_top ? 'bg-[var(--y-primary)]' : 'bg-[var(--y-surface-hover)]'"
                @click.prevent="form.is_top = !form.is_top"
              >
                <span
                  class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white transition-transform duration-300"
                  :class="form.is_top ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </label>
            <label class="flex items-center justify-between cursor-pointer group">
              <span class="text-sm text-[var(--y-text-secondary)] group-hover:text-[var(--y-text)] transition-colors">允许评论</span>
              <button
                class="relative w-11 h-6 rounded-full transition-colors duration-300 flex-shrink-0"
                :class="form.allow_comment ? 'bg-[var(--y-primary)]' : 'bg-[var(--y-surface-hover)]'"
                @click.prevent="form.allow_comment = !form.allow_comment"
              >
                <span
                  class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white transition-transform duration-300"
                  :class="form.allow_comment ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </label>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

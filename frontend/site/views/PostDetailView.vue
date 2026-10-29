<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { onMounted, ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { postApi, commentApi } from '../../core/api/index.js'
import { useSiteStore } from '../../core/stores/site.js'
import { useAuthStore } from '../../core/stores/auth.js'
import MarkdownView from '../../shared/components/MarkdownView.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import EmptyState from '../../shared/components/EmptyState.vue'
import { showToast } from '../../core/utils/toast.js'
import { trackPage } from '../../core/utils/track.js'
import { applySeo, applyJsonLd, absoluteUrl } from '../../core/utils/seo.js'

const route = useRoute()
const router = useRouter()
const site = useSiteStore()
const auth = useAuthStore()

const post = ref(null)
const comments = ref([])
const loading = ref(true)
const viewCounted = ref(false)

const commentForm = ref({ nickname: '', email: '', content: '' })
const submitting = ref(false)

// 兼容两种详情路由：/posts/:id 与 /post/:slug
const identifier = computed(() => route.params.id || route.params.slug || '')

const relatedPosts = computed(() => (Array.isArray(post.value?.related_posts) ? post.value.related_posts : []))

function formatDate(dateStr) {
  if (!dateStr) return ''
  return dateStr.slice(0, 10)
}

function formatDateTime(dateStr) {
  if (!dateStr) return ''
  return dateStr.slice(0, 16)
}

/** 文章规范地址（SEO 唯一地址） */
function canonicalUrl() {
  const slug = post.value?.slug
  return slug ? absoluteUrl(`/post/${slug}`) : absoluteUrl(route.path)
}

/** 跳转详情（优先使用可读 slug 地址） */
function goPost(target) {
  if (!target) return
  if (target.slug) {
    router.push({ name: 'post-detail-slug', params: { slug: target.slug } })
  } else {
    router.push({ name: 'post-detail', params: { id: target.id } })
  }
}

/** 应用 SEO 元信息 */
function applyPostSeo() {
  const p = post.value
  if (!p) return
  const siteName = site.settings.site_name || 'ycms2.0'
  const title = `${p.title} · ${siteName}`
  const description = (p.summary || '').slice(0, 160) || p.title
  const keywords = [
    p.title,
    p.category_name,
    ...(p.tags || []).map((t) => t.name),
    site.settings.site_keywords,
  ]
    .filter(Boolean)
    .join(',')

  applySeo({
    title,
    description,
    keywords,
    image: p.cover_image ? absoluteUrl(p.cover_image) : '',
    url: canonicalUrl(),
    type: 'article',
    publishedAt: p.published_at || p.created_at,
    author: p.author_name || site.settings.owner_name || '',
  })

  applyJsonLd({
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: p.title,
    description,
    image: p.cover_image ? [absoluteUrl(p.cover_image)] : undefined,
    datePublished: p.published_at || p.created_at,
    dateModified: p.updated_at || p.published_at || p.created_at,
    author: { '@type': 'Person', name: p.author_name || site.settings.owner_name || '' },
    publisher: { '@type': 'Organization', name: siteName },
    mainEntityOfPage: { '@type': 'WebPage', '@id': canonicalUrl() },
    keywords: p.summary || '',
    articleSection: p.category_name || '',
    wordCount: (p.content || '').length,
  })
}

async function loadPost() {
  loading.value = true
  try {
    post.value = await postApi.detail(identifier.value)
    await loadComments()
    if (!viewCounted.value) {
      viewCounted.value = true
      postApi.addView(post.value.id).catch(() => {})
      // 访问统计：文章页带上文章 ID，便于热门文章排行
      trackPage(route.path, post.value.id)
    }
    applyPostSeo()
  } catch {
    router.push({ name: 'not-found' })
  } finally {
    loading.value = false
  }
}

async function loadComments() {
  try {
    const data = await commentApi.list({ post_id: identifier.value })
    comments.value = Array.isArray(data) ? data : []
  } catch { /* ignore */ }
}

async function submitComment() {
  if (!commentForm.value.content.trim()) {
    showToast('请填写评论内容', 'warning')
    return
  }
  submitting.value = true
  try {
    await commentApi.create({
      post_id: Number(post.value?.id || identifier.value),
      nickname: commentForm.value.nickname,
      email: commentForm.value.email,
      content: commentForm.value.content,
    })
    commentForm.value.content = ''
    showToast('评论已提交，感谢你的留言', 'success')
  } finally {
    submitting.value = false
  }
}

onMounted(loadPost)

// 上一篇/下一篇（同页面内切换）时重新加载
watch(() => route.params.id, loadPost)
watch(() => route.params.slug, loadPost)
</script>

<template>
  <div class="y-container-narrow py-12 page-enter">
    <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

    <template v-else-if="post">
      <!-- 面包屑 -->
      <nav class="flex items-center gap-2 text-xs text-[var(--y-text-muted)] mb-6">
        <router-link :to="{ name: 'home' }" class="hover:text-[var(--y-primary)] transition-colors duration-200">首页</router-link>
        <span>/</span>
        <router-link :to="{ name: 'posts' }" class="hover:text-[var(--y-primary)] transition-colors duration-200">文章</router-link>
        <span>/</span>
        <span class="text-[var(--y-text-secondary)] truncate max-w-[240px]">{{ post.title }}</span>
      </nav>

      <!-- 文章头部 -->
      <header class="mb-8">
        <h1 class="text-2xl md:text-4xl font-bold text-[var(--y-text)] leading-tight mb-4">
          {{ post.title }}
        </h1>
        <div class="flex flex-wrap items-center gap-3 text-xs">
          <router-link
            v-if="post.category_id"
            :to="{ name: 'category-posts', params: { id: post.category_id } }"
            class="y-tag y-tag-primary"
          >
            {{ post.category_name || '未分类' }}
          </router-link>
          <span v-else class="y-tag y-tag-plain">{{ post.category_name || '未分类' }}</span>
          <span class="text-[var(--y-text-muted)]">{{ formatDate(post.published_at || post.created_at) }}</span>
          <span class="text-[var(--y-text-muted)] flex items-center gap-1">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
            {{ post.views }} 次阅读
          </span>
          <span v-if="post.author_name" class="text-[var(--y-text-muted)]">@{{ post.author_name }}</span>
        </div>
        <div v-if="post.tags?.length" class="flex flex-wrap gap-2 mt-4">
          <router-link
            v-for="tag in post.tags"
            :key="tag.id"
            :to="{ name: 'tag-posts', params: { id: tag.id } }"
            class="y-tag y-tag-plain hover:text-[var(--y-primary)] transition-colors duration-200"
          >
            #{{ tag.name }}
          </router-link>
        </div>
      </header>

      <!-- 封面图 -->
      <div v-if="post.cover_image" class="mb-8 rounded-[var(--y-radius-card)] overflow-hidden border border-[var(--y-border)]">
        <img :src="post.cover_image" :alt="post.title" class="w-full" />
      </div>

      <!-- 正文 -->
      <article class="y-card p-6 md:p-9">
        <MarkdownView variant="flat" :content="post.content" />
      </article>

      <!-- 上下篇 -->
      <nav class="grid md:grid-cols-2 gap-3 mt-6">
        <div
          v-if="post.prev_post"
          class="y-card y-card-hover p-4 cursor-pointer group"
          @click="goPost(post.prev_post)"
        >
          <div class="text-xs text-[var(--y-text-muted)] mb-1">← 上一篇</div>
          <div class="text-sm text-[var(--y-text-secondary)] group-hover:text-[var(--y-primary)] transition-colors duration-200 truncate">{{ post.prev_post.title }}</div>
        </div>
        <div v-else class="hidden md:block" />
        <div
          v-if="post.next_post"
          class="y-card y-card-hover p-4 cursor-pointer group md:text-right md:col-start-2"
          @click="goPost(post.next_post)"
        >
          <div class="text-xs text-[var(--y-text-muted)] mb-1">下一篇 →</div>
          <div class="text-sm text-[var(--y-text-secondary)] group-hover:text-[var(--y-primary)] transition-colors duration-200 truncate">{{ post.next_post.title }}</div>
        </div>
      </nav>

      <!-- ============ 相关文章（SEO 内链） ============ -->
      <section v-if="relatedPosts.length" class="mt-12">
        <div class="y-section-title">相关文章</div>
        <div class="grid sm:grid-cols-2 gap-3">
          <a
            v-for="rp in relatedPosts"
            :key="rp.id"
            :href="`/post/${rp.slug || rp.id}`"
            class="y-card y-card-hover p-4 group block"
            @click.prevent="goPost(rp)"
          >
            <div class="flex items-center gap-2 text-xs mb-2">
              <span class="y-tag y-tag-primary">{{ rp.category_name || '未分类' }}</span>
              <span class="text-[var(--y-text-muted)] flex items-center gap-1">
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                {{ rp.views }}
              </span>
            </div>
            <h3 class="font-semibold text-[var(--y-text)] group-hover:text-[var(--y-primary)] transition-colors duration-200 text-sm leading-snug">
              {{ rp.title }}
            </h3>
            <p v-if="rp.summary" class="text-xs text-[var(--y-text-muted)] mt-1.5 line-clamp-2 leading-relaxed">
              {{ rp.summary }}
            </p>
          </a>
        </div>
      </section>

      <!-- ============ 评论区 ============ -->
      <section v-if="post.allow_comment" class="mt-12">
        <div class="y-section-title">评论 · {{ comments.length }}</div>

        <!-- 评论列表 -->
        <div v-if="comments.length" class="space-y-3 mb-8">
          <div v-for="c in comments" :key="c.id" class="y-card p-4 flex gap-3">
            <div class="w-9 h-9 rounded-full bg-[var(--y-primary-light)] text-[var(--y-primary-active)] flex items-center justify-center flex-shrink-0 text-sm font-semibold">
              {{ (c.nickname || '匿').charAt(0) }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 mb-1">
                <span class="text-sm font-medium text-[var(--y-text)]">{{ c.nickname }}</span>
                <span class="text-xs text-[var(--y-text-muted)]">{{ formatDateTime(c.created_at) }}</span>
              </div>
              <p class="text-sm text-[var(--y-text-secondary)] leading-relaxed whitespace-pre-wrap">{{ c.content }}</p>
            </div>
          </div>
        </div>
        <div v-else class="y-card mb-8">
          <EmptyState variant="flat" icon="comment" text="还没有评论，来说两句吧" />
        </div>

        <!-- 发表评论 -->
        <form class="y-card p-6" @submit.prevent="submitComment">
          <h3 class="text-base font-semibold text-[var(--y-text)] mb-5">发表评论</h3>
          <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-xs text-[var(--y-text-muted)] mb-1.5">昵称</label>
              <a-input v-model="commentForm.nickname" placeholder="怎么称呼你？（可选）" :max-length="50" allow-clear />
            </div>
            <div>
              <label class="block text-xs text-[var(--y-text-muted)] mb-1.5">邮箱</label>
              <a-input v-model="commentForm.email" placeholder="保密，不会公开（可选）" :max-length="120" allow-clear />
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-xs text-[var(--y-text-muted)] mb-1.5">评论内容</label>
            <a-textarea
              v-model="commentForm.content"
              placeholder="写下你想说的话…"
              :max-length="1000"
              :auto-size="{ minRows: 4, maxRows: 8 }"
              show-word-limit
            />
          </div>
          <div class="flex items-center justify-between flex-wrap gap-3">
            <span class="text-xs text-[var(--y-text-muted)]">
              {{ site.settings.comment_audit === '0' ? '评论将直接展示' : '评论需博主审核后展示' }}
            </span>
            <a-button type="primary" html-type="submit" :loading="submitting">提交评论</a-button>
          </div>
        </form>
      </section>

      <div v-else class="y-card mt-12 p-6 text-center text-sm text-[var(--y-text-muted)]">
        该文章已关闭评论
      </div>
    </template>
  </div>
</template>

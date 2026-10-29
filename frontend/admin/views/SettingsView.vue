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
import { settingApi } from '../../core/api/index.js'
import { useSiteStore } from '../../core/stores/site.js'
import HelpTip from '../../shared/components/HelpTip.vue'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import { showToast } from '../../core/utils/toast.js'

const site = useSiteStore()
const loading = ref(true)
const saving = ref(false)

const form = ref({
  site_name: '',
  site_title: '',
  site_description: '',
  site_keywords: '',
  site_icp: '',
  owner_name: '',
  owner_bio: '',
  logo_type: 'text',
  logo_image: '',
  hero_suffix: '',
  site_closed: '0',
  site_closed_message: '',
  social_github: '',
  social_email: '',
  social_weibo: '',
  social_zhihu: '',
  social_csdn: '',
  social_wechat: '',
  social_wechat_qr: '',
  nav_links: [],
  comment_audit: '1',
  posts_per_page: '10',
  home_post_sort: 'hot',
  track_enabled: '1',
})

function parseNavLinks(value) {
  if (Array.isArray(value)) return value
  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value || '[]')
      return Array.isArray(parsed) ? parsed : []
    } catch {
      return []
    }
  }
  return []
}

function addNavLink() {
  form.value.nav_links.push({ name: '', url: '' })
}

function removeNavLink(index) {
  form.value.nav_links.splice(index, 1)
}

async function load() {
  loading.value = true
  try {
    const data = await settingApi.get()
    data.nav_links = parseNavLinks(data.nav_links)
    form.value = { ...form.value, ...data }
  } finally {
    loading.value = false
  }
}

async function save() {
  // 过滤空行并序列化为 JSON 字符串提交
  const navLinks = form.value.nav_links
    .filter((l) => l.name && l.name.trim() && l.url && l.url.trim())
    .map((l) => ({ name: l.name.trim(), url: l.url.trim() }))
  const payload = { ...form.value, nav_links: JSON.stringify(navLinks) }
  saving.value = true
  try {
    const data = await settingApi.update(payload)
    data.nav_links = parseNavLinks(data.nav_links)
    form.value = { ...form.value, ...data }
    site.settings = { ...site.settings, ...data }
    showToast('设置已保存', 'success')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-enter max-w-3xl">
    <LoadingSpinner variant="flat" v-if="loading" text="加载中…" />

    <template v-else>
      <!-- 站点信息 -->
      <div class="y-card p-6 mb-5">
        <h3 class="text-base text-[var(--y-text)] mb-5 flex items-center gap-2">
          <span class="w-1 h-4 rounded-full bg-[var(--y-primary)]" />
          站点信息
        </h3>
        <div class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">站点名称</label>
              <input v-model="form.site_name" class="y-input" />
            </div>
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">浏览器标题</label>
              <input v-model="form.site_title" class="y-input" />
            </div>
          </div>

          <!-- Logo 类型：文字 / 图片 二选一 -->
          <div>
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">站点 Logo（文字 / 图片二选一）</label>
            <div class="flex gap-2">
              <button
                class="y-tag y-tag-plain flex-1 justify-center !py-2"
                :class="form.logo_type !== 'image' ? '!bg-[var(--y-primary-light)] !border-[var(--y-primary)] !text-[var(--y-text)]' : ''"
                @click="form.logo_type = 'text'"
              >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3M9 20h6M12 4v16"/></svg>
                文字 Logo
              </button>
              <button
                class="y-tag y-tag-plain flex-1 justify-center !py-2"
                :class="form.logo_type === 'image' ? '!bg-[var(--y-primary-light)] !border-[var(--y-primary)] !text-[var(--y-text)]' : ''"
                @click="form.logo_type = 'image'"
              >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                图片 Logo
              </button>
            </div>
          </div>

          <!-- 图片 Logo 地址（选择图片后显示） -->
          <div v-if="form.logo_type === 'image'">
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">Logo 图片 URL</label>
            <input v-model="form.logo_image" class="y-input" placeholder="图片地址，可在媒体库上传后复制链接" />
            <div class="flex items-center gap-3 mt-2">
              <div v-if="form.logo_image" class="w-10 h-10 rounded-xl overflow-hidden border border-[var(--y-border)] bg-[var(--y-surface)]/60 flex items-center justify-center">
                <img :src="form.logo_image" alt="Logo 预览" class="w-full h-full object-cover" />
              </div>
              <p class="text-[11px] text-[var(--y-text-muted)]">支持图片链接：建议正方形图片；留空则回退为默认品牌图标 /logo.svg</p>
            </div>
          </div>
          <div>
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">站点描述</label>
            <textarea v-model="form.site_description" class="y-input min-h-[80px] resize-y" />
          </div>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">站点关键词（逗号分隔）</label>
              <input v-model="form.site_keywords" class="y-input" />
            </div>
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">备案号（选填）</label>
              <input v-model="form.site_icp" class="y-input" placeholder="如：京ICP备XXXXXXXX号" />
            </div>
          </div>
        </div>
      </div>

      <!-- 关站设置 -->
      <div class="y-card p-6 mb-5">
        <h3 class="text-base text-[var(--y-text)] mb-5 flex items-center gap-2">
          <span class="w-1 h-4 rounded-full bg-[var(--y-primary)]" />
          关站设置
        </h3>
        <div class="space-y-4">
          <label class="flex items-center justify-between cursor-pointer group">
            <div>
              <div class="text-sm text-[var(--y-text-secondary)] group-hover:text-[var(--y-text)] transition-colors duration-300">关闭站点</div>
              <p class="text-[11px] text-[var(--y-text-muted)] mt-1">开启后前台全站显示关闭页（后台仍可正常登录管理）</p>
            </div>
            <button
              type="button"
              class="relative w-11 h-6 rounded-full transition-colors duration-300 flex-shrink-0"
              :class="form.site_closed === '1' ? 'bg-rose-500/70' : 'bg-[var(--y-surface-hover)]'"
              @click.prevent="form.site_closed = form.site_closed === '1' ? '0' : '1'"
            >
              <span
                class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white transition-transform duration-300"
                :class="form.site_closed === '1' ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </label>

          <div>
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">关站文案</label>
            <textarea
              v-model="form.site_closed_message"
              class="y-input min-h-[90px] resize-y"
              placeholder="站点暂时关闭时向前台访客展示的说明文字"
            />
          </div>
        </div>
      </div>

      <!-- 博主信息 -->
      <div class="y-card p-6 mb-5">
        <h3 class="text-base text-[var(--y-text)] mb-5 flex items-center gap-2">
          <span class="w-1 h-4 rounded-full bg-[var(--y-primary)]" />
          博主信息
        </h3>
        <div class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">博主昵称</label>
              <input v-model="form.owner_name" class="y-input" />
            </div>
            <div>
              <label class="flex items-center gap-1.5 text-xs text-[var(--y-text-secondary)] mb-1.5">
                首页标题后缀
                <HelpTip text="首页主标题 = 博主昵称 + 此后缀" />
              </label>
              <input v-model="form.hero_suffix" class="y-input" placeholder="如：的个人博客" />
            </div>
          </div>
          <div>
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">个人简介</label>
            <textarea v-model="form.owner_bio" class="y-input min-h-[80px] resize-y" />
          </div>
        </div>
      </div>

      <!-- 社交账号 -->
      <div class="y-card p-6 mb-5">
        <h3 class="text-base text-[var(--y-text)] mb-5 flex items-center gap-2">
          <span class="w-1 h-4 rounded-full bg-[var(--y-primary)]" />
          社交账号
        </h3>
        <div class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">GitHub</label>
              <input v-model="form.social_github" class="y-input" placeholder="https://github.com/..." />
            </div>
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">邮箱</label>
              <input v-model="form.social_email" class="y-input" placeholder="you@example.com" />
            </div>
          </div>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">微博</label>
              <input v-model="form.social_weibo" class="y-input" placeholder="https://weibo.com/..." />
            </div>
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">知乎</label>
              <input v-model="form.social_zhihu" class="y-input" placeholder="https://zhihu.com/people/..." />
            </div>
          </div>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">CSDN</label>
              <input v-model="form.social_csdn" class="y-input" placeholder="https://blog.csdn.net/..." />
            </div>
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">微信公众平台（主页链接）</label>
              <input v-model="form.social_wechat" class="y-input" placeholder="https://mp.weixin.qq.com/..." />
            </div>
          </div>
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">微信二维码图片 URL</label>
          <input v-model="form.social_wechat_qr" class="y-input" placeholder="二维码图片地址，填写后前台点击微信图标弹出二维码（可先在媒体库上传复制链接）" />
        </div>
      </div>

      <!-- 导航友站（前台导航栏「友站」下拉） -->
      <div class="y-card p-6 mb-5">
        <h3 class="text-base text-[var(--y-text)] mb-5 flex items-center gap-2">
          <span class="w-1 h-4 rounded-full bg-[var(--y-primary)]" />
          导航友站
        </h3>
        <p class="text-xs text-[var(--y-text-muted)] mb-4">配置后将出现在前台顶部导航栏的「友站」下拉菜单中，点击后在新标签页打开。</p>
        <div class="space-y-3">
          <div v-for="(link, i) in form.nav_links" :key="i" class="flex items-center gap-3">
            <input v-model="link.name" class="y-input !w-40 flex-shrink-0" placeholder="站点名称" maxlength="50" />
            <input v-model="link.url" class="y-input flex-1" placeholder="https://..." />
            <button class="y-btn-text y-btn-text-danger !px-2.5" title="删除" @click="removeNavLink(i)">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            </button>
          </div>
          <button class="y-btn y-btn-secondary !py-2 !text-sm" @click="addNavLink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            添加友站
          </button>
        </div>
      </div>

      <!-- 评论与列表 -->
      <div class="y-card p-6 mb-5">
        <h3 class="text-base text-[var(--y-text)] mb-5 flex items-center gap-2">
          <span class="w-1 h-4 rounded-full bg-[var(--y-primary)]" />
          评论与列表
        </h3>
        <div class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">评论审核</label>
              <select v-model="form.comment_audit" class="y-input">
                <option value="1">开启（评论需审核后展示）</option>
                <option value="0">关闭（评论直接展示）</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">每页文章数</label>
              <input v-model="form.posts_per_page" type="number" min="1" max="50" class="y-input" />
            </div>
            <div>
              <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">首页文章排序</label>
              <select v-model="form.home_post_sort" class="y-input">
                <option value="hot">按热门（浏览量优先）</option>
                <option value="latest">按发布时间</option>
              </select>
              <p class="text-[11px] text-[var(--y-text-muted)] mt-1">置顶文章始终排在最前</p>
            </div>
          </div>

          <!-- 访问统计开关 -->
          <label class="flex items-center justify-between cursor-pointer group pt-2">
            <div>
              <div class="flex items-center gap-1.5 text-sm text-[var(--y-text-secondary)] group-hover:text-[var(--y-text)] transition-colors duration-300">
                访问统计
                <HelpTip text="记录全站访问（含首页）与文章阅读量，访客 IP 仅以哈希形式存储" width="17rem" />
              </div>
            </div>
            <button
              type="button"
              class="relative w-11 h-6 rounded-full transition-colors duration-300 flex-shrink-0"
              :class="form.track_enabled === '1' ? 'bg-[var(--y-primary)]' : 'bg-[var(--y-surface-hover)]'"
              @click.prevent="form.track_enabled = form.track_enabled === '1' ? '0' : '1'"
            >
              <span
                class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white transition-transform duration-300"
                :class="form.track_enabled === '1' ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </label>
        </div>
      </div>

      <div class="flex justify-end">
        <button class="y-btn y-btn-primary" :disabled="saving" @click="save">
          <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
          <span v-else>保存设置</span>
        </button>
      </div>
    </template>
  </div>
</template>

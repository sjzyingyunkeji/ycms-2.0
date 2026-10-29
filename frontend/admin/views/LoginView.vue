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
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../core/stores/auth.js'
import { useSiteStore } from '../../core/stores/site.js'
import { authApi } from '../../core/api/index.js'
import BrandLogo from '../../shared/components/BrandLogo.vue'
import { showToast } from '../../core/utils/toast.js'

const auth = useAuthStore()
const site = useSiteStore()
const route = useRoute()
const router = useRouter()

const form = ref({ username: '', password: '' })
const loading = ref(false)
// 是否仍为出厂默认账号（控制默认账号提示的显示）
const defaultActive = ref(true)

// 生成一次性随机 nonce（防重放）
function generateNonce() {
  const bytes = new Uint8Array(16)
  crypto.getRandomValues(bytes)
  return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
}

async function handleLogin() {
  if (!form.value.username || !form.value.password) {
    showToast('请输入用户名和密码', 'warning')
    return
  }
  loading.value = true
  try {
    // 每次登录使用全新一次性 nonce + 时间戳（服务端防重放校验）
    await auth.login(form.value.username, form.value.password, {
      nonce: generateNonce(),
      ts: Math.floor(Date.now() / 1000),
    })
    // 默认账号 → 强制进入初始化页；否则进入后台
    if (auth.mustChange) {
      showToast('请先修改默认用户名和密码', 'warning')
      router.push({ name: 'admin-setup' })
      return
    }
    showToast('登录成功', 'success')
    const redirect = route.query.redirect || '/admin'
    router.push(redirect)
  } catch {
    // 拦截器已提示
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    const data = await authApi.setupStatus()
    defaultActive.value = !!data.default_credentials_active
  } catch {
    defaultActive.value = false
  }
})
</script>

<template>
  <div class="min-h-screen flex bg-[var(--y-bg)]">
    <!-- 左侧品牌区（大屏可见） -->
    <div class="hidden lg:flex w-[46%] xl:w-[42%] flex-col justify-between p-12 bg-[var(--y-surface)] border-r border-[var(--y-border)]">
      <BrandLogo size="lg" />

      <div>
        <h1 class="text-[32px] leading-tight font-bold text-[var(--y-text)] mb-4">
          内容管理<br />从这里开始
        </h1>
        <p class="text-sm text-[var(--y-text-secondary)] leading-relaxed max-w-sm">
          文章、页面、分类、评论与媒体资源，一站式管理。轻量、快速、无冗余依赖。
        </p>

        <ul class="mt-10 space-y-4">
          <li v-for="item in ['Markdown 写作与草稿管理', '评论审核与内容安全', 'SEO 与站点配置开箱可用']" :key="item" class="flex items-center gap-3 text-sm text-[var(--y-text-secondary)]">
            <span class="w-5 h-5 rounded-full bg-[var(--y-primary-light)] flex items-center justify-center flex-shrink-0">
              <svg class="w-3 h-3 text-[var(--y-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
            </span>
            {{ item }}
          </li>
        </ul>
      </div>

      <div class="text-xs text-[var(--y-text-muted)]">
        © {{ new Date().getFullYear() }} {{ site.settings.site_name || 'ycms2.0' }}
      </div>
    </div>

    <!-- 右侧表单区 -->
    <div class="flex-1 flex items-center justify-center px-5 py-12">
      <div class="w-full max-w-[360px] page-enter">
        <!-- 小屏 Logo -->
        <div class="lg:hidden mb-10">
          <BrandLogo size="lg" />
        </div>

        <h2 class="text-xl font-semibold text-[var(--y-text)] mb-1.5">登录后台</h2>
        <p class="text-sm text-[var(--y-text-muted)] mb-8">请输入管理员账号继续</p>

        <form @submit.prevent="handleLogin">
          <div class="mb-5">
            <label class="y-label">用户名</label>
            <input
              v-model="form.username"
              class="y-input !h-10"
              placeholder="请输入用户名"
              autocomplete="username"
              maxlength="30"
            />
          </div>
          <div class="mb-6">
            <label class="y-label">密码</label>
            <input
              v-model="form.password"
              type="password"
              class="y-input !h-10"
              placeholder="请输入密码"
              autocomplete="current-password"
              maxlength="72"
              @keyup.enter="handleLogin"
            />
          </div>

          <button type="submit" class="y-btn y-btn-primary y-btn-block !h-10" :disabled="loading">
            <span v-if="loading" class="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
            <span v-else>登 录</span>
          </button>
        </form>

        <!-- 默认账号提示：仅当仍为出厂默认账号时显示 -->
        <div v-if="defaultActive" class="y-alert y-alert-warning mt-6">
          <span class="text-xs leading-relaxed">
            当前仍为默认账号
            <code class="font-mono px-1 rounded bg-white/60">admin</code>
            /
            <code class="font-mono px-1 rounded bg-white/60">admin123</code>
            ，登录后请立即修改。
          </span>
        </div>

        <div class="mt-10 flex items-center justify-between text-xs">
          <a href="/" class="text-[var(--y-text-muted)] hover:text-[var(--y-primary)] transition-colors duration-200">← 返回前台</a>
          <span class="font-mono text-[var(--y-text-disabled)]">v2.0.1</span>
        </div>
      </div>
    </div>
  </div>
</template>

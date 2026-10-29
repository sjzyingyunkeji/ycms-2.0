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
import { useAuthStore } from '../../core/stores/auth.js'
import { useSiteStore } from '../../core/stores/site.js'
import { authApi } from '../../core/api/index.js'
import BrandLogo from '../../shared/components/BrandLogo.vue'
import { showToast } from '../../core/utils/toast.js'

const auth = useAuthStore()
const site = useSiteStore()
const router = useRouter()

const form = ref({ username: '', password: '', password_confirmation: '' })
const loading = ref(true)
const submitting = ref(false)

const usernameError = ref('')
const passwordError = ref('')

// 与后端一致的规则（前端预校验，提升体验）
const BANNED = ['admin', 'administrator', 'root', 'system', 'sysadmin', 'superadmin', 'test', 'testing', 'guest', 'user', 'operator', 'manager', 'webmaster']
const WEAK = ['123456', '12345678', '123456789', '1234567890', 'password', 'admin123', 'qwerty', 'abc123', '111111', '000000', 'iloveyou', 'letmein', 'welcome', 'ycms123', 'cms123']

function validate() {
  let ok = true
  usernameError.value = ''
  passwordError.value = ''

  const u = form.value.username.trim()
  if (u.length < 3 || u.length > 30) {
    usernameError.value = '用户名长度需为 3-30 个字符'
    ok = false
  } else if (!/^[\u4e00-\u9fa5\w-]+$/.test(u)) {
    usernameError.value = '只能包含字母、数字、下划线、连字符和中文'
    ok = false
  } else if (BANNED.includes(u.toLowerCase())) {
    usernameError.value = '该用户名为系统保留的默认名称，禁止使用'
    ok = false
  }

  const p = form.value.password
  if (p.length < 8) {
    passwordError.value = '密码长度至少 8 位'
    ok = false
  } else if (!/[A-Za-z]/.test(p) || !/\d/.test(p)) {
    passwordError.value = '密码必须同时包含字母和数字'
    ok = false
  } else if (WEAK.includes(p.toLowerCase())) {
    passwordError.value = '该密码为常见弱口令，禁止使用'
    ok = false
  } else if (u && p.toLowerCase().includes(u.toLowerCase())) {
    passwordError.value = '密码不能包含用户名'
    ok = false
  } else if (p !== form.value.password_confirmation) {
    passwordError.value = '两次输入的密码不一致'
    ok = false
  }

  return ok
}

async function submit() {
  if (!validate()) return
  submitting.value = true
  try {
    await authApi.credentials({
      username: form.value.username.trim(),
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    })
    showToast('账号初始化完成，请使用新账号重新登录', 'success')
    await auth.logout()
    router.push({ name: 'admin-login' })
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  const user = await auth.checkSession()
  loading.value = false
  // 非默认账号无需初始化
  if (user && !auth.mustChange) {
    router.replace({ name: 'dashboard' })
  } else if (!user) {
    router.replace({ name: 'admin-login' })
  }
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-5 py-16">
    <div class="w-full max-w-md">
      <!-- 标题 -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center mb-5">
          <BrandLogo size="lg" />
        </div>
        <h1 class="text-2xl font-bold text-[var(--y-text)]">账号安全初始化</h1>
        <p class="text-sm text-[var(--y-text-muted)] mt-2">检测到您正在使用出厂默认账号，请立即设置新的用户名和密码</p>
      </div>

      <div v-if="loading" class="y-card p-8 text-center text-sm text-[var(--y-text-muted)]">加载中…</div>

      <form v-else class="y-card p-8" @submit.prevent="submit">
        <!-- 安全提示 -->
        <div class="mb-6 rounded-xl border border-amber-400/30 bg-[var(--y-warning)]/[0.07] px-4 py-3 text-xs text-[var(--y-warning)]/90 leading-relaxed">
          为保障站点安全：<br />
          · 禁止使用 <code class="font-mono">admin / root</code> 等默认用户名<br />
          · 密码至少 8 位，须同时包含字母和数字，禁止弱口令
        </div>

        <div class="mb-5">
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">新用户名 <span class="text-[var(--y-danger)]">*</span></label>
          <input v-model="form.username" class="y-input" placeholder="3-30 位字母/数字/下划线/中文" maxlength="30" autocomplete="off" />
          <p v-if="usernameError" class="text-xs text-[var(--y-danger)] mt-1.5">{{ usernameError }}</p>
        </div>

        <div class="mb-5">
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">新密码 <span class="text-[var(--y-danger)]">*</span></label>
          <input v-model="form.password" type="password" class="y-input" placeholder="至少 8 位，包含字母和数字" maxlength="72" autocomplete="new-password" />
          <p v-if="passwordError" class="text-xs text-[var(--y-danger)] mt-1.5">{{ passwordError }}</p>
        </div>

        <div class="mb-7">
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">确认新密码 <span class="text-[var(--y-danger)]">*</span></label>
          <input v-model="form.password_confirmation" type="password" class="y-input" placeholder="再次输入新密码" maxlength="72" autocomplete="new-password" />
        </div>

        <button type="submit" class="y-btn y-btn-primary w-full" :disabled="submitting">
          <span v-if="submitting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
          <span v-else>保存并重新登录</span>
        </button>
      </form>

      <div class="text-center mt-6">
        <a href="/" class="text-xs text-[var(--y-text-muted)] hover:text-[var(--y-text-secondary)] transition-colors">
          ← 返回前台
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { ref } from 'vue'
import { useAuthStore } from '../../core/stores/auth.js'
import { showToast } from '../../core/utils/toast.js'

const auth = useAuthStore()

const profile = ref({
  nickname: auth.user?.nickname || '',
  email: auth.user?.email || '',
  bio: auth.user?.bio || '',
  avatar: auth.user?.avatar || '',
})

const password = ref({ old_password: '', new_password: '', confirm: '' })
const savingProfile = ref(false)
const savingPassword = ref(false)

async function saveProfile() {
  savingProfile.value = true
  try {
    const { authApi } = await import('../../core/api/index.js')
    const data = await authApi.updateProfile(profile.value)
    auth.user = data
    showToast('资料已更新', 'success')
  } finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  if (password.value.new_password !== password.value.confirm) {
    showToast('两次输入的新密码不一致', 'warning')
    return
  }
  if (password.value.new_password.length < 6) {
    showToast('新密码长度至少 6 位', 'warning')
    return
  }
  savingPassword.value = true
  try {
    const { authApi } = await import('../../core/api/index.js')
    await authApi.updatePassword({
      old_password: password.value.old_password,
      new_password: password.value.new_password,
    })
    showToast('密码修改成功，请重新登录', 'success')
    password.value = { old_password: '', new_password: '', confirm: '' }
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="page-enter max-w-2xl">
    <!-- 资料 -->
    <div class="y-card p-6 mb-5">
      <div class="flex items-center gap-4 mb-6">
        <div class="w-16 h-16 rounded-2xl bg-[var(--y-primary)] border border-[var(--y-border)] flex items-center justify-center text-white text-2xl font-bold">
          {{ (auth.user?.nickname || auth.user?.username || 'A').charAt(0) }}
        </div>
        <div>
          <div class="text-lg text-[var(--y-text)]">{{ auth.user?.nickname || auth.user?.username }}</div>
          <div class="text-xs text-[var(--y-text-muted)]">@{{ auth.user?.username }} · 注册于 {{ auth.user?.created_at?.slice(0, 10) }}</div>
        </div>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">昵称</label>
          <input v-model="profile.nickname" class="y-input" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">邮箱</label>
          <input v-model="profile.email" class="y-input" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">头像 URL（选填）</label>
          <input v-model="profile.avatar" class="y-input" placeholder="留空使用默认头像" />
        </div>
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">个人简介</label>
          <textarea v-model="profile.bio" class="y-input min-h-[80px] resize-y" />
        </div>
        <div class="flex justify-end">
          <button class="y-btn y-btn-primary" :disabled="savingProfile" @click="saveProfile">
            <span v-if="savingProfile" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            <span v-else>保存资料</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 密码 -->
    <div class="y-card p-6">
      <h3 class="text-base text-[var(--y-text)] mb-5 flex items-center gap-2">
        <span class="w-1 h-4 rounded-full bg-[var(--y-primary)]" />
        修改密码
      </h3>
      <div class="space-y-4">
        <div>
          <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">当前密码</label>
          <input v-model="password.old_password" type="password" class="y-input" />
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">新密码</label>
            <input v-model="password.new_password" type="password" class="y-input" placeholder="至少 6 位" />
          </div>
          <div>
            <label class="block text-xs text-[var(--y-text-secondary)] mb-1.5">确认新密码</label>
            <input v-model="password.confirm" type="password" class="y-input" />
          </div>
        </div>
        <div class="flex justify-end">
          <button class="y-btn y-btn-secondary" :disabled="savingPassword" @click="savePassword">
            <span v-if="savingPassword" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            <span v-else>修改密码</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

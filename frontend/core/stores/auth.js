/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '../api/index.js'

/**
 * 认证状态（Cookie 会话模式）
 * 登录成功后由服务端写入 HttpOnly Cookie（24 小时有效），
 * 刷新页面 / 关闭浏览器再打开无需重新登录。
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  // 是否仍为出厂默认账号（admin/admin123）——需强制修改用户名密码
  const mustChange = ref(false)
  // 运行目录中是否仍存在高危重置密码文件
  const resetFileExists = ref(false)
  // 会话是否已检查过（防止重复请求）
  const initialized = ref(false)

  const isLoggedIn = computed(() => !!user.value)

  async function login(username, password, extra = {}) {
    const data = await authApi.login({ username, password, ...extra })
    user.value = data.user
    mustChange.value = !!data.must_change_credentials
    resetFileExists.value = !!data.reset_file_exists
    initialized.value = true
    return data
  }

  /**
   * 静默检查会话（Cookie 自动携带），用于刷新后恢复登录态
   */
  async function checkSession() {
    if (initialized.value) return user.value
    try {
      const data = await authApi.me({ silent: true })
      user.value = data
      mustChange.value = !!data.must_change_credentials
      resetFileExists.value = !!data.reset_file_exists
    } catch {
      user.value = null
    }
    initialized.value = true
    return user.value
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch { /* ignore */ }
    user.value = null
    mustChange.value = false
    resetFileExists.value = false
    initialized.value = true
  }

  return { user, mustChange, resetFileExists, initialized, isLoggedIn, login, checkSession, logout }
})

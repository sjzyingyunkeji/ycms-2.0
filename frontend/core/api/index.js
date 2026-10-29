/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

import axios from 'axios'
import { useAuthStore } from '../stores/auth.js'
import { routerRef } from '../router/registry.js'
import { showToast } from '../utils/toast.js'

// 零配置部署：API 走真实文件 /api.php（无需 Nginx 重写规则）
// 若服务器配置了 /api 重写，也可通过 VITE_API_BASE 切回 /api
const API_BASE = import.meta.env.VITE_API_BASE || '/api.php'

const request = axios.create({
  baseURL: API_BASE,
  timeout: 15000,
  withCredentials: true, // Cookie 会话模式：自动携带登录 Cookie
})

// Cookie 会话模式：登录态由 HttpOnly Cookie 自动携带，无需手动附加令牌
request.interceptors.request.use((config) => config)

request.interceptors.response.use(
  (response) => {
    const body = response.data
    if (body && typeof body === 'object' && 'code' in body) {
      if (body.code !== 0) {
        showToast(body.message || '请求失败', 'error')
        return Promise.reject(new Error(body.message || '请求失败'))
      }
      return body.data
    }
    return body
  },
  (error) => {
    const status = error.response?.status
    const payload = error.response?.data
    // 默认账号门禁：未修改用户名/密码前，重定向到强制初始化页
    if (status === 403 && payload?.errors?.code === 'must_change_credentials') {
      // 从路由注册表取当前应用的 router（前台/后台各自独立构建）
      const router = routerRef.current
      if (router && router.currentRoute.value.name !== 'admin-setup') {
        router.replace({ name: 'admin-setup' })
      }
      return Promise.reject(error)
    }
    if (status === 401) {
      const auth = useAuthStore()
      const isLoginRequest = String(error.config?.url || '').includes('/auth/login')
      if (auth.user && !isLoginRequest) {
        auth.logout()
        if (!error.config?.silent) {
          showToast('登录已过期，请重新登录', 'warning')
        }
      } else if (!error.config?.silent) {
        // 登录失败时也展示后端的具体原因（此前后端 401 被静默，用户看不到提示）
        showToast(payload?.message || '用户名或密码错误', 'error')
      }
    } else {
      const message = payload?.message || error.message || '网络错误'
      showToast(message, 'error')
    }
    return Promise.reject(error)
  }
)

export default request

/* ===================== 各模块 API ===================== */

export const authApi = {
  login: (data) => request.post('/auth/login', data),
  logout: () => request.post('/auth/logout'),
  me: (config) => request.get('/auth/me', config),
  setupStatus: () => request.get('/auth/setup-status'),
  credentials: (data) => request.post('/auth/credentials', data),
  updateProfile: (data) => request.put('/auth/profile', data),
  updatePassword: (data) => request.put('/auth/password', data),
}

export const postApi = {
  list: (params) => request.get('/posts', { params }),
  detail: (id) => request.get(`/posts/${id}`),
  create: (data) => request.post('/posts', data),
  update: (id, data) => request.put(`/posts/${id}`, data),
  remove: (id) => request.delete(`/posts/${id}`),
  addView: (id) => request.post(`/posts/${id}/view`),
  toggleTop: (id, isTop) => request.post(`/posts/${id}/top`, { is_top: isTop }),
}

export const categoryApi = {
  list: () => request.get('/categories'),
  create: (data) => request.post('/categories', data),
  update: (id, data) => request.put(`/categories/${id}`, data),
  remove: (id) => request.delete(`/categories/${id}`),
}

export const tagApi = {
  list: () => request.get('/tags'),
  create: (data) => request.post('/tags', data),
  update: (id, data) => request.put(`/tags/${id}`, data),
  remove: (id) => request.delete(`/tags/${id}`),
}

export const pageApi = {
  list: () => request.get('/pages'),
  detail: (idOrSlug) => request.get(`/pages/${idOrSlug}`),
  create: (data) => request.post('/pages', data),
  update: (id, data) => request.put(`/pages/${id}`, data),
  remove: (id) => request.delete(`/pages/${id}`),
}

export const mediaApi = {
  list: (params) => request.get('/media', { params }),
  upload: (formData, onProgress) =>
    request.post('/media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: onProgress,
    }),
  remove: (id) => request.delete(`/media/${id}`),
}

export const commentApi = {
  list: (params) => request.get('/comments', { params }),
  create: (data) => request.post('/comments', data),
  update: (id, data) => request.put(`/comments/${id}`, data),
  remove: (id) => request.delete(`/comments/${id}`),
}

export const linkApi = {
  list: () => request.get('/links'),
  create: (data) => request.post('/links', data),
  update: (id, data) => request.put(`/links/${id}`, data),
  remove: (id) => request.delete(`/links/${id}`),
}

export const settingApi = {
  get: () => request.get('/settings'),
  update: (settings) => request.put('/settings', { settings }),
}

export const dashboardApi = {
  stats: () => request.get('/dashboard/stats'),
}

export const statsApi = {
  overview: (params) => request.get('/stats/overview', { params }),
  post: (id, params) => request.get(`/stats/post/${id}`, { params }),
}

export const updateApi = {
  // 状态读取不发起网络请求，首屏不会卡在检查更新上
  status: () => request.get('/system/update', { timeout: 30000 }),
  check: (force = false) => request.post('/system/update/check', { force }, { timeout: 120000 }),
  // 单步执行：download / extract / backup / apply / migrate / finalize
  run: (step) => request.post('/system/update/run', { step }, { timeout: 600000 }),
  backups: () => request.get('/system/update/backups'),
  rollback: (name, withDb = false) => request.post('/system/update/rollback', { name, with_db: withDb }, { timeout: 600000 }),
  upload: (formData, onProgress) =>
    request.post('/system/update/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 600000,
      onUploadProgress: onProgress,
    }),
}

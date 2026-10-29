/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

import { reactive } from 'vue'

/**
 * 全局 Toast 状态（供 ToastHost 组件渲染，任意模块可调用）
 */
export const toastState = reactive({
  toasts: [],
})

let seq = 0

/**
 * 显示一条轻提示
 * @param {string} message 提示内容
 * @param {'success'|'error'|'warning'|'info'} type 类型
 * @param {number} duration 展示时长（毫秒）
 */
export function showToast(message, type = 'info', duration = 3000) {
  const id = ++seq
  toastState.toasts.push({ id, message, type })
  if (toastState.toasts.length > 5) {
    toastState.toasts.shift()
  }
  setTimeout(() => {
    const index = toastState.toasts.findIndex((t) => t.id === id)
    if (index > -1) {
      toastState.toasts.splice(index, 1)
    }
  }, duration)
}

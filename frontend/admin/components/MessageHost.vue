<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { watch } from 'vue'
import { Message } from '@arco-design/web-vue'
import '@arco-design/web-vue/es/message/style/css.js'
import { toastState } from '../../core/utils/toast.js'

/**
 * 通用后台的消息提示：统一走 Arco Design 的 Message
 * 视觉与交互遵循 Arco 默认规范（顶部居中、白底卡片、图标 + 文案、3 秒自动关闭）。
 * 全局 showToast() 的调用方式不变，只是渲染层换成 Arco。
 */
const seen = new Set()

const handlers = {
  success: Message.success,
  error: Message.error,
  warning: Message.warning,
  info: Message.info,
}

watch(
  () => toastState.toasts.map((item) => item.id).join(','),
  () => {
    for (const item of toastState.toasts) {
      if (seen.has(item.id)) continue
      seen.add(item.id)
      const push = handlers[item.type] || Message.info
      push({ content: item.message, duration: 3000, closable: false })
    }
    // 清理已出队的记录，避免 seen 无限增长
    const alive = new Set(toastState.toasts.map((item) => item.id))
    for (const id of seen) {
      if (!alive.has(id)) seen.delete(id)
    }
  },
  { immediate: true }
)
</script>

<template>
  <span class="hidden" aria-hidden="true" />
</template>

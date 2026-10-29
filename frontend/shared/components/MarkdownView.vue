<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { computed } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

const props = defineProps({
  content: { type: String, default: '' },
  /** 风格：star（星空，后台默认）/ flat（扁平商务，前台使用） */
  variant: { type: String, default: 'star' },
})

marked.setOptions({
  breaks: true,
  gfm: true,
})

const html = computed(() => {
  if (!props.content) return ''
  const raw = marked.parse(props.content)
  return DOMPurify.sanitize(raw, {
    ADD_ATTR: ['target', 'rel'],
  })
})
</script>

<template>
  <div :class="variant === 'star' ? 'markdown-body' : 'y-prose'" v-html="html" />
</template>

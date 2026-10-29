<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="min-h-[70vh] flex items-center justify-center px-5">
    <div class="text-center">
      <div class="text-[100px] leading-none font-bold text-[var(--y-primary-light)] select-none">404</div>
      <p class="text-[var(--y-text)] font-medium mt-2">页面不存在</p>
      <p class="text-sm text-[var(--y-text-muted)] mt-2 mb-8">你访问的页面可能已被移动或删除</p>
      <div class="flex items-center justify-center gap-3">
        <a-button type="primary" @click="router.push({ name: 'home' })">回到首页</a-button>
        <a-button @click="router.push({ name: 'posts' })">浏览文章</a-button>
      </div>
    </div>
  </div>
</template>

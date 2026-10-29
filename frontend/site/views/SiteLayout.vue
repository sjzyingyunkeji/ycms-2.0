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
import SiteHeader from '../components/SiteHeader.vue'
import SiteFooter from '../components/SiteFooter.vue'
import SiteClosedView from './SiteClosedView.vue'
import { useSiteStore } from '../../core/stores/site.js'

const site = useSiteStore()

// 关站开关（后台「站点设置」控制）：开启后前台全站显示关闭页，后台仍可登录
const closed = computed(() => String(site.settings.site_closed) === '1')
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <template v-if="closed">
      <SiteClosedView />
    </template>
    <template v-else>
      <SiteHeader />
      <main class="flex-1 pt-[72px]">
        <router-view v-slot="{ Component }">
          <transition name="page" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
      <SiteFooter />
    </template>
  </div>
</template>

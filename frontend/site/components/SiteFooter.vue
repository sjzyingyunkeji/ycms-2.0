<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { ref, computed } from 'vue'
import { useSiteStore } from '../../core/stores/site.js'
import BaseModal from '../../shared/components/BaseModal.vue'

const site = useSiteStore()

// 微信二维码弹窗
const qrOpen = ref(false)

const socials = [
  { key: 'social_github', label: 'GitHub', icon: 'M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 00-.94-2.61c3.14-.35 4.44-2.34 4.44-5.12 0-1.34-.47-2.58-1.32-3.57.41-1.09.37-2.28-.06-3.35 0 0-1.05-.33-3.5 1.3a12.4 12.4 0 00-6.35 0C6.86 3.18 5.8 3.5 5.8 3.5c-.43 1.07-.47 2.26-.06 3.35A5.03 5.03 0 004 10.4c0 2.78 1.3 4.77 4.44 5.12a3.37 3.37 0 00-.94 2.58V22' },
  { key: 'social_email', label: 'Email', icon: 'M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z M22 6l-10 7L2 6' },
  { key: 'social_weibo', label: '微博', icon: 'M18 8a4 4 0 00-4-4 4 4 0 00-8 0c0 4 3.5 5 6 7 3 2.5 3 6 0 8s-7-1-8-4' },
  { key: 'social_zhihu', label: '知乎', icon: 'M12 2a10 10 0 100 20 10 10 0 000-20zM8 12h8M12 8v8' },
  { key: 'social_csdn', label: 'CSDN', icon: 'M4 5h16v14H4zM8 9h8M8 13h5' },
]

const hasSocials = computed(() => socials.some((s) => site.settings[s.key]) || resolveWechat() !== '')

// 微信公众平台：有二维码图片 → 弹窗展示；仅有链接 → 新标签页打开
function resolveWechat() {
  if (site.settings.social_wechat_qr) return 'qr'
  if (site.settings.social_wechat) return 'link'
  return ''
}

function onWechatClick() {
  const mode = resolveWechat()
  if (mode === 'qr') {
    qrOpen.value = true
  } else if (mode === 'link') {
    window.open(site.settings.social_wechat, '_blank', 'noopener')
  }
}
</script>

<template>
  <footer class="mt-20 border-t border-[var(--y-border)] bg-[var(--y-surface)]">
    <div class="y-container py-10">
      <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-center md:text-left">
          <div class="text-base font-semibold text-[var(--y-text)] mb-1">{{ site.settings.site_name || 'ycms2.0' }}</div>
          <p class="text-sm text-[var(--y-text-secondary)] max-w-md">{{ site.settings.site_description }}</p>
        </div>

        <div v-if="hasSocials" class="flex items-center gap-2">
          <a
            v-for="s in socials"
            :key="s.key"
            v-show="site.settings[s.key]"
            :href="s.key === 'social_email' ? `mailto:${site.settings[s.key]}` : site.settings[s.key]"
            target="_blank"
            rel="noopener"
            class="w-9 h-9 rounded-[var(--y-radius-sm)] border border-[var(--y-border)] flex items-center justify-center text-[var(--y-text-secondary)] hover:text-[var(--y-primary)] hover:border-[var(--y-primary)] transition-colors duration-200"
            :title="s.label"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="s.icon" /></svg>
          </a>

          <!-- 微信公众平台（支持二维码图片） -->
          <button
            v-if="resolveWechat()"
            class="w-9 h-9 rounded-[var(--y-radius-sm)] border border-[var(--y-border)] flex items-center justify-center text-[var(--y-text-secondary)] hover:text-[var(--y-primary)] hover:border-[var(--y-primary)] transition-colors duration-200"
            title="微信公众平台"
            @click="onWechatClick"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
            </svg>
          </button>
        </div>
      </div>

      <div class="mt-8 pt-6 border-t border-[var(--y-border)] flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[var(--y-text-muted)]">
        <div>
          © {{ new Date().getFullYear() }} {{ site.settings.owner_name || site.settings.site_name || 'ycms2.0' }} · Powered by
          <span class="text-[var(--y-text-secondary)] font-medium">ycms2.0</span>
        </div>
        <a v-if="site.settings.site_icp" href="https://beian.miit.gov.cn" target="_blank" rel="noopener" class="hover:text-[var(--y-primary)] transition-colors duration-200">
          {{ site.settings.site_icp }}
        </a>
      </div>
    </div>

    <!-- 微信二维码弹窗 -->
    <BaseModal variant="flat" v-model="qrOpen" title="微信公众平台" width="max-w-xs">
      <div class="text-center">
        <div class="rounded-[var(--y-radius-sm)] overflow-hidden border border-[var(--y-border)] bg-[var(--y-surface-hover)] p-3 inline-block">
          <img :src="site.settings.social_wechat_qr" alt="微信二维码" class="w-56 h-56 object-contain" />
        </div>
        <p class="text-xs text-[var(--y-text-muted)] mt-4">扫码关注微信公众平台</p>
        <a
          v-if="site.settings.social_wechat"
          :href="site.settings.social_wechat"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-1.5 text-xs text-[var(--y-primary)] hover:underline mt-2"
        >
          或访问主页
          <icon-launch :size="12" />
        </a>
      </div>
    </BaseModal>
  </footer>
</template>

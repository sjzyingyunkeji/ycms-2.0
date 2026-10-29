<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


/**
 * 全局氛围覆盖层（全站 & 星盘页面共用）
 *  1. 胶片噪点：SVG feTurbulence 分形噪声，低透明度 + steps 抖动
 *  2. 径向暗角：四角压暗，突出中心内容
 *  3. 左右色彩偏移：左紫、右青（与 body 极光渐变呼应）
 */
</script>

<template>
  <div class="fixed inset-0 z-[5] pointer-events-none overflow-hidden" aria-hidden="true">
    <!-- 胶片噪点 -->
    <div class="grain absolute -inset-[10%] opacity-[var(--grain-opacity)] mix-blend-overlay animate-grain">
      <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
        <filter id="grain-filter">
          <feTurbulence type="fractalNoise" baseFrequency="0.75" numOctaves="2" stitchTiles="stitch" result="noise" />
          <feColorMatrix type="saturate" values="0" />
        </filter>
        <rect width="100%" height="100%" filter="url(#grain-filter)" />
      </svg>
    </div>

    <!-- 径向暗角 -->
    <div
      class="absolute inset-0"
      :style="{
        background: 'radial-gradient(ellipse 95% 85% at 50% 45%, transparent 52%, rgba(4, 6, 26, var(--vignette-strength)) 100%)',
      }"
    />

    <!-- 左右色彩偏移：左紫右青 -->
    <div
      class="absolute inset-0"
      :style="{
        background:
          'radial-gradient(ellipse 55% 70% at 0% 30%, rgba(111, 99, 173, 0.05), transparent 70%), radial-gradient(ellipse 55% 70% at 100% 70%, rgba(79, 138, 158, 0.045), transparent 70%)',
      }"
    />
  </div>
</template>

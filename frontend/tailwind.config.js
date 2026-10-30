/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 说明：
 *  - 本配置当前承载「内置主题」的设计 token（星空色板）。
 *  - 主题独立构建时，由主题仓库提供自己的 tailwind.config.js，Core 不代管。
 *  - content 覆盖 core / shared / site / admin 四层，确保类名不被裁剪。
 */

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './site/index.html',
    './star/index.html',
    './admin/index.html',
    './core/**/*.{vue,js,ts,jsx,tsx}',
    './shared/**/*.{vue,js,ts,jsx,tsx}',
    './site/**/*.{vue,js,ts,jsx,tsx}',
    './star/**/*.{vue,js,ts,jsx,tsx}',
    './admin/**/*.{vue,js,ts,jsx,tsx}',
    '../backend/src/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        // 深空色板（带灰调，避免纯黑假白）
        space: {
          950: '#04061a',
          900: '#080c26',
          850: '#0a0f2e',
          800: '#0d1234',
          700: '#151b45',
          600: '#1d2456',
        },
        // 星光色板 —— 混入灰调降低饱和度，模拟真实星色
        star: {
          glow: '#aeb8d9', // 正文亮星光（偏灰蓝）
          violet: '#8f83c9', // 主紫（降饱和）
          'violet-deep': '#6f63ad',
          cyan: '#6fa9bd', // 青（降饱和）
          'cyan-deep': '#4f8a9e',
          gold: '#c9a86a', // 暖金（置顶，降饱和）
          warm: '#f2e3c2', // 恒星暖核
          dim: '#5b6478', // 辅助文字（更暗）
        },
      },
      fontFamily: {
        sans: ['"PingFang SC"', '"HarmonyOS Sans SC"', '"Microsoft YaHei"', 'system-ui', 'sans-serif'],
        serif: ['"Noto Serif SC"', '"Songti SC"', '"SimSun"', 'serif'],
        mono: ['"JetBrains Mono"', '"Cascadia Code"', '"SF Mono"', 'Consolas', '"Courier New"', 'monospace'],
      },
      boxShadow: {
        glow: '0 0 24px rgba(143, 131, 201, 0.28)',
        'glow-violet': '0 0 18px rgba(143, 131, 201, 0.22)',
        'glow-warm': '0 0 18px rgba(242, 227, 194, 0.16)',
        card: '0 10px 36px rgba(4, 6, 26, 0.45)',
      },
      transitionTimingFunction: {
        // 缓动 token：transform 用 ease-out，透明度用 ease-in-out，星盘拖拽用阻尼
        'out-quart': 'cubic-bezier(0.22, 1, 0.36, 1)',
        'in-out-soft': 'cubic-bezier(0.42, 0, 0.58, 1)',
        damp: 'cubic-bezier(0.34, 1.24, 0.4, 1)',
      },
      keyframes: {
        twinkle: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.35' },
        },
        breathe: {
          '0%, 100%': { opacity: '0.55' },
          '50%': { opacity: '0.95' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-10px)' },
        },
        'fade-up': {
          '0%': { opacity: '0', transform: 'translateY(16px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'grain-shift': {
          '0%, 100%': { transform: 'translate(0, 0)' },
          '25%': { transform: 'translate(-2%, 3%)' },
          '50%': { transform: 'translate(3%, -2%)' },
          '75%': { transform: 'translate(-3%, -3%)' },
        },
      },
      animation: {
        twinkle: 'twinkle 3s ease-in-out infinite',
        breathe: 'breathe 4.5s ease-in-out infinite',
        float: 'float 6s ease-in-out infinite',
        'fade-up': 'fade-up 0.6s ease forwards',
        grain: 'grain-shift 1.2s steps(4) infinite',
      },
    },
  },
  plugins: [],
}

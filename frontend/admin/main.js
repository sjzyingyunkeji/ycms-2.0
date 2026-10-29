/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 后台入口（独立构建）
 * 构建命令：npm run build:admin → 输出 dist/admin/
 */

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import AdminApp from './App.vue'
import '../core/utils/brand.js'
import router from './router.js'
import '../shared/styles/ui.css'

const app = createApp(AdminApp)
app.use(createPinia())
app.use(router)
app.mount('#app')

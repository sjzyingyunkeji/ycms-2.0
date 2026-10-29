/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 后台路由（独立构建入口）
 *
 * 与前台完全解耦：修改前台 UI 不会影响本文件的编译产物。
 * 使用 hash 模式（/admin/#/posts），因此后台是纯静态目录，
 * 部署时无需任何 URL 重写规则。
 */

import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '../core/stores/auth.js'
import { registerRouter } from '../core/router/registry.js'

const routes = [
  {
    path: '/login',
    name: 'admin-login',
    component: () => import('./views/LoginView.vue'),
    meta: { title: '登录' },
  },
  {
    path: '/setup',
    name: 'admin-setup',
    component: () => import('./views/SetupView.vue'),
    meta: { title: '账号安全初始化' },
  },
  {
    path: '/',
    component: () => import('./components/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('./views/DashboardView.vue'), meta: { title: '仪表盘' } },
      { path: 'stats', name: 'admin-stats', component: () => import('./views/StatsView.vue'), meta: { title: '访问统计' } },
      { path: 'posts', name: 'admin-posts', component: () => import('./views/PostsView.vue'), meta: { title: '文章管理' } },
      { path: 'posts/new', name: 'post-new', component: () => import('./views/PostEditView.vue'), meta: { title: '写文章' } },
      { path: 'posts/:id/edit', name: 'post-edit', component: () => import('./views/PostEditView.vue'), meta: { title: '编辑文章' } },
      { path: 'categories', name: 'admin-categories', component: () => import('./views/CategoriesView.vue'), meta: { title: '分类管理' } },
      { path: 'tags', name: 'admin-tags', component: () => import('./views/TagsView.vue'), meta: { title: '标签管理' } },
      { path: 'pages', name: 'admin-pages', component: () => import('./views/PagesView.vue'), meta: { title: '页面管理' } },
      { path: 'pages/new', name: 'page-new', component: () => import('./views/PageEditView.vue'), meta: { title: '新建页面' } },
      { path: 'pages/:id/edit', name: 'page-edit', component: () => import('./views/PageEditView.vue'), meta: { title: '编辑页面' } },
      { path: 'comments', name: 'admin-comments', component: () => import('./views/CommentsView.vue'), meta: { title: '评论管理' } },
      { path: 'media', name: 'admin-media', component: () => import('./views/MediaView.vue'), meta: { title: '媒体库' } },
      { path: 'links', name: 'admin-links', component: () => import('./views/LinksView.vue'), meta: { title: '友链管理' } },
      { path: 'settings', name: 'admin-settings', component: () => import('./views/SettingsView.vue'), meta: { title: '站点设置' } },
      { path: 'update', name: 'admin-update', component: () => import('./views/UpdateView.vue'), meta: { title: '系统更新' } },
      { path: 'profile', name: 'admin-profile', component: () => import('./views/ProfileView.vue'), meta: { title: '个人资料' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.user) {
    // Cookie 会话：先静默检查，再决定是否跳登录页
    const user = await auth.checkSession()
    if (!user) {
      return { name: 'admin-login', query: { redirect: to.fullPath } }
    }
  }
  // 默认账号门禁：未完成初始化前，后台任何页面都跳到初始化页
  if (auth.user && auth.mustChange && to.name !== 'admin-setup') {
    return { name: 'admin-setup' }
  }
})

router.afterEach((to) => {
  const title = to.meta.title ? `${to.meta.title} · 后台` : '后台 · ycms2.0'
  document.title = title
})

export default registerRouter(router)

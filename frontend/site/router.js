/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 前台路由（独立构建入口）
 * 与后台完全解耦：修改后台 UI 不会影响本文件的编译产物
 */

import { createRouter, createWebHistory } from 'vue-router'
import { trackPage } from '../core/utils/track.js'
import { applySeo, absoluteUrl } from '../core/utils/seo.js'
import { registerRouter } from '../core/router/registry.js'

const routes = [
  {
    path: '/',
    component: () => import('./views/SiteLayout.vue'),
    children: [
      { path: '', name: 'home', component: () => import('./views/HomeView.vue'), meta: { title: '首页' } },
      { path: 'posts', name: 'posts', component: () => import('./views/PostListView.vue'), meta: { title: '文章' } },
      { path: 'posts/:id', name: 'post-detail', component: () => import('./views/PostDetailView.vue'), meta: { title: '阅读' } },
      { path: 'post/:slug', name: 'post-detail-slug', component: () => import('./views/PostDetailView.vue'), meta: { title: '阅读' } },
      { path: 'archive', name: 'archive', component: () => import('./views/ArchiveView.vue'), meta: { title: '归档' } },
      { path: 'links', name: 'links', component: () => import('./views/LinksView.vue'), meta: { title: '友链' } },
      { path: 'page/:slug', name: 'page', component: () => import('./views/PageView.vue'), meta: { title: '页面' } },
      { path: 'category/:id', name: 'category-posts', component: () => import('./views/PostListView.vue'), meta: { title: '分类' } },
      { path: 'tag/:id', name: 'tag-posts', component: () => import('./views/PostListView.vue'), meta: { title: '标签' } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./views/NotFoundView.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (to, from, savedPosition) => (savedPosition ? savedPosition : { top: 0 }),
})

router.afterEach((to) => {
  const siteTitle = 'ycms2.0'
  document.title = to.meta.title ? `${to.meta.title} · ${siteTitle}` : siteTitle

  // 访问统计上报（文章详情页由 PostDetailView 携带文章 ID 单独上报）
  const isPost = to.name === 'post-detail' || to.name === 'post-detail-slug'
  if (!isPost) {
    trackPage(to.path)
  }

  applySeo({
    title: document.title,
    url: absoluteUrl(to.path),
  })
})

export default registerRouter(router)

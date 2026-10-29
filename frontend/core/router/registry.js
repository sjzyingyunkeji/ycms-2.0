/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

/**
 * 路由注册表
 *
 * 前台（site）与后台（admin）是两个独立构建入口，各自拥有独立 router。
 * 共享的 api / stores 层通过本注册表拿到「当前运行的 router」，
 * 避免静态 import 造成两个入口互相耦合。
 */

export const routerRef = { current: null }

export function registerRouter(router) {
  routerRef.current = router
  return router
}

export function getRouter() {
  return routerRef.current
}

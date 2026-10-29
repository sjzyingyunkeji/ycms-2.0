/**
 * 发行版本常量（构建期条件编译）
 *
 * __YCMS_EDITION__ 由 vite.config.js 在构建时替换为字面量：
 *   oss → "开源产物"：不包含任何主题安装/切换 UI
 *   pro → "商业产物"：由 Pro 仓库提供 admin/pro/ 下的页面并在此挂载
 *
 * 约束：不要在运行期通过接口探测来“隐藏开关”，
 *      未命中分支必须能被 Rollup 静态消除（产物中无对应 chunk）。
 */

export const EDITION = __YCMS_EDITION__
export const IS_PRO = EDITION === 'pro'

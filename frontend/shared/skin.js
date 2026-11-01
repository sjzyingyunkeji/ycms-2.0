/**
 * 后台皮肤标识（构建期常量）
 *
 * 由 vite.config.js 依据仓库里实际存在的主题目录替换：
 *   - 主仓库（只含 site 主题）→ 'default'，后台使用通用扁平 UI
 *   - 星空仓库（只含 star 主题）→ 'star'，后台使用星空皮肤
 *
 * 皮肤差异集中在 shared/styles/admin-star.css 的 token 覆写，
 * 后台组件与视图保持同一份源码，避免两套后台分叉维护。
 */

export const ADMIN_SKIN = __YCMS_ADMIN_SKIN__
export const IS_STAR_ADMIN = ADMIN_SKIN === 'star'

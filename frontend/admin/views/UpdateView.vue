<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */

import { computed, onMounted, ref } from 'vue'
import { updateApi } from '../../core/api/index.js'
import { showToast } from '../../core/utils/toast.js'
import LoadingSpinner from '../../shared/components/LoadingSpinner.vue'
import BaseModal from '../../shared/components/BaseModal.vue'

/** 升级流水线（与后端 UpdateController::STEPS 保持一致） */
const STEPS = [
  { key: 'download', label: '下载升级包', desc: '多镜像下载，校验 sha256 与签名' },
  { key: 'extract', label: '解压校验', desc: '检查包结构并拦截非法路径' },
  { key: 'backup', label: '备份当前版本', desc: '代码打包 + 数据库快照' },
  { key: 'apply', label: '覆盖文件', desc: '仅更新 backend / public 代码' },
  { key: 'migrate', label: '数据库迁移', desc: '执行 schema.sql 与增量迁移' },
  { key: 'finalize', label: '收尾', desc: '重建静态页、写入版本号' },
]

const info = ref(null)
const loading = ref(true)
const checking = ref(false)
const upgrading = ref(false)
const percent = ref(0)
const stepState = ref({})
const logs = ref([])

const rollbackTarget = ref(null)
const rollbackWithDb = ref(false)
const rollingBack = ref(false)

const fileInput = ref(null)
const uploading = ref(false)
const uploadPercent = ref(0)

/** CPU 密集步骤执行中的提示语 */
const runningLabel = computed(() => {
  const running = STEPS.find((s) => stepState.value[s.key] === 'running')
  return running ? running.label : ''
})

const canUpgrade = computed(() => {
  if (!info.value || upgrading.value || uploading.value) return false
  if (info.value.blocked) return false
  return Boolean(info.value.has_update || info.value.offline)
})

const stepsForRun = computed(() =>
  info.value?.offline ? STEPS.filter((s) => s.key !== 'download') : STEPS
)

function pushLog(message) {
  const time = new Date().toLocaleTimeString('zh-CN', { hour12: false })
  logs.value.push({ time, message })
}

async function load() {
  loading.value = true
  try {
    info.value = await updateApi.status()
    if (info.value.progress?.percent) {
      percent.value = info.value.progress.percent
    }
  } finally {
    loading.value = false
  }
}

/** 每日懒触发：距上次检查超过 24h 时自动检查一次 */
async function lazyCheck() {
  if (!info.value?.needs_check || checking.value) return
  await check(false, true)
}

async function check(force = false, silent = false) {
  if (checking.value || upgrading.value) return
  checking.value = true
  try {
    const summary = await updateApi.check(force)
    info.value = { ...info.value, ...summary }
    if (!silent) {
      showToast(summary.has_update ? `发现新版本 ${summary.latest}` : '当前已是最新版本', summary.has_update ? 'success' : 'info')
    }
  } catch {
    // 拦截器已提示
  } finally {
    checking.value = false
  }
}

async function startUpgrade() {
  if (!canUpgrade.value) return
  upgrading.value = true
  percent.value = 0
  logs.value = []
  const state = {}
  stepsForRun.value.forEach((s) => {
    state[s.key] = 'pending'
  })
  stepState.value = state

  try {
    for (const step of stepsForRun.value) {
      stepState.value[step.key] = 'running'
      pushLog(`开始：${step.label}`)
      const res = await updateApi.run(step.key)
      stepState.value[step.key] = 'done'
      percent.value = Number(res?.percent ?? percent.value)
      pushLog(res?.message || `${step.label}完成`)
    }
    showToast('升级完成，已切换到新版本', 'success')
    await load()
    await check(true, true)
  } catch (error) {
    const message = error?.response?.data?.message || error?.message || '升级中断'
    pushLog(`中断：${message}`)
    showToast('升级中断，可在备份列表回滚', 'error')
  } finally {
    upgrading.value = false
    stepState.value = Object.fromEntries(
      Object.entries(stepState.value).map(([k, v]) => [k, v === 'running' ? 'failed' : v])
    )
  }
}

function askRollback(backup) {
  rollbackTarget.value = backup
  rollbackWithDb.value = false
}

async function doRollback() {
  if (!rollbackTarget.value) return
  rollingBack.value = true
  try {
    const res = await updateApi.rollback(rollbackTarget.value.name, rollbackWithDb.value)
    showToast(res?.message || '回滚完成', 'success')
    rollbackTarget.value = null
    await load()
    await check(false, true)
  } catch {
    // 拦截器已提示
  } finally {
    rollingBack.value = false
  }
}

async function handleUpload(event) {
  const file = event.target.files?.[0]
  if (!file) return
  if (!file.name.toLowerCase().endsWith('.zip')) {
    showToast('请选择 zip 格式的升级包', 'warning')
    return
  }
  const form = new FormData()
  form.append('package', file)
  uploading.value = true
  uploadPercent.value = 0
  try {
    await updateApi.upload(form, (evt) => {
      if (evt.total) uploadPercent.value = Math.round((evt.loaded / evt.total) * 100)
    })
    showToast('离线包已导入，可以开始升级', 'success')
    await load()
  } catch {
    // 拦截器已提示
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function fmtSize(bytes) {
  if (!bytes) return '—'
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(2)} MB`
  if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${bytes} B`
}

function fmtTime(ts) {
  if (!ts) return ''
  return new Date(ts * 1000).toLocaleString('zh-CN', { hour12: false })
}

onMounted(async () => {
  await load()
  lazyCheck()
})
</script>

<template>
  <div class="page-enter">
    <!-- 顶部：版本与检查 -->
    <div class="y-card p-6 mb-5">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
        <div>
          <h3 class="text-xl text-[var(--y-text)] mb-1">系统更新</h3>
          <p class="text-sm text-[var(--y-text-muted)]">每日自动检查一次，也可手动检查；升级按步骤执行，中断可回滚</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button class="y-btn y-btn-secondary !py-2" :disabled="checking || upgrading" @click="check(true)">
            <svg v-if="checking" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.22-8.56" stroke-linecap="round" /></svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6 M1 20v-6h6 M3.51 9a9 9 0 0114.85-3.36L23 10 M1 14l4.64 4.36A9 9 0 0020.49 15" /></svg>
            {{ checking ? '检查中…' : '检查更新' }}
          </button>
        </div>
      </div>

      <LoadingSpinner variant="flat" v-if="loading" text="正在读取版本信息…" />

      <template v-else-if="info">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
          <div class="y-stat p-5">
            <div class="text-[10px] text-[var(--y-text-muted)] tracking-widest mb-2">当前版本</div>
            <div class="text-2xl font-bold text-[var(--y-text)]">{{ info.current }}</div>
            <div class="text-xs text-[var(--y-text-muted)] mt-1">渠道 {{ info.channel || 'stable' }}</div>
          </div>
          <div class="y-stat p-5">
            <div class="text-[10px] text-[var(--y-text-muted)] tracking-widest mb-2">最新版本</div>
            <div class="text-2xl font-bold" :class="info.has_update ? 'text-[var(--y-text)]' : 'text-[var(--y-text)]'">{{ info.latest }}</div>
            <div class="text-xs mt-1" :class="info.has_update ? 'text-[var(--y-text)]/80' : 'text-[var(--y-text-muted)]'">
              {{ info.has_update ? '有可用更新' : '已是最新' }}
            </div>
          </div>
          <div class="y-stat p-5">
            <div class="text-[10px] text-[var(--y-text-muted)] tracking-widest mb-2">上次检查</div>
            <div class="text-sm text-[var(--y-text-secondary)] mt-2">{{ info.last_checked_text }}</div>
            <div class="text-xs mt-1" :class="info.last_result === 'fail' ? 'text-[var(--y-danger)]/80' : 'text-[var(--y-text-muted)]'">
              {{ info.last_result === 'fail' ? '检查失败' : (info.offline ? '离线包模式' : '检查正常') }}
            </div>
          </div>
          <div class="y-stat p-5">
            <div class="text-[10px] text-[var(--y-text-muted)] tracking-widest mb-2">运行环境</div>
            <div class="text-sm text-[var(--y-text-secondary)] mt-2">PHP {{ info.php }}</div>
            <div class="text-xs mt-1" :class="info.zip ? 'text-[var(--y-text-muted)]' : 'text-[var(--y-danger)]/80'">
              {{ info.zip ? 'ZipArchive 可用' : '缺少 ZipArchive 扩展' }}
            </div>
          </div>
        </div>

        <!-- 环境提示 -->
        <div class="mt-5 space-y-2">
          <div v-if="!info.zip" class="flex items-start gap-2 text-xs text-[var(--y-danger)] bg-[var(--y-danger-light)] border border-rose-400/30 rounded-xl px-4 py-3">
            <span class="mt-0.5">●</span>当前 PHP 未启用 ZipArchive 扩展，无法解压升级包。
          </div>
          <div v-if="!info.storage_writable" class="flex items-start gap-2 text-xs text-[var(--y-danger)] bg-[var(--y-danger-light)] border border-rose-400/30 rounded-xl px-4 py-3">
            <span class="mt-0.5">●</span>backend/storage 不可写，无法保存状态与备份。
          </div>
          <div v-if="info.last_error" class="text-xs text-[var(--y-warning)]/80 bg-[var(--y-warning-light)] border border-amber-300/25 rounded-xl px-4 py-3">
            {{ info.last_error }}
          </div>
        </div>
      </template>
    </div>

    <!-- 更新详情 / 进度 -->
    <div v-if="info && (info.has_update || info.offline || upgrading || logs.length)" class="y-card p-6 mb-5">
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-5">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h4 class="text-base text-[var(--y-text)]">
              {{ info.offline ? '离线升级包' : `升级到 ${info.latest}` }}
            </h4>
            <span v-if="info.breaking" class="y-tag y-tag-top">重要变更</span>
            <span v-if="info.published_at" class="text-[11px] text-[var(--y-text-muted)]">{{ info.published_at }}</span>
          </div>
          <p class="text-xs text-[var(--y-text-muted)]">
            <template v-if="info.offline">已上传离线包，将跳过下载步骤</template>
            <template v-else>升级前会自动备份代码与数据库，失败可回滚</template>
          </p>
        </div>
        <button class="y-btn y-btn-primary !py-2.5" :disabled="!canUpgrade" :title="info.blocked ? info.block_reason : ''" @click="startUpgrade">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12 M7 10l5 5 5-5 M5 21h14" /></svg>
          {{ upgrading ? '升级中…' : '立即升级' }}
        </button>
      </div>

      <!-- 跨代升级拦截 -->
      <div v-if="info.blocked" class="mb-5 text-xs text-[var(--y-danger)] bg-[var(--y-danger-light)] border border-rose-400/30 rounded-xl px-4 py-3 leading-relaxed">
        {{ info.block_reason }}。请先安装中间版本，再升级到 {{ info.latest }}。
      </div>

      <!-- 更新日志 -->
      <div v-if="info.changelog?.length" class="mb-5">
        <div class="text-[10px] text-[var(--y-text-muted)] tracking-widest mb-2">更新日志</div>
        <ul class="space-y-1.5 text-sm text-[var(--y-text-secondary)]">
          <li v-for="(item, i) in info.changelog" :key="i" class="flex gap-2">
            <span class="text-[var(--y-primary)] mt-0.5">✦</span>{{ item }}
          </li>
        </ul>
      </div>

      <!-- 进度 -->
      <div v-if="upgrading || logs.length" class="border-t border-[var(--y-border)] pt-5">
        <div class="flex items-center justify-between text-xs mb-2">
          <span class="text-[var(--y-text-secondary)]">{{ runningLabel ? `正在执行：${runningLabel}` : '升级流程' }}</span>
          <span class="font-mono text-[var(--y-primary)]">{{ percent }}%</span>
        </div>
        <div class="h-1.5 rounded-full bg-[var(--y-surface-hover)] overflow-hidden mb-5">
          <div class="h-full rounded-full transition-all duration-500" :style="{ width: percent + '%' }" />
        </div>

        <div class="grid sm:grid-cols-2 gap-2.5 mb-5">
          <div
            v-for="step in stepsForRun"
            :key="step.key"
            class="flex items-start gap-2.5 rounded-xl border px-3.5 py-2.5 transition-colors"
            :class="{ 'border-[var(--y-primary)] bg-[var(--y-primary-light)]': stepState[step.key] === 'running', 'border-emerald-400/25 bg-[var(--y-success)]/[0.06]': stepState[step.key] === 'done', 'border-rose-400/30 bg-[var(--y-danger)]/[0.06]': stepState[step.key] === 'failed', 'border-[var(--y-border)] bg-[var(--y-surface-hover)]': !stepState[step.key] || stepState[step.key] === 'pending', }"
          >
            <span class="mt-0.5 flex-shrink-0">
              <svg v-if="stepState[step.key] === 'done'" class="w-4 h-4 text-[var(--y-success)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
              <svg v-else-if="stepState[step.key] === 'running'" class="w-4 h-4 text-[var(--y-primary)] animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.22-8.56" stroke-linecap="round" /></svg>
              <svg v-else-if="stepState[step.key] === 'failed'" class="w-4 h-4 text-[var(--y-danger)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
              <span v-else class="block w-4 h-4 rounded-full border border-[var(--y-border-strong)]" />
            </span>
            <div class="min-w-0">
              <div class="text-xs text-[var(--y-text)]">{{ step.label }}</div>
              <div class="text-[11px] text-[var(--y-text-muted)] mt-0.5">{{ step.desc }}</div>
            </div>
          </div>
        </div>

        <div v-if="logs.length" class="rounded-xl bg-[var(--y-surface)] border border-[var(--y-border)] p-4 max-h-52 overflow-y-auto font-mono text-[11px] leading-relaxed text-[var(--y-text-secondary)]">
          <div v-for="(log, i) in logs" :key="i">
            <span class="text-[var(--y-text-muted)]">{{ log.time }}</span>
            <span class="ml-2">{{ log.message }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
      <!-- 离线包 -->
      <div class="y-card p-6">
        <h4 class="text-base text-[var(--y-text)] mb-1">离线升级包</h4>
        <p class="text-xs text-[var(--y-text-muted)] mb-4">
          服务器无法访问 GitHub 时，在本地上传发布方提供的 zip 包（最大 {{ fmtSize(info?.max_upload) }}）
        </p>

        <input ref="fileInput" type="file" accept=".zip" class="hidden" @change="handleUpload" />
        <button class="y-btn y-btn-secondary w-full !py-3" :disabled="uploading || upgrading" @click="fileInput?.click()">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4 M17 8l-5-5-5 5 M12 3v12" /></svg>
          {{ uploading ? `上传中 ${uploadPercent}%` : '选择离线包并上传' }}
        </button>
        <div v-if="uploading" class="mt-3 h-1.5 rounded-full bg-[var(--y-surface-hover)] overflow-hidden">
          <div class="h-full rounded-full transition-all" :style="{ width: uploadPercent + '%' }" />
        </div>
        <p v-if="info?.offline" class="text-[11px] text-[var(--y-success)]/80 mt-3">离线包已就绪，点击上方「立即升级」开始</p>
        <p class="text-[11px] text-[var(--y-text-muted)] mt-3">若上传被拒绝，请调大 PHP 的 upload_max_filesize 与 post_max_size</p>
      </div>

      <!-- 备份与回滚 -->
      <div class="y-card p-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h4 class="text-base text-[var(--y-text)]">版本备份</h4>
            <p class="text-xs text-[var(--y-text-muted)] mt-1">保留最近 3 份，回滚会覆盖代码文件</p>
          </div>
        </div>

        <div v-if="info?.backups?.length" class="space-y-2.5">
          <div
            v-for="backup in info.backups"
            :key="backup.name"
            class="flex items-center gap-3 rounded-xl border border-[var(--y-border)] px-3.5 py-2.5 hover:border-[var(--y-border)] transition-colors"
          >
            <div class="min-w-0 flex-1">
              <div class="text-xs text-[var(--y-text)] truncate font-mono" :title="backup.name">{{ backup.name }}</div>
              <div class="text-[11px] text-[var(--y-text-muted)] mt-0.5">
                {{ backup.time }} · {{ fmtSize(backup.size) }}
                <span v-if="backup.has_db" class="text-[var(--y-success)]/70">· 含数据库</span>
              </div>
            </div>
            <button class="text-xs text-[var(--y-primary)] hover:text-[var(--y-text)] px-2.5 py-1 rounded-lg hover:bg-[var(--y-surface-hover)]" :disabled="rollingBack || upgrading" @click="askRollback(backup)">
              回滚
            </button>
          </div>
        </div>
        <p v-else class="text-xs text-[var(--y-text-muted)] py-6 text-center">暂无备份，首次升级时会自动创建</p>
      </div>
    </div>

    <!-- 回滚确认 -->
    <BaseModal variant="flat" :model-value="Boolean(rollbackTarget)" title="确认回滚" @update:model-value="rollbackTarget = null">
      <div class="space-y-4">
        <p class="text-sm text-[var(--y-text-secondary)]">
          将把代码文件恢复为备份
          <code class="font-mono text-[var(--y-primary)]">{{ rollbackTarget?.name }}</code>
          的版本，当前版本的修改会被覆盖。
        </p>
        <label v-if="rollbackTarget?.has_db" class="flex items-start gap-2.5 text-xs text-[var(--y-text-secondary)] cursor-pointer">
          <input v-model="rollbackWithDb" type="checkbox" class="mt-0.5 accent-[#8f83c9]" />
          <span>同时恢复当时的数据库快照（仅当升级后数据库结构异常时勾选）</span>
        </label>
        <div class="flex justify-end gap-3 pt-2">
          <button class="y-btn y-btn-secondary !py-2" @click="rollbackTarget = null">取消</button>
          <button class="y-btn y-btn-danger !py-2" :disabled="rollingBack" @click="doRollback">
            {{ rollingBack ? '回滚中…' : '确认回滚' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

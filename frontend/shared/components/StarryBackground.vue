<script setup>
/**
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


import { onMounted, onUnmounted, ref } from 'vue'

/**
 * 三层星空粒子系统：
 *  L1 远景静态星 —— 数量多、体极小、低透明度、不闪烁（深空底色）
 *  L2 中层呼吸星 —— 中等体量，正弦慢呼吸（相位随机）
 *  L3 暖芯亮恒星 —— 少量大星，径向渐变带极淡暖黄星核（真实恒星色温）
 *  + 偶发流星（向右下拖尾）
 *  + 分层视差（越近的层随鼠标偏移越大，幅度刻意保持微弱）
 */
const canvasRef = ref(null)
let ctx = null
let animationId = null
let farStars = [] // L1
let midStars = [] // L2
let brightStars = [] // L3
let shootingStars = []
let mouse = { x: 0, y: 0 }
let targetMouse = { x: 0, y: 0 }
let width = 0
let height = 0
let dpr = 1
let shootingCooldown = 0

function randomBetween(min, max) {
  return Math.random() * (max - min) + min
}

/* ---------- 三层星星生成 ---------- */
function createLayers() {
  farStars = []
  midStars = []
  brightStars = []

  const area = width * height

  // L1 远景静态星：极小极暗，蓝灰调，不闪烁
  const farCount = Math.max(140, Math.floor(area * 0.00028))
  for (let i = 0; i < farCount; i++) {
    farStars.push({
      x: randomBetween(0, width),
      y: randomBetween(0, height),
      radius: randomBetween(0.3, 0.9),
      depth: randomBetween(0.15, 0.35),
      alpha: randomBetween(0.12, 0.3),
    })
  }

  // L2 中层呼吸星：中等亮度，呼吸节奏相位随机
  const midCount = Math.max(60, Math.floor(area * 0.00012))
  for (let i = 0; i < midCount; i++) {
    const warm = Math.random() < 0.18 // 少量偏暖
    midStars.push({
      x: randomBetween(0, width),
      y: randomBetween(0, height),
      radius: randomBetween(0.7, 1.5),
      depth: randomBetween(0.45, 0.7),
      baseAlpha: randomBetween(0.35, 0.7),
      twinkleSpeed: randomBetween(0.5, 1.4),
      twinkleOffset: randomBetween(0, Math.PI * 2),
      color: warm ? [242, 227, 194] : [200, 212, 236],
    })
  }

  // L3 暖芯亮恒星：固定少量，带光晕
  const brightCount = Math.max(5, Math.min(12, Math.floor(area / 220000)))
  for (let i = 0; i < brightCount; i++) {
    const warm = Math.random() < 0.5 // 半数暖黄星核
    brightStars.push({
      x: randomBetween(width * 0.08, width * 0.92),
      y: randomBetween(0, height * 0.75),
      radius: randomBetween(1.4, 2.2),
      depth: randomBetween(0.85, 1),
      baseAlpha: randomBetween(0.75, 0.95),
      twinkleSpeed: randomBetween(0.35, 0.8),
      twinkleOffset: randomBetween(0, Math.PI * 2),
      // 暖芯：中心暖黄 → 边缘蓝白
      core: warm ? [255, 244, 218] : [226, 234, 250],
      halo: warm ? [242, 227, 194] : [174, 184, 217],
    })
  }
}

/* ---------- 绘制 ---------- */
function drawFarStar(star) {
  const px = star.x + mouse.x * star.depth * 14
  const py = star.y + mouse.y * star.depth * 14
  ctx.fillStyle = `rgba(190, 200, 222, ${star.alpha})`
  ctx.beginPath()
  ctx.arc(px, py, star.radius, 0, Math.PI * 2)
  ctx.fill()
}

function drawMidStar(star, time) {
  const breathe = 0.6 + 0.4 * Math.sin(time * star.twinkleSpeed + star.twinkleOffset)
  const alpha = star.baseAlpha * breathe
  const px = star.x + mouse.x * star.depth * 20
  const py = star.y + mouse.y * star.depth * 20
  const [r, g, b] = star.color
  ctx.fillStyle = `rgba(${r}, ${g}, ${b}, ${alpha})`
  ctx.beginPath()
  ctx.arc(px, py, star.radius, 0, Math.PI * 2)
  ctx.fill()
}

function drawBrightStar(star, time) {
  const twinkle = 0.75 + 0.25 * Math.sin(time * star.twinkleSpeed + star.twinkleOffset)
  const alpha = star.baseAlpha * twinkle
  const px = star.x + mouse.x * star.depth * 26
  const py = star.y + mouse.y * star.depth * 26

  // 外光晕
  const haloR = star.radius * 7
  const halo = ctx.createRadialGradient(px, py, 0, px, py, haloR)
  const [hr, hg, hb] = star.halo
  halo.addColorStop(0, `rgba(${hr}, ${hg}, ${hb}, ${alpha * 0.32})`)
  halo.addColorStop(0.4, `rgba(${hr}, ${hg}, ${hb}, ${alpha * 0.1})`)
  halo.addColorStop(1, `rgba(${hr}, ${hg}, ${hb}, 0)`)
  ctx.fillStyle = halo
  ctx.beginPath()
  ctx.arc(px, py, haloR, 0, Math.PI * 2)
  ctx.fill()

  // 星核：暖黄中心 → 边缘淡出
  const coreR = star.radius * 2.4
  const core = ctx.createRadialGradient(px, py, 0, px, py, coreR)
  const [cr, cg, cb] = star.core
  core.addColorStop(0, `rgba(${cr}, ${cg}, ${cb}, ${alpha})`)
  core.addColorStop(0.55, `rgba(${cr}, ${cg}, ${cb}, ${alpha * 0.55})`)
  core.addColorStop(1, `rgba(${cr}, ${cg}, ${cb}, 0)`)
  ctx.fillStyle = core
  ctx.beginPath()
  ctx.arc(px, py, coreR, 0, Math.PI * 2)
  ctx.fill()

  // 中心亮点
  ctx.fillStyle = `rgba(255, 252, 244, ${alpha})`
  ctx.beginPath()
  ctx.arc(px, py, star.radius * 0.55, 0, Math.PI * 2)
  ctx.fill()
}

function createShootingStar() {
  const fromLeft = Math.random() < 0.5
  const startX = fromLeft ? randomBetween(width * 0.05, width * 0.4) : randomBetween(width * 0.6, width * 0.95)
  const startY = randomBetween(0, height * 0.35)
  const angle = fromLeft ? randomBetween(Math.PI * 0.72, Math.PI * 0.85) : randomBetween(Math.PI * 0.95, Math.PI * 1.08)
  const speed = randomBetween(10, 17)
  return {
    x: startX,
    y: startY,
    vx: Math.cos(angle) * speed,
    vy: Math.abs(Math.sin(angle)) * speed,
    length: randomBetween(90, 170),
    life: 1,
    decay: randomBetween(0.01, 0.018),
  }
}

function drawShootingStar(s) {
  const tailX = s.x - s.vx * (s.length / 12)
  const tailY = s.y - s.vy * (s.length / 12)
  const gradient = ctx.createLinearGradient(s.x, s.y, tailX, tailY)
  gradient.addColorStop(0, `rgba(235, 240, 252, ${s.life * 0.9})`)
  gradient.addColorStop(0.25, `rgba(200, 212, 236, ${s.life * 0.45})`)
  gradient.addColorStop(1, 'rgba(200, 212, 236, 0)')

  ctx.strokeStyle = gradient
  ctx.lineWidth = 1.6
  ctx.lineCap = 'round'
  ctx.beginPath()
  ctx.moveTo(s.x, s.y)
  ctx.lineTo(tailX, tailY)
  ctx.stroke()

  ctx.fillStyle = `rgba(255, 252, 244, ${s.life * 0.9})`
  ctx.beginPath()
  ctx.arc(s.x, s.y, 1.5, 0, Math.PI * 2)
  ctx.fill()
}

function animate(time) {
  const t = time / 1000
  ctx.clearRect(0, 0, width, height)

  // 鼠标平滑跟随（微弱视差）
  mouse.x += (targetMouse.x - mouse.x) * 0.035
  mouse.y += (targetMouse.y - mouse.y) * 0.035

  // L1 远景静态星
  for (const star of farStars) drawFarStar(star)
  // L2 中层呼吸星
  for (const star of midStars) drawMidStar(star, t)
  // L3 暖芯亮恒星
  for (const star of brightStars) drawBrightStar(star, t)

  // 流星：冷却后随机出现，同屏最多 1 颗
  shootingCooldown -= 1
  if (shootingCooldown <= 0 && shootingStars.length === 0 && Math.random() < 0.006) {
    shootingStars.push(createShootingStar())
    shootingCooldown = randomBetween(600, 1400)
  }

  for (let i = shootingStars.length - 1; i >= 0; i--) {
    const s = shootingStars[i]
    s.x += s.vx
    s.y += s.vy
    s.life -= s.decay
    if (s.life <= 0 || s.x > width + 220 || s.y > height + 220) {
      shootingStars.splice(i, 1)
      continue
    }
    drawShootingStar(s)
  }

  animationId = requestAnimationFrame(animate)
}

/* ---------- 事件 ---------- */
function handleResize() {
  dpr = Math.min(window.devicePixelRatio || 1, 2)
  width = window.innerWidth
  height = window.innerHeight
  canvasRef.value.width = width * dpr
  canvasRef.value.height = height * dpr
  canvasRef.value.style.width = `${width}px`
  canvasRef.value.style.height = `${height}px`
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  createLayers()
}

function handleMouseMove(e) {
  targetMouse.x = (e.clientX / window.innerWidth) * 2 - 1
  targetMouse.y = (e.clientY / window.innerHeight) * 2 - 1
}

function handleTouchMove(e) {
  if (e.touches.length > 0) {
    targetMouse.x = (e.touches[0].clientX / window.innerWidth) * 2 - 1
    targetMouse.y = (e.touches[0].clientY / window.innerHeight) * 2 - 1
  }
}

onMounted(() => {
  ctx = canvasRef.value.getContext('2d')
  handleResize()
  window.addEventListener('resize', handleResize)
  window.addEventListener('mousemove', handleMouseMove)
  window.addEventListener('touchmove', handleTouchMove, { passive: true })
  animationId = requestAnimationFrame(animate)
})

onUnmounted(() => {
  cancelAnimationFrame(animationId)
  window.removeEventListener('resize', handleResize)
  window.removeEventListener('mousemove', handleMouseMove)
  window.removeEventListener('touchmove', handleTouchMove)
})
</script>

<template>
  <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
    <canvas ref="canvasRef" class="absolute inset-0" />
  </div>
</template>

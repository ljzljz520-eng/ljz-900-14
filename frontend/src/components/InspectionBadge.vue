<template>
  <span class="ibadge" :class="[`ibadge--${theme}`, `ibadge--${size}`]">
    <span class="ibadge__name" :title="displayName">{{ displayName }}</span>
    <span class="ibadge__score" :title="`扣 ${displayScore} 分`">
      <span class="ibadge__minus">-</span>{{ displayScore }}<span class="ibadge__unit">分</span>
    </span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: { type: [String, Number], default: '' },
  score: { type: [String, Number, null], default: null },
  // light: 浅色卡片背景（管理员/汇总页）；dark: 深色背景（员工整改页）
  theme: { type: String, default: 'light' },
  size: { type: String, default: 'md' },
})

const displayName = computed(() => {
  const n = props.name === null || props.name === undefined ? '' : String(props.name).trim()
  return n || '未命名检查项'
})

const displayScore = computed(() => {
  const raw = props.score
  if (raw === null || raw === undefined || String(raw).trim() === '') return '—'
  const num = Number(raw)
  if (Number.isNaN(num)) return '—'
  // 整数不带小数点，小数最多保留 2 位并去掉多余的 0
  return Number.isInteger(num) ? String(num) : String(Math.round(num * 100) / 100)
})
</script>

<style scoped>
.ibadge {
  display: inline-flex;
  align-items: stretch;
  border-radius: 8px;
  overflow: hidden;
  line-height: 1;
  vertical-align: middle;
  flex-shrink: 0;
}

/* 名称段：低调中性色，不抢扣分的视觉 */
.ibadge__name {
  display: inline-flex;
  align-items: center;
  max-width: 220px;
  padding: 0 10px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* 扣分段：唯一的强色，红底白字，一眼可见 */
.ibadge__score {
  display: inline-flex;
  align-items: baseline;
  gap: 1px;
  background: #dc2626;
  color: #fff;
  padding: 0 9px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.ibadge__minus {
  font-weight: 800;
  margin-right: 1px;
}

.ibadge__unit {
  font-size: 0.72em;
  font-weight: 600;
  margin-left: 2px;
}

/* 主题 */
.ibadge--light .ibadge__name {
  background: #f1f5f9;
  color: #334155;
  box-shadow: inset 0 0 0 1px #e2e8f0;
}

.ibadge--dark .ibadge__name {
  background: rgba(148, 163, 184, 0.18);
  color: #e2e8f0;
}

/* 尺寸 */
.ibadge--sm {
  font-size: 12px;
  border-radius: 6px;
}
.ibadge--sm .ibadge__name {
  padding: 0 8px;
}
.ibadge--sm .ibadge__score {
  padding: 0 7px;
}

.ibadge--md {
  font-size: 13px;
}
.ibadge--md .ibadge__name,
.ibadge--md .ibadge__score {
  min-height: 26px;
}

.ibadge--lg {
  font-size: 15px;
  border-radius: 10px;
}
.ibadge--lg .ibadge__name,
.ibadge--lg .ibadge__score {
  min-height: 32px;
}
.ibadge--lg .ibadge__name {
  padding: 0 14px;
}
.ibadge--lg .ibadge__score {
  padding: 0 12px;
}
</style>

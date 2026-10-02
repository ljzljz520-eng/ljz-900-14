<template>
  <span class="insp-badge" :class="[`insp-badge--${variant}`, `insp-badge--${size}`]" :title="title">
    <span class="insp-badge__name">{{ displayName }}</span>
    <span class="insp-badge__score">
      <span class="insp-badge__minus">-</span>{{ displayScore }}<span class="insp-badge__unit">分</span>
    </span>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { formatScore } from '@/utils/score'

const props = defineProps({
  // 检查项名称
  name: { type: [String, Number], default: '' },
  // 扣分值（数字 / 数字字符串均可）
  score: { type: [String, Number], default: 0 },
  // light：浅色背景（管理页 / 汇总页）；dark：深色背景（员工整改页）
  variant: { type: String, default: 'light' },
  // default：常规尺寸；small：紧凑（图片角标等）
  size: { type: String, default: 'default' },
})

const displayName = computed(() => {
  const n = String(props.name ?? '').trim()
  return n || '未命名检查项'
})
const displayScore = computed(() => {
  const raw = props.score
  if (raw === null || raw === undefined || String(raw).trim() === '') return '0'
  const n = Number(raw)
  // 输入中的非数字（如 "3."）原样展示，方便管理员核对
  return Number.isFinite(n) ? formatScore(n) : String(raw)
})
const title = computed(() => `${displayName.value}，扣 ${displayScore.value} 分`)
</script>

<style scoped>
/* 统一徽章：检查项名称（中性色）+ 扣分值（醒目的红），全系统共用同一外观 */
.insp-badge {
  display: inline-flex;
  align-items: stretch;
  border-radius: 8px;
  overflow: hidden;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  vertical-align: middle;
  flex-shrink: 0;
  max-width: 100%;
}

.insp-badge__name {
  display: inline-flex;
  align-items: center;
  padding: 0 10px;
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.insp-badge__score {
  display: inline-flex;
  align-items: baseline;
  gap: 1px;
  padding: 0 10px;
  font-size: 14px;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.insp-badge__minus {
  font-weight: 800;
  margin-right: 1px;
}

.insp-badge__unit {
  font-size: 11px;
  font-weight: 600;
  margin-left: 2px;
}

/* 紧凑尺寸 */
.insp-badge--small {
  border-radius: 6px;
}
.insp-badge--small .insp-badge__name {
  font-size: 12px;
  padding: 0 8px;
}
.insp-badge--small .insp-badge__score {
  font-size: 12px;
  padding: 0 8px;
}
.insp-badge--small .insp-badge__unit {
  font-size: 10px;
}

/* 浅色背景：名称 = 石板灰，扣分 = 实心红块（唯一强色，一眼可见） */
.insp-badge--light .insp-badge__name {
  background: #eef2f7;
  color: #334155;
}
.insp-badge--light .insp-badge__score {
  background: #dc2626;
  color: #fff;
}
.insp-badge--light .insp-badge__unit {
  color: rgba(255, 255, 255, 0.85);
}

/* 深色背景：名称 = 半透明白，扣分 = 实心红块，保持一致 */
.insp-badge--dark .insp-badge__name {
  background: rgba(148, 163, 184, 0.22);
  color: #e2e8f0;
}
.insp-badge--dark .insp-badge__score {
  background: #ef4444;
  color: #fff;
}
.insp-badge--dark .insp-badge__unit {
  color: rgba(255, 255, 255, 0.85);
}
</style>

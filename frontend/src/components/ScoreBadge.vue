<template>
  <!-- 检查项徽章：名称 + 扣分，管理员/员工整改/老板汇总三端共用同一组件 -->
  <span class="score-badge" :class="`score-badge--${size}`" :title="`${name} 扣 ${score} 分`">
    <span class="score-badge__name">{{ name || '未命名检查项' }}</span>
    <span class="score-badge__score">{{ scoreText }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: { type: String, default: '' },
  score: { type: [Number, String], default: null },
  size: { type: String, default: 'md' }, // md | sm
})

const scoreText = computed(() => {
  const s = props.score
  if (s === null || s === undefined || s === '') return '--'
  const n = Number(s)
  return Number.isFinite(n) ? `-${n}分` : '--'
})
</script>

<style scoped>
/* 配色保持克制：中性灰名称段 + 唯一的红色扣分段，深浅背景下都清晰 */
.score-badge {
  display: inline-flex;
  align-items: stretch;
  border-radius: 8px;
  overflow: hidden;
  font-weight: 600;
  line-height: 1;
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.12);
  max-width: 100%;
  vertical-align: middle;
}

.score-badge__name {
  background: #f1f5f9;
  color: #334155;
  padding: 5px 10px;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

/* 扣分一眼可见：实心红底白字加粗 */
.score-badge__score {
  background: #dc2626;
  color: #ffffff;
  padding: 5px 10px;
  font-size: 13px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  flex-shrink: 0;
}

.score-badge--sm .score-badge__name,
.score-badge--sm .score-badge__score {
  padding: 3px 8px;
  font-size: 12px;
}
</style>

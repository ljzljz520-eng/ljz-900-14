// 扣分值展示与校验工具（检查项徽章在管理端 / 员工端 / 老板端共用）

/**
 * 将扣分值格式化为整数或最多两位小数（去掉多余的 0）。
 * @param {*} raw 数字或数字字符串
 * @returns {string} 例如 5 -> "5"、3.5 -> "3.5"、2.50 -> "2.5"
 */
export function formatScore(raw) {
  if (raw === null || raw === undefined || raw === '') return '0'
  const n = Number(raw)
  if (!Number.isFinite(n)) return String(raw)
  const rounded = Math.round(n * 100) / 100
  return String(rounded)
}

/**
 * 校验扣分值：必填且必须是合法数字（非负）。
 * 规则：去空格后非空、可被 Number 解析、有限、>= 0。
 * @returns {boolean}
 */
export function isValidScore(raw) {
  if (raw === null || raw === undefined) return false
  const s = String(raw).trim()
  if (s === '') return false
  const n = Number(s)
  return Number.isFinite(n) && n >= 0
}

<template>
  <div class="admin-page">
    <header class="page-header">
      <h1 class="page-title">检查上传</h1>
      <p class="page-desc">选择员工、上传问题图片并填写检查项名称与扣分值，key 序号连续、删除后自动重排，生成整改链接与二维码；徽章在员工整改页与老板汇总页一致展示</p>
    </header>

    <section v-loading="loading" class="admin-section">
      <div class="card">
        <div class="card-header">
          <span class="card-icon">
            <el-icon><User /></el-icon>
          </span>
          <h2 class="card-title">选择员工</h2>
        </div>
        <div class="filter-row">
          <el-select v-model="selectedUserId" placeholder="请选择员工" filterable class="employee-select">
            <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
          <el-date-picker
            v-model="selectedDate"
            type="date"
            placeholder="检查日期"
            value-format="YYYY-MM-DD"
            class="date-picker"
          />
        </div>
      </div>

      <!-- 该员工已有问题图片：key 横向可换行，可删除，序号连续 -->
      <div v-if="selectedUserId && existingRecords.length > 0" class="card existing-card">
        <div class="card-header">
          <span class="card-icon existing">
            <el-icon><Picture /></el-icon>
          </span>
          <h2 class="card-title">已有问题图片（#key + 检查项徽章）</h2>
        </div>
        <p class="card-hint-inline">序号 #1、#2… 横向排列可换行；徽章为检查项名称 + 扣分值，删除某张后序号自动连续、无跳跃。</p>
        <div class="key-grid">
          <div
            v-for="r in existingRecords"
            :key="r.id"
            class="key-tile"
          >
            <span class="key-badge">#{{ r.sequence_key }}</span>
            <div class="key-preview">
              <img :src="imageUrl(r.issue_image)" alt="问题图" @error="(e) => (e.target.style.display = 'none')" />
            </div>
            <div class="key-footer">
              <InspectionBadge
                size="small"
                :name="r.item_name_snapshot || r.item?.name"
                :score="r.item_score_snapshot ?? r.item?.score ?? 0"
              />
              <el-button type="danger" text size="small" class="key-delete" @click="deleteRecord(r.id)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-icon">
            <el-icon><PictureFilled /></el-icon>
          </span>
          <h2 class="card-title">上传问题图片</h2>
        </div>
        <div class="upload-wrapper">
          <el-upload
            :file-list="fileList"
            :auto-upload="false"
            :limit="20"
            multiple
            drag
            accept="image/jpeg,image/png,image/gif"
            list-type="picture-card"
            class="issue-upload"
            :on-preview="handlePreview"
            :on-remove="handleRemove"
            :on-change="onUploadChange"
          >
            <div class="upload-content">
              <el-icon class="upload-icon"><UploadFilled /></el-icon>
              <p class="upload-text">拖拽图片到此处，或 <em>点击上传</em></p>
              <p class="upload-hint">支持 JPG、PNG、GIF</p>
            </div>
          </el-upload>
        </div>
        <p class="card-hint">每张图片需填写「检查项名称」与「扣分值」（必填、数字），保存后 key 自增连续、并生成整改链接与二维码；同一徽章会展示在员工整改页与老板汇总页。</p>
      </div>

      <!-- 待保存的新图片：显示临时 key #n，手动填写检查项名称与扣分值 -->
      <div v-if="pendingItems.length" class="card">
        <div class="card-header">
          <span class="card-icon">
            <el-icon><List /></el-icon>
          </span>
          <h2 class="card-title">填写检查项与扣分值（保存后序号为 #{{ nextKey }}～#{{ nextKey + pendingItems.length - 1 }}）</h2>
        </div>
        <div class="pending-grid">
          <div
            v-for="(item, idx) in pendingItems"
            :key="item.uid"
            class="pending-item"
            :class="{ 'pending-item--invalid': showErrors && !isPendingValid(item) }"
          >
            <span class="pending-key-badge">#{{ nextKey + idx }}</span>
            <div class="pending-preview">
              <img v-if="item.url" :src="item.url" alt="预览" />
            </div>

            <div class="pending-form">
              <div class="pending-field">
                <label class="field-label">检查项名称</label>
                <el-input
                  v-model="item.itemName"
                  placeholder="如：地面清洁"
                  maxlength="64"
                  size="small"
                  clearable
                />
                <p v-if="showErrors && !item.itemName?.trim()" class="field-error">请填写检查项名称</p>
              </div>
              <div class="pending-field">
                <label class="field-label">扣分值（数字）</label>
                <el-input
                  v-model="item.scoreRaw"
                  placeholder="如：5"
                  inputmode="decimal"
                  size="small"
                  class="score-input"
                  @input="(v) => (item.scoreRaw = sanitizeScore(v))"
                >
                  <template #append>分</template>
                </el-input>
                <p v-if="showErrors && !isValidScore(item.scoreRaw)" class="field-error">扣分值不能为空，且必须是数字</p>
              </div>

              <!-- 实时徽章预览：与员工端 / 老板端为同一个组件 -->
              <div class="pending-badge-row">
                <InspectionBadge
                  :name="item.itemName?.trim()"
                  :score="item.scoreRaw"
                />
                <el-dropdown v-if="inspectionItems.length" trigger="click" placement="top-start" @command="(it) => applyPreset(item, it)">
                  <el-button text size="small" class="preset-btn">
                    常用项<el-icon class="el-icon--right"><ArrowDown /></el-icon>
                  </el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item
                        v-for="i in inspectionItems"
                        :key="i.id"
                        :command="i"
                      >
                        {{ i.name }}（-{{ i.score }}分）
                      </el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </div>
            </div>
          </div>
        </div>
        <el-button type="primary" size="large" :loading="saving" class="save-btn" @click="saveRecords">
          <el-icon class="mr-2"><Check /></el-icon>
          保存并生成链接与二维码
        </el-button>
      </div>

      <div v-if="resultLink" class="card result-card">
        <div class="card-header success">
          <span class="card-icon success">
            <el-icon><CircleCheckFilled /></el-icon>
          </span>
          <h2 class="card-title">已生成整改链接与二维码</h2>
        </div>
        <div class="result-content">
          <div class="result-link-wrap">
            <label class="result-label">整改链接（可复制给员工扫码或打开）：</label>
            <el-input v-model="resultLink" readonly size="large">
              <template #append>
                <el-button type="primary" @click="copyLink">复制</el-button>
              </template>
            </el-input>
          </div>
          <div v-if="resultQrUrl" class="result-qr">
            <label class="result-label">二维码：</label>
            <img :src="imageUrl(resultQrUrl)" alt="二维码" class="qr-image" />
          </div>
        </div>
      </div>
    </section>

    <el-dialog v-model="previewVisible" title="图片预览" width="80%" class="preview-dialog" append-to-body>
      <img v-if="previewUrl" :src="previewUrl" alt="预览" class="w-full rounded-lg" />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { UploadFilled, User, PictureFilled, List, Check, CircleCheckFilled, Picture, Delete, ArrowDown } from '@element-plus/icons-vue'
import { api, apiBase } from '@/api/request'
import InspectionBadge from '@/components/inspection/InspectionBadge.vue'
import { isValidScore } from '@/utils/score'

const loading = ref(false)
const saving = ref(false)
const users = ref([])
const inspectionItems = ref([])
const selectedUserId = ref(null)
const selectedDate = ref(new Date())
const existingRecords = ref([])
const fileList = ref([])
const resultLink = ref('')
const resultQrUrl = ref('')
const previewVisible = ref(false)
const previewUrl = ref('')

const nextKey = computed(() => {
  if (existingRecords.value.length === 0) return 1
  const max = Math.max(...existingRecords.value.map((r) => r.sequence_key))
  return max + 1
})

const pendingItems = ref([])
const showErrors = ref(false)

function syncPendingItems() {
  const prev = new Map(pendingItems.value.map((p) => [p.uid, p]))
  pendingItems.value = fileList.value.map((f) => {
    const old = prev.get(f.uid)
    return {
      uid: f.uid,
      url: f.raw ? URL.createObjectURL(f.raw) : null,
      itemName: old?.itemName ?? '',
      scoreRaw: old?.scoreRaw ?? '',
      raw: f.raw,
    }
  })
}
watch(fileList, syncPendingItems, { deep: true })

// 只允许数字与一个小数点；空字符串允许（输入中途），由保存校验拦截
function sanitizeScore(v) {
  let s = String(v).replace(/[^\d.]/g, '')
  const firstDot = s.indexOf('.')
  if (firstDot !== -1) {
    s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, '')
  }
  return s
}

function isPendingValid(p) {
  return !!(p.itemName && p.itemName.trim() && isValidScore(p.scoreRaw))
}

// 一键填入常用检查项（来自系统预置项），填入后仍可手动修改
function applyPreset(item, preset) {
  item.itemName = preset.name
  item.scoreRaw = String(preset.score ?? '')
  showErrors.value = false
}

function formatDate(date) {
  if (!date) return ''
  if (typeof date === 'string') return date
  const d = new Date(date)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

async function loadRecords() {
  if (!selectedUserId.value) {
    existingRecords.value = []
    return
  }
  try {
    const list = await api.getRecords({
      user_id: selectedUserId.value,
      check_date: formatDate(selectedDate.value),
    })
    existingRecords.value = list || []
  } catch (_) {
    existingRecords.value = []
  }
}
watch(selectedUserId, loadRecords)
watch(selectedDate, loadRecords)

async function deleteRecord(id) {
  try {
    await ElMessageBox.confirm('删除后序号将自动连续重排，确定删除该条？', '确认删除', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning',
    })
    await api.deleteRecord(id)
    await loadRecords()
    ElMessage.success('已删除，序号已连续')
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('删除失败')
  }
}

function imageUrl(path) {
  if (!path) return ''
  const base = apiBase() || (typeof window !== 'undefined' ? window.location.origin : '')
  return path.startsWith('http') ? path : (base.replace(/\/$/, '') + path)
}

function handlePreview(uploadFile) {
  previewUrl.value = uploadFile.url || URL.createObjectURL(uploadFile.raw)
  previewVisible.value = true
}
function onUploadChange(_uploadFile, uploadFiles) {
  fileList.value = uploadFiles
}
function handleRemove() {}

async function loadUsers() {
  loading.value = true
  try {
    users.value = await api.getUsers()
    if (users.value.length && !selectedUserId.value) selectedUserId.value = users.value[0].id
  } finally {
    loading.value = false
  }
}
async function loadItems() {
  try {
    inspectionItems.value = await api.getInspectionItems()
  } catch (_) {}
}

async function saveRecords() {
  if (!selectedUserId.value) {
    ElMessage.warning('请选择员工')
    return
  }
  if (pendingItems.value.length === 0) {
    ElMessage.warning('请先上传问题图片')
    return
  }
  // 扣分值为空或不是数字时不允许保存
  showErrors.value = true
  const invalidName = pendingItems.value.find((p) => !p.itemName || !p.itemName.trim())
  if (invalidName) {
    ElMessage.error('请为每张图片填写检查项名称')
    return
  }
  const invalidScore = pendingItems.value.find((p) => !isValidScore(p.scoreRaw))
  if (invalidScore) {
    ElMessage.error('扣分值不能为空，且必须是数字，请检查标红的图片')
    return
  }
  saving.value = true
  try {
    const uploaded = []
    for (const item of pendingItems.value) {
      const res = await api.uploadImage(item.raw)
      if (res?.path) {
        uploaded.push({
          item_name: item.itemName.trim(),
          item_score: Number(String(item.scoreRaw).trim()),
          issue_image: res.path,
        })
      }
    }
    const baseUrl = typeof window !== 'undefined' ? window.location.origin + '/fix' : 'http://localhost:3000/fix'
    const saved = await api.createRecords({
      user_id: selectedUserId.value,
      check_date: formatDate(selectedDate.value),
      items: uploaded,
      base_url: baseUrl,
    })
    await loadRecords()
    // 新后端：同一步返回二维码；旧后端：这里保底再走一次 generateQr
    if (saved && typeof saved === 'object' && !Array.isArray(saved) && (saved.link || saved.qr_code_url)) {
      resultLink.value = saved.link || ''
      resultQrUrl.value = saved.qr_code_url || ''
    } else {
      const qrData = await api.generateQr(selectedUserId.value, baseUrl)
      resultLink.value = qrData?.link || ''
      resultQrUrl.value = qrData?.qr_code_url || ''
    }
    fileList.value = []
    showErrors.value = false
    ElMessage.success('已保存并生成链接与二维码')
  } catch (_) {
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

function copyLink() {
  if (!resultLink.value) return
  navigator.clipboard.writeText(resultLink.value).then(() => ElMessage.success('已复制到剪贴板'))
}

onMounted(() => {
  syncPendingItems()
})
loadUsers()
loadItems()
</script>

<style scoped>
.admin-page {
  max-width: 1120px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 32px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}

.page-desc {
  font-size: 15px;
  color: #64748b;
  margin: 0;
}

.admin-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.card-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.card-header.success .card-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.card-header .card-icon.existing {
  background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
}

.card-hint-inline {
  font-size: 13px;
  color: #94a3b8;
  margin: -8px 0 16px;
}

.key-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.key-tile {
  position: relative;
  width: 168px;
  background: #f8fafc;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  transition: box-shadow 0.2s;
  display: flex;
  flex-direction: column;
}

.key-tile:hover {
  box-shadow: 0 4px 12px rgb(0 0 0 / 0.08);
}

.key-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: rgba(15, 23, 42, 0.75);
  color: white;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  z-index: 1;
}

.key-preview {
  aspect-ratio: 4/3;
  background: #e2e8f0;
  overflow: hidden;
}

.key-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.key-footer {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px;
}

.key-footer .insp-badge {
  min-width: 0;
  flex: 1;
}

.key-footer .insp-badge__name {
  min-width: 0;
}

.key-delete {
  flex-shrink: 0;
}

.pending-key-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: rgba(15, 23, 42, 0.75);
  color: white;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  z-index: 1;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.employee-select {
  max-width: 280px;
}

.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

.date-picker {
  width: 200px;
}

.upload-wrapper {
  overflow: hidden;
}

.card-hint {
  margin-top: 16px;
  font-size: 13px;
  color: #94a3b8;
  clear: both;
  display: block;
}

/* picture-card 模式下：触发区占满整行，虚线四边完整显示 */
.issue-upload :deep(.el-upload-list--picture-card) {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.issue-upload :deep(.el-upload-list--picture-card .el-upload-list__item) {
  margin: 0;
}

.issue-upload :deep(.el-upload.el-upload--picture-card) {
  width: 100%;
  min-height: 150px;
  height: auto;
  margin: 0;
  border: none;
  border-radius: 0;
  overflow: visible;
}

.issue-upload :deep(.el-upload-dragger) {
  width: 100%;
  min-height: 150px;
  padding: 20px 16px;
  border-radius: 12px;
  border: 2px dashed #e2e8f0;
  background: #fafbfc;
  transition: all 0.2s;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.issue-upload :deep(.el-upload-dragger:hover) {
  border-color: #0ea5e9;
  background: rgba(14, 165, 233, 0.04);
}

.upload-content {
  text-align: center;
}

.upload-icon {
  font-size: 40px;
  color: #94a3b8;
  margin-bottom: 8px;
}

.issue-upload :deep(.el-upload-dragger:hover) .upload-icon {
  color: #0ea5e9;
}

.upload-text {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 4px;
  line-height: 1.5;
}

.upload-text em {
  color: #0ea5e9;
  font-style: normal;
  font-weight: 500;
}

.upload-hint {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
}

.pending-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.pending-item {
  position: relative;
  background: #f8fafc;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  transition: box-shadow 0.2s, border-color 0.2s;
}

.pending-item:hover {
  box-shadow: 0 4px 12px rgb(0 0 0 / 0.08);
}

.pending-item--invalid {
  border-color: #fca5a5;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15);
}

.pending-preview {
  aspect-ratio: 16/10;
  background: #e2e8f0;
  overflow: hidden;
}

.pending-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pending-form {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pending-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
}

.field-error {
  margin: 0;
  font-size: 12px;
  color: #dc2626;
  line-height: 1.3;
}

.score-input :deep(input) {
  font-weight: 700;
  color: #dc2626;
}

.pending-badge-row {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: space-between;
  flex-wrap: wrap;
}

.pending-badge-row .insp-badge {
  min-width: 0;
  max-width: 100%;
}

.preset-btn {
  flex-shrink: 0;
  font-size: 12px;
}

.save-btn {
  height: 48px;
  padding: 0 28px;
  font-size: 15px;
  font-weight: 600;
  border-radius: 12px;
}

.mr-2 {
  margin-right: 8px;
}

.result-card {
  border-color: rgba(16, 185, 129, 0.2);
  background: linear-gradient(to bottom, rgba(16, 185, 129, 0.03), white);
}

.result-content {
  display: flex;
  flex-wrap: wrap;
  gap: 32px;
  align-items: flex-start;
}

.result-link-wrap {
  flex: 1;
  min-width: 0;
}

.result-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 8px;
}

.result-qr {
  flex-shrink: 0;
}

.qr-image {
  width: 180px;
  height: 180px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  object-fit: contain;
  background: white;
}

.preview-dialog :deep(.el-dialog__body) {
  padding: 16px;
}
</style>

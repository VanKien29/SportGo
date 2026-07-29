<template>
  <Teleport to="body">
    <!-- Modal Backdrop -->
    <div v-if="modalMode" class="modal-backdrop" @click.self="$emit('close')">
      
      <!-- MODE 1: CREATE POST MODAL -->
      <div v-if="modalMode === 'create'" class="modal-dialog-card">
        <div class="modal-card-header">
          <h3 class="modal-title">Tạo bài giao lưu mới</h3>
          <button type="button" class="modal-close-btn" @click="$emit('close')">✕</button>
        </div>

        <form @submit.prevent="$emit('submit-create')">
          <div class="modal-card-body">
            <!-- Cluster Select -->
            <div class="form-field">
              <label>Cụm sân áp dụng <span class="req">*</span></label>
              <select
                :value="createForm.venue_cluster_id"
                required
                @change="onClusterChange($event.target.value)"
              >
                <option value="" disabled>-- Chọn cụm sân --</option>
                <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
                  {{ cluster.name }}
                </option>
              </select>
            </div>

            <!-- Booking Select -->
            <div class="form-field">
              <label>Lịch đặt sân sắp tới <span class="req">*</span></label>
              <select
                :value="createForm.booking_id"
                :disabled="!createForm.venue_cluster_id || eligibleBookingsLoading"
                required
                @change="$emit('update:create-form', { ...createForm, booking_id: $event.target.value })"
              >
                <option value="" disabled>-- Chọn lịch đặt sân --</option>
                <option v-for="bk in eligibleBookings" :key="bk.id" :value="bk.id">
                  {{ bk.venueCourt?.name || 'Sân trống' }} | {{ formatDate(bk.booking_date) }} ({{ formatTime(bk.start_time) }} - {{ formatTime(bk.end_time) }})
                </option>
              </select>
              <span v-if="eligibleBookingsLoading" class="help-text">Đang tải lịch...</span>
              <span v-else-if="createForm.venue_cluster_id && !eligibleBookings.length" class="help-text text-danger">
                Không có lịch trống phù hợp để tạo bài giao lưu.
              </span>
            </div>

            <!-- Description -->
            <div class="form-field">
              <label>Mô tả / Ghi chú cho người chơi</label>
              <textarea
                :value="createForm.description"
                rows="3"
                placeholder="Nhập ghi chú cho buổi giao lưu..."
                @input="$emit('update:create-form', { ...createForm, description: $event.target.value })"
              ></textarea>
            </div>

            <!-- Needed Players & Cost -->
            <div class="form-row-2">
              <div class="form-field">
                <label>Số người cần thêm <span class="req">*</span></label>
                <input
                  type="number"
                  min="1"
                  max="50"
                  :value="createForm.needed_players"
                  required
                  @input="$emit('update:create-form', { ...createForm, needed_players: Number($event.target.value) })"
                />
              </div>

              <div class="form-field">
                <label>Chi phí / người (đ)</label>
                <input
                  type="number"
                  min="0"
                  step="1000"
                  :value="createForm.cost_per_player"
                  placeholder="0"
                  @input="$emit('update:create-form', { ...createForm, cost_per_player: Number($event.target.value) })"
                />
              </div>
            </div>
          </div>

          <div class="modal-card-footer">
            <button type="button" class="btn-secondary-cancel" @click="$emit('close')">
              Hủy
            </button>
            <button type="submit" class="btn-primary-submit" :disabled="saving || !createForm.booking_id">
              <template v-if="saving">Đang lưu...</template>
              <template v-else>Đăng bài giao lưu</template>
            </button>
          </div>
        </form>
      </div>

      <!-- MODE 2: HIDE POST MODAL -->
      <div v-else-if="modalMode === 'hide'" class="modal-dialog-card">
        <div class="modal-card-header">
          <h3 class="modal-title">Ẩn bài viết giao lưu</h3>
          <button type="button" class="modal-close-btn" @click="$emit('close')">✕</button>
        </div>

        <form @submit.prevent="$emit('submit-hide')">
          <div class="modal-card-body">
            <p class="modal-desc">
              Bài viết sẽ được chuyển sang trạng thái <strong>Đóng</strong> và ẩn khỏi trang tìm đối thủ.
            </p>

            <div class="form-field">
              <label>Lý do ẩn bài <span class="req">*</span></label>
              <textarea
                :value="hideReason"
                rows="3"
                placeholder="Nhập lý do ẩn gửi đến người đăng..."
                required
                @input="$emit('update:hide-reason', $event.target.value)"
              ></textarea>
            </div>
          </div>

          <div class="modal-card-footer">
            <button type="button" class="btn-secondary-cancel" @click="$emit('close')">
              Hủy
            </button>
            <button type="submit" class="btn-primary-submit" :disabled="saving || !hideReason">
              <template v-if="saving">Đang ẩn...</template>
              <template v-else>Ẩn bài</template>
            </button>
          </div>
        </form>
      </div>

      <!-- MODE 3: REPORT POST MODAL -->
      <div v-else-if="modalMode === 'report'" class="modal-dialog-card">
        <div class="modal-card-header">
          <h3 class="modal-title">Báo cáo vi phạm bài viết</h3>
          <button type="button" class="modal-close-btn" @click="$emit('close')">✕</button>
        </div>

        <form @submit.prevent="$emit('submit-report')">
          <div class="modal-card-body">
            <div class="form-field">
              <label>Lý do vi phạm <span class="req">*</span></label>
              <select
                :value="reportForm.reason"
                required
                @change="$emit('update:report-form', { ...reportForm, reason: $event.target.value })"
              >
                <option value="" disabled>-- Chọn lý do --</option>
                <option value="spam">Spam quảng cáo</option>
                <option value="offensive">Nội dung phản cảm</option>
                <option value="fake">Thông tin giả mạo</option>
                <option value="harassment">Quấy rối / Đả kích</option>
                <option value="other">Lý do khác</option>
              </select>
            </div>

            <div class="form-field">
              <label>Mô tả chi tiết</label>
              <textarea
                :value="reportForm.description"
                rows="3"
                placeholder="Mô tả cụ thể vi phạm..."
                @input="$emit('update:report-form', { ...reportForm, description: $event.target.value })"
              ></textarea>
            </div>
          </div>

          <div class="modal-card-footer">
            <button type="button" class="btn-secondary-cancel" @click="$emit('close')">
              Hủy
            </button>
            <button type="submit" class="btn-primary-submit" :disabled="saving || !reportForm.reason">
              <template v-if="saving">Đang gửi...</template>
              <template v-else>Gửi báo cáo</template>
            </button>
          </div>
        </form>
      </div>

    </div>
  </Teleport>
</template>

<script>
export default {
  name: 'MatchmakingFormModal',
  props: {
    modalMode: { type: String, default: null }, // 'create' | 'hide' | 'report' | null
    clusters: { type: Array, default: () => [] },
    eligibleBookings: { type: Array, default: () => [] },
    eligibleBookingsLoading: { type: Boolean, default: false },
    createForm: { type: Object, default: () => ({}) },
    hideReason: { type: String, default: '' },
    reportForm: { type: Object, default: () => ({}) },
    saving: { type: Boolean, default: false },
  },
  emits: [
    'close',
    'submit-create',
    'submit-hide',
    'submit-report',
    'update:create-form',
    'update:hide-reason',
    'update:report-form',
    'cluster-select-changed',
  ],
  methods: {
    onClusterChange(clusterId) {
      this.$emit('update:create-form', { ...this.createForm, venue_cluster_id: clusterId, booking_id: '' });
      this.$emit('cluster-select-changed', clusterId);
    },
    formatDate(val) {
      if (!val) return '-';
      const d = new Date(val);
      if (Number.isNaN(d.getTime())) return val;
      return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
    formatTime(val) {
      if (!val) return '--:--';
      return String(val).substring(0, 5);
    },
  },
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-dialog-card {
  width: 100%;
  max-width: 480px;
  background: var(--admin-surface, #ffffff);
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: fadeInModal 0.2s ease-out;
}

@keyframes fadeInModal {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

.modal-card-header {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: none;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  color: var(--admin-muted, #2f3d34);
  cursor: pointer;
  padding: 4px;
}

.modal-card-body {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.modal-desc {
  margin: 0;
  font-size: 13px;
  font-weight: 400;
  color: var(--admin-muted, #2f3d34);
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-field label {
  font-size: 13px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.req {
  color: var(--admin-danger, #dc2626);
}

.form-field input,
.form-field select,
.form-field textarea {
  width: 100%;
  padding: 9px 12px;
  border-radius: 8px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  font-size: 13.5px;
  font-weight: 400;
  outline: none;
  transition: border-color 0.15s ease;
}

.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
  border-color: var(--admin-primary, #22a653);
}

.help-text {
  font-size: 11.5px;
  color: var(--admin-muted, #2f3d34);
}

.text-danger {
  color: var(--admin-danger, #dc2626);
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.modal-card-footer {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  border-top: none;
}

.btn-secondary-cancel {
  height: 36px;
  padding: 0 16px;
  border-radius: 8px;
  border: 1px solid var(--admin-border-soft, #e3ece4);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
}

.btn-primary-submit {
  height: 36px;
  padding: 0 18px;
  border-radius: 8px;
  border: none;
  background: var(--admin-primary, #22a653);
  color: var(--admin-primary-text, #ffffff);
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
}

.btn-primary-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>

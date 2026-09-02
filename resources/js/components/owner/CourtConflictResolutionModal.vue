<template>
  <div v-if="modelValue" class="modal-backdrop" @click.self="onCancel">
    <section class="conflict-modal" role="dialog" aria-modal="true" aria-labelledby="court-conflict-title">
      <header>
        <div>
          <p class="eyebrow">XỬ LÝ LỊCH ĐẶT TRÙNG</p>
          <h3 id="court-conflict-title">
            Chuyển {{ courtName || 'Sân con' }} sang trạng thái {{ targetStatusLabel }}
          </h3>
        </div>
        <button type="button" class="icon-close" aria-label="Đóng" @click="onCancel">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </header>

      <p class="conflict-help">
        Phát hiện <strong>{{ conflicts.affected_count || conflicts.items?.length || 0 }}</strong> booking trong tương lai trên sân này. Vui lòng chọn phương án xử lý cho từng booking trước khi xác nhận.
      </p>

      <div class="conflict-reason-box">
        <label for="court-status-reason" class="reason-label">Lý do thay đổi trạng thái:</label>
        <input
          id="court-status-reason"
          v-model="localReason"
          type="text"
          class="conflict-input"
          :placeholder="defaultPlaceholder"
        />
      </div>

      <div v-if="!conflicts.items?.length" class="conflict-empty">
        Không có booking nào trùng lịch.
      </div>

      <div v-else class="conflict-list">
        <article v-for="item in conflicts.items" :key="item.booking_item_id" class="conflict-card">
          <div class="conflict-main">
            <strong>{{ item.booking_code || 'Booking' }}</strong>
            <span>{{ item.customer?.name || 'Khách hàng' }} · {{ item.customer?.phone || '-' }}</span>
            <small>
              {{ formatDate(item.booking_date) }} · {{ item.court?.name || courtName }} · {{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }} · {{ bookingStatusLabel(item.booking_status) }} · {{ paymentStatusLabel(item.payment_status) }}
            </small>
            <small v-if="item.is_playing" class="incident-summary">
              Đang chơi · đã dùng {{ item.incident?.played_minutes || 0 }} phút · còn {{ item.incident?.remaining_minutes || 0 }} phút
            </small>
            <div v-if="item.affected_range" class="conflict-impact">
              <span>Bị ảnh hưởng: {{ formatTime(item.affected_range.start_time) }} - {{ formatTime(item.affected_range.end_time) }} · {{ formatCurrency(item.affected_range.subtotal) }}</span>
            </div>
          </div>

          <div class="conflict-actions">
            <!-- PHẠM VI XỬ LÝ -->
            <div class="resolution-group">
              <span class="resolution-label">Phạm vi xử lý</span>
              <div class="scope-switch">
                <button
                  type="button"
                  :class="{ active: getResolution(item).scope === 'affected' }"
                  @click="setScope(item, 'affected')"
                >
                  Chỉ phần bị ảnh hưởng
                </button>
                <button
                  type="button"
                  :class="{ active: getResolution(item).scope === 'booking_item' }"
                  @click="setScope(item, 'booking_item')"
                >
                  Cả khung booking
                </button>
              </div>
            </div>

            <!-- SÂN THAY THẾ (NẾU CHỌN ĐỔI SÂN) -->
            <div v-if="alternativesForItem(item).length" class="resolution-group">
              <span class="resolution-label">Sân thay thế</span>
              <select
                v-model="getResolution(item).venue_court_id"
                :disabled="getResolution(item).action !== 'switch'"
                class="conflict-select"
                aria-label="Chọn sân thay thế"
              >
                <option v-for="alt in alternativesForItem(item)" :key="alt.id" :value="alt.id">
                  {{ alt.name }}
                </option>
              </select>
            </div>
            <div v-else class="no-alternative">
              <strong>Không có sân thay thế</strong>
              <span>Các sân cùng loại đều đã có lịch hoặc không hoạt động.</span>
            </div>

            <!-- PHƯƠNG ÁN XỬ LÝ -->
            <div class="resolution-group">
              <span class="resolution-label">Cách xử lý</span>
              <div class="conflict-radios">
                <button
                  v-if="alternativesForItem(item).length"
                  type="button"
                  class="resolution-option"
                  :class="{ active: getResolution(item).action === 'switch' }"
                  @click="setAction(item, 'switch')"
                >
                  Đổi sang sân cùng loại
                </button>
                <button
                  type="button"
                  class="resolution-option danger"
                  :class="{ active: getResolution(item).action === 'cancel' }"
                  @click="setAction(item, 'cancel')"
                >
                  {{ item.payment_status === 'paid' ? 'Hủy và hoàn vào ví' : 'Hủy khung booking' }}
                </button>
                <button
                  v-if="item.payment_status === 'paid'"
                  type="button"
                  class="resolution-option cash"
                  :class="{ active: getResolution(item).action === 'cash_refund' }"
                  @click="setAction(item, 'cash_refund')"
                >
                  Hoàn tiền mặt tại quầy
                </button>
              </div>
            </div>
          </div>
        </article>
      </div>

      <footer>
        <button type="button" class="secondary-btn" :disabled="loading" @click="onCancel">
          Quay lại
        </button>
        <button
          type="button"
          class="primary-btn"
          :disabled="loading || !canConfirm"
          @click="onConfirm"
        >
          {{ loading ? 'Đang xử lý...' : 'Xác nhận và cập nhật trạng thái' }}
        </button>
      </footer>
    </section>
  </div>
</template>

<script>
export default {
  name: 'CourtConflictResolutionModal',
  props: {
    modelValue: {
      type: Boolean,
      default: false,
    },
    courtName: {
      type: String,
      default: '',
    },
    targetStatus: {
      type: String,
      default: 'maintenance',
    },
    conflicts: {
      type: Object,
      default: () => ({ affected_count: 0, items: [] }),
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['update:modelValue', 'confirm', 'cancel'],
  data() {
    return {
      localResolutions: {},
      localReason: '',
    };
  },
  computed: {
    targetStatusLabel() {
      if (this.targetStatus === 'inactive') return 'Tạm ngưng';
      if (this.targetStatus === 'maintenance') return 'Bảo trì';
      return 'Hoạt động';
    },
    defaultPlaceholder() {
      return this.targetStatus === 'inactive'
        ? 'Ví dụ: Tạm ngưng hoạt động do sửa chữa / bảo dưỡng'
        : 'Ví dụ: Bảo trì định kỳ mặt sân, hệ thống chiếu sáng';
    },
    canConfirm() {
      const items = this.conflicts?.items || [];
      if (!items.length) return true;

      for (const item of items) {
        const res = this.localResolutions[item.booking_item_id];
        if (!res) return false;
        if (res.action === 'switch' && !res.venue_court_id) return false;
      }
      return true;
    },
  },
  watch: {
    modelValue(val) {
      if (val) {
        this.initResolutions();
      }
    },
    conflicts: {
      deep: true,
      handler() {
        this.initResolutions();
      },
    },
  },
  methods: {
    initResolutions() {
      const items = this.conflicts?.items || [];
      const map = {};
      items.forEach((item) => {
        const alts = item.alternatives || [];
        const firstAlt = alts[0]?.id || '';
        map[item.booking_item_id] = {
          booking_item_id: item.booking_item_id,
          scope: 'affected',
          action: firstAlt ? 'switch' : 'cancel',
          venue_court_id: firstAlt,
        };
      });
      this.localResolutions = map;
      this.localReason = this.defaultPlaceholder.replace('Ví dụ: ', '');
    },
    getResolution(item) {
      if (!this.localResolutions[item.booking_item_id]) {
        const firstAlt = item.alternatives?.[0]?.id || '';
        this.localResolutions[item.booking_item_id] = {
          booking_item_id: item.booking_item_id,
          scope: 'affected',
          action: firstAlt ? 'switch' : 'cancel',
          venue_court_id: firstAlt,
        };
      }
      return this.localResolutions[item.booking_item_id];
    },
    setScope(item, scope) {
      const res = this.getResolution(item);
      res.scope = scope;
      const alts = this.alternativesForItem(item);
      if (!alts.some((a) => String(a.id) === String(res.venue_court_id))) {
        res.venue_court_id = alts[0]?.id || '';
        if (!res.venue_court_id && res.action === 'switch') {
          res.action = 'cancel';
        }
      }
    },
    setAction(item, action) {
      const res = this.getResolution(item);
      res.action = action;
      if (action === 'switch' && !res.venue_court_id) {
        const alts = this.alternativesForItem(item);
        res.venue_court_id = alts[0]?.id || '';
      }
    },
    alternativesForItem(item) {
      const res = this.localResolutions[item.booking_item_id] || {};
      return res.scope === 'booking_item'
        ? (item.full_item_alternatives || [])
        : (item.alternatives || []);
    },
    onCancel() {
      this.$emit('update:modelValue', false);
      this.$emit('cancel');
    },
    onConfirm() {
      const resolutions = Object.values(this.localResolutions).map((r) => ({
        booking_item_id: r.booking_item_id,
        action: r.action,
        scope: r.scope || 'affected',
        venue_court_id: r.action === 'switch' ? r.venue_court_id : null,
      }));
      this.$emit('confirm', {
        resolutions,
        reason: this.localReason,
      });
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const [year, month, day] = String(dateStr).split('-');
      return `${day}/${month}/${year}`;
    },
    formatTime(timeStr) {
      if (!timeStr) return '';
      return String(timeStr).substring(0, 5);
    },
    formatCurrency(val) {
      const num = Number(val) || 0;
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
    },
    bookingStatusLabel(status) {
      const map = {
        pending_approval: 'Chờ duyệt',
        pending_payment: 'Chờ thanh toán',
        confirmed: 'Đã xác nhận',
        checked_in: 'Đang chơi',
        completed: 'Hoàn thành',
      };
      return map[status] || status || 'Chưa rõ';
    },
    paymentStatusLabel(status) {
      return status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán';
    },
  },
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  font-family: inherit;
}

.conflict-modal {
  width: min(960px, 100%);
  max-height: min(780px, calc(100vh - 48px));
  display: grid;
  grid-template-rows: auto auto auto minmax(0, 1fr) auto;
  gap: 14px;
  overflow: hidden;
  border-radius: 14px;
  border: 1px solid #d7ead7;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
  padding: 20px;
}

.conflict-modal header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.eyebrow {
  margin: 0 0 2px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #16a34a;
}

.conflict-modal h3 {
  margin: 0;
  color: #163222;
  font-size: 18px;
  font-weight: 700;
}

.icon-close {
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
  color: #64748b;
  cursor: pointer;
}

.icon-close:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.conflict-help {
  margin: 0;
  padding: 8px 12px;
  border-radius: 8px;
  background: #fefce8;
  border: 1px solid #fef08a;
  color: #854d0e;
  font-size: 13px;
  line-height: 1.45;
}

.conflict-reason-box {
  display: flex;
  align-items: center;
  gap: 10px;
}

.reason-label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
}

.conflict-input {
  flex: 1;
  height: 38px;
  padding: 0 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13px;
  color: #1e293b;
  outline: none;
}

.conflict-input:focus {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.conflict-empty {
  padding: 24px;
  text-align: center;
  color: #64748b;
  font-size: 14px;
}

.conflict-list {
  display: grid;
  gap: 12px;
  min-height: 0;
  overflow-y: auto;
  padding: 4px;
}

.conflict-card {
  display: grid;
  grid-template-columns: minmax(260px, 0.9fr) minmax(340px, 1.1fr);
  gap: 16px;
  align-items: start;
  padding: 14px;
  border: 1px solid #e2eadf;
  border-radius: 10px;
  background: #fbfffb;
}

.conflict-main {
  display: grid;
  gap: 4px;
}

.conflict-main strong {
  color: #12301f;
  font-size: 14px;
}

.conflict-main span,
.conflict-main small {
  color: #5d7165;
  font-size: 12px;
  line-height: 1.35;
}

.incident-summary {
  color: #d97706 !important;
  font-weight: 600;
}

.conflict-impact {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.conflict-impact span {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border: 1px solid #dbe8df;
  border-radius: 6px;
  background: #fff;
  color: #43564a;
  font-size: 12px;
}

.conflict-actions {
  display: grid;
  gap: 10px;
}

.resolution-group {
  display: grid;
  gap: 4px;
}

.resolution-label {
  color: #64748b;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.scope-switch {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px;
}

.scope-switch button {
  min-height: 36px;
  padding: 6px 10px;
  border: 1px solid #d7e5da;
  border-radius: 7px;
  background: #fff;
  color: #475569;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.12s ease;
}

.scope-switch button.active {
  border-color: #16a34a;
  background: #f0fdf4;
  color: #15803d;
  font-weight: 600;
}

.conflict-select {
  width: 100%;
  height: 38px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 0 10px;
  background: #fff;
  color: #1e293b;
  font-size: 13px;
  outline: none;
}

.no-alternative {
  display: grid;
  gap: 2px;
  padding: 8px 12px;
  border: 1px solid #fee2e2;
  border-radius: 7px;
  background: #fef2f2;
}

.no-alternative strong {
  color: #991b1b;
  font-size: 12px;
}

.no-alternative span {
  color: #b91c1c;
  font-size: 11px;
}

.conflict-radios {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.resolution-option {
  flex: 1;
  min-width: 130px;
  min-height: 38px;
  padding: 6px 10px;
  border: 1px solid #cddfd1;
  border-radius: 7px;
  background: #fff;
  color: #2f5a3a;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.12s ease;
}

.resolution-option.active {
  border-color: #16a34a;
  background: #f0fdf4;
  color: #15803d;
  font-weight: 600;
}

.resolution-option.danger {
  border-color: #fecaca;
  color: #b91c1c;
}

.resolution-option.danger.active {
  border-color: #ef4444;
  background: #fef2f2;
  color: #991b1b;
  font-weight: 600;
}

.resolution-option.cash {
  border-color: #fed7aa;
  color: #c2410c;
}

.resolution-option.cash.active {
  border-color: #f97316;
  background: #fff7ed;
  color: #9a3412;
  font-weight: 600;
}

.conflict-modal footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 10px;
  border-top: 1px solid #e2e8f0;
}

.primary-btn,
.secondary-btn {
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.primary-btn {
  border: 0;
  background: #16a34a;
  color: #fff;
  box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25);
}

.primary-btn:hover:not(:disabled) {
  background: #15803d;
}

.secondary-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}

.secondary-btn:hover:not(:disabled) {
  background: #f8fafc;
}

.primary-btn:disabled,
.secondary-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .conflict-card {
    grid-template-columns: 1fr;
  }
}
</style>

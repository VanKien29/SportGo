<template>
  <div class="rules-table-section">
    <!-- Section Top Bar -->
    <div class="rules-table-toolbar">
      <div class="toolbar-right">
        <button
          type="button"
          class="btn-primary-add"
          :disabled="!courtTypes.length"
          @click="$emit('open-create-modal')"
        >
          + {{ activeTabMeta.addLabel }}
        </button>
      </div>
    </div>

    <!-- Data Table Container -->
    <div v-if="isLoading" class="table-state-card">
      <div class="spinner-sm"></div>
      <span>Đang tải quy tắc cấu hình giá...</span>
    </div>

    <div v-else-if="loadFailed" class="table-state-card is-error">
      <span>Không thể tải dữ liệu cấu hình giá. Vui lòng thử lại.</span>
    </div>

    <div v-else-if="!filteredRows.length" class="table-state-card">
      <p>{{ activeTabMeta.empty }}</p>
    </div>

    <div v-else class="rules-table-wrapper">
      <table class="rules-data-table">
        <thead>
          <tr>
            <th>Loại sân</th>
            <th>{{ activeTab === 'weekly' ? 'Ngày trong tuần' : 'Ngày áp dụng' }}</th>
            <th>Khung giờ</th>
            <th>Loại booking</th>
            <th class="money-col">Giá / giờ</th>
            <th>Trạng thái</th>
            <th class="action-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in filteredRows" :key="row.id" :class="{ 'row-inactive': !row.is_active }">
            <td class="cell-court-type">
              <div class="court-type-info">
                <span class="type-name">{{ getCourtTypeName(row) }}</span>
                <small v-if="row.note" class="type-note">{{ row.note }}</small>
              </div>
            </td>
            <td class="cell-application">
              <span class="application-tag">{{ getApplicationLabel(row) }}</span>
            </td>
            <td class="cell-time">
              <span class="time-range-text">
                {{ formatTime(row.start_time) }} - {{ formatTime(row.end_time) }}
              </span>
            </td>
            <td class="cell-booking-type">
              <span class="booking-type-text">
                {{ getBookingTypeLabel(row.booking_type) }}
              </span>
            </td>
            <td class="money-col cell-price">
              <span class="price-value">{{ formatMoney(row.price) }} đ</span>
            </td>
            <td class="cell-status">
              <button
                type="button"
                class="switch-btn"
                :class="{ on: row.is_active }"
                :aria-pressed="row.is_active"
                :title="row.is_active ? 'Nhấp để tắt quy tắc này' : 'Nhấp để bật quy tắc này'"
                @click="$emit('toggle-row', row)"
              >
                <span class="switch-handle"></span>
              </button>
            </td>
            <td class="action-col">
              <div class="table-actions">
                <button
                  type="button"
                  class="action-btn edit-btn"
                  title="Sửa quy tắc giá"
                  @click="$emit('open-edit-modal', row)"
                >
                  Sửa
                </button>
                <button
                  type="button"
                  class="action-btn delete-btn"
                  title="Xóa quy tắc giá"
                  @click="$emit('delete-row', row)"
                >
                  Xóa
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PricingRulesTable',
  props: {
    activeTab: { type: String, default: 'weekly' },
    activeTabMeta: { type: Object, required: true },
    courtTypes: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    filteredRows: { type: Array, default: () => [] },
    isLoading: { type: Boolean, default: false },
    loadFailed: { type: Boolean, default: false },
  },
  emits: [
    'open-create-modal',
    'open-edit-modal',
    'toggle-row',
    'delete-row',
  ],
  methods: {
    getCourtTypeName(row) {
      if (row.court_type?.name) return row.court_type.name;
      const found = this.courtTypes.find((t) => Number(t.id) === Number(row.court_type_id));
      return found ? found.name : `Loại sân #${row.court_type_id}`;
    },
    getApplicationLabel(row) {
      if (this.activeTab === 'weekly') {
        const daysList = Array.isArray(row.apply_to_days)
          ? row.apply_to_days
          : JSON.parse(row.apply_to_days || '[]');
        if (!daysList.length) return 'Tất cả các ngày';
        if (daysList.length === 7) return 'Cả tuần (T2-CN)';
        const map = { 1: 'T2', 2: 'T3', 3: 'T4', 4: 'T5', 5: 'T6', 6: 'T7', 7: 'CN' };
        return daysList.sort((a, b) => a - b).map((d) => map[d] || d).join(', ');
      }
      if (row.holiday_date) {
        const d = new Date(row.holiday_date);
        return d.toLocaleDateString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit' });
      }
      return 'N/A';
    },
    getBookingTypeLabel(type) {
      return { all: 'Dùng chung', single: 'Đặt lẻ', recurring: 'Đặt cố định' }[type] || type;
    },
    formatTime(val) {
      if (!val) return '--:--';
      return String(val).substring(0, 5);
    },
    formatMoney(val) {
      const num = Number(val);
      if (!Number.isFinite(num)) return '0';
      return new Intl.NumberFormat('vi-VN').format(num);
    },
  },
};
</script>

<style scoped>
.rules-table-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.rules-table-toolbar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 16px;
}

.btn-primary-add {
  height: 36px;
  padding: 0 14px;
  border-radius: 8px;
  border: none;
  background: var(--admin-primary, #22a653);
  color: var(--admin-primary-text, #ffffff);
  font-size: 13px;
  font-weight: 400;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-primary-add:hover:not(:disabled) {
  background: var(--admin-primary-dark, #15733a);
}

.btn-primary-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* States */
.table-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
  padding: 36px 20px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px dashed var(--admin-border, #cfded1);
  border-radius: 8px;
  color: var(--admin-muted, #2f3d34);
  font-size: 13.5px;
  font-weight: 400;
  text-align: center;
}

.table-state-card.is-error {
  color: var(--admin-text, #101c15);
  background: var(--admin-bg-soft, #f7fbf5);
  border-color: var(--admin-border, #cfded1);
}

.spinner-sm {
  width: 18px;
  height: 18px;
  border: 2px solid var(--admin-border, #cfded1);
  border-top-color: var(--admin-primary, #22a653);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Data Table */
.rules-table-wrapper {
  overflow-x: auto;
  border: none;
  border-radius: 10px;
}

.rules-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.rules-data-table th {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-weight: 400;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 12px 14px;
  border-bottom: none;
}

.rules-data-table td {
  padding: 12px 14px;
  border-bottom: none;
  color: var(--admin-text, #101c15);
  font-weight: 400;
  vertical-align: middle;
}

.rules-data-table tbody tr {
  transition: background-color 0.12s ease;
}

.rules-data-table tbody tr:hover {
  background: var(--admin-hover, #edf7ed);
}

.rules-data-table tbody tr.row-inactive {
  opacity: 0.6;
}

.court-type-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.type-name {
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.type-note {
  font-size: 11.5px;
  font-weight: 400;
  color: var(--admin-muted, #2f3d34);
}

.application-tag {
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.time-range-text {
  font-weight: 400;
  font-size: 12.5px;
  color: var(--admin-text, #101c15);
}

.booking-type-text {
  font-size: 12.5px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.money-col {
  text-align: right;
}

.price-value {
  font-weight: 400;
  font-size: 13.5px;
  color: var(--admin-text, #101c15);
}

.action-col {
  text-align: center;
  width: 100px;
}

.table-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.action-btn {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 400;
  border: 1px solid var(--admin-border-soft, #e3ece4);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  cursor: pointer;
  transition: all 0.15s ease;
}

.action-btn:hover {
  background: var(--admin-hover, #edf7ed);
  border-color: var(--admin-border, #cfded1);
}

.action-btn.delete-btn {
  color: var(--admin-text, #101c15);
  background: var(--admin-surface, #ffffff);
  border-color: var(--admin-border-soft, #e3ece4);
}

.action-btn.delete-btn:hover {
  background: var(--admin-hover, #edf7ed);
}

/* Switch Toggle Button */
.switch-btn {
  position: relative;
  width: 40px;
  height: 22px;
  border-radius: 999px;
  background: var(--admin-border, #cfded1);
  border: none;
  cursor: pointer;
  padding: 2px;
  transition: background-color 0.2s ease;
}

.switch-btn.on {
  background: var(--admin-primary, #22a653);
}

.switch-handle {
  display: block;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #ffffff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s ease;
}

.switch-btn.on .switch-handle {
  transform: translateX(18px);
}

@media (max-width: 768px) {
  .rules-table-toolbar {
    justify-content: flex-start;
  }
}
</style>

<template>
  <Teleport to="body">
    <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
      <section class="confirm-modal pricing-edit-modal">
        <header class="modal-head">
          <div class="head-title-wrap">
            <h2 class="modal-title">
              {{ editingRow ? `Cập nhật ${activeTabMeta.label.toLowerCase()}` : activeTabMeta.addLabel }}
            </h2>
          </div>
          <button type="button" class="close-icon-btn" title="Đóng modal" @click="$emit('close')">
            ✕
          </button>
        </header>

        <form class="modal-form-body" @submit.prevent="$emit('save-price')">
          <div v-if="errorMessage" class="alert error-alert">
            <span>{{ errorMessage }}</span>
          </div>

          <!-- Section 1: Target Court & Booking Type -->
          <div class="form-grid">
            <label class="field-group">
              <span class="field-label">
                Loại sân <span class="required">*</span>
              </span>
              <select
                :value="form.court_type_id"
                required
                @change="updateFormField('court_type_id', Number($event.target.value))"
              >
                <option v-for="t in courtTypes" :key="t.id" :value="t.id">
                  {{ t.name }}
                </option>
              </select>
            </label>

            <label class="field-group">
              <span class="field-label">
                Loại booking <span class="required">*</span>
              </span>
              <select
                :value="form.booking_type"
                required
                @change="updateFormField('booking_type', $event.target.value)"
              >
                <option value="all">Dùng chung (Tất cả)</option>
                <option value="single">Đặt lẻ</option>
                <option value="recurring">Đặt cố định</option>
              </select>
            </label>
          </div>

          <!-- Section 2: Application Days OR Date -->
          <template v-if="activeTab === 'weekly'">
            <div class="field-group">
              <span class="field-label">
                Ngày áp dụng trong tuần <span class="required">*</span>
              </span>
              <div class="day-checkbox-grid">
                <button
                  v-for="d in days"
                  :key="d.value"
                  type="button"
                  class="day-pill-btn"
                  :class="{ selected: form.apply_to_days.includes(d.value) }"
                  @click="toggleDay(d.value)"
                >
                  <span>{{ d.label }} ({{ d.fullLabel }})</span>
                </button>
              </div>
            </div>
          </template>

          <template v-else>
            <label class="field-group">
              <span class="field-label">
                Ngày áp dụng cụ thể <span class="required">*</span>
              </span>
              <input
                type="date"
                :value="form.holiday_date"
                required
                @input="updateFormField('holiday_date', $event.target.value)"
              />
            </label>
          </template>

          <!-- Section 3: Time Range -->
          <div class="form-grid">
            <label class="field-group">
              <span class="field-label">
                Giờ bắt đầu <span class="required">*</span>
              </span>
              <input
                type="time"
                :value="form.start_time"
                required
                @input="updateFormField('start_time', $event.target.value)"
              />
            </label>

            <label class="field-group">
              <span class="field-label">
                Giờ kết thúc <span class="required">*</span>
              </span>
              <input
                type="time"
                :value="form.end_time"
                required
                @input="updateFormField('end_time', $event.target.value)"
              />
            </label>
          </div>

          <!-- Section 4: Price & Notes -->
          <div class="form-grid" :class="{ 'full-width-price': activeTab === 'weekly' }">
            <label class="field-group">
              <span class="field-label">
                Giá / giờ (VNĐ) <span class="required">*</span>
              </span>
              <div class="money-input-wrap">
                <input
                  type="number"
                  min="1"
                  step="1000"
                  :value="form.price"
                  required
                  placeholder="150000"
                  @input="updateFormField('price', Number($event.target.value))"
                />
                <span class="money-suffix">đ / giờ</span>
              </div>
              <span v-if="form.price > 0" class="price-formatted-hint">
                = {{ formatMoney(form.price) }} VNĐ
              </span>
            </label>

            <label v-if="activeTab !== 'weekly'" class="field-group">
              <span class="field-label">
                Ghi chú tên dịp / sự kiện
              </span>
              <input
                type="text"
                maxlength="255"
                :value="form.note"
                :placeholder="activeTabMeta.notePlaceholder"
                @input="updateFormField('note', $event.target.value)"
              />
            </label>
          </div>

          <!-- Section 5: Active Status Switch -->
          <label class="active-switch-card">
            <input
              type="checkbox"
              :checked="form.is_active"
              @change="updateFormField('is_active', $event.target.checked)"
            />
            <div class="switch-card-text">
              <strong>Kích hoạt quy tắc ngay sau khi lưu</strong>
              <p>Trạng thái bật cho phép hệ thống tính giá theo quy tắc này khi khách hàng đặt sân.</p>
            </div>
          </label>

          <!-- Footer Actions -->
          <footer class="modal-actions">
            <button type="button" class="btn-cancel" @click="$emit('close')">
              Hủy bỏ
            </button>
            <button type="submit" class="btn-submit-primary" :disabled="isSavingPrice">
              <template v-if="isSavingPrice">
                <span class="spinner-xs"></span>
                <span>Đang lưu cấu hình...</span>
              </template>
              <template v-else>
                <span>{{ editingRow ? 'Cập nhật quy tắc' : 'Lưu quy tắc mới' }}</span>
              </template>
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>

<script>
export default {
  name: 'PricingRuleModal',
  props: {
    show: { type: Boolean, default: false },
    editingRow: { type: Object, default: null },
    activeTab: { type: String, default: 'weekly' },
    activeTabMeta: { type: Object, required: true },
    courtTypes: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    isSavingPrice: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
  },
  emits: ['close', 'save-price', 'update:form'],
  methods: {
    updateFormField(key, val) {
      this.$emit('update:form', { ...this.form, [key]: val });
    },
    toggleDay(dayVal) {
      const currentDays = [...(this.form.apply_to_days || [])];
      const idx = currentDays.indexOf(dayVal);
      if (idx > -1) {
        currentDays.splice(idx, 1);
      } else {
        currentDays.push(dayVal);
      }
      this.updateFormField('apply_to_days', currentDays);
    },
    formatMoney(val) {
      const num = Number(val);
      if (!Number.isFinite(num) || num <= 0) return '0';
      return new Intl.NumberFormat('vi-VN').format(num);
    },
  },
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.pricing-edit-modal {
  background: var(--admin-surface, #ffffff);
  border-radius: 14px;
  max-width: 580px;
  width: 100%;
  box-shadow: var(--admin-shadow-lg, 0 24px 70px rgba(23, 34, 27, 0.16));
  border: 1px solid var(--admin-border-soft, #e3ece4);
  overflow: hidden;
  animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}

.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 0 20px;
  background: transparent;
  border-top: none !important;
  border-bottom: none !important;
}

.head-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--admin-text, #101c15);
}

.close-icon-btn {
  background: transparent;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  color: var(--admin-faint, #45564a);
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.close-icon-btn:hover {
  background: var(--admin-hover, #edf7ed);
  color: var(--admin-text, #101c15);
}

.modal-form-body {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  max-height: calc(85vh - 80px);
  overflow-y: auto;
}

.alert.error-alert {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  border-radius: 8px;
  background: var(--admin-danger-soft, #fef2f2);
  border: 1px solid var(--admin-danger, #dc2626);
  color: var(--admin-danger-text, #991b1b);
  font-size: 13px;
  font-weight: 500;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.full-width-price {
  grid-template-columns: 1fr;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  color: var(--admin-text, #101c15);
}

.required {
  color: var(--admin-danger, #dc2626);
}

.field-group input[type="text"],
.field-group input[type="number"],
.field-group input[type="date"],
.field-group input[type="time"],
.field-group select {
  height: 40px;
  border-radius: 8px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  padding: 0 12px;
  font-size: 13.5px;
  font-weight: 500;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.field-group input:focus,
.field-group select:focus {
  border-color: var(--admin-primary, #22a653);
  box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(34, 166, 83, 0.22));
}

.day-checkbox-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.day-pill-btn {
  display: inline-flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.day-pill-btn:hover {
  background: var(--admin-hover, #edf7ed);
}

.day-pill-btn.selected {
  background: var(--admin-primary, #22a653);
  border-color: var(--admin-primary, #22a653);
  color: var(--admin-primary-text, #ffffff);
}

.money-input-wrap {
  display: flex;
  align-items: center;
  border: 1px solid var(--admin-border, #cfded1);
  border-radius: 8px;
  background: var(--admin-surface, #ffffff);
  overflow: hidden;
}

.money-input-wrap:focus-within {
  border-color: var(--admin-primary, #22a653);
  box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(34, 166, 83, 0.22));
}

.money-input-wrap input {
  flex: 1;
  border: none;
  height: 40px;
  outline: none;
  padding: 0 12px;
  font-size: 14px;
  font-weight: 700;
}

.money-suffix {
  padding: 0 12px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--admin-faint, #45564a);
  background: var(--admin-bg, #eef6f0);
  height: 40px;
  display: flex;
  align-items: center;
  border-left: 1px solid var(--admin-border-soft, #e3ece4);
}

.price-formatted-hint {
  font-size: 12px;
  color: var(--admin-primary-dark, #15733a);
  font-weight: 600;
}

.active-switch-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border-soft, #e3ece4);
  cursor: pointer;
}

.active-switch-card input[type="checkbox"] {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  accent-color: var(--admin-primary, #22a653);
}

.switch-card-text strong {
  display: block;
  font-size: 13px;
  color: var(--admin-text, #101c15);
}

.switch-card-text p {
  margin: 2px 0 0 0;
  font-size: 12px;
  color: var(--admin-muted, #2f3d34);
}

.modal-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 4px;
  padding-top: 4px;
  border-top: none !important;
  border-bottom: none !important;
}

.btn-cancel {
  height: 38px;
  padding: 0 16px;
  border-radius: 8px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-cancel:hover {
  background: var(--admin-hover, #edf7ed);
}

.btn-submit-primary {
  height: 38px;
  padding: 0 18px;
  border-radius: 8px;
  border: none;
  background: var(--admin-primary, #22a653);
  color: var(--admin-primary-text, #ffffff);
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  cursor: pointer;
}

.btn-submit-primary:hover:not(:disabled) {
  background: var(--admin-primary-dark, #15733a);
}

.btn-submit-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner-xs {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

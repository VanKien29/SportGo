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

          <!-- Section 1: Target Court & Booking Type (Custom Dropdowns) -->
          <div class="form-grid">
            <div class="field-group">
              <span class="field-label">Loại sân</span>
              <div class="custom-select-wrap" ref="courtTypeSelect">
                <button
                  type="button"
                  class="custom-select-trigger"
                  :class="{ open: courtTypeOpen }"
                  @click.stop="toggleCourtTypeDropdown"
                >
                  <span class="trigger-text">{{ selectedCourtTypeName }}</span>
                  <AppIcon name="chevronDown" :size="14" class="arrow" :class="{ open: courtTypeOpen }" />
                </button>
                <div v-if="courtTypeOpen" class="custom-select-menu">
                  <div
                    v-for="t in courtTypes"
                    :key="t.id"
                    class="custom-select-option"
                    :class="{ active: String(form.court_type_id) === String(t.id) }"
                    @click="selectCourtType(t.id)"
                  >
                    <span>{{ t.name }}</span>
                    <AppIcon v-if="String(form.court_type_id) === String(t.id)" name="check" :size="14" />
                  </div>
                </div>
              </div>
            </div>

            <div class="field-group">
              <span class="field-label">Loại booking</span>
              <div class="custom-select-wrap" ref="bookingTypeSelect">
                <button
                  type="button"
                  class="custom-select-trigger"
                  :class="{ open: bookingTypeOpen }"
                  @click.stop="toggleBookingTypeDropdown"
                >
                  <span class="trigger-text">{{ selectedBookingTypeLabel }}</span>
                  <AppIcon name="chevronDown" :size="14" class="arrow" :class="{ open: bookingTypeOpen }" />
                </button>
                <div v-if="bookingTypeOpen" class="custom-select-menu">
                  <div
                    v-for="opt in bookingTypeOptions"
                    :key="opt.value"
                    class="custom-select-option"
                    :class="{ active: form.booking_type === opt.value }"
                    @click="selectBookingType(opt.value)"
                  >
                    <span>{{ opt.label }}</span>
                    <AppIcon v-if="form.booking_type === opt.value" name="check" :size="14" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 2: Application Days OR Date -->
          <template v-if="activeTab === 'weekly'">
            <div class="field-group">
              <span class="field-label">Ngày áp dụng trong tuần</span>
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
              <span class="field-label">Ngày áp dụng cụ thể</span>
              <input
                type="date"
                :value="form.holiday_date"
                required
                class="form-control-input"
                @input="updateFormField('holiday_date', $event.target.value)"
              />
            </label>
          </template>

          <!-- Section 3: Time Range -->
          <div class="form-grid">
            <label class="field-group">
              <span class="field-label">Giờ bắt đầu</span>
              <input
                type="time"
                :value="form.start_time"
                required
                class="form-control-input"
                @input="updateFormField('start_time', $event.target.value)"
              />
            </label>

            <label class="field-group">
              <span class="field-label">Giờ kết thúc</span>
              <input
                type="time"
                :value="form.end_time"
                required
                class="form-control-input"
                @input="updateFormField('end_time', $event.target.value)"
              />
            </label>
          </div>

          <!-- Section 4: Price & Notes -->
          <div class="form-grid" :class="{ 'full-width-price': activeTab === 'weekly' }">
            <label class="field-group">
              <span class="field-label">Giá / giờ (VNĐ)</span>
              <div class="modal-money-group">
                <input
                  type="number"
                  min="0"
                  step="any"
                  :value="form.price"
                  required
                  placeholder="150000"
                  class="modal-money-input"
                  @input="updateFormField('price', Number($event.target.value))"
                />
                <span class="modal-money-suffix">đ / giờ</span>
              </div>
            </label>

            <label v-if="activeTab !== 'weekly'" class="field-group">
              <span class="field-label">Ghi chú tên dịp / sự kiện</span>
              <input
                type="text"
                maxlength="255"
                :value="form.note"
                :placeholder="activeTabMeta.notePlaceholder"
                class="form-control-input"
                @input="updateFormField('note', $event.target.value)"
              />
            </label>
          </div>

          <!-- Section 5: Active Status Switch -->
          <label class="active-switch-card">
            <input
              type="checkbox"
              :checked="form.is_active"
              class="switch-checkbox"
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
                <span>Đang lưu...</span>
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
  data() {
    return {
      courtTypeOpen: false,
      bookingTypeOpen: false,
      bookingTypeOptions: [
        { value: 'all', label: 'Dùng chung (Tất cả)' },
        { value: 'single', label: 'Đặt lẻ' },
        { value: 'recurring', label: 'Đặt cố định' },
      ],
    };
  },
  computed: {
    selectedCourtTypeName() {
      const found = this.courtTypes.find((t) => String(t.id) === String(this.form.court_type_id));
      return found ? found.name : (this.courtTypes[0]?.name || 'Chọn loại sân...');
    },
    selectedBookingTypeLabel() {
      const found = this.bookingTypeOptions.find((opt) => opt.value === this.form.booking_type);
      return found ? found.label : 'Dùng chung (Tất cả)';
    },
  },
  mounted() {
    document.addEventListener('click', this.handleDocumentClick);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleDocumentClick);
  },
  methods: {
    handleDocumentClick(e) {
      if (this.$refs.courtTypeSelect && !this.$refs.courtTypeSelect.contains(e.target)) {
        this.courtTypeOpen = false;
      }
      if (this.$refs.bookingTypeSelect && !this.$refs.bookingTypeSelect.contains(e.target)) {
        this.bookingTypeOpen = false;
      }
    },
    toggleCourtTypeDropdown() {
      this.courtTypeOpen = !this.courtTypeOpen;
      this.bookingTypeOpen = false;
    },
    toggleBookingTypeDropdown() {
      this.bookingTypeOpen = !this.bookingTypeOpen;
      this.courtTypeOpen = false;
    },
    selectCourtType(id) {
      this.updateFormField('court_type_id', Number(id));
      this.courtTypeOpen = false;
    },
    selectBookingType(val) {
      this.updateFormField('booking_type', val);
      this.bookingTypeOpen = false;
    },
    updateFormField(field, val) {
      this.$emit('update:form', {
        ...this.form,
        [field]: val,
      });
    },
    toggleDay(dayVal) {
      const days = [...(this.form.apply_to_days || [])];
      const idx = days.indexOf(dayVal);
      if (idx >= 0) {
        days.splice(idx, 1);
      } else {
        days.push(dayVal);
      }
      this.updateFormField('apply_to_days', days);
    },
  },
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.pricing-edit-modal {
  background: #ffffff;
  border-radius: 14px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  width: 100%;
  max-width: 580px;
  overflow: visible;
  display: flex;
  flex-direction: column;
}

.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid #f1f5f9;
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
}

.close-icon-btn {
  background: transparent;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  color: #64748b;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.close-icon-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.modal-form-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  max-height: calc(85vh - 70px);
  overflow-y: visible;
}

.alert.error-alert {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fca5a5;
  color: #991b1b;
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
  position: relative;
}

.field-label {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}

.form-control-input {
  height: 38px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
  padding: 0 12px;
  font-size: 13.5px;
  font-weight: 500;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-control-input:focus {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

/* Custom Vue Dropdown */
.custom-select-wrap {
  position: relative;
  width: 100%;
}

.custom-select-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  height: 38px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.custom-select-trigger:hover {
  border-color: #94a3b8;
}

.custom-select-trigger.open {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

.trigger-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.custom-select-trigger .arrow {
  transition: transform 0.2s ease;
  color: #64748b;
  flex-shrink: 0;
  margin-left: 8px;
}

.custom-select-trigger .arrow.open {
  transform: rotate(180deg);
}

.custom-select-menu {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 100;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
  padding: 4px;
  max-height: 200px;
  overflow-y: auto;
}

.custom-select-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 13.5px;
  font-weight: 500;
  color: #334155;
  cursor: pointer;
  transition: background-color 0.12s ease;
}

.custom-select-option:hover {
  background: #f8fafc;
  color: #0f172a;
}

.custom-select-option.active {
  background: #f0fdf4;
  color: #16a34a;
  font-weight: 600;
}

.day-checkbox-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.day-pill-btn {
  display: inline-flex;
  align-items: center;
  padding: 7px 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.day-pill-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.day-pill-btn.selected {
  background: #16a34a;
  border-color: #16a34a;
  color: #ffffff;
}

/* Modal Money Addon Container */
.modal-money-group {
  display: flex;
  align-items: center;
  height: 38px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #ffffff;
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.modal-money-group:focus-within {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

.modal-money-input {
  flex: 1;
  min-width: 0;
  height: 100%;
  border: none !important;
  outline: none !important;
  background: transparent !important;
  padding: 0 8px 0 12px;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  text-align: right;
  -moz-appearance: textfield;
}

.modal-money-input::-webkit-outer-spin-button,
.modal-money-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.modal-money-suffix {
  display: flex;
  align-items: center;
  height: 100%;
  padding: 0 12px 0 4px;
  font-size: 12.5px;
  font-weight: 500;
  color: #64748b;
  background: #f8fafc;
  border-left: 1px solid #e2e8f0;
  white-space: nowrap;
  user-select: none;
}

.active-switch-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  cursor: pointer;
}

.switch-checkbox {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  accent-color: #16a34a;
  cursor: pointer;
}

.switch-card-text strong {
  display: block;
  font-size: 13px;
  color: #0f172a;
}

.switch-card-text p {
  margin: 2px 0 0 0;
  font-size: 12px;
  color: #64748b;
}

.modal-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 4px;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
}

.btn-cancel {
  height: 38px;
  padding: 0 16px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-cancel:hover {
  background: #f8fafc;
  color: #0f172a;
}

.btn-submit-primary {
  height: 38px;
  padding: 0 18px;
  border-radius: 8px;
  border: none;
  background: #16a34a;
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-submit-primary:hover:not(:disabled) {
  background: #15803d;
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

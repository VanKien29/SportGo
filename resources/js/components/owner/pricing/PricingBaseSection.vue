<template>
  <div class="base-price-section">
    <!-- Loading / Empty States -->
    <div v-if="isLoading" class="base-price-state-card">
      <div class="spinner-sm"></div>
      <span>Đang tải giá chung theo loại sân...</span>
    </div>
    <div v-else-if="!selectedClusterId" class="base-price-state-card">
      <span>Chưa chọn cụm sân để xem và cấu hình giá chung.</span>
    </div>
    <div v-else-if="!courtTypes.length" class="base-price-state-card">
      <span>Cụm sân này chưa có loại sân nào. Bạn cần thêm sân con trước khi đặt giá.</span>
    </div>

    <!-- Cards Grid -->
    <div v-else class="base-price-grid">
      <div
        v-for="type in courtTypes"
        :key="type.id"
        class="base-price-item-card"
        :class="{ 'is-saving': savingBasePriceId === type.id }"
      >
        <div class="card-type-header">
          <span class="type-title">{{ type.name }}</span>
        </div>

        <div class="card-input-block">
          <div class="money-input-group" :class="{ invalid: !isValidPrice(getDraftValue(type.id)) }">
            <input
              type="number"
              min="1"
              step="1000"
              :value="getDraftValue(type.id)"
              :disabled="savingBasePriceId === type.id"
              placeholder="100000"
              @input="onInput(type.id, $event.target.value)"
            />
            <span class="currency-unit">đ / giờ</span>
          </div>
          <span v-if="!isValidPrice(getDraftValue(type.id))" class="input-error-msg">
            Giá phải là số lớn hơn 0
          </span>
          <span v-else class="formatted-preview">
            {{ formatMoney(getDraftValue(type.id)) }} đ / giờ
          </span>
        </div>

        <div class="card-action-block">
          <button
            type="button"
            class="btn-save-base"
            :disabled="savingBasePriceId === type.id || !isValidPrice(getDraftValue(type.id))"
            @click="$emit('save-base-price', type)"
          >
            <template v-if="savingBasePriceId === type.id">
              <span class="spinner-xs"></span>
              <span>Đang lưu...</span>
            </template>
            <template v-else>
              <span>Lưu giá chung</span>
            </template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PricingBaseSection',
  props: {
    courtTypes: { type: Array, default: () => [] },
    basePriceDrafts: { type: Object, default: () => ({}) },
    basePrices: { type: Array, default: () => [] },
    savingBasePriceId: { type: [Number, String], default: null },
    isLoading: { type: Boolean, default: false },
    selectedClusterId: { type: [String, Number], default: '' },
  },
  emits: ['update-draft', 'save-base-price'],
  methods: {
    getDraftValue(typeId) {
      return this.basePriceDrafts[typeId] !== undefined ? this.basePriceDrafts[typeId] : '';
    },
    onInput(typeId, value) {
      this.$emit('update-draft', { courtTypeId: typeId, price: value });
    },
    isValidPrice(val) {
      return Number.isFinite(Number(val)) && Number(val) > 0;
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
.base-price-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.base-price-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 24px 16px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px dashed var(--admin-border, #cfded1);
  border-radius: 8px;
  color: var(--admin-muted, #2f3d34);
  font-size: 13.5px;
  font-weight: 400;
}

.spinner-sm {
  width: 16px;
  height: 16px;
  border: 2px solid var(--admin-border, #cfded1);
  border-top-color: var(--admin-primary, #22a653);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

.spinner-xs {
  width: 13px;
  height: 13px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.base-price-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px;
}

.base-price-item-card {
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border-soft, #e3ece4);
  border-radius: 10px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.base-price-item-card:hover {
  border-color: var(--admin-border, #cfded1);
}

.base-price-item-card.is-saving {
  opacity: 0.85;
  pointer-events: none;
}

.card-type-header {
  display: flex;
  align-items: center;
}

.type-title {
  font-size: 14px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.card-input-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.money-input-group {
  display: flex;
  align-items: center;
  border: 1px solid var(--admin-border, #cfded1);
  border-radius: 8px;
  background: var(--admin-surface, #ffffff);
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.money-input-group:focus-within {
  border-color: var(--admin-primary, #22a653);
  box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(34, 166, 83, 0.22));
}

.money-input-group.invalid {
  border-color: var(--admin-danger, #dc2626);
}

.money-input-group input {
  flex: 1;
  height: 38px;
  padding: 0 10px;
  border: none;
  background: transparent;
  font-size: 14px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
  outline: none;
}

.currency-unit {
  padding: 0 10px;
  font-size: 12px;
  font-weight: 400;
  color: var(--admin-faint, #45564a);
  background: var(--admin-bg, #eef6f0);
  height: 38px;
  display: flex;
  align-items: center;
  border-left: 1px solid var(--admin-border-soft, #e3ece4);
}

.input-error-msg {
  font-size: 11.5px;
  color: var(--admin-text, #101c15);
  font-weight: 400;
}

.formatted-preview {
  font-size: 11.5px;
  color: var(--admin-muted, #2f3d34);
  font-weight: 400;
}

.card-action-block {
  margin-top: 2px;
}

.btn-save-base {
  width: 100%;
  height: 34px;
  border-radius: 7px;
  border: none;
  background: var(--admin-primary, #22a653);
  color: var(--admin-primary-text, #ffffff);
  font-size: 12.5px;
  font-weight: 400;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-save-base:hover:not(:disabled) {
  background: var(--admin-primary-dark, #15733a);
}

.btn-save-base:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>

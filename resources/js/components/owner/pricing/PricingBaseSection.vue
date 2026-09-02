<template>
  <div class="base-price-section">
    <!-- Header title for section -->
    <div class="section-header-bar">
      <div class="title-with-badge">
        <h3 class="section-heading">Giá chung theo loại sân</h3>
        <span class="info-tag">Áp dụng mặc định cho tất cả khung giờ khi chưa có quy tắc giá nâng cao</span>
      </div>
    </div>

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

    <!-- Clean Modern List -->
    <div v-else class="base-price-groups">
      <section v-for="group in groupedCourtTypes" :key="group.id" class="base-price-category">
        <div class="category-heading">
          <div>
            <span class="category-eyebrow">DANH MỤC SÂN</span>
            <h4>{{ group.name }}</h4>
          </div>
          <span class="category-count">{{ group.types.length }} loại sân</span>
        </div>
        <div class="base-price-list">
      <div
        v-for="type in group.types"
        :key="type.id"
        class="base-price-row-card"
        :class="{ 'is-saving': savingBasePriceId === type.id }"
      >
        <div class="card-left-info">
          <div class="type-name-block">
            <span class="type-title">{{ type.name }}</span>
            <span class="type-subtitle">Giá đang áp dụng: <strong>{{ formatMoney(getDraftValue(type.id)) }} đ/giờ</strong></span>
          </div>
        </div>

        <div class="card-right-controls">
          <div class="currency-input-group" :class="{ invalid: !isValidPrice(getDraftValue(type.id)) }">
            <input
              type="number"
              min="0"
              step="any"
              :value="getDraftValue(type.id)"
              :disabled="savingBasePriceId === type.id || isWriteBlocked"
              placeholder="100000"
              class="currency-num-input"
              @input="onInput(type.id, $event.target.value)"
            />
            <span class="currency-unit-addon">đ / giờ</span>
          </div>

          <button
            type="button"
            class="btn-save-inline"
            :disabled="savingBasePriceId === type.id || isWriteBlocked || !isValidPrice(getDraftValue(type.id))"
            @click="$emit('save-base-price', type)"
          >
            <template v-if="savingBasePriceId === type.id">
              <span class="spinner-xs"></span>
              <span>Lưu...</span>
            </template>
            <template v-else>
              <AppIcon name="check" :size="14" />
              <span>Lưu giá</span>
            </template>
          </button>
        </div>
      </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PricingBaseSection',
  props: {
    courtTypes: { type: Array, default: () => [] },
    courtTypeGroups: { type: Array, default: () => [] },
    basePriceDrafts: { type: Object, default: () => ({}) },
    basePrices: { type: Array, default: () => [] },
    savingBasePriceId: { type: [Number, String], default: null },
    isLoading: { type: Boolean, default: false },
    selectedClusterId: { type: [String, Number], default: '' },
    isWriteBlocked: { type: Boolean, default: false },
  },
  emits: ['update-draft', 'save-base-price'],
  computed: {
    groupedCourtTypes() {
      if (this.courtTypeGroups.length) return this.courtTypeGroups;
      return this.courtTypes.length
        ? [{ id: 'all', name: 'Tất cả danh mục sân', types: this.courtTypes }]
        : [];
    },
  },
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
  gap: 14px;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--admin-border-light, #f1f5f9);
}

.section-header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.section-heading {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--admin-text, #0f172a);
}

.info-tag {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
  background: transparent;
  padding: 0;
  border: none;
}

.base-price-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 20px 16px;
  background: var(--admin-bg-soft, #f8fafc);
  border: 1px dashed var(--admin-border, #cbd5e1);
  border-radius: 10px;
  color: var(--admin-muted, #64748b);
  font-size: 13.5px;
}

.spinner-sm {
  width: 16px;
  height: 16px;
  border: 2px solid var(--admin-border, #cbd5e1);
  border-top-color: var(--admin-primary, #16a34a);
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

.base-price-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.base-price-groups {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.base-price-category {
  min-width: 0;
  padding: 14px;
  border: 1px solid var(--admin-border-soft, #e3ece4);
  border-radius: 10px;
  background: var(--admin-bg-soft, #f8fafc);
}

.category-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}

.category-eyebrow {
  display: block;
  margin-bottom: 3px;
  color: var(--admin-muted, #64748b);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
}

.category-heading h4 {
  margin: 0;
  color: var(--admin-text, #0f172a);
  font-size: 14px;
  font-weight: 700;
}

.category-count {
  flex-shrink: 0;
  color: var(--admin-muted, #64748b);
  font-size: 11px;
}

.base-price-row-card {
  background: transparent;
  border: none;
  border-bottom: 1px solid var(--admin-border-light, #f1f5f9);
  border-radius: 0;
  padding: 12px 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.base-price-row-card:last-child {
  border-bottom: none;
}

.base-price-row-card.is-saving {
  opacity: 0.75;
  pointer-events: none;
}

.card-left-info {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.type-name-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.type-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--admin-text, #0f172a);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.type-subtitle {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
}

.type-subtitle strong {
  color: #16a34a;
  font-weight: 600;
}

.card-right-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

/* Standalone Input Addon Box */
.currency-input-group {
  display: flex;
  align-items: center;
  width: 175px;
  height: 36px;
  border: none !important;
  border-radius: 8px;
  background: var(--admin-bg-soft, #f8fafc);
  overflow: hidden;
  box-shadow: none !important;
  outline: none !important;
}

.currency-input-group:focus-within {
  box-shadow: none !important;
  border: none !important;
  outline: none !important;
}

.currency-input-group.invalid {
  box-shadow: 0 0 0 1px #dc2626;
}

.currency-num-input {
  flex: 1;
  min-width: 0;
  height: 100%;
  padding: 0 6px 0 10px;
  border: none !important;
  outline: none !important;
  background: transparent !important;
  box-shadow: none !important;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--admin-text, #0f172a);
  text-align: right;
  -moz-appearance: textfield;
}

.currency-num-input::-webkit-outer-spin-button,
.currency-num-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.currency-unit-addon {
  display: flex;
  align-items: center;
  height: 100%;
  padding: 0 10px 0 2px;
  font-size: 12px;
  font-weight: 500;
  color: var(--admin-muted, #64748b);
  background: transparent !important;
  border: none !important;
  border-left: none !important;
  white-space: nowrap;
  user-select: none;
}

.btn-save-inline {
  height: 36px;
  padding: 0 14px;
  border-radius: 8px;
  border: none;
  background: #16a34a;
  color: #ffffff;
  font-size: 13px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  white-space: nowrap;
  transition: background-color 0.15s ease;
}

.btn-save-inline:hover:not(:disabled) {
  background: #15803d;
}

.btn-save-inline:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .base-price-groups {
    grid-template-columns: 1fr;
  }

  .base-price-row-card {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  .card-right-controls {
    justify-content: flex-end;
  }
}
</style>

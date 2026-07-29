<template>
  <Teleport to="body">
    <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
      <section class="confirm-modal service-edit-modal">
        <header class="modal-head">
          <div class="head-title-wrap">
            <h2 class="modal-title">
              {{ form.id ? 'Sửa dịch vụ/sản phẩm tại sân' : 'Thêm dịch vụ/sản phẩm tại sân' }}
            </h2>
          </div>
          <button type="button" class="close-icon-btn" title="Đóng modal" @click="$emit('close')">
            ✕
          </button>
        </header>

        <form class="modal-form-body" @submit.prevent="$emit('save-form')">
          <div v-if="errorMessage" class="alert error-alert">
            <span>{{ errorMessage }}</span>
          </div>

          <div class="field-group">
            <span class="field-label">
              Tên sản phẩm/Dịch vụ <span class="required">*</span>
            </span>
            <input
              type="text"
              :value="form.name"
              placeholder="Ví dụ: Nước ngọt Sting dâu, Thuê vợt Yonex Astrox 88D..."
              required
              @input="onFieldInput('name', $event.target.value)"
            />
            <small v-if="validationErrors.name" class="field-error">{{ validationErrors.name }}</small>
          </div>

          <div class="form-grid">
            <div class="field-group">
              <span class="field-label">
                Phân loại danh mục <span class="required">*</span>
              </span>
              <select
                :value="form.category_id"
                required
                @change="onFieldInput('category_id', $event.target.value)"
              >
                <option value="">-- Chọn danh mục --</option>
                <option v-for="cat in dbCategories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
              <small v-if="validationErrors.category_id" class="field-error">{{ validationErrors.category_id }}</small>
            </div>

            <div class="field-group">
              <span class="field-label">
                Trạng thái kinh doanh <span class="required">*</span>
              </span>
              <select
                :value="form.status"
                required
                @change="onFieldInput('status', $event.target.value)"
              >
                <option value="active">Đang kinh doanh</option>
                <option value="inactive">Tạm ngưng</option>
                <option value="out_of_stock">Hết hàng</option>
              </select>
            </div>
          </div>

          <div class="form-grid">
            <div class="field-group">
              <span class="field-label">
                Giá (VNĐ) <span class="required">*</span>
              </span>
              <input
                type="number"
                min="0"
                step="500"
                :value="form.price"
                placeholder="Ví dụ: 15000"
                required
                @input="onFieldInput('price', $event.target.value ? Number($event.target.value) : '')"
              />
              <small v-if="validationErrors.price" class="field-error">{{ validationErrors.price }}</small>
            </div>

            <div class="field-group">
              <span class="field-label">
                Đơn vị tính <span class="required">*</span>
              </span>
              <input
                type="text"
                list="modal-units-list"
                :value="form.unit"
                placeholder="Ví dụ: chai, lượt, tiếng, quả..."
                required
                @input="onFieldInput('unit', $event.target.value)"
              />
              <datalist id="modal-units-list">
                <option value="chai"></option>
                <option value="lượt"></option>
                <option value="tiếng"></option>
                <option value="quả"></option>
                <option value="cái"></option>
                <option value="buổi"></option>
                <option value="ngày"></option>
              </datalist>
              <small v-if="validationErrors.unit" class="field-error">{{ validationErrors.unit }}</small>
            </div>
          </div>

          <div class="field-group">
            <span class="field-label">Mô tả chi tiết</span>
            <textarea
              rows="3"
              :value="form.description"
              placeholder="Nhập mô tả về sản phẩm (Ví dụ: thương hiệu, hương vị, chính sách thuê cọc...)"
              @input="onFieldInput('description', $event.target.value)"
            ></textarea>
          </div>

          <!-- Footer Actions -->
          <footer class="modal-actions">
            <button type="button" class="btn-cancel" @click="$emit('close')">
              Hủy bỏ
            </button>
            <button type="submit" class="btn-submit-primary" :disabled="saving">
              <template v-if="saving">
                <span class="spinner-xs"></span>
                <span>Đang lưu...</span>
              </template>
              <template v-else>
                <span>{{ form.id ? 'Cập nhật' : 'Lưu dịch vụ mới' }}</span>
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
  name: 'ServiceFormModal',
  props: {
    show: { type: Boolean, default: false },
    form: { type: Object, required: true },
    dbCategories: { type: Array, default: () => [] },
    validationErrors: { type: Object, default: () => ({}) },
    saving: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
  },
  emits: ['close', 'save-form', 'update:form', 'clear-error'],
  methods: {
    onFieldInput(key, val) {
      this.$emit('update:form', { ...this.form, [key]: val });
      this.$emit('clear-error', key);
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

.service-edit-modal {
  background: var(--admin-surface, #ffffff);
  border-radius: 14px;
  max-width: 540px;
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
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 24px;
  background: var(--admin-bg-soft, #f7fbf5);
}

.head-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 400;
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
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: calc(85vh - 80px);
  overflow-y: auto;
}

.alert.error-alert {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  border-radius: 8px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border, #cfded1);
  color: var(--admin-text, #101c15);
  font-size: 13px;
  font-weight: 400;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
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
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.required {
  color: var(--admin-text, #101c15);
}

.field-error {
  font-size: 11.5px;
  color: var(--admin-text, #101c15);
  font-weight: 400;
}

.field-group input[type="text"],
.field-group input[type="number"],
.field-group select,
.field-group textarea {
  border-radius: 8px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  padding: 0 12px;
  font-size: 13.5px;
  font-weight: 400;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.field-group input[type="text"],
.field-group input[type="number"],
.field-group select {
  height: 40px;
}

.field-group textarea {
  padding: 10px 12px;
}

.field-group input:focus,
.field-group select:focus,
.field-group textarea:focus {
  border-color: var(--admin-primary, #22a653);
  box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(34, 166, 83, 0.22));
}

.modal-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 8px;
  padding-top: 16px;
}

.btn-cancel {
  height: 38px;
  padding: 0 16px;
  border-radius: 8px;
  border: 1px solid var(--admin-border, #cfded1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #101c15);
  font-size: 13px;
  font-weight: 400;
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
  font-weight: 400;
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

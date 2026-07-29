<template>
  <section class="categories-container animate-fade-in">
    <!-- Alert Thông báo -->
    <div v-if="error" class="alert error animate-slide-in" style="margin-bottom: 20px;">
      {{ error }}
      <button class="alert-close" @click="error = ''">&times;</button>
    </div>
    <div v-if="success" class="alert success animate-slide-in" style="margin-bottom: 20px;">
      {{ success }}
      <button class="alert-close" @click="success = ''">&times;</button>
    </div>

    <!-- SaaS Filter Bar -->
    <SaaSFilterBar
      v-model="statusFilter"
      :tabs="statusTabs"
      v-model:search="searchQuery"
      searchPlaceholder="Tìm kiếm tên danh mục..."
    />

    <!-- Table content -->
    <div class="table-container">
      <div v-if="loading" class="state card" style="padding: 48px; text-align: center; color: #64748b; background: var(--admin-surface, #fff); border-radius: 12px; border: 1px solid var(--admin-border, #e2e8f0);">
        <div class="spinner" style="margin: 0 auto 12px; border: 3px solid rgba(0,0,0,0.1); border-top-color: var(--primary-color, #10b981); width: 28px; height: 28px; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        Đang tải danh sách danh mục...
      </div>
      
      <SaaSTable v-else :columns="columns" :data="filteredCategories" clickable @row-click="row => openFormModal(row)">
        <template #name="{ row }">
          <div class="name-col-cell" style="display: flex; flex-direction: column; gap: 2px;">
            <span style="font-weight: 400; color: var(--admin-text, #1e293b);">{{ row.name }}</span>
            <span v-if="row.description" style="font-size: 12px; color: var(--admin-muted, #64748b); line-height: 1.4;">{{ row.description }}</span>
          </div>
        </template>
        
        <template #status="{ row }">
          <span class="status-badge" :class="'status-is-' + row.status">
            {{ row.status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}
          </span>
        </template>
        
        <template #actions="{ row }">
          <div class="table-actions" @click.stop style="display: flex; align-items: center; gap: 8px; justify-content: flex-end;">
            <ActionIconButton icon="pencil" label="Chỉnh sửa" size="sm" @click="openFormModal(row)" />
            <ActionIconButton
              :icon="row.status === 'active' ? 'power' : 'check'"
              :label="row.status === 'active' ? 'Tắt danh mục' : 'Kích hoạt'"
              :variant="row.status === 'active' ? 'danger' : 'success'"
              size="sm"
              @click="toggleCategoryStatus(row)"
            />
            <ActionIconButton icon="trash" label="Xóa danh mục" variant="danger" size="sm" @click="deleteCategory(row)" />
          </div>
        </template>

        <template #empty>
          Không tìm thấy danh mục dịch vụ nào khớp với điều kiện lọc.
        </template>
      </SaaSTable>
    </div>

    <!-- Modal Form Thêm/Sửa Danh mục -->
    <div v-if="showFormModal" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeFormModal)">
      <form class="modal card" @submit.prevent="saveForm" @mousedown.stop style="max-width: 500px; width: 100%;">
        <div class="modal-header">
          <h3>{{ form.id ? 'Sửa danh mục dịch vụ hệ thống' : 'Thêm danh mục dịch vụ hệ thống' }}</h3>
          <button class="btn-close" type="button" @click="closeFormModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
          
          <div class="form-group">
            <label class="form-label">Tên danh mục</label>
            <input
              v-model.trim="form.name"
              type="text"
              class="form-control"
              placeholder="Ví dụ: Nước uống, Thuê vợt, Đồ ăn nhẹ..."
              @input="clearError('name')"
            />
            <small v-if="validationErrors.name" class="field-error">{{ validationErrors.name }}</small>
          </div>

          <div class="form-group">
            <label class="form-label">Trạng thái hoạt động</label>
            <div class="custom-select-container">
              <div 
                class="custom-select-trigger" 
                :class="{ open: isStatusSelectOpen }"
                @click="toggleStatusSelect"
              >
                <span class="option-text">{{ formStatusText }}</span>
                <AppIcon name="chevronDown" size="14" class="select-arrow-icon" :class="{ rotated: isStatusSelectOpen }" />
              </div>
              <div v-if="isStatusSelectOpen" class="custom-select-options-wrapper">
                <div 
                  class="custom-select-option" 
                  :class="{ active: form.status === 'active' }"
                  @click="selectFormStatus('active')"
                >
                  <span class="option-text">Kích hoạt</span>
                </div>
                <div 
                  class="custom-select-option" 
                  :class="{ active: form.status === 'inactive' }"
                  @click="selectFormStatus('inactive')"
                >
                  <span class="option-text">Tạm ngưng</span>
                </div>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Mô tả chi tiết</label>
            <textarea
              v-model.trim="form.description"
              class="form-control"
              rows="3"
              placeholder="Mô tả công dụng hoặc phạm vi áp dụng của danh mục này..."
            ></textarea>
          </div>

        </div>
        <div class="modal-footer" style="margin-top: 12px;">
          <button class="btn secondary" type="button" @click="closeFormModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu' }}
          </button>
        </div>
      </form>
    </div>
    <!-- Floating Add Button -->
    <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="btn-float-add" @click="openFormModal()">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Thêm danh mục</span>
      </button>
    </div>
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import SaaSFilterBar from '../../components/ui/SaaSFilterBar.vue';
import SaaSTable from '../../components/ui/SaaSTable.vue';
import { adminServiceCategoryService } from '../../services/adminServiceCategory.js';

export default {
  name: 'AdminServiceCategories',
  components: { ActionIconButton, AppIcon, TableActionGroup, SaaSFilterBar, SaaSTable },
  data() {
    return {
      categories: [],
      loading: false,
      saving: false,
      showFormModal: false,
      error: '',
      success: '',
      searchQuery: '',
      statusFilter: 'all',
      form: this.emptyForm(),
      validationErrors: {},
      mousedownWasOnBackdrop: false,
      isStatusSelectOpen: false,
      showScrollTop: false,
      statusTabs: [
        { value: 'all', label: 'Tất cả' },
        { value: 'active', label: 'Đang hoạt động' },
        { value: 'inactive', label: 'Tạm ngưng' }
      ],
      columns: [
        { key: 'name', label: 'Tên danh mục' },
        { key: 'status', label: 'Trạng thái' },
        { key: 'actions', label: '', align: 'right' }
      ]
    };
  },
  computed: {
    filteredCategories() {
      return this.categories.filter(item => {
        const matchesSearch = !this.searchQuery || item.name.toLowerCase().includes(this.searchQuery.toLowerCase());
        const matchesStatus = this.statusFilter === 'all' || item.status === this.statusFilter;
        return matchesSearch && matchesStatus;
      });
    },
    formStatusText() {
      switch (this.form.status) {
        case 'active': return 'Kích hoạt';
        case 'inactive': return 'Tạm ngưng';
        default: return '';
      }
    }
  },
  mounted() {
    this.fetchCategories();
  },
  methods: {
    handleBackdropMousedown(event) {
      this.mousedownWasOnBackdrop = event.target === event.currentTarget;
    },
    handleBackdropClick(event, closeFn) {
      if (this.mousedownWasOnBackdrop && event.target === event.currentTarget) {
        closeFn();
      }
      this.mousedownWasOnBackdrop = false;
    },
    async fetchCategories() {
      this.loading = true;
      this.error = '';
      try {
        const res = await adminServiceCategoryService.list();
        this.categories = res.data || [];
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách danh mục dịch vụ.';
      } finally {
        this.loading = false;
      }
    },
    emptyForm() {
      return {
        id: null,
        name: '',
        status: 'active',
        description: ''
      };
    },
    openFormModal(item = null) {
      this.validationErrors = {};
      this.isStatusSelectOpen = false;
      if (item) {
        this.form = {
          id: item.id,
          name: item.name,
          status: item.status,
          description: item.description || ''
        };
      } else {
        this.form = this.emptyForm();
      }
      this.showFormModal = true;
    },
    closeFormModal() {
      this.showFormModal = false;
      this.form = this.emptyForm();
      this.isStatusSelectOpen = false;
    },
    toggleStatusSelect() {
      this.isStatusSelectOpen = !this.isStatusSelectOpen;
    },
    selectFormStatus(status) {
      this.form.status = status;
      this.isStatusSelectOpen = false;
    },
    clearError(field) {
      if (this.validationErrors[field]) {
        delete this.validationErrors[field];
      }
    },
    validate() {
      const errors = {};
      if (!this.form.name) errors.name = 'Vui lòng nhập tên danh mục';
      this.validationErrors = errors;
      return Object.keys(errors).length === 0;
    },
    async saveForm() {
      if (!this.validate()) return;
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        if (this.form.id) {
          const res = await adminServiceCategoryService.update(this.form.id, this.form);
          const idx = this.categories.findIndex(c => c.id === this.form.id);
          if (idx !== -1) {
            this.categories[idx] = res.data;
          }
          this.success = 'Cập nhật danh mục dịch vụ thành công!';
        } else {
          const res = await adminServiceCategoryService.create(this.form);
          this.categories.unshift(res.data);
          this.success = 'Thêm danh mục dịch vụ mới thành công!';
        }
        this.closeFormModal();
      } catch (err) {
        if (err.errors && err.errors.name) {
          this.validationErrors.name = err.errors.name[0];
        } else {
          this.error = err.message || 'Lỗi khi lưu danh mục.';
        }
      } finally {
        this.saving = false;
      }
    },
    async toggleCategoryStatus(item) {
      this.error = '';
      this.success = '';
      try {
        const res = await adminServiceCategoryService.toggleStatus(item.id);
        const idx = this.categories.findIndex(c => c.id === item.id);
        if (idx !== -1) {
          this.categories[idx].status = res.data.status;
        }
        this.success = `Đã chuyển đổi trạng thái danh mục "${item.name}" thành công.`;
      } catch (err) {
        this.error = err.message || 'Lỗi khi cập nhật trạng thái.';
      }
    },
    async deleteCategory(item) {
      if (!confirm(`Bạn có chắc chắn muốn xóa danh mục "${item.name}"?\nHành động này chỉ được phép nếu không có dịch vụ nào đang sử dụng danh mục này.`)) return;
      this.error = '';
      this.success = '';
      try {
        const res = await adminServiceCategoryService.delete(item.id);
        if (res.success === false) {
          this.error = res.message;
        } else {
          this.categories = this.categories.filter(c => c.id !== item.id);
          this.success = `Đã xóa danh mục "${item.name}" thành công.`;
        }
      } catch (err) {
        this.error = err.message || 'Lỗi khi xóa danh mục.';
      }
    }
  }
};
</script>

<style scoped>
.categories-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 1000px;
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
}

.table-container {
  width: 100%;
  min-width: 0;
}

.badge {
  font-size: 11.5px;
  font-weight: 400;
  padding: 4px 10px;
  border-radius: 4px;
  display: inline-block;
}
.badge.active {
  background: #ecfdf5;
  color: #10b981;
}
.badge.inactive {
  background: #f1f5f9;
  color: #64748b;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Animations */
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}
.animate-slide-in {
  animation: slideIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slideIn {
  from { transform: translateY(-10px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

:deep(.dark) .badge.active {
  background: rgba(16, 185, 129, 0.15);
}
:deep(.dark) .badge.inactive {
  background: rgba(255, 255, 255, 0.05);
}

/* Custom Select Dropdown */
.custom-select-container {
    position: relative;
    width: 100%;
    user-select: none;
}

.custom-select-trigger {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--sg-border, #e2e8f0);
    background: var(--sg-surface, #ffffff);
    font-size: 14px;
    color: var(--sg-text, #101c15);
    cursor: pointer;
    transition: all 0.2s ease;
    height: 42px;
    box-sizing: border-box;
}

.custom-select-trigger:focus-within,
.custom-select-trigger.open {
    border-color: #0f172a;
}

.select-arrow-icon {
    width: 16px;
    height: 16px;
    color: rgba(15, 23, 42, 0.4);
    transition: transform 0.2s ease;
}

.select-arrow-icon.rotated {
    transform: rotate(180deg);
}

.custom-select-options-wrapper {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: var(--sg-surface, #ffffff);
    border: 1px solid var(--sg-border, #e2e8f0);
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    z-index: 1010;
    max-height: 220px;
    overflow-y: auto;
    padding: 4px;
    animation: slideDown 0.15s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.custom-select-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    color: var(--sg-text, #101c15) !important;
    cursor: pointer;
    transition: background 0.15s ease;
}

.custom-select-option:hover {
    background: rgba(0, 0, 0, 0.03) !important;
}

.custom-select-option.active {
    background: rgba(15, 23, 42, 0.05) !important;
    color: #0f172a !important;
    font-weight: 400;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 13px;
    font-weight: 500;
    padding: 0 !important;
    border-radius: 0 !important;
    background-color: transparent !important;
}

.status-is-active {
    color: var(--admin-primary-dark, #15803d) !important;
}

[data-theme="dark"] .status-is-active {
    color: #34d399 !important;
}

.status-is-inactive {
    color: var(--admin-danger, #dc2626) !important;
}

/* Floating Add Button */
.floating-add-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9998;
    transition: right 0.25s ease;
}
.floating-add-container.has-scroll {
    right: 86px;
}
.btn-float-add {
    width: 44px;
    height: 44px;
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #10b981;
    color: #fff;
    border: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    white-space: nowrap;
    padding: 0 12px;
}
.btn-float-add .btn-float-text {
    max-width: 0;
    opacity: 0;
    margin-left: 0;
    font-weight: 400;
    font-size: 13px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
}
.btn-float-add:hover {
    width: 145px;
    justify-content: flex-start;
    padding-left: 14px;
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    background-color: #059669;
}
.btn-float-add:hover .btn-float-text {
    max-width: 100px;
    opacity: 1;
    margin-left: 6px;
}
@media (max-width: 768px) {
    .floating-add-container {
        bottom: 20px;
        right: 20px;
    }
    .floating-add-container.has-scroll {
        right: 72px;
    }
    .btn-float-add {
        width: 40px;
        height: 40px;
        border-radius: 20px;
        padding: 0 10px;
    }
    .btn-float-add:hover {
        width: 130px;
        padding-left: 12px;
    }
    .btn-float-add:hover .btn-float-text {
        max-width: 80px;
    }
}
</style>

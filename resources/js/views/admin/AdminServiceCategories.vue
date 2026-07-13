<template>
  <section class="page admin-categories-page animate-fade-in" style="padding: 24px;">
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
    >
      <template #actions>
        <button class="btn btn-primary" type="button" @click="openFormModal()" style="height: 38px; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px;">
          <AppIcon name="plus" size="16" />
          <span>Thêm danh mục</span>
        </button>
      </template>
    </SaaSFilterBar>

    <!-- Table content -->
    <div class="table-container" style="margin-top: 16px;">
      <div v-if="loading" class="state card" style="padding: 48px; text-align: center; color: #64748b; background: var(--admin-surface, #fff); border-radius: 12px; border: 1px solid var(--admin-border, #e2e8f0);">
        <div class="spinner" style="margin: 0 auto 12px; border: 3px solid rgba(0,0,0,0.1); border-top-color: var(--primary-color, #10b981); width: 28px; height: 28px; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        Đang tải danh sách danh mục...
      </div>
      
      <SaaSTable v-else :columns="columns" :data="filteredCategories">
        <template #name="{ row }">
          <span style="font-weight: 600; color: var(--admin-text, #1e293b);">{{ row.name }}</span>
        </template>
        
        <template #status="{ row }">
          <span class="badge" :class="row.status">
            {{ row.status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}
          </span>
        </template>
        
        <template #description="{ row }">
          <span class="desc-text" :title="row.description" style="max-width: 350px; display: inline-block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; color: var(--admin-muted, #64748b);">
            {{ row.description || '-' }}
          </span>
        </template>
        
        <template #actions="{ row }">
          <TableActionGroup>
            <ActionIconButton icon="pencil" label="Chỉnh sửa" @click="openFormModal(row)" />
            <ActionIconButton
              :icon="row.status === 'active' ? 'power' : 'check'"
              :label="row.status === 'active' ? 'Tắt danh mục' : 'Kích hoạt'"
              :variant="row.status === 'active' ? 'danger' : 'success'"
              @click="toggleCategoryStatus(row)"
            />
            <ActionIconButton icon="trash" label="Xóa danh mục" variant="danger" @click="deleteCategory(row)" />
          </TableActionGroup>
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
            <label class="form-label">Tên danh mục <span class="text-danger">*</span></label>
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
            <label class="form-label">Trạng thái hoạt động <span class="text-danger">*</span></label>
            <select v-model="form.status" class="form-control">
              <option value="active">Kích hoạt</option>
              <option value="inactive">Tạm ngưng</option>
            </select>
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
      statusTabs: [
        { value: 'all', label: 'Tất cả' },
        { value: 'active', label: 'Đang hoạt động' },
        { value: 'inactive', label: 'Tạm ngưng' }
      ],
      columns: [
        { key: 'name', label: 'Tên danh mục' },
        { key: 'status', label: 'Trạng thái' },
        { key: 'description', label: 'Mô tả' },
        { key: 'actions', label: 'Thao tác', align: 'center', style: { width: '120px' } }
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
.table-container {
  width: 100%;
  min-width: 0;
}

.badge {
  font-size: 11.5px;
  font-weight: 700;
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
</style>

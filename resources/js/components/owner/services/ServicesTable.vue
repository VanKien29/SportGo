<template>
  <div class="services-table-section">
    <!-- State Cards -->
    <div v-if="isLoading" class="table-state-card">
      <div class="spinner-sm"></div>
      <span>Đang tải danh sách dịch vụ...</span>
    </div>

    <div v-else-if="!selectedCluster" class="table-state-card">
      <span>Vui lòng chọn cụm sân để quản lý danh sách dịch vụ tại sân.</span>
    </div>

    <div v-else-if="!services.length" class="table-state-card">
      <span>Chưa có dịch vụ hoặc sản phẩm nào được tạo.</span>
    </div>

    <!-- Data Table Container -->
    <div v-else class="services-table-wrapper">
      <table class="services-data-table">
        <thead>
          <tr>
            <th>Tên sản phẩm/Dịch vụ</th>
            <th>Phân loại</th>
            <th class="money-col">Giá bán/thuê</th>
            <th>ĐVT</th>
            <th>Trạng thái</th>
            <th>Mô tả</th>
            <th class="action-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in services" :key="item.id" :class="{ 'row-inactive': item.status !== 'active' }">
            <td class="cell-name">
              <span class="service-name">{{ item.name }}</span>
            </td>
            <td class="cell-category">
              <span>{{ item.category?.name || 'Chưa phân loại' }}</span>
            </td>
            <td class="money-col cell-price">
              <span class="price-value">{{ formatPrice(item.price) }}</span>
            </td>
            <td class="cell-unit">
              <span class="unit-text">{{ item.unit }}</span>
            </td>
            <td class="cell-status">
              <span class="status-text">
                {{ getStatusLabel(item.status) }}
              </span>
            </td>
            <td class="cell-desc">
              <span class="desc-text" :title="item.description">
                {{ item.description || '-' }}
              </span>
            </td>
            <td class="action-col">
              <div class="table-actions">
                <button
                  type="button"
                  class="action-btn edit-btn"
                  title="Chỉnh sửa thông tin"
                  @click="$emit('open-edit-modal', item)"
                >
                  Sửa
                </button>
                <button
                  type="button"
                  class="action-btn toggle-btn"
                  :title="item.status === 'active' ? 'Tạm ngưng kinh doanh' : 'Kích hoạt kinh doanh'"
                  @click="$emit('toggle-status', item)"
                >
                  {{ item.status === 'active' ? 'Tắt' : 'Bật' }}
                </button>
                <button
                  type="button"
                  class="action-btn delete-btn"
                  title="Xóa dịch vụ"
                  @click="$emit('delete-item', item)"
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
  name: 'ServicesTable',
  props: {
    services: { type: Array, default: () => [] },
    selectedCluster: { type: Object, default: null },
    isLoading: { type: Boolean, default: false },
  },
  emits: ['open-edit-modal', 'toggle-status', 'delete-item'],
  methods: {
    getStatusLabel(status) {
      return {
        active: 'Kinh doanh',
        inactive: 'Tạm ngưng',
        out_of_stock: 'Hết hàng',
      }[status] || status;
    },
    formatPrice(val) {
      if (!val && val !== 0) return '0 đ';
      return new Intl.NumberFormat('vi-VN').format(val) + ' đ';
    },
  },
};
</script>

<style scoped>
.services-table-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
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
.services-table-wrapper {
  overflow-x: auto;
  border: none;
  border-radius: 0;
}

.services-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.services-data-table th {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-weight: 400;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 12px 14px;
  border-bottom: none;
}

.services-data-table td {
  padding: 12px 14px;
  border-bottom: none;
  color: var(--admin-text, #101c15);
  font-weight: 400;
  vertical-align: middle;
}

.services-data-table tbody tr {
  transition: background-color 0.12s ease;
}

.services-data-table tbody tr:hover {
  background: var(--admin-hover, #edf7ed);
}

.services-data-table tbody tr.row-inactive {
  opacity: 0.6;
}

.service-name {
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

.unit-text {
  color: var(--admin-text, #101c15);
  font-weight: 400;
}

.status-text {
  font-weight: 400;
  font-size: 12.5px;
  color: var(--admin-text, #101c15);
}

.desc-text {
  max-width: 260px;
  display: inline-block;
  text-overflow: ellipsis;
  overflow: hidden;
  white-space: nowrap;
  color: var(--admin-text, #101c15);
  font-weight: 400;
}

.action-col {
  text-align: center;
  width: 140px;
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

@media (max-width: 768px) {
  .services-table-wrapper {
    overflow-x: auto;
  }
}
</style>

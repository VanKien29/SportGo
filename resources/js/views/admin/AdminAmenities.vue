<template>
    <div class="amenities-container">
        <header class="page-header">
            <div>
                <h2>Quản lý tiện ích chung</h2>
                <p>Cấu hình các tiện ích hiển thị cho cụm sân và hồ sơ vận hành.</p>
            </div>
            <button class="btn btn-primary" @click="openCreateModal">
                <AppIcon name="plus" size="17" />
                <span>Thêm tiện ích</span>
            </button>
        </header>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state card">
            <div class="spinner"></div>
            <p>Đang tải danh sách tiện ích...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state card">
            <p class="error-message">{{ error }}</p>
            <ActionIconButton icon="refresh" label="Thử lại" @click="fetchAmenities" />
        </div>

        <!-- Empty State -->
        <div v-else-if="amenities.length === 0" class="empty-state card">
            <p>Chưa có tiện ích nào được cấu hình trên hệ thống.</p>
            <button class="btn btn-primary" @click="openCreateModal">
                <AppIcon name="plus" size="17" />
                <span>Thêm ngay</span>
            </button>
        </div>

        <!-- Table View -->
        <div v-else class="card table-card animate-fade-in">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">#</th>
                            <th style="width: 50%;">Tên tiện ích</th>
                            <th class="status-header text-center" style="width: 20%;">Trạng thái</th>
                            <th class="text-center actions-header" style="width: 20%;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in amenities" :key="item.id">
                            <td class="text-muted font-mono">{{ idx + 1 }}</td>
                            <td class="font-bold amenity-name-cell">
                                <span class="name-text">{{ item.name }}</span>
                            </td>
                            <td class="status-cell text-center">
                                <span class="status-badge" :class="item.is_active ? 'status-active' : 'status-locked'">
                                    {{ item.is_active ? 'Đang hoạt động' : 'Tạm khóa' }}
                                </span>
                            </td>
                            <td class="text-center actions-cell">
                                <TableActionGroup>
                                    <ActionIconButton icon="pencil" label="Sửa tiện ích" @click="openEditModal(item)" />
                                    <ActionIconButton icon="trash" label="Xóa tiện ích" variant="danger" @click="confirmDelete(item)" />
                                </TableActionGroup>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
            <div class="modal card">
                <div class="modal-header">
                    <h3>
                        {{ editingId ? "Cập nhật tiện ích" : "Thêm tiện ích mới" }}
                    </h3>
                    <button class="btn-close" @click="closeModal">
                        &times;
                    </button>
                </div>
                <form @submit.prevent="handleSubmit">
                    <div class="modal-body">
                        <div v-if="modalError" class="alert alert-danger">
                            {{ modalError }}
                        </div>

                        <div class="form-group">
                            <label for="name">
                                Tên tiện ích <span class="required">*</span>
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                placeholder="Ví dụ: Wifi, Gửi xe, Tắm nóng lạnh..."
                                required
                            />
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                />
                                <span>Kích hoạt hoạt động</span>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline"
                            @click="closeModal"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="submitting"
                        >
                            {{ submitting ? "Đang lưu..." : "Lưu lại" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import TableActionGroup from "../../components/TableActionGroup.vue";
import { amenityService } from "../../services/amenityService";

export default {
    name: "AdminAmenities",
    components: { ActionIconButton, AppIcon, TableActionGroup },
    data() {
        return {
            amenities: [],
            loading: true,
            error: null,
            showModal: false,
            editingId: null,
            submitting: false,
            modalError: null,
            form: {
                name: "",
                is_active: true,
            },
        };
    },
    methods: {
        async fetchAmenities() {
            this.loading = true;
            this.error = null;
            try {
                const res = await amenityService.getAll(false); // lấy tất cả tiện ích
                this.amenities = res.data || [];
            } catch (err) {
                this.error = err.message || "Lỗi khi tải danh sách tiện ích.";
            } finally {
                this.loading = false;
            }
        },
        openCreateModal() {
            this.editingId = null;
            this.modalError = null;
            this.form = {
                name: "",
                is_active: true,
            };
            this.showModal = true;
        },
        openEditModal(item) {
            this.editingId = item.id;
            this.modalError = null;
            this.form = {
                name: item.name,
                is_active: !!item.is_active,
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.editingId = null;
            this.modalError = null;
        },
        async handleSubmit() {
            this.submitting = true;
            this.modalError = null;
            try {
                if (this.editingId) {
                    await amenityService.update(this.editingId, this.form);
                } else {
                    await amenityService.create(this.form);
                }
                await this.fetchAmenities();
                this.closeModal();
            } catch (err) {
                this.modalError = err.message || "Lỗi lưu thông tin.";
            } finally {
                this.submitting = false;
            }
        },
        async confirmDelete(item) {
            if (confirm(`Bạn có chắc chắn muốn xóa tiện ích "${item.name}" không?`)) {
                try {
                    await amenityService.delete(item.id);
                    await this.fetchAmenities();
                } catch (err) {
                    alert(err.message || "Không thể xóa tiện ích.");
                }
            }
        },
    },
    created() {
        this.fetchAmenities();
    },
};
</script>

<style scoped>
.amenities-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 1000px;
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
}

.page-title {
    font-size: 20px;
    font-weight: 800;
    margin: 0;
    color: var(--sg-text, #0f172a);
}

.header-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--sg-border, #e2e8f0);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    padding: 24px;
}

.loading-state, .error-state, .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 24px;
    gap: 16px;
    text-align: center;
}

.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(0, 0, 0, 0.05);
    border-top-color: #0f172a;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #0f172a;
    color: #fff;
}

.btn-primary:hover {
    background: #1e293b;
}

.btn-outline {
    border: 1px solid var(--sg-border, #e2e8f0);
    background: transparent;
    color: var(--sg-text, #0f172a);
}

.btn-outline:hover {
    background: #f8fafc;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    text-align: left;
}

.data-table th, .data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--sg-border, #e2e8f0);
}

.data-table th {
    background: #f8fafc;
    font-weight: 700;
    color: #475569;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.font-bold {
    font-weight: 700;
}

.font-mono {
    font-family: monospace;
}

.status-badge {
    display: inline-flex;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-locked {
    background: #fee2e2;
    color: #991b1b;
}

.actions-wrapper {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.btn-action {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid var(--sg-border, #e2e8f0);
    background: #fff;
    transition: all 0.15s ease;
}

.btn-action:hover {
    background: #f1f5f9;
}

.btn-edit {
    color: #2563eb;
    border-color: #bfdbfe;
}
.btn-edit:hover {
    background: #eff6ff;
}

.btn-delete {
    color: #dc2626;
    border-color: #fecaca;
}
.btn-delete:hover {
    background: #fef2f2;
}

/* Modal */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 999;
}

.modal {
    width: min(450px, 95vw);
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--sg-border, #e2e8f0);
}

.modal-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
}

.btn-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #64748b;
}

.modal-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 13px;
    font-weight: 700;
}

.form-control {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid var(--sg-border, #e2e8f0);
    outline: none;
    font-size: 14px;
}

.form-control:focus {
    border-color: #0f172a;
}

.checkbox-group {
    margin-top: 4px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}

.checkbox-label input {
    width: 16px;
    height: 16px;
    accent-color: #0f172a;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 20px;
    border-top: 1px solid var(--sg-border, #e2e8f0);
    background: #f8fafc;
}

.alert-danger {
    padding: 10px 14px;
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}

.required {
    color: #ef4444;
}

.text-center {
    text-align: center;
}
</style>

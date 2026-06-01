<template>
    <div class="court-types-container">
        <div class="header-actions">
            <button class="btn btn-primary" @click="openCreateModal">
                <span class="plus-icon">+</span> Thêm loại sân mới
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state card">
            <div class="spinner"></div>
            <p>Đang tải danh sách loại sân...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state card">
            <p class="error-message">{{ error }}</p>
            <button class="btn btn-outline" @click="fetchCourtTypes">
                Thử lại
            </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="courtTypes.length === 0" class="empty-state card">
            <p>Chưa có loại sân nào được cấu hình trên hệ thống.</p>
            <button class="btn btn-primary" @click="openCreateModal">
                Thêm ngay
            </button>
        </div>

        <!-- Table Grid -->
        <div v-else class="card table-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tên loại sân</th>
                            <th>Số người chơi tiêu chuẩn</th>
                            <th>Mô tả</th>
                            <th>Trạng thái</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="parent in mainParentTypes" :key="parent.id">
                            <tr class="parent-row" :class="{ 'has-children': getChildren(parent.id).length > 0 }" @click="toggleExpand(parent.id)">
                                <td class="font-bold">
                                    {{ parent.name }}
                                </td>
                                <td>{{ parent.player_count }} người</td>
                                <td class="text-muted text-truncate">
                                    {{ parent.description || "Chưa có mô tả" }}
                                </td>
                                <td>
                                    <span
                                        class="status-badge"
                                        :class="
                                            parent.is_active ? 'active' : 'inactive'
                                        "
                                    >
                                        {{
                                            parent.is_active
                                                ? "Đang hoạt động"
                                                : "Tạm khóa"
                                        }}
                                    </span>
                                </td>
                                <td class="text-right actions-cell" @click.stop>
                                    <button
                                        class="btn-action btn-edit"
                                        @click="openEditModal(parent)"
                                    >
                                        Sửa
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-if="isExpanded(parent.id)"
                                v-for="child in getChildren(parent.id)"
                                :key="child.id"
                                class="child-row"
                            >
                                <td class="font-bold child-name">
                                    {{ child.name }}
                                </td>
                                <td>{{ child.player_count }} người</td>
                                <td class="text-muted text-truncate">
                                    {{ child.description || "Chưa có mô tả" }}
                                </td>
                                <td>
                                    <span
                                        class="status-badge"
                                        :class="
                                            child.is_active ? 'active' : 'inactive'
                                        "
                                    >
                                        {{
                                            child.is_active
                                                ? "Đang hoạt động"
                                                : "Tạm khóa"
                                        }}
                                    </span>
                                </td>
                                <td class="text-right actions-cell">
                                    <button
                                        class="btn-action btn-edit"
                                        @click="openEditModal(child)"
                                    >
                                        Sửa
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
            <div class="modal card">
                <div class="modal-header">
                    <h3>
                        {{
                            editingId
                                ? "Cập nhật loại sân"
                                : "Thêm loại sân mới"
                        }}
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
                            <label for="name"
                                >Tên loại sân
                                <span class="required">*</span></label
                            >
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                placeholder="Ví dụ: Sân cầu lông, Sân bóng đá 5 người..."
                                required
                            />
                        </div>

                        <div class="form-group">
                            <label for="parent_id">Danh mục cha</label>
                            <select id="parent_id" v-model="form.parent_id" class="form-control">
                                <option :value="null">-- Không chọn (Là danh mục gốc) --</option>
                                <option v-for="pt in parentTypes" :key="pt.id" :value="pt.id">
                                    {{ pt.name }}
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="player_count"
                                >Số người chơi tiêu chuẩn
                                <span class="required">*</span></label
                            >
                            <input
                                id="player_count"
                                v-model.number="form.player_count"
                                type="number"
                                min="1"
                                class="form-control"
                                required
                            />
                        </div>

                        <div class="form-group">
                            <label for="description">Mô tả</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="form-control"
                                rows="3"
                                placeholder="Nhập mô tả ngắn về loại sân..."
                            ></textarea>
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
import { courtTypeService } from "../../services/courtTypes";

export default {
    name: "AdminCourtTypes",
    data() {
        return {
            courtTypes: [],
            expandedParentIds: [],
            loading: true,
            error: null,
            showModal: false,
            editingId: null,
            submitting: false,
            modalError: null,
            form: {
                name: "",
                parent_id: null,
                player_count: 4,
                description: "",
                is_active: true,
            },
        };
    },
    computed: {
        parentTypes() {
            // Lọc danh sách loại sân là gốc (chưa có cha) và không phải chính nó để tránh vòng lặp vô hạn
            return this.courtTypes.filter(
                (type) => !type.parent_id && type.id !== this.editingId
            );
        },
        mainParentTypes() {
            // Chỉ hiển thị các loại sân gốc (Cha) trên bảng chính
            return this.courtTypes.filter(type => !type.parent_id);
        },
    },
    methods: {
        isExpanded(parentId) {
            return this.expandedParentIds.includes(parentId);
        },
        toggleExpand(parentId) {
            if (this.getChildren(parentId).length === 0) return;
            const index = this.expandedParentIds.indexOf(parentId);
            if (index > -1) {
                this.expandedParentIds.splice(index, 1);
            } else {
                this.expandedParentIds.push(parentId);
            }
        },
        getChildren(parentId) {
            return this.courtTypes.filter(type => type.parent_id === parentId);
        },
        async fetchCourtTypes() {
            this.loading = true;
            this.error = null;
            try {
                const res = await courtTypeService.getAll();
                this.courtTypes = res.data || [];
            } catch (err) {
                this.error = err.message || "Lỗi khi tải danh sách loại sân.";
            } finally {
                this.loading = false;
            }
        },
        openCreateModal() {
            this.editingId = null;
            this.modalError = null;
            this.form = {
                name: "",
                parent_id: null,
                player_count: 4,
                description: "",
                is_active: true,
            };
            this.showModal = true;
        },
        openEditModal(type) {
            this.editingId = type.id;
            this.modalError = null;
            this.form = {
                name: type.name,
                parent_id: type.parent_id || null,
                player_count: type.player_count,
                description: type.description || "",
                is_active: !!type.is_active,
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
                    await courtTypeService.update(this.editingId, this.form);
                } else {
                    await courtTypeService.create(this.form);
                }
                await this.fetchCourtTypes();
                this.closeModal();
            } catch (err) {
                this.modalError = err.message || "Lỗi lưu thông tin.";
            } finally {
                this.submitting = false;
            }
        },
        async confirmDelete(type) {
            if (
                confirm(
                    `Bạn có chắc chắn muốn xóa loại sân "${type.name}" không?`,
                )
            ) {
                try {
                    await courtTypeService.delete(type.id);
                    await this.fetchCourtTypes();
                } catch (err) {
                    alert(err.message || "Không thể xóa loại sân.");
                }
            }
        },
    },
    created() {
        this.fetchCourtTypes();
    },
};
</script>

<style scoped>
.court-types-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--sg-border);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    padding: 24px;
}

.header-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 16px;
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
    transition: all 0.2s ease;
}

.btn-primary {
    background: #000000;
    border: 1px solid #000000;
    color: #fff;
}

.btn-primary:hover {
    background: #222222;
    border-color: #222222;
}

.btn-outline {
    border: 1px solid var(--sg-border);
    background: transparent;
    color: var(--sg-text);
}

.btn-outline:hover {
    background: var(--sg-surface);
}

.loading-state,
.error-state,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 24px;
    text-align: center;
    gap: 16px;
    color: rgba(15, 23, 42, 0.6);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(0, 0, 0, 0.1);
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.table-card {
    padding: 0;
    overflow: hidden;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.data-table th,
.data-table td {
    padding: 16px 24px;
    border-bottom: 1px solid var(--sg-border);
    font-size: 14px;
}

.data-table th {
    background: var(--sg-surface);
    font-weight: 700;
    color: var(--sg-text);
}

.font-bold {
    font-weight: 700;
    color: var(--sg-text);
}

.text-muted {
    color: rgba(15, 23, 42, 0.5);
}

.text-truncate {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.status-badge {
    display: inline-flex;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid transparent;
}

.status-badge.active {
    background: rgba(0, 0, 0, 0.04);
    color: #000000;
    border-color: rgba(0, 0, 0, 0.15);
}

.status-badge.inactive {
    background: #f3f4f6;
    color: rgba(0, 0, 0, 0.4);
    border-color: rgba(0, 0, 0, 0.08);
}

.text-right {
    text-align: right;
}

.actions-cell {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-action {
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.btn-edit {
    background: rgba(0, 0, 0, 0.02);
    border-color: rgba(0, 0, 0, 0.1);
    color: rgba(0, 0, 0, 0.8);
}

.btn-edit:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #000000;
}

.btn-delete {
    background: rgba(0, 0, 0, 0.02);
    border-color: rgba(0, 0, 0, 0.08);
    color: rgba(0, 0, 0, 0.6);
}

.btn-delete:hover {
    background: rgba(239, 68, 68, 0.05);
    border-color: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

/* Modal Styling */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 1000;
    padding: 20px;
}

.modal {
    width: 100%;
    max-width: 500px;
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
    padding: 0;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--sg-border);
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 800;
    margin: 0;
    color: var(--sg-text);
}

.btn-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: rgba(15, 23, 42, 0.4);
}

.modal-body {
    padding: 24px;
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
    color: var(--sg-text);
}

.required {
    color: #ef4444;
}

.form-control {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--sg-border);
    font-size: 14px;
    color: var(--sg-text);
    outline: none;
    transition: border-color 0.2s ease;
}

.form-control:focus {
    border-color: #000000;
}

.checkbox-group {
    margin-top: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
    color: var(--sg-text);
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    accent-color: #000000;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid var(--sg-border);
    background: var(--sg-surface);
}

.alert-danger {
    background: #f3f4f6;
    color: #ef4444;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid #e5e7eb;
}

.parent-row.has-children {
    cursor: pointer;
}

.parent-row:hover {
    background: rgba(0, 0, 0, 0.01) !important;
}

.toggle-icon {
    display: inline-block;
    width: 20px;
    font-size: 10px;
    color: rgba(0, 0, 0, 0.4);
    user-select: none;
}

.toggle-placeholder {
    display: inline-block;
    width: 20px;
}

.child-row {
    background: rgba(0, 0, 0, 0.015) !important;
}

.child-row:hover {
    background: rgba(0, 0, 0, 0.03) !important;
}


.tree-connector {
    color: rgba(0, 0, 0, 0.25);
    margin-right: 6px;
    font-weight: normal;
}
</style>

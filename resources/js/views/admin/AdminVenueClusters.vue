<template>
    <div class="avc-page">
        <!-- ── Loading ── -->
        <div v-if="loading" class="state-box card animate-fade-in">
            <div class="spinner"></div>
            <p>Đang tải danh sách cụm sân...</p>
        </div>

        <!-- ── Error ── -->
        <div v-else-if="error" class="state-box card error-box animate-fade-in">
            <p>{{ error }}</p>
            <button class="btn btn-outline" @click="loadClusters">
                Thử lại
            </button>
        </div>

        <template v-else>
            <header class="avc-header animate-fade-in">
                <div class="avc-title">
                    <p class="eyebrow">Quản lý cụm sân</p>
                    <h1>Cụm sân & vận hành đối tác</h1>
                    <p>Theo dõi trạng thái, chủ sân và các hồ sơ cần xử lý.</p>
                </div>
                <button class="btn btn-outline" type="button" @click="loadClusters">
                    <AppIcon name="refresh" size="16" />
                    Làm mới
                </button>
            </header>
            <!-- ── Bộ lọc & Ô tìm kiếm (SaaS Command Bar) ── -->
            <div class="avc-filters card animate-fade-in" v-if="clusters.length > 0">
                <div class="filter-row">
                    <label class="status-filter-field">
                        <span>Trạng thái</span>
                        <select v-model="filterStatus">
                            <option v-for="tab in statusTabsUi" :key="tab.value" :value="tab.value">
                                {{ tab.label }} ({{ statusTabCount(tab.value) }})
                            </option>
                        </select>
                    </label>
                    <div class="filter-search">
                        <span>Tìm cụm sân</span>
                        <div class="search-box">
                            <AppIcon name="search" size="16" />
                            <input
                                id="search-venue-cluster"
                                v-model="searchText"
                                type="text"
                                placeholder="Tìm kiếm nhanh tên sân, địa chỉ hoặc chủ sân..."
                                class="search-input"
                            />
                        </div>
                    </div>
                    <p class="filter-result">{{ filteredClusters.length }} kết quả</p>
                </div>
            </div>

            <!-- ── Empty State khi hệ thống không có cụm sân nào ── -->
            <div v-if="clusters.length === 0" class="state-box card animate-fade-in">
                <p class="empty-msg">Chưa có cụm sân nào được đăng ký trên hệ thống.</p>
            </div>

            <!-- ── Empty State khi tìm kiếm không ra kết quả ── -->
            <div v-else-if="filteredClusters.length === 0" class="state-box card animate-fade-in">
                <p class="empty-msg">Không tìm thấy cụm sân nào phù hợp với điều kiện tìm kiếm.</p>
                <button class="btn btn-outline" @click="searchText = ''; filterStatus = ''">
                    Xóa bộ lọc
                </button>
            </div>

            <!-- ── Elegant SaaS Table View ── -->
            <div v-else class="clusters-list-wrapper animate-fade-in">
                <div class="mobile-cluster-list">
                    <button
                        v-for="row in filteredClusters"
                        :key="row.id"
                        type="button"
                        class="mobile-cluster-row"
                        @click="goDetail(row.id)"
                    >
                        <span class="mobile-cluster-heading">
                            <strong>{{ row.name }}</strong>
                            <span class="status-badge" :class="'state-is-' + displayClusterStatus(row)">
                                {{ statusLabel(row) }}
                            </span>
                        </span>
                        <span class="mobile-cluster-address">{{ formatFullAddress(row) }}</span>
                        <span class="mobile-cluster-facts">
                            <span>
                                <small>Chủ sân</small>
                                <strong>{{ row.owner?.full_name || 'Chưa cập nhật' }}</strong>
                            </span>
                            <span>
                                <small>Quy mô</small>
                                <strong>{{ row.court_count }} sân</strong>
                            </span>
                            <span>
                                <small>Phí</small>
                                <strong>{{ feeStatusLabel(row.fee_status) }}</strong>
                            </span>
                            <AppIcon name="chevronRight" size="18" />
                        </span>
                    </button>
                </div>
                <SaaSTable
                    class="desktop-cluster-table"
                    :columns="tableColumns" 
                    :data="filteredClusters" 
                    clickable 
                    @row-click="row => goDetail(row.id)"
                >
                    <!-- Tên cụm sân & Address -->
                    <template #name="{ row }">
                        <div class="name-col-cell">
                            <div class="cluster-name-wrapper" style="display: flex; align-items: center; gap: 6px;">
                                <span class="cluster-name-text">{{ row.name }}</span>
                                <div v-if="row.has_pending_requests" class="pending-indicator" title="Có yêu cầu đang chờ duyệt" style="display: flex; align-items: center; color: #ef4444;">
                                    <AppIcon name="alertCircle" size="14" />
                                </div>
                            </div>
                            <span class="cluster-address-text">{{ formatFullAddress(row) }}</span>
                        </div>
                    </template>

                    <!-- Chủ sân -->
                    <template #owner="{ row }">
                        <div class="owner-col-cell" v-if="row.owner">
                            <span class="owner-name-text">{{ row.owner.full_name }}</span>
                            <span class="owner-email-text">{{ row.owner.email }}</span>
                        </div>
                        <span v-else class="text-muted">—</span>
                    </template>

                    <!-- Số sân con -->
                    <template #courts="{ row }">
                        <span class="courts-badge-count">{{ row.court_count }} sân</span>
                    </template>

                    <!-- Trạng thái phí -->
                    <template #fee_status="{ row }">
                        <span class="fee-badge" :class="'fee-is-' + row.fee_status">
                            {{ feeStatusLabel(row.fee_status) }}
                        </span>
                    </template>

                    <!-- Trạng thái hoạt động -->
                    <template #status="{ row }">
                        <span class="status-badge" :class="'state-is-' + displayClusterStatus(row)">
                            {{ statusLabel(row) }}
                        </span>
                    </template>

                    <!-- Action Column -->
                    <template #actions="{ row }">
                        <div class="table-actions" @click.stop>
                            <ActionIconButton
                                icon="eye"
                                label="Chi tiết"
                                size="sm"
                                @click="goDetail(row.id)"
                            />
                        </div>
                    </template>
                </SaaSTable>
            </div>
        </template>
    </div>
</template>


<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import SaaSTable from "../../components/ui/SaaSTable.vue";
import { adminVenueClusterService } from "../../services/adminVenueClusterService.js";
import { venueDisplayStatus } from "../../utils/venuePartnerState.js";

export default {
    name: "AdminVenueClusters",
    components: { ActionIconButton, AppIcon, SaaSTable },
    data() {
        return {
            clusters: [],
            loading: true,
            error: "",
            filterStatus: "",
            searchText: "",
            tableColumns: [
                { key: "name", label: "Tên cụm sân" },
                { key: "owner", label: "Chủ sân" },
                { key: "courts", label: "Số sân con", align: "center" },
                { key: "fee_status", label: "Trạng thái phí" },
                { key: "status", label: "Trạng thái" },
                { key: "actions", label: "", align: "right" }
            ]
        };
    },
    computed: {
        statusTabsUi() {
            return [
                { value: "", label: "Tất cả" },
                { value: "has_pending_requests", label: "Có thay đổi chờ duyệt" },
                { value: "pending", label: "Chờ duyệt mới" },
                { value: "active", label: "Hoạt động" },
                { value: "locked", label: "Đã khóa" },
                { value: "termination_processing", label: "Đang chấm dứt" },
                { value: "partner_terminated", label: "Đã chấm dứt" },
            ];
        },
        filteredClusters() {
            let list = this.clusters;
            if (this.filterStatus) {
                if (this.filterStatus === "has_pending_requests") {
                    list = list.filter((c) => c.has_pending_requests);
                } else if (this.filterStatus === "termination_processing") {
                    list = list.filter((c) => this.displayClusterStatus(c) === "termination_processing");
                } else {
                    list = list.filter((c) => this.displayClusterStatus(c) === this.filterStatus);
                }
            }
            if (this.searchText.trim()) {
                const q = this.searchText.trim().toLowerCase();
                list = list.filter(
                    (c) =>
                        c.name.toLowerCase().includes(q) ||
                        (c.address || "").toLowerCase().includes(q) ||
                        (c.owner?.full_name || "").toLowerCase().includes(q),
                );
            }
            return list;
        },
    },
    mounted() {
        this.loadClusters();
    },
    methods: {
        async loadClusters() {
            this.loading = true;
            this.error = "";
            try {
                const res = await adminVenueClusterService.list();
                this.clusters = res.data || [];
            } catch (err) {
                this.error = err.message || "Không tải được danh sách cụm sân.";
            } finally {
                this.loading = false;
            }
        },

        displayClusterStatus(cluster) {
            return venueDisplayStatus(cluster);
        },

        statusLabel(cluster) {
            const status = this.displayClusterStatus(cluster);
            if (status === "termination_locked") return "Khoa cham dut";
            if (status === "termination_processing") return "Đang chấm dứt";
            if (status === "partner_terminated") return "Đã chấm dứt";

            const map = {
                pending: "Chờ duyệt",
                active: "Hoạt động",
                locked: "Đã khóa",
            };
            return map[status] || status;
        },

        statusTabCount(status) {
            if (status === "") return this.clusters.length;
            if (status === "has_pending_requests") {
                return this.clusters.filter((c) => c.has_pending_requests).length;
            }
            if (status === "termination_processing") {
                return this.clusters.filter((cluster) => this.displayClusterStatus(cluster) === "termination_processing").length;
            }
            return this.clusters.filter((cluster) => this.displayClusterStatus(cluster) === status).length;
        },

        feeStatusLabel(status) {
            const map = {
                pending: "Chờ thanh toán",
                paid: "Đã thanh toán",
                overdue: "Quá hạn",
                cancelled: "Hủy bỏ",
                no_fee: "Không có phí",
            };
            return map[status] || status;
        },
        goDetail(id) {
            this.$router.push({
                name: "admin-venue-cluster-detail",
                params: { id },
            });
        },
        formatFullAddress(c) {
            if (!c) return "";
            const parts = [
                c.address,
                c.ward,
                c.province
            ].filter(Boolean);
            return parts.join(', ') || '—';
        },
    },
};
</script>

<style scoped>
.avc-page {
    display: flex;
    flex-direction: column;
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
    max-width: 1180px;
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
    overflow: hidden;
}

.avc-page > * {
    min-width: 0;
    max-width: 100%;
}

.avc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 20px 0 12px;
}

.avc-header .btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}

.avc-title {
    min-width: 0;
}

.eyebrow {
    margin: 0 0 4px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
}

.avc-title h1 {
    margin: 0;
    color: var(--admin-text, #0f172a);
    font-size: 24px;
    line-height: 1.2;
}

.avc-title p:last-child {
    margin: 6px 0 0;
    color: var(--admin-muted, #64748b);
    font-size: 14px;
}

.avc-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.kpi-card {
    display: grid;
    gap: 4px;
    min-height: 106px;
    border: 1px solid var(--admin-border, #e2e8f0);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    padding: 16px;
}

.kpi-card span {
    color: var(--admin-muted, #64748b);
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.kpi-card strong {
    color: var(--admin-text, #0f172a);
    font-size: 28px;
    line-height: 1;
}

.kpi-card small {
    color: var(--admin-muted, #64748b);
    font-size: 12px;
}

.card {
    background: transparent;
    border-radius: 0;
    border: none;
    padding: 12px 0;
    box-shadow: none;
}

:deep(.saas-table-container) {
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

/* Filters */
.avc-filters {
    padding: 12px 0;
}
.filter-row {
    display: grid;
    grid-template-columns: minmax(180px, 230px) minmax(0, 1fr) auto;
    align-items: end;
    gap: 16px;
}
.filter-tabs {
    display: flex;
    gap: 6px;
}
.avc-filters .filter-tabs button.tab-btn {
    height: 38px !important;
    min-height: 38px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    padding: 0 16px !important;
    border-radius: 8px !important;
    border: 1px solid #cbd5e1 !important;
    background: var(--admin-surface) !important;
    color: #475569 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.18s !important;
    box-sizing: border-box !important;
}

.avc-filters .filter-tabs button.tab-btn strong {
    min-width: 20px;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.08);
    color: inherit;
    font-size: 11px;
    line-height: 20px;
    text-align: center;
}

.avc-filters .filter-tabs button.tab-btn.active strong {
    background: rgba(255, 255, 255, 0.22);
}
.avc-filters .filter-tabs button.tab-btn.active {
    background: var(--admin-primary) !important;
    border-color: var(--admin-primary) !important;
    color: var(--admin-primary-text, #fff) !important;
}
.avc-filters .filter-tabs button.tab-btn:not(.active):hover {
    background: var(--admin-hover) !important;
    color: var(--admin-primary-dark) !important;
}
[data-theme="dark"] .avc-filters .filter-tabs button.tab-btn {
    border: 1px solid var(--admin-border) !important;
    color: var(--admin-muted) !important;
}
.filter-search {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.filter-search > span,
.status-filter-field > span {
    color: var(--admin-muted, #64748b);
    font-size: 12px;
    font-weight: 700;
}

.status-filter-field {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.status-filter-field select {
    width: 100%;
    height: 40px;
    border: 1px solid var(--admin-border, #cbd5e1);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #0f172a);
    padding: 0 34px 0 12px;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    height: 40px;
    min-width: 0;
    border: 1px solid var(--admin-border, #cbd5e1);
    border-radius: 8px;
    padding: 0 12px;
    background: var(--admin-surface, #fff);
}

.search-input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
}

.filter-result {
    margin: 0;
    padding-bottom: 10px;
    color: var(--admin-muted, #64748b) !important;
    font-size: 12px;
    white-space: nowrap;
}
/* Search box border styling to increase contrast on light theme */
.filter-search :deep(.search-box) {
    border-color: #cbd5e1 !important;
}
.filter-search :deep(.search-box input::placeholder) {
    color: #64748b !important;
}
.filter-search :deep(.search-box svg) {
    color: #64748b !important;
}
[data-theme="dark"] .filter-search :deep(.search-box) {
    border-color: var(--admin-border) !important;
}
[data-theme="dark"] .filter-search :deep(.search-box input::placeholder) {
    color: var(--admin-faint) !important;
}
[data-theme="dark"] .filter-search :deep(.search-box svg) {
    color: var(--admin-faint) !important;
}

@media (max-width: 900px) {
    .avc-header {
        align-items: stretch;
        flex-direction: column;
    }

    .avc-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .avc-kpis {
        grid-template-columns: 1fr;
    }
}

/* State */
.state-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 24px;
    gap: 14px;
    text-align: center;
    color: var(--admin-muted, rgba(15, 23, 42, 0.5));
}
.error-box {
    color: var(--admin-danger, #dc2626);
    background: var(--admin-danger-soft, #fef2f2);
    border-color: var(--admin-border, #fecaca);
}
.empty-msg {
    font-size: 15px;
    font-weight: 600;
}
.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(0, 0, 0, 0.05);
    border-top-color: var(--admin-text, #0f172a);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* SaaS Compact Rows View */
.clusters-list-wrapper {
    display: flex;
    flex-direction: column;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    overflow-x: auto;
}

.mobile-cluster-list {
    display: none;
}

:deep(.saas-table-container) {
    min-width: 0;
    max-width: 100%;
    overflow-x: auto;
}

.clusters-list {
    display: flex;
    flex-direction: column;
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border, rgba(15, 23, 42, 0.08));
    border-radius: 12px;
    overflow: hidden;
}

.cluster-row-item {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 58px;
    padding: 12px 20px;
    border-bottom: 1px solid var(--admin-border-soft, rgba(15, 23, 42, 0.04));
    transition: background 0.15s ease;
    cursor: pointer;
}

.cluster-row-item:last-child {
    border-bottom: none;
}

.cluster-row-item:hover {
    background: var(--admin-hover, rgba(15, 23, 42, 0.015));
}

.accent-line {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--admin-primary, #10b981);
    opacity: 0;
    transition: opacity 0.15s ease;
}

.cluster-row-item:hover .accent-line {
    opacity: 1;
}

.row-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1.3;
    min-width: 0;
}

.cluster-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.cluster-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
    transition: opacity 0.2s ease;
}

.cluster-row-item.row-locked .cluster-name {
    color: var(--admin-danger, #ef4444);
}

.cluster-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--admin-faint, #64748b);
    min-width: 0;
}

.cluster-slug {
    font-weight: 600;
    flex-shrink: 0;
}

.meta-dot {
    opacity: 0.5;
}

.cluster-address {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.courts-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--admin-muted, rgba(15, 23, 42, 0.6));
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.row-middle {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 24px;
    flex: 1.2;
    padding-right: 16px;
    min-width: 0;
}

.owner-info {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    text-align: right;
    min-width: 0;
    flex: 1;
}

.owner-name {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.owner-email {
    font-size: 11px;
    color: var(--admin-faint, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.status-badges {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}

.row-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    background: transparent !important;
    padding: 0 !important;
}

/* Status styles */
.state-is-pending {
    color: var(--admin-warning, #f59e0b) !important;
}

.state-is-active {
    color: var(--admin-primary, #10b981) !important;
}

.state-is-locked {
    color: var(--admin-danger, #ef4444) !important;
}

.state-is-termination_locked,
.state-is-termination_processing {
    color: #c2410c !important;
}

.state-is-partner_terminated {
    color: #64748b !important;
}

/* Fee styles */
.fee-is-paid {
    color: var(--admin-primary, #10b981) !important;
}

.fee-is-pending {
    color: var(--admin-warning, #f59e0b) !important;
}

.fee-is-unpaid,
.fee-is-overdue {
    color: var(--admin-danger, #ef4444) !important;
}

.fee-is-cancelled {
    color: var(--admin-muted, #71717a) !important;
}

.fee-is-no_fee {
    color: var(--admin-muted, #71717a) !important;
}

.row-right {
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transform: translateX(6px);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.cluster-row-item:hover .row-right {
    opacity: 1;
    transform: translateX(0);
}

/* Buttons */
.btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.18s;
}
.btn-outline {
    background: transparent;
    border-color: var(--admin-border, var(--sg-border));
    color: var(--admin-text, var(--sg-text));
}
.btn-outline:hover {
    background: var(--admin-hover, #f1f5f9);
}

/* Animations */
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ==========================================
   RESPONSIVE DESIGN (MOBILE & TABLET)
   ========================================== */

@media (max-width: 1024px) {
    .avc-page {
        gap: 16px;
        padding: 0;
    }
}

@media (max-width: 900px) {
    .hide-on-tablet {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .avc-title h1 {
        max-width: calc(100% - 8px);
        font-size: 22px;
        overflow-wrap: anywhere;
    }

    .filter-row {
        grid-template-columns: minmax(0, 1fr);
        gap: 12px;
    }

    .filter-result {
        padding-bottom: 0;
    }

    .desktop-cluster-table {
        display: none !important;
    }

    .mobile-cluster-list {
        display: grid;
        gap: 8px;
        width: 100%;
    }

    .mobile-cluster-row {
        display: grid;
        gap: 8px;
        width: 100%;
        min-width: 0;
        border: 1px solid var(--admin-border, #dbe4de);
        border-radius: 8px;
        background: var(--admin-surface, #fff);
        padding: 13px;
        color: var(--admin-text, #0f172a);
        text-align: left;
    }

    .mobile-cluster-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        min-width: 0;
    }

    .mobile-cluster-heading > strong {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .mobile-cluster-heading .status-badge {
        flex: 0 0 auto;
        font-size: 12px;
    }

    .mobile-cluster-address {
        color: var(--admin-muted, #64748b);
        font-size: 12px;
        line-height: 1.45;
    }

    .mobile-cluster-facts {
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) minmax(62px, .6fr) minmax(72px, .8fr) 18px;
        align-items: end;
        gap: 8px;
        border-top: 1px solid var(--admin-border-soft, #edf2ef);
        padding-top: 9px;
    }

    .mobile-cluster-facts > span {
        display: grid;
        gap: 2px;
        min-width: 0;
    }

    .mobile-cluster-facts small {
        color: var(--admin-muted, #64748b);
        font-size: 10px;
    }

    .mobile-cluster-facts strong {
        min-width: 0;
        overflow: hidden;
        color: var(--admin-text, #0f172a);
        font-size: 11px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cluster-row-item {
        flex-direction: column;
        align-items: stretch;
        padding: 14px;
        gap: 10px;
    }

    .row-left {
        width: 100%;
        justify-content: space-between;
        gap: 12px;
    }

    .cluster-address {
        max-width: 180px;
    }

    .row-middle {
        width: 100%;
        justify-content: space-between;
        padding-right: 0;
        gap: 12px;
        border-top: 1px dashed var(--admin-border, rgba(15, 23, 42, 0.04));
        padding-top: 8px;
    }

    .row-right {
        position: absolute;
        top: 10px;
        right: 10px;
        opacity: 1;
        transform: none;
    }

}

/* SaaS Table cell custom layout styles */
.name-col-cell, .owner-col-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.cluster-name-text {
    font-size: 13.5px;
    font-weight: 500;
    color: var(--admin-text, #0f172a);
}

.cluster-address-text {
    font-size: 11.5px;
    color: var(--admin-faint, #64748b);
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.owner-name-text {
    font-size: 13px;
    font-weight: 500;
    color: var(--admin-text, #0f172a);
}

.owner-email-text {
    font-size: 11px;
    color: var(--admin-faint, #64748b);
}

.courts-badge-count {
    font-size: 13px;
    font-weight: 400;
    color: var(--admin-text, #0f172a);
    background: transparent !important;
    padding: 0 !important;
    border: none !important;
    border-radius: 0 !important;
}

.fee-badge, .status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 13px;
    font-weight: 500;
    padding: 0 !important;
    border-radius: 0 !important;
    background-color: transparent !important;
}

/* Status coloring mapping */
.state-is-pending, .fee-is-pending {
    color: var(--admin-warning) !important;
}

.state-is-active, .fee-is-paid {
    color: var(--admin-primary-dark) !important;
}

/* Ensure readability/contrast for active status in dark mode */
[data-theme="dark"] .state-is-active, 
[data-theme="dark"] .fee-is-paid {
    color: #34d399 !important;
}

.state-is-locked, .fee-is-unpaid, .fee-is-overdue {
    color: var(--admin-danger-text, var(--admin-danger)) !important;
}

.fee-is-cancelled, .fee-is-no_fee {
    color: #4b5563 !important;
}

[data-theme="dark"] .fee-is-cancelled,
[data-theme="dark"] .fee-is-no_fee {
    color: #9ca3af !important;
}
</style>

<template>
    <div class="avc-page">
        <!-- ── Bộ lọc ── -->
        <div class="avc-filters card">
            <div class="filter-row">
                <div class="filter-tabs">
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        class="tab-btn"
                        :class="{ active: filterStatus === tab.value }"
                        @click="filterStatus = tab.value"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <div class="filter-search">
                    <input
                        id="search-venue-cluster"
                        v-model="searchText"
                        type="text"
                        placeholder="Tìm theo tên sân, địa chỉ..."
                        class="search-input"
                        @input="onSearch"
                    />
                </div>
            </div>
        </div>

        <!-- ── Loading ── -->
        <div v-if="loading" class="state-box card">
            <div class="spinner"></div>
            <p>Đang tải danh sách cụm sân...</p>
        </div>

        <!-- ── Error ── -->
        <div v-else-if="error" class="state-box card error-box">
            <p>{{ error }}</p>
            <button class="btn btn-outline" @click="loadClusters">
                Thử lại
            </button>
        </div>

        <!-- ── Empty ── -->
        <div v-else-if="filteredClusters.length === 0" class="state-box card">
            <p class="empty-msg">Không tìm thấy cụm sân nào phù hợp.</p>
        </div>

        <!-- ── Table ── -->
        <div v-else class="avc-table-wrap card">
            <div class="table-scroll">
                <table class="avc-table">
                    <thead>
                        <tr>
                            <th class="col-name">Tên cụm sân</th>
                            <th class="col-owner">Chủ sân</th>
                            <th class="col-courts text-center">Số sân con</th>
                            <th class="col-fee text-center">Trạng thái phí</th>
                            <th class="col-status text-center">Trạng thái</th>
                            <th class="col-actions text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in filteredClusters"
                            :key="c.id"
                            class="avc-row"
                            @click="goDetail(c.id)"
                        >
                            <td class="col-name">
                                <div class="cluster-info-meta">
                                    <div class="cluster-name">{{ c.name }}</div>
                                    <div class="cluster-slug">{{ c.slug }}</div>
                                </div>
                            </td>
                            <td class="col-owner" data-label="Chủ sân">
                                <div class="owner-meta-info">
                                    <div class="owner-name">
                                        {{ c.owner?.full_name || "—" }}
                                    </div>
                                    <div class="owner-email">
                                        {{ c.owner?.email || "" }}
                                    </div>
                                </div>
                            </td>
                            <td class="col-courts text-center" data-label="Số sân con">
                                <span class="court-count">{{
                                    c.court_count
                                }}</span>
                            </td>
                            <td class="col-fee text-center" data-label="Trạng thái phí">
                                <span
                                    class="status-badge"
                                    :class="`fee-${c.fee_status}`"
                                >
                                    {{ feeStatusLabel(c.fee_status) }}
                                </span>
                            </td>
                            <td class="col-status text-center" data-label="Trạng thái">
                                <span
                                    class="status-badge"
                                    :class="`status-${c.status}`"
                                >
                                    {{ statusLabel(c.status) }}
                                </span>
                            </td>
                            <td class="col-actions text-center" data-label="Thao tác" @click.stop>
                                <ActionIconButton icon="eye" label="Xem chi tiết" @click="goDetail(c.id)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import { adminVenueClusterService } from "../../services/adminVenueClusterService.js";

export default {
    name: "AdminVenueClusters",
    components: { ActionIconButton },
    data() {
        return {
            clusters: [],
            loading: true,
            error: "",
            filterStatus: "",
            searchText: "",
            searchTimer: null,
            statusTabs: [
                { value: "", label: "Tất cả" },
                { value: "pending", label: "Chờ duyệt" },
                { value: "active", label: "Hoạt động" },
                { value: "locked", label: "Đã khóa" },
            ],
        };
    },
    computed: {
        filteredClusters() {
            let list = this.clusters;
            if (this.filterStatus) {
                list = list.filter((c) => c.status === this.filterStatus);
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
        onSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {}, 0);
        },

        statusLabel(status) {
            const map = {
                pending: "Chờ duyệt",
                active: "Hoạt động",
                locked: "Đã khóa",
            };
            return map[status] || status;
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
    gap: 20px;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px;
    box-sizing: border-box;
}

.card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--sg-border);
    padding: 20px 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

/* Header */
.avc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
}
.avc-title {
    font-size: 22px;
    font-weight: 800;
    margin: 0;
    color: var(--sg-text);
}
.avc-sub {
    margin: 4px 0 0;
    font-size: 14px;
    color: rgba(15, 23, 42, 0.5);
}

/* Stats chips */
.avc-header-stats {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.stat-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--sg-surface, #f8fafc);
    border: 1px solid var(--sg-border);
    font-size: 13px;
    font-weight: 600;
}
.stat-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}
.dot-all {
    background: #94a3b8;
}
.dot-pending {
    background: #f59e0b;
}
.dot-active {
    background: #22c55e;
}
.dot-locked {
    background: #ef4444;
}

/* Filters */
.avc-filters {
    padding: 14px 24px;
}
.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
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
    padding: 0 16px !important;
    border-radius: 8px !important;
    border: 1px solid var(--sg-border) !important;
    background: var(--sg-surface, #f8fafc) !important;
    color: rgba(15, 23, 42, 0.6) !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.18s !important;
    box-sizing: border-box !important;
}
.avc-filters .filter-tabs button.tab-btn.active {
    background: #0f172a !important;
    border-color: #0f172a !important;
    color: #fff !important;
}
.avc-filters .filter-tabs button.tab-btn:not(.active):hover {
    background: #f1f5f9 !important;
}
.avc-filters .filter-row input.search-input {
    height: 38px !important;
    min-height: 38px !important;
    padding: 0 14px !important;
    border: 1px solid var(--sg-border) !important;
    border-radius: 8px !important;
    font-size: 14px !important;
    outline: none !important;
    min-width: 280px !important;
    color: var(--sg-text) !important;
    background: #fff !important;
    transition: border-color 0.18s !important;
    box-sizing: border-box !important;
}
.avc-filters .filter-row input.search-input:focus {
    border-color: #0f172a !important;
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
    color: rgba(15, 23, 42, 0.5);
}
.error-box {
    color: #dc2626;
    background: #fef2f2;
    border-color: #fecaca;
}
.empty-msg {
    font-size: 15px;
}
.spinner {
    width: 36px;
    height: 36px;
    border: 3px solid rgba(0, 0, 0, 0.08);
    border-top-color: #0f172a;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Table */
.avc-table-wrap {
    padding: 0;
    overflow: hidden;
}
.table-scroll {
    width: 100%;
    overflow-x: auto;
}
.avc-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}
.avc-table th,
.avc-table td {
    padding: 14px 20px;
    border-bottom: 1px solid var(--sg-border);
    font-size: 14px;
    text-align: left;
}
.avc-table th {
    background: var(--sg-surface, #f8fafc);
    font-weight: 700;
    color: var(--sg-text);
    font-size: 13px;
}
.avc-row {
    cursor: pointer;
    transition: background 0.12s;
}
.avc-row:hover {
    background: #f8fafc;
}


.cluster-info-meta {
    display: flex;
    flex-direction: column;
}
.cluster-name {
    font-weight: 700;
    color: var(--sg-text);
}
.cluster-slug {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.4);
    margin-top: 2px;
}
.owner-name {
    font-weight: 600;
}
.owner-email {
    font-size: 12px;
    color: rgba(15, 23, 42, 0.5);
}
.address-cell {
    color: rgba(15, 23, 42, 0.7);
    font-size: 13px;
}

/* Custom column widths */
.col-name {
    min-width: 260px;
}
.col-owner {
    min-width: 180px;
}
.col-courts {
    min-width: 100px;
}
.col-fee {
    min-width: 140px;
}
.col-status {
    min-width: 120px;
}
.col-actions {
    min-width: 110px;
}

.court-count {
    font-weight: 700;
    font-size: 15px;
}
.text-muted {
    color: rgba(15, 23, 42, 0.4);
}
.text-center {
    text-align: center !important;
}
.text-right {
    text-align: right !important;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
.status-pending {
    background: #fef3c7;
    color: #92400e;
}
.status-active {
    background: #dcfce7;
    color: #166534;
}
.status-locked {
    background: #fee2e2;
    color: #991b1b;
}

/* Fee status badges */
.fee-paid {
    background: #dcfce7;
    color: #166534;
}
.fee-unpaid,
.fee-overdue {
    background: #fee2e2;
    color: #991b1b;
}
.fee-pending {
    background: #fef3c7;
    color: #92400e;
}
.fee-cancelled {
    background: #f3f4f6;
    color: #6b7280;
}
.fee-no_fee {
    background: #f1f5f9;
    color: #475569;
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
    border-color: var(--sg-border);
    color: var(--sg-text);
}
.btn-outline:hover {
    background: #f1f5f9;
}
.btn-action {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.18s;
}
.btn-view {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #1e293b;
}
.btn-view:hover {
    background: #e2e8f0;
}

/* Responsive Styles for Mobile and Tablet */
@media (max-width: 768px) {
    .avc-page {
        gap: 16px;
        padding: 0 12px !important;
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        box-sizing: border-box !important;
        min-width: 0 !important;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .filter-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 4px;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        gap: 8px;
        -webkit-overflow-scrolling: touch;
    }
    
    .tab-btn {
        flex: 0 0 auto;
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .search-input {
        width: 100%;
        min-width: 0;
        box-sizing: border-box !important;
    }
    
    .avc-table-wrap {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    
    .table-scroll {
        overflow-x: hidden !important;
        width: 100% !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    
    .avc-table {
        min-width: 0 !important;
        display: block !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    
    .avc-table thead {
        display: none !important; /* Hide headers on mobile */
    }
    
    .avc-table tbody {
        display: block !important;
        width: 100% !important;
    }
    
    .avc-row {
        display: block !important;
        width: auto !important;
        box-sizing: border-box !important;
        background: #fff;
        border: 1px solid var(--sg-border);
        border-radius: 12px;
        margin-bottom: 16px;
        padding: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .avc-row:hover {
        background: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .avc-table td {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 10px 0 !important;
        border-bottom: 1px dashed #f1f5f9 !important;
        font-size: 13px !important;
        width: 100% !important;
        min-width: 0 !important;
        text-align: right !important;
        box-sizing: border-box !important;
    }
    
    .avc-table td:last-child {
        border-bottom: none !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
    }
    
    .avc-table td::before {
        content: attr(data-label);
        font-weight: 700;
        color: rgba(15, 23, 42, 0.5);
        text-align: left;
        margin-right: 12px;
        flex-shrink: 0;
    }
    
    /* Highlight Venue name at the top of the card */
    .avc-table td.col-name {
        display: block !important;
        text-align: left !important;
        padding-top: 0 !important;
        padding-bottom: 12px !important;
        margin-bottom: 8px !important;
        border-bottom: 2px solid #f1f5f9 !important;
        width: auto !important;
        box-sizing: border-box !important;
    }
    
    .avc-table td.col-name::before {
        display: none !important;
    }
    
    .cluster-name {
        font-size: 16px;
    }
    
    .owner-meta-info {
        text-align: right;
    }
    
    .owner-name {
        font-size: 13px;
    }
    
    .owner-email {
        font-size: 11px;
        word-break: break-all;
    }
    
    .status-badge {
        padding: 2px 8px;
        font-size: 11px;
    }
}
</style>

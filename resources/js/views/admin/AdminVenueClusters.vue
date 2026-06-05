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
                            <th>Tên cụm sân</th>
                            <th>Chủ sân</th>
                            <th>Địa chỉ</th>
                            <th>Loại sân</th>
                            <th class="text-center">Số sân con</th>
                            <th class="text-center">Rating</th>
                            <th class="text-center">Trạng thái phí</th>
                            <th class="text-center">Trạng thái</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in filteredClusters"
                            :key="c.id"
                            class="avc-row"
                            @click="goDetail(c.id)"
                        >
                            <td class="cluster-cell-flex">
                                <div class="cluster-thumb-container">
                                    <img
                                        v-if="c.image_path"
                                        :src="imageUrl(c.image_path)"
                                        :alt="c.name"
                                        class="cluster-thumb-img"
                                        @error="hideBrokenImage"
                                    />
                                </div>
                                <div class="cluster-info-meta">
                                    <div class="cluster-name">{{ c.name }}</div>
                                    <div class="cluster-slug">{{ c.slug }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="owner-name">
                                    {{ c.owner?.full_name || "—" }}
                                </div>
                                <div class="owner-email">
                                    {{ c.owner?.email || "" }}
                                </div>
                            </td>
                            <td class="address-cell">{{ c.address }}</td>
                            <td>
                                <div class="court-types">
                                    <span
                                        v-for="(ct, i) in c.court_types || []"
                                        :key="i"
                                        class="ct-chip"
                                        >{{ ct }}</span
                                    >
                                    <span
                                        v-if="
                                            !c.court_types ||
                                            c.court_types.length === 0
                                        "
                                        class="text-muted"
                                        >—</span
                                    >
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="court-count">{{
                                    c.court_count
                                }}</span>
                            </td>
                            <td class="text-center">
                                <span class="rating">
                                    {{ Number(c.rating_avg || 0).toFixed(1) }}
                                    <span class="rating-count"
                                        >({{ c.rating_count }})</span
                                    >
                                </span>
                            </td>
                            <td class="text-center">
                                <span
                                    class="status-badge"
                                    :class="`fee-${c.fee_status}`"
                                >
                                    {{ feeStatusLabel(c.fee_status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span
                                    class="status-badge"
                                    :class="`status-${c.status}`"
                                >
                                    {{ statusLabel(c.status) }}
                                </span>
                            </td>
                            <td class="text-right" @click.stop>
                                <button
                                    class="btn-action btn-view"
                                    @click="goDetail(c.id)"
                                >
                                    Chi tiết
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import { adminVenueClusterService } from "../../services/adminVenueClusterService.js";

export default {
    name: "AdminVenueClusters",
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
        imageUrl(path) {
            if (!path) return "";
            if (/^https?:\/\//.test(path)) return path;
            return `/storage/${path}`;
        },
        hideBrokenImage(e) {
            e.target.style.display = "none";
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
.tab-btn {
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid var(--sg-border);
    background: var(--sg-surface, #f8fafc);
    color: rgba(15, 23, 42, 0.6);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s;
}
.tab-btn.active {
    background: #0f172a;
    border-color: #0f172a;
    color: #fff;
}
.tab-btn:not(.active):hover {
    background: #f1f5f9;
}
.search-input {
    padding: 9px 14px;
    border: 1px solid var(--sg-border);
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    min-width: 280px;
    color: var(--sg-text);
    background: #fff;
    transition: border-color 0.18s;
}
.search-input:focus {
    border-color: #0f172a;
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
    min-width: 1000px;
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

.cluster-cell-flex {
    display: flex;
    align-items: center;
    gap: 12px;
}
.cluster-thumb-container {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    border: 1px solid var(--sg-border);
    background: #f1f5f9;
}
.cluster-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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
    max-width: 200px;
    color: rgba(15, 23, 42, 0.7);
    font-size: 13px;
}

.court-types {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.ct-chip {
    padding: 3px 8px;
    border-radius: 6px;
    background: #f1f5f9;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
}
.court-count {
    font-weight: 700;
    font-size: 15px;
}
.rating {
    font-size: 13px;
    white-space: nowrap;
}
.rating-count {
    color: rgba(15, 23, 42, 0.4);
    font-size: 12px;
}
.text-muted {
    color: rgba(15, 23, 42, 0.4);
}
.text-center {
    text-align: center;
}
.text-right {
    text-align: right;
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
.cluster-thumb-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #94a3b8;
    font-size: 16px;
}
</style>

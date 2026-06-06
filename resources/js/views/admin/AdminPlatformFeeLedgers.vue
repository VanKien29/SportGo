<template>
    <section class="ledger-page">
        <header class="page-head">
            <div>
                <p class="eyebrow">Sổ phí duy trì</p>
            </div>
            <div class="head-actions">
                <button
                    class="btn secondary icon-text"
                    type="button"
                    @click="runReminderCheck"
                >
                    <AppIcon name="bell" size="18" />
                    <span>Chạy kiểm tra nhắc phí</span>
                </button>
                <button
                    class="btn primary icon-text"
                    type="button"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="18" />
                    <span>Tạo kỳ phí</span>
                </button>
            </div>
        </header>

        <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>

        <section class="panel filter-grid">
            <select v-model="filters.venue_cluster_id" @change="loadLedgers">
                <option value="">Tất cả cụm sân</option>
                <option
                    v-for="venue in venues"
                    :key="venue.id"
                    :value="venue.id"
                >
                    {{ venue.name }}
                </option>
            </select>
            <select v-model="filters.owner_id" @change="loadLedgers">
                <option value="">Tất cả owner</option>
                <option
                    v-for="owner in owners"
                    :key="owner.id"
                    :value="owner.id"
                >
                    {{ owner.full_name }}
                </option>
            </select>
            <select v-model="filters.status" @change="loadLedgers">
                <option value="">Tất cả trạng thái</option>
                <option value="pending">Chờ thanh toán</option>
                <option value="paid">Đã thanh toán</option>
                <option value="overdue">Quá hạn</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <select v-model="filters.period_months" @change="loadLedgers">
                <option value="">Tất cả kỳ đóng</option>
                <option v-for="month in periods" :key="month" :value="month">
                    {{ month }} tháng
                </option>
            </select>
            <input
                v-model="filters.period_start"
                type="date"
                @change="loadLedgers"
            />
            <input
                v-model="filters.period_end"
                type="date"
                @change="loadLedgers"
            />
            <input
                v-model="filters.due_date"
                type="date"
                @change="loadLedgers"
            />
            <select v-model="filters.email_status" @change="loadLedgers">
                <option value="">Tất cả email</option>
                <option value="due_soon_7_days">Đã gửi nhắc trước hạn</option>
                <option value="due_today">Đã gửi nhắc đúng hạn</option>
                <option value="overdue_3_days">
                    Đã gửi cảnh báo quá hạn 3 ngày
                </option>
                <option value="not_sent">Chưa gửi nhắc phí</option>
                <option value="failed">Gửi email lỗi</option>
            </select>
            <label class="check-row">
                <input
                    v-model="filters.overdue_only"
                    type="checkbox"
                    @change="loadLedgers"
                />
                <span>Chỉ xem quá hạn</span>
            </label>
            <input
                v-model.trim="filters.keyword"
                placeholder="Tìm mã kỳ phí, cụm sân, owner"
                @input="loadLedgers"
            />
        </section>

        <section class="kpi-grid">
            <router-link
                class="kpi-card"
                to="/admin/platform-fee-ledgers?status=pending"
            >
                <strong>{{ metrics.pending }}</strong
                ><span>Chờ thanh toán</span>
            </router-link>
            <router-link
                class="kpi-card danger"
                to="/admin/platform-fee-ledgers?status=overdue"
            >
                <strong>{{ metrics.overdue }}</strong
                ><span>Quá hạn</span>
            </router-link>
            <article class="kpi-card">
                <strong>{{ money(metrics.pending_amount) }}</strong
                ><span>Chờ thanh toán</span>
            </article>
            <article class="kpi-card danger">
                <strong>{{ money(metrics.overdue_amount) }}</strong
                ><span>Quá hạn</span>
            </article>
        </section>

        <section class="panel">
            <div v-if="loading" class="empty">Đang tải danh sách kỳ phí...</div>
            <div v-else-if="ledgers.length === 0" class="empty">
                Chưa có kỳ phí. Hãy tạo kỳ phí mới.
            </div>
            <div v-else class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Mã kỳ phí</th>
                            <th>Cụm sân</th>
                            <th>Chủ sân</th>
                            <th>Số sân</th>
                            <th>Bậc phí snapshot</th>
                            <th>Kỳ đóng</th>
                            <th>Thời gian kỳ phí</th>
                            <th>Hạn thanh toán</th>
                            <th>Gia snapshot</th>
                            <th>Giảm</th>
                            <th>Phải đóng</th>
                            <th>Đã đóng</th>
                            <th>Còn thiếu</th>
                            <th>Trạng thái</th>
                            <th>Ngày thanh toán</th>
                            <th>Email</th>
                            <th class="actions-header">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ledger in ledgers" :key="ledger.id">
                            <td class="mono">{{ ledger.code }}</td>
                            <td>{{ ledger.venue?.name || "-" }}</td>
                            <td>{{ ledger.owner?.full_name || "-" }}</td>
                            <td>{{ ledger.court_count }}</td>
                            <td>{{ ledger.tier_name }}</td>
                            <td>{{ ledger.period_months }} tháng</td>
                            <td>
                                {{ date(ledger.period_start) }} -
                                {{ date(ledger.period_end) }}
                            </td>
                            <td
                                :class="{
                                    overdue: ledger.status === 'overdue',
                                }"
                            >
                                {{ date(ledger.due_date) }}
                            </td>
                            <td>{{ money(ledger.price_per_court_month) }}</td>
                            <td>{{ percent(ledger.discount_percent) }}</td>
                            <td>{{ money(ledger.amount_due) }}</td>
                            <td>{{ money(ledger.amount_paid) }}</td>
                            <td>{{ money(ledger.remaining_amount) }}</td>
                            <td>
                                <span
                                    class="status-dot"
                                    :class="ledger.status"
                                    :title="statusLabel(ledger.status)"
                                    :aria-label="statusLabel(ledger.status)"
                                ></span>
                            </td>
                            <td>
                                {{
                                    ledger.paid_at ? date(ledger.paid_at) : "-"
                                }}
                            </td>
                            <td>{{ emailSummary(ledger) }}</td>
                            <td>
                                <div class="actions">
                                    <button
                                        class="icon-btn"
                                        type="button"
                                        title="Xem chi tiết"
                                        aria-label="Xem chi tiết"
                                        @click="
                                            $router.push({
                                                name: 'admin-platform-fee-ledger-detail',
                                                params: { id: ledger.id },
                                            })
                                        "
                                    >
                                        <AppIcon name="eye" size="18" />
                                    </button>
                                    <button
                                        class="icon-btn"
                                        type="button"
                                        title="Xác nhận thanh toán"
                                        aria-label="Xác nhận thanh toán"
                                        :disabled="
                                            ledger.status === 'paid' ||
                                            ledger.status === 'cancelled'
                                        "
                                        @click="openPay(ledger)"
                                    >
                                        <AppIcon name="creditCard" size="18" />
                                    </button>
                                    <button
                                        class="icon-btn warning"
                                        type="button"
                                        title="Đánh dấu quá hạn"
                                        aria-label="Đánh dấu quá hạn"
                                        :disabled="
                                            ledger.status === 'paid' ||
                                            ledger.status === 'cancelled'
                                        "
                                        @click="markOverdue(ledger)"
                                    >
                                        <AppIcon name="clock" size="18" />
                                    </button>
                                    <button
                                        class="icon-btn danger"
                                        type="button"
                                        title="Hủy kỳ phí"
                                        aria-label="Hủy kỳ phí"
                                        :disabled="
                                            ledger.status === 'paid' ||
                                            ledger.status === 'cancelled'
                                        "
                                        @click="openCancel(ledger)"
                                    >
                                        <AppIcon name="trash" size="18" />
                                    </button>
                                    <button
                                        class="icon-btn danger"
                                        type="button"
                                        title="Khóa cụm sân"
                                        aria-label="Khóa cụm sân"
                                        :disabled="ledger.status !== 'overdue'"
                                        @click="openLock(ledger)"
                                    >
                                        <AppIcon name="lock" size="18" />
                                    </button>
                                    <button
                                        class="icon-btn success"
                                        type="button"
                                        title="Mở khóa cụm sân"
                                        aria-label="Mở khóa cụm sân"
                                        :disabled="ledger.status !== 'paid'"
                                        @click="unlockVenue(ledger)"
                                    >
                                        <AppIcon name="unlock" size="18" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="showCreate" class="modal-backdrop" @click.self="closeCreate">
            <form class="modal" @submit.prevent="createNewLedger">
                <header class="modal-head">
                    <h3>Tạo kỳ phí duy trì</h3>
                    <button
                        class="icon-close"
                        type="button"
                        title="Đóng"
                        aria-label="Đóng"
                        @click="closeCreate"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>
                <div class="form-grid">
                    <label>
                        Cụm sân *
                        <select
                            v-model="form.venue_cluster_id"
                            required
                            @change="refreshPreview"
                        >
                            <option value="">Chọn cụm sân</option>
                            <option
                                v-for="venue in venues"
                                :key="venue.id"
                                :value="venue.id"
                            >
                                {{ venue.name }} - {{ venue.court_count }} sân
                            </option>
                        </select>
                    </label>
                    <label>
                        Kỳ đóng *
                        <select
                            v-model.number="form.period_months"
                            @change="refreshPreview"
                        >
                            <option
                                v-for="month in periods"
                                :key="month"
                                :value="month"
                            >
                                {{ month }} tháng
                            </option>
                        </select>
                    </label>
                    <label>
                        Ngày bắt đầu *
                        <input
                            v-model="form.period_start"
                            type="date"
                            required
                            @change="refreshPreview"
                        />
                    </label>
                    <label>
                        Hạn thanh toán
                        <input
                            v-model="form.due_date"
                            type="date"
                            @change="refreshPreview"
                        />
                    </label>
                </div>
                <div v-if="previewError" class="alert error">
                    {{ previewError }}
                </div>
                <div v-if="previewResult" class="preview-grid">
                    <div>
                        <span>Số sân snapshot</span
                        ><strong>{{ previewResult.court_count }}</strong>
                    </div>
                    <div>
                        <span>Bậc phí</span
                        ><strong>{{ previewResult.tier.name }}</strong>
                    </div>
                    <div>
                        <span>Kỳ phí</span
                        ><strong
                            >{{ date(previewResult.period_start) }} -
                            {{ date(previewResult.period_end) }}</strong
                        >
                    </div>
                    <div>
                        <span>Tổng phải đóng</span
                        ><strong>{{
                            money(previewResult.fee.amount_due)
                        }}</strong>
                    </div>
                </div>
                <div
                    v-for="warning in previewWarnings"
                    :key="warning"
                    class="alert warning"
                >
                    {{ warning }}
                </div>
                <footer class="modal-actions">
                    <button
                        class="btn secondary"
                        type="button"
                        @click="closeCreate"
                    >
                        Hủy
                    </button>
                    <button
                        class="btn primary icon-text"
                        type="submit"
                        :disabled="!previewResult || Boolean(previewError)"
                    >
                        <AppIcon name="plus" size="18" />
                        <span>Tạo kỳ phí</span>
                    </button>
                </footer>
            </form>
        </div>

        <div
            v-if="dialog.type"
            class="modal-backdrop"
            @click.self="closeDialog"
        >
            <form class="modal small" @submit.prevent="submitDialog">
                <header class="modal-head">
                    <h3>{{ dialogTitle }}</h3>
                    <button
                        class="icon-close"
                        type="button"
                        title="Đóng"
                        aria-label="Đóng"
                        @click="closeDialog"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>
                <div class="form-grid one">
                    <label v-if="dialog.type === 'pay'">
                        Số tiền thanh toán *
                        <input
                            v-model.number="dialog.amount"
                            type="number"
                            min="1"
                            required
                        />
                    </label>
                    <label v-if="dialog.type !== 'pay'">
                        Lý do *
                        <textarea
                            v-model.trim="dialog.reason"
                            rows="4"
                            required
                        ></textarea>
                    </label>
                </div>
                <footer class="modal-actions">
                    <button
                        class="btn secondary"
                        type="button"
                        @click="closeDialog"
                    >
                        Hủy
                    </button>
                    <button class="btn primary icon-text" type="submit">
                        <AppIcon name="check" size="18" />
                        <span>Xác nhận</span>
                    </button>
                </footer>
            </form>
        </div>
    </section>
</template>

<script>
import { platformFeeStore } from "../../stores/platformFee.store.js";
import AppIcon from "../../components/AppIcon.vue";
import {
    calculateLedgerPreview,
    cancelLedger,
    confirmLedgerPayment,
    createLedger,
    getLedgers,
    getPlatformFeeDashboardMetrics,
    lockVenueForOverdueLedger,
    markLedgerOverdue,
    unlockVenueAfterPayment,
} from "../../services/platformFeeLedger.service.js";
import { processPlatformFeeReminders } from "../../services/platformFeeReminder.service.js";

function initialFilters(routeQuery = {}) {
    return {
        venue_cluster_id: "",
        owner_id: "",
        status: routeQuery.status || "",
        period_months: "",
        period_start: "",
        period_end: "",
        due_date: "",
        overdue_only: false,
        email_status: routeQuery.email_status || "",
        range: routeQuery.range || "",
        keyword: "",
    };
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

export default {
    name: "AdminPlatformFeeLedgers",
    components: { AppIcon },
    data() {
        return {
            ledgers: [],
            venues: platformFeeStore.state.venues,
            filters: initialFilters(this.$route.query),
            metrics: getPlatformFeeDashboardMetrics(),
            periods: [1, 3, 6, 9, 12],
            loading: false,
            showCreate: false,
            form: {
                venue_cluster_id: "",
                period_months: 1,
                period_start: today(),
                due_date: "",
            },
            previewResult: null,
            previewError: "",
            previewWarnings: [],
            dialog: { type: "", ledger: null, amount: 0, reason: "" },
            toast: "",
            toastType: "success",
        };
    },
    computed: {
        owners() {
            const map = new Map();
            this.venues.forEach((venue) => {
                if (venue.owner?.id) map.set(venue.owner.id, venue.owner);
            });
            return Array.from(map.values());
        },
        dialogTitle() {
            return (
                {
                    pay: "Xác nhận thanh toán",
                    cancel: "Hủy kỳ phí",
                    lock: "Khóa cụm sân vì quá hạn",
                }[this.dialog.type] || "Xác nhận"
            );
        },
    },
    watch: {
        "$route.query": {
            handler(query) {
                this.filters = initialFilters(query);
                this.loadLedgers();
            },
        },
    },
    mounted() {
        this.loadLedgers();
    },
    methods: {
        async loadLedgers() {
            this.loading = true;
            this.ledgers = await getLedgers(this.filters);
            this.metrics = getPlatformFeeDashboardMetrics();
            this.loading = false;
        },
        openCreate() {
            this.form = {
                venue_cluster_id: "",
                period_months: 1,
                period_start: today(),
                due_date: "",
            };
            this.previewResult = null;
            this.previewError = "";
            this.previewWarnings = [];
            this.showCreate = true;
        },
        closeCreate() {
            this.showCreate = false;
        },
        refreshPreview() {
            if (!this.form.venue_cluster_id) return;
            const result = calculateLedgerPreview(this.form);
            this.previewResult = result.isValid ? result : null;
            this.previewError = result.isValid ? "" : result.error;
            this.previewWarnings = result.warnings || [];
        },
        async createNewLedger() {
            try {
                await createLedger(this.form);
                this.showMessage("Đã tạo kỳ phí chờ thanh toán.");
                this.closeCreate();
                await this.loadLedgers();
            } catch (error) {
                this.previewError = error.message;
                this.showMessage(error.message, "error");
            }
        },
        openPay(ledger) {
            this.dialog = {
                type: "pay",
                ledger,
                amount: ledger.remaining_amount,
                reason: "",
            };
        },
        openCancel(ledger) {
            this.dialog = { type: "cancel", ledger, amount: 0, reason: "" };
        },
        openLock(ledger) {
            this.dialog = {
                type: "lock",
                ledger,
                amount: 0,
                reason: "Quá hạn phí duy trì hệ thống",
            };
        },
        closeDialog() {
            this.dialog = { type: "", ledger: null, amount: 0, reason: "" };
        },
        async submitDialog() {
            try {
                if (this.dialog.type === "pay")
                    await confirmLedgerPayment(this.dialog.ledger.id, {
                        amount: this.dialog.amount,
                    });
                if (this.dialog.type === "cancel")
                    await cancelLedger(
                        this.dialog.ledger.id,
                        this.dialog.reason,
                    );
                if (this.dialog.type === "lock")
                    await lockVenueForOverdueLedger(
                        this.dialog.ledger.id,
                        this.dialog.reason,
                    );
                this.showMessage("Thao tác thành công.");
                this.closeDialog();
                await this.loadLedgers();
            } catch (error) {
                this.showMessage(error.message, "error");
            }
        },
        async markOverdue(ledger) {
            const reason = prompt(
                "Nhập lý do đánh dấu quá hạn:",
                "Quá hạn thanh toán",
            );
            if (!reason) return;
            try {
                await markLedgerOverdue(ledger.id, reason);
                this.showMessage("Đã đánh dấu quá hạn.");
                await this.loadLedgers();
            } catch (error) {
                this.showMessage(error.message, "error");
            }
        },
        async unlockVenue(ledger) {
            try {
                await unlockVenueAfterPayment(ledger.id);
                this.showMessage("Đã mở khóa cụm sân.");
                await this.loadLedgers();
            } catch (error) {
                this.showMessage(error.message, "error");
            }
        },
        async runReminderCheck() {
            const logs = await processPlatformFeeReminders(new Date());
            this.showMessage(
                logs.length
                    ? `Đã xử lý ${logs.length} email nhắc phí.`
                    : "Không có email nhắc phí cần gửi hôm nay.",
            );
            await this.loadLedgers();
        },
        emailSummary(ledger) {
            const logs = ledger.email_logs || [];
            if (!logs.length) return "Chưa gửi";
            if (logs.some((log) => log.status === "failed")) return "Có lỗi";
            return `${logs.filter((log) => log.status === "sent").length} đã gửi`;
        },
        statusLabel(status) {
            return (
                {
                    pending: "Chờ thanh toán",
                    paid: "Đã thanh toán",
                    overdue: "Quá hạn",
                    cancelled: "Đã hủy",
                }[status] || status
            );
        },
        money(value) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(value || 0);
        },
        percent(value) {
            return `${Number(value || 0).toLocaleString("vi-VN")}%`;
        },
        date(value) {
            return value ? new Date(value).toLocaleDateString("vi-VN") : "-";
        },
        showMessage(message, type = "success") {
            this.toast = message;
            this.toastType = type;
            setTimeout(() => {
                this.toast = "";
            }, 3500);
        },
    },
};
</script>

<style scoped>
.ledger-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.page-head,
.head-actions,
.actions,
.modal-head,
.modal-actions,
.icon-text {
    display: flex;
    gap: 12px;
}
.page-head {
    justify-content: space-between;
    align-items: flex-start;
}
.eyebrow {
    margin: 0 0 4px;
    color: #16a34a;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}
h2,
h3,
p {
    margin: 0;
}
.panel,
.kpi-card,
.modal {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.panel {
    padding: 16px;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    align-items: center;
}
input,
select,
textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 12px;
    font: inherit;
}
.check-row {
    flex-direction: row;
    align-items: center;
    font-weight: 800;
    color: #334155;
}
.check-row input {
    width: auto;
}
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}
.kpi-card {
    padding: 16px;
    text-decoration: none;
    color: #0f172a;
}
.kpi-card strong {
    display: block;
    font-size: 24px;
}
.kpi-card span {
    color: #64748b;
}
.kpi-card.danger strong {
    color: #b91c1c;
}
.table-wrap {
    overflow-x: auto;
}
table {
    width: 100%;
    min-width: 1680px;
    border-collapse: collapse;
}
th,
td {
    padding: 11px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
}
th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    text-transform: uppercase;
}
.actions-header {
    text-align: center;
}
.mono {
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
}
.overdue {
    color: #b91c1c;
    font-weight: 900;
}
.status-dot {
    display: inline-grid;
    width: 14px;
    height: 14px;
    border-radius: 999px;
    background: #f59e0b;
    box-shadow: 0 0 0 3px #fef3c7;
}
.status-dot.paid {
    background: #10b981;
    box-shadow: 0 0 0 3px #d1fae5;
}
.status-dot.overdue {
    background: #ef4444;
    box-shadow: 0 0 0 3px #fee2e2;
}
.status-dot.cancelled {
    background: #94a3b8;
    box-shadow: 0 0 0 3px #e2e8f0;
}
.actions {
    flex-wrap: wrap;
    justify-content: center;
    min-width: 244px;
}
.icon-btn,
.icon-close {
    display: inline-grid;
    place-items: center;
    border: 1px solid #dbe3ea;
    border-radius: 8px;
    background: #f8fafc;
    color: #334155;
    cursor: pointer;
}
.icon-btn {
    width: 34px;
    height: 34px;
}
.icon-btn:hover:not(:disabled) {
    background: #eef2f7;
}
.icon-btn.success {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
}
.icon-btn.warning {
    background: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
}
.icon-btn.danger {
    background: #fee2e2;
    color: #991b1b;
    border-color: #fecaca;
}
.icon-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.icon-close {
    width: 32px;
    height: 32px;
}
.btn {
    border: 0;
    border-radius: 8px;
    padding: 10px 14px;
    font-weight: 900;
    cursor: pointer;
}
.btn.primary {
    background: #16a34a;
    color: #fff;
}
.btn.secondary {
    background: #e2e8f0;
    color: #334155;
}
.icon-text {
    align-items: center;
    justify-content: center;
}
.empty {
    padding: 36px;
    text-align: center;
    color: #64748b;
}
.toast {
    border-radius: 8px;
    padding: 11px 13px;
    font-weight: 800;
}
.toast.success {
    background: #ecfdf5;
    color: #047857;
}
.toast.error,
.alert.error {
    background: #fef2f2;
    color: #991b1b;
}
.alert {
    border-radius: 8px;
    padding: 10px 12px;
    margin: 10px 18px 0;
    font-weight: 800;
}
.alert.warning {
    background: #fef3c7;
    color: #92400e;
}
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 900;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.55);
}
.modal {
    width: min(820px, calc(100vw - 32px));
    max-height: calc(100vh - 40px);
    overflow: auto;
}
.modal.small {
    width: min(520px, calc(100vw - 32px));
}
.modal-head {
    justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid #e2e8f0;
}
.modal-head button {
    border: 0;
    background: transparent;
    font-weight: 900;
    cursor: pointer;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    padding: 18px 22px;
}
.form-grid.one {
    grid-template-columns: 1fr;
}
label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-weight: 800;
    color: #334155;
}
.preview-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    padding: 0 18px 10px;
}
.preview-grid div {
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px;
}
.preview-grid span {
    display: block;
    color: #64748b;
    font-size: 12px;
}
.modal-actions {
    justify-content: flex-end;
    padding: 16px 22px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}
@media (max-width: 1000px) {
    .page-head {
        flex-direction: column;
    }
    .filter-grid,
    .kpi-grid,
    .preview-grid,
    .form-grid {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 640px) {
    .filter-grid,
    .kpi-grid,
    .preview-grid,
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

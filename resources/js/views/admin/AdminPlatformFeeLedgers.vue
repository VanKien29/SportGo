<template>
    <section class="ledger-page">
        <PlatformFeeSubnav />

        <!-- Action bar with reminder check button -->
        <div class="action-bar-layout" style="margin-bottom: 12px; display: flex; justify-content: flex-end;">
            <button
                class="btn secondary icon-text"
                type="button"
                @click="runReminderCheck"
            >
                <AppIcon name="bell" size="18" />
                <span>Chạy kiểm tra nhắc phí</span>
            </button>
        </div>

        <!-- Floating Add Button -->
        <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
            <button class="btn-float-add" type="button" @click="openCreate" title="Tạo kỳ phí">
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Tạo kỳ phí</span>
            </button>
        </div>

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
            <label class="date-filter">
                <span>Từ ngày áp dụng</span>
                <input
                    v-model="filters.period_start"
                    type="date"
                    @change="loadLedgers"
                />
            </label>
            <label class="date-filter">
                <span>Đến ngày áp dụng</span>
                <input
                    v-model="filters.period_end"
                    type="date"
                    @change="loadLedgers"
                />
            </label>
            <label class="date-filter">
                <span>Hạn thanh toán</span>
                <input
                    v-model="filters.due_date"
                    type="date"
                    @change="loadLedgers"
                />
            </label>
            <select v-model="filters.email_status" @change="loadLedgers">
                <option value="">Tất cả email</option>
                <option value="due_soon">Đã gửi nhắc trước hạn</option>
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
                            <th>Cụm sân / Chủ sân</th>
                            <th>Bậc phí / Số sân</th>
                            <th>Kỳ áp dụng</th>
                            <th>Hạn thanh toán</th>
                            <th>Công nợ</th>
                            <th>Trạng thái / Email</th>
                            <th class="actions-header">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ledger in ledgers" :key="ledger.id">
                            <td class="mono">{{ ledger.code }}</td>
                            <td class="stacked-cell">
                                <strong>{{ ledger.venue?.name || "-" }}</strong>
                                <small>{{ ledger.owner?.full_name || "-" }}</small>
                            </td>
                            <td class="stacked-cell">
                                <strong>{{ ledger.tier_name }}</strong>
                                <small>{{ ledger.court_count }} sân · {{ money(ledger.price_per_court_month) }}/tháng</small>
                                <small v-if="Number(ledger.discount_percent) > 0">
                                    Giảm {{ percent(ledger.discount_percent) }}
                                </small>
                            </td>
                            <td class="period-cell">
                                <strong class="period-badge">{{ ledger.period_months }} tháng</strong>
                                <span class="date-line">
                                    <small>Từ</small>
                                    <strong>{{ date(ledger.period_start) }}</strong>
                                </span>
                                <span class="date-line">
                                    <small>Đến</small>
                                    <strong>{{ date(ledger.period_end) }}</strong>
                                </span>
                                <small class="period-note" :class="ledger.period_warning_level">
                                    {{ periodStatusLabel(ledger) }}
                                </small>
                            </td>
                            <td
                                :class="{
                                    overdue: ledger.status === 'overdue',
                                }"
                            >
                                <strong>{{ date(ledger.due_date) }}</strong>
                                <small v-if="ledger.paid_at" class="paid-date">
                                    Thanh toán {{ date(ledger.paid_at) }}
                                </small>
                            </td>
                            <td class="debt-cell">
                                <span><small>Phải đóng</small><strong>{{ money(ledger.amount_due) }}</strong></span>
                                <span><small>Đã đóng</small><strong>{{ money(ledger.amount_paid) }}</strong></span>
                                <span v-if="Number(ledger.remaining_amount) > 0" class="remaining">
                                    <small>Còn thiếu</small><strong>{{ money(ledger.remaining_amount) }}</strong>
                                </span>
                            </td>
                            <td class="status-cell">
                                <span class="status-line">
                                <span
                                    class="status-dot"
                                    :class="ledger.status"
                                    :title="statusLabel(ledger.status)"
                                    :aria-label="statusLabel(ledger.status)"
                                ></span>
                                    <strong>{{ statusLabel(ledger.status) }}</strong>
                                </span>
                                <small>{{ emailSummary(ledger) }}</small>
                            </td>
                            <td class="actions-cell">
                                <button
                                    class="icon-btn"
                                    type="button"
                                    title="Mở menu thao tác"
                                    aria-label="Mở menu thao tác"
                                    @click.stop="openLedgerActions($event, ledger)"
                                >
                                    <AppIcon name="moreHorizontal" size="19" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="actionMenu.ledger"
                class="ledger-action-menu"
                :style="{ top: `${actionMenu.top}px`, right: `${actionMenu.right}px` }"
                @click.stop
            >
                <button type="button" @click="selectLedgerAction('view')">
                    <AppIcon name="eye" size="16" /><span>Xem chi tiết</span>
                </button>
                <button
                    type="button"
                    :disabled="actionMenu.ledger.status === 'paid' || actionMenu.ledger.status === 'cancelled'"
                    @click="selectLedgerAction('pay')"
                >
                    <AppIcon name="creditCard" size="16" /><span>Xác nhận thanh toán</span>
                </button>
                <button
                    type="button"
                    :disabled="actionMenu.ledger.status === 'paid' || actionMenu.ledger.status === 'cancelled'"
                    @click="selectLedgerAction('overdue')"
                >
                    <AppIcon name="clock" size="16" /><span>Đánh dấu quá hạn</span>
                </button>
                <button
                    class="danger"
                    type="button"
                    :disabled="!canCancelLedger(actionMenu.ledger)"
                    @click="selectLedgerAction('cancel')"
                >
                    <AppIcon name="trash" size="16" /><span>Hủy kỳ phí</span>
                </button>
                <button
                    class="danger"
                    type="button"
                    :disabled="actionMenu.ledger.status !== 'overdue'"
                    @click="selectLedgerAction('lock')"
                >
                    <AppIcon name="lock" size="16" /><span>Khóa cụm sân</span>
                </button>
                <button
                    class="success"
                    type="button"
                    :disabled="actionMenu.ledger.status !== 'paid'"
                    @click="selectLedgerAction('unlock')"
                >
                    <AppIcon name="unlock" size="16" /><span>Mở khóa cụm sân</span>
                </button>
            </div>
        </Teleport>

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
                    <p v-if="dialog.type === 'cancel'" class="cancel-warning">
                        Kỳ phí sẽ chuyển sang trạng thái “Đã hủy”. Kỳ đã thanh toán hoặc đã ghi nhận một phần tiền không thể hủy.
                    </p>
                    <p v-if="dialog.type === 'discard-create'" class="cancel-warning">
                        Dữ liệu kỳ phí đang nhập chưa được lưu và sẽ bị bỏ.
                    </p>
                    <label v-if="dialog.type === 'pay'">
                        Số tiền thanh toán *
                        <input
                            v-model.number="dialog.amount"
                            type="number"
                            min="1"
                            required
                        />
                    </label>
                    <label v-if="dialog.type !== 'pay' && dialog.type !== 'discard-create'">
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
import AppIcon from "../../components/AppIcon.vue";
import PlatformFeeSubnav from "../../components/PlatformFeeSubnav.vue";
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
import { adminVenueClusterService } from "../../services/adminVenueClusterService.js";
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
    components: { AppIcon, PlatformFeeSubnav },
    data() {
        return {
            ledgers: [],
            venues: [],
            filters: initialFilters(this.$route.query),
            metrics: {
                pending: 0,
                overdue: 0,
                pending_amount: 0,
                overdue_amount: 0,
                paid_this_month: 0,
                locked_venues: 0,
                email_sent_today: 0,
                email_failed: 0,
            },
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
            actionMenu: { ledger: null, top: 0, right: 0 },
            toast: "",
            toastType: "success",
            showScrollTop: false,
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
                    overdue: "Đánh dấu kỳ phí quá hạn",
                    "discard-create": "Hủy tạo kỳ phí?",
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
        this.loadVenueOptions();
        this.loadLedgers();
        window.addEventListener("scroll", this.handleScroll);
        window.addEventListener("click", this.closeLedgerActions);
        window.addEventListener("resize", this.closeLedgerActions);
    },
    beforeUnmount() {
        window.removeEventListener("scroll", this.handleScroll);
        window.removeEventListener("click", this.closeLedgerActions);
        window.removeEventListener("resize", this.closeLedgerActions);
    },
    methods: {
        async loadVenueOptions() {
            try {
                const response = await adminVenueClusterService.list();
                const clusters = Array.isArray(response)
                    ? response
                    : response.data || [];
                this.syncVenueOptions(
                    clusters.map((cluster) => ({
                        venue: {
                            id: cluster.id,
                            name: cluster.name,
                            status: cluster.status,
                            owner_id: cluster.owner_id,
                            court_count: cluster.court_count,
                            owner: cluster.owner || null,
                        },
                        owner: cluster.owner || null,
                    })),
                );
            } catch (error) {
                this.showMessage(
                    "Không tải được danh sách cụm sân từ DB.",
                    "error",
                );
            }
        },
        async loadLedgers() {
            this.loading = true;
            try {
                this.ledgers = await getLedgers(this.filters);
                this.syncVenueOptions(this.ledgers);
                this.metrics = await getPlatformFeeDashboardMetrics();
            } finally {
                this.loading = false;
            }
        },
        syncVenueOptions(ledgers) {
            const map = new Map(this.venues.map((venue) => [venue.id, venue]));
            ledgers.forEach((ledger) => {
                if (ledger.venue?.id) {
                    map.set(ledger.venue.id, {
                        ...ledger.venue,
                        court_count:
                            ledger.venue.court_count ||
                            ledger.court_count ||
                            ledger.venue.venue_courts_count ||
                            0,
                        owner: ledger.owner || ledger.venue.owner || null,
                    });
                }
            });
            this.venues = Array.from(map.values());
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
            if (this.showCreate && this.form.venue_cluster_id) {
                this.dialog = {
                    type: "discard-create",
                    ledger: null,
                    amount: 0,
                    reason: "",
                };
                return;
            }
            this.showCreate = false;
        },
        async refreshPreview() {
            if (!this.form.venue_cluster_id) return;
            try {
                const result = await calculateLedgerPreview(this.form);
                this.previewResult = result.isValid ? result : null;
                this.previewError = result.isValid ? "" : result.error;
                this.previewWarnings = result.warnings || [];
            } catch (error) {
                const result = error.data?.preview || null;
                this.previewResult = null;
                this.previewError = result?.error || error.message;
                this.previewWarnings = result?.warnings || [];
            }
        },
        async createNewLedger() {
            try {
                await createLedger(this.form);
                this.showMessage("Đã tạo kỳ phí chờ thanh toán.");
                this.showCreate = false;
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
        canCancelLedger(ledger) {
            return ledger.can_cancel === true;
        },
        openLock(ledger) {
            this.dialog = {
                type: "lock",
                ledger,
                amount: 0,
                reason: "Quá hạn phí duy trì hệ thống",
            };
        },
        openOverdue(ledger) {
            this.dialog = {
                type: "overdue",
                ledger,
                amount: 0,
                reason: "Quá hạn thanh toán",
            };
        },
        openLedgerActions(event, ledger) {
            const rect = event.currentTarget.getBoundingClientRect();
            const menuHeight = 286;
            const preferredTop = rect.bottom + 6;
            this.actionMenu = {
                ledger,
                top:
                    preferredTop + menuHeight > window.innerHeight
                        ? Math.max(12, rect.top - menuHeight - 6)
                        : preferredTop,
                right: Math.max(12, window.innerWidth - rect.right),
            };
        },
        closeLedgerActions() {
            this.actionMenu = { ledger: null, top: 0, right: 0 };
        },
        selectLedgerAction(action) {
            const ledger = this.actionMenu.ledger;
            if (!ledger) return;
            this.closeLedgerActions();

            if (action === "view") {
                this.$router.push({
                    name: "admin-platform-fee-ledger-detail",
                    params: { id: ledger.id },
                });
            }
            if (action === "pay") this.openPay(ledger);
            if (action === "overdue") this.openOverdue(ledger);
            if (action === "cancel") this.openCancel(ledger);
            if (action === "lock") this.openLock(ledger);
            if (action === "unlock") this.unlockVenue(ledger);
        },
        closeDialog() {
            this.dialog = { type: "", ledger: null, amount: 0, reason: "" };
        },
        async submitDialog() {
            if (this.dialog.type === "discard-create") {
                this.showCreate = false;
                this.closeDialog();
                return;
            }

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
                if (this.dialog.type === "overdue")
                    await markLedgerOverdue(
                        this.dialog.ledger.id,
                        this.dialog.reason,
                    );
                this.showMessage(
                    this.dialog.type === "cancel"
                        ? "Đã hủy kỳ phí."
                        : "Thao tác thành công.",
                );
                this.closeDialog();
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
            try {
                const results = await processPlatformFeeReminders();
                this.showMessage(`Đã xử lý ${results.length} email nhắc phí.`);
                await this.loadLedgers();
            } catch (error) {
                this.showMessage(error.message, "error");
            }
        },
        emailSummary(ledger) {
            const logs = ledger.email_logs || [];
            if (!logs.length) return "Chưa gửi";
            if (logs.some((log) => log.status === "failed")) return "Có lỗi";
            return `${logs.filter((log) => log.status === "sent").length} đã gửi`;
        },
        periodRemainingLabel(ledger) {
            if (ledger.period_state === "upcoming") return "Chưa bắt đầu";
            if (ledger.period_state === "expired")
                return "Đã hết hạn " + Math.abs(ledger.period_days_remaining || 0) + " ngày";
            if (ledger.period_days_remaining === 0) return "Hết hạn hôm nay";
            if (
                ledger.period_days_remaining !== null &&
                ledger.period_days_remaining !== undefined
            )
                return "Còn " + ledger.period_days_remaining + " ngày";
            return "Chưa cập nhật";
        },
        periodStatusLabel(ledger) {
            const state = {
                active: "Đang hiệu lực",
                upcoming: "Sắp áp dụng",
                expired: "Đã hết hạn",
                unknown: "Chưa rõ thời gian",
            }[ledger.period_state] || "";
            return state
                ? state + " · " + this.periodRemainingLabel(ledger)
                : this.periodRemainingLabel(ledger);
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
            if (!value) return "-";

            const dateOnly = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (dateOnly) {
                return `${dateOnly[3]}/${dateOnly[2]}/${dateOnly[1]}`;
            }

            return new Intl.DateTimeFormat("vi-VN", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            }).format(new Date(value));
        },
        showMessage(message, type = "success") {
            this.toast = message;
            this.toastType = type;
            setTimeout(() => {
                this.toast = "";
            }, 3500);
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
            this.closeLedgerActions();
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
.head-actions,
.actions,
.modal-head,
.modal-actions,
.icon-text {
    display: flex;
    gap: 12px;
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
.date-filter {
    display: grid;
    gap: 5px;
}
.date-filter span {
    color: #475569;
    font-size: 11px;
    font-weight: 800;
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
    position: relative;
    overflow-x: auto;
}
table {
    width: 100%;
    min-width: 1080px;
    table-layout: fixed;
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
th:nth-child(1) { width: 145px; }
th:nth-child(2) { width: 170px; }
th:nth-child(3) { width: 190px; }
th:nth-child(4) { width: 190px; }
th:nth-child(5) { width: 135px; }
th:nth-child(6) { width: 190px; }
th:nth-child(7) { width: 145px; }
th:nth-child(8) { width: 62px; }
.stacked-cell strong,
.stacked-cell small,
.paid-date,
.status-cell > small {
    display: block;
}
.stacked-cell small,
.paid-date,
.status-cell > small {
    margin-top: 4px;
    color: #64748b;
    line-height: 1.35;
}
.period-cell {
    min-width: 180px;
}
.period-badge {
    display: inline-block;
    margin-bottom: 6px;
    color: #334155;
}
.date-line {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
    white-space: nowrap;
}
.date-line + .date-line {
    margin-top: 3px;
}
.date-line small {
    color: #64748b;
    font-size: 11px;
}
.date-line strong {
    color: #0f172a;
}
.debt-cell {
    display: grid;
    gap: 5px;
}
.debt-cell span {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
}
.debt-cell small {
    color: #64748b;
    font-size: 11px;
}
.debt-cell .remaining,
.debt-cell .remaining small {
    color: #b91c1c;
}
.status-line {
    display: flex;
    align-items: center;
    gap: 9px;
}
.status-line strong {
    white-space: nowrap;
}
.actions-cell {
    text-align: center;
    vertical-align: middle;
}
.mono {
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
}
.overdue {
    color: #b91c1c;
    font-weight: 900;
}
.period-note {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
}
.period-note.expiring_soon { color: #92400e; }
.period-note.overdue { color: #b91c1c; }
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
.ledger-action-menu {
    position: fixed;
    z-index: 1200;
    display: grid;
    width: 230px;
    padding: 6px;
    border: 1px solid #dbe3ea;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.2);
}
.ledger-action-menu button {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 9px 10px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #334155;
    font: inherit;
    font-weight: 750;
    text-align: left;
    cursor: pointer;
}
.ledger-action-menu button:hover:not(:disabled) {
    background: #f1f5f9;
}
.ledger-action-menu button.danger {
    color: #b91c1c;
}
.ledger-action-menu button.success {
    color: #047857;
}
.ledger-action-menu button:disabled {
    cursor: not-allowed;
    opacity: 0.4;
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
.cancel-warning {
    margin: 0;
    padding: 12px;
    border-radius: 8px;
    background: #fff7ed;
    color: #9a3412;
    line-height: 1.5;
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

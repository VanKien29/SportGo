<template>
    <div class="cluster-profile-surface standalone">
        <div class="profile-section-card ledgers-main-content">
            <section class="ledger-page">
                <div class="pf-header-bar">
                    <PlatformFeeSubnav />

                    <!-- Action bar with reminder check button -->
                    <div class="header-actions">
                        <button
                            class="btn secondary icon-text run-reminder-btn"
                            type="button"
                            :disabled="reminderRunning"
                            @click="runReminderCheck"
                        >
                            <AppIcon name="bell" size="18" />
                            <span>{{
                                reminderRunning
                                    ? "Đang kiểm tra..."
                                    : "Chạy kiểm tra nhắc phí"
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Floating Add Button -->
                <div
                    class="floating-add-container"
                    :class="{ 'has-scroll': showScrollTop }"
                >
                    <button
                        class="btn-float-add"
                        type="button"
                        @click="openCreate"
                        title="Tạo kỳ phí"
                    >
                        <AppIcon name="plus" size="20" />
                        <span class="btn-float-text">Tạo kỳ phí</span>
                    </button>
                </div>

                <div v-if="toast" class="toast" :class="toastType">
                    {{ toast }}
                </div>

                <AdminFilterPanel
                    panel-class="filter-grid"
                    :show-refresh="false"
                >
                    <VenueClusterCombobox
                        class="filter-wide"
                        :model-value="filters.venue_cluster_id"
                        placeholder="Tìm theo cụm sân hoặc Chủ sân"
                        @update:model-value="changeVenueFilter"
                    />
                    <select v-model="filters.status" @change="applyFilters">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending">Chờ thanh toán</option>
                        <option value="paid">Đã thanh toán</option>
                        <option value="overdue">Quá hạn</option>
                        <option value="awaiting_acceptance">Chờ chủ sân xác nhận</option>
                        <option value="settled_zero">Kỳ 0 đ</option>
                        <option value="voided">Đã vô hiệu</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                    <select
                        v-model="filters.period_months"
                        @change="applyFilters"
                    >
                        <option value="">Tất cả kỳ đóng</option>
                        <option
                            v-for="month in periods"
                            :key="month"
                            :value="month"
                        >
                            {{ month }} tháng
                        </option>
                    </select>
                    <label class="date-filter">
                        <span>Từ ngày áp dụng</span>
                        <input
                            v-model="filters.period_start"
                            type="date"
                            @change="applyFilters"
                        />
                    </label>
                    <label class="date-filter">
                        <span>Đến ngày áp dụng</span>
                        <input
                            v-model="filters.period_end"
                            type="date"
                            @change="applyFilters"
                        />
                    </label>
                    <label class="date-filter">
                        <span>Hạn thanh toán</span>
                        <input
                            v-model="filters.due_date"
                            type="date"
                            @change="applyFilters"
                        />
                    </label>
                    <select
                        v-model="filters.email_status"
                        @change="applyFilters"
                    >
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
                            @change="applyFilters"
                        />
                        <span>Chỉ xem quá hạn</span>
                    </label>
                    <label class="search-box filter-wide">
                        <AppIcon name="search" size="18" />
                        <input
                            v-model.trim="filters.keyword"
                            placeholder="Tìm mã kỳ phí, cụm sân, owner"
                            @input="queueLoadLedgers"
                        />
                    </label>
                </AdminFilterPanel>

                <section class="kpi-grid">
                    <router-link
                        class="kpi-card"
                        to="/admin/platform-fee-ledgers?status=pending"
                    >
                        <strong>{{ metrics.pending }}</strong
                        ><span>Kỳ trong hạn</span>
                    </router-link>
                    <router-link
                        class="kpi-card danger"
                        to="/admin/platform-fee-ledgers?status=overdue"
                    >
                        <strong>{{ metrics.overdue }}</strong
                        ><span>Kỳ quá hạn</span>
                    </router-link>
                    <article class="kpi-card">
                        <strong>{{ money(metrics.pending_amount) }}</strong
                        ><span>Công nợ trong hạn</span>
                    </article>
                    <article class="kpi-card danger">
                        <strong>{{ money(metrics.overdue_amount) }}</strong
                        ><span>Công nợ quá hạn</span>
                    </article>
                </section>

                <section class="panel">
                    <div v-if="loading" class="state-box animate-fade-in">
                        <div class="spinner"></div>
                        <p>Đang tải danh sách kỳ phí...</p>
                    </div>
                    <div v-else-if="ledgers.length === 0" class="empty">
                        Chưa có kỳ phí. Hãy tạo kỳ phí mới.
                    </div>
                    <div v-else class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kỳ phí</th>
                                    <th>Cụm sân</th>
                                    <th>Thời gian dịch vụ</th>
                                    <th>Giá trị kỳ</th>
                                    <th>Thanh toán</th>
                                    <th>Trạng thái & nhắc phí</th>
                                    <th class="actions-header">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ledger in ledgers" :key="ledger.id">
                                    <td class="stacked-cell ledger-code-cell">
                                        <strong class="mono">{{ ledger.code }}</strong>
                                        <small>{{ ledger.tier_name }}</small>
                                        <small v-if="ledger.plan_version?.code">{{ ledger.plan_version.code }}</small>
                                    </td>
                                    <td class="stacked-cell">
                                        <strong>{{
                                            ledger.venue?.name || "-"
                                        }}</strong>
                                        <small>{{
                                            ledger.owner?.full_name || "-"
                                        }}</small>
                                        <small
                                            >{{ ledger.court_count }} sân ·
                                            {{
                                                money(
                                                    ledger.price_per_court_month,
                                                )
                                            }}/tháng</small
                                        >
                                    </td>
                                    <td class="period-cell">
                                        <strong class="period-badge"
                                            >{{ ledger.period_label }}</strong
                                        >
                                        <span class="date-line">
                                            <small>Từ</small>
                                            <strong>{{
                                                date(ledger.period_start)
                                            }}</strong>
                                        </span>
                                        <span class="date-line">
                                            <small>Đến</small>
                                            <strong>{{
                                                date(ledger.period_end)
                                            }}</strong>
                                        </span>
                                        <small
                                            class="period-note"
                                            :class="ledger.period_warning_level"
                                        >
                                            {{ periodStatusLabel(ledger) }}
                                        </small>
                                    </td>
                                    <td class="amount-cell">
                                        <span><small>Giá gốc</small><strong>{{ money(ledger.base_amount) }}</strong></span>
                                        <span v-if="discountTotal(ledger) > 0" class="discount"><small>Tổng giảm</small><strong>-{{ money(discountTotal(ledger)) }}</strong></span>
                                        <span class="final-amount"><small>Tổng tiền kỳ</small><strong>{{ money(ledger.amount_due) }}</strong></span>
                                    </td>
                                    <td class="payment-cell">
                                        <span><small>Đã thanh toán</small><strong>{{ money(ledger.amount_paid) }}</strong></span>
                                        <span class="remaining" :class="{ settled: Number(ledger.remaining_amount) === 0 }"><small>Còn phải trả</small><strong>{{ money(ledger.remaining_amount) }}</strong></span>
                                        <small class="due-line" :class="{ overdue: ledger.status === 'overdue' }">Hạn {{ date(ledger.due_date) }}</small>
                                        <small v-if="ledger.paid_at" class="paid-date">Đã trả {{ date(ledger.paid_at) }}</small>
                                    </td>
                                    <td class="status-cell">
                                        <span class="status-line">
                                            <span
                                                class="status-dot"
                                                :class="ledger.status"
                                                :title="
                                                    statusLabel(ledger.status)
                                                "
                                                :aria-label="
                                                    statusLabel(ledger.status)
                                                "
                                            ></span>
                                            <strong>{{
                                                statusLabel(ledger.status)
                                            }}</strong>
                                        </span>
                                        <small>{{
                                            emailSummary(ledger)
                                        }}</small>
                                    </td>
                                    <td class="actions-cell">
                                        <button
                                            class="icon-btn"
                                            type="button"
                                            title="Mở menu thao tác"
                                            aria-label="Mở menu thao tác"
                                            @click.stop="
                                                openLedgerActions(
                                                    $event,
                                                    ledger,
                                                )
                                            "
                                        >
                                            <AppIcon
                                                name="moreHorizontal"
                                                size="19"
                                            />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <footer v-if="pagination.total > 0" class="pagination-bar">
                        <span>Hiển thị {{ pageFrom }}–{{ pageTo }} trong {{ pagination.total }} kỳ phí</span>
                        <div>
                            <button type="button" :disabled="pagination.current_page <= 1 || loading" @click="goToPage(pagination.current_page - 1)">Trước</button>
                            <strong>Trang {{ pagination.current_page }}/{{ pagination.last_page }}</strong>
                            <button type="button" :disabled="pagination.current_page >= pagination.last_page || loading" @click="goToPage(pagination.current_page + 1)">Sau</button>
                        </div>
                    </footer>
                </section>

                <Teleport to="body">
                    <div
                        v-if="actionMenu.ledger"
                        class="ledger-action-menu"
                        :style="{
                            top: `${actionMenu.top}px`,
                            right: `${actionMenu.right}px`,
                        }"
                        @click.stop
                    >
                        <button
                            type="button"
                            @click="selectLedgerAction('view')"
                        >
                            <AppIcon name="eye" size="16" /><span
                                >Xem chi tiết</span
                            >
                        </button>
                        <button
                            type="button"
                            :disabled="
                                actionMenu.ledger.status === 'paid' ||
                                actionMenu.ledger.status === 'cancelled'
                            "
                            @click="selectLedgerAction('pay')"
                        >
                            <AppIcon name="creditCard" size="16" /><span
                                >Xác nhận thanh toán</span
                            >
                        </button>
                        <button
                            type="button"
                            :disabled="
                                actionMenu.ledger.status === 'paid' ||
                                actionMenu.ledger.status === 'cancelled'
                            "
                            @click="selectLedgerAction('overdue')"
                        >
                            <AppIcon name="clock" size="16" /><span
                                >Đánh dấu quá hạn</span
                            >
                        </button>
                        <button
                            class="danger"
                            type="button"
                            :disabled="!canCancelLedger(actionMenu.ledger)"
                            @click="selectLedgerAction('cancel')"
                        >
                            <AppIcon name="trash" size="16" /><span
                                >Hủy kỳ phí</span
                            >
                        </button>
                        <button
                            class="danger"
                            type="button"
                            :disabled="actionMenu.ledger.status !== 'overdue'"
                            @click="selectLedgerAction('lock')"
                        >
                            <AppIcon name="lock" size="16" /><span
                                >Khóa cụm sân</span
                            >
                        </button>
                        <button
                            class="success"
                            type="button"
                            :disabled="actionMenu.ledger.status !== 'paid'"
                            @click="selectLedgerAction('unlock')"
                        >
                            <AppIcon name="unlock" size="16" /><span
                                >Mở khóa cụm sân</span
                            >
                        </button>
                    </div>
                </Teleport>

                <div
                    v-if="showCreate"
                    class="modal-backdrop"
                    @click.self="closeCreate"
                >
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
                            <label class="venue-field">
                                Cụm sân *
                                <VenueClusterCombobox
                                    :model-value="form.venue_cluster_id"
                                    placeholder="Nhập tên cụm sân hoặc Chủ sân"
                                    require-courts
                                    @update:model-value="changeCreateVenue"
                                />
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
                                ><strong>{{
                                    previewResult.court_count
                                }}</strong>
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
                                :disabled="
                                    !previewResult || Boolean(previewError)
                                "
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
                            <p
                                v-if="dialog.type === 'cancel'"
                                class="cancel-warning"
                            >
                                Kỳ phí sẽ chuyển sang trạng thái “Đã hủy”. Kỳ đã
                                thanh toán hoặc đã ghi nhận một phần tiền không
                                thể hủy.
                            </p>
                            <p
                                v-if="dialog.type === 'discard-create'"
                                class="cancel-warning"
                            >
                                Dữ liệu kỳ phí đang nhập chưa được lưu và sẽ bị
                                bỏ.
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
                            <label
                                v-if="
                                    dialog.type !== 'pay' &&
                                    dialog.type !== 'discard-create'
                                "
                            >
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
        </div>
    </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import PlatformFeeSubnav from "../../components/PlatformFeeSubnav.vue";
import AdminFilterPanel from "../../components/AdminFilterPanel.vue";
import VenueClusterCombobox from "../../components/admin/VenueClusterCombobox.vue";
import {
    calculateLedgerPreview,
    cancelLedger,
    confirmLedgerPayment,
    createLedger,
    getLedgerPage,
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
    components: { AppIcon, PlatformFeeSubnav, AdminFilterPanel, VenueClusterCombobox },
    data() {
        return {
            ledgers: [],
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
            pagination: {
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
            },
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
            filterTimer: null,
            loadToken: 0,
            reminderRunning: false,
        };
    },
    computed: {
        pageFrom() {
            if (!this.pagination.total) return 0;
            return (this.pagination.current_page - 1) * this.pagination.per_page + 1;
        },
        pageTo() {
            return Math.min(this.pagination.current_page * this.pagination.per_page, this.pagination.total);
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
                this.pagination.current_page = 1;
                this.loadLedgers();
            },
        },
    },
    mounted() {
        this.loadLedgers();
        window.addEventListener("scroll", this.handleScroll);
        window.addEventListener("click", this.closeLedgerActions);
        window.addEventListener("resize", this.closeLedgerActions);
    },
    beforeUnmount() {
        clearTimeout(this.filterTimer);
        window.removeEventListener("scroll", this.handleScroll);
        window.removeEventListener("click", this.closeLedgerActions);
        window.removeEventListener("resize", this.closeLedgerActions);
    },
    methods: {
        queueLoadLedgers() {
            clearTimeout(this.filterTimer);
            this.filterTimer = setTimeout(() => this.applyFilters(), 320);
        },
        applyFilters() {
            this.pagination.current_page = 1;
            this.loadLedgers();
        },
        changeVenueFilter(value) {
            this.filters.venue_cluster_id = value;
            this.applyFilters();
        },
        changeCreateVenue(value) {
            this.form.venue_cluster_id = value;
            this.previewResult = null;
            this.previewError = "";
            if (value) this.refreshPreview();
        },
        goToPage(page) {
            const next = Math.max(1, Math.min(Number(page), this.pagination.last_page));
            if (next === this.pagination.current_page) return;
            this.pagination.current_page = next;
            this.loadLedgers();
        },
        async loadLedgers() {
            const requestToken = ++this.loadToken;
            this.loading = true;
            try {
                const response = await getLedgerPage({
                    ...this.filters,
                    page: this.pagination.current_page,
                    per_page: this.pagination.per_page,
                });
                if (requestToken !== this.loadToken) return;
                this.ledgers = response.data;
                this.pagination = { ...this.pagination, ...response.meta };
                this.metrics = { ...this.metrics, ...response.metrics };
            } catch (error) {
                if (requestToken === this.loadToken) {
                    this.showMessage(
                        error.message || "Không tải được lịch sử phí nền tảng.",
                        "error",
                    );
                }
            } finally {
                if (requestToken === this.loadToken) this.loading = false;
            }
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
            if (this.reminderRunning) return;
            this.reminderRunning = true;
            try {
                const results = await processPlatformFeeReminders();
                this.showMessage(`Đã xử lý ${results.length} email nhắc phí.`);
                await this.loadLedgers();
            } catch (error) {
                this.showMessage(error.message, "error");
            } finally {
                this.reminderRunning = false;
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
                return (
                    "Đã hết hạn " +
                    Math.abs(ledger.period_days_remaining || 0) +
                    " ngày"
                );
            if (ledger.period_days_remaining === 0) return "Hết hạn hôm nay";
            if (
                ledger.period_days_remaining !== null &&
                ledger.period_days_remaining !== undefined
            )
                return "Còn " + ledger.period_days_remaining + " ngày";
            return "Chưa cập nhật";
        },
        periodStatusLabel(ledger) {
            const state =
                {
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
                    awaiting_acceptance: "Chờ chủ sân xác nhận",
                    settled_zero: "Không phải thanh toán",
                    voided: "Đã vô hiệu",
                    written_off: "Đã xử lý công nợ",
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
        discountTotal(ledger) {
            return Math.max(Number(ledger.base_amount || 0) - Number(ledger.amount_due || 0), 0);
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
:deep(.filter-controls.filter-grid) {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 10px;
    align-items: center;
}
:deep(.filter-controls.filter-grid) > * {
    min-width: 0;
}
.filter-wide {
    grid-column: span 2;
}
.date-filter {
    display: grid;
    gap: 5px;
}
.date-filter span {
    color: #475569;
    font-size: 11px;
    font-weight: 400;
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
.pf-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}
.run-reminder-btn {
    transition: all 0.2s ease-in-out;
}
.run-reminder-btn.never-hover-class-placeholder {
    background: var(--admin-primary-soft, #f0fdf4) !important;
    color: var(--admin-primary, #22a653) !important;
    border-color: color-mix(
        in srgb,
        var(--admin-primary, #22a653) 35%,
        transparent
    ) !important;
    transform: translateY(-1px);
}
.check-row {
    flex-direction: row;
    align-items: center;
    font-weight: 400;
    color: #334155;
}
.check-row input {
    width: auto;
}
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;

    align-items: start;
}

.kpi-card {
    padding: 8px 16px;

    text-decoration: none;
    color: #0f172a;

    height: auto !important;
    min-height: 0 !important;

    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 4px;
}

.kpi-card strong {
    display: block;
    font-size: 24px;
    line-height: 1.2;
}

.kpi-card span {
    color: #64748b;
    line-height: 1.2;
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
    min-width: 1120px;
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
th:nth-child(1) {
    width: 165px;
}
th:nth-child(2) {
    width: 195px;
}
th:nth-child(3) {
    width: 205px;
}
th:nth-child(4) {
    width: 180px;
}
th:nth-child(5) {
    width: 185px;
}
th:nth-child(6) {
    width: 175px;
}
th:nth-child(7) {
    width: 62px;
}
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
.amount-cell,
.payment-cell {
    display: grid;
    gap: 5px;
}
.amount-cell span,
.payment-cell span {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
}
.amount-cell small,
.payment-cell small {
    color: #64748b;
    font-size: 11px;
}
.amount-cell .discount,
.amount-cell .discount small {
    color: #047857;
}
.amount-cell .final-amount {
    margin-top: 2px;
    padding-top: 5px;
    border-top: 1px dashed #cbd5e1;
}
.payment-cell .remaining,
.payment-cell .remaining small {
    color: #b91c1c;
}
.payment-cell .remaining.settled,
.payment-cell .remaining.settled small {
    color: #047857;
}
.payment-cell .due-line,
.payment-cell .paid-date {
    display: block;
    margin-top: 2px;
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
    font-weight: 400;
}
.period-note {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
}
.period-note.expiring_soon {
    color: #92400e;
}
.period-note.overdue {
    color: #b91c1c;
}
.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 12px 2px;
    color: #64748b;
    font-size: 13px;
}
.pagination-bar div {
    display: flex;
    align-items: center;
    gap: 10px;
}
.pagination-bar button {
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    padding: 7px 11px;
    background: #fff;
    color: #334155;
    cursor: pointer;
}
.pagination-bar button:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}
.pagination-bar strong {
    color: #334155;
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
.status-dot.awaiting_acceptance {
    background: #3b82f6;
    box-shadow: 0 0 0 3px #dbeafe;
}
.status-dot.settled_zero {
    background: #10b981;
    box-shadow: 0 0 0 3px #d1fae5;
}
.status-dot.voided,
.status-dot.written_off {
    background: #64748b;
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
.icon-btn.never-hover-class-placeholder:not(:disabled) {
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
    font-weight: 400;
    text-align: left;
    cursor: pointer;
}
.ledger-action-menu button.never-hover-class-placeholder:not(:disabled) {
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
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
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
    position: sticky;
    z-index: 2;
    top: 0;
    background: #fff;
    justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid #e2e8f0;
}
.modal-head button {
    border: 0;
    background: transparent;
    font-weight: 400;
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
    font-weight: 400;
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
    :deep(.filter-controls.filter-grid),
    .kpi-grid,
    .preview-grid,
    .form-grid {
        grid-template-columns: 1fr 1fr !important;
    }
}
@media (max-width: 640px) {
    :deep(.filter-controls.filter-grid),
    .kpi-grid,
    .preview-grid,
    .form-grid {
        grid-template-columns: 1fr !important;
    }
    .pagination-bar {
        align-items: flex-start;
        flex-direction: column;
    }
    .filter-wide {
        grid-column: auto;
    }
}

.profile-section-card.ledgers-main-content {
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 0;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.profile-section-card.ledgers-main-content {
    padding: 16px;
}
:deep(.filter-controls.filter-grid) {
    gap: 12px;
}
:deep(.search-box) {
    position: relative;
    min-width: 0;
}
:deep(.search-box > .app-icon) {
    position: absolute;
    inset-inline-start: 13px;
    top: 50%;
    z-index: 1;
    color: #64748b;
    pointer-events: none;
    transform: translateY(-50%);
}
:deep(.search-box > input) {
    min-width: 0;
    padding: 10px 12px 10px 44px;
}
:deep(.search-box) {
    display: block !important;
    position: relative !important;
    padding: 0 !important;
}
:deep(.search-box > .app-icon) {
    position: absolute !important;
    inset-inline-start: 13px !important;
    top: 50% !important;
    z-index: 1 !important;
    pointer-events: none !important;
    transform: translateY(-50%) !important;
}
:deep(.search-box > input) {
    box-sizing: border-box;
    min-height: 40px !important;
    padding: 10px 12px 10px 44px !important;
}
</style>

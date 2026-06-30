<template>
    <section class="page">
        <section class="filters">
            <input
                v-model.trim="filters.keyword"
                placeholder="Tìm mã hoặc tên voucher"
                @keyup.enter="load"
            />
            <select v-model="filters.status" @change="load">
                <option value="">Tất cả trạng thái</option>
                <option value="draft">Bản nháp</option>
                <option value="active">Đang áp dụng</option>
                <option value="inactive">Đã tắt</option>
                <option value="expired">Hết hạn</option>
            </select>
            <select v-model="filters.discount_type" @change="load">
                <option value="">Tất cả loại giảm</option>
                <option value="percent">Phần trăm</option>
                <option value="fixed">Số tiền</option>
            </select>
            <ActionIconButton
                icon="filter"
                label="Lọc danh sách"
                @click="load"
            />
        </section>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="success" class="alert success">{{ success }}</div>

        <section class="budget-card">
            <div class="budget-copy">
                <span class="eyebrow">Ngân sách khuyến mãi</span>
                <h3>Theo dõi chi phí voucher hệ thống</h3>
            </div>
            <div class="budget-metrics">
                <article class="budget-metric">
                    <span>Đã dùng kỳ này</span>
                    <strong>{{
                        money(
                            promotionExpenses?.voucher_total ||
                                promotionExpenses?.total,
                        )
                    }}</strong>
                </article>
                <article
                    class="budget-metric"
                    :class="{ warning: promotionBudget?.is_over_budget }"
                >
                    <span>Ngân sách</span>
                    <strong>{{
                        money(budgetSettings.promotion_budget)
                    }}</strong>
                </article>
                <article class="budget-metric">
                    <span>Tỷ lệ dùng</span>
                    <strong>{{ budgetUsageText }}</strong>
                </article>
            </div>
            <form class="budget-form" @submit.prevent="saveBudget">
                <label class="budget-toggle">
                    <input
                        v-model="budgetSettings.is_alert_enabled"
                        type="checkbox"
                    />
                    <span>Bật cảnh báo</span>
                </label>
                <label class="budget-field">
                    <span>Ngân sách</span>
                    <input
                        v-model.number="budgetSettings.promotion_budget"
                        type="number"
                        min="0"
                        step="1000"
                    />
                </label>
                <label class="budget-field">
                    <span>Kỳ ngân sách</span>
                    <select v-model="budgetSettings.budget_period">
                        <option value="week">Tuần</option>
                        <option value="month">Tháng</option>
                        <option value="year">Năm</option>
                    </select>
                </label>
                <button
                    class="btn primary"
                    type="submit"
                    :disabled="budgetSaving"
                >
                    {{ budgetSaving ? "Đang lưu..." : "Lưu ngân sách" }}
                </button>
            </form>
            <div v-if="budgetLoading" class="budget-note">
                Đang tải ngân sách khuyến mãi...
            </div>
            <div v-else-if="budgetError" class="budget-note danger">
                {{ budgetError }}
            </div>
            <div
                v-else
                class="budget-note"
                :class="{ danger: promotionBudget?.is_over_budget }"
            >
                {{ budgetStatusText }}
            </div>
            <div class="budget-actions">
                <button
                    class="btn secondary"
                    type="button"
                    @click="openHistory"
                >
                    {{
                        showHistoryPanel
                            ? "Ẩn lịch sử sử dụng voucher"
                            : "Xem lịch sử sử dụng voucher"
                    }}
                </button>
            </div>
        </section>

        <section v-if="showHistoryPanel" class="table-card history-panel">
            <div class="history-head">
                <div>
                    <span class="eyebrow">Lịch sử sử dụng voucher</span>
                    <h3>Voucher hệ thống đã trừ quỹ</h3>
                </div>
                <button
                    class="btn secondary"
                    type="button"
                    :disabled="budgetLoading"
                    @click="loadBudget"
                >
                    Tải lại
                </button>
            </div>
            <div v-if="budgetLoading" class="state">
                Đang tải lịch sử voucher...
            </div>
            <div v-else-if="voucherLedgers.length === 0" class="state">
                Chưa có lịch sử sử dụng voucher hệ thống.
            </div>
            <div v-else class="history-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Số tiền</th>
                            <th>Số dư sau trừ</th>
                            <th>Tham chiếu</th>
                            <th>Mô tả</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ledger in voucherLedgers" :key="ledger.id">
                            <td>{{ dateTime(ledger.transacted_at) }}</td>
                            <td>
                                <strong>{{ money(ledger.amount) }}</strong>
                            </td>
                            <td>{{ money(ledger.balance_after) }}</td>
                            <td>
                                <strong>{{
                                    ledger.transaction_ref || "-"
                                }}</strong>
                                <small>{{ ledgerReference(ledger) }}</small>
                            </td>
                            <td>{{ ledger.description || "-" }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <footer>
                <button
                    class="btn secondary"
                    type="button"
                    @click="closeHistory"
                >
                    Đóng
                </button>
            </footer>
        </section>

        <section class="table-card">
            <div v-if="loading" class="state">Đang tải voucher hệ thống...</div>
            <div v-else-if="vouchers.length === 0" class="state">
                Chưa có voucher hệ thống.
            </div>
            <table v-else>
                <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Tên</th>
                        <th>Loại giảm</th>
                        <th>Giá trị</th>
                        <th>Đơn tối thiểu</th>
                        <th>Số lượng</th>
                        <th>Đã dùng</th>
                        <th>Hiệu lực</th>
                        <th>Trạng thái</th>
                        <th class="actions-col">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="voucher in vouchers" :key="voucher.id">
                        <td>
                            <strong>{{ voucher.code }}</strong>
                        </td>
                        <td>{{ voucher.name }}</td>
                        <td>{{ voucher.type_label }}</td>
                        <td>{{ discountText(voucher) }}</td>
                        <td>{{ money(voucher.min_order_amount) }}</td>
                        <td>
                            {{ voucher.total_quantity || "Không giới hạn" }}
                        </td>
                        <td>{{ voucher.used_quantity }}</td>
                        <td>
                            {{ date(voucher.valid_from) }} -
                            {{ date(voucher.valid_to) }}
                        </td>
                        <td>
                            <span class="badge" :class="voucher.status">{{
                                voucher.status_label
                            }}</span>
                        </td>
                        <td class="actions-col">
                            <TableActionGroup>
                                <ActionIconButton
                                    icon="pencil"
                                    label="Sửa voucher"
                                    @click="openForm(voucher)"
                                />
                                <ActionIconButton
                                    icon="power"
                                    label="Tắt voucher"
                                    variant="danger"
                                    @click="turnOff(voucher)"
                                />
                            </TableActionGroup>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div v-if="showModal" class="modal-backdrop" @click.self="closeForm">
            <form class="modal" novalidate @submit.prevent="save">
                <h3>
                    {{
                        form.id
                            ? "Sửa voucher hệ thống"
                            : "Tạo voucher hệ thống"
                    }}
                </h3>
                <div class="grid">
                    <label
                        >Mã voucher<input v-model.trim="form.code" @input="clearFieldError('code')"
                    /><small v-if="validationErrors.code" class="field-error">{{ validationErrors.code }}</small></label>
                    <label
                        >Tên voucher<input v-model.trim="form.name" @input="clearFieldError('name')"
                    /><small v-if="validationErrors.name" class="field-error">{{ validationErrors.name }}</small></label>
                    <label
                        >Loại giảm
                        <select v-model="form.discount_type" @change="normalizeDiscountFields">
                            <option value="percent">Phần trăm</option>
                            <option value="fixed">Số tiền</option>
                        </select>
                    </label>
                    <label
                        >{{ discountValueLabel }}<span class="suffix-field"><input
                            v-model.number="form.discount_value"
                            type="number"
                            min="0.01"
                            :max="form.discount_type === 'percent' ? 100 : null"
                            :step="form.discount_type === 'percent' ? 0.01 : 1"
                            @input="clearFieldError('discount_value')"
                            @change="normalizeDiscountFields"
                    /><span>{{ discountValueUnit }}</span></span><small v-if="validationErrors.discount_value" class="field-error">{{ validationErrors.discount_value }}</small></label>
                    <label v-if="form.discount_type === 'percent'"
                        >Giảm tối đa bao nhiêu tiền<span class="suffix-field"><input
                            v-model.number="form.max_discount_amount"
                            type="number"
                            min="0"
                            step="1"
                            @input="clearFieldError('max_discount_amount')"
                            @change="normalizeDiscountFields"
                    /><span>VNĐ</span></span><small v-if="validationErrors.max_discount_amount" class="field-error">{{ validationErrors.max_discount_amount }}</small></label>
                    <label
                        >Đơn tối thiểu<span class="suffix-field"><input
                            v-model.number="form.min_order_amount"
                            type="number"
                            min="0"
                            step="1"
                            @input="clearFieldError('min_order_amount')"
                            @change="normalizeDiscountFields"
                    /><span>VNĐ</span></span><small v-if="validationErrors.min_order_amount" class="field-error">{{ validationErrors.min_order_amount }}</small></label>
                    <label
                        >Tổng số lượng<input
                            v-model.number="form.total_quantity"
                            type="number"
                            min="1"
                            @input="clearFieldError('total_quantity')"
                    /><small v-if="validationErrors.total_quantity" class="field-error">{{ validationErrors.total_quantity }}</small></label>
                    <label
                        >Giới hạn mỗi khách<input
                            v-model.number="form.per_user_limit"
                            type="number"
                            min="1"
                            @input="clearFieldError('per_user_limit')"
                    /><small v-if="validationErrors.per_user_limit" class="field-error">{{ validationErrors.per_user_limit }}</small></label>
                    <label
                        >Bắt đầu<input
                            v-model="form.valid_from"
                            type="datetime-local"
                            @input="clearFieldError('valid_from')"
                    /><small v-if="validationErrors.valid_from" class="field-error">{{ validationErrors.valid_from }}</small></label>
                    <label
                        >Kết thúc<input
                            v-model="form.valid_to"
                            type="datetime-local"
                            @input="clearFieldError('valid_to')"
                    /><small v-if="validationErrors.valid_to" class="field-error">{{ validationErrors.valid_to }}</small></label>
                    <label
                        >Trạng thái
                        <select v-model="form.status">
                            <option value="active">Đang áp dụng</option>
                            <option value="inactive">Đã tắt</option>
                        </select>
                    </label>
                </div>
                <div class="scope-editor">
                    <label>
                        Phạm vi
                        <select
                            v-model="form.scopes[0].scope_type"
                            @change="resetScopeId"
                        >
                            <option value="all">Toàn hệ thống</option>
                            <option value="venue_cluster">Cụm sân</option>
                            <option value="court_type">Loại sân</option>
                            <option value="booking_type">Hình thức booking</option>
                            <option value="membership_tier">Hạng thành viên sân</option>
                            <option value="vip_package">Gói VIP hệ thống</option>
                        </select>
                    </label>
                    <label v-if="form.scopes[0].scope_type === 'venue_cluster'">
                        Cụm sân áp dụng
                        <select
                            v-model="form.scopes[0].scope_id"
                            @change="clearFieldError('scope_id')"
                        >
                            <option
                                v-for="item in scopeOptions.venue_clusters"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </label>
                    <label v-else-if="form.scopes[0].scope_type === 'court_type'">
                        Loại sân áp dụng
                        <select
                            v-model="form.scopes[0].scope_id"
                            @change="clearFieldError('scope_id')"
                        >
                            <option
                                v-for="item in scopeOptions.court_types"
                                :key="item.id"
                                :value="String(item.id)"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </label>
                    <label v-else-if="form.scopes[0].scope_type === 'membership_tier'">
                        Hạng sân áp dụng
                        <select
                            v-model="form.scopes[0].scope_id"
                            @change="clearFieldError('scope_id')"
                        >
                            <option
                                v-for="item in scopeOptions.membership_tiers"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </label>
                    <label v-else-if="form.scopes[0].scope_type === 'vip_package'">
                        Gói VIP áp dụng
                        <select
                            v-model="form.scopes[0].scope_id"
                            @change="clearFieldError('scope_id')"
                        >
                            <option
                                v-for="item in scopeOptions.vip_packages"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </label>
                    <label v-else-if="form.scopes[0].scope_type === 'booking_type'">
                        Loại booking
                        <select
                            v-model="form.scopes[0].scope_id"
                            @change="clearFieldError('scope_id')"
                        >
                            <option
                                v-for="item in scopeOptions.booking_types"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </label>
                    <small v-if="validationErrors.scope_id" class="field-error">{{ validationErrors.scope_id }}</small>
                    <p class="scope-hint">{{ scopeHint }}</p>
                </div>
                <footer>
                    <button
                        class="btn secondary"
                        type="button"
                        @click="closeForm"
                    >
                        Hủy
                    </button>
                    <button
                        class="btn primary"
                        type="submit"
                        :disabled="saving"
                    >
                        {{ saving ? "Đang lưu..." : "Lưu" }}
                    </button>
                </footer>
            </form>
        </div>
        <!-- Floating Add Button -->
        <div
            class="floating-add-container"
            :class="{ 'has-scroll': showScrollTop }"
        >
            <button class="btn-float-add" @click="openForm()">
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Tạo voucher</span>
            </button>
        </div>
    </section>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import TableActionGroup from "../../components/TableActionGroup.vue";
import { adminVoucherService } from "../../services/adminVoucherService.js";
import { adminSystemWalletService } from "../../services/adminSystemWallet.js";

export default {
    name: "AdminVouchers",
    components: { ActionIconButton, AppIcon, TableActionGroup },
    data() {
        return {
            filters: { keyword: "", status: "", discount_type: "", per_page: 50 },
            vouchers: [],
            loading: false,
            saving: false,
            showModal: false,
            error: "",
            success: "",
            form: this.emptyForm(),
            validationErrors: {},
            scopeOptions: this.emptyScopeOptions(),
            showHistoryPanel: false,
            showScrollTop: false,
            budgetLoading: false,
            budgetSaving: false,
            budgetError: "",
            promotionExpenses: null,
            promotionBudget: null,
            voucherLedgers: [],
            budgetSettings: {
                is_alert_enabled: true,
                promotion_budget: 0,
                budget_period: "month",
            },
        };
    },
    mounted() {
        this.load();
        this.loadBudget();
        window.addEventListener("scroll", this.handleScroll);
    },
    beforeUnmount() {
        window.removeEventListener("scroll", this.handleScroll);
    },
    computed: {
        discountValueLabel() {
            return this.form.discount_type === "percent" ? "Giảm bao nhiêu %" : "Giảm bao nhiêu tiền";
        },
        discountValueUnit() {
            return this.form.discount_type === "percent" ? "%" : "VNĐ";
        },
        budgetUsageText() {
            if (
                this.promotionBudget?.usage_percent === null ||
                this.promotionBudget?.usage_percent === undefined
            ) {
                return this.budgetSettings.promotion_budget > 0
                    ? "0%"
                    : "Chưa đặt";
            }

            return `${this.promotionBudget.usage_percent}%`;
        },
        budgetStatusText() {
            if (!this.budgetSettings.is_alert_enabled) {
                return "Cảnh báo ngân sách đang tắt.";
            }

            if (!this.budgetSettings.promotion_budget) {
                return "Chưa đặt ngưỡng cảnh báo cho voucher hệ thống.";
            }

            if (this.promotionBudget?.is_over_budget) {
                return "Chi phí voucher hệ thống đã vượt ngân sách cảnh báo.";
            }

            return "Chi phí voucher hệ thống đang trong ngưỡng cảnh báo.";
        },
        scopeHint() {
            const type = this.form.scopes?.[0]?.scope_type || "all";
            return {
                all: "Voucher áp dụng cho toàn bộ booking hợp lệ.",
                venue_cluster: "Chỉ áp dụng cho booking thuộc cụm sân đã chọn.",
                court_type: "Chỉ áp dụng cho loại sân đã chọn.",
                booking_type: "Chỉ áp dụng theo hình thức đặt sân.",
                membership_tier: "Chỉ áp dụng cho khách đạt hạng thành viên sân.",
                vip_package: "Chỉ áp dụng cho khách đang có gói VIP hệ thống.",
            }[type];
        },
    },
    methods: {
        emptyForm() {
            return {
                id: null,
                code: "",
                name: "",
                description: null,
                discount_type: "percent",
                discount_value: 10,
                max_discount_amount: null,
                min_order_amount: 0,
                total_quantity: 100,
                per_user_limit: 1,
                valid_from: "",
                valid_to: "",
                status: "active",
                scopes: [{ scope_type: "all", scope_id: null }],
            };
        },
        emptyScopeOptions() {
            return {
                venue_clusters: [],
                court_types: [],
                membership_tiers: [
                    { id: "standard", name: "Thường" },
                    { id: "silver", name: "Bạc" },
                    { id: "gold", name: "Vàng" },
                    { id: "diamond", name: "Kim cương" },
                ],
                vip_packages: [
                    { id: "saving", name: "Tiết kiệm" },
                    { id: "pro", name: "Pro" },
                ],
                booking_types: [
                    { id: "single", name: "Đơn lẻ" },
                    { id: "recurring", name: "Lịch cố định" },
                ],
            };
        },
        async load() {
            this.loading = true;
            try {
                const response = await adminVoucherService.list(this.filters);
                this.vouchers = response.data || [];
                this.scopeOptions = {
                    ...this.emptyScopeOptions(),
                    ...(response.meta?.scope_options || {}),
                };
            } catch (error) {
                this.error = error.message || "Không thể tải voucher hệ thống.";
            } finally {
                this.loading = false;
            }
        },
        async loadBudget() {
            this.budgetLoading = true;
            this.budgetError = "";
            try {
                const response = await adminSystemWalletService.show({
                    period_type: this.budgetSettings.budget_period,
                    entry_kind: "voucher_subsidy",
                    per_page: 10,
                });
                this.promotionExpenses = response.promotion_expenses || null;
                this.promotionBudget = response.promotion_budget || null;
                this.voucherLedgers = response.ledgers?.data || [];
                this.budgetSettings = {
                    is_alert_enabled: Boolean(
                        response.wallet?.is_alert_enabled,
                    ),
                    promotion_budget: Number(
                        response.wallet?.promotion_monthly_budget || 0,
                    ),
                    budget_period:
                        response.wallet?.budget_period_type ||
                        response.promotion_budget?.budget_period ||
                        "month",
                };
            } catch (error) {
                this.budgetError =
                    error.message || "Không thể tải ngân sách khuyến mãi.";
            } finally {
                this.budgetLoading = false;
            }
        },
        async saveBudget() {
            this.budgetSaving = true;
            this.budgetError = "";
            this.success = "";
            try {
                const response = await adminSystemWalletService.updateSettings(
                    this.budgetSettings,
                );
                this.success =
                    response.message || "Đã lưu ngân sách khuyến mãi.";
                await this.loadBudget();
            } catch (error) {
                this.budgetError =
                    error.message || "Không thể lưu ngân sách khuyến mãi.";
            } finally {
                this.budgetSaving = false;
            }
        },
        openHistory() {
            this.showHistoryPanel = !this.showHistoryPanel;
        },
        closeHistory() {
            this.showHistoryPanel = false;
        },
        openForm(voucher = null) {
            this.form = voucher
                ? {
                      ...voucher,
                      description: null,
                      status: voucher.status === "draft" ? "active" : voucher.status,
                      valid_from: this.inputDate(voucher.valid_from),
                      valid_to: this.inputDate(voucher.valid_to),
                      scopes: this.normalizeScopes(voucher.scopes),
                  }
                : this.emptyForm();
            this.validationErrors = {};
            this.error = "";
            this.showModal = true;
        },
        closeForm() {
            this.showModal = false;
            this.validationErrors = {};
        },
        async save() {
            this.normalizeDiscountFields();
            if (!this.validateForm()) return;

            this.saving = true;
            try {
                const payload = {
                    ...this.form,
                    description: null,
                    scopes: this.normalizeScopes(this.form.scopes),
                };
                const response = this.form.id
                    ? await adminVoucherService.update(this.form.id, payload)
                    : await adminVoucherService.create(payload);
                this.success = response.message;
                this.closeForm();
                await this.load();
            } catch (error) {
                this.applyApiValidationErrors(error);
                this.error = error.message || "Không thể lưu voucher hệ thống.";
            } finally {
                this.saving = false;
            }
        },
        validateForm() {
            const errors = {};
            const discountValue = Number(this.form.discount_value);
            const maxDiscountAmount = Number(this.form.max_discount_amount || 0);
            const minOrderAmount = Number(this.form.min_order_amount || 0);
            const totalQuantity = Number(this.form.total_quantity);
            const perUserLimit = Number(this.form.per_user_limit);
            const scope = this.form.scopes?.[0] || { scope_type: "all", scope_id: null };

            if (!this.form.code) errors.code = "Vui lòng nhập mã voucher.";
            if (!this.form.name) errors.name = "Vui lòng nhập tên voucher.";
            if (!discountValue || discountValue <= 0) {
                errors.discount_value =
                    this.form.discount_type === "percent"
                        ? "Vui lòng nhập phần trăm giảm lớn hơn 0."
                        : "Vui lòng nhập số tiền giảm lớn hơn 0.";
            } else if (this.form.discount_type === "percent" && discountValue > 100) {
                errors.discount_value = "Phần trăm giảm không được vượt quá 100%.";
            }

            if (this.form.discount_type === "percent" && maxDiscountAmount < 0) {
                errors.max_discount_amount = "Số tiền giảm tối đa không được âm.";
            }

            if (minOrderAmount < 0) errors.min_order_amount = "Đơn tối thiểu không được âm.";
            if (!Number.isInteger(totalQuantity) || totalQuantity < 1) {
                errors.total_quantity = "Tổng số lượng phải từ 1 trở lên.";
            }
            if (!Number.isInteger(perUserLimit) || perUserLimit < 1) {
                errors.per_user_limit = "Giới hạn mỗi khách phải từ 1 trở lên.";
            }
            if (
                Number.isInteger(totalQuantity)
                && Number.isInteger(perUserLimit)
                && perUserLimit > totalQuantity
            ) {
                errors.per_user_limit = "Giới hạn mỗi khách không được lớn hơn tổng số lượng.";
            }
            if (!this.form.valid_from) errors.valid_from = "Vui lòng chọn thời gian bắt đầu.";
            if (!this.form.valid_to) errors.valid_to = "Vui lòng chọn thời gian kết thúc.";
            if (
                this.form.valid_from
                && this.form.valid_to
                && new Date(this.form.valid_to) <= new Date(this.form.valid_from)
            ) {
                errors.valid_to = "Thời gian kết thúc phải sau thời gian bắt đầu.";
            }
            if (scope.scope_type !== "all" && !scope.scope_id) {
                errors.scope_id = "Vui lòng chọn phạm vi áp dụng.";
            }

            this.validationErrors = errors;
            return Object.keys(errors).length === 0;
        },
        clearFieldError(field) {
            if (!this.validationErrors[field]) return;
            const { [field]: removed, ...rest } = this.validationErrors;
            this.validationErrors = rest;
        },
        applyApiValidationErrors(error) {
            const apiErrors = error?.data?.errors || {};
            const mapped = {};

            Object.entries(apiErrors).forEach(([field, messages]) => {
                mapped[field] = Array.isArray(messages) ? messages[0] : String(messages);
            });

            this.validationErrors = {
                ...this.validationErrors,
                ...mapped,
            };
        },
        async turnOff(voucher) {
            if (!confirm(`Tắt voucher hệ thống ${voucher.code}?`)) return;
            const response = await adminVoucherService.deactivate(
                voucher.id,
                "Admin tắt voucher hệ thống.",
            );
            this.success = response.message;
            await this.load();
        },
        discountText(voucher) {
            if (voucher.discount_label) return voucher.discount_label;
            return voucher.discount_type === "percent"
                ? `${this.formatPercent(voucher.discount_value)}%`
                : this.money(voucher.discount_value);
        },
        normalizeDiscountFields() {
            if (this.form.discount_type === "percent") {
                const percent = Number(this.form.discount_value || 0);
                this.form.discount_value = Math.min(
                    Math.max(Number(percent.toFixed(2)), 0.01),
                    100,
                );
                this.form.max_discount_amount =
                    this.form.max_discount_amount === null ||
                    this.form.max_discount_amount === ""
                        ? null
                        : this.toVndInteger(this.form.max_discount_amount);
            } else {
                this.form.discount_value = Math.max(
                    this.toVndInteger(this.form.discount_value),
                    1,
                );
                this.form.max_discount_amount = null;
            }

            this.form.min_order_amount = this.toVndInteger(
                this.form.min_order_amount,
            );
        },
        toVndInteger(value) {
            return Math.max(Math.round(Number(value || 0)), 0);
        },
        formatPercent(value) {
            return new Intl.NumberFormat("vi-VN", {
                maximumFractionDigits: 2,
            }).format(Number(value || 0));
        },
        normalizeScopes(scopes) {
            const first = Array.isArray(scopes) && scopes.length
                ? scopes[0]
                : { scope_type: "all", scope_id: null };
            const scopeType = first.scope_type || "all";

            return [
                {
                    scope_type: scopeType,
                    scope_id: scopeType === "all" ? null : first.scope_id || null,
                },
            ];
        },
        resetScopeId() {
            const scope = this.form.scopes[0];
            scope.scope_id = scope.scope_type === "all" ? null : "";
            this.clearFieldError("scope_id");
        },
        money(value) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(value || 0);
        },
        date(value) {
            return value ? new Date(value).toLocaleDateString("vi-VN") : "-";
        },
        dateTime(value) {
            if (!value) return "-";
            return new Intl.DateTimeFormat("vi-VN", {
                hour: "2-digit",
                minute: "2-digit",
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            }).format(new Date(value));
        },
        ledgerReference(ledger) {
            const type = ledger.reference_type
                ? String(ledger.reference_type).toUpperCase()
                : "-";
            return `${type} · ${ledger.reference_id || "-"}`;
        },
        inputDate(value) {
            if (!value) return "";
            const date = new Date(value);
            return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
                .toISOString()
                .slice(0, 16);
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
        },
    },
};
</script>

<style scoped>
.page {
    display: grid;
    gap: 16px;
}
.filters {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
}
.filters {
    justify-content: flex-start;
    align-items: center;
}
.filters input,
.filters select {
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    padding: 10px;
    font: inherit;
}
.notice {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    border-radius: 10px;
    padding: 12px;
    font-weight: 800;
}
.table-card,
.modal {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.table-card {
    overflow: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1040px;
}
th,
td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
.state {
    padding: 24px;
    color: #64748b;
}
.btn,
.mini-btn {
    border: 0;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
}
.btn {
    padding: 10px 14px;
}
.mini-btn {
    padding: 7px 10px;
    margin-right: 6px;
    background: #f1f5f9;
}
.primary {
    background: #16a34a;
    color: #fff;
}
.secondary {
    background: #f1f5f9;
    color: #0f172a;
}
.danger {
    background: #fee2e2;
    color: #b91c1c;
}
.badge {
    border-radius: 999px;
    padding: 5px 9px;
    font-size: 12px;
    font-weight: 800;
    background: #e2e8f0;
}
.badge.active {
    background: #dcfce7;
    color: #166534;
}
.badge.inactive,
.badge.expired {
    background: #fee2e2;
    color: #b91c1c;
}
.badge.draft {
    background: #f1f5f9;
    color: #475569;
}
.alert {
    padding: 12px;
    border-radius: 10px;
    font-weight: 700;
}
.error {
    background: #fee2e2;
    color: #b91c1c;
}
.success {
    background: #dcfce7;
    color: #166534;
}
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.56);
    display: grid;
    place-items: center;
    z-index: 500;
    padding: 20px;
}
.modal {
    width: min(760px, calc(100vw - 32px));
    padding: 22px;
    display: grid;
    gap: 16px;
}
.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
label {
    display: grid;
    gap: 6px;
    font-weight: 800;
}
input,
select,
textarea {
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    padding: 10px;
    font: inherit;
}
.suffix-field {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}
.suffix-field input {
    min-width: 0;
    border: 0;
    border-radius: 0;
}
.suffix-field input:disabled {
    background: #f8fafc;
    color: #94a3b8;
}
.suffix-field span {
    padding: 0 12px;
    color: #475569;
    font-weight: 900;
    white-space: nowrap;
}
.modal .field-error {
    display: block;
    width: 100%;
    border-left: 3px solid #dc2626;
    border-radius: 6px;
    background: #fef2f2;
    color: #b91c1c !important;
    font-size: 12px;
    font-weight: 900;
    line-height: 1.35;
    padding: 6px 8px;
}
.scope-editor {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    border: 1px solid #dbe3ef;
    border-radius: 10px;
    background: #f8fafc;
    padding: 14px;
}
.scope-hint {
    grid-column: 1 / -1;
    margin: 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
}
footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
@media (max-width: 720px) {
    .grid,
    .scope-editor,
    .filters {
        grid-template-columns: 1fr;
        flex-direction: column;
        align-items: stretch;
    }
}

.budget-card {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) minmax(340px, 1.4fr);
    gap: 16px;
    border: 1px solid #cfe2d2;
    border-radius: 12px;
    background: #fff;
    padding: 18px;
    box-shadow: 0 12px 28px rgba(18, 68, 35, 0.06);
}

.budget-copy {
    display: grid;
    gap: 6px;
    align-content: start;
}

.budget-copy h3,
.budget-copy p {
    margin: 0;
}

.budget-copy p,
.budget-note {
    color: #64756b;
}

.eyebrow {
    color: #15803d;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.budget-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.budget-metric {
    display: grid;
    gap: 8px;
    border: 1px solid #d7e7da;
    border-radius: 10px;
    background: #f8fcf8;
    padding: 12px;
}

.budget-metric span,
.budget-field span,
.budget-toggle {
    color: #64756b;
    font-size: 13px;
    font-weight: 800;
}

.budget-metric strong {
    color: #102018;
    font-size: 18px;
}

.budget-metric.warning {
    border-color: #fcd34d;
    background: #fffbeb;
}

.budget-form {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: auto minmax(180px, 1fr) minmax(160px, 0.8fr) auto;
    gap: 12px;
    align-items: end;
}

.budget-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    align-self: end;
    border: 1px solid #d7e7da;
    border-radius: 999px;
    background: #f8fcf8;
    padding: 8px 12px 8px 8px;
    color: #102018;
    cursor: pointer;
}

.budget-toggle input {
    display: grid;
    width: 36px;
    height: 20px;
    margin: 0;
    padding: 0;
    appearance: none;
    border: 0;
    border-radius: 999px;
    background: #cbd5e1;
    cursor: pointer;
    transition: background-color 180ms ease;
}

.budget-toggle input::before {
    content: "";
    width: 16px;
    height: 16px;
    margin: 2px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
    transition: transform 180ms ease;
}

.budget-toggle input:checked {
    background: #16a34a;
}

.budget-toggle input:checked::before {
    transform: translateX(16px);
}

.budget-field {
    display: grid;
    gap: 6px;
}

.budget-note {
    grid-column: 1 / -1;
    border-radius: 8px;
    background: #f6faf6;
    padding: 10px 12px;
    font-weight: 700;
}

.budget-note.danger {
    background: #fff1f2;
    color: #b91c1c;
}

.budget-actions {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
}

.history-panel {
    display: grid;
    gap: 0;
    border-color: #cfe2d2;
    box-shadow: 0 12px 28px rgba(18, 68, 35, 0.06);
}

.history-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
}

.history-head h3 {
    margin: 4px 0 0;
    color: #102018;
    font-size: 18px;
}

.history-table-wrap {
    overflow: auto;
    max-height: 420px;
}

.history-table-wrap table {
    min-width: 820px;
}

.history-panel footer {
    padding: 14px 16px;
    border-top: 1px solid #e2e8f0;
}

.history-panel small {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-weight: 700;
}

@media (max-width: 980px) {
    .budget-card,
    .budget-form {
        grid-template-columns: 1fr;
    }

    .budget-metrics {
        grid-template-columns: 1fr;
    }
}
</style>

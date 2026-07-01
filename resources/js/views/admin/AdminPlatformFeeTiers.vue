<template>
    <section class="pf-page">
        <PlatformFeeSubnav />

        <!-- Action bar with secondary actions -->
        <div
            class="action-bar-layout"
            style="
                margin-bottom: 12px;
                display: flex;
                justify-content: flex-end;
                gap: 12px;
            "
        >
            <button
                class="btn secondary icon-text"
                type="button"
                @click="openDiscountSettings"
            >
                <AppIcon name="settings" size="18" />
                <span>Giảm kỳ 12 tháng</span>
            </button>
            <button
                class="btn secondary icon-text"
                type="button"
                @click="checkCoverage"
            >
                <AppIcon name="check" size="18" />
                <span>Kiểm tra khoảng bậc phí</span>
            </button>
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
                title="Thêm bậc phí"
            >
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Thêm bậc phí</span>
            </button>
        </div>

        <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>

        <section class="panel filter-panel">
            <input v-model.trim="keyword" placeholder="Tìm theo tên bậc phí" />
            <select v-model="statusFilter">
                <option value="">Tất cả trạng thái</option>
                <option value="active">Đang dùng</option>
                <option value="inactive">Ngưng dùng</option>
            </select>
            <button
                class="btn secondary icon-text"
                type="button"
                @click="reloadFromDb"
            >
                <AppIcon name="refresh" size="18" />
            </button>
        </section>

        <section class="panel">
            <div class="panel-title">
                <strong>Danh sách bậc phí</strong>
                <span>{{ filteredTiers.length }} bậc phí</span>
            </div>
            <div v-if="filteredTiers.length === 0" class="empty">
                Chưa có bậc phí. Hãy tạo bậc phí đầu tiên.
            </div>
            <div v-else class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tên bậc</th>
                            <th>Khoảng số sân</th>
                            <th>Giá / sân / tháng</th>
                            <th>Giảm 12 tháng</th>
                            <th>Trạng thái</th>
                            <th
                                title="Số kỳ phí đã được tạo và tham chiếu bậc phí này"
                            >
                                Kỳ phí tham chiếu
                            </th>
                            <th>Cập nhật</th>
                            <th class="actions-header">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tier in filteredTiers" :key="tier.id">
                            <td>
                                <strong>{{ tier.name }}</strong>
                                <small>{{
                                    tier.note || "Không có ghi chú"
                                }}</small>
                            </td>
                            <td>{{ rangeLabel(tier) }}</td>
                            <td>{{ money(tier.price_per_court_month) }}</td>
                            <td>{{ percent(tier.discount_12_months) }}</td>
                            <td>
                                <span
                                    class="status-dot"
                                    :class="{ inactive: !tier.is_active }"
                                    :title="
                                        tier.is_active
                                            ? 'Đang dùng'
                                            : 'Ngưng dùng'
                                    "
                                    :aria-label="
                                        tier.is_active
                                            ? 'Đang dùng'
                                            : 'Ngưng dùng'
                                    "
                                ></span>
                            </td>
                            <td>{{ usageCount(tier.id) }}</td>
                            <td>{{ date(tier.updated_at) }}</td>
                            <td>
                                <div class="actions">
                                    <button
                                        class="icon-btn"
                                        type="button"
                                        title="Xem chi tiết"
                                        aria-label="Xem chi tiết"
                                        @click="viewTier(tier)"
                                    >
                                        <AppIcon name="eye" size="18" />
                                    </button>
                                    <button
                                        class="icon-btn"
                                        type="button"
                                        title="Sửa bậc phí"
                                        aria-label="Sửa bậc phí"
                                        @click="openEdit(tier)"
                                    >
                                        <AppIcon name="pencil" size="18" />
                                    </button>
                                    <button
                                        class="icon-btn"
                                        :class="{ danger: tier.is_active }"
                                        type="button"
                                        :title="
                                            tier.is_active
                                                ? 'Ngừng dùng bậc phí'
                                                : 'Bật lại bậc phí'
                                        "
                                        :aria-label="
                                            tier.is_active
                                                ? 'Ngừng dùng bậc phí'
                                                : 'Bật lại bậc phí'
                                        "
                                        @click="toggleTier(tier)"
                                    >
                                        <AppIcon
                                            :name="
                                                tier.is_active
                                                    ? 'power'
                                                    : 'refresh'
                                            "
                                            size="18"
                                        />
                                    </button>
                                    <button
                                        class="icon-btn danger"
                                        type="button"
                                        :title="usageCount(tier.id) > 0 ? 'Ngừng dùng bậc phí' : 'Xóa bậc phí'"
                                        :aria-label="usageCount(tier.id) > 0 ? 'Ngừng dùng bậc phí' : 'Xóa bậc phí'"
                                        @click="openRemoveTier(tier)"
                                    >
                                        <AppIcon name="trash" size="18" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="showModal" class="modal-backdrop" @click.self="requestCloseModal">
            <form class="modal" @submit.prevent="saveTier">
                <header class="modal-head">
                    <h3>
                        {{
                            editingId
                                ? "Sửa bậc phí nền tảng"
                                : "Thêm bậc phí nền tảng"
                        }}
                    </h3>
                    <button
                        class="icon-close"
                        type="button"
                        title="Đóng"
                        aria-label="Đóng"
                        @click="requestCloseModal"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>

                <div class="form-grid">
                    <label>
                        Tên bậc phí *
                        <input
                            :value="form.name"
                            @input="handleTierNameInput"
                        />
                        <small v-if="autoSyncTierName">
                            Tự động cập nhật theo khoảng số sân.
                        </small>
                        <small v-if="fieldError('name')" class="field-error">{{
                            fieldError("name")
                        }}</small>
                    </label>
                    <label>
                        Giá / sân / tháng *
                        <input
                            v-model.number="form.price_per_court_month"
                            type="number"
                            min="1"
                            max="9999999999"
                            step="1"
                        />
                        <div
                            v-if="fieldError('price_per_court_month')"
                            class="validation-message"
                            role="alert"
                        >
                            <AppIcon name="alert" size="15" />
                            <span>{{ fieldError("price_per_court_month") }}</span>
                        </div>
                    </label>
                    <label>
                        Số sân tối thiểu *
                        <input
                            v-model.number="form.min_courts"
                            type="number"
                            min="1"
                            step="1"
                        />
                        <small
                            v-if="fieldError('min_courts')"
                            class="field-error"
                            >{{ fieldError("min_courts") }}</small
                        >
                    </label>
                    <label>
                        Số sân tối đa
                        <input
                            :value="automaticMaxLabel"
                            type="text"
                            disabled
                        />
                        <small>Tự động cân theo bậc kế tiếp.</small>
                    </label>
                    <label class="full">
                        Giảm kỳ 12 tháng (%)
                        <input
                            v-model.number="form.discount_12_months"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                        />
                        <small
                            v-if="fieldError('discount_12_months')"
                            class="field-error"
                            >{{ fieldError("discount_12_months") }}</small
                        >
                        <small v-else>
                            DB hiện chỉ lưu mức giảm khi đóng 12 tháng; các kỳ
                            1/3/6/9 tháng không áp dụng giảm.
                        </small>
                    </label>
                    <label class="check-row">
                        <input v-model="form.is_active" type="checkbox" />
                        <span>Đang dùng</span>
                    </label>
                    <label class="full">
                        Ghi chú nội bộ
                        <textarea v-model.trim="form.note" rows="3"></textarea>
                    </label>
                </div>

                <div v-if="formErrors._coverage" class="alert error">
                    <div v-for="message in formErrors._coverage" :key="message">
                        {{ message }}
                    </div>
                </div>

                <footer class="modal-actions">
                    <button
                        class="btn secondary"
                        type="button"
                        @click="requestCloseModal"
                    >
                        Hủy
                    </button>
                    <button class="btn primary icon-text" type="submit">
                        <AppIcon name="check" size="18" />
                        <span>Lưu bậc phí</span>
                    </button>
                </footer>
            </form>
        </div>

        <div
            v-if="showDiscountModal"
            class="modal-backdrop"
            @click.self="closeDiscountSettings"
        >
            <div class="modal discount-modal">
                <header class="modal-head">
                    <div>
                        <h3>Cấu hình giảm giá theo kỳ</h3>
                        <p>
                            Mức giảm phải tăng dần: 1 tháng &lt; 3 tháng &lt; 6
                            tháng &lt; 9 tháng &lt; 12 tháng.
                        </p>
                    </div>
                    <button
                        class="icon-close"
                        type="button"
                        title="Đóng"
                        aria-label="Đóng"
                        @click="closeDiscountSettings"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>

                <form
                    class="discount-form"
                    @submit.prevent="saveDiscountProfile"
                >
                    <div
                        v-if="discountFieldError('_form')"
                        class="alert error full"
                    >
                        {{ discountFieldError("_form") }}
                    </div>
                    <label class="full">
                        Tên mẫu giảm giá *
                        <input
                            v-model.trim="discountForm.name"
                            placeholder="Ví dụ: Ưu đãi tiêu chuẩn"
                        />
                        <small
                            v-if="discountFieldError('name')"
                            class="field-error"
                            >{{ discountFieldError("name") }}</small
                        >
                    </label>
                    <label v-for="field in discountFields" :key="field.key">
                        {{ field.label }}
                        <input
                            v-model.number="discountForm[field.key]"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                        />
                        <small
                            v-if="discountFieldError(field.key)"
                            class="field-error"
                            >{{ discountFieldError(field.key) }}</small
                        >
                    </label>
                    <div class="full discount-form-actions">
                        <button
                            v-if="editingDiscountId"
                            class="btn secondary"
                            type="button"
                            @click="resetDiscountForm"
                        >
                            Hủy chỉnh sửa
                        </button>
                        <button class="btn primary icon-text" type="submit">
                            <AppIcon name="check" size="18" />
                            <span>{{
                                editingDiscountId
                                    ? "Lưu mẫu giảm giá"
                                    : "Thêm mẫu giảm giá"
                            }}</span>
                        </button>
                    </div>
                </form>

                <div class="discount-list">
                    <div class="panel-title">
                        <strong>Danh sách mẫu giảm giá</strong>
                        <span>{{ discountProfiles.length }} mẫu</span>
                    </div>
                    <div
                        v-for="profile in discountProfiles"
                        :key="profile.id"
                        class="discount-profile-row"
                    >
                        <div>
                            <strong>{{ profile.name }}</strong>
                            <small>{{ discountProfileLabel(profile) }}</small>
                        </div>
                        <div class="actions">
                            <button
                                class="icon-btn"
                                type="button"
                                title="Sửa mẫu giảm giá"
                                aria-label="Sửa mẫu giảm giá"
                                @click="editDiscountProfile(profile)"
                            >
                                <AppIcon name="pencil" size="18" />
                            </button>
                            <button
                                class="icon-btn danger"
                                type="button"
                                title="Xóa mẫu giảm giá"
                                aria-label="Xóa mẫu giảm giá"
                                @click="requestRemoveDiscountProfile(profile)"
                            >
                                <AppIcon name="trash" size="18" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="viewingTier"
            class="modal-backdrop"
            @click.self="viewingTier = null"
        >
            <div class="modal detail-modal">
                <header class="modal-head">
                    <h3>Chi tiết bậc phí</h3>
                    <button
                        class="icon-close"
                        type="button"
                        title="Đóng"
                        aria-label="Đóng"
                        @click="viewingTier = null"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>
                <div class="detail-grid">
                    <div>
                        <span>Tên bậc</span
                        ><strong>{{ viewingTier.name }}</strong>
                    </div>
                    <div>
                        <span>Khoảng sân</span
                        ><strong>{{ rangeLabel(viewingTier) }}</strong>
                    </div>
                    <div>
                        <span>Giá</span
                        ><strong>{{
                            money(viewingTier.price_per_court_month)
                        }}</strong>
                    </div>
                    <div>
                        <span>Kỳ phí tham chiếu</span
                        ><strong>{{ usageCount(viewingTier.id) }}</strong>
                    </div>
                    <div class="full">
                        <span>Ghi chú</span
                        ><strong>{{ viewingTier.note || "-" }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="removingTier"
            class="modal-backdrop"
            @click.self="closeRemoveTier"
        >
            <div
                class="modal confirm-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="remove-tier-title"
            >
                <header class="modal-head">
                    <h3 id="remove-tier-title">
                        {{ removingTierHasUsage ? "Ngừng dùng bậc phí" : "Xóa bậc phí" }}
                    </h3>
                    <button
                        class="icon-close"
                        type="button"
                        title="Đóng"
                        aria-label="Đóng"
                        :disabled="removingTierBusy"
                        @click="closeRemoveTier"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>

                <div class="confirm-content">
                    <strong>{{ removingTier.name }}</strong>
                    <p v-if="removingTierHasUsage">
                        Bậc phí này đã được {{ usageCount(removingTier.id) }} kỳ phí
                        tham chiếu nên sẽ chỉ được ngừng dùng. Lịch sử kỳ phí vẫn được giữ nguyên.
                    </p>
                    <p v-else>
                        Bậc phí chưa được kỳ phí nào tham chiếu và sẽ bị xóa khỏi cấu hình.
                    </p>
                </div>

                <footer class="modal-actions">
                    <button
                        class="btn secondary"
                        type="button"
                        :disabled="removingTierBusy"
                        @click="closeRemoveTier"
                    >
                        Hủy
                    </button>
                    <button
                        class="btn danger"
                        type="button"
                        :disabled="removingTierBusy"
                        @click="confirmRemoveTier"
                    >
                        <AppIcon :name="removingTierHasUsage ? 'power' : 'trash'" size="18" />
                        <span>
                            {{ removingTierBusy ? "Đang xử lý..." : removingTierHasUsage ? "Ngừng dùng" : "Xóa bậc phí" }}
                        </span>
                    </button>
                </footer>
            </div>
        </div>

        <div
            v-if="confirmationDialog"
            class="modal-backdrop confirmation-backdrop"
            @click.self="closeConfirmationDialog"
        >
            <div
                class="modal confirm-modal"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="confirmation-dialog-title"
                aria-describedby="confirmation-dialog-message"
            >
                <header class="modal-head">
                    <h3 id="confirmation-dialog-title">
                        {{ confirmationDialog.title }}
                    </h3>
                    <button
                        class="icon-close"
                        type="button"
                        title="Đóng"
                        aria-label="Đóng"
                        :disabled="confirmationBusy"
                        @click="closeConfirmationDialog"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>

                <div class="confirm-content">
                    <p id="confirmation-dialog-message">
                        {{ confirmationDialog.message }}
                    </p>
                </div>

                <footer class="modal-actions">
                    <button
                        class="btn secondary"
                        type="button"
                        :disabled="confirmationBusy"
                        @click="closeConfirmationDialog"
                    >
                        Quay lại
                    </button>
                    <button
                        class="btn danger"
                        type="button"
                        :disabled="confirmationBusy"
                        @click="confirmDialogAction"
                    >
                        <AppIcon name="check" size="18" />
                        <span>{{ confirmationBusy ? "Đang xử lý..." : confirmationDialog.confirmLabel }}</span>
                    </button>
                </footer>
            </div>
        </div>
    </section>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import PlatformFeeSubnav from "../../components/PlatformFeeSubnav.vue";
import { adminVenueClusterService } from "../../services/adminVenueClusterService.js";
import {
    calculatePlatformFee,
    createDiscountProfile,
    createTier,
    deactivateTier,
    deleteDiscountProfile,
    deleteTier,
    findTierForCourtCount,
    getDiscountProfiles,
    getTierUsageCount,
    getTiers,
    reactivateTier,
    updateDiscountProfile,
    updateTier,
    validateTierCoverage,
} from "../../services/platformFeeTier.service.js";

const emptyDiscountForm = () => ({
    name: "",
    discount_1_month: 0,
    discount_3_months: 5,
    discount_6_months: 10,
    discount_9_months: 12,
    discount_12_months: 15,
});

const defaultForm = (profile = null, minCourts = 1) => ({
    name: "",
    min_courts: minCourts,
    max_courts: "",
    price_per_court_month: 50000,
    discount_profile_id: profile?.id || "db-annual",
    discount_1_month: profile?.discount_1_month ?? 0,
    discount_3_months: profile?.discount_3_months ?? 0,
    discount_6_months: profile?.discount_6_months ?? 0,
    discount_9_months: profile?.discount_9_months ?? 0,
    discount_12_months: profile?.discount_12_months ?? 0,
    annual_discount_percent: profile?.discount_12_months ?? 0,
    is_active: true,
    note: "",
});

const rangeTierNamePattern = /^(?:\d+\s*[-–]\s*\d+\s*sân|từ\s+\d+\s+sân\s+trở\s+lên)$/iu;

const usesRangeAsTierName = (name) =>
    rangeTierNamePattern.test(String(name || "").trim());

const rangeTierName = (minCourts, maxCourts) =>
    maxCourts === null
        ? `Từ ${minCourts} sân trở lên`
        : `${minCourts}-${maxCourts} sân`;

export default {
    name: "AdminPlatformFeeTiers",
    components: { AppIcon, PlatformFeeSubnav },
    data() {
        return {
            tiers: [],
            discountProfiles: [],
            venues: [],
            keyword: "",
            statusFilter: "",
            showModal: false,
            editingId: null,
            viewingTier: null,
            removingTier: null,
            removingTierBusy: false,
            confirmationDialog: null,
            confirmationBusy: false,
            form: defaultForm(),
            autoSyncTierName: true,
            initialFormSnapshot: "",
            formErrors: {},
            showDiscountModal: false,
            editingDiscountId: null,
            discountForm: emptyDiscountForm(),
            discountErrors: {},
            preview: { venue_cluster_id: "", court_count: 3, period_months: 3 },
            previewResult: null,
            previewError: "",
            previewWarnings: [],
            toast: "",
            toastType: "success",
            periods: [1, 3, 6, 9, 12],
            discountFields: [
                { key: "discount_1_month", label: "Giảm kỳ 1 tháng (%)" },
                { key: "discount_3_months", label: "Giảm kỳ 3 tháng (%)" },
                { key: "discount_6_months", label: "Giảm kỳ 6 tháng (%)" },
                { key: "discount_9_months", label: "Giảm kỳ 9 tháng (%)" },
                { key: "discount_12_months", label: "Giảm kỳ 12 tháng (%)" },
            ],
            showScrollTop: false,
        };
    },
    computed: {
        filteredTiers() {
            return this.tiers.filter((tier) => {
                const matchKeyword =
                    !this.keyword ||
                    tier.name
                        .toLowerCase()
                        .includes(this.keyword.toLowerCase());
                const matchStatus =
                    !this.statusFilter ||
                    (this.statusFilter === "active"
                        ? tier.is_active
                        : !tier.is_active);
                return matchKeyword && matchStatus;
            });
        },
        automaticMaxLabel() {
            if (!this.form.is_active) return "Không áp dụng khi đang tắt";
            return this.automaticMaxCourts === null
                ? `Từ ${Number(this.form.min_courts)} sân trở lên`
                : `${Number(this.form.min_courts)} - ${this.automaticMaxCourts} sân`;
        },
        automaticMaxCourts() {
            const nextTier = this.tiers
                .filter(
                    (tier) =>
                        tier.is_active &&
                        tier.id !== this.editingId &&
                        tier.min_courts > Number(this.form.min_courts),
                )
                .sort((left, right) => left.min_courts - right.min_courts)[0];
            return nextTier ? nextTier.min_courts - 1 : null;
        },
        removingTierHasUsage() {
            return this.removingTier
                ? this.usageCount(this.removingTier.id) > 0
                : false;
        },
        selectedDiscountSummary() {
            const profile = this.discountProfiles.find(
                (item) => item.id === this.form.discount_profile_id,
            );
            return profile
                ? this.discountProfileLabel(profile)
                : "Chọn mẫu để tự động áp dụng giảm giá theo từng kỳ.";
        },
    },
    watch: {
        "form.min_courts"() {
            this.syncAutomaticTierName();
        },
        automaticMaxCourts() {
            this.syncAutomaticTierName();
        },
    },
    mounted() {
        this.loadDiscountProfiles();
        this.loadVenues();
        this.loadTiers();
        window.addEventListener("scroll", this.handleScroll);
    },
    beforeUnmount() {
        window.removeEventListener("scroll", this.handleScroll);
    },
    methods: {
        async loadTiers() {
            this.tiers = await getTiers();
            this.runPreview();
        },
        async loadDiscountProfiles() {
            this.discountProfiles = await getDiscountProfiles();
        },
        async loadVenues() {
            const response = await adminVenueClusterService.list();
            const clusters = Array.isArray(response)
                ? response
                : response.data || [];
            this.venues = clusters.map((cluster) => ({
                id: cluster.id,
                name: cluster.name,
                court_count: cluster.court_count || 0,
            }));
        },
        suggestedMinimum() {
            const activeTiers = this.tiers
                .filter((tier) => tier.is_active)
                .sort((left, right) => left.min_courts - right.min_courts);
            const lastTier = activeTiers.at(-1);
            return lastTier ? lastTier.min_courts + 2 : 1;
        },
        openCreate() {
            this.editingId = null;
            const profile = this.discountProfiles[0] || null;
            this.autoSyncTierName = true;
            this.form = defaultForm(profile, this.suggestedMinimum());
            this.syncAutomaticTierName();
            this.formErrors = {};
            this.initialFormSnapshot = JSON.stringify(this.form);
            this.showModal = true;
        },
        openEdit(tier) {
            this.editingId = tier.id;
            this.autoSyncTierName = usesRangeAsTierName(tier.name);
            const matchedProfile =
                this.discountProfiles.find(
                    (profile) => profile.id === tier.discount_profile_id,
                ) ||
                this.discountProfiles.find((profile) =>
                    this.discountFields.every(
                        (field) =>
                            Number(profile[field.key]) ===
                            Number(tier[field.key]),
                    ),
                );
            this.form = {
                ...tier,
                discount_profile_id: matchedProfile?.id || "",
                max_courts: tier.max_courts ?? "",
            };
            this.syncAutomaticTierName();
            this.formErrors = {};
            this.initialFormSnapshot = JSON.stringify(this.form);
            this.showModal = true;
        },
        requestCloseModal() {
            const hasChanges =
                JSON.stringify(this.form) !== this.initialFormSnapshot;
            if (hasChanges) {
                this.confirmationDialog = {
                    type: "discard-tier-form",
                    title: "Hủy thay đổi?",
                    message:
                        "Các thay đổi chưa lưu trong cấu hình bậc phí sẽ bị bỏ.",
                    confirmLabel: "Hủy thay đổi",
                };
                return;
            }
            this.closeModal();
        },
        closeModal() {
            this.showModal = false;
            this.formErrors = {};
            this.initialFormSnapshot = "";
        },
        handleTierNameInput(event) {
            this.form.name = event.target.value;
            this.autoSyncTierName = false;
        },
        syncAutomaticTierName() {
            const minCourts = Number(this.form.min_courts);
            if (
                !this.autoSyncTierName ||
                !this.form.is_active ||
                !Number.isInteger(minCourts) ||
                minCourts < 1
            ) {
                return;
            }

            this.form.name = rangeTierName(
                minCourts,
                this.automaticMaxCourts,
            );
        },
        async saveTier() {
            this.form.annual_discount_percent = this.form.discount_12_months;
            try {
                if (this.editingId)
                    await updateTier(this.editingId, this.form, this.tiers);
                else await createTier(this.form, this.tiers);
                this.showMessage("Đã lưu bậc phí.");
                this.closeModal();
                await this.loadTiers();
            } catch (error) {
                this.formErrors = error.validation?.errors ||
                    error.data?.errors || {
                        _coverage: [error.message],
                    };
                this.showMessage(error.message, "error");
            }
        },
        async toggleTier(tier) {
            try {
                if (tier.is_active)
                    await deactivateTier(tier.id, "Admin tắt trạng thái");
                else await reactivateTier(tier.id);
                this.showMessage("Đã cập nhật trạng thái bậc phí.");
                await this.loadTiers();
            } catch (error) {
                this.showMessage(error.message, "error");
            }
        },
        applySelectedDiscountProfile() {
            const profile = this.discountProfiles.find(
                (item) => item.id === this.form.discount_profile_id,
            );
            if (!profile || profile.readonly) return;
            this.discountFields.forEach((field) => {
                this.form[field.key] = profile[field.key];
            });
            this.form.annual_discount_percent = this.form.discount_12_months;
        },
        openDiscountSettings() {
            this.showMessage(
                "DB hiện chỉ lưu giảm kỳ 12 tháng trực tiếp trên từng bậc phí. Chưa có bảng riêng cho mẫu giảm kỳ.",
                "error",
            );
        },
        closeDiscountSettings() {
            this.showDiscountModal = false;
            this.resetDiscountForm();
        },
        resetDiscountForm() {
            this.editingDiscountId = null;
            this.discountForm = emptyDiscountForm();
            this.discountErrors = {};
        },
        editDiscountProfile(profile) {
            this.editingDiscountId = profile.id;
            this.discountForm = { ...profile };
            this.discountErrors = {};
        },
        async saveDiscountProfile() {
            try {
                if (this.editingDiscountId) {
                    await updateDiscountProfile(
                        this.editingDiscountId,
                        this.discountForm,
                    );
                } else {
                    await createDiscountProfile(this.discountForm);
                }
                await this.loadDiscountProfiles();
                await this.loadTiers();
                this.resetDiscountForm();
                this.showMessage("Đã lưu mẫu giảm giá.");
            } catch (error) {
                this.discountErrors = error.validation?.errors ||
                    error.data?.errors || {
                        _form: [error.message],
                    };
                this.showMessage(error.message, "error");
            }
        },
        requestRemoveDiscountProfile(profile) {
            this.confirmationDialog = {
                type: "delete-discount-profile",
                profile,
                title: "Xóa mẫu giảm giá?",
                message: `Mẫu “${profile.name}” sẽ bị xóa khỏi cấu hình.`,
                confirmLabel: "Xóa mẫu",
            };
        },
        closeConfirmationDialog() {
            if (this.confirmationBusy) return;
            this.confirmationDialog = null;
        },
        async confirmDialogAction() {
            if (!this.confirmationDialog || this.confirmationBusy) return;

            const dialog = this.confirmationDialog;
            if (dialog.type === "discard-tier-form") {
                this.confirmationDialog = null;
                this.closeModal();
                return;
            }

            this.confirmationBusy = true;
            try {
                if (dialog.type === "delete-discount-profile") {
                    await deleteDiscountProfile(dialog.profile.id);
                    await this.loadDiscountProfiles();
                    this.showMessage("Đã xóa mẫu giảm giá.");
                }
                this.confirmationDialog = null;
            } catch (error) {
                this.showMessage(error.message, "error");
            } finally {
                this.confirmationBusy = false;
            }
        },
        discountFieldError(field) {
            return this.discountErrors[field]?.[0] || "";
        },
        discountProfileLabel(profile) {
            return `1T ${this.percent(profile.discount_1_month)} · 3T ${this.percent(profile.discount_3_months)} · 6T ${this.percent(profile.discount_6_months)} · 9T ${this.percent(profile.discount_9_months)} · 12T ${this.percent(profile.discount_12_months)}`;
        },
        openRemoveTier(tier) {
            this.removingTier = tier;
        },
        closeRemoveTier() {
            if (this.removingTierBusy) return;
            this.removingTier = null;
        },
        async confirmRemoveTier() {
            if (!this.removingTier || this.removingTierBusy) return;

            const tier = this.removingTier;
            const hasUsage = this.usageCount(tier.id) > 0;
            this.removingTierBusy = true;
            try {
                const response = await deleteTier(tier.id);
                this.removingTier = null;
                this.showMessage(
                    response?.message ||
                        (hasUsage
                            ? "Đã ngừng dùng bậc phí."
                            : "Đã xóa bậc phí."),
                );
                await this.loadTiers();
            } catch (error) {
                this.showMessage(error.message, "error");
            } finally {
                this.removingTierBusy = false;
            }
        },
        viewTier(tier) {
            this.viewingTier = tier;
        },
        checkCoverage() {
            const result = validateTierCoverage(this.tiers);
            this.showMessage(
                result.isValid
                    ? "Khoảng bậc phí hợp lệ."
                    : result.errors.join(" "),
                result.isValid ? "success" : "error",
            );
        },
        syncPreviewCourtCount() {
            const venue = this.venues.find(
                (item) => item.id === this.preview.venue_cluster_id,
            );
            if (venue) this.preview.court_count = venue.court_count;
            this.runPreview();
        },
        runPreview() {
            this.previewError = "";
            this.previewResult = null;
            this.previewWarnings = [];
            const coverage = validateTierCoverage(this.tiers);
            if (!coverage.isValid) {
                this.previewError =
                    "Cấu hình bậc phí hiện chưa hợp lệ, vui lòng sửa trước khi tạo kỳ phí.";
                return;
            }
            const found = findTierForCourtCount(
                this.preview.court_count,
                this.tiers,
            );
            if (!found.tier) {
                this.previewError = "Chưa có bậc phí phù hợp cho cụm sân này.";
                return;
            }
            this.previewResult = calculatePlatformFee({
                court_count: this.preview.court_count,
                period_months: this.preview.period_months,
                tier: found.tier,
            });
            this.previewWarnings = this.previewResult.warnings;
        },
        async reloadFromDb() {
            await Promise.all([
                this.loadDiscountProfiles(),
                this.loadVenues(),
                this.loadTiers(),
            ]);
            this.showMessage("Đã tải lại dữ liệu phí nền tảng từ DB.");
        },
        fieldError(field) {
            return this.formErrors[field]?.[0] || "";
        },
        usageCount(id) {
            return getTierUsageCount(id, this.tiers);
        },
        rangeLabel(tier) {
            return tier.max_courts === null || tier.max_courts === ""
                ? `Từ ${tier.min_courts} sân trở lên`
                : `${tier.min_courts} - ${tier.max_courts} sân`;
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
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
        },
    },
};
</script>

<style scoped>
.pf-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.head-actions,
.panel-title,
.filter-panel,
.actions,
.preview-form,
.modal-head,
.modal-actions,
.icon-text {
    display: flex;
    gap: 12px;
}
.head-actions,
.modal-actions {
    align-items: center;
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
.notice-card,
.info-card,
.modal {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.panel {
    padding: 16px;
}
.notice-card {
    padding: 14px 16px;
    background: #fff7ed;
    color: #9a3412;
    font-weight: 800;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
}
.info-card {
    padding: 12px;
    color: #334155;
    font-weight: 800;
}
.filter-panel {
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
.filter-panel input {
    max-width: 360px;
}
.filter-panel select {
    max-width: 220px;
}
.panel-title {
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.panel-title span,
small {
    color: #64748b;
}
.table-wrap {
    overflow-x: auto;
}
table {
    width: 100%;
    min-width: 1180px;
    border-collapse: collapse;
}
th,
td {
    padding: 12px;
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
td strong,
td small {
    display: block;
}
.status-dot {
    display: inline-grid;
    width: 14px;
    height: 14px;
    border-radius: 999px;
    background: #10b981;
    box-shadow: 0 0 0 3px #d1fae5;
}
.status-dot.inactive {
    background: #ef4444;
    box-shadow: 0 0 0 3px #fee2e2;
}
.actions {
    flex-wrap: wrap;
    justify-content: center;
    min-width: 176px;
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
.icon-btn.danger {
    background: #fee2e2;
    color: #991b1b;
    border-color: #fecaca;
}
.icon-btn:disabled {
    cursor: not-allowed;
    opacity: 0.45;
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
.btn.danger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #dc2626;
    color: #fff;
}
.btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
.icon-text {
    align-items: center;
    justify-content: center;
}
.preview-form {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr auto;
    align-items: end;
}
label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-weight: 800;
    color: #334155;
}
.preview-result,
.detail-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}
.preview-result div,
.detail-grid div {
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px;
}
.preview-result span,
.detail-grid span {
    display: block;
    color: #64748b;
    font-size: 12px;
}
.alert {
    border-radius: 8px;
    padding: 10px 12px;
    margin-top: 10px;
    font-weight: 800;
}
.alert.error,
.toast.error {
    background: #fef2f2;
    color: #991b1b;
}
.alert.warning {
    background: #fef3c7;
    color: #92400e;
}
.toast.success {
    background: #ecfdf5;
    color: #047857;
}
.toast {
    border-radius: 8px;
    padding: 11px 13px;
    font-weight: 800;
}
.empty {
    padding: 36px;
    text-align: center;
    color: #64748b;
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
.confirmation-backdrop {
    z-index: 950;
}
.modal {
    width: min(840px, calc(100vw - 32px));
    max-height: calc(100vh - 40px);
    overflow: auto;
}
.discount-modal {
    width: min(980px, calc(100vw - 32px));
}
.confirm-modal {
    width: min(500px, calc(100vw - 32px));
}
.confirm-content {
    display: grid;
    gap: 10px;
    padding: 20px 22px;
}
.confirm-content p {
    color: #475569;
    line-height: 1.55;
}
.modal-head p {
    margin-top: 5px;
    color: #64748b;
    font-size: 13px;
}
.discount-form {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    padding: 18px 22px;
    border-bottom: 1px solid #e2e8f0;
}
.discount-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.discount-list {
    padding: 18px 22px 22px;
}
.discount-profile-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}
.discount-profile-row:last-child {
    border-bottom: 0;
}
.discount-profile-row strong,
.discount-profile-row small {
    display: block;
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
.full {
    grid-column: 1 / -1;
}
.check-row {
    flex-direction: row;
    align-items: center;
}
.check-row input {
    width: auto;
}
.field-error,
small.field-error {
    color: #dc2626 !important;
    font-weight: 800;
}
.validation-message {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin-top: 2px;
    padding: 7px 9px;
    border: 1px solid #fecaca;
    border-radius: 6px;
    background: #fef2f2;
    color: #dc2626 !important;
    font-size: 12px;
    font-weight: 900;
    line-height: 1.35;
}
.validation-message svg {
    flex: 0 0 auto;
    margin-top: 1px;
}
.modal-actions {
    justify-content: flex-end;
    padding: 16px 22px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}
@media (max-width: 900px) {
    .info-grid,
    .preview-result,
    .detail-grid {
        grid-template-columns: 1fr 1fr;
    }
    .preview-form,
    .form-grid,
    .discount-form {
        grid-template-columns: 1fr;
    }
}
</style>

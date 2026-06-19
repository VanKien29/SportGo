<template>
    <section class="finance-operations">
        <header class="page-header">
            <div>
                <h2>Xử lý hoàn tiền và rút tiền</h2>
                <p>Đối soát yêu cầu, số dư online và phiếu tài chính.</p>
            </div>
            <button
                class="icon-only"
                type="button"
                title="Tải lại"
                aria-label="Tải lại"
                :disabled="loading"
                @click="loadData(1)"
            >
                <AppIcon name="refresh" size="17" />
            </button>
        </header>

        <div class="tabs" role="tablist">
            <button
                :class="{ active: tab === 'refunds' }"
                type="button"
                @click="switchTab('refunds')"
            >
                Hoàn tiền
            </button>
            <button
                :class="{ active: tab === 'withdrawals' }"
                type="button"
                @click="switchTab('withdrawals')"
            >
                Rút tiền
            </button>
        </div>

    <form class="toolbar" @submit.prevent="loadData(1)">
      <label class="search-field">
        <AppIcon name="search" size="17" />
        <input v-model.trim="filters.keyword" type="search" :placeholder="searchPlaceholder" />
      </label>
      <select v-model="filters.status">
        <option value="">Tất cả trạng thái</option>
        <option v-for="status in statusOptions" :key="status" :value="status">{{ statusLabel(status) }}</option>
      </select>
      <select v-if="tab === 'refunds'" v-model="filters.refund_destination">
        <option value="">Tất cả nơi nhận</option>
        <option value="bank_account">Tài khoản ngân hàng</option>
        <option value="user_wallet">Ví SportGo</option>
        <option value="original_payment">Phương thức gốc</option>
      </select>
      <select v-if="tab === 'refunds'" v-model="filters.owner_confirmed">
        <option value="">Phản hồi chủ sân</option>
        <option value="yes">Đã phản hồi</option>
        <option value="no">Chưa phản hồi</option>
      </select>
      <select v-model="filters.date_range">
        <option value="">{{ tab === 'refunds' ? 'Ngày yêu cầu' : 'Ngày rút' }}</option>
        <option value="today">Hôm nay</option>
        <option value="yesterday">Hôm qua</option>
        <option value="last_3_days">3 ngày gần đây</option>
        <option value="last_7_days">7 ngày gần đây</option>
        <option value="last_30_days">30 ngày gần đây</option>
        <option value="this_month">Tháng này</option>
        <option value="last_month">Tháng trước</option>
        <option value="custom">Tùy chỉnh</option>
      </select>
      <div v-if="filters.date_range === 'custom'" class="date-range-fields" :aria-label="tab === 'refunds' ? 'Khoảng ngày yêu cầu hoàn tiền tùy chỉnh' : 'Khoảng ngày yêu cầu rút tiền tùy chỉnh'">
        <input v-model="filters.date_from" type="date" :title="tab === 'refunds' ? 'Yêu cầu hoàn tiền từ ngày' : 'Yêu cầu rút tiền từ ngày'" />
        <span>đến</span>
        <input v-model="filters.date_to" type="date" :title="tab === 'refunds' ? 'Yêu cầu hoàn tiền đến ngày' : 'Yêu cầu rút tiền đến ngày'" :min="filters.date_from" />
      </div>
      <button class="icon-only primary" type="submit" title="Lọc danh sách" aria-label="Lọc danh sách">
        <AppIcon name="filter" size="16" />
      </button>
      <button class="icon-only" type="button" title="Xóa lọc" aria-label="Xóa lọc" @click="resetFilters">
        <AppIcon name="x" size="16" />
      </button>
      <button
        class="export-btn"
        type="button"
        :disabled="selectedExportableIds.length === 0 || exporting"
        @click="exportSelected"
      >
        <AppIcon name="fileText" size="16" />
        {{ exporting ? 'Đang export...' : `Export MB (${selectedExportableIds.length})` }}
      </button>
    </form>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="success" class="alert success">{{ success }}</div>

        <div class="table-wrap">
            <table v-if="tab === 'refunds'">
                <thead>
                    <tr>
                        <th>
                            <input
                                type="checkbox"
                                :checked="allExportableSelected"
                                @change="toggleAllExportable"
                            />
                        </th>
                        <th>Booking / Payment</th>
                        <th>Khách hàng</th>
                        <th>Tài khoản nhận tiền</th>
                        <th>Owner xác nhận</th>
                        <th>Số tiền</th>
                        <th>Trạng thái</th>
                        <th>Phiếu</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="empty">
                            Đang tải yêu cầu hoàn tiền...
                        </td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="9" class="empty">
                            Chưa có yêu cầu hoàn tiền.
                        </td>
                    </tr>
                    <tr v-for="refund in items" :key="refund.id">
                        <td>
                            <input
                                v-if="isExportable(refund)"
                                v-model="selectedIds"
                                type="checkbox"
                                :value="refund.id"
                            />
                        </td>
                        <td>
                            <strong>{{
                                refund.booking?.booking_code || "-"
                            }}</strong
                            ><span class="sub-line"
                                >{{ refund.payment?.payment_code || "-" }} ·
                                {{ refund.venue_cluster?.name || "-" }}</span
                            >
                        </td>
                        <td>
                            <strong>{{ personName(refund.customer) }}</strong
                            ><span class="sub-line">{{
                                refund.customer?.email ||
                                refund.customer?.phone ||
                                "-"
                            }}</span>
                        </td>
                        <td>
                            <template
                                v-if="
                                    refund.refund_destination?.type ===
                                    'bank_account'
                                "
                            >
                                <template v-if="hasRefundBankAccount(refund)">
                                    <strong>
                                        {{
                                            refund.refund_destination?.label ||
                                            "-"
                                        }}
                                        ·
                                        {{
                                            refund.refund_destination
                                                ?.account_number
                                        }}
                                    </strong>
                                    <span class="sub-line">{{
                                        refund.refund_destination
                                            ?.account_holder || "-"
                                    }}</span>
                                    <span
                                        v-if="refund.payout_transfer_code"
                                        class="transfer-subline"
                                    >
                                        Nội dung:
                                        {{ refund.payout_transfer_code }}
                                    </span>
                                </template>
                                <template v-else>
                                    <strong>{{
                                        refund.refund_destination?.label ||
                                        "Tài khoản ngân hàng"
                                    }}</strong>
                                    <span class="inline-warning">
                                        Thiếu tài khoản nhận tiền
                                    </span>
                                </template>
                            </template>
                            <template v-else>
                                <strong>{{
                                    refund.refund_destination?.label || "-"
                                }}</strong>
                                <span class="sub-line">{{
                                    refund.refund_destination?.account_holder ||
                                    "-"
                                }}</span>
                                <span
                                    v-if="isRefundWaitingTransfer(refund)"
                                    class="inline-warning"
                                >
                                    Thiếu tài khoản nhận tiền
                                </span>
                            </template>
                        </td>
                        <td>
                            <span
                                class="status-pill"
                                :class="ownerDecisionClass(refund)"
                            >
                                {{ ownerDecisionLabel(refund) }}
                            </span>
                            <span class="sub-line">{{
                                formatDate(
                                    refund.owner_confirmation?.confirmed_at,
                                )
                            }}</span>
                        </td>
                        <td>
                            <strong>{{ formatCurrency(refund.amount) }}</strong>
                            <span class="sub-line">{{
                                refund.reason || "-"
                            }}</span>
                            <div
                                v-if="refund.policy_evaluation"
                                class="policy-badge"
                                :class="
                                    policyBadgeClass(refund.policy_evaluation)
                                "
                            >
                                <span class="policy-icon">
                                    <AppIcon
                                        :name="
                                            policyIcon(refund.policy_evaluation)
                                        "
                                        size="13"
                                    />
                                </span>
                                <span class="policy-text">
                                    <template
                                        v-if="refund.policy_evaluation.detail"
                                    >
                                        {{
                                            refund.policy_evaluation.detail
                                                .refund_percent != null
                                                ? refund.policy_evaluation
                                                      .detail.refund_percent +
                                                  "%"
                                                : ""
                                        }}
                                        <template
                                            v-if="
                                                refund.policy_evaluation.detail
                                                    .suggested_amount != null
                                            "
                                        >
                                            · tối đa
                                            {{
                                                formatCurrency(
                                                    refund.policy_evaluation
                                                        .detail
                                                        .suggested_amount,
                                                )
                                            }}
                                        </template>
                                    </template>
                                    <template v-else>
                                        {{
                                            refund.policy_evaluation.summary ||
                                            "Chưa đánh giá"
                                        }}
                                    </template>
                                </span>
                                <button
                                    v-if="
                                        refund.policy_evaluation.detail ||
                                        refund.policy_evaluation.violations
                                            ?.length
                                    "
                                    class="policy-expand"
                                    type="button"
                                    :title="
                                        policyExpandTooltip(
                                            refund.policy_evaluation,
                                        )
                                    "
                                    @click="togglePolicyDetail(refund.id)"
                                >
                                    <AppIcon
                                        :name="
                                            expandedPolicies[refund.id]
                                                ? 'chevronUp'
                                                : 'chevronDown'
                                        "
                                        size="12"
                                    />
                                </button>
                            </div>
                            <div
                                v-if="
                                    expandedPolicies[refund.id] &&
                                    refund.policy_evaluation
                                "
                                class="policy-detail"
                            >
                                <div
                                    v-if="refund.policy_evaluation.detail"
                                    class="policy-detail-grid"
                                >
                                    <span>Nguồn</span
                                    ><strong>{{
                                        refund.policy_evaluation.detail
                                            .source_label || "-"
                                    }}</strong>
                                    <span>Rule</span
                                    ><strong>{{
                                        refund.policy_evaluation.detail
                                            .rule_name || "-"
                                    }}</strong>
                                    <span>Đã thanh toán</span
                                    ><strong>{{
                                        formatCurrency(
                                            refund.policy_evaluation.detail
                                                .paid_amount,
                                        )
                                    }}</strong>
                                    <span>Giờ trước sân</span
                                    ><strong>{{
                                        refund.policy_evaluation.detail
                                            .hours_before_start != null
                                            ? refund.policy_evaluation.detail
                                                  .hours_before_start + "h"
                                            : "-"
                                    }}</strong>
                                </div>
                                <div
                                    v-for="(v, vi) in refund.policy_evaluation
                                        .violations || []"
                                    :key="vi"
                                    class="policy-violation"
                                >
                                    <AppIcon name="alert" size="13" />
                                    {{ v.message }}
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="status-pill" :class="refund.status">{{
                                statusLabel(refund.status)
                            }}</span
                            ><span class="sub-line">{{
                                refund.status_reason ||
                                formatDate(refund.processed_at)
                            }}</span>
                        </td>
                        <td>
                            <button
                                v-if="refund.receipt"
                                class="code-link"
                                type="button"
                                @click="openReceipt(refund.receipt)"
                            >
                                {{ refund.receipt.receipt_code }}</button
                            ><span v-else>-</span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button
                                    v-if="canOpenPayout(refund)"
                                    class="pay-command"
                                    type="button"
                                    title="Tạo QR chuyển khoản"
                                    @click="openPayout(refund)"
                                >
                                    <AppIcon name="banknote" size="16" />Thanh
                                    toán
                                </button>
                                <button
                                    v-if="refund.allowed_statuses.length"
                                    class="icon-only"
                                    type="button"
                                    title="Từ chối yêu cầu"
                                    @click="openAction(refund)"
                                >
                                    <AppIcon name="settings" size="17" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table v-else>
                <thead>
                    <tr>
                        <th>
                            <input
                                type="checkbox"
                                :checked="allExportableSelected"
                                @change="toggleAllExportable"
                            />
                        </th>
                        <th>Yêu cầu / Chủ sân</th>
                        <th>Cụm sân</th>
                        <th>Số dư online còn lại</th>
                        <th>Tài khoản owner</th>
                        <th>Số tiền yêu cầu</th>
                        <th>Trạng thái</th>
                        <th>Phiếu</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="empty">
                            Đang tải yêu cầu rút tiền...
                        </td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="9" class="empty">
                            Chưa có yêu cầu rút tiền.
                        </td>
                    </tr>
                    <tr v-for="withdrawal in items" :key="withdrawal.id">
                        <td>
                            <input
                                v-if="isExportable(withdrawal)"
                                v-model="selectedIds"
                                type="checkbox"
                                :value="withdrawal.id"
                            />
                        </td>
                        <td>
                            <strong>{{ withdrawal.request_code }}</strong
                            ><span class="sub-line">{{
                                personName(withdrawal.owner)
                            }}</span>
                        </td>
                        <td>
                            {{ withdrawal.venue_clusters?.join(", ") || "-" }}
                        </td>
                        <td>
                            <strong>{{
                                formatCurrency(
                                    withdrawal.wallet?.available_balance,
                                )
                            }}</strong
                            ><span class="sub-line"
                                >Đang giữ:
                                {{
                                    formatCurrency(
                                        withdrawal.wallet
                                            ?.pending_withdrawal_balance,
                                    )
                                }}</span
                            >
                        </td>
                        <td>
                            <strong
                                >{{ withdrawal.bank_account?.bank_name }} ·
                                {{
                                    withdrawal.bank_account?.account_number
                                }}</strong
                            ><span class="sub-line">{{
                                withdrawal.bank_account?.account_holder_name
                            }}</span>
                        </td>
                        <td>
                            <strong>{{
                                formatCurrency(withdrawal.amount)
                            }}</strong
                            ><span class="sub-line">{{
                                withdrawal.owner_note || "-"
                            }}</span>
                        </td>
                        <td>
                            <span
                                class="status-pill"
                                :class="withdrawal.status"
                                >{{ statusLabel(withdrawal.status) }}</span
                            ><span class="sub-line">{{
                                withdrawal.status_reason ||
                                formatDate(withdrawal.requested_at)
                            }}</span>
                        </td>
                        <td>
                            <button
                                v-if="withdrawal.receipt"
                                class="code-link"
                                type="button"
                                @click="openReceipt(withdrawal.receipt)"
                            >
                                {{ withdrawal.receipt.receipt_code }}</button
                            ><span v-else>-</span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button
                                    v-if="canOpenPayout(withdrawal)"
                                    class="pay-command"
                                    type="button"
                                    title="Tạo QR chuyển khoản"
                                    @click="openPayout(withdrawal)"
                                >
                                    <AppIcon name="banknote" size="16" />Thanh
                                    toán
                                </button>
                                <button
                                    v-if="withdrawal.allowed_statuses.length"
                                    class="icon-only"
                                    type="button"
                                    title="Từ chối yêu cầu"
                                    @click="openAction(withdrawal)"
                                >
                                    <AppIcon name="settings" size="17" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <button
                class="icon-only"
                type="button"
                title="Trang trước"
                aria-label="Trang trước"
                :disabled="meta.current_page <= 1 || loading"
                @click="loadData(meta.current_page - 1)"
            >
                <AppIcon name="chevronLeft" size="17" />
            </button>
            <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
            <button
                class="icon-only"
                type="button"
                title="Trang sau"
                aria-label="Trang sau"
                :disabled="meta.current_page >= meta.last_page || loading"
                @click="loadData(meta.current_page + 1)"
            >
                <AppIcon name="chevronRight" size="17" />
            </button>
        </div>

        <div v-if="actionItem" class="modal-backdrop" @click.self="closeAction">
            <form class="action-modal" @submit.prevent="submitAction">
                <header>
                    <h3>
                        Từ chối
                        {{ tab === "refunds" ? "hoàn tiền" : "rút tiền" }}
                    </h3>
                    <button
                        class="icon-only"
                        type="button"
                        @click="closeAction"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>
                <label
                    >Trạng thái tiếp theo<select
                        v-model="actionForm.status"
                        required
                    >
                        <option
                            v-for="status in actionItem.allowed_statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ actionLabel(status) }}
                        </option>
                    </select></label
                >
                <label v-if="actionForm.status === 'completed'"
                    >{{
                        tab === "refunds"
                            ? "Mã giao dịch hoàn tiền"
                            : "Mã giao dịch MB"
                    }}<input
                        v-model.trim="actionForm.reference"
                        type="text"
                        required
                /></label>
                <label
                    >Lý do / ghi chú<textarea
                        v-model.trim="actionForm.reason"
                        rows="4"
                        :required="actionForm.status === 'rejected'"
                        placeholder="Bắt buộc khi từ chối"
                    ></textarea>
                </label>
                <div v-if="actionError" class="alert error">
                    {{ actionError }}
                </div>
                <footer>
                    <button
                        class="secondary-btn"
                        type="button"
                        @click="closeAction"
                    >
                        Hủy</button
                    ><button
                        class="primary-btn"
                        :class="{ danger: actionForm.status === 'rejected' }"
                        type="submit"
                        :disabled="saving"
                    >
                        {{ saving ? "Đang lưu..." : "Xác nhận từ chối" }}
                    </button>
                </footer>
            </form>
        </div>

        <div v-if="receipt" class="modal-backdrop" @click.self="receipt = null">
            <section class="receipt-modal">
                <header>
                    <div>
                        <span class="eyebrow">Phiếu tài chính</span>
                        <h3>{{ receipt.receipt_code }}</h3>
                    </div>
                    <button
                        class="icon-only"
                        type="button"
                        @click="receipt = null"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>
                <div class="receipt-facts">
                    <span>Tiêu đề</span><strong>{{ receipt.title }}</strong
                    ><span>Số tiền</span
                    ><strong>{{ formatCurrency(receipt.amount) }}</strong
                    ><span>Phát hành lúc</span
                    ><strong>{{ formatDate(receipt.issued_at) }}</strong
                    ><span>Trạng thái</span
                    ><strong>{{ receipt.status }}</strong>
                </div>
                <pre>{{ prettyJson(receipt.metadata) }}</pre>
            </section>
        </div>

        <div v-if="payoutOpen" class="modal-backdrop" @click.self="closePayout">
            <section class="payout-modal">
                <header>
                    <div>
                        <span class="eyebrow">{{
                            tab === "refunds" ? "Hoàn tiền" : "Rút tiền"
                        }}</span>
                        <h3>Thanh toán QR</h3>
                    </div>
                    <button
                        class="icon-only"
                        type="button"
                        title="Đóng"
                        @click="closePayout"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                </header>

                <div v-if="payoutLoading" class="empty">
                    Đang tạo QR chuyển khoản...
                </div>
                <div v-else-if="payoutError && !payout" class="alert error">
                    {{ payoutError }}
                </div>
                <template v-else-if="payout">
                    <div class="payout-content">
                        <img :src="payout.qr_url" alt="QR chuyển khoản" />
                        <div class="payout-info">
                            <div class="receipt-facts compact">
                                <span>Ngân hàng</span
                                ><strong>{{
                                    payout.recipient.bank_name
                                }}</strong>
                                <span>Số tài khoản</span
                                ><strong>{{
                                    payout.recipient.account_number
                                }}</strong>
                                <span>Chủ tài khoản</span
                                ><strong>{{
                                    payout.recipient.account_holder
                                }}</strong>
                                <span>Số tiền</span
                                ><strong>{{
                                    formatCurrency(payout.amount)
                                }}</strong>
                                <span>Nội dung</span
                                ><strong class="transfer-code">{{
                                    payout.transfer_code
                                }}</strong>
                            </div>
                            <div class="payout-actions">
                                <button
                                    class="secondary-btn"
                                    type="button"
                                    @click="copyText(payout.transfer_code)"
                                >
                                    <AppIcon name="copy" size="16" />Copy nội
                                    dung
                                </button>
                            </div>
                            <div class="payout-waiting">
                                <span class="spinner" aria-hidden="true"></span>
                                <span>Đang chờ thanh toán...</span>
                            </div>
                            <p v-if="copyMessage" class="inline-success">
                                {{ copyMessage }}
                            </p>
                            <p v-if="payoutError" class="inline-error">
                                {{ payoutError }}
                            </p>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </section>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import { adminFinanceOperationsService } from "../../services/adminFinanceOperations.js";

export default {
    name: "AdminFinanceOperations",
    components: { AppIcon },
    data() {
        return {
            tab: "refunds",
            items: [],
            summary: { total: 0, completed: 0, requested_amount: 0 },
            meta: { current_page: 1, last_page: 1 },
            filters: {
                keyword: "",
                status: "",
                refund_destination: "",
                owner_confirmed: "",
                date_range: "",
                date_from: "",
                date_to: "",
            },
            selectedIds: [],
            loading: false,
            exporting: false,
            saving: false,
            error: "",
            success: "",
            actionError: "",
            actionItem: null,
            actionForm: { status: "", reason: "", reference: "" },
            receipt: null,
            payoutOpen: false,
            payoutLoading: false,
            payout: null,
            payoutItem: null,
            payoutError: "",
            payoutPollTimer: null,
            payoutPolling: false,
            copyMessage: "",
            expandedPolicies: {},
        };
    },
    computed: {
        statusOptions() {
            return this.tab === "refunds"
                ? [
                      "pending_confirmation",
                      "pending_owner_confirmation",
                      "owner_confirmed",
                      "owner_rejected",
                      "completed",
                      "failed",
                      "rejected",
                  ]
                : ["pending", "rejected", "completed", "cancelled"];
        },
        pendingSummary() {
            return this.tab === "refunds"
                ? Number(this.summary.pending_confirmation || 0) +
                      Number(this.summary.processing || 0)
                : Number(this.summary.pending || 0) +
                      Number(this.summary.approved || 0);
        },
        searchPlaceholder() {
            return this.tab === "refunds"
                ? "Booking, payment, khách, cụm sân..."
                : "Mã yêu cầu, chủ sân, tài khoản...";
        },
        selectedExportableIds() {
            const exportable = new Set(
                this.items
                    .filter((item) => this.isExportable(item))
                    .map((item) => item.id),
            );
            return this.selectedIds.filter((id) => exportable.has(id));
        },
        allExportableSelected() {
            const exportable = this.items.filter((item) =>
                this.isExportable(item),
            );
            return (
                exportable.length > 0 &&
                exportable.every((item) => this.selectedIds.includes(item.id))
            );
        },
    },
    mounted() {
        this.loadData(1);
    },
    beforeUnmount() {
        this.stopPayoutPolling();
    },
    methods: {
        async loadData(page = 1) {
            this.loading = true;
            this.error = "";
            try {
                const service =
                    this.tab === "refunds"
                        ? adminFinanceOperationsService.refunds
                        : adminFinanceOperationsService.withdrawals;
                const response = await service(
                    this.operationFilterParams(page),
                );
                this.items = response.data || [];
                this.summary = response.summary || this.summary;
                this.meta = response.meta || this.meta;
                this.selectedIds = [];
            } catch (error) {
                this.error =
                    error.message || "Không tải được dữ liệu tài chính.";
            } finally {
                this.loading = false;
            }
        },
        switchTab(tab) {
            this.tab = tab;
            this.filters = this.blankFilters();
            this.success = "";
            this.loadData(1);
        },
        resetFilters() {
            this.filters = this.blankFilters();
            this.loadData(1);
        },
        toggleAllExportable(event) {
            this.selectedIds = event.target.checked
                ? this.items
                      .filter((item) => this.isExportable(item))
                      .map((item) => item.id)
                : [];
        },
        async exportSelected() {
            this.exporting = true;
            this.error = "";
            try {
                if (this.tab === "refunds") {
                    await adminFinanceOperationsService.exportRefunds(
                        this.selectedExportableIds,
                    );
                } else {
                    await adminFinanceOperationsService.exportWithdrawals(
                        this.selectedExportableIds,
                    );
                }
                this.success =
                    "Đã export file chuyển lô MB. Nội dung chuyển khoản là mã yêu cầu.";
                await this.loadData(this.meta.current_page);
            } catch (error) {
                this.error =
                    error.message || "Không export được file chuyển lô MB.";
            } finally {
                this.exporting = false;
            }
        },
        async openPayout(item) {
            this.payoutOpen = true;
            this.payoutLoading = true;
            this.payout = null;
            this.payoutItem = item;
            this.payoutError = "";
            this.copyMessage = "";
            try {
                const response =
                    this.tab === "refunds"
                        ? await adminFinanceOperationsService.refundPayoutQr(
                              item.id,
                          )
                        : await adminFinanceOperationsService.withdrawalPayoutQr(
                              item.id,
                          );
                this.payout = response.data;
                this.startPayoutPolling();
            } catch (error) {
                this.payoutError =
                    error.message || "Không tạo được QR chuyển khoản.";
            } finally {
                this.payoutLoading = false;
            }
        },
        closePayout() {
            this.stopPayoutPolling();
            this.payoutOpen = false;
            this.payout = null;
            this.payoutItem = null;
            this.payoutError = "";
            this.copyMessage = "";
        },
        startPayoutPolling() {
            this.stopPayoutPolling();
            this.payoutPollTimer = window.setInterval(
                () => this.refreshPayoutStatus(),
                5000,
            );
        },
        stopPayoutPolling() {
            if (this.payoutPollTimer) {
                window.clearInterval(this.payoutPollTimer);
                this.payoutPollTimer = null;
            }
            this.payoutPolling = false;
        },
        async refreshPayoutStatus() {
            if (!this.payoutItem || this.payoutPolling) return;
            this.payoutPolling = true;
            try {
                const service =
                    this.tab === "refunds"
                        ? adminFinanceOperationsService.refunds
                        : adminFinanceOperationsService.withdrawals;
                const response = await service(
                    this.operationFilterParams(this.meta.current_page),
                );
                this.items = response.data || [];
                this.summary = response.summary || this.summary;
                this.meta = response.meta || this.meta;

                const updated = this.items.find(
                    (item) => item.id === this.payoutItem.id,
                );
                if (!updated) return;

                this.payoutItem = updated;
                if (updated.status === "completed") {
                    this.success =
                        "SePay đã xác nhận giao dịch. Yêu cầu đã hoàn tất.";
                    this.closePayout();
                } else if (
                    [
                        "rejected",
                        "cancelled",
                        "failed",
                        "owner_rejected",
                    ].includes(updated.status)
                ) {
                    this.payoutError =
                        "Yêu cầu đã kết thúc nên không tiếp tục chờ SePay.";
                    this.stopPayoutPolling();
                }
            } catch (error) {
                this.payoutError =
                    error.message || "Không cập nhật được trạng thái mới nhất.";
            } finally {
                this.payoutPolling = false;
            }
        },
        openAction(item) {
            this.actionItem = item;
            this.actionError = "";
            this.actionForm = {
                status: item.allowed_statuses[0] || "",
                reason: "",
                reference: "",
            };
        },
        closeAction() {
            this.actionItem = null;
            this.actionError = "";
        },
        async submitAction() {
            this.saving = true;
            this.actionError = "";
            try {
                const payload = {
                    status: this.actionForm.status,
                    reason: this.actionForm.reason || null,
                    source: "admin",
                };
                if (this.tab === "refunds") {
                    payload.gateway_refund_txn_id =
                        this.actionForm.reference || null;
                    await adminFinanceOperationsService.updateRefund(
                        this.actionItem.id,
                        payload,
                    );
                } else {
                    payload.transfer_reference =
                        this.actionForm.reference || null;
                    await adminFinanceOperationsService.updateWithdrawal(
                        this.actionItem.id,
                        payload,
                    );
                }
                this.success = "Đã cập nhật trạng thái thành công.";
                this.closeAction();
                await this.loadData(this.meta.current_page);
            } catch (error) {
                this.actionError = error.message || "Không thể xử lý yêu cầu.";
            } finally {
                this.saving = false;
            }
        },
        openReceipt(receipt) {
            this.receipt = receipt;
        },
        policyBadgeClass(pe) {
            if (!pe.evaluated) return "muted";
            if (pe.compliant === true) return "compliant";
            if (pe.compliant === false) return "non-compliant";
            return "neutral";
        },
        policyIcon(pe) {
            if (!pe.evaluated) return "alert";
            if (pe.compliant === true) return "circleCheck";
            if (pe.compliant === false) return "circleX";
            return "alert";
        },
        policyExpandTooltip(pe) {
            if (pe.violations?.length)
                return `${pe.violations.length} cảnh báo chính sách`;
            return "Xem chi tiết chính sách";
        },
        togglePolicyDetail(id) {
            this.expandedPolicies = {
                ...this.expandedPolicies,
                [id]: !this.expandedPolicies[id],
            };
        },
        async copyText(value) {
            try {
                await navigator.clipboard.writeText(String(value || ""));
                this.copyMessage = "Đã copy nội dung chuyển khoản.";
            } catch {
                this.copyMessage = "";
                this.payoutError = "Không thể copy tự động.";
            }
        },
        personName(person) {
            return person?.full_name || person?.username || "-";
        },
        isExportable(item) {
            if (this.tab === "refunds") {
                return (
                    [
                        "pending_confirmation",
                        "owner_confirmed",
                        "admin_processing",
                        "processing",
                    ].includes(item.status) &&
                    item.refund_destination?.type === "bank_account" &&
                    Boolean(item.refund_destination?.account_number)
                );
            }

            return (
                ["pending", "reviewing", "approved"].includes(item.status) &&
                item.bank_account?.status === "active" &&
                Boolean(item.bank_account?.account_number)
            );
        },
        canOpenPayout(item) {
            return Boolean(item.can_pay_by_qr) || this.isExportable(item);
        },
        hasRefundBankAccount(refund) {
            return Boolean(refund.refund_destination?.account_number);
        },
        isRefundWaitingTransfer(refund) {
            return [
                "pending_confirmation",
                "owner_confirmed",
                "admin_processing",
                "processing",
            ].includes(refund.status);
        },
        blankFilters() {
            return {
                keyword: "",
                status: "",
                refund_destination: "",
                owner_confirmed: "",
                date_range: "",
                date_from: "",
                date_to: "",
            };
        },
        operationFilterParams(page) {
            const params = { ...this.filters, page };
            delete params.date_range;

            if (this.filters.date_range === "custom") {
                if (!params.date_from) delete params.date_from;
                if (!params.date_to) delete params.date_to;
                return params;
            }

            delete params.date_from;
            delete params.date_to;
            const range = this.resolveDateRange(this.filters.date_range);

            if (range) {
                params.date_from = range.from;
                params.date_to = range.to;
            }

            return params;
        },
        resolveDateRange(value) {
            const today = new Date();
            const from = new Date(today);
            const to = new Date(today);

            if (value === "today") {
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "yesterday") {
                from.setDate(from.getDate() - 1);
                to.setDate(to.getDate() - 1);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "last_3_days") {
                from.setDate(from.getDate() - 3);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "last_7_days") {
                from.setDate(from.getDate() - 7);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "last_30_days") {
                from.setDate(from.getDate() - 30);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "this_month") {
                from.setDate(1);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "last_month") {
                const firstDayThisMonth = new Date(
                    today.getFullYear(),
                    today.getMonth(),
                    1,
                );
                const lastDayLastMonth = new Date(firstDayThisMonth);
                lastDayLastMonth.setDate(0);
                const firstDayLastMonth = new Date(
                    lastDayLastMonth.getFullYear(),
                    lastDayLastMonth.getMonth(),
                    1,
                );
                return {
                    from: this.dateInputValue(firstDayLastMonth),
                    to: this.dateInputValue(lastDayLastMonth),
                };
            }

            return null;
        },
        dateInputValue(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
        },
        actionLabel(value) {
            return { rejected: "Từ chối" }[value] || value;
        },
        statusLabel(value) {
            return (
                {
                    pending_confirmation: "Chờ chuyển khoản",
                    pending_owner_confirmation: "Chờ chủ sân",
                    owner_confirmed: "Chờ chuyển khoản",
                    owner_rejected: "Chủ sân từ chối",
                    admin_processing: "Chờ chuyển khoản",
                    processing: "Chờ chuyển khoản",
                    completed: "Hoàn tất",
                    failed: "Thất bại",
                    rejected: "Từ chối",
                    pending: "Chờ chuyển khoản",
                    reviewing: "Chờ chuyển khoản",
                    approved: "Chờ chuyển khoản",
                    cancelled: "Đã hủy",
                }[value] || value
            );
        },
        ownerDecisionLabel(refund) {
            return {
                approved: "Đã đồng ý",
                rejected: "Đã từ chối",
                pending: "Chưa xác nhận",
            }[refund.owner_confirmation?.decision || "pending"];
        },
        ownerDecisionClass(refund) {
            return {
                approved: "completed",
                rejected: "rejected",
                pending: "pending",
            }[refund.owner_confirmation?.decision || "pending"];
        },
        formatCurrency(value) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(Number(value || 0));
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleString("vi-VN") : "-";
        },
        prettyJson(value) {
            return JSON.stringify(value || {}, null, 2);
        },
    },
};
</script>

<style scoped>
.finance-operations {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.page-header,
.toolbar,
.pagination,
.action-modal header,
.action-modal footer,
.receipt-modal header,
.payout-modal header,
.row-actions,
.payout-actions {
    display: flex;
    align-items: center;
}
.page-header {
    justify-content: space-between;
    gap: 16px;
}
.page-header h2,
.receipt-modal h3 {
    margin: 0 0 4px;
    color: #0f172a;
}
.page-header p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}
.tabs {
    display: flex;
    border-bottom: 1px solid #dbe2ea;
    gap: 8px;
}
.tabs button {
    border: 0;
    border-bottom: 3px solid transparent;
    background: transparent;
    padding: 10px 18px;
    color: #64748b;
    font-weight: 800;
    cursor: pointer;
}
.tabs button.active {
    border-color: #16a34a;
    color: #166534;
}
.sub-line {
    display: block;
    color: #64748b;
    font-size: 12px;
}
.transfer-subline {
    display: block;
    margin-top: 4px;
    color: #15803d;
    font-size: 12px;
    font-weight: 800;
}
.inline-warning {
    display: block;
    margin-top: 4px;
    color: #b45309;
    font-size: 12px;
    font-weight: 800;
}
.toolbar {
    flex-wrap: wrap;
    gap: 8px;
    align-items: stretch;
}
.toolbar select,
.toolbar input,
.action-modal select,
.action-modal input,
.action-modal textarea {
    border: 1px solid #dbe2ea;
    border-radius: 7px;
    background: #fff;
    color: #0f172a;
    padding: 9px 10px;
    font: inherit;
}
.search-field {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 290px;
    border: 1px solid #dbe2ea;
    border-radius: 7px;
    padding: 0 10px;
    background: #fff;
}
.search-field input {
    flex: 1;
    border: 0;
    padding: 9px 0;
    outline: 0;
}
.date-range-fields {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 8px;
    border: 1px solid #dbe2ea;
    border-radius: 7px;
    background: #f8fafc;
    color: #64748b;
}
.date-range-fields input {
    width: 142px;
    border-color: transparent;
    background: #fff;
}
.primary-btn,
.secondary-btn,
.export-btn,
.icon-only,
.pay-command {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border-radius: 7px;
    font-weight: 700;
    cursor: pointer;
}
.primary-btn {
    border: 1px solid #16a34a;
    background: #16a34a;
    color: #fff;
    padding: 9px 12px;
}
.primary-btn.danger {
    border-color: #dc2626;
    background: #dc2626;
}
.secondary-btn,
.export-btn {
    border: 1px solid #dbe2ea;
    background: #f8fafc;
    color: #334155;
    padding: 9px 12px;
}
.export-btn {
    margin-left: auto;
    border-color: #2563eb;
    color: #1d4ed8;
    background: #eff6ff;
}
.pay-command {
    border: 1px solid #16a34a;
    background: #ecfdf5;
    color: #15803d;
    padding: 8px 10px;
    font-size: 12px;
}
.icon-only {
    width: 34px;
    height: 34px;
    border: 1px solid #dbe2ea;
    background: #fff;
    color: #475569;
}
button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.alert {
    padding: 11px 13px;
    border-radius: 7px;
    font-size: 13px;
}
.alert.error {
    background: #fef2f2;
    color: #b91c1c;
}
.alert.success {
    background: #ecfdf5;
    color: #047857;
}
.table-wrap {
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}
table {
    width: 100%;
    min-width: 1120px;
    border-collapse: collapse;
}
th,
td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}
th {
    background: #f8fafc;
    color: #334155;
    font-weight: 800;
}
.empty {
    padding: 28px;
    text-align: center;
    color: #64748b;
}
.status-pill {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 999px;
    background: #e2e8f0;
    color: #334155;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}
.status-pill.pending,
.status-pill.pending_confirmation,
.status-pill.pending_owner_confirmation,
.status-pill.reviewing {
    background: #fef3c7;
    color: #92400e;
}
.status-pill.processing,
.status-pill.admin_processing,
.status-pill.owner_confirmed,
.status-pill.approved {
    background: #dbeafe;
    color: #1e40af;
}
.status-pill.completed {
    background: #dcfce7;
    color: #166534;
}
.status-pill.failed,
.status-pill.rejected,
.status-pill.owner_rejected,
.status-pill.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

/* Policy evaluation badge */
.policy-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.4;
}
.policy-badge.compliant {
    background: #ecfdf5;
    color: #15803d;
}
.policy-badge.non-compliant {
    background: #fef2f2;
    color: #b91c1c;
}
.policy-badge.neutral {
    background: #fef3c7;
    color: #92400e;
}
.policy-badge.muted {
    background: #f1f5f9;
    color: #64748b;
}
.policy-icon {
    display: inline-flex;
    flex-shrink: 0;
}
.policy-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}
.policy-expand {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    padding: 0;
    border-radius: 4px;
    flex-shrink: 0;
}
.policy-expand:hover {
    background: rgba(0, 0, 0, 0.08);
}
.policy-detail {
    margin-top: 6px;
    padding: 8px 10px;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 12px;
}
.policy-detail-grid {
    display: grid;
    grid-template-columns: 90px 1fr;
    gap: 4px 10px;
    color: #64748b;
}
.policy-detail-grid strong {
    color: #0f172a;
    font-weight: 700;
}
.policy-violation {
    display: flex;
    align-items: flex-start;
    gap: 5px;
    margin-top: 6px;
    padding: 5px 8px;
    border-radius: 5px;
    background: #fef2f2;
    color: #b91c1c;
    font-weight: 700;
    line-height: 1.4;
}

.code-link {
    border: 0;
    padding: 0;
    background: transparent;
    color: #15803d;
    font-weight: 800;
    text-decoration: underline;
    cursor: pointer;
}
.row-actions {
    gap: 8px;
    justify-content: flex-end;
}
.pagination {
    justify-content: flex-end;
    gap: 12px;
    color: #64748b;
    font-size: 13px;
}
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 500;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.48);
}
.action-modal,
.receipt-modal,
.payout-modal {
    width: min(540px, calc(100vw - 32px));
    padding: 22px;
    border-radius: 8px;
    background: #fff;
}
.payout-modal {
    width: min(760px, calc(100vw - 32px));
}
.action-modal {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.action-modal header,
.receipt-modal header,
.payout-modal header {
    justify-content: space-between;
    gap: 16px;
}
.action-modal h3 {
    margin: 0;
}
.action-modal label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}
.action-modal footer {
    justify-content: flex-end;
    gap: 8px;
}
.eyebrow {
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}
.receipt-facts {
    display: grid;
    grid-template-columns: 130px 1fr;
    gap: 8px 14px;
    margin: 18px 0;
    color: #475569;
    font-size: 13px;
}
.receipt-facts strong {
    color: #0f172a;
}
.receipt-facts.compact {
    margin: 0;
    grid-template-columns: 108px 1fr;
}
.transfer-code {
    color: #15803d !important;
    letter-spacing: 0.04em;
}
.payout-content {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 20px;
    align-items: start;
    margin-top: 18px;
}
.payout-content img {
    width: 280px;
    max-width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}
.payout-info {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.payout-actions {
    gap: 8px;
    flex-wrap: wrap;
}
.payout-waiting {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px 12px;
    border: 1px solid #bfdbfe;
    border-radius: 7px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 13px;
    font-weight: 700;
}
.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #bfdbfe;
    border-top-color: #2563eb;
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
    flex: 0 0 auto;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.inline-success,
.inline-error {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
}
.inline-success {
    color: #15803d;
}
.inline-error {
    color: #b91c1c;
}
pre {
    max-height: 240px;
    overflow: auto;
    padding: 10px;
    border-radius: 6px;
    background: #0f172a;
    color: #d1fae5;
    font-size: 11px;
    white-space: pre-wrap;
}
@media (max-width: 700px) {
    .payout-content {
        grid-template-columns: 1fr;
    }
    .payout-content img {
        width: 100%;
    }
}
@media (max-width: 600px) {
    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }
    .search-field {
        min-width: 100%;
    }
    .export-btn {
        margin-left: 0;
    }
}
</style>

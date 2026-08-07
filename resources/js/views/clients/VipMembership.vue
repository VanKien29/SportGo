<template>
    <div class="vip-shell sg-client-page">
        <PublicNavbar />
        <main class="vip-page sg-client-shell">
            <header class="page-head">
                <div>
                    <p>SportGo VIP</p>
                    <h1>Chọn gói VIP</h1>
                </div>
                <div class="head-actions">
                    <button class="back-btn" type="button" @click="goBack">
                        ← Quay lại
                    </button>
                    <router-link class="link-btn" to="/profile"
                        >Hồ sơ</router-link
                    >
                </div>
            </header>

            <div v-if="error" class="alert error">{{ error }}</div>
            <div v-if="success" class="alert success">{{ success }}</div>
            <div v-if="hasActiveSubscription" class="alert info">
                Bạn đang có gói VIP còn hiệu lực. Hệ thống chỉ cho phép dùng 1
                gói VIP tại một thời điểm.
            </div>

            <section v-if="subscription" class="current-plan">
                <div>
                    <span>Đang dùng</span>
                    <strong>{{
                        subscription.package?.label ||
                        subscription.package?.name
                    }}</strong>
                </div>
                <div>
                    <span>Hiệu lực đến</span>
                    <strong>{{ date(subscription.expires_at) }}</strong>
                </div>
                <div>
                    <span>Đã thanh toán</span>
                    <strong>{{ money(subscription.paid_amount) }}</strong>
                </div>
            </section>

            <section
                v-if="paymentInfo && !hasActiveSubscription"
                class="payment-panel"
            >
                <div class="payment-copy">
                    <span>Thanh toán</span>
                    <strong>{{ money(paymentInfo.payment?.amount) }}</strong>
                    <small
                        >Chuyển đúng số tiền và nội dung để hệ thống tự kích
                        hoạt gói VIP.</small
                    >
                </div>
                <img
                    v-if="paymentInfo.qr_url"
                    :src="paymentInfo.qr_url"
                    alt="QR thanh toán VIP"
                />
                <div class="payment-details">
                    <div>
                        <small>Ngân hàng</small>
                        <strong>{{
                            paymentInfo.payment_account?.bank_name ||
                            paymentInfo.system_bank_account?.bank_name ||
                            "-"
                        }}</strong>
                    </div>
                    <div>
                        <small>Số tài khoản</small>
                        <button
                            type="button"
                            @click="
                                copyText(
                                    paymentInfo.payment_account
                                        ?.account_number ||
                                        paymentInfo.system_bank_account
                                            ?.account_number,
                                )
                            "
                        >
                            {{
                                paymentInfo.payment_account?.account_number ||
                                paymentInfo.system_bank_account
                                    ?.account_number ||
                                "-"
                            }}
                        </button>
                    </div>
                    <div>
                        <small>Nội dung</small>
                        <button
                            type="button"
                            @click="copyText(paymentInfo.transfer_content)"
                        >
                            {{ paymentInfo.transfer_content }}
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="state">Đang tải gói VIP...</div>
            <section v-else class="plan-grid">
                <article
                    v-for="pkg in paidPackages"
                    :key="pkg.id"
                    class="plan-card"
                    :class="[
                        `plan-${pkg.type}`,
                        { current: isCurrentPackage(pkg) },
                    ]"
                >
                    <header>
                        <div>
                            <span>{{ packageBadgeText(pkg) }}</span>
                            <h2>{{ pkg.label || pkg.name }}</h2>
                        </div>
                        <em v-if="isCurrentPackage(pkg)">Đang dùng</em>
                    </header>

                    <ul>
                        <li>
                            Hoàn tiền {{ pkg.cashback_percent }}% sau booking
                            hoàn tất
                        </li>
                        <li>
                            {{ postLimitText(pkg.match_post_limit_per_month) }}
                        </li>
                        <li>
                            {{
                                pkg.priority_complaint
                                    ? "Ưu tiên xử lý khiếu nại"
                                    : "Xử lý khiếu nại tiêu chuẩn"
                            }}
                        </li>
                    </ul>

                    <div class="cycle-list">
                        <button
                            v-for="cycle in pkg.available_cycles"
                            :key="cycle.key"
                            type="button"
                            :disabled="
                                !canPurchasePackage(pkg) || Boolean(subscribing)
                            "
                            @click="openConfirm(pkg, cycle)"
                        >
                            <span>{{ purchaseActionText(pkg, cycle) }}</span>
                            <strong>{{ money(cycle.price) }}</strong>
                        </button>
                    </div>
                </article>
            </section>

            <div
                v-if="pendingPurchase"
                class="confirm-backdrop"
                @click.self="closeConfirm"
            >
                <section class="confirm-modal" role="dialog" aria-modal="true">
                    <header>
                        <span>Xác nhận mua gói</span>
                        <h2>
                            {{
                                pendingPurchase.package.label ||
                                pendingPurchase.package.name
                            }}
                        </h2>
                    </header>
                    <div class="confirm-summary">
                        <div>
                            <small>Chu kỳ</small>
                            <strong>{{
                                cycleLabel(pendingPurchase.cycle)
                            }}</strong>
                        </div>
                        <div>
                            <small>Số tiền</small>
                            <strong>{{
                                money(pendingPurchase.cycle.price)
                            }}</strong>
                        </div>
                    </div>
                    <p>
                        Sau khi thanh toán thành công, gói VIP sẽ được kích hoạt
                        ngay và hệ thống sẽ phát quyền lợi của tháng đầu tiên.
                    </p>
                    <footer>
                        <button
                            class="ghost-btn"
                            type="button"
                            @click="closeConfirm"
                        >
                            Hủy
                        </button>
                        <button
                            class="confirm-btn"
                            type="button"
                            :disabled="Boolean(subscribing)"
                            @click="confirmPurchase"
                        >
                            {{ subscribing ? "Đang xử lý..." : "Xác nhận mua" }}
                        </button>
                    </footer>
                </section>
            </div>
        </main>
    </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import { vipMembershipService } from "../../services/vipMembershipService.js";

export default {
    name: "VipMembership",
    components: { PublicNavbar },
    data() {
        return {
            packages: [],
            subscription: null,
            loading: false,
            subscribing: "",
            error: "",
            success: "",
            pendingPurchase: null,
            paymentInfo: null,
            paymentPollInterval: null,
        };
    },
    computed: {
        paidPackages() {
            return this.packages.filter(
                (pkg) => pkg.type !== "free" && pkg.is_active,
            );
        },
        hasActiveSubscription() {
            return Boolean(
                this.subscription?.status === "active" &&
                this.subscription?.expires_at &&
                new Date(this.subscription.expires_at) > new Date(),
            );
        },
    },
    mounted() {
        this.load();
    },
    beforeUnmount() {
        this.stopPaymentPolling();
    },
    methods: {
        async load() {
            this.loading = true;
            this.error = "";
            try {
                const response = await vipMembershipService.playerIndex();
                this.packages = response.packages || [];
                this.subscription = response.subscription || null;
                if (this.hasActiveSubscription) {
                    this.paymentInfo = null;
                    this.stopPaymentPolling();
                }
            } catch (error) {
                this.error = error.message || "Không thể tải gói VIP.";
            } finally {
                this.loading = false;
            }
        },
        isCurrentPackage(pkg) {
            return this.subscription?.package?.id === pkg.id;
        },
        canPurchasePackage(pkg) {
            return !this.hasActiveSubscription && !this.isCurrentPackage(pkg);
        },
        goBack() {
            this.$router.push("/profile");
        },
        packageBadgeText(pkg) {
            if (pkg.type === "saving") return "SportGo Tiết kiệm";
            if (pkg.type === "pro") return "SportGo Pro";
            return pkg.badge_name || pkg.label || pkg.name;
        },
        cycleLabel(cycle) {
            return (
                {
                    monthly: "Hằng tháng",
                    quarterly: "Hằng quý",
                    yearly: "Hằng năm",
                }[cycle.key] ||
                cycle.label ||
                "Chu kỳ"
            );
        },
        openConfirm(pkg, cycle) {
            this.error = "";
            this.success = "";
            if (!this.canPurchasePackage(pkg)) {
                this.error =
                    "Bạn đang có gói VIP còn hiệu lực. Không thể mua thêm hoặc đổi sang gói khác cho đến khi gói hiện tại hết hạn.";
                return;
            }
            this.pendingPurchase = { package: pkg, cycle };
        },
        purchaseActionText(pkg, cycle) {
            if (this.isCurrentPackage(pkg)) return "Đang dùng";
            if (this.hasActiveSubscription) return "Đã có gói VIP";
            return `Mua ${this.cycleLabel(cycle).toLowerCase()}`;
        },
        closeConfirm() {
            if (this.subscribing) return;
            this.pendingPurchase = null;
        },
        async confirmPurchase() {
            if (!this.pendingPurchase) return;

            const { package: pkg, cycle } = this.pendingPurchase;
            this.subscribing = `${pkg.id}-${cycle.key}`;
            this.error = "";
            try {
                const response = await vipMembershipService.subscribe({
                    package_id: pkg.id,
                    billing_cycle: cycle.key,
                });
                this.success =
                    response.message || "Đã tạo thông tin thanh toán gói VIP.";
                this.paymentInfo = response;
                this.pendingPurchase = null;
                this.startPaymentPolling();
            } catch (error) {
                this.error = error.message || "Không thể kích hoạt gói VIP.";
            } finally {
                this.subscribing = "";
            }
        },
        startPaymentPolling() {
            this.stopPaymentPolling();
            this.paymentPollInterval = setInterval(() => {
                this.load();
            }, 5000);
        },
        stopPaymentPolling() {
            if (!this.paymentPollInterval) return;
            clearInterval(this.paymentPollInterval);
            this.paymentPollInterval = null;
        },
        async copyText(value) {
            if (!value) return;
            try {
                await navigator.clipboard.writeText(String(value));
                this.success = "Đã sao chép thông tin thanh toán.";
            } catch {
                this.error = "Không thể sao chép. Vui lòng sao chép thủ công.";
            }
        },
        postLimitText(limit) {
            return Number(limit) < 0
                ? "Bài giao lưu không giới hạn"
                : `${Number(limit || 0)} bài giao lưu/tháng`;
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
    },
};
</script>



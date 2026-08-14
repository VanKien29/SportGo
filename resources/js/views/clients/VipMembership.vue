<template>
    <div class="vip-shell sg-client-page">
        <PublicNavbar />
        <main class="vip-page sg-client-shell">
            <header class="page-head">
                <div class="page-head-copy">
                    <p>SportGo VIP</p>
                    <h1>Chọn gói phù hợp với bạn</h1>
                    <span>Quyền lợi rõ ràng, giá minh bạch và kích hoạt sau khi thanh toán.</span>
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

            <section class="vip-intro" aria-label="Thông tin gói VIP">
                <div class="vip-intro__lead">
                    <span class="vip-intro__icon"><AppIcon name="star" :size="20" /></span>
                    <div>
                        <strong>Đặc quyền SportGo dành cho người chơi thường xuyên</strong>
                        <p>Chọn chu kỳ bên dưới để xem đúng mức giá và mức tiết kiệm của từng gói.</p>
                    </div>
                </div>
                <div class="vip-intro__facts">
                    <span><AppIcon name="shieldCheck" :size="15" /> Giá lấy từ cấu hình hệ thống</span>
                    <span><AppIcon name="calendar" :size="15" /> Có thể chọn tháng, quý hoặc năm</span>
                </div>
            </section>

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
            <div v-else-if="!paidPackages.length" class="state">
                Hiện chưa có gói VIP đang mở bán. Vui lòng quay lại sau.
            </div>
            <section v-else class="plan-grid" aria-label="Danh sách gói VIP">
                <article
                    v-for="pkg in paidPackages"
                    :key="pkg.id"
                    class="plan-card"
                    :class="[
                        `plan-${pkg.type}`,
                        { current: isCurrentPackage(pkg) },
                    ]"
                >
                    <header class="plan-card__header">
                        <div class="plan-card__identity">
                            <span class="plan-card__icon" :class="`plan-card__icon--${pkg.type}`">
                                <AppIcon :name="pkg.type === 'pro' ? 'shieldCheck' : 'star'" :size="19" />
                            </span>
                            <div>
                                <span class="plan-card__eyebrow">{{ packageBadgeText(pkg) }}</span>
                                <h2>{{ pkg.label || pkg.name }}</h2>
                                <p>{{ packageDescription(pkg) }}</p>
                            </div>
                        </div>
                        <div class="plan-card__labels">
                            <span v-if="pkg.type === 'saving'" class="plan-card__recommended">Phổ biến</span>
                            <em v-if="isCurrentPackage(pkg)">Đang dùng</em>
                        </div>
                    </header>

                    <div class="plan-price">
                        <div>
                            <strong>{{ money(pkg.monthly_price) }}</strong>
                            <span>/ tháng</span>
                        </div>
                        <small>Giá tham chiếu theo chu kỳ tháng</small>
                    </div>

                    <div class="plan-card__section-head">
                        <div>
                            <strong>Chọn chu kỳ thanh toán</strong>
                            <span>Thanh toán một lần cho toàn bộ chu kỳ</span>
                        </div>
                        <span v-if="cycleDiscount(pkg, 'yearly') > 0" class="plan-card__saving">
                            Tiết kiệm {{ formatPercent(cycleDiscount(pkg, 'yearly')) }}%/năm
                        </span>
                    </div>

                    <div class="cycle-list">
                        <button
                            v-for="cycle in pkg.available_cycles"
                            :key="cycle.key"
                            class="cycle-option"
                            type="button"
                            :disabled="
                                !canPurchasePackage(pkg) || Boolean(subscribing)
                            "
                            @click="openConfirm(pkg, cycle)"
                        >
                            <span class="cycle-option__name">
                                <strong>{{ cycleLabel(cycle) }}</strong>
                                <small v-if="cycleDiscount(pkg, cycle.key) > 0">Giảm {{ formatPercent(cycleDiscount(pkg, cycle.key)) }}%</small>
                            </span>
                            <span class="cycle-option__price">
                                <strong>{{ money(cycle.price) }}</strong>
                                <small>{{ money(cycleUnitPrice(cycle)) }}/tháng</small>
                            </span>
                        </button>
                    </div>

                    <div class="plan-card__features">
                        <div class="plan-card__section-head plan-card__section-head--features">
                            <strong>Quyền lợi trong gói</strong>
                            <span>{{ packageFeatures(pkg).length }} quyền lợi</span>
                        </div>
                        <ul>
                            <li v-for="feature in packageFeatures(pkg)" :key="feature.key">
                                <span class="plan-feature__icon"><AppIcon name="check" :size="14" /></span>
                                <span><strong>{{ feature.value }}</strong><small>{{ feature.label }}</small></span>
                            </li>
                        </ul>
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
import AppIcon from "../../components/AppIcon.vue";
import { vipMembershipService } from "../../services/vipMembershipService.js";

export default {
    name: "VipMembership",
    components: { AppIcon, PublicNavbar },
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
        packageDescription(pkg) {
            if (pkg.type === "saving") return "Cân bằng chi phí và quyền lợi cho người chơi đều đặn.";
            if (pkg.type === "pro") return "Tối đa đặc quyền cho người chơi và cộng đồng giao lưu.";
            return "Quyền lợi cơ bản để bắt đầu hành trình cùng SportGo.";
        },
        packageFeatures(pkg) {
            const cashback = Number(pkg.cashback_percent || 0);
            const postLimit = Number(pkg.match_post_limit_per_month || 0);
            const voucherCount = Number(pkg.voucher_count_per_month || 0);
            const voucherDiscount = Number(pkg.voucher_discount_percent || 0);
            const voucherMax = Number(pkg.voucher_max_discount_amount || 0);
            const voucherMin = Number(pkg.voucher_min_order_amount || 0);

            return [
                {
                    key: "cashback",
                    value: cashback > 0 ? `${this.formatPercent(cashback)}% cashback` : "Không có cashback",
                    label: "Hoàn tiền sau booking hoàn tất",
                },
                {
                    key: "matchmaking",
                    value: this.postLimitText(postLimit),
                    label: "Đăng bài tuyển giao lưu mỗi tháng",
                },
                {
                    key: "voucher-count",
                    value: voucherCount > 0 ? `${voucherCount} voucher VIP/tháng` : "Không có voucher VIP",
                    label: "Voucher được phát theo chu kỳ hệ thống",
                },
                {
                    key: "voucher-value",
                    value: voucherDiscount > 0 ? `Giảm ${this.formatPercent(voucherDiscount)}%` : "Không áp dụng giảm voucher",
                    label: voucherDiscount > 0 && voucherMax > 0
                        ? `Tối đa ${this.money(voucherMax)} · đơn từ ${this.money(voucherMin)}`
                        : "Ưu đãi voucher theo cấu hình gói",
                },
                {
                    key: "complaint",
                    value: pkg.priority_complaint ? "Ưu tiên xử lý" : "Xử lý tiêu chuẩn",
                    label: "Khiếu nại và hỗ trợ từ SportGo",
                },
            ];
        },
        cycleDiscount(pkg, cycle) {
            return Number(pkg.pricing_discounts?.[cycle] || 0);
        },
        cycleUnitPrice(cycle) {
            const months = Number(cycle.months || 1);
            return Number(cycle.price || 0) / Math.max(months, 1);
        },
        formatPercent(value) {
            return Number(value || 0).toLocaleString("vi-VN", { maximumFractionDigits: 2 });
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

<style scoped>
.vip-shell {
  min-height: 100vh;
  background:
    radial-gradient(circle at 8% 0%, rgba(220, 252, 231, 0.68), transparent 28rem),
    #f6faf7;
  color: #14231b;
}

.vip-page {
  max-width: 1240px;
  padding: 34px 24px 72px;
}

.page-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 22px;
}

.page-head-copy > p {
  margin: 0 0 7px;
  color: #15803d;
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.page-head-copy h1 {
  margin: 0;
  color: #102219;
  font-size: clamp(27px, 3vw, 42px);
  font-weight: 720;
  letter-spacing: -0.04em;
  line-height: 1.06;
}

.page-head-copy span {
  display: block;
  max-width: 630px;
  margin-top: 10px;
  color: #647b6b;
  font-size: 14px;
  line-height: 1.6;
}

.head-actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 9px;
}

.back-btn,
.link-btn,
.ghost-btn,
.confirm-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 14px;
  border-radius: 9px;
  font: inherit;
  font-size: 12.5px;
  font-weight: 650;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.back-btn,
.link-btn,
.ghost-btn {
  border: 1px solid #d6e5da;
  background: #ffffff;
  color: #355242;
}

.back-btn:hover,
.link-btn:hover,
.ghost-btn:hover {
  border-color: #a5cdb0;
  background: #f7fcf8;
}

.alert {
  margin-bottom: 14px;
  padding: 12px 15px;
  border-radius: 10px;
  font-size: 13px;
  line-height: 1.45;
}

.alert.error {
  border: 1px solid #fecaca;
  background: #fff7f7;
  color: #b91c1c;
}

.alert.success {
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
  color: #15803d;
}

.alert.info {
  border: 1px solid #cbe4d2;
  background: #effaf1;
  color: #256d3d;
}

.vip-intro {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 18px;
  padding: 18px 20px;
  border: 1px solid #cfe7d5;
  border-radius: 15px;
  background: linear-gradient(125deg, #f2fcf4 0%, #fbfefb 100%);
}

.vip-intro__lead {
  display: flex;
  align-items: center;
  gap: 12px;
}

.vip-intro__icon,
.plan-card__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: #dcf4e2;
  color: #15803d;
}

.vip-intro__lead strong {
  display: block;
  color: #1c3b27;
  font-size: 14px;
  font-weight: 700;
}

.vip-intro__lead p {
  margin: 4px 0 0;
  color: #64816e;
  font-size: 12px;
}

.vip-intro__facts {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  color: #5e7667;
  font-size: 11.5px;
  white-space: nowrap;
}

.vip-intro__facts span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.current-plan {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1px;
  margin-bottom: 18px;
  overflow: hidden;
  border: 1px solid #c8e3cf;
  border-radius: 14px;
  background: #c8e3cf;
}

.current-plan > div {
  display: flex;
  min-height: 82px;
  flex-direction: column;
  justify-content: center;
  gap: 5px;
  padding: 14px 18px;
  background: #f7fff8;
}

.current-plan span {
  color: #668170;
  font-size: 11px;
  font-weight: 600;
}

.current-plan strong {
  color: #166534;
  font-size: 17px;
  font-weight: 720;
}

.payment-panel {
  display: grid;
  grid-template-columns: 190px minmax(0, 1fr);
  gap: 18px;
  align-items: center;
  margin-bottom: 18px;
  padding: 18px;
  border: 1px solid #f2d99a;
  border-radius: 15px;
  background: #fffaf0;
}

.payment-copy {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.payment-copy > span {
  color: #a16207;
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.payment-copy > strong {
  color: #7c4a06;
  font-size: 23px;
  font-weight: 750;
}

.payment-copy small {
  color: #8a6d36;
  font-size: 11.5px;
  line-height: 1.5;
}

.payment-panel img {
  width: 150px;
  height: 150px;
  justify-self: center;
  border: 7px solid #ffffff;
  border-radius: 9px;
  box-shadow: 0 5px 14px rgba(124, 74, 6, 0.12);
}

.payment-details {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.payment-details > div {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 5px;
  padding: 11px 12px;
  border: 1px solid #f1dfb9;
  border-radius: 9px;
  background: rgba(255, 255, 255, 0.7);
}

.payment-details small {
  color: #9a7a45;
  font-size: 10.5px;
}

.payment-details strong,
.payment-details button {
  overflow: hidden;
  padding: 0;
  border: 0;
  background: transparent;
  color: #66420b;
  font: inherit;
  font-size: 12.5px;
  font-weight: 700;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.payment-details button {
  cursor: pointer;
}

.payment-details button:hover {
  color: #a16207;
  text-decoration: underline;
}

.state {
  padding: 42px 20px;
  border: 1px dashed #cddfd1;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.7);
  color: #668170;
  font-size: 13px;
  text-align: center;
}

.plan-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  align-items: start;
}

.plan-card {
  display: flex;
  min-width: 0;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #dbe8de;
  border-radius: 17px;
  background: #ffffff;
  box-shadow: 0 8px 24px rgba(20, 59, 32, 0.055);
}

.plan-card.current {
  border-color: #47a65c;
  box-shadow: 0 0 0 3px rgba(71, 166, 92, 0.13), 0 10px 26px rgba(20, 59, 32, 0.07);
}

.plan-card.plan-pro {
  border-color: #b8cce9;
  background: linear-gradient(180deg, #fbfdff 0%, #ffffff 38%);
}

.plan-card.plan-pro .plan-card__icon {
  background: #e1ebfb;
  color: #2563a8;
}

.plan-card.plan-saving {
  border-color: #b9e2c4;
  background: linear-gradient(180deg, #fbfffc 0%, #ffffff 38%);
}

.plan-card__header {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  padding: 20px 20px 14px;
}

.plan-card__identity {
  display: flex;
  min-width: 0;
  align-items: flex-start;
  gap: 11px;
}

.plan-card__icon {
  width: 36px;
  height: 36px;
}

.plan-card__eyebrow {
  display: block;
  margin-bottom: 4px;
  color: #15803d;
  font-size: 10px;
  font-weight: 750;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.plan-card__header h2 {
  margin: 0;
  color: #102219;
  font-size: 21px;
  font-weight: 720;
}

.plan-card__header p {
  margin: 5px 0 0;
  color: #6b8172;
  font-size: 12px;
  line-height: 1.5;
}

.plan-card__labels {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;
}

.plan-card__recommended,
.plan-card__labels em {
  padding: 5px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-style: normal;
  font-weight: 700;
  white-space: nowrap;
}

.plan-card__recommended {
  background: #fff4d6;
  color: #a16207;
}

.plan-card__labels em {
  background: #e4f6e8;
  color: #15803d;
}

.plan-price {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0 20px 15px;
  padding-bottom: 15px;
  border-bottom: 1px solid #edf2ee;
}

.plan-price > div {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.plan-price strong {
  color: #0f5130;
  font-size: 25px;
  font-weight: 750;
}

.plan-price span {
  color: #789181;
  font-size: 11px;
}

.plan-price small {
  color: #81958a;
  font-size: 10.5px;
}

.plan-card__section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 0 20px 10px;
}

.plan-card__section-head > div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.plan-card__section-head strong {
  color: #273f30;
  font-size: 12px;
}

.plan-card__section-head span {
  color: #7b9182;
  font-size: 10.5px;
}

.plan-card__section-head--features {
  padding: 0 0 10px;
}

.plan-card__saving {
  flex-shrink: 0;
  color: #15803d !important;
  font-size: 10.5px !important;
  font-weight: 700;
  text-align: right;
}

.cycle-list {
  display: flex;
  flex-direction: column;
  gap: 7px;
  padding: 0 20px;
}

.cycle-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 58px;
  padding: 10px 12px;
  border: 1px solid #dfeae2;
  border-radius: 10px;
  background: #fbfdfb;
  color: #263f2e;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.cycle-option:hover:not(:disabled) {
  border-color: #77bd88;
  background: #f4fcf5;
  box-shadow: 0 4px 11px rgba(21, 128, 61, 0.08);
  transform: translateY(-1px);
}

.cycle-option:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.cycle-option__name,
.cycle-option__price {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.cycle-option__name strong {
  font-size: 12px;
}

.cycle-option__name small {
  color: #15803d;
  font-size: 10.5px;
  font-weight: 650;
}

.cycle-option__price {
  align-items: flex-end;
}

.cycle-option__price strong {
  color: #0f5130;
  font-size: 14px;
}

.cycle-option__price small {
  color: #81958a;
  font-size: 10px;
}

.plan-card__features {
  margin: 17px 20px 20px;
  padding-top: 16px;
  border-top: 1px solid #edf2ee;
}

.plan-card__features > .plan-card__section-head > span {
  white-space: nowrap;
}

.plan-card__features ul {
  display: flex;
  flex-direction: column;
  gap: 9px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.plan-card__features li {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  color: #566d5d;
  font-size: 11.5px;
  line-height: 1.4;
}

.plan-card__features li > span:last-child {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}

.plan-card__features li strong {
  color: #31523b;
  font-size: 11.5px;
}

.plan-card__features li small {
  color: #82968a;
  font-size: 10.5px;
}

.plan-feature__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #e8f7eb;
  color: #15803d;
}

.confirm-backdrop {
  position: fixed;
  z-index: 50;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
  background: rgba(13, 33, 21, 0.48);
  backdrop-filter: blur(3px);
}

.confirm-modal {
  width: min(100%, 440px);
  padding: 22px;
  border: 1px solid #d8e7db;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 18px 55px rgba(15, 23, 42, 0.22);
}

.confirm-modal header > span {
  color: #15803d;
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.confirm-modal h2 {
  margin: 6px 0 18px;
  color: #102219;
  font-size: 23px;
}

.confirm-summary {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 9px;
}

.confirm-summary > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 11px;
  border-radius: 9px;
  background: #f5faf6;
}

.confirm-summary small {
  color: #789181;
  font-size: 10.5px;
}

.confirm-summary strong {
  color: #166534;
  font-size: 14px;
}

.confirm-modal > p {
  margin: 16px 0;
  color: #647b6b;
  font-size: 12.5px;
  line-height: 1.55;
}

.confirm-modal footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.confirm-btn {
  border: 1px solid #15803d;
  background: #15803d;
  color: #ffffff;
}

.confirm-btn:hover:not(:disabled) {
  background: #166534;
  box-shadow: 0 5px 13px rgba(21, 128, 61, 0.18);
  transform: translateY(-1px);
}

.confirm-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

@media (max-width: 840px) {
  .page-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .head-actions {
    width: 100%;
  }

  .head-actions > * {
    flex: 1;
  }

  .vip-intro {
    align-items: flex-start;
    flex-direction: column;
  }

  .vip-intro__facts {
    align-items: flex-start;
  }

  .payment-panel {
    grid-template-columns: 160px minmax(0, 1fr);
  }

  .payment-details {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 680px) {
  .vip-page {
    padding: 24px 16px 48px;
  }

  .vip-intro__lead {
    align-items: flex-start;
  }

  .current-plan {
    grid-template-columns: 1fr;
  }

  .current-plan > div {
    min-height: 0;
    padding: 12px 15px;
  }

  .payment-panel {
    grid-template-columns: 1fr;
  }

  .payment-panel img {
    order: -1;
  }

  .plan-grid {
    grid-template-columns: 1fr;
  }

  .plan-card__header {
    padding: 17px 16px 13px;
  }

  .plan-price,
  .cycle-list {
    margin-inline: 16px;
  }

  .cycle-list {
    padding-inline: 0;
  }

  .plan-card__section-head {
    padding-inline: 16px;
  }

  .plan-card__features {
    margin-inline: 16px;
  }

  .plan-card__labels {
    display: none;
  }
}
</style>

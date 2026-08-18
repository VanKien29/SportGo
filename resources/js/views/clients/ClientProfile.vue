<template>
  <div class="cp-page">
    <PublicNavbar />

    <main class="cp-main">
      <div class="wallet-layout-grid">
        <ClientAccountNav />

        <div class="w2-white-content">
        <!-- USER IDENTITY BANNER (FRAMELESS) -->
        <div class="cp-identity">
          <div class="cp-avatar-wrap">
            <span class="cp-avatar">{{ userInitial }}</span>
          </div>

          <div class="cp-identity-info">
            <div class="cp-name-row">
              <h2>{{ formData.fullName || "Người dùng SportGo" }}</h2>
              <span class="cp-role-tag">{{ roleLabel }}</span>
              <ClientAuthorBadges :badges="profileBadges" />
            </div>
            <p class="cp-email-text">{{ user?.email }}<span v-if="user?.phone"> · SĐT: {{ user.phone }}</span></p>
            <span class="cp-verify-text">Trạng thái: {{ user?.email_verified_at ? "Đã xác thực Email" : "Tài khoản hoạt động" }}</span>
          </div>

          <div class="cp-identity-actions">
            <router-link to="/vip-membership" class="cp-btn cp-btn--outline">
              <span>Quyền lợi VIP</span>
            </router-link>
          </div>
        </div>

        <!-- METRICS OVERVIEW ROW -->
        <div class="cp-metrics-row">
          <router-link to="/bookings" class="cp-metric-card">
            <span class="cp-metric-label">Lịch đặt đã tạo</span>
            <strong class="cp-metric-val">{{ bookingCount }} đơn</strong>
            <span class="cp-metric-link">Xem tất cả lịch đặt</span>
          </router-link>

          <router-link to="/wallet" class="cp-metric-card">
            <span class="cp-metric-label">Số dư ví SportGo</span>
            <strong class="cp-metric-val cp-metric-val--green">{{ formatCurrency(walletBalance) }}</strong>
            <span class="cp-metric-link">Nạp tiền & Xem ví</span>
          </router-link>

          <router-link to="/vip-membership" class="cp-metric-card">
            <span class="cp-metric-label">Hạng thành viên</span>
            <strong class="cp-metric-val">{{ membershipLabel }}</strong>
            <span class="cp-metric-link">Khám phá ưu đãi</span>
          </router-link>
        </div>

        <section class="cp-account-overview" aria-labelledby="cp-account-overview-title">
          <div class="cp-account-overview-head">
            <div>
              <span class="cp-section-kicker">TỔNG QUAN QUYỀN LỢI</span>
              <h3 id="cp-account-overview-title">Gói & chi phí của bạn</h3>
            </div>
            <router-link to="/vip-membership" class="cp-text-link">Xem ưu đãi VIP →</router-link>
          </div>

          <div class="cp-account-overview-grid">
            <article class="cp-account-summary-card cp-account-summary-card--vip">
              <span class="cp-summary-label">Gói SportGo VIP</span>
              <strong>{{ vipPackageLabel }}</strong>
              <p>{{ vipSubscription ? "Hiệu lực đến " + formatDate(vipSubscription.expires_at) : "Chưa đăng ký gói trả phí" }}</p>
              <router-link v-if="!vipSubscription" to="/vip-membership" class="cp-summary-action">Khám phá gói phù hợp</router-link>
            </article>

            <article class="cp-account-summary-card">
              <span class="cp-summary-label">Chi tiêu tại sân</span>
              <strong>{{ formatCurrency(venueSpendTotal) }}</strong>
              <p>{{ venueMemberships.length ? venueMemberships.length + " cụm sân đang theo dõi hạng" : "Chưa có lịch sử tích lũy theo sân" }}</p>
            </article>

            <article class="cp-account-summary-card">
              <span class="cp-summary-label">Số dư có thể dùng</span>
              <strong>{{ formatCurrency(walletBalance) }}</strong>
              <p>Đang khóa: {{ formatCurrency(walletLockedBalance) }}</p>
              <router-link to="/wallet" class="cp-summary-action">Mở Ví SportGo</router-link>
            </article>
          </div>

          <div v-if="venueMemberships.length" class="cp-venue-memberships">
            <div class="cp-venue-memberships-head">
              <div>
                <strong>Hội viên theo từng sân</strong>
                <span>Hạng được tính riêng theo lịch đặt và chi tiêu tại mỗi cụm sân.</span>
              </div>
              <span class="cp-venue-memberships-count">{{ venueMemberships.length }} cụm sân</span>
            </div>
            <div class="cp-venue-membership-list">
              <router-link
                v-for="membership in venueMemberships"
                :key="membership.venue_cluster_id"
                class="cp-venue-membership-item"
                :to="{ name: 'venue-detail', params: { id: membership.venue_cluster_id }, query: { tab: 'membership' } }"
              >
                <div class="cp-venue-membership-copy">
                  <strong>{{ membership.venue_name || "Cụm sân SportGo" }}</strong>
                  <span>{{ membership.tier?.label || membership.tier?.tier_label || "Thường" }} · {{ formatPercent(membership.tier?.discount_percent) }}% ưu đãi</span>
                </div>
                <div class="cp-venue-membership-stats">
                  <strong>{{ formatCurrency(membership.total_spend_amount || membership.total_spent) }}</strong>
                  <span>{{ Number(membership.completed_bookings || membership.total_bookings || 0) }} lượt hoàn tất</span>
                </div>
                <span class="cp-venue-membership-arrow">→</span>
              </router-link>
            </div>
          </div>
        </section>

        <!-- 2-COLUMN PROFILE DETAILS & EDIT FORM -->
        <div class="cp-grid">
          <!-- LEFT COLUMN: EDIT FORM -->
          <div class="cp-col-main">
            <h3 class="cp-sec-title">Chỉnh sửa thông tin cá nhân</h3>

            <form class="cp-form" @submit.prevent="handleSaveProfile">
              <div class="cp-form-group">
                <label for="fullName">Họ và tên</label>
                <input
                  id="fullName"
                  v-model.trim="formData.fullName"
                  type="text"
                  placeholder="Nhập họ và tên đầy đủ"
                  required
                />
              </div>

              <div class="cp-form-row">
                <div class="cp-form-group">
                  <label for="phone">
                    Số điện thoại
                    <span v-if="formData.phone !== user?.phone" class="cp-phone-notice">(Cần xác minh OTP)</span>
                  </label>
                  <input
                    id="phone"
                    v-model.trim="formData.phone"
                    type="tel"
                    placeholder="Ví dụ: 0988888888"
                  />
                </div>

                <div class="cp-form-group">
                  <label for="email">
                    Địa chỉ Email
                    <span v-if="formData.email !== user?.email" class="cp-phone-notice">(Cần xác minh OTP)</span>
                  </label>
                  <input
                    id="email"
                    v-model.trim="formData.email"
                    type="email"
                    placeholder="Nhập địa chỉ email"
                    required
                  />
                </div>
              </div>

              <div class="cp-form-group">
                <label>Môn thể thao yêu thích</label>
                <div class="cp-sports-options">
                  <label
                    v-for="sport in sportsList"
                    :key="sport.id"
                    class="cp-checkbox-label"
                    :class="{ 'is-selected': formData.sports.includes(sport.id) }"
                  >
                    <input
                      v-model="formData.sports"
                      type="checkbox"
                      :value="sport.id"
                    />
                    <span>{{ sport.name }}</span>
                  </label>
                </div>
              </div>

              <div class="cp-form-group">
                <label for="bio">Ghi chú cá nhân / Giới thiệu</label>
                <textarea
                  id="bio"
                  v-model.trim="formData.bio"
                  rows="3"
                  placeholder="Ví dụ: Thích đánh cầu lông buổi tối, tìm kèo bóng đá giao hữu..."
                ></textarea>
              </div>

              <div v-if="saveMessage" class="cp-alert" :class="saveStatusClass">
                {{ saveMessage }}
              </div>

              <div class="cp-form-actions">
                <button type="submit" class="cp-btn cp-btn--primary" :disabled="saving">
                  <span>{{ saving ? "Đang lưu..." : "Lưu thay đổi" }}</span>
                </button>
              </div>
            </form>
          </div>

          <!-- RIGHT COLUMN: QUICK UTILITIES & PARTNER PORTAL -->
          <div class="cp-col-side">
            <h3 class="cp-sec-title">Tài khoản & Tiện ích</h3>

            <div class="cp-side-list">
              <div class="cp-side-item">
                <div>
                  <strong>Vai trò tài khoản</strong>
                  <p>{{ roleLabel }}</p>
                </div>
              </div>

              <div class="cp-side-item">
                <div>
                  <strong>Tên đăng nhập</strong>
                  <p>{{ user?.username || user?.email || "Chưa thiết lập" }}</p>
                </div>
              </div>

              <div class="cp-side-item">
                <div>
                  <strong>Bảo mật & Mật khẩu</strong>
                  <p>Cập nhật mật khẩu định kỳ để bảo vệ tài khoản</p>
                </div>
                <button type="button" class="cp-btn cp-btn--outline" @click="openPasswordModal">
                  Đổi mật khẩu
                </button>
              </div>

              <div v-if="user?.role === 'owner'" class="cp-partner-banner cp-owner-banner">
                <h4>Quản lý cụm sân</h4>
                <p>Chuyển sang khu vực vận hành để quản lý sân, lịch đặt và doanh thu.</p>
                <router-link to="/owner/dashboard" class="cp-btn cp-btn--primary cp-btn--full">
                  <span>Vào trang chủ sân</span>
                </router-link>
              </div>

              <div v-else-if="!['admin', 'owner'].includes(user?.role)" class="cp-partner-banner">
                <h4>Kinh doanh sân thể thao?</h4>
                <p>Đăng ký đối tác với SportGo để đăng tải cụm sân và tự động hóa quản lý lịch đặt.</p>
                <router-link to="/become-partner" class="cp-btn cp-btn--primary cp-btn--full">
                  <span>Đăng ký Chủ sân</span>
                </router-link>
              </div>

              <div class="cp-support-box">
                <p>Cần hỗ trợ sự cố giao dịch hoặc phản ánh dịch vụ?</p>
                <router-link to="/complaints" class="cp-btn cp-btn--outline cp-btn--full">
                  <span>Gửi yêu cầu hỗ trợ</span>
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>
      </div>
    </main>

    <!-- EMAIL OTP VERIFICATION MODAL -->
    <Teleport to="body">
      <div v-if="showEmailOtpModal" class="cp-modal-backdrop" @click.self="closeEmailOtpModal">
        <div class="cp-modal">
          <div class="cp-modal-head">
            <h3>Xác minh địa chỉ Email mới</h3>
            <button type="button" class="cp-modal-close" @click="closeEmailOtpModal">✕</button>
          </div>

          <div class="cp-modal-body">
            <p class="cp-modal-desc">
              Hệ thống đã gửi mã xác thực OTP 6 chữ số đến địa chỉ Email mới: <strong>{{ pendingNewEmail }}</strong>.
            </p>

            <div class="cp-otp-demo-badge">
              <span>Mã OTP dùng thử: <strong>123456</strong></span>
            </div>

            <div class="cp-form-group">
              <label for="emailOtpInput">Nhập mã OTP (6 chữ số)</label>
              <input
                id="emailOtpInput"
                v-model.trim="emailOtpInput"
                type="text"
                maxlength="6"
                placeholder="Ví dụ: 123456"
                class="cp-otp-input"
                autofocus
              />
            </div>

            <div v-if="emailOtpError" class="cp-alert cp-alert--error">
              {{ emailOtpError }}
            </div>

            <div class="cp-resend-row">
              <span v-if="emailOtpCountdown > 0">Gửi lại mã sau {{ emailOtpCountdown }}s</span>
              <button
                v-else
                type="button"
                class="cp-resend-btn"
                @click="sendEmailOtp"
              >
                Gửi lại mã OTP
              </button>
            </div>
          </div>

          <div class="cp-modal-foot">
            <button type="button" class="cp-btn cp-btn--outline" @click="closeEmailOtpModal">Hủy bỏ</button>
            <button
              type="button"
              class="cp-btn cp-btn--primary"
              :disabled="emailOtpInput.length !== 6 || emailOtpVerifying"
              @click="verifyEmailOtp"
            >
              <span>{{ emailOtpVerifying ? "Đang xác thực..." : "Xác nhận & Cập nhật Email" }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- PHONE OTP VERIFICATION MODAL -->
    <Teleport to="body">
      <div v-if="showPhoneOtpModal" class="cp-modal-backdrop" @click.self="closeOtpModal">
        <div class="cp-modal">
          <div class="cp-modal-head">
            <h3>Xác minh số điện thoại mới</h3>
            <button type="button" class="cp-modal-close" @click="closeOtpModal">✕</button>
          </div>

          <div class="cp-modal-body">
            <p class="cp-modal-desc">
              Hệ thống đã gửi mã xác thực OTP 6 chữ số đến số điện thoại mới: <strong>{{ pendingNewPhone }}</strong> và email của bạn.
            </p>

            <div class="cp-otp-demo-badge">
              <span>Mã OTP dùng thử: <strong>123456</strong></span>
            </div>

            <div class="cp-form-group">
              <label for="otpInput">Nhập mã OTP (6 chữ số)</label>
              <input
                id="otpInput"
                v-model.trim="otpInput"
                type="text"
                maxlength="6"
                placeholder="Ví dụ: 123456"
                class="cp-otp-input"
                autofocus
              />
            </div>

            <div v-if="otpError" class="cp-alert cp-alert--error">
              {{ otpError }}
            </div>

            <div class="cp-resend-row">
              <span v-if="otpCountdown > 0">Gửi lại mã sau {{ otpCountdown }}s</span>
              <button
                v-else
                type="button"
                class="cp-resend-btn"
                @click="sendPhoneOtp"
              >
                Gửi lại mã OTP
              </button>
            </div>
          </div>

          <div class="cp-modal-foot">
            <button type="button" class="cp-btn cp-btn--outline" @click="closeOtpModal">Hủy bỏ</button>
            <button
              type="button"
              class="cp-btn cp-btn--primary"
              :disabled="otpInput.length !== 6 || otpVerifying"
              @click="verifyPhoneOtp"
            >
              <span>{{ otpVerifying ? "Đang xác thực..." : "Xác nhận & Cập nhật" }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- PASSWORD CHANGE MODAL -->
    <Teleport to="body">
      <div v-if="showPasswordModal" class="cp-modal-backdrop" @click.self="closePasswordModal">
        <div class="cp-modal">
          <div class="cp-modal-head">
            <h3>Đổi mật khẩu tài khoản</h3>
            <button type="button" class="cp-modal-close" @click="closePasswordModal">✕</button>
          </div>

          <form @submit.prevent="submitPasswordChange">
            <div class="cp-modal-body">
              <p class="cp-modal-desc">
                Nhập mật khẩu hiện tại và mật khẩu mới để bảo vệ an toàn cho tài khoản của bạn.
              </p>

              <div class="cp-form-group">
                <label for="currentPassword">Mật khẩu hiện tại</label>
                <input
                  id="currentPassword"
                  v-model="pwdData.current_password"
                  type="password"
                  placeholder="Nhập mật khẩu đang dùng"
                  required
                />
              </div>

              <div class="cp-form-group">
                <label for="newPassword">Mật khẩu mới (tối thiểu 8 ký tự)</label>
                <input
                  id="newPassword"
                  v-model="pwdData.password"
                  type="password"
                  placeholder="Nhập mật khẩu mới"
                  required
                  minlength="8"
                />
              </div>

              <div class="cp-form-group">
                <label for="confirmPassword">Xác nhận mật khẩu mới</label>
                <input
                  id="confirmPassword"
                  v-model="pwdData.password_confirmation"
                  type="password"
                  placeholder="Nhập lại mật khẩu mới"
                  required
                />
              </div>

              <div v-if="pwdError" class="cp-alert cp-alert--error">
                {{ pwdError }}
              </div>

              <div v-if="pwdSuccess" class="cp-alert cp-alert--success">
                {{ pwdSuccess }}
              </div>
            </div>

            <div class="cp-modal-foot">
              <button type="button" class="cp-btn cp-btn--outline" @click="closePasswordModal">Hủy bỏ</button>
              <button
                type="submit"
                class="cp-btn cp-btn--primary"
                :disabled="pwdSaving"
              >
                <span>{{ pwdSaving ? "Đang xử lý..." : "Lưu mật khẩu mới" }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import ClientAccountNav from "../../components/ClientAccountNav.vue";
import ClientAuthorBadges from "../../components/ClientAuthorBadges.vue";
import { authService } from "../../services/authService.js";
import { bookingService } from "../../services/bookingService.js";
import { courtTypeService } from "../../services/courtTypes.js";
import { getAuth, saveAuth } from "../../stores/auth.js";

const MAX_PROFILE_SPORTS = 5;
const PROFILE_SPORT_PRIORITY = [
  "badminton",
  "football",
  "pickleball",
  "tennis",
  "basketball",
  "volleyball",
];
const FALLBACK_SPORTS = [
  { id: "badminton", name: "Cầu lông" },
  { id: "football", name: "Bóng đá" },
  { id: "pickleball", name: "Pickleball" },
  { id: "tennis", name: "Tennis" },
  { id: "basketball", name: "Bóng rổ" },
];

function sportIdFromName(name) {
  const normalizedName = String(name || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/Đ/g, "D")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");

  const aliases = {
    "cau-long": "badminton",
    "bong-da": "football",
    pickleball: "pickleball",
    tennis: "tennis",
    "bong-ro": "basketball",
    "bong-chuyen": "volleyball",
  };

  return aliases[normalizedName] || normalizedName;
}

export default {
  name: "ClientProfile",
  components: { PublicNavbar, ClientAccountNav, ClientAuthorBadges },
  data() {
    const user = getAuth();
    return {
      user,
      bookingCount: 0,
      walletBalance: 0,
      walletLockedBalance: 0,
      membershipLabel: user?.membership_tier?.tier?.label || user?.membership_tier?.tier?.tier_label || "Thường",
      saving: false,
      saveMessage: "",
      saveStatusClass: "",
      formData: {
        fullName: user?.fullName || "",
        email: user?.email || "",
        phone: user?.phone || "",
        bio: user?.bio || "",
        sports: Array.isArray(user?.sports) && user.sports.length
          ? user.sports.map((sport) => String(typeof sport === "object" ? sport.id || sport.code || sport.name : sport))
          : (Array.isArray(user?.preferred_sports) && user.preferred_sports.length
            ? user.preferred_sports.map((sport) => String(sport))
            : ["badminton", "football"]),
      },
      courtTypes: [],
      // EMAIL OTP VERIFICATION STATE
      showEmailOtpModal: false,
      pendingNewEmail: "",
      emailOtpInput: "",
      emailOtpVerifying: false,
      emailOtpError: "",
      emailOtpCountdown: 0,
      emailOtpTimer: null,
      // PHONE OTP VERIFICATION STATE
      showPhoneOtpModal: false,
      pendingNewPhone: "",
      otpInput: "",
      otpVerifying: false,
      otpError: "",
      otpCountdown: 0,
      otpTimer: null,
      // PASSWORD CHANGE MODAL STATE
      showPasswordModal: false,
      pwdData: {
        current_password: "",
        password: "",
        password_confirmation: "",
      },
      pwdSaving: false,
      pwdError: "",
      pwdSuccess: "",
    };
  },
  computed: {
    userInitial() {
      return this.formData.fullName?.trim()?.charAt(0)?.toUpperCase() || "S";
    },
    profileBadges() {
      const vipPackage = this.user?.vip_subscription?.package;
      const tier = this.user?.membership_tier?.tier;

      return {
        vip: vipPackage
          ? {
              type: vipPackage.type,
              label: this.user?.vip_subscription?.badge?.label || vipPackage.badge_name || "VIP SportGo",
              icon: vipPackage.type === "pro" ? "shieldCheck" : vipPackage.type === "saving" ? "sparkles" : "star",
            }
          : null,
        venue_membership: tier
          ? {
              tier_key: tier.tier_key || tier.tier || "standard",
              label: tier.label || tier.tier_label || "Hội viên sân",
              venue_name: this.user?.membership_tier?.venue_name || "",
              discount_percent: tier.discount_percent || 0,
              icon: tier.tier_key === "diamond"
                ? "sparkles"
                : tier.tier_key === "gold"
                  ? "crown"
                  : tier.tier_key === "silver"
                    ? "star"
                    : "shieldCheck",
            }
          : null,
      };
    },
    roleLabel() {
      return this.user?.role === "owner"
        ? "Chủ sân"
        : this.user?.role === "admin"
          ? "Quản trị viên"
          : this.user?.role === "staff"
            ? "Nhân viên sân"
            : "Người chơi";
    },
    sportsList() {
      const dynamicSports = this.courtTypes
        .filter((type) => type?.is_active !== false && !type.parent_id)
        .map((type) => ({
          id: sportIdFromName(type.name),
          name: type.name,
        }))
        .sort((a, b) => {
          const aIndex = PROFILE_SPORT_PRIORITY.indexOf(a.id);
          const bIndex = PROFILE_SPORT_PRIORITY.indexOf(b.id);
          return (aIndex === -1 ? Number.MAX_SAFE_INTEGER : aIndex)
            - (bIndex === -1 ? Number.MAX_SAFE_INTEGER : bIndex);
        })
        .slice(0, MAX_PROFILE_SPORTS);

      return dynamicSports.length ? dynamicSports : FALLBACK_SPORTS;
    },
    vipSubscription() {
      return this.user?.vip_subscription || null;
    },
    vipPackageLabel() {
      return this.vipSubscription?.package?.label || this.vipSubscription?.package?.name || "Chưa đăng ký";
    },
    venueMemberships() {
      return Array.isArray(this.user?.venue_memberships) ? this.user.venue_memberships : [];
    },
    venueSpendTotal() {
      return this.venueMemberships.reduce((sum, membership) => sum + Number(membership.total_spend_amount ?? membership.total_spent ?? 0), 0);
    },
  },
  created() {
    if (!this.user) {
      this.$router.replace({ name: "login", query: { redirect: this.$route.fullPath } });
      return;
    }
    this.refreshAccountData();
    this.loadOverview();
    this.loadCourtTypes();
  },
  beforeUnmount() {
    if (this.otpTimer) clearInterval(this.otpTimer);
    if (this.emailOtpTimer) clearInterval(this.emailOtpTimer);
  },
  methods: {
    async refreshAccountData() {
      try {
        const payload = await authService.me();
        this.user = saveAuth(payload);
        this.formData.fullName = this.user?.fullName || this.formData.fullName;
        this.formData.email = this.user?.email || this.formData.email;
        this.formData.phone = this.user?.phone || "";
        this.formData.bio = this.user?.bio || "";
        this.formData.sports = Array.isArray(this.user?.preferred_sports) && this.user.preferred_sports.length
          ? this.user.preferred_sports.map((sport) => String(sport))
          : this.formData.sports;
        this.membershipLabel = this.user?.membership_tier?.tier?.label
          || this.user?.membership_tier?.tier?.tier_label
          || "Thường";
      } catch (error) {
        console.warn("Không thể làm mới quyền lợi tài khoản", error);
      }
    },
    async loadCourtTypes() {
      try {
        const courtTypes = await courtTypeService.getCourtTypes();
        this.courtTypes = Array.isArray(courtTypes) ? courtTypes : [];
      } catch (error) {
        this.courtTypes = [];
        console.warn("Không thể tải danh sách môn thể thao", error);
      }
    },
    async loadOverview() {
      try {
        const [bookingsResponse, walletResponse] = await Promise.allSettled([
          bookingService.listBookings({ limit: 1 }),
          bookingService.getWallet(),
        ]);
        if (bookingsResponse.status === "fulfilled") {
          const payload = bookingsResponse.value?.data;
          this.bookingCount = Number(payload?.meta?.total ?? payload?.total ?? (Array.isArray(payload) ? payload.length : 0));
        }
        if (walletResponse.status === "fulfilled") {
          const payload = walletResponse.value?.data || walletResponse.value || {};
          this.walletBalance = Number(payload.balance ?? payload.wallet?.balance ?? 0);
          this.walletLockedBalance = Number(payload.locked_balance ?? payload.wallet?.locked_balance ?? 0);
        }
      } catch (error) {
        console.warn("Không thể tải thông tin ví", error);
      }
    },
    handleSaveProfile() {
      const isEmailChanged = Boolean(
        this.formData.email &&
        this.formData.email.trim() !== (this.user?.email || "").trim()
      );

      const isPhoneChanged = Boolean(
        this.formData.phone &&
        this.formData.phone.trim() !== (this.user?.phone || "").trim()
      );

      if (isEmailChanged) {
        this.pendingNewEmail = this.formData.email.trim();
        this.showEmailOtpModal = true;
        this.sendEmailOtp();
      } else if (isPhoneChanged) {
        this.pendingNewPhone = this.formData.phone.trim();
        this.showPhoneOtpModal = true;
        this.sendPhoneOtp();
      } else {
        this.executeSaveProfile(this.user?.email || "", this.user?.phone || "");
      }
    },
    // EMAIL OTP METHODS
    sendEmailOtp() {
      this.emailOtpInput = "";
      this.emailOtpError = "";
      this.emailOtpCountdown = 60;
      if (this.emailOtpTimer) clearInterval(this.emailOtpTimer);
      this.emailOtpTimer = setInterval(() => {
        if (this.emailOtpCountdown > 0) {
          this.emailOtpCountdown--;
        } else {
          clearInterval(this.emailOtpTimer);
        }
      }, 1000);
    },
    async verifyEmailOtp() {
      this.emailOtpVerifying = true;
      this.emailOtpError = "";

      setTimeout(() => {
        if (this.emailOtpInput === "123456" || this.emailOtpInput.length === 6) {
          this.showEmailOtpModal = false;
          const isPhoneChanged = Boolean(
            this.formData.phone &&
            this.formData.phone.trim() !== (this.user?.phone || "").trim()
          );
          if (isPhoneChanged) {
            this.pendingNewPhone = this.formData.phone.trim();
            this.showPhoneOtpModal = true;
            this.sendPhoneOtp();
          } else {
            this.executeSaveProfile(this.pendingNewEmail, this.user?.phone || "");
          }
        } else {
          this.emailOtpError = "Mã OTP không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.";
        }
        this.emailOtpVerifying = false;
      }, 600);
    },
    closeEmailOtpModal() {
      this.showEmailOtpModal = false;
      this.emailOtpInput = "";
      this.emailOtpError = "";
      if (this.emailOtpTimer) clearInterval(this.emailOtpTimer);
    },
    // PHONE OTP METHODS
    sendPhoneOtp() {
      this.otpInput = "";
      this.otpError = "";
      this.otpCountdown = 60;
      if (this.otpTimer) clearInterval(this.otpTimer);
      this.otpTimer = setInterval(() => {
        if (this.otpCountdown > 0) {
          this.otpCountdown--;
        } else {
          clearInterval(this.otpTimer);
        }
      }, 1000);
    },
    async verifyPhoneOtp() {
      this.otpVerifying = true;
      this.otpError = "";

      setTimeout(() => {
        if (this.otpInput === "123456" || this.otpInput.length === 6) {
          this.showPhoneOtpModal = false;
          this.executeSaveProfile(
            this.pendingNewEmail || this.user?.email || "",
            this.pendingNewPhone
          );
        } else {
          this.otpError = "Mã OTP không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.";
        }
        this.otpVerifying = false;
      }, 600);
    },
    closeOtpModal() {
      this.showPhoneOtpModal = false;
      this.otpInput = "";
      this.otpError = "";
      if (this.otpTimer) clearInterval(this.otpTimer);
    },
    async executeSaveProfile(finalEmail, finalPhone) {
      this.saving = true;
      this.saveMessage = "";
      try {
        const payload = new FormData();
        payload.append("full_name", this.formData.fullName.trim());
        payload.append("email", finalEmail || "");
        payload.append("phone", finalPhone || "");
        payload.append("bio", this.formData.bio || "");
        this.formData.sports.forEach((sport) => payload.append("preferred_sports[]", sport));

        const response = await authService.updateProfile(payload);
        const currentAuth = getAuth();
        this.user = saveAuth({
          ...currentAuth,
          user: {
            ...(currentAuth?.user || {}),
            ...(response?.user || {}),
          },
        });
        this.formData.email = this.user?.email || finalEmail;
        this.formData.phone = this.user?.phone || finalPhone;
        this.formData.bio = this.user?.bio || this.formData.bio;
        this.formData.sports = Array.isArray(this.user?.preferred_sports)
          ? this.user.preferred_sports.map((sport) => String(sport))
          : this.formData.sports;
        this.saveStatusClass = "cp-alert--success";
        this.saveMessage = response?.message || "Thông tin hồ sơ đã được cập nhật thành công.";
      } catch (err) {
        this.saveStatusClass = "cp-alert--error";
        this.saveMessage = err.message || "Không thể cập nhật thông tin.";
      } finally {
        this.saving = false;
      }
    },
    // PASSWORD CHANGE MODAL METHODS
    openPasswordModal() {
      this.showPasswordModal = true;
      this.pwdData = { current_password: "", password: "", password_confirmation: "" };
      this.pwdError = "";
      this.pwdSuccess = "";
    },
    closePasswordModal() {
      this.showPasswordModal = false;
      this.pwdError = "";
      this.pwdSuccess = "";
    },
    async submitPasswordChange() {
      if (this.pwdData.password !== this.pwdData.password_confirmation) {
        this.pwdError = "Xác nhận mật khẩu mới không trùng khớp.";
        return;
      }
      this.pwdSaving = true;
      this.pwdError = "";
      this.pwdSuccess = "";
      try {
        const res = await authService.changePassword(this.pwdData);
        this.pwdSuccess = res.message || "Đổi mật khẩu thành công!";
        setTimeout(() => {
          this.closePasswordModal();
        }, 1500);
      } catch (err) {
        this.pwdError = err.message || "Mật khẩu hiện tại không đúng hoặc mật khẩu không đủ độ dài.";
      } finally {
        this.pwdSaving = false;
      }
    },
    formatCurrency(value) {
      return `${new Intl.NumberFormat("vi-VN").format(Number(value || 0))} đ`;
    },
    formatPercent(value) {
      return Number(value || 0).toLocaleString("vi-VN", { maximumFractionDigits: 2 });
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString("vi-VN") : "-";
    },
  },
};
</script>

<style scoped>
.cp-page {
  min-height: 100vh;
  background: #f7faf8;
}

.cp-main {
  max-width: 1860px !important;
  width: 100% !important;
  margin: 0 auto !important;
  padding: 32px 36px 72px !important;
}

.wallet-layout-grid {
  display: flex;
  gap: 28px;
  align-items: flex-start;
  width: 100%;
}

.w2-white-content {
  flex: 1;
  min-width: 0;
  padding: 30px 32px 34px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* BUTTONS */
.cp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 4px;
  cursor: pointer;
  text-decoration: none;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
  transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.cp-btn:hover:not(:disabled) {
  border-color: #94a3b8;
  background: #f8fafc;
}

.cp-btn:focus-visible {
  outline: 3px solid rgba(21, 128, 61, 0.2);
  outline-offset: 2px;
}

.cp-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.cp-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.cp-btn--primary:hover:not(:disabled) {
  background: #166534;
  border-color: #166534;
  box-shadow: 0 4px 10px rgba(21, 128, 61, 0.18);
}

.cp-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}

.cp-btn--full {
  width: 100%;
}

/* MAIN BODY */
.cp-body {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* TOP IDENTITY */
.cp-identity {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 0 0 24px;
  border-bottom: 1px solid #e2e8f0;
  flex-wrap: wrap;
}

.cp-avatar-wrap {
  flex-shrink: 0;
}

.cp-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #15803d;
  color: #ffffff;
  font-size: 22px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cp-identity-info {
  flex: 1;
  min-width: 220px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cp-name-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.cp-name-row h2 {
  font-size: 19px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.cp-role-tag {
  font-size: 12.5px;
  color: #15803d;
  font-weight: 500;
}

.cp-email-text {
  font-size: 13.5px;
  color: #1e293b;
  margin: 0;
}

.cp-verify-text {
  font-size: 12.5px;
  color: #15803d;
  font-weight: 500;
}

.cp-identity-actions {
  margin-left: auto;
  display: flex;
  align-items: center;
}

/* METRICS ROW */
.cp-metrics-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  padding-bottom: 24px;
  border-bottom: 1px solid #e2e8f0;
}

@media (max-width: 650px) {
  .cp-metrics-row {
    grid-template-columns: 1fr;
  }
}

.cp-metric-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  padding: 16px 18px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  text-decoration: none;
  transition: border-color 0.18s ease, background-color 0.18s ease, transform 0.18s ease;
}

.cp-metric-card:hover {
  border-color: #bbf7d0;
  background: #f0fdf4;
  transform: translateY(-1px);
}

.cp-metric-label {
  font-size: 12.5px;
  color: #1e293b;
}

.cp-metric-val {
  font-size: 20px;
  font-weight: 500;
  color: #0f172a;
}

.cp-metric-val--green {
  color: #15803d;
}

.cp-metric-link {
  font-size: 12px;
  color: #15803d;
  font-weight: 500;
  margin-top: 4px;
}

/* ACCOUNT BENEFITS & COST OVERVIEW */
.cp-account-overview {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border: 1px solid #dcebe0;
  border-radius: 14px;
  background: linear-gradient(135deg, #fbfffc 0%, #f3faf5 100%);
}

.cp-account-overview-head,
.cp-venue-memberships-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.cp-section-kicker {
  display: block;
  margin-bottom: 5px;
  color: #15803d;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
}

.cp-account-overview h3 {
  margin: 0;
  color: #0f172a;
  font-size: 17px;
  font-weight: 600;
}

.cp-text-link,
.cp-summary-action {
  color: #15803d;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
}

.cp-text-link:hover,
.cp-summary-action:hover {
  color: #166534;
  text-decoration: underline;
}

.cp-account-overview-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.cp-account-summary-card {
  display: flex;
  min-height: 128px;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  padding: 16px;
  border: 1px solid #e0ebe3;
  border-radius: 11px;
  background: rgba(255, 255, 255, 0.86);
}

.cp-account-summary-card--vip {
  border-color: #b9e2c4;
  background: #f8fff9;
}

.cp-summary-label {
  color: #64748b;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.cp-account-summary-card > strong {
  color: #0f172a;
  font-size: 19px;
  font-weight: 650;
}

.cp-account-summary-card p {
  min-height: 30px;
  margin: 0;
  color: #64748b;
  font-size: 12px;
  line-height: 1.5;
}

.cp-summary-action {
  margin-top: auto;
}

.cp-venue-memberships {
  padding-top: 16px;
  border-top: 1px solid #dcebe0;
}

.cp-venue-memberships-head {
  align-items: center;
  margin-bottom: 10px;
}

.cp-venue-memberships-head div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.cp-venue-memberships-head strong {
  color: #0f172a;
  font-size: 13.5px;
}

.cp-venue-memberships-head span {
  color: #64748b;
  font-size: 11.5px;
}

.cp-venue-memberships-count {
  flex-shrink: 0;
  padding: 5px 9px;
  border-radius: 999px;
  background: #e8f6eb;
  color: #15803d !important;
  font-size: 11px !important;
  font-weight: 650;
}

.cp-venue-membership-list {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.cp-venue-membership-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 16px;
  padding: 11px 12px;
  border: 1px solid #e1ebe3;
  border-radius: 9px;
  background: #ffffff;
  color: inherit;
  text-decoration: none;
  transition: border-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
}

.cp-venue-membership-item:hover {
  border-color: #9ed3aa;
  box-shadow: 0 5px 14px rgba(21, 128, 61, 0.08);
  transform: translateY(-1px);
}

.cp-venue-membership-copy,
.cp-venue-membership-stats {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 3px;
}

.cp-venue-membership-copy strong,
.cp-venue-membership-stats strong {
  overflow: hidden;
  color: #0f172a;
  font-size: 12.5px;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cp-venue-membership-copy span,
.cp-venue-membership-stats span {
  color: #64748b;
  font-size: 11px;
}

.cp-venue-membership-stats {
  align-items: flex-end;
}

.cp-venue-membership-arrow {
  color: #15803d;
  font-size: 18px;
  line-height: 1;
}

@media (max-width: 850px) {
  .cp-account-overview-grid {
    grid-template-columns: 1fr;
  }

  .cp-account-summary-card {
    min-height: 0;
  }
}

@media (max-width: 620px) {
  .cp-account-overview {
    padding: 16px;
  }

  .cp-account-overview-head,
  .cp-venue-memberships-head {
    flex-direction: column;
  }

  .cp-venue-membership-item {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .cp-venue-membership-stats {
    grid-column: 1 / 2;
    align-items: flex-start;
  }

  .cp-venue-membership-arrow {
    grid-column: 2;
    grid-row: 1 / span 2;
  }
}

/* 2-COLUMN GRID */
.cp-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 36px;
  padding-top: 2px;
}

@media (max-width: 850px) {
  .cp-grid {
    grid-template-columns: 1fr;
  }
}

.cp-sec-title {
  font-size: 15.5px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 16px;
}

/* FORM */
.cp-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cp-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 550px) {
  .cp-form-row {
    grid-template-columns: 1fr;
  }
}

.cp-form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cp-form-group label {
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
}

.cp-phone-notice {
  font-size: 11.5px;
  color: #15803d;
  font-weight: 400;
}

.cp-form-group input,
.cp-form-group textarea {
  padding: 8px 12px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #ffffff;
  color: #0f172a;
  outline: none;
  font-family: inherit;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.cp-form-group input:focus,
.cp-form-group textarea:focus {
  border-color: #15803d;
  box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.1);
}

.cp-form-group input.is-disabled {
  background: #f8fafc;
  color: #475569;
  cursor: not-allowed;
}

.cp-sports-options {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding: 12px;
  border: 1px solid #e2ebe4;
  border-radius: 10px;
  background: #fbfdfb;
}

.cp-checkbox-label {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  padding: 9px 13px;
  border: 1px solid #d6e3d9;
  border-radius: 9px;
  background: #ffffff;
  font-size: 13px;
  color: #334155;
  cursor: pointer;
  user-select: none;
  transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
}

.cp-checkbox-label::before {
  content: "";
  width: 18px;
  height: 18px;
  flex: 0 0 18px;
  border: 1.5px solid #a8b8ad;
  border-radius: 5px;
  background: #ffffff;
  transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.cp-checkbox-label::after {
  content: "";
  position: absolute;
  top: 50%;
  left: 22px;
  width: 8px;
  height: 5px;
  border-bottom: 2px solid #ffffff;
  border-left: 2px solid #ffffff;
  transform: translate(-50%, -65%) rotate(-45deg) scale(0);
  transition: transform 0.16s ease;
}

.cp-checkbox-label:hover {
  border-color: #8fc9a1;
  background: #f7fcf8;
}

.cp-checkbox-label:focus-within {
  border-color: #159447;
  box-shadow: 0 0 0 3px rgba(21, 148, 71, 0.14);
}

.cp-checkbox-label.is-selected {
  border-color: #8fc9a1;
  background: #f0faf3;
  color: #0d7137;
}

.cp-checkbox-label.is-selected::before {
  border-color: #159447;
  background: #159447;
  box-shadow: 0 2px 5px rgba(21, 148, 71, 0.2);
}

.cp-checkbox-label.is-selected::after {
  transform: translate(-50%, -65%) rotate(-45deg) scale(1);
}

.cp-checkbox-label input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.cp-alert {
  padding: 10px 14px;
  font-size: 13px;
  border-radius: 4px;
}

.cp-alert--success {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.cp-alert--error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.cp-form-actions {
  margin-top: 8px;
  padding-top: 18px;
  border-top: 1px solid #e2e8f0;
}

/* SIDEBAR UTILITIES */
.cp-side-list {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.cp-side-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 0 0 18px;
  margin-bottom: 18px;
  border-bottom: 1px solid #e2e8f0;
}

.cp-side-item strong {
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 500;
}

.cp-side-item p {
  font-size: 13px;
  color: #1e293b;
  margin: 2px 0 0;
}

.cp-partner-banner {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 18px;
}

.cp-partner-banner h4 {
  margin: 0;
  font-size: 14px;
  color: #0f172a;
  font-weight: 500;
}

.cp-partner-banner p {
  margin: 0;
  font-size: 12.5px;
  color: #1e293b;
}

.cp-support-box p {
  font-size: 13px;
  color: #1e293b;
  margin: 0 0 8px;
}

.cp-support-box {
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
}

@media (max-width: 1080px) {
  .cp-main {
    padding-inline: 24px !important;
  }

  .wallet-layout-grid {
    gap: 20px;
  }

  .w2-white-content {
    padding-inline: 24px;
  }

  .cp-grid {
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 24px;
  }
}

@media (max-width: 850px) {
  .wallet-layout-grid {
    flex-direction: column;
  }

  .wallet-layout-grid :deep(.an-sidebar) {
    width: 100%;
  }

  .w2-white-content {
    width: 100%;
  }
}

@media (max-width: 620px) {
  .cp-main {
    padding: 20px 16px 48px !important;
  }

  .w2-white-content {
    padding: 22px 18px 26px;
    border-radius: 10px;
  }

  .cp-identity-actions {
    width: 100%;
    margin-left: 0;
  }

  .cp-identity-actions .cp-btn {
    width: 100%;
  }

  .cp-grid {
    grid-template-columns: 1fr;
    gap: 28px;
  }
}

/* MODAL OTP & PASSWORD STYLES */
.cp-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 16px;
}

.cp-modal {
  background: #ffffff;
  border-radius: 6px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.cp-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px 8px;
  border-bottom: none;
}

.cp-modal-head h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.cp-modal-close {
  background: transparent;
  border: none;
  font-size: 16px;
  color: #64748b;
  cursor: pointer;
}

.cp-modal-body {
  padding: 12px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cp-modal-desc {
  font-size: 13px;
  color: #1e293b;
  margin: 0;
  line-height: 1.5;
}

.cp-otp-demo-badge {
  background: #ffffff;
  border: 1px dashed #cbd5e1;
  border-radius: 4px;
  padding: 8px 12px;
  font-size: 12.5px;
  color: #15803d;
}

.cp-otp-input {
  letter-spacing: 4px;
  font-size: 18px !important;
  font-weight: 600;
  text-align: center;
}

.cp-resend-row {
  font-size: 12.5px;
  color: #64748b;
  text-align: right;
}

.cp-resend-btn {
  background: transparent;
  border: none;
  color: #15803d;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
}

.cp-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 8px 20px 20px;
  border-top: none;
  background: #ffffff;
}
</style>

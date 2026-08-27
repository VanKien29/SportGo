<template>
  <div class="w2-white-content">
    <!-- PAGE HEADER -->
          <div class="sg3-page-head">
            <div>
              <p class="sg3-kicker">Quản lý tài khoản</p>
              <h1 class="page-head-title">Hồ sơ cá nhân</h1>
              <p class="page-head-desc">Quản lý thông tin tài khoản và cài đặt bảo mật.</p>
            </div>
            <div class="sg3-head-actions">
              <router-link to="/vip-membership" class="w2-btn w2-btn--outline">
                <AppIcon name="crown" :size="15" />
                <span>Quyền lợi VIP</span>
              </router-link>
            </div>
          </div>

          <div class="cp-profile-shell">
            <div class="cp-profile-primary">
          <!-- USER IDENTITY (SEAMLESS) -->
          <div class="cp-identity-strip">
            <div class="cp-avatar-wrap">
              <div class="cp-avatar">
                <img v-if="avatarSrc" :src="avatarSrc" :alt="formData.fullName" />
                <span v-else>{{ userInitial }}</span>
              </div>
              <button
                type="button"
                class="cp-avatar-camera-btn"
                title="Đổi ảnh đại diện"
                :disabled="uploadingAvatar"
                @click="triggerAvatarPick"
              >
                <AppIcon name="camera" :size="12" />
              </button>
              <input
                ref="avatarFileInput"
                type="file"
                accept="image/png,image/jpeg,image/webp,image/jpg"
                style="display: none !important;"
                @change="handleAvatarUpload"
              />
            </div>

            <div class="cp-identity-body">
              <div class="cp-name-row">
                <h2>{{ formData.fullName || "Người dùng SportGo" }}</h2>
                <span class="cp-role-badge">{{ roleLabel }}</span>
                <ClientAuthorBadges :badges="profileBadges" />
              </div>
              <p class="cp-meta-text">
                <span>{{ user?.email }}</span>
                <span v-if="user?.phone"> · {{ user.phone }}</span>
                <span> · {{ user?.email_verified_at ? "Đã xác thực Email" : "Tài khoản đang hoạt động" }}</span>
              </p>
            </div>
          </div>

          <!-- QUICK METRIC STRIP (INLINE SPACING, NO BORDERS) -->
          <div class="cp-stat-strip">
            <router-link to="/bookings" class="cp-stat-item">
              <span class="cp-stat-label">Lịch đặt sân</span>
              <strong class="cp-stat-val">{{ bookingCount }} đơn</strong>
              <span class="cp-stat-sub">Xem lịch sử →</span>
            </router-link>

            <router-link to="/wallet" class="cp-stat-item">
              <span class="cp-stat-label">Số dư ví SportGo</span>
              <strong class="cp-stat-val cp-stat-val--green">{{ formatCurrency(walletBalance) }}</strong>
              <span class="cp-stat-sub">Quản lý ví →</span>
            </router-link>

            <router-link to="/vip-membership" class="cp-stat-item">
              <span class="cp-stat-label">Gói VIP / Hạng</span>
              <strong class="cp-stat-val">{{ vipPackageLabel !== 'Chưa đăng ký' ? vipPackageLabel : membershipLabel }}</strong>
              <span class="cp-stat-sub">{{ vipSubscription ? 'Đến ' + formatDate(vipSubscription.expires_at) : 'Khám phá ưu đãi →' }}</span>
            </router-link>
          </div>

          <!-- VENUE MEMBERSHIPS (ONLY IF USER HAS ACCUMULATED VENUE TIERS) -->
          <div v-if="venueMemberships.length" class="cp-venue-section">
            <div class="cp-section-title-row">
              <h3 class="cp-title-sm">Hội viên tại các cụm sân</h3>
              <span class="cp-count-badge">{{ venueMemberships.length }} sân</span>
            </div>
            <div class="cp-venue-list">
              <router-link
                v-for="membership in venueMemberships"
                :key="membership.venue_cluster_id"
                class="cp-venue-item"
                :to="{ name: 'venue-detail', params: { id: membership.venue_cluster_id }, query: { tab: 'membership' } }"
              >
                <div class="cp-venue-col">
                  <strong>{{ membership.venue_name || "Cụm sân SportGo" }}</strong>
                  <span>Hạng {{ membership.tier?.label || membership.tier?.tier_label || "Thường" }} · Giảm {{ formatPercent(membership.tier?.discount_percent) }}%</span>
                </div>
                <div class="cp-venue-stats">
                  <strong>{{ formatCurrency(membership.total_spend_amount || membership.total_spent) }}</strong>
                  <span>{{ Number(membership.completed_bookings || membership.total_bookings || 0) }} lượt đặt</span>
                </div>
                <span class="cp-arrow">→</span>
              </router-link>
            </div>
          </div>

          <!-- PROFILE FORM -->
            <div class="cp-form-column">
              <div class="cp-col-head">
                <h3 class="cp-title-sm">Thông tin cá nhân</h3>
                <p class="cp-desc-sm">Cập nhật họ tên và thông tin liên hệ.</p>
              </div>

              <form class="cp-form" @submit.prevent="handleSaveProfile">
                <div class="cp-field">
                  <label for="fullName">Họ và tên <span class="cp-req">*</span></label>
                  <input
                    id="fullName"
                    v-model.trim="formData.fullName"
                    type="text"
                    placeholder="Nhập họ và tên đầy đủ"
                    class="w2-input"
                    required
                  />
                </div>

                <div class="cp-field-row">
                  <div class="cp-field">
                    <label for="phone">
                      Số điện thoại
                    </label>
                    <input
                      id="phone"
                      v-model.trim="formData.phone"
                      type="tel"
                      placeholder="Ví dụ: 0988888888"
                      class="w2-input"
                    />
                  </div>

                  <div class="cp-field">
                    <label for="email">
                      Địa chỉ Email <span class="cp-req">*</span>
                      <span
                        v-if="(formData.email || '').trim().toLowerCase() !== (user?.email || '').trim().toLowerCase()"
                        class="cp-hint-opt"
                      >(Cần OTP)</span>
                    </label>
                    <input
                      id="email"
                      v-model.trim="formData.email"
                      type="email"
                      placeholder="Nhập địa chỉ email"
                      class="w2-input"
                      required
                    />
                  </div>
                </div>

                <div class="cp-field">
                  <label for="bio">Giới thiệu bản thân</label>
                  <textarea
                    id="bio"
                    v-model.trim="formData.bio"
                    rows="3"
                    placeholder="Ví dụ: Thích đánh cầu lông buổi tối, tìm kèo giao lưu thể thao..."
                    class="w2-input cp-textarea"
                  ></textarea>
                </div>

                <div v-if="saveMessage" class="cp-alert-banner" :class="saveStatusClass">
                  {{ saveMessage }}
                </div>

                <div class="cp-submit-row">
                  <button type="submit" class="w2-btn w2-btn--primary" :disabled="saving">
                    <AppIcon v-if="saving" name="loader" :size="15" class="spin" />
                    <span>{{ saving ? "Đang lưu..." : "Lưu thay đổi" }}</span>
                  </button>
                </div>
              </form>
            </div>

            </div>

            <!-- RIGHT: ACCOUNT & SECURITY UTILITIES -->
            <div class="cp-side-column">
              <div class="cp-col-head">
                <h3 class="cp-title-sm">Tài khoản &amp; Bảo mật</h3>
                <p class="cp-desc-sm">Thông tin phân quyền và quản lý tài khoản.</p>
              </div>

              <div class="cp-flat-list">
                <div class="cp-flat-item">
                  <span class="cp-flat-label">Vai trò tài khoản</span>
                  <span class="cp-flat-val">{{ roleLabel }}</span>
                </div>

                <div class="cp-flat-item">
                  <span class="cp-flat-label">Tên đăng nhập</span>
                  <span class="cp-flat-val">{{ user?.username || user?.email || "Chưa thiết lập" }}</span>
                </div>

                <div class="cp-flat-item cp-flat-item--action">
                  <div>
                    <span class="cp-flat-label">Mật khẩu đăng nhập</span>
                    <p class="cp-flat-sub">Đổi mật khẩu định kỳ để an toàn</p>
                  </div>
                  <button type="button" class="w2-btn w2-btn--outline w2-btn--sm" @click="openPasswordModal">
                    <AppIcon name="lock" :size="13" />
                    <span>Đổi mật khẩu</span>
                  </button>
                </div>

                <div v-if="user?.role === 'owner'" class="cp-flat-box">
                  <strong>Quản lý cụm sân</strong>
                  <p>Chuyển sang trang vận hành sân và lịch đặt.</p>
                  <router-link to="/owner/dashboard" class="w2-btn w2-btn--primary w2-btn--sm cp-btn-block">
                    <span>Vào trang chủ sân →</span>
                  </router-link>
                </div>

                <div v-else-if="!['admin', 'owner'].includes(user?.role)" class="cp-flat-box">
                  <strong>Đối tác cụm sân</strong>
                  <p>Đăng ký trở thành đối tác kinh doanh sân thể thao với SportGo.</p>
                  <router-link to="/become-partner" class="w2-btn w2-btn--outline w2-btn--sm cp-btn-block">
                    <span>Đăng ký Chủ sân →</span>
                  </router-link>
                </div>

                <div class="cp-flat-box">
                  <strong>Hỗ trợ &amp; Khiếu nại</strong>
                  <p>Cần hỗ trợ sự cố giao dịch hoặc phản ánh dịch vụ?</p>
                  <router-link to="/complaints" class="w2-btn w2-btn--outline w2-btn--sm cp-btn-block">
                    <span>Gửi yêu cầu hỗ trợ</span>
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>

    <!-- EMAIL OTP VERIFICATION MODAL -->
    <Teleport to="body">
      <div v-if="showEmailOtpModal" class="w2-modal-backdrop" @click.self="closeEmailOtpModal">
        <div class="w2-modal">
          <div class="w2-modal-head">
            <h3>Xác minh địa chỉ Email mới</h3>
            <button type="button" class="w2-modal-close" @click="closeEmailOtpModal">✕</button>
          </div>

          <div class="w2-modal-body">
            <p class="w2-modal-desc">
              Hệ thống đã gửi mã OTP 6 chữ số đến địa chỉ Email mới: <strong>{{ pendingNewEmail }}</strong>.
            </p>

            <div class="w2-otp-badge">
              <span>Kiểm tra hộp thư đến hoặc mục thư rác của email mới.</span>
            </div>

            <div class="cp-field">
              <label for="emailOtpInput">Nhập mã OTP (6 chữ số)</label>
              <input
                id="emailOtpInput"
                v-model.trim="emailOtpInput"
                type="text"
                maxlength="6"
                placeholder="Ví dụ: 123456"
                class="w2-input cp-otp-field"
                autofocus
              />
            </div>

            <div v-if="emailOtpError" class="cp-alert-banner is-error">
              {{ emailOtpError }}
            </div>

            <div class="cp-resend-row">
              <span v-if="emailOtpCountdown > 0">Gửi lại mã sau {{ emailOtpCountdown }}s</span>
              <button
                v-else
                type="button"
                class="cp-link-btn"
                @click="sendEmailOtp"
              >
                Gửi lại mã OTP
              </button>
            </div>
          </div>

          <div class="w2-modal-foot">
            <button type="button" class="w2-btn w2-btn--outline" @click="closeEmailOtpModal">Hủy bỏ</button>
            <button
              type="button"
              class="w2-btn w2-btn--primary"
              :disabled="emailOtpInput.length !== 6 || emailOtpVerifying"
              @click="verifyEmailOtp"
            >
              <span>{{ emailOtpVerifying ? "Đang xác thực..." : "Xác nhận & Cập nhật Email" }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- PASSWORD CHANGE MODAL -->
    <Teleport to="body">
      <div v-if="showPasswordModal" class="w2-modal-backdrop" @click.self="closePasswordModal">
        <div class="w2-modal">
          <div class="w2-modal-head">
            <h3>Đổi mật khẩu tài khoản</h3>
            <button type="button" class="w2-modal-close" @click="closePasswordModal">✕</button>
          </div>

          <form @submit.prevent="submitPasswordChange">
            <div class="w2-modal-body">
              <p class="w2-modal-desc">
                Nhập mật khẩu hiện tại và mật khẩu mới để bảo vệ an toàn cho tài khoản của bạn.
              </p>

              <div class="cp-field">
                <label for="currentPassword">Mật khẩu hiện tại <span class="cp-req">*</span></label>
                <input
                  id="currentPassword"
                  v-model="pwdData.current_password"
                  type="password"
                  placeholder="Nhập mật khẩu đang dùng"
                  class="w2-input"
                  required
                />
              </div>

              <div class="cp-field">
                <label for="newPassword">Mật khẩu mới (tối thiểu 8 ký tự) <span class="cp-req">*</span></label>
                <input
                  id="newPassword"
                  v-model="pwdData.password"
                  type="password"
                  placeholder="Nhập mật khẩu mới"
                  class="w2-input"
                  required
                  minlength="8"
                />
              </div>

              <div class="cp-field">
                <label for="confirmPassword">Xác nhận mật khẩu mới <span class="cp-req">*</span></label>
                <input
                  id="confirmPassword"
                  v-model="pwdData.password_confirmation"
                  type="password"
                  placeholder="Nhập lại mật khẩu mới"
                  class="w2-input"
                  required
                />
              </div>

              <div v-if="pwdError" class="cp-alert-banner is-error">
                {{ pwdError }}
              </div>

              <div v-if="pwdSuccess" class="cp-alert-banner is-success">
                {{ pwdSuccess }}
              </div>
            </div>

            <div class="w2-modal-foot">
              <button type="button" class="w2-btn w2-btn--outline" @click="closePasswordModal">Hủy bỏ</button>
              <button
                type="submit"
                class="w2-btn w2-btn--primary"
                :disabled="pwdSaving"
              >
                <AppIcon v-if="pwdSaving" name="loader" :size="15" class="spin" />
                <span>{{ pwdSaving ? "Đang xử lý..." : "Lưu mật khẩu mới" }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
</template>

<script>
import ClientAuthorBadges from "../../components/ClientAuthorBadges.vue";
import AppIcon from "../../components/AppIcon.vue";
import { authService } from "../../services/authService.js";
import { bookingService } from "../../services/bookingService.js";
import { getAuth, saveAuth } from "../../stores/auth.js";

export default {
  name: "ClientProfile",
  components: { AppIcon, ClientAuthorBadges },
  data() {
    const user = getAuth();
    return {
      user,
      bookingCount: 0,
      walletBalance: 0,
      walletLockedBalance: 0,
      membershipLabel: user?.membership_tier?.tier?.label || user?.membership_tier?.tier?.tier_label || "Thường",
      saving: false,
      uploadingAvatar: false,
      saveMessage: "",
      saveStatusClass: "",
      formData: {
        fullName: user?.fullName || "",
        email: user?.email || "",
        phone: user?.phone || "",
        bio: user?.bio || "",
      },
      // EMAIL OTP VERIFICATION STATE
      showEmailOtpModal: false,
      pendingNewEmail: "",
      emailOtpInput: "",
      emailOtpVerifying: false,
      emailOtpError: "",
      emailOtpCountdown: 0,
      emailOtpTimer: null,
      profileMutationVersion: 0,
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
    avatarSrc() {
      const path = this.user?.avatar_url || this.user?.avatar;
      if (!path) return "";
      if (/^https?:\/\//.test(path) || path.startsWith("/")) return path;
      return `/storage/${path}`;
    },
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
  },
  beforeUnmount() {
    if (this.emailOtpTimer) clearInterval(this.emailOtpTimer);
  },
  methods: {
    async refreshAccountData() {
      const requestVersion = this.profileMutationVersion;
      try {
        const payload = await authService.me("", { dedupe: false });
        if (requestVersion !== this.profileMutationVersion) return;
        this.user = saveAuth(payload);
        this.formData.fullName = this.user?.fullName || this.formData.fullName;
        this.formData.email = this.user?.email || this.formData.email;
        this.formData.phone = this.user?.phone || "";
        this.formData.bio = this.user?.bio || "";
        this.membershipLabel = this.user?.membership_tier?.tier?.label
          || this.user?.membership_tier?.tier?.tier_label
          || "Thường";
      } catch (error) {
        console.warn("Không thể làm mới quyền lợi tài khoản", error);
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
    async handleSaveProfile() {
      const normalizedEmail = (this.formData.email || "").trim().toLowerCase();
      const currentEmail = (this.user?.email || "").trim().toLowerCase();
      const isEmailChanged = Boolean(
        normalizedEmail &&
        normalizedEmail !== currentEmail
      );

      if (isEmailChanged) {
        this.pendingNewEmail = normalizedEmail;
        await this.sendEmailOtp();
      } else {
        await this.executeSaveProfile(this.user?.email || "", this.formData.phone.trim());
      }
    },
    // EMAIL OTP METHODS
    async sendEmailOtp() {
      this.emailOtpInput = "";
      this.emailOtpError = "";
      try {
        await authService.requestEmailChangeOtp(this.pendingNewEmail);
        this.showEmailOtpModal = true;
        this.startEmailOtpCountdown();
      } catch (error) {
        this.saveStatusClass = "is-error";
        this.saveMessage = error.message || "Không thể gửi OTP đến email mới.";
      }
    },
    startEmailOtpCountdown() {
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
      try {
        const response = await authService.verifyEmailChangeOtp(this.pendingNewEmail, this.emailOtpInput);
        this.profileMutationVersion++;
        const currentAuth = getAuth() || {};
        const mergedUser = {
          ...(currentAuth.user || {}),
          ...(response?.user || {}),
        };
        this.user = saveAuth({ ...currentAuth, user: mergedUser });
        this.formData.email = this.user?.email || this.pendingNewEmail;
        this.showEmailOtpModal = false;
        if (this.emailOtpTimer) clearInterval(this.emailOtpTimer);
        await this.executeSaveProfile(this.formData.email, this.formData.phone.trim());
      } catch (error) {
        this.emailOtpError = error.message || "Mã xác thực không hợp lệ. Vui lòng thử lại.";
      } finally {
        this.emailOtpVerifying = false;
      }
    },
    closeEmailOtpModal() {
      this.showEmailOtpModal = false;
      this.formData.email = this.user?.email || "";
      if (this.emailOtpTimer) clearInterval(this.emailOtpTimer);
    },
    // EXECUTE SAVE PROFILE
    async executeSaveProfile(finalEmail, finalPhone) {
      this.saving = true;
      this.profileMutationVersion++;
      this.saveMessage = "";
      try {
        const payload = new FormData();
        payload.append("full_name", String(this.formData.fullName || "").trim());
        payload.append("email", finalEmail || "");
        payload.append("phone", finalPhone || "");
        payload.append("bio", this.formData.bio || "");

        const response = await authService.updateProfile(payload);
        const currentAuth = getAuth() || {};
        const mergedUser = {
          ...(currentAuth.user || {}),
          ...(response?.user || {}),
        };
        this.user = saveAuth({
          ...currentAuth,
          user: mergedUser,
        });
        this.formData.email = this.user?.email || finalEmail;
        this.formData.phone = this.user?.phone || finalPhone;
        this.formData.bio = this.user?.bio || this.formData.bio;
        this.saveStatusClass = "is-success";
        this.saveMessage = response?.message || "Thông tin hồ sơ đã được cập nhật thành công.";
      } catch (err) {
        this.saveStatusClass = "is-error";
        this.saveMessage = err.message || "Không thể cập nhật thông tin.";
      } finally {
        this.saving = false;
      }
      await this.refreshAccountData();
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
    triggerAvatarPick() {
      this.$refs.avatarFileInput?.click();
    },
    async handleAvatarUpload(event) {
      const file = event.target.files?.[0];
      if (!file) return;

      if (file.size > 4 * 1024 * 1024) {
        alert("Ảnh đại diện không được vượt quá 4MB.");
        event.target.value = "";
        return;
      }

      const formData = new FormData();
      formData.append("avatar", file);

      this.uploadingAvatar = true;
      try {
        const res = await api("/api/profile/avatar", {
          method: "POST",
          body: formData,
        });

        const currentAuth = getAuth() || {};
        const updatedUser = {
          ...currentAuth,
          avatar_url: res.avatar_url,
          user: {
            ...(currentAuth.user || {}),
            avatar_url: res.avatar_url,
          },
        };
        saveAuth(updatedUser);
        this.user = updatedUser;
        this.saveStatusClass = "is-success";
        this.saveMessage = "Đã cập nhật ảnh đại diện thành công!";
      } catch (err) {
        this.saveStatusClass = "is-error";
        this.saveMessage = err.message || "Không thể tải lên ảnh đại diện.";
      } finally {
        this.uploadingAvatar = false;
        event.target.value = "";
      }
    },
  },
};
</script>

<style scoped>
* {
  font-weight: 400 !important;
}

.wallet-white-page {
  min-height: 100vh;
  background: #ffffff;
}

.wallet-white-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
  color: #0f172a;
}

.wallet-layout-grid {
  display: flex;
  gap: 32px;
  align-items: flex-start;
  width: 100%;
}

.w2-white-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* PAGE HEADER */
.sg3-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
}

.sg3-kicker {
  font-size: 12px;
  color: #475569;
  letter-spacing: 0.05em;
  margin: 0 0 4px;
}

.page-head-title {
  font-size: 24px;
  color: #0f172a;
  margin: 0 0 6px;
  line-height: 1.2;
}

.page-head-desc {
  font-size: 13.5px;
  color: #475569;
  margin: 0;
}

.sg3-head-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* BUTTONS */
.w2-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 18px;
  font-size: 13.5px;
  font-weight: 600;
  border-radius: 999px;
  cursor: pointer;
  text-decoration: none;
  border: 1.5px solid transparent;
  transition: all 0.15s ease;
}

.w2-btn--sm {
  padding: 6px 14px;
  font-size: 12.5px;
}

.w2-btn--primary {
  background: #54656f;
  color: #ffffff;
  border: none;
  box-shadow: 0 4px 14px rgba(84, 101, 111, 0.25);
}

.w2-btn--primary:hover:not(:disabled) {
  background: #405059;
}

.w2-btn--outline {
  background: #ffffff;
  color: #475569;
  border-color: #cbd5e1;
}

.w2-btn--outline:hover:not(:disabled) {
  border-color: #54656f;
  color: #0f172a;
  background: #f8fafc;
}

.w2-input {
  padding: 9px 14px;
  font-size: 13.5px;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  outline: none;
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
  transition: all 0.15s ease;
}

.w2-input:focus {
  border-color: #54656f;
  box-shadow: 0 0 0 3px rgba(84, 101, 111, 0.12);
}

/* USER IDENTITY (NO BORDER) */
.cp-identity-strip {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
}

.cp-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.cp-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #54656f;
  color: #ffffff;
  font-size: 22px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 0 4px #edf4f0;
  overflow: hidden;
}

.cp-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cp-avatar-camera-btn {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #54656f;
  color: #ffffff;
  border: 2px solid #ffffff;
  display: inline-grid;
  place-items: center;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
}

.cp-avatar-camera-btn:hover {
  background: #405059;
}

.cp-identity-body {
  flex: 1;
  min-width: 220px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.cp-name-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.cp-name-row h2 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.cp-role-badge {
  font-size: 12px;
  font-weight: 600;
  color: #5c7e6e;
  padding: 1px 6px;
  background: #edf4f0;
  border-radius: 4px;
}

.cp-meta-text {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

/* QUICK STAT STRIP (SPACING ONLY, NO BORDER LINES) */
.cp-stat-strip {
  display: flex;
  align-items: flex-start;
  gap: 36px;
  flex-wrap: wrap;
}

.cp-stat-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-decoration: none;
  color: inherit;
  min-width: 140px;
}

.cp-stat-item:hover .cp-stat-sub {
  color: #15803d;
  text-decoration: underline;
}

.cp-stat-label {
  font-size: 12px;
  color: #64748b;
}

.cp-stat-val {
  font-size: 20px;
  color: #0f172a;
  line-height: 1.2;
}

.cp-stat-val--green {
  color: #15803d;
}

.cp-stat-sub {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

/* VENUE SECTION */
.cp-venue-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cp-section-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cp-count-badge {
  font-size: 11px;
  color: #64748b;
  padding: 1px 6px;
}

.cp-venue-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cp-venue-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  background: #ffffff;
  text-decoration: none;
  color: inherit;
}

.cp-venue-item:hover strong {
  color: #15803d;
}

.cp-venue-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cp-venue-col strong {
  font-size: 13px;
  color: #0f172a;
}

.cp-venue-col span {
  font-size: 11.5px;
  color: #64748b;
}

.cp-venue-stats {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.cp-venue-stats strong {
  font-size: 13px;
  color: #0f172a;
}

.cp-venue-stats span {
  font-size: 11px;
  color: #64748b;
}

.cp-arrow {
  color: #15803d;
  font-size: 14px;
  margin-left: 12px;
}

/* PROFILE WORKSPACE */
.cp-profile-shell {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 48px;
  align-items: start;
}

.cp-profile-primary {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.cp-form-column,
.cp-side-column {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.cp-col-head {
  padding-bottom: 2px;
}

.cp-title-sm {
  font-size: 15px;
  color: #0f172a;
  margin: 0 0 3px;
}

.cp-desc-sm {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
}

/* FORM FIELDS */
.cp-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cp-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.cp-field label {
  display: flex !important;
  flex-direction: row !important;
  align-items: center !important;
  gap: 4px !important;
  font-size: 13px;
  color: #0f172a;
}

.cp-req {
  color: #dc2626;
  display: inline-block !important;
  line-height: 1;
}

.cp-hint-opt {
  font-size: 11.5px;
  color: #15803d;
  display: inline-block !important;
  margin-left: 2px;
}

.cp-field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.cp-textarea {
  resize: vertical;
}

.cp-alert-banner {
  padding: 10px 12px;
  font-size: 13px;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
}

.cp-alert-banner.is-success {
  border-color: #15803d;
  color: #15803d;
}

.cp-alert-banner.is-error {
  border-color: #dc2626;
  color: #dc2626;
}

.cp-submit-row {
  padding-top: 4px;
}

/* SIDEBAR FLAT LIST (NO PER-ITEM BORDERS) */
.cp-flat-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.cp-flat-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cp-flat-item--action {
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cp-flat-label {
  font-size: 12px;
  color: #64748b;
}

.cp-flat-val {
  font-size: 13.5px;
  color: #0f172a;
}

.cp-flat-sub {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}

.cp-flat-box {
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 4px;
}

.cp-flat-box strong {
  font-size: 13px;
  color: #0f172a;
}

.cp-flat-box p {
  font-size: 12px;
  color: #64748b;
  margin: 0 0 4px;
  line-height: 1.4;
}

.cp-btn-block {
  width: 100%;
}

/* MODALS */
.w2-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 16px;
}

.w2-modal {
  background: #ffffff;
  border-radius: 6px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.w2-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
}

.w2-modal-head h3 {
  margin: 0;
  font-size: 15px;
  color: #0f172a;
}

.w2-modal-close {
  background: transparent;
  border: none;
  font-size: 16px;
  color: #64748b;
  cursor: pointer;
  padding: 2px 4px;
}

.w2-modal-close:hover {
  color: #0f172a;
}

.w2-modal-body {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.w2-modal-desc {
  font-size: 13px;
  color: #475569;
  margin: 0;
  line-height: 1.45;
}

.w2-otp-badge {
  background: #ffffff;
  border: 1px dashed #cbd5e1;
  border-radius: 4px;
  padding: 8px 12px;
  font-size: 12px;
  color: #15803d;
}

.cp-otp-field {
  letter-spacing: 4px;
  font-size: 18px !important;
  text-align: center;
}

.cp-resend-row {
  font-size: 12px;
  color: #64748b;
  text-align: right;
}

.cp-link-btn {
  background: transparent;
  border: none;
  color: #15803d;
  cursor: pointer;
  padding: 0;
  font-size: 12px;
}

.cp-link-btn:hover {
  text-decoration: underline;
}

.w2-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 18px;
  background: #ffffff;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* RESPONSIVE */
@media (max-width: 960px) {
  .cp-profile-shell {
    grid-template-columns: 1fr;
    gap: 32px;
  }
}

@media (max-width: 768px) {
  .wallet-layout-grid {
    flex-direction: column;
  }

  .wallet-layout-grid :deep(.an-sidebar) {
    width: 100%;
  }

  .w2-white-content {
    width: 100%;
  }

  .sg3-page-head {
    flex-direction: column;
    align-items: flex-start;
  }

  .cp-stat-strip {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .cp-field-row {
    grid-template-columns: 1fr;
  }
}
</style>

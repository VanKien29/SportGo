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
                  <label v-for="sport in sportsList" :key="sport.id" class="cp-checkbox-label">
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
import { authService } from "../../services/authService.js";
import { bookingService } from "../../services/bookingService.js";
import { getAuth, saveAuth } from "../../stores/auth.js";

export default {
  name: "ClientProfile",
  components: { PublicNavbar, ClientAccountNav },
  data() {
    const user = getAuth();
    return {
      user,
      bookingCount: 0,
      walletBalance: 0,
      membershipLabel: "Cơ bản",
      saving: false,
      saveMessage: "",
      saveStatusClass: "",
      formData: {
        fullName: user?.fullName || "",
        email: user?.email || "",
        phone: user?.phone || "",
        bio: user?.bio || "",
        sports: user?.sports || ["badminton", "football"],
      },
      sportsList: [
        { id: "badminton", name: "Cầu lông" },
        { id: "football", name: "Bóng đá" },
        { id: "pickleball", name: "Pickleball" },
        { id: "tennis", name: "Tennis" },
        { id: "basketball", name: "Bóng rổ" },
      ],
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
    roleLabel() {
      return this.user?.role === "owner"
        ? "Chủ sân"
        : this.user?.role === "admin"
          ? "Quản trị viên"
          : this.user?.role === "staff"
            ? "Nhân viên sân"
            : "Người chơi";
    },
  },
  created() {
    if (!this.user) {
      this.$router.replace({ name: "login", query: { redirect: this.$route.fullPath } });
      return;
    }
    this.loadOverview();
  },
  beforeUnmount() {
    if (this.otpTimer) clearInterval(this.otpTimer);
    if (this.emailOtpTimer) clearInterval(this.emailOtpTimer);
  },
  methods: {
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
        const updatedUser = {
          user: {
            ...this.user,
            full_name: this.formData.fullName,
            fullName: this.formData.fullName,
            email: finalEmail,
            phone: finalPhone,
            bio: this.formData.bio,
            sports: this.formData.sports,
          },
        };
        saveAuth(updatedUser);
        this.user = getAuth();
        this.formData.email = finalEmail;
        this.formData.phone = finalPhone;
        this.saveStatusClass = "cp-alert--success";
        this.saveMessage = "Thông tin hồ sơ và địa chỉ đã được xác minh & cập nhật thành công!";
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
  },
};
</script>

<style scoped>
.cp-page {
  min-height: 100vh;
  background: #ffffff;
}

.cp-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
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
}

.cp-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
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
  padding-bottom: 16px;
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
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cp-name-row {
  display: flex;
  align-items: center;
  gap: 10px;
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

/* METRICS ROW */
.cp-metrics-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
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
  padding: 0;
  background: transparent;
  border: none;
  text-decoration: none;
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

/* 2-COLUMN GRID */
.cp-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 36px;
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
}

.cp-form-group input:focus,
.cp-form-group textarea:focus {
  border-color: #15803d;
}

.cp-form-group input.is-disabled {
  background: #f8fafc;
  color: #475569;
  cursor: not-allowed;
}

.cp-sports-options {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.cp-checkbox-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #0f172a;
  cursor: pointer;
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
}

/* SIDEBAR UTILITIES */
.cp-side-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cp-side-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
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
  background: transparent;
  border: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
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

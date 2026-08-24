<template>
  <AuthLayout
    class="sg-account-auth sg-admin-auth"
    :title="titleText"
    :subtitle="subtitleText"
    quote-title="Khôi phục quyền truy cập"
    quote-text="Bảo vệ và lấy lại quyền quản trị tài khoản hệ thống SportGo an toàn."
    :image-src="authVisual"
    back-to="/admin/login"
  >
    <!-- Success Alert -->
    <transition name="fade">
      <div v-if="successMsg" class="flex items-center gap-2.5 p-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 text-xs font-medium mb-3">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 16 16 12 12 8"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        <span>{{ successMsg }}</span>
      </div>
    </transition>

    <!-- Error Alert -->
    <transition name="shake">
      <div v-if="error" class="flex items-center gap-2.5 p-3 rounded-lg border border-red-200 bg-red-50 text-red-600 text-xs font-medium mb-3">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- STEP 1: IDENTIFY -->
    <form v-if="step === 'identify'" @submit.prevent="handleSendOtp" class="sg-lovebirds-form flex flex-col gap-5 w-full text-left" novalidate>
      <div class="sg-lovebirds-field">
        <label for="admin-forgot-identifier" class="sg-lovebirds-label">Tài khoản Quản trị</label>
        <input
          id="admin-forgot-identifier"
          v-model.trim="identifier"
          type="text"
          placeholder="Tên đăng nhập, email hoặc số điện thoại"
          autocomplete="username"
          class="sg-lovebirds-input"
        />
      </div>

      <div class="flex justify-center mt-2">
        <button type="submit" :disabled="isLoading" class="sg-lovebirds-submit-btn">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang gửi mã...' : 'Gửi mã OTP' }}</span>
        </button>
      </div>

      <div class="text-center mt-4 pt-3 border-t border-slate-100">
        <router-link to="/admin/login" class="sg-lovebirds-forgot text-xs">
          ← Quay lại đăng nhập Quản trị
        </router-link>
      </div>
    </form>

    <!-- STEP 2: OTP VERIFICATION -->
    <form v-else-if="step === 'otp'" @submit.prevent="handleVerifyOtp" class="sg-lovebirds-form flex flex-col gap-5 w-full text-left" novalidate>
      <div class="sg-lovebirds-field">
        <label for="admin-forgot-otp" class="sg-lovebirds-label">Mã xác thực OTP (6 chữ số)</label>
        <input
          id="admin-forgot-otp"
          v-model.trim="otp"
          type="text"
          inputmode="numeric"
          maxlength="6"
          placeholder="000000"
          autocomplete="one-time-code"
          class="sg-lovebirds-input text-center tracking-widest font-semibold text-lg"
        />
      </div>

      <div class="flex flex-col items-center gap-3 mt-2">
        <button type="submit" :disabled="isLoading" class="sg-lovebirds-submit-btn w-full">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang xác thực...' : 'Xác nhận mã OTP' }}</span>
        </button>

        <button
          type="button"
          :disabled="isLoading"
          @click.prevent="handleResendOtp"
          class="text-xs font-semibold text-slate-500 hover:text-slate-700 bg-transparent border-0 cursor-pointer transition-colors"
        >
          Chưa nhận được mã? Gửi lại OTP
        </button>
      </div>

      <div class="flex items-center justify-between text-xs mt-4 pt-3 border-t border-slate-100">
        <button
          type="button"
          :disabled="isLoading"
          @click="goBackToIdentify"
          class="text-slate-500 hover:text-slate-800 bg-transparent border-0 cursor-pointer"
        >
          ← Đổi tài khoản khác
        </button>
        <router-link to="/admin/login" class="sg-lovebirds-forgot">
          Hủy bỏ
        </router-link>
      </div>
    </form>

    <!-- STEP 3: RESET PASSWORD -->
    <form v-else @submit.prevent="handleResetPassword" class="sg-lovebirds-form flex flex-col gap-5 w-full text-left" novalidate>
      <div class="sg-lovebirds-field">
        <label for="admin-new-password" class="sg-lovebirds-label">Mật khẩu mới</label>
        <div class="sg-lovebirds-password-wrap">
          <input
            id="admin-new-password"
            v-model="password"
            :type="showNewPassword ? 'text' : 'password'"
            class="sg-lovebirds-input"
            placeholder="Tối thiểu 8 ký tự, 1 hoa, 1 số, 1 ký tự đặc biệt"
            autocomplete="new-password"
          />
          <button
            type="button"
            class="sg-lovebirds-eye"
            @click="showNewPassword = !showNewPassword"
            aria-label="Xem mật khẩu"
          >
            <svg v-if="showNewPassword" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="sg-lovebirds-field">
        <label for="admin-confirm-password" class="sg-lovebirds-label">Xác nhận mật khẩu mới</label>
        <div class="sg-lovebirds-password-wrap">
          <input
            id="admin-confirm-password"
            v-model="passwordConfirmation"
            :type="showConfirmPassword ? 'text' : 'password'"
            class="sg-lovebirds-input"
            placeholder="Nhập lại mật khẩu mới"
            autocomplete="new-password"
          />
          <button
            type="button"
            class="sg-lovebirds-eye"
            @click="showConfirmPassword = !showConfirmPassword"
            aria-label="Xem mật khẩu"
          >
            <svg v-if="showConfirmPassword" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="flex justify-center mt-2">
        <button type="submit" :disabled="isLoading" class="sg-lovebirds-submit-btn">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang cập nhật...' : 'Đặt lại mật khẩu Admin' }}</span>
        </button>
      </div>
    </form>
  </AuthLayout>
</template>

<script>
import { resetAdminPassword, sendAdminForgotOtp, verifyAdminForgotOtp } from '../../stores/auth.js';
import AuthLayout from '../../components/ui/AuthLayout.vue';

export default {
  name: 'AdminForgotPassword',
  components: {
    AuthLayout,
  },
  data() {
    return {
      authVisual: '/images/auth/sportgo_art.png',
      step: 'identify', // identify, otp, reset
      identifier: '',
      otp: '',
      password: '',
      passwordConfirmation: '',
      showNewPassword: false,
      showConfirmPassword: false,
      error: '',
      successMsg: '',
      isLoading: false,
    };
  },
  computed: {
    titleText() {
      return {
        identify: 'Quên mật khẩu Admin',
        otp: 'Xác thực OTP Admin',
        reset: 'Đặt mật khẩu Admin mới',
      }[this.step];
    },
    subtitleText() {
      return {
        identify: 'Nhập tài khoản quản trị để nhận mã xác thực OTP qua email.',
        otp: `Mã OTP đã được gửi đến email đăng ký của tài khoản ${this.identifier}.`,
        reset: 'Nhập mật khẩu quản trị mới của bạn.',
      }[this.step];
    },
  },
  methods: {
    async handleSendOtp() {
      this.error = '';
      this.successMsg = '';

      if (!this.identifier || !this.identifier.trim()) {
        this.error = 'Vui lòng nhập tài khoản quản trị.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await sendAdminForgotOtp(this.identifier);
        this.successMsg = response.message || 'Mã OTP đã được gửi về email quản trị.';
        this.step = 'otp';
      } catch (err) {
        this.error = err.message || 'Không thể gửi mã OTP.';
      } finally {
        this.isLoading = false;
      }
    },
    async handleResendOtp() {
      this.otp = '';
      await this.handleSendOtp();
    },
    async handleVerifyOtp() {
      this.error = '';
      this.successMsg = '';

      if (!this.otp || !this.otp.trim()) {
        this.error = 'Vui lòng nhập mã OTP.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await verifyAdminForgotOtp(this.identifier, this.otp);
        this.successMsg = response.message || 'OTP hợp lệ. Vui lòng đặt mật khẩu mới.';
        this.step = 'reset';
      } catch (err) {
        this.error = err.message || 'Mã OTP không đúng.';
      } finally {
        this.isLoading = false;
      }
    },
    async handleResetPassword() {
      this.error = '';
      this.successMsg = '';

      if (!this.password) {
        this.error = 'Vui lòng nhập mật khẩu mới.';
        return;
      }
      if (this.password.length < 8 || this.password.length > 50) {
        this.error = 'Mật khẩu phải từ 8 đến 50 ký tự.';
        return;
      }
      if (!/[A-Z]/.test(this.password)) {
        this.error = 'Mật khẩu phải chứa ít nhất 1 chữ hoa.';
        return;
      }
      if (!/\d/.test(this.password)) {
        this.error = 'Mật khẩu phải chứa ít nhất 1 chữ số.';
        return;
      }
      if (!/[!@#$%^&*(),.?":{}|<>]/.test(this.password)) {
        this.error = 'Mật khẩu phải chứa ít nhất 1 ký tự đặc biệt.';
        return;
      }
      if (!this.passwordConfirmation) {
        this.error = 'Vui lòng nhập xác nhận mật khẩu.';
        return;
      }
      if (this.password !== this.passwordConfirmation) {
        this.error = 'Xác nhận mật khẩu không khớp.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await resetAdminPassword(this.identifier, this.otp, this.password, this.passwordConfirmation);
        this.successMsg = response.message || 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.';
        setTimeout(() => this.$router.push('/admin/login'), 1200);
      } catch (err) {
        this.error = err.message || 'Không thể đặt lại mật khẩu Admin.';
      } finally {
        this.isLoading = false;
      }
    },
    goBackToIdentify() {
      this.error = '';
      this.successMsg = '';
      this.otp = '';
      this.step = 'identify';
    },
  },
};
</script>

<style scoped>
.shake-enter-active {
  animation: shakeAnim .4s ease;
}
@keyframes shakeAnim {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-6px); }
  40% { transform: translateX(6px); }
  60% { transform: translateX(-4px); }
  80% { transform: translateX(4px); }
}
.fade-enter-active, .fade-leave-active {
  transition: opacity .3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>

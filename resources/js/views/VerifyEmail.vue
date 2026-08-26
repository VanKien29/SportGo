<template>
  <AuthLayout
    class="sg-account-auth"
    title="Xác thực email"
    subtitle="Hoàn tất xác thực để bắt đầu dùng SportGo"
    quote-title="Bảo vệ tài khoản"
    quote-text="Xác thực email giúp SportGo bảo vệ tài khoản và gửi đúng thông tin đến bạn."
    :image-src="authVisual"
    back-to="/"
  >
    <div class="sg-account-form sg-auth-verify-email">
      <div
        v-if="successMsg"
        class="sg-auth-alert sg-auth-alert--success"
        role="status"
        aria-live="polite"
      >
        <AppIcon name="circleCheck" :size="18" />
        <span>{{ successMsg }}</span>
      </div>

      <div
        v-if="error"
        class="sg-auth-alert sg-auth-alert--error"
        role="alert"
        aria-live="assertive"
      >
        <AppIcon name="alert" :size="18" />
        <span>{{ error }}</span>
      </div>

      <form class="sg-auth-step sg-auth-otp-step" autocomplete="one-time-code" novalidate @submit.prevent="handleVerifyOtp">
        <p class="sg-auth-context">
          Nhập email đăng ký và mã OTP 6 chữ số đã được gửi đến hộp thư của bạn.
        </p>

        <div class="sg-auth-field">
          <label for="verification-email">Email đăng ký <span aria-hidden="true">*</span></label>
          <input
            id="verification-email"
            ref="emailInput"
            v-model.trim="email"
            class="sg-auth-input"
            :class="{ 'sg-auth-input--error': fieldErrors.email }"
            type="email"
            placeholder="ban@example.com"
            autocomplete="email"
            autocapitalize="none"
            spellcheck="false"
            required
            :aria-invalid="Boolean(fieldErrors.email)"
            @input="clearFieldError('email')"
          />
          <p v-if="fieldErrors.email" class="sg-auth-field-error">{{ fieldErrors.email }}</p>
        </div>

        <div class="sg-auth-field">
          <label for="verification-otp">Mã OTP <span aria-hidden="true">*</span></label>
          <input
            id="verification-otp"
            ref="otpInput"
            v-model.trim="otp"
            class="sg-auth-input sg-auth-otp"
            :class="{ 'sg-auth-input--error': fieldErrors.otp }"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            placeholder="000000"
            autocomplete="one-time-code"
            required
            :aria-invalid="Boolean(fieldErrors.otp)"
            @input="clearFieldError('otp')"
          />
          <p v-if="fieldErrors.otp" class="sg-auth-field-error">{{ fieldErrors.otp }}</p>
        </div>

        <button class="sg-auth-submit sg-auth-otp-submit" type="submit" :disabled="isLoading || isResending">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang xác thực...' : 'Xác nhận tài khoản' }}</span>
        </button>

        <div class="sg-auth-inline-actions sg-auth-otp-actions">
          <button type="button" :disabled="isLoading || isResending || resendCountdown > 0" @click="resendOtp">
            {{ isResending ? 'Đang gửi lại...' : resendCountdown > 0 ? `Gửi lại sau ${resendCountdown}s` : 'Gửi lại mã' }}
          </button>
          <button type="button" :disabled="isLoading || isResending" @click="changeEmail">Đổi email</button>
        </div>
      </form>

      <p class="sg-auth-switch">
        Đã xác thực rồi?
        <router-link to="/login">Đăng nhập</router-link>
      </p>
      <p class="sg-auth-switch">
        Chưa có tài khoản?
        <router-link to="/register">Đăng ký ngay</router-link>
      </p>
    </div>
  </AuthLayout>
</template>

<script>
import { resendRegisterOtp, verifyRegisterOtp } from '../stores/auth.js';
import AppIcon from '../components/AppIcon.vue';
import AuthLayout from '../components/ui/AuthLayout.vue';

const emptyFieldErrors = () => ({ email: '', otp: '' });

export default {
  name: 'VerifyEmailView',
  components: { AppIcon, AuthLayout },
  data() {
    return {
      authVisual: '/images/auth/sportgo_art.png',
      email: '',
      otp: '',
      error: '',
      successMsg: '',
      fieldErrors: emptyFieldErrors(),
      isLoading: false,
      isResending: false,
      resendCountdown: 0,
      resendTimer: null,
    };
  },
  mounted() {
    this.email = this.normalizeEmail(this.$route.query.email || '');
    this.$nextTick(() => {
      if (this.email) {
        this.$refs.otpInput?.focus();
      } else {
        this.$refs.emailInput?.focus();
      }
    });
  },
  beforeUnmount() {
    if (this.resendTimer) clearInterval(this.resendTimer);
  },
  methods: {
    normalizeEmail(value) {
      return String(value || '').trim().toLowerCase();
    },
    clearMessages() {
      this.error = '';
      this.successMsg = '';
    },
    clearFieldError(field) {
      this.fieldErrors[field] = '';
      if (!Object.values(this.fieldErrors).some(Boolean)) this.error = '';
    },
    setFieldError(field, message) {
      this.fieldErrors[field] = message;
      this.error = message;
      const refMap = { email: 'emailInput', otp: 'otpInput' };
      this.$nextTick(() => this.$refs[refMap[field]]?.focus());
    },
    validateEmail() {
      if (!this.email) {
        this.setFieldError('email', 'Vui lòng nhập email đăng ký.');
        return false;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
        this.setFieldError('email', 'Địa chỉ email không đúng định dạng.');
        return false;
      }
      return true;
    },
    startResendCountdown() {
      this.resendCountdown = 60;
      if (this.resendTimer) clearInterval(this.resendTimer);
      this.resendTimer = setInterval(() => {
        this.resendCountdown -= 1;
        if (this.resendCountdown <= 0) {
          clearInterval(this.resendTimer);
          this.resendTimer = null;
        }
      }, 1000);
    },
    async handleVerifyOtp() {
      this.clearMessages();
      this.fieldErrors = emptyFieldErrors();
      if (!this.validateEmail()) return;
      if (!/^\d{6}$/.test(this.otp)) {
        this.setFieldError('otp', 'Mã OTP phải gồm đúng 6 chữ số.');
        return;
      }

      this.isLoading = true;
      try {
        const response = await verifyRegisterOtp(this.email, this.otp);
        this.successMsg = response.message || 'Xác thực tài khoản thành công.';
        window.setTimeout(() => this.$router.push('/login'), 1000);
      } catch (requestError) {
        const emailMessage = requestError.data?.errors?.email?.[0];
        if (emailMessage) {
          this.setFieldError('email', emailMessage);
          return;
        }

        const message = requestError.data?.errors?.otp?.[0]
          || requestError.message
          || 'Mã OTP không đúng hoặc đã hết hạn.';
        this.setFieldError('otp', message);
      } finally {
        this.isLoading = false;
      }
    },
    async resendOtp() {
      this.clearMessages();
      if (!this.validateEmail()) return;

      this.isResending = true;
      try {
        const response = await resendRegisterOtp(this.email);
        this.successMsg = response.message || 'Đã gửi lại mã xác thực.';
        this.otp = '';
        this.startResendCountdown();
      } catch (requestError) {
        this.error = requestError.message || 'Không thể gửi lại mã xác thực.';
      } finally {
        this.isResending = false;
      }
    },
    changeEmail() {
      this.email = '';
      this.otp = '';
      this.fieldErrors = emptyFieldErrors();
      this.clearMessages();
      this.$nextTick(() => this.$refs.emailInput?.focus());
    },
  },
};
</script>

<template>
  <AuthLayout
    class="sg-account-auth"
    :title="titleText"
    :subtitle="subtitleText"
    :image-src="authVisual"
    quote-text="Khôi phục quyền truy cập để tiếp tục lịch chơi của bạn."
    back-to="/"
  >
    <div class="sg-account-form">
      <div class="sg-reset-progress" aria-label="Tiến độ khôi phục mật khẩu">
        <span
          v-for="(item, index) in resetSteps"
          :key="item.key"
          :class="{ active: step === item.key, passed: currentStepIndex > index }"
        >
          {{ index + 1 }}. {{ item.label }}
        </span>
      </div>

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

      <form v-if="step === 'identify'" class="sg-auth-step" novalidate @submit.prevent="handleIdentify">
        <p class="sg-auth-context">
          SportGo sẽ gửi mã xác thực đến email gắn với tài khoản. Vì bảo mật, hệ thống không xác nhận tài khoản có tồn tại hay không.
        </p>
        <div class="sg-auth-field">
          <label for="identifier">Tài khoản</label>
          <input
            id="identifier"
            ref="identifierInput"
            v-model.trim="identifier"
            class="sg-auth-input"
            :class="{ 'sg-auth-input--error': fieldErrors.identifier }"
            type="text"
            placeholder="Email, số điện thoại hoặc tên đăng nhập"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            :aria-invalid="Boolean(fieldErrors.identifier)"
            @input="clearFieldError('identifier')"
          />
          <p v-if="fieldErrors.identifier" class="sg-auth-field-error">{{ fieldErrors.identifier }}</p>
        </div>
        <button class="sg-auth-submit" type="submit" :disabled="isLoading">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang gửi mã...' : 'Gửi mã OTP' }}</span>
        </button>
      </form>

      <form v-else-if="step === 'otp'" class="sg-auth-step" novalidate @submit.prevent="handleVerifyOtp">
        <p class="sg-auth-context">
          Nhập mã 6 chữ số được gửi đến email của tài khoản <strong>{{ identifier }}</strong>.
        </p>
        <div class="sg-auth-field">
          <label for="otp">Mã OTP</label>
          <input
            id="otp"
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
            :aria-invalid="Boolean(fieldErrors.otp)"
            @input="clearFieldError('otp')"
          />
          <p v-if="fieldErrors.otp" class="sg-auth-field-error">{{ fieldErrors.otp }}</p>
        </div>
        <button class="sg-auth-submit" type="submit" :disabled="isLoading || isResending">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang xác nhận...' : 'Xác nhận mã OTP' }}</span>
        </button>
        <div class="sg-auth-inline-actions">
          <button type="button" :disabled="isLoading || isResending" @click="resendOtp">
            {{ isResending ? 'Đang gửi lại...' : 'Gửi lại mã' }}
          </button>
          <button type="button" :disabled="isLoading || isResending" @click="changeIdentifier">
            Đổi tài khoản
          </button>
        </div>
      </form>

      <form v-else class="sg-auth-step" novalidate @submit.prevent="handleReset">
        <p class="sg-auth-context">
          Tạo mật khẩu mới cho <strong>{{ identifier }}</strong>. Mật khẩu mới sẽ đăng xuất các phiên đang mở để bảo vệ tài khoản.
        </p>
        <div class="sg-auth-field">
          <PasswordInput
            v-model="password"
            class="sg-auth-password"
            :class="{ 'sg-auth-password--error': fieldErrors.password }"
            label="Mật khẩu mới"
            placeholder="Tối thiểu 8 ký tự"
            autocomplete="new-password"
            @update:model-value="clearFieldError('password')"
          />
          <p v-if="fieldErrors.password" class="sg-auth-field-error">{{ fieldErrors.password }}</p>
        </div>

        <ul class="sg-password-rules" aria-label="Yêu cầu mật khẩu">
          <li :class="{ passed: passwordChecks.length }">8–50 ký tự</li>
          <li :class="{ passed: passwordChecks.uppercase }">Có chữ hoa</li>
          <li :class="{ passed: passwordChecks.number }">Có chữ số</li>
          <li :class="{ passed: passwordChecks.special }">Có ký tự đặc biệt</li>
        </ul>

        <div class="sg-auth-field">
          <PasswordInput
            v-model="password_confirmation"
            class="sg-auth-password"
            :class="{ 'sg-auth-password--error': fieldErrors.password_confirmation }"
            label="Xác nhận mật khẩu mới"
            placeholder="Nhập lại mật khẩu"
            autocomplete="new-password"
            @update:model-value="clearFieldError('password_confirmation')"
          />
          <p v-if="fieldErrors.password_confirmation" class="sg-auth-field-error">
            {{ fieldErrors.password_confirmation }}
          </p>
        </div>
        <button class="sg-auth-submit" type="submit" :disabled="isLoading">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang đổi mật khẩu...' : 'Đặt lại mật khẩu' }}</span>
        </button>
      </form>

      <div class="sg-auth-divider"><span>Hoặc</span></div>
      <router-link class="sg-auth-secondary" to="/login">
        <AppIcon name="chevronLeft" :size="17" />
        Quay lại đăng nhập
      </router-link>
    </div>
  </AuthLayout>
</template>

<script>
import { resetPassword, sendForgotOtp, verifyForgotOtp } from '../stores/auth.js';
import AppIcon from '../components/AppIcon.vue';
import AuthLayout from '../components/ui/AuthLayout.vue';
import PasswordInput from '../components/ui/PasswordInput.vue';

const emptyFieldErrors = () => ({
  identifier: '',
  otp: '',
  password: '',
  password_confirmation: '',
});

export default {
  name: 'ForgotPasswordView',
  components: {
    AppIcon,
    AuthLayout,
    PasswordInput,
  },
  data() {
    return {
      authVisual: '/images/home/badminton-cover.webp',
      step: 'identify',
      resetSteps: [
        { key: 'identify', label: 'Tài khoản' },
        { key: 'otp', label: 'OTP' },
        { key: 'reset', label: 'Mật khẩu mới' },
      ],
      identifier: '',
      otp: '',
      password: '',
      password_confirmation: '',
      error: '',
      fieldErrors: emptyFieldErrors(),
      successMsg: '',
      isLoading: false,
      isResending: false,
    };
  },
  computed: {
    currentStepIndex() {
      return this.resetSteps.findIndex((item) => item.key === this.step);
    },
    titleText() {
      if (this.step === 'identify') return 'Khôi phục mật khẩu';
      if (this.step === 'otp') return 'Xác thực mã OTP';
      return 'Tạo mật khẩu mới';
    },
    subtitleText() {
      if (this.step === 'identify') return 'Nhập tài khoản để nhận mã xác thực qua email';
      if (this.step === 'otp') return 'Mã OTP gồm 6 chữ số và chỉ có hiệu lực trong thời gian giới hạn';
      return 'Dùng mật khẩu mạnh và không trùng với mật khẩu ở dịch vụ khác';
    },
    passwordChecks() {
      return {
        length: this.password.length >= 8 && this.password.length <= 50,
        uppercase: /[A-Z]/.test(this.password),
        number: /\d/.test(this.password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(this.password),
      };
    },
  },
  mounted() {
    this.$refs.identifierInput?.focus();
  },
  methods: {
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
      const refMap = { identifier: 'identifierInput', otp: 'otpInput' };
      this.$nextTick(() => this.$refs[refMap[field]]?.focus());
    },
    async handleIdentify() {
      this.clearMessages();
      this.fieldErrors = emptyFieldErrors();
      if (!this.identifier.trim()) {
        this.setFieldError('identifier', 'Vui lòng nhập email, số điện thoại hoặc tên đăng nhập.');
        return;
      }

      this.isLoading = true;
      try {
        const response = await sendForgotOtp(this.identifier.trim());
        this.step = 'otp';
        this.successMsg = response.message || 'Nếu tài khoản tồn tại, mã OTP sẽ được gửi đến email đăng ký.';
        this.$nextTick(() => this.$refs.otpInput?.focus());
      } catch (requestError) {
        this.setFieldError('identifier', requestError.message || 'Không thể gửi mã OTP.');
      } finally {
        this.isLoading = false;
      }
    },
    async handleVerifyOtp() {
      this.clearMessages();
      this.fieldErrors.otp = '';
      if (!/^\d{6}$/.test(this.otp)) {
        this.setFieldError('otp', 'Mã OTP phải gồm đúng 6 chữ số.');
        return;
      }

      this.isLoading = true;
      try {
        const response = await verifyForgotOtp(this.identifier.trim(), this.otp);
        this.step = 'reset';
        this.successMsg = response.message || 'Mã OTP hợp lệ. Bạn có thể tạo mật khẩu mới.';
      } catch (requestError) {
        this.setFieldError('otp', requestError.message || 'Mã OTP không đúng hoặc đã hết hạn.');
      } finally {
        this.isLoading = false;
      }
    },
    async resendOtp() {
      this.clearMessages();
      this.isResending = true;
      try {
        const response = await sendForgotOtp(this.identifier.trim());
        this.successMsg = response.message || 'Nếu tài khoản tồn tại, mã OTP mới sẽ được gửi đến email đăng ký.';
      } catch (requestError) {
        this.error = requestError.message || 'Không thể gửi lại mã OTP.';
      } finally {
        this.isResending = false;
      }
    },
    changeIdentifier() {
      this.step = 'identify';
      this.otp = '';
      this.fieldErrors = emptyFieldErrors();
      this.clearMessages();
      this.$nextTick(() => this.$refs.identifierInput?.focus());
    },
    validatePassword() {
      if (!this.password) {
        this.setFieldError('password', 'Vui lòng nhập mật khẩu mới.');
        return false;
      }
      if (!this.passwordChecks.length) {
        this.setFieldError('password', 'Mật khẩu phải từ 8 đến 50 ký tự.');
        return false;
      }
      if (!this.passwordChecks.uppercase) {
        this.setFieldError('password', 'Mật khẩu phải có ít nhất một chữ hoa.');
        return false;
      }
      if (!this.passwordChecks.number) {
        this.setFieldError('password', 'Mật khẩu phải có ít nhất một chữ số.');
        return false;
      }
      if (!this.passwordChecks.special) {
        this.setFieldError('password', 'Mật khẩu phải có ít nhất một ký tự đặc biệt.');
        return false;
      }
      if (!this.password_confirmation) {
        this.setFieldError('password_confirmation', 'Vui lòng nhập lại mật khẩu mới.');
        return false;
      }
      if (this.password !== this.password_confirmation) {
        this.setFieldError('password_confirmation', 'Xác nhận mật khẩu không khớp.');
        return false;
      }
      return true;
    },
    async handleReset() {
      this.clearMessages();
      this.fieldErrors.password = '';
      this.fieldErrors.password_confirmation = '';
      if (!this.validatePassword()) return;

      this.isLoading = true;
      try {
        const response = await resetPassword(
          this.identifier.trim(),
          this.otp,
          this.password,
          this.password_confirmation,
        );
        this.successMsg = response.message || 'Đặt lại mật khẩu thành công.';
        window.setTimeout(() => this.$router.push('/login'), 1500);
      } catch (requestError) {
        this.error = requestError.message || 'Không thể đặt lại mật khẩu.';
      } finally {
        this.isLoading = false;
      }
    },
  },
};
</script>

<style scoped src="/resources/css/views/client-forgot-password.css"></style>

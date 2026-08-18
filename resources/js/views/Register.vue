<template>
  <AuthLayout
    class="sg-account-auth"
    :title="step === 'register' ? 'Tạo tài khoản SportGo' : 'Xác thực email'"
    :subtitle="step === 'register' ? 'Một tài khoản cho đặt sân, lịch chơi và cộng đồng' : 'Nhập mã gồm 6 chữ số đã gửi đến email đăng ký'"
    :image-src="authVisual"
    quote-text="Tạo tài khoản để tìm sân, đặt lịch và kết nối cùng cộng đồng thể thao."
    back-to="/"
  >
    <form
      v-if="step === 'register'"
      class="sg-account-form sg-register-form"
      autocomplete="on"
      novalidate
      @submit.prevent="handleRegister"
    >
      <div
        v-if="error"
        class="sg-auth-alert sg-auth-alert--error"
        role="alert"
        aria-live="assertive"
      >
        <AppIcon name="alert" :size="18" />
        <span>{{ error }}</span>
      </div>

      <div v-if="isCompactScreen" class="sg-register-progress" aria-label="Tiến độ đăng ký">
        <div>
          <span>Bước {{ registerSubStep }} / 2</span>
          <strong>{{ registerSubStep === 1 ? 'Thông tin cá nhân' : 'Tạo mật khẩu' }}</strong>
        </div>
        <span class="sg-register-progress-track" aria-hidden="true">
          <span :class="{ 'is-complete': registerSubStep === 2 }"></span>
        </span>
      </div>

      <div v-show="!isCompactScreen || registerSubStep === 1" class="sg-register-section">
        <div class="sg-auth-field">
          <label for="username">Tên đăng nhập <span aria-hidden="true">*</span></label>
          <input
            id="username"
            ref="usernameInput"
            v-model.trim="form.username"
            class="sg-auth-input"
            :class="{ 'sg-auth-input--error': fieldErrors.username }"
            type="text"
            placeholder="Ví dụ: nguyenvana"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            maxlength="50"
            required
            :aria-invalid="Boolean(fieldErrors.username)"
            @input="clearFieldError('username')"
          />
          <p v-if="fieldErrors.username" class="sg-auth-field-error">{{ fieldErrors.username }}</p>
        </div>

        <div class="sg-auth-field">
          <label for="full_name">Họ và tên <span aria-hidden="true">*</span></label>
          <input
            id="full_name"
            ref="fullNameInput"
            v-model.trim="form.full_name"
            class="sg-auth-input"
            :class="{ 'sg-auth-input--error': fieldErrors.full_name }"
            type="text"
            placeholder="Nguyễn Văn A"
            autocomplete="name"
            maxlength="255"
            required
            :aria-invalid="Boolean(fieldErrors.full_name)"
            @input="clearFieldError('full_name')"
          />
          <p v-if="fieldErrors.full_name" class="sg-auth-field-error">{{ fieldErrors.full_name }}</p>
        </div>

        <div class="sg-auth-field">
          <label for="phone">Số điện thoại <span aria-hidden="true">*</span></label>
          <input
            id="phone"
            ref="phoneInput"
            v-model.trim="form.phone"
            class="sg-auth-input"
            :class="{ 'sg-auth-input--error': fieldErrors.phone }"
            type="tel"
            inputmode="tel"
            placeholder="09xxxxxxxx"
            autocomplete="tel"
            maxlength="20"
            required
            :aria-invalid="Boolean(fieldErrors.phone)"
            @input="clearFieldError('phone')"
          />
          <p v-if="fieldErrors.phone" class="sg-auth-field-error">{{ fieldErrors.phone }}</p>
        </div>

        <div class="sg-auth-field">
          <label for="email">Email <span aria-hidden="true">*</span></label>
          <input
            id="email"
            ref="emailInput"
            v-model.trim="form.email"
            class="sg-auth-input"
            :class="{ 'sg-auth-input--error': fieldErrors.email }"
            type="email"
            placeholder="ban@example.com"
            autocomplete="email"
            autocapitalize="none"
            spellcheck="false"
            maxlength="255"
            required
            :aria-invalid="Boolean(fieldErrors.email)"
            @input="clearFieldError('email')"
          />
          <p v-if="fieldErrors.email" class="sg-auth-field-error">{{ fieldErrors.email }}</p>
        </div>
      </div>

      <div v-show="!isCompactScreen || registerSubStep === 2" class="sg-register-section">
        <div class="sg-auth-field">
          <PasswordInput
            v-model="form.password"
            class="sg-auth-password"
            :class="{ 'sg-auth-password--error': fieldErrors.password }"
            label="Mật khẩu"
            required
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
            v-model="form.password_confirmation"
            class="sg-auth-password"
            :class="{ 'sg-auth-password--error': fieldErrors.password_confirmation }"
            label="Xác nhận mật khẩu"
            required
            placeholder="Nhập lại mật khẩu"
            autocomplete="new-password"
            @update:model-value="clearFieldError('password_confirmation')"
          />
          <p v-if="fieldErrors.password_confirmation" class="sg-auth-field-error">
            {{ fieldErrors.password_confirmation }}
          </p>
        </div>
      </div>

      <div class="sg-auth-action-stack">
        <button class="sg-auth-submit" type="submit" :disabled="isLoading">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ submitLabel }}</span>
        </button>
        <button
          v-if="isCompactScreen && registerSubStep === 2"
          class="sg-auth-secondary"
          type="button"
          :disabled="isLoading"
          @click="goToRegisterStep(1)"
        >
          <AppIcon name="chevronLeft" :size="17" />
          Quay lại thông tin cá nhân
        </button>
      </div>

      <div class="sg-auth-divider"><span>Hoặc</span></div>
      <button class="sg-auth-secondary" type="button" @click="handleGoogleLogin">
        <span class="sg-auth-provider-mark" aria-hidden="true">G</span>
        Tiếp tục với Google
      </button>
      <p class="sg-auth-switch">
        Đã có tài khoản?
        <router-link to="/login">Đăng nhập</router-link>
      </p>
    </form>

    <form
      v-else
      class="sg-account-form"
      autocomplete="one-time-code"
      novalidate
      @submit.prevent="handleVerifyOtp"
    >
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

      <p class="sg-auth-context">
        Mã xác thực đã được gửi đến <strong>{{ form.email }}</strong>. Mã gồm đúng 6 chữ số.
      </p>
      <div class="sg-auth-field">
        <label for="otp">Mã OTP <span aria-hidden="true">*</span></label>
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
          required
          :aria-invalid="Boolean(fieldErrors.otp)"
          @input="clearFieldError('otp')"
        />
        <p v-if="fieldErrors.otp" class="sg-auth-field-error">{{ fieldErrors.otp }}</p>
      </div>

      <button class="sg-auth-submit" type="submit" :disabled="isLoading || isResending">
        <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
        <span>{{ isLoading ? 'Đang xác nhận...' : 'Xác nhận tài khoản' }}</span>
      </button>
      <div class="sg-auth-inline-actions">
        <button type="button" :disabled="isLoading || isResending" @click="handleResendOtp">
          {{ isResending ? 'Đang gửi lại...' : 'Gửi lại mã' }}
        </button>
        <button type="button" :disabled="isLoading || isResending" @click="editRegistration">
          Đổi email
        </button>
      </div>
    </form>
  </AuthLayout>
</template>

<script>
import { register, verifyRegisterOtp, resendRegisterOtp, loginWithGoogle } from '../stores/auth.js';
import AppIcon from '../components/AppIcon.vue';
import AuthLayout from '../components/ui/AuthLayout.vue';
import PasswordInput from '../components/ui/PasswordInput.vue';

const emptyFieldErrors = () => ({
  username: '',
  full_name: '',
  phone: '',
  email: '',
  password: '',
  password_confirmation: '',
  otp: '',
});

export default {
  name: 'RegisterView',
  components: {
    AppIcon,
    AuthLayout,
    PasswordInput,
  },
  data() {
    return {
      authVisual: '/images/home/anhbia2.webp',
      form: {
        username: '',
        full_name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
      },
      step: 'register',
      otp: '',
      error: '',
      fieldErrors: emptyFieldErrors(),
      successMsg: '',
      isLoading: false,
      isResending: false,
      isCompactScreen: false,
      registerSubStep: 1,
    };
  },
  computed: {
    passwordChecks() {
      return {
        length: this.form.password.length >= 8 && this.form.password.length <= 50,
        uppercase: /[A-Z]/.test(this.form.password),
        number: /\d/.test(this.form.password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(this.form.password),
      };
    },
    submitLabel() {
      if (this.isLoading) return 'Đang tạo tài khoản...';
      if (this.isCompactScreen && this.registerSubStep === 1) return 'Tiếp tục';
      return 'Tạo tài khoản';
    },
  },
  mounted() {
    this.checkScreenSize();
    window.addEventListener('resize', this.checkScreenSize);
    this.$refs.usernameInput?.focus();
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.checkScreenSize);
  },
  methods: {
    checkScreenSize() {
      this.isCompactScreen = window.innerWidth < 1024 || window.innerHeight < 820;
      if (!this.isCompactScreen) this.registerSubStep = 1;
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
      const refMap = {
        username: 'usernameInput',
        full_name: 'fullNameInput',
        phone: 'phoneInput',
        email: 'emailInput',
        otp: 'otpInput',
      };
      this.$nextTick(() => this.$refs[refMap[field]]?.focus());
      return false;
    },
    validateIdentity() {
      if (!this.form.username) return this.setFieldError('username', 'Vui lòng nhập tên đăng nhập.');
      if (this.form.username.length > 50) return this.setFieldError('username', 'Tên đăng nhập tối đa 50 ký tự.');
      if (!this.form.full_name) return this.setFieldError('full_name', 'Vui lòng nhập họ và tên.');
      if (!this.form.phone) return this.setFieldError('phone', 'Vui lòng nhập số điện thoại.');
      if (!/^(0|84|\+84)?[35789]\d{8}$/.test(this.form.phone)) {
        return this.setFieldError('phone', 'Số điện thoại không đúng định dạng Việt Nam.');
      }
      if (!this.form.email) return this.setFieldError('email', 'Vui lòng nhập địa chỉ email.');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email)) {
        return this.setFieldError('email', 'Địa chỉ email không đúng định dạng.');
      }
      return true;
    },
    validatePassword() {
      if (!this.form.password) return this.setFieldError('password', 'Vui lòng nhập mật khẩu.');
      if (!this.passwordChecks.length) return this.setFieldError('password', 'Mật khẩu phải từ 8 đến 50 ký tự.');
      if (!this.passwordChecks.uppercase) return this.setFieldError('password', 'Mật khẩu phải có ít nhất một chữ hoa.');
      if (!this.passwordChecks.number) return this.setFieldError('password', 'Mật khẩu phải có ít nhất một chữ số.');
      if (!this.passwordChecks.special) return this.setFieldError('password', 'Mật khẩu phải có ít nhất một ký tự đặc biệt.');
      if (!this.form.password_confirmation) {
        return this.setFieldError('password_confirmation', 'Vui lòng nhập lại mật khẩu.');
      }
      if (this.form.password !== this.form.password_confirmation) {
        return this.setFieldError('password_confirmation', 'Xác nhận mật khẩu không khớp.');
      }
      return true;
    },
    goToRegisterStep(step) {
      this.registerSubStep = step;
      this.clearMessages();
      if (step === 1) this.$nextTick(() => this.$refs.usernameInput?.focus());
    },
    applyBackendErrors(requestError) {
      const errors = requestError.data?.errors || {};
      const knownFields = Object.keys(this.fieldErrors);
      const firstField = knownFields.find((field) => Array.isArray(errors[field]) && errors[field][0]);
      if (firstField) {
        this.setFieldError(firstField, errors[firstField][0]);
        if (this.isCompactScreen && ['username', 'full_name', 'phone', 'email'].includes(firstField)) {
          this.registerSubStep = 1;
        }
        return;
      }
      this.error = requestError.message || 'Đăng ký không thành công.';
    },
    async handleRegister() {
      this.clearMessages();
      this.fieldErrors = emptyFieldErrors();

      if (!this.validateIdentity()) {
        if (this.isCompactScreen) this.registerSubStep = 1;
        return;
      }
      if (this.isCompactScreen && this.registerSubStep === 1) {
        this.registerSubStep = 2;
        return;
      }
      if (!this.validatePassword()) return;

      this.isLoading = true;
      try {
        const response = await register(this.form);
        this.step = 'otp';
        this.successMsg = response.message || 'Mã xác thực đã được gửi về email.';
        this.$nextTick(() => this.$refs.otpInput?.focus());
      } catch (requestError) {
        this.applyBackendErrors(requestError);
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
        const response = await verifyRegisterOtp(this.form.email, this.otp);
        this.successMsg = response.message || 'Xác thực thành công.';
        window.setTimeout(() => this.$router.push('/login'), 1200);
      } catch (requestError) {
        this.setFieldError('otp', requestError.message || 'Mã OTP không đúng hoặc đã hết hạn.');
      } finally {
        this.isLoading = false;
      }
    },
    async handleResendOtp() {
      this.clearMessages();
      this.isResending = true;
      try {
        const response = await resendRegisterOtp(this.form.email);
        this.successMsg = response.message || 'Đã gửi lại mã OTP.';
      } catch (requestError) {
        this.error = requestError.message || 'Không thể gửi lại mã OTP.';
      } finally {
        this.isResending = false;
      }
    },
    editRegistration() {
      this.step = 'register';
      this.otp = '';
      this.registerSubStep = 1;
      this.fieldErrors = emptyFieldErrors();
      this.clearMessages();
      this.$nextTick(() => this.$refs.emailInput?.focus());
    },
    handleGoogleLogin() {
      loginWithGoogle();
    },
  },
};
</script>

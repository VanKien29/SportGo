<template>
  <div class="login-page">
    <div class="bg-decor">
      <div class="bg-circle bg-circle-1"></div>
      <div class="bg-circle bg-circle-2"></div>
      <div class="bg-circle bg-circle-3"></div>
      <div class="bg-grid"></div>
    </div>

    <div class="login-container register-container">
      <router-link to="/" class="back-link">Về trang chủ</router-link>

      <div class="login-card">
        <div class="card-brand">
          <h1 class="brand-title">Sport<span class="accent">Go</span></h1>
          <p class="brand-sub">{{ step === 'register' ? 'Tạo tài khoản mới' : 'Xác thực OTP đăng ký' }}</p>
        </div>

        <transition name="shake">
          <div v-if="error" class="error-msg">{{ error }}</div>
        </transition>

        <transition name="fade">
          <div v-if="successMsg" class="success-msg">{{ successMsg }}</div>
        </transition>

        <form v-if="step === 'register'" @submit.prevent="handleRegister" class="login-form" autocomplete="off">
          <div class="input-group">
            <label for="username">Tên tài khoản <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'username' }">
              <input id="username" v-model.trim="form.username" type="text" placeholder="Nhập tên tài khoản" required autocomplete="username" @focus="focusedField = 'username'" @blur="focusedField = ''" />
            </div>
          </div>

          <div class="input-group">
            <label for="full_name">Họ tên <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'full_name' }">
              <input id="full_name" v-model.trim="form.full_name" type="text" placeholder="Nhập họ tên" required autocomplete="name" @focus="focusedField = 'full_name'" @blur="focusedField = ''" />
            </div>
          </div>

          <div class="input-group">
            <label for="phone">Số điện thoại <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'phone' }">
              <input id="phone" v-model.trim="form.phone" type="tel" placeholder="Nhập số điện thoại" required autocomplete="tel" @focus="focusedField = 'phone'" @blur="focusedField = ''" />
            </div>
          </div>

          <div class="input-group">
            <label for="email">Email <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'email' }">
              <input id="email" v-model.trim="form.email" type="email" placeholder="Nhập email" required autocomplete="email" @focus="focusedField = 'email'" @blur="focusedField = ''" />
            </div>
          </div>

          <div class="input-group">
            <label for="password">Mật khẩu <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'password' }">
              <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Nhập mật khẩu" required autocomplete="new-password" @focus="focusedField = 'password'" @blur="focusedField = ''" />
              <button type="button" class="toggle-pw" @click="showPassword = !showPassword" tabindex="-1">
                {{ showPassword ? 'Ẩn' : 'Hiện' }}
              </button>
            </div>
          </div>

          <div class="input-group">
            <label for="password_confirmation">Xác nhận mật khẩu <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'password_confirmation' }">
              <input id="password_confirmation" v-model="form.password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" placeholder="Nhập lại mật khẩu" required autocomplete="new-password" @focus="focusedField = 'password_confirmation'" @blur="focusedField = ''" />
              <button type="button" class="toggle-pw" @click="showPasswordConfirm = !showPasswordConfirm" tabindex="-1">
                {{ showPasswordConfirm ? 'Ẩn' : 'Hiện' }}
              </button>
            </div>
          </div>

          <button type="submit" class="submit-btn" :class="{ loading: isLoading }" :disabled="isLoading">
            <span v-if="!isLoading">Đăng ký</span>
            <span v-else class="spinner"></span>
          </button>

          <button type="button" class="btn-google" @click.prevent="handleGoogleLogin">
            Đăng ký bằng Google
          </button>
        </form>

        <form v-else @submit.prevent="handleVerifyOtp" class="login-form">
          <p class="field-hint">Nhập mã OTP 6 số đã gửi về email {{ form.email }}.</p>
          <div class="input-group">
            <label for="otp">Mã OTP <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'otp' }">
              <input id="otp" v-model.trim="otp" type="text" inputmode="numeric" maxlength="6" placeholder="Nhập mã OTP" required autocomplete="one-time-code" @focus="focusedField = 'otp'" @blur="focusedField = ''" />
            </div>
          </div>

          <button type="submit" class="submit-btn" :disabled="isLoading || isResending">
            <span v-if="!isLoading">Xác thực tài khoản</span>
            <span v-else class="spinner"></span>
          </button>

          <button type="button" class="btn-resend" :disabled="isResending" @click="handleResendOtp">
            {{ isResending ? 'Đang gửi lại...' : 'Gửi lại mã OTP' }}
          </button>
        </form>

        <div class="register-section">
          <span>Đã có tài khoản?</span>
          <router-link to="/login" class="register-link">Đăng nhập</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { register, verifyRegisterOtp, resendRegisterOtp, loginWithGoogle } from '../stores/auth.js';

export default {
  name: 'RegisterView',
  data() {
    return {
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
      successMsg: '',
      isLoading: false,
      isResending: false,
      showPassword: false,
      showPasswordConfirm: false,
      focusedField: '',
    };
  },
  methods: {
    async handleRegister() {
      this.error = '';
      this.successMsg = '';

      if (!this.form.username || !this.form.full_name || !this.form.phone || !this.form.email || !this.form.password || !this.form.password_confirmation) {
        this.error = 'Vui lòng nhập đầy đủ các trường bắt buộc.';
        return;
      }

      if (this.form.password !== this.form.password_confirmation) {
        this.error = 'Xác nhận mật khẩu không khớp.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await register(this.form);
        this.step = 'otp';
        this.successMsg = response.message || 'Mã xác thực đã được gửi về email.';
      } catch (error) {
        this.error = error.message || 'Đăng ký không thành công.';
      } finally {
        this.isLoading = false;
      }
    },
    async handleVerifyOtp() {
      this.error = '';
      this.successMsg = '';
      this.isLoading = true;

      try {
        const response = await verifyRegisterOtp(this.form.email, this.otp);
        this.successMsg = response.message || 'Xác thực thành công.';
        setTimeout(() => this.$router.push('/login'), 1200);
      } catch (error) {
        this.error = error.message || 'Mã OTP không đúng.';
      } finally {
        this.isLoading = false;
      }
    },
    async handleResendOtp() {
      this.error = '';
      this.successMsg = '';
      this.isResending = true;

      try {
        const response = await resendRegisterOtp(this.form.email);
        this.successMsg = response.message || 'Đã gửi lại mã OTP.';
      } catch (error) {
        this.error = error.message || 'Không thể gửi lại mã OTP.';
      } finally {
        this.isResending = false;
      }
    },
    handleGoogleLogin() {
      loginWithGoogle();
    },
  },
};
</script>

<style src="../../css/auth/login.css" scoped></style>
<style scoped>
.register-container {
  max-width: 480px;
}

.required {
  color: #ef4444;
}

.field-hint {
  color: rgba(255, 255, 255, 0.6);
  font-size: 13px;
  line-height: 1.5;
  margin: 0;
}

.success-msg {
  padding: 12px 16px;
  border-radius: var(--sg-radius-sm);
  color: #86efac;
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.2);
  font-size: 13px;
  margin-bottom: 20px;
}

.btn-resend {
  height: 44px;
  color: var(--sg-green);
  font-weight: 700;
}
</style>

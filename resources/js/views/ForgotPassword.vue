<template>
  <div class="login-page">
    <div class="bg-decor">
      <div class="bg-circle bg-circle-1"></div>
      <div class="bg-circle bg-circle-2"></div>
      <div class="bg-circle bg-circle-3"></div>
      <div class="bg-grid"></div>
    </div>

    <div class="login-container">
      <router-link to="/" class="back-link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="19" y1="12" x2="5" y2="12"/>
          <polyline points="12 19 5 12 12 5"/>
        </svg> Về trang chủ </router-link>

      <div class="login-card">
        <div class="card-brand">
          <div class="brand-icon">
            <svg width="36" height="36" viewBox="0 0 32 32" fill="none">
              <circle cx="16" cy="16" r="15" stroke="#22c55e" stroke-width="2"/>
              <path d="M16 4C20 8 22 12 22 16C22 20 20 24 16 28" stroke="#22c55e" stroke-width="1.5" fill="none"/>
              <path d="M16 4C12 8 10 12 10 16C10 20 12 24 16 28" stroke="#22c55e" stroke-width="1.5" fill="none"/>
              <line x1="4" y1="12" x2="28" y2="12" stroke="#22c55e" stroke-width="1.5"/>
              <line x1="4" y1="20" x2="28" y2="20" stroke="#22c55e" stroke-width="1.5"/>
            </svg>
          </div>
          <h1 class="brand-title">Sport<span class="accent">Go</span></h1>
          <p class="brand-sub" v-if="step === 'identify'">Quên mật khẩu</p>
          <p class="brand-sub" v-if="step === 'otp'">Xác thực OTP</p>
          <p class="brand-sub" v-if="step === 'reset'">Đặt lại mật khẩu</p>
        </div>

        <transition name="shake">
          <div v-if="error" class="error-msg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="15" y1="9" x2="9" y2="15"/>
              <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            {{ error }}
          </div>
        </transition>

        <transition name="fade">
          <div v-if="successMsg" class="success-msg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            {{ successMsg }}
          </div>
        </transition>

        <!-- STEP 1: IDENTIFY -->
        <form v-if="step === 'identify'" @submit.prevent="handleIdentify" class="login-form">
          <div style="width: 0; height: 0; overflow: hidden; position: absolute; z-index: -1;">
            <input type="text" autocomplete="username" tabindex="-1" />
            <input type="email" autocomplete="email" tabindex="-1" />
          </div>
          <p class="step-desc">Nhập tên đăng nhập, email hoặc số điện thoại để nhận mã OTP.</p>
          <div class="input-group">
            <div class="input-wrapper" :class="{ focused: focusedField === 'identifier' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              <input id="identifier" v-model="identifier" type="text" placeholder="Tên đăng nhập / Email / Số điện thoại" required autocomplete="nope" @focus="focusedField = 'identifier'" @blur="focusedField = ''" />
            </div>
          </div>
          <button type="submit" class="submit-btn" :class="{ loading: isLoading }" :disabled="isLoading">
            <span v-if="!isLoading">Gửi mã OTP</span>
            <span v-else class="spinner"></span>
          </button>
        </form>

        <!-- STEP 2: OTP -->
        <form v-if="step === 'otp'" @submit.prevent="handleVerifyOtp" class="login-form">
          <div style="width: 0; height: 0; overflow: hidden; position: absolute; z-index: -1;">
            <input type="text" autocomplete="one-time-code" tabindex="-1" />
          </div>
          <p class="step-desc">Vui lòng nhập mã OTP đã được gửi về email đăng ký tài khoản.</p>
          <div class="input-group">
            <div class="input-wrapper otp-wrapper" :class="{ focused: focusedField === 'otp' }">
              <input id="otp" v-model="otp" type="text" maxlength="6" placeholder="Nhập mã OTP 6 số" required autocomplete="one-time-code" @focus="focusedField = 'otp'" @blur="focusedField = ''" class="otp-input" />
            </div>
          </div>
          <button type="submit" class="submit-btn" :class="{ loading: isLoading }" :disabled="isLoading">
            <span v-if="!isLoading">Xác nhận</span>
            <span v-else class="spinner"></span>
          </button>
          <div class="resend-wrapper">
            <a href="#" @click.prevent="resendOtp" class="resend-link">Gửi lại mã</a>
          </div>
        </form>

        <!-- STEP 3: RESET -->
        <form v-if="step === 'reset'" @submit.prevent="handleReset" class="login-form">
          <div style="width: 0; height: 0; overflow: hidden; position: absolute; z-index: -1;">
            <input type="password" autocomplete="new-password" tabindex="-1" />
          </div>
          <div class="input-group">
            <label for="password">Mật khẩu mới <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'password' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input id="password" v-model="password" :type="showPassword ? 'text' : 'password'" placeholder="Nhập mật khẩu mới" required autocomplete="new-password" @focus="focusedField = 'password'" @blur="focusedField = ''" />
              <button type="button" class="toggle-pw" @click="showPassword = !showPassword" tabindex="-1">
                <svg v-if="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                  <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="input-group">
            <label for="password_confirmation">Xác nhận mật khẩu <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'password_confirmation' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input id="password_confirmation" v-model="password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" placeholder="Nhập lại mật khẩu" required autocomplete="new-password" @focus="focusedField = 'password_confirmation'" @blur="focusedField = ''" />
              <button type="button" class="toggle-pw" @click="showPasswordConfirm = !showPasswordConfirm" tabindex="-1">
                <svg v-if="!showPasswordConfirm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                  <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
              </button>
            </div>
          </div>
          <button type="submit" class="submit-btn" :class="{ loading: isLoading }" :disabled="isLoading">
            <span v-if="!isLoading">Cập nhật mật khẩu</span>
            <span v-else class="spinner"></span>
          </button>
        </form>

        <div class="register-section">
          <router-link to="/login" class="register-link">Quay lại đăng nhập</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { resetPassword, sendForgotOtp, verifyForgotOtp } from '../stores/auth.js';

export default {
  name: 'ForgotPasswordView',
  data() {
    return {
      step: 'identify', // identify, otp, reset
      identifier: '',
      otp: '',
      password: '',
      password_confirmation: '',
      error: '',
      successMsg: '',
      isLoading: false,
      showPassword: false,
      showPasswordConfirm: false,
      focusedField: '',
    };
  },
  methods: {
    async handleIdentify() {
      this.error = '';
      this.successMsg = '';
      if (!this.identifier) return;

      this.isLoading = true;
      try {
        const response = await sendForgotOtp(this.identifier);
        this.isLoading = false;
        this.step = 'otp';
        this.successMsg = response.message;
      } catch (error) {
        this.isLoading = false;
        this.error = error.message || 'Không thể gửi OTP.';
      }
    },
    async handleVerifyOtp() {
      this.error = '';
      this.successMsg = '';
      if (!this.otp) return;

      this.isLoading = true;
      try {
        const response = await verifyForgotOtp(this.identifier, this.otp);
        this.isLoading = false;
        this.step = 'reset';
        this.successMsg = response.message;
      } catch (error) {
        this.isLoading = false;
        this.error = error.message || 'Mã OTP không đúng.';
      }
    },
    async resendOtp() {
      this.error = '';
      this.successMsg = '';
      try {
        const response = await sendForgotOtp(this.identifier);
        this.successMsg = response.message;
      } catch (error) {
        this.error = error.message || 'Không thể gửi OTP.';
      }
    },
    async handleReset() {
      this.error = '';
      this.successMsg = '';

      if (!this.password) {
        this.error = 'Vui lòng nhập mật khẩu mới.';
        return;
      }
      if (this.password !== this.password_confirmation) {
        this.error = 'Xác nhận mật khẩu không khớp.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await resetPassword(this.identifier, this.otp, this.password, this.password_confirmation);
        this.isLoading = false;
        this.successMsg = response.message;
        setTimeout(() => this.$router.push('/login'), 1500);
      } catch (error) {
        this.isLoading = false;
        this.error = error.message || 'Không thể đặt lại mật khẩu.';
      }
    },
  }
};
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: var(--sg-darker);
  position: relative;
  overflow: hidden;
}

.bg-decor {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.bg-circle {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
}
.bg-circle-1 {
  width: 500px;
  height: 500px;
  background: rgba(34,197,94,.12);
  top: -150px;
  right: -100px;
}
.bg-circle-2 {
  width: 400px;
  height: 400px;
  background: rgba(34,197,94,.08);
  bottom: -100px;
  left: -100px;
}
.bg-circle-3 {
  width: 300px;
  height: 300px;
  background: rgba(22,163,106,.06);
  top: 40%;
  left: 50%;
  transform: translate(-50%, -50%);
}
.bg-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
  background-size: 40px 40px;
}

.login-container {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: rgba(255,255,255,.5);
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 24px;
  transition: var(--sg-transition);
}
.back-link:hover {
  color: var(--sg-green-light);
}

.login-card {
  background: rgba(255,255,255,.05);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 20px;
  padding: 40px 32px;
}

.card-brand {
  text-align: center;
  margin-bottom: 24px;
}
.brand-icon {
  margin-bottom: 16px;
}
.brand-title {
  font-size: 28px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -.5px;
}
.accent {
  color: var(--sg-green);
}
.brand-sub {
  font-size: 14px;
  color: rgba(255,255,255,.45);
  margin-top: 6px;
}

.step-desc {
  font-size: 13px;
  color: rgba(255,255,255,.6);
  text-align: center;
  margin-bottom: 20px;
  line-height: 1.5;
}

.error-msg, .success-msg {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  border-radius: var(--sg-radius-sm);
  font-size: 13px;
  margin-bottom: 20px;
}
.error-msg {
  background: rgba(239,68,68,.1);
  border: 1px solid rgba(239,68,68,.2);
  color: #fca5a5;
  animation: shakeAnim .4s ease;
}
.success-msg {
  background: rgba(34,197,94,.1);
  border: 1px solid rgba(34,197,94,.2);
  color: #86efac;
}
@keyframes shakeAnim {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-6px); }
  40% { transform: translateX(6px); }
  60% { transform: translateX(-4px); }
  80% { transform: translateX(4px); }
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.input-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,.7);
  margin-bottom: 8px;
}
.required {
  color: #ef4444;
  margin-left: 2px;
}
.input-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 16px;
  height: 48px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: var(--sg-radius-sm);
  transition: var(--sg-transition);
}
.input-wrapper svg {
  color: rgba(255,255,255,.3);
  min-width: 18px;
}
.input-wrapper.focused {
  border-color: var(--sg-green);
  background: rgba(34,197,94,.06);
  box-shadow: 0 0 0 3px rgba(34,197,94,.1);
}
.input-wrapper.focused svg {
  color: var(--sg-green);
}
.input-wrapper input {
  flex: 1;
  height: 100%;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: #fff;
}
.input-wrapper input::placeholder {
  color: rgba(255,255,255,.25);
}

.otp-wrapper {
  padding: 0;
}
.otp-input {
  text-align: center;
  letter-spacing: 8px;
  font-size: 18px !important;
  font-weight: 600;
}

.toggle-pw {
  padding: 4px;
  color: rgba(255,255,255,.3);
  transition: var(--sg-transition);
}
.toggle-pw:hover {
  color: rgba(255,255,255,.6);
}

.submit-btn {
  height: 48px;
  background: var(--sg-green);
  color: #fff;
  border-radius: var(--sg-radius-sm);
  font-size: 15px;
  font-weight: 700;
  transition: var(--sg-transition);
  display: flex;
  align-items: center;
  justify-content: center;
}
.submit-btn:hover:not(:disabled) {
  background: var(--sg-green-dark);
  box-shadow: 0 4px 20px rgba(34,197,94,.4);
  transform: translateY(-1px);
}
.submit-btn:disabled {
  opacity: .7;
  cursor: not-allowed;
}
.spinner {
  width: 20px;
  height: 20px;
  border: 3px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

.resend-wrapper {
  text-align: center;
  margin-top: -8px;
}
.resend-link {
  font-size: 13px;
  color: var(--sg-green);
  font-weight: 500;
}
.resend-link:hover {
  color: var(--sg-green-light);
  text-decoration: underline;
}

.register-section {
  text-align: center;
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid rgba(255,255,255,.08);
  font-size: 14px;
  color: rgba(255,255,255,.4);
}
.register-link {
  color: var(--sg-green);
  font-weight: 600;
  margin-left: 4px;
  transition: var(--sg-transition);
}
.register-link:hover {
  color: var(--sg-green-light);
}

.fade-enter-active, .fade-leave-active {
  transition: opacity .3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

@media (max-width: 480px) {
  .login-card { padding: 32px 24px; }
  .brand-title { font-size: 24px; }
}
</style>




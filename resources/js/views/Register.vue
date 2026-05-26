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
          <p class="brand-sub">Tạo tài khoản mới</p>
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

        <form v-if="step === 'register'" @submit.prevent="handleRegister" class="login-form" autocomplete="off">
          <div style="width: 0; height: 0; overflow: hidden; position: absolute; z-index: -1;">
            <input type="text" autocomplete="username" tabindex="-1" />
            <input type="email" autocomplete="email" tabindex="-1" />
            <input type="password" autocomplete="current-password" tabindex="-1" />
            <input type="password" autocomplete="new-password" tabindex="-1" />
          </div>
          <div class="input-group">
            <label for="username">Tên tài khoản <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'username' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              <input id="username" v-model="form.username" type="text" placeholder="Nhập tên tài khoản" required autocomplete="nope" @focus="focusedField = 'username'" @blur="focusedField = ''" />
            </div>
            <span class="field-hint">Dùng để đăng nhập, khác với họ tên hiển thị.</span>
          </div>

          <div class="input-group">
            <label for="full_name">Họ tên <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'full_name' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
              <input id="full_name" v-model="form.full_name" type="text" placeholder="Nhập họ tên" required autocomplete="nope" @focus="focusedField = 'full_name'" @blur="focusedField = ''" />
            </div>
          </div>

          <div class="input-group">
            <label for="phone">Số điện thoại <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'phone' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
              <input id="phone" v-model="form.phone" type="text" placeholder="Nhập số điện thoại" required autocomplete="nope" @focus="focusedField = 'phone'" @blur="focusedField = ''" />
            </div>
            <span class="field-hint">Số điện thoại chính dùng khi đăng ký và đăng nhập.</span>
          </div>

          <div class="input-group">
            <label for="email">Email</label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'email' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
              </svg>
              <input id="email" v-model="form.email" type="email" placeholder="Nhập email" autocomplete="nope" @focus="focusedField = 'email'" @blur="focusedField = ''" />
            </div>
            <span class="field-hint">Email dùng để nhận mã xác thực và reset mật khẩu.</span>
          </div>

          <div class="input-group">
            <label for="password">Mật khẩu <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'password' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Nhập mật khẩu" required autocomplete="new-password" @focus="focusedField = 'password'" @blur="focusedField = ''" />
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
              <input id="password_confirmation" v-model="form.password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" placeholder="Nhập lại mật khẩu" required autocomplete="new-password" @focus="focusedField = 'password_confirmation'" @blur="focusedField = ''" />
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
            <span v-if="!isLoading">Đăng ký</span>
            <span v-else class="spinner"></span>
          </button>
          
          <button type="button" class="btn-google" @click.prevent="handleGoogleLogin">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Đăng ký bằng Google
          </button>
        </form>

        <form v-else @submit.prevent="handleVerifyOtp" class="login-form">
          <p class="field-hint">Nhập mã OTP 6 số đã được gửi về email {{ form.email }}.</p>
          <div class="input-group">
            <label for="otp">Mã OTP <span class="required">*</span></label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'otp' }">
              <input id="otp" v-model="otp" type="text" maxlength="6" placeholder="Nhập mã OTP" required autocomplete="one-time-code" @focus="focusedField = 'otp'" @blur="focusedField = ''" />
            </div>
          </div>
          <button type="submit" class="submit-btn" :class="{ loading: isLoading }" :disabled="isLoading">
            <span v-if="!isLoading">Xác thực tài khoản</span>
            <span v-else class="spinner"></span>
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
import { register, verifyRegisterOtp, loginWithGoogle } from '../stores/auth.js';

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
        password_confirmation: ''
      },
      step: 'register',
      otp: '',
      error: '',
      successMsg: '',
      isLoading: false,
      showPassword: false,
      showPasswordConfirm: false,
      focusedField: '',
    };
  },
  methods: {
    async handleRegister() {
      this.error = '';
      this.successMsg = '';

      if (!this.form.username || !this.form.full_name || !this.form.phone || !this.form.email || !this.form.password) {
        this.error = 'Vui lòng nhập đầy đủ các trường bắt buộc (*).';
        return;
      }
      if (this.form.password !== this.form.password_confirmation) {
        this.error = 'Xác nhận mật khẩu không khớp.';
        return;
      }

      this.isLoading = true;

      try {
        const response = await register(this.form);
        this.isLoading = false;
        this.step = 'otp';
        this.successMsg = response.message;
      } catch (error) {
        this.isLoading = false;
        this.error = error.message || 'Đăng ký không thành công.';
      }
    },
    async handleVerifyOtp() {
      this.error = '';
      this.successMsg = '';
      this.isLoading = true;

      try {
        const response = await verifyRegisterOtp(this.form.email, this.otp);
        this.isLoading = false;
        this.successMsg = response.message;
        setTimeout(() => this.$router.push('/login'), 1500);
      } catch (error) {
        this.isLoading = false;
        this.error = error.message || 'Mã OTP không đúng.';
      }
    },
    handleGoogleLogin() {
      loginWithGoogle();
    },
  },
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
  max-width: 480px;
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
  margin-bottom: 32px;
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
.toggle-pw {
  padding: 4px;
  color: rgba(255,255,255,.3);
  transition: var(--sg-transition);
}
.toggle-pw:hover {
  color: rgba(255,255,255,.6);
}
.field-hint {
  display: block;
  font-size: 12px;
  color: rgba(255,255,255,.4);
  margin-top: 6px;
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
  margin-top: 8px;
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

.btn-google {
  height: 48px;
  background: rgba(255,255,255,.05);
  color: #fff;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: var(--sg-radius-sm);
  font-size: 15px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: var(--sg-transition);
}
.btn-google:hover {
  background: rgba(255,255,255,.1);
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





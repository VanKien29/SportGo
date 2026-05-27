<template>
  <main class="admin-auth-page">
    <section class="auth-shell">
      <aside class="auth-info">
        <router-link to="/admin/login" class="home-link">Quay lại đăng nhập Admin</router-link>

        <div class="brand-block">
          <div class="brand-mark">SG</div>
          <div>
            <p class="brand-kicker">SportGo Admin</p>
            <h1>Khôi phục quyền truy cập</h1>
          </div>
        </div>

        <div class="step-list">
          <div :class="['step-item', { active: step === 'identify', done: step !== 'identify' }]">
            <span>1</span>
            <p>Nhập tài khoản</p>
          </div>
          <div :class="['step-item', { active: step === 'otp', done: step === 'reset' }]">
            <span>2</span>
            <p>Xác nhận OTP</p>
          </div>
          <div :class="['step-item', { active: step === 'reset' }]">
            <span>3</span>
            <p>Đặt mật khẩu mới</p>
          </div>
        </div>
      </aside>

      <section class="form-panel">
        <div class="form-heading">
          <p class="eyebrow">Tài khoản quản trị</p>
          <h2>{{ title }}</h2>
          <p>{{ subtitle }}</p>
        </div>

        <div v-if="error" class="alert-error">{{ error }}</div>
        <div v-if="successMsg" class="alert-success">{{ successMsg }}</div>

        <form v-if="step === 'identify'" class="auth-form" autocomplete="off" @submit.prevent="handleSendOtp">
          <div class="field">
            <label for="admin-forgot-identifier">Tên đăng nhập / Email / Số điện thoại</label>
            <input
              id="admin-forgot-identifier"
              v-model.trim="identifier"
              type="text"
              autocomplete="username"
              placeholder="Nhập tài khoản quản trị"
              required
            />
          </div>

          <button class="primary-btn" type="submit" :disabled="isLoading">
            <span v-if="!isLoading">Gửi mã OTP</span>
            <span v-else class="spinner"></span>
          </button>

          <router-link to="/admin/login" class="plain-link">Tôi nhớ mật khẩu</router-link>
        </form>

        <form v-else-if="step === 'otp'" class="auth-form" autocomplete="off" @submit.prevent="handleVerifyOtp">
          <div class="field">
            <label for="admin-forgot-otp">Mã OTP</label>
            <input
              id="admin-forgot-otp"
              v-model.trim="otp"
              type="text"
              inputmode="numeric"
              maxlength="6"
              autocomplete="one-time-code"
              placeholder="Nhập mã OTP 6 số"
              required
            />
          </div>

          <div class="otp-actions">
            <button class="primary-btn" type="submit" :disabled="isLoading">
              <span v-if="!isLoading">Xác nhận OTP</span>
              <span v-else class="spinner"></span>
            </button>
            <button class="secondary-btn" type="button" :disabled="isLoading" @click="handleResendOtp">
              Gửi lại mã OTP
            </button>
          </div>

          <button class="text-btn" type="button" :disabled="isLoading" @click="goBackToIdentify">
            Đổi tài khoản nhận mã
          </button>
        </form>

        <form v-else class="auth-form" autocomplete="off" @submit.prevent="handleResetPassword">
          <div class="field">
            <label for="admin-new-password">Mật khẩu mới</label>
            <input
              id="admin-new-password"
              v-model="password"
              type="password"
              autocomplete="new-password"
              placeholder="Nhập mật khẩu mới"
              required
            />
          </div>

          <div class="field">
            <label for="admin-new-password-confirm">Xác nhận mật khẩu</label>
            <input
              id="admin-new-password-confirm"
              v-model="passwordConfirmation"
              type="password"
              autocomplete="new-password"
              placeholder="Nhập lại mật khẩu mới"
              required
            />
          </div>

          <button class="primary-btn" type="submit" :disabled="isLoading">
            <span v-if="!isLoading">Đặt lại mật khẩu Admin</span>
            <span v-else class="spinner"></span>
          </button>
        </form>
      </section>
    </section>
  </main>
</template>

<script>
import { resetAdminPassword, sendAdminForgotOtp, verifyAdminForgotOtp } from '../../stores/auth.js';

export default {
  name: 'AdminForgotPassword',
  data() {
    return {
      step: 'identify',
      identifier: '',
      otp: '',
      password: '',
      passwordConfirmation: '',
      error: '',
      successMsg: '',
      isLoading: false,
    };
  },
  computed: {
    title() {
      return {
        identify: 'Quên mật khẩu Admin',
        otp: 'Nhập mã OTP',
        reset: 'Đặt mật khẩu mới',
      }[this.step];
    },
    subtitle() {
      return {
        identify: 'Nhập tài khoản quản trị. SportGo sẽ gửi mã OTP đến email đã đăng ký.',
        otp: `Mã OTP đã được gửi đến email của tài khoản ${this.identifier}.`,
        reset: 'Tạo mật khẩu mới cho tài khoản quản trị.',
      }[this.step];
    },
  },
  methods: {
    async handleSendOtp() {
      this.error = '';
      this.successMsg = '';

      if (!this.identifier) {
        this.error = 'Vui lòng nhập tài khoản quản trị.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await sendAdminForgotOtp(this.identifier);
        this.successMsg = response.message || 'Mã OTP đã được gửi về email quản trị.';
        this.step = 'otp';
      } catch (error) {
        this.error = error.message || 'Không thể gửi mã OTP.';
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

      if (!this.otp) {
        this.error = 'Vui lòng nhập mã OTP.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await verifyAdminForgotOtp(this.identifier, this.otp);
        this.successMsg = response.message || 'OTP hợp lệ. Vui lòng đặt mật khẩu mới.';
        this.step = 'reset';
      } catch (error) {
        this.error = error.message || 'Mã OTP không đúng.';
      } finally {
        this.isLoading = false;
      }
    },
    async handleResetPassword() {
      this.error = '';
      this.successMsg = '';

      if (this.password !== this.passwordConfirmation) {
        this.error = 'Xác nhận mật khẩu không khớp.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await resetAdminPassword(this.identifier, this.otp, this.password, this.passwordConfirmation);
        this.successMsg = response.message || 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.';
        setTimeout(() => this.$router.push('/admin/login'), 1200);
      } catch (error) {
        this.error = error.message || 'Không thể đặt lại mật khẩu Admin.';
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
.admin-auth-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 32px 20px;
  background: #eef2f6;
  color: #111827;
}

.auth-shell {
  width: min(940px, 100%);
  min-height: 590px;
  display: grid;
  grid-template-columns: 0.95fr 1.05fr;
  border: 1px solid #d8dee8;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.16);
}

.auth-info {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 34px;
  background: #0f172a;
  color: #f8fafc;
}

.home-link {
  width: fit-content;
  color: #cbd5e1;
  font-size: 14px;
  font-weight: 800;
}

.home-link:hover {
  color: #86efac;
}

.brand-block {
  display: grid;
  gap: 18px;
}

.brand-mark {
  width: 52px;
  height: 52px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: #16a34a;
  color: #fff;
  font-weight: 900;
}

.brand-kicker {
  margin: 0 0 8px;
  color: #86efac;
  font-size: 13px;
  font-weight: 900;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.brand-block h1 {
  margin: 0;
  max-width: 320px;
  color: #fff;
  font-size: 34px;
  line-height: 1.12;
  font-weight: 900;
}

.step-list {
  display: grid;
  gap: 14px;
}

.step-item {
  display: grid;
  grid-template-columns: 34px 1fr;
  gap: 12px;
  align-items: center;
  color: #cbd5e1;
}

.step-item span {
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border: 1px solid #334155;
  border-radius: 8px;
  background: #111827;
  color: #cbd5e1;
  font-size: 13px;
  font-weight: 900;
}

.step-item p {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
}

.step-item.active span,
.step-item.done span {
  border-color: #16a34a;
  background: #16a34a;
  color: #fff;
}

.step-item.active p,
.step-item.done p {
  color: #fff;
}

.form-panel {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 48px;
}

.form-heading {
  margin-bottom: 26px;
}

.eyebrow {
  margin: 0 0 8px;
  color: #16a34a;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.form-heading h2 {
  margin: 0;
  color: #0f172a;
  font-size: 28px;
  font-weight: 900;
}

.form-heading p {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 14px;
  line-height: 1.55;
}

.alert-error,
.alert-success {
  margin-bottom: 18px;
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 800;
  line-height: 1.45;
}

.alert-error {
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.alert-success {
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
  color: #166534;
}

.auth-form {
  display: grid;
  gap: 18px;
}

.field {
  display: grid;
  gap: 8px;
}

.field label {
  color: #334155;
  font-size: 13px;
  font-weight: 900;
}

.field input {
  width: 100%;
  height: 46px;
  padding: 0 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: #fff;
  color: #0f172a;
  font-size: 14px;
}

.field input:focus {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.14);
}

.primary-btn,
.secondary-btn {
  min-height: 48px;
  padding: 0 16px;
  border-radius: 8px;
  font-weight: 900;
}

.primary-btn {
  background: #16a34a;
  color: #fff;
}

.secondary-btn {
  border: 1px solid #16a34a;
  background: #f0fdf4;
  color: #166534;
}

.primary-btn:disabled,
.secondary-btn:disabled,
.text-btn:disabled {
  opacity: .72;
  cursor: not-allowed;
}

.otp-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.plain-link,
.text-btn {
  width: fit-content;
  justify-self: center;
  color: #15803d;
  font-size: 13px;
  font-weight: 900;
}

.text-btn {
  background: transparent;
}

.plain-link:hover,
.text-btn:hover {
  color: #166534;
}

.spinner {
  width: 20px;
  height: 20px;
  display: inline-block;
  border: 3px solid rgba(255, 255, 255, .35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 760px) {
  .auth-shell {
    min-height: auto;
    grid-template-columns: 1fr;
  }

  .auth-info {
    gap: 28px;
  }

  .form-panel {
    padding: 34px 24px;
  }

  .otp-actions {
    grid-template-columns: 1fr;
  }
}
</style>

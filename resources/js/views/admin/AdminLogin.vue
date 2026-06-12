<template>
  <main class="admin-auth-page">
    <section class="auth-shell">
      <aside class="auth-info">
        <router-link to="/" class="home-link">Về trang chủ</router-link>

        <div class="brand-block">
          <div class="brand-mark">SG</div>
          <div>
            <p class="brand-kicker">SportGo Admin</p>
            <h1>Quản trị hệ thống</h1>
          </div>
        </div>

        <div class="info-list">
          <div class="info-row">
            <span class="info-dot"></span>
            <span>Chỉ dành cho quản trị viên và nhân viên hệ thống.</span>
          </div>
          <div class="info-row">
            <span class="info-dot"></span>
            <span>Tài khoản user, chủ sân và nhân viên sân không thể đăng nhập tại đây.</span>
          </div>
        </div>
      </aside>

      <section class="form-panel">
        <div class="form-heading">
          <p class="eyebrow">Khu vực quản trị</p>
          <h2>Đăng nhập Admin</h2>
          <p>Nhập tài khoản quản trị để tiếp tục.</p>
        </div>

        <div v-if="error" class="alert-error">{{ error }}</div>

        <form class="auth-form" autocomplete="off" @submit.prevent="handleSubmit">
          <div class="field">
            <label for="admin-login">Tên đăng nhập / Email / Số điện thoại</label>
            <input
              id="admin-login"
              v-model.trim="loginValue"
              type="text"
              autocomplete="username"
              placeholder="Nhập tài khoản quản trị"
              required
            />
          </div>

          <div class="field">
            <label for="admin-password">Mật khẩu</label>
            <div class="password-field">
              <input
                id="admin-password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="Nhập mật khẩu"
                required
              />
              <button type="button" @click="showPassword = !showPassword">
                {{ showPassword ? 'Ẩn' : 'Hiện' }}
              </button>
            </div>
          </div>

          <button class="primary-btn" type="submit" :disabled="isLoading">
            <span v-if="!isLoading">Đăng nhập quản trị</span>
            <span v-else class="spinner"></span>
          </button>

          <router-link to="/admin/forgot-password" class="forgot-link">
            Quên mật khẩu admin?
          </router-link>
        </form>
      </section>
    </section>
  </main>
</template>

<script>
import { adminLogin } from '../../stores/auth.js';

export default {
  name: 'AdminLogin',
  data() {
    return {
      loginValue: '',
      password: '',
      showPassword: false,
      isLoading: false,
      error: '',
    };
  },
  methods: {
    async handleSubmit() {
      this.error = '';
      this.isLoading = true;

      try {
        const auth = await adminLogin(this.loginValue, this.password);
        this.$router.push(auth.redirect_to || '/admin/dashboard');
      } catch (error) {
        const details = error.data || {};
        const lockDetails = [
          details.status_reason,
          details.lock_type ? `Loại khóa: ${details.lock_type}` : null,
          details.locked_until ? `Khóa đến: ${details.locked_until}` : null,
        ].filter(Boolean).join(' - ');

        this.error = lockDetails
          ? `${error.message} ${lockDetails}`
          : (error.message || 'Không thể đăng nhập quản trị.');
      } finally {
        this.isLoading = false;
      }
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
  background:
    linear-gradient(180deg, rgba(247, 251, 245, 0.9), rgba(238, 246, 240, 0.98)),
    #eef6f0;
  color: #111827;
}

.auth-shell {
  width: min(920px, 100%);
  min-height: 560px;
  display: grid;
  grid-template-columns: 0.92fr 1.08fr;
  border: 1px solid #dce8dc;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 24px 70px rgba(23, 34, 27, 0.14);
}

.auth-info {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 34px;
  background:
    linear-gradient(135deg, rgba(33, 107, 52, 0.96), rgba(47, 158, 68, 0.9)),
    #2f9e44;
  color: #f8fff9;
}

.home-link {
  width: fit-content;
  color: rgba(248, 255, 249, 0.78);
  font-size: 14px;
  font-weight: 800;
}

.home-link:hover {
  color: #fff;
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
  background: rgba(255, 255, 255, 0.92);
  color: #216b34;
  font-weight: 900;
}

.brand-kicker {
  margin: 0 0 8px;
  color: #d8ffe1;
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

.info-list {
  display: grid;
  gap: 14px;
  color: rgba(248, 255, 249, 0.78);
  font-size: 14px;
  line-height: 1.55;
}

.info-row {
  display: grid;
  grid-template-columns: 10px 1fr;
  gap: 10px;
}

.info-dot {
  width: 8px;
  height: 8px;
  margin-top: 7px;
  border-radius: 50%;
  background: #d8ffe1;
}

.form-panel {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 48px;
}

.form-heading {
  margin-bottom: 28px;
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
}

.alert-error {
  margin-bottom: 18px;
  padding: 12px 14px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fef2f2;
  color: #991b1b;
  font-size: 13px;
  font-weight: 800;
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
  border-color: #2f9e44;
  box-shadow: 0 0 0 3px rgba(47, 158, 68, 0.14);
}

.password-field {
  display: flex;
}

.password-field input {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}

.password-field button {
  min-width: 70px;
  padding: 0 14px;
  border: 1px solid #cbd5e1;
  border-left: 0;
  border-top-right-radius: 8px;
  border-bottom-right-radius: 8px;
  background: #f8fafc;
  color: #475569;
  font-weight: 900;
}

.primary-btn {
  height: 48px;
  border-radius: 8px;
  background: #2f9e44;
  color: #fff;
  font-weight: 900;
}

.primary-btn:disabled {
  opacity: .72;
  cursor: not-allowed;
}

.forgot-link {
  width: fit-content;
  justify-self: center;
  color: #15803d;
  font-size: 13px;
  font-weight: 900;
}

.forgot-link:hover {
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
}
</style>

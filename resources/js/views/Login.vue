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
        </svg>
        Về trang chủ
      </router-link>

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
          <p class="brand-sub">Đăng nhập để tiếp tục</p>
        </div>

        <transition name="shake">
          <div v-if="error" class="error-msg">{{ error }}</div>
        </transition>

        <form @submit.prevent="handleLogin" class="login-form" autocomplete="off">
          <div class="input-group">
            <label for="login">Email / Số điện thoại / Username</label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'login' }">
              <input
                id="login"
                ref="loginRef"
                v-model="loginValue"
                type="text"
                placeholder="Nhập email, số điện thoại hoặc username"
                required
                autocomplete="username"
                @focus="focusedField = 'login'"
                @blur="focusedField = ''"
              />
            </div>
          </div>

          <div class="input-group">
            <label for="password">Mật khẩu</label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'password' }">
              <input
                id="password"
                ref="passwordRef"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Nhập mật khẩu"
                required
                autocomplete="current-password"
                @focus="focusedField = 'password'"
                @blur="focusedField = ''"
              />
              <button type="button" class="toggle-pw" @click="showPassword = !showPassword" tabindex="-1">
                {{ showPassword ? 'Ẩn' : 'Hiện' }}
              </button>
            </div>
          </div>

          <div class="form-footer">
            <router-link to="/forgot-password" class="forgot-link">Quên mật khẩu?</router-link>
          </div>

          <button type="submit" class="submit-btn" :class="{ loading: isLoading }" :disabled="isLoading">
            <span v-if="!isLoading">Đăng nhập</span>
            <span v-else class="spinner"></span>
          </button>

          <button type="button" class="btn-google" @click="handleGoogleLogin">
            Đăng nhập bằng Google
          </button>
        </form>

        <div class="register-section">
          <span>Chưa có tài khoản?</span>
          <router-link to="/register" class="register-link">Đăng ký</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { login, loginWithGoogle } from '../stores/auth.js';

export default {
  name: 'LoginView',
  data() {
    return {
      loginValue: '',
      password: '',
      error: '',
      isLoading: false,
      showPassword: false,
      focusedField: '',
    };
  },
  methods: {
    async handleLogin() {
      this.error = '';
      this.isLoading = true;

      try {
        const auth = await login(this.loginValue.trim(), this.password);
        this.$router.push(auth.redirect_to || '/');
      } catch (error) {
        const details = error.data || {};
        
        if (details.lock_type) {
          const lockedBy = details.lock_type === 'auto' ? 'hệ thống' : 'quản trị viên';
          const reasonText = details.status_reason ? ` bởi lí do: ${details.status_reason}` : '';
          const untilText = details.locked_until ? ` - Khóa đến: ${details.locked_until}` : '';
          
          this.error = `Bạn đã bị khóa bởi ${lockedBy}${reasonText}${untilText}`;
        } else {
          this.error = error.message || 'Sai tài khoản hoặc mật khẩu.';
        }
      } finally {
        this.isLoading = false;
      }
    },
    handleGoogleLogin() {
      loginWithGoogle();
    },
  },
};
</script>

<style src="../../css/auth/login.css" scoped></style>

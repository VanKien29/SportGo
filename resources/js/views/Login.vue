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
          <p class="brand-sub">Đăng nhập để tiếp tục</p>
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


        <form @submit.prevent="handleLogin" class="login-form" autocomplete="off">
          <div style="width: 0; height: 0; overflow: hidden; position: absolute; z-index: -1;">
            <input type="text" autocomplete="username" tabindex="-1" />
            <input type="password" autocomplete="current-password" tabindex="-1" />
          </div>
          <div class="input-group">
            <label for="username">Tên đăng nhập</label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'username' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              <input
                id="username"
                ref="usernameRef"
                v-model="username"
                type="text"
                placeholder="Nhập tên đăng nhập" required autocomplete="nope"
                @focus="focusedField = 'username'"
                @blur="focusedField = ''"
              />
            </div>
          </div>

          <div class="input-group">
            <label for="password">Mật khẩu</label>
            <div class="input-wrapper" :class="{ focused: focusedField === 'password' }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input
                id="password"
                ref="passwordRef"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Nhập mật khẩu"
                required
                autocomplete="new-password"
                @focus="focusedField = 'password'"
                @blur="focusedField = ''"
              />
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

          <div class="form-footer">
            <router-link to="/forgot-password" class="forgot-link">Quên mật khẩu?</router-link>
          </div>

          <button type="submit" class="submit-btn" :class="{ loading: isLoading }" :disabled="isLoading">
            <span v-if="!isLoading">Đăng nhập</span>
            <span v-else class="spinner"></span>
          </button>

          <button type="button" class="btn-google" @click="handleGoogleLogin"> Đăng nhập bằng Google </button>
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
import LoginLogic from '../controllers/auth/Login.js';
export default LoginLogic;
</script>
<style src="../../css/auth/login.css" scoped></style>

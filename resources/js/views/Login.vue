<template>
  <AuthLayout
    class="sg-account-auth"
    title="SportGo"
    subtitle="Chào mừng bạn trở lại với SportGo"
    quote-title="Khởi động đam mê"
    quote-text="Đặt sân thể thao dễ dàng, kết nối bạn chơi và thỏa sức bứt phá từng điểm số."
    :image-src="authVisual"
    back-to="/"
  >
    <form @submit.prevent="handleLogin" class="sg-lovebirds-form flex flex-col gap-5 w-full text-left" autocomplete="off" novalidate>
      <!-- Error Alert -->
      <transition name="shake">
        <div v-if="error" class="flex items-center gap-2.5 p-3 rounded-lg border border-red-200 bg-red-50 text-red-600 text-xs font-medium">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
          <span>
            {{ error }}
            <router-link
              v-if="pendingVerificationEmail"
              class="sg-lovebirds-verification-link"
              :to="{ name: 'verify-email', query: { email: pendingVerificationEmail } }"
            >
              Mở trang xác thực email
            </router-link>
          </span>
        </div>
      </transition>

      <!-- Field: Users name or Email -->
      <div class="sg-lovebirds-field">
        <label for="login" class="sg-lovebirds-label">Tài khoản hoặc Email</label>
        <input
          id="login"
          ref="loginInput"
          v-model.trim="loginValue"
          class="sg-lovebirds-input"
          :class="{ 'is-error': fieldErrors.login }"
          type="text"
          placeholder="Email, số điện thoại hoặc tên đăng nhập"
          autocomplete="username"
          autocapitalize="none"
          spellcheck="false"
          @input="clearFieldError('login')"
        />
        <p v-if="fieldErrors.login" class="sg-lovebirds-error">{{ fieldErrors.login }}</p>
      </div>

      <!-- Field: Password -->
      <div class="sg-lovebirds-field">
        <label for="password" class="sg-lovebirds-label">Mật khẩu</label>
        <div class="sg-lovebirds-password-wrap">
          <input
            id="password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            class="sg-lovebirds-input"
            :class="{ 'is-error': fieldErrors.password }"
            placeholder="••••••••"
            autocomplete="current-password"
            @input="clearFieldError('password')"
          />
          <button
            type="button"
            class="sg-lovebirds-eye"
            @click="showPassword = !showPassword"
            aria-label="Xem mật khẩu"
          >
            <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="flex justify-end mt-1.5">
          <router-link to="/forgot-password" class="sg-lovebirds-forgot">Quên mật khẩu?</router-link>
        </div>
        <p v-if="fieldErrors.password" class="sg-lovebirds-error">{{ fieldErrors.password }}</p>
      </div>

      <!-- Action Button: Pill Shape -->
      <div class="flex justify-center mt-2">
        <button class="sg-lovebirds-submit-btn" type="submit" :disabled="isLoading">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang đăng nhập...' : 'Đăng nhập' }}</span>
        </button>
      </div>

      <!-- Divider: hoặc -->
      <div class="sg-lovebirds-divider"><span>hoặc</span></div>

      <!-- Social 1-Click: Đăng nhập với Google -->
      <div class="flex justify-center">
        <button class="sg-lovebirds-google-btn" type="button" @click="handleGoogleLogin">
          <svg class="sg-google-icon shrink-0" width="17" height="17" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
          </svg>
          <span>Đăng nhập với Google</span>
        </button>
      </div>

      <!-- Register Switch -->
      <p class="sg-lovebirds-switch text-center mt-3">
        Chưa có tài khoản SportGo? <router-link to="/register">Đăng ký ngay</router-link>
      </p>
    </form>
  </AuthLayout>
</template>

<script>
import { login, loginWithGoogle } from '../stores/auth.js';
import AppIcon from '../components/AppIcon.vue';
import AuthLayout from '../components/ui/AuthLayout.vue';

export default {
  name: 'LoginView',
  components: {
    AppIcon,
    AuthLayout,
  },
  data() {
    return {
      authVisual: '/images/auth/sportgo_art.png',
      loginValue: '',
      password: '',
      showPassword: false,
      error: '',
      pendingVerificationEmail: '',
      fieldErrors: {
        login: '',
        password: '',
      },
      isLoading: false,
    };
  },
  mounted() {
    const googleError = String(this.$route.query.google_error || '');
    if (googleError) {
      this.error = {
        invalid_account: 'Không thể xác minh tài khoản Google này.',
        locked: 'Tài khoản liên kết Google đang bị khóa. Vui lòng liên hệ hỗ trợ.',
        inactive: 'Tài khoản liên kết Google chưa hoạt động.',
      }[googleError] || 'Đăng nhập Google không thành công. Vui lòng thử lại.';
    }
    this.$refs.loginInput?.focus();
  },
  methods: {
    clearFieldError(field) {
      this.fieldErrors[field] = '';
      if (!this.fieldErrors.login && !this.fieldErrors.password) this.error = '';
    },
    setFieldError(field, message) {
      this.fieldErrors[field] = message;
      this.error = message;
      if (field === 'login') this.$nextTick(() => this.$refs.loginInput?.focus());
    },
    safeRequestedRedirect(auth) {
      const requested = String(this.$route.query.redirect || '');
      const isSafeLocalPath = requested.startsWith('/') && !requested.startsWith('//');

      if (auth.role_group === 'user' && isSafeLocalPath) return requested;
      return auth.redirect_to || '/';
    },
    async handleLogin() {
      this.error = '';
      this.pendingVerificationEmail = '';
      this.fieldErrors.login = '';
      this.fieldErrors.password = '';

      if (!this.loginValue.trim()) {
        this.setFieldError('login', 'Vui lòng nhập email, số điện thoại hoặc tên đăng nhập.');
        return;
      }
      if (!this.password) {
        this.setFieldError('password', 'Vui lòng nhập mật khẩu.');
        return;
      }

      this.isLoading = true;

      try {
        const auth = await login(this.loginValue.trim(), this.password);
        await this.$router.push(this.safeRequestedRedirect(auth));
      } catch (requestError) {
        const details = requestError.data || {};

        if (details.lock_type) {
          const lockedBy = details.lock_type === 'auto' ? 'hệ thống' : 'quản trị viên';
          const reasonText = details.status_reason ? ` Lý do: ${details.status_reason}.` : '';
          const untilText = details.locked_until ? ` Khóa đến ${details.locked_until}.` : '';
          this.setFieldError('login', `Tài khoản đang bị khóa bởi ${lockedBy}.${reasonText}${untilText}`);
        } else if (details.verification_email) {
          this.pendingVerificationEmail = details.verification_email;
          this.setFieldError('login', requestError.message || 'Tài khoản chưa xác thực email.');
        } else {
          this.setFieldError('login', requestError.message || 'Sai tài khoản hoặc mật khẩu.');
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


<template>
  <AuthLayout
    class="sg-account-auth sg-admin-auth"
    title="SportGo Admin"
    subtitle="Cổng đăng nhập Quản trị hệ thống"
    quote-title="Vận hành & Giám sát"
    quote-text="Hệ thống điều hành và phân quyền dịch vụ thể thao tập trung SportGo."
    :image-src="authVisual"
    back-to="/"
  >
    <form @submit.prevent="handleSubmit" class="sg-lovebirds-form flex flex-col gap-5 w-full text-left" autocomplete="off" novalidate>
      <!-- Error Alert -->
      <transition name="shake">
        <div v-if="error" class="flex items-center gap-2.5 p-3 rounded-lg border border-red-200 bg-red-50 text-red-600 text-xs font-medium">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span>{{ error }}</span>
        </div>
      </transition>

      <!-- Field: Admin Username / Email / Phone -->
      <div class="sg-lovebirds-field">
        <label for="admin-login" class="sg-lovebirds-label">Tài khoản Quản trị</label>
        <input
          id="admin-login"
          ref="loginInput"
          v-model.trim="loginValue"
          class="sg-lovebirds-input"
          :class="{ 'is-error': fieldErrors.login }"
          type="text"
          placeholder="Tên đăng nhập, email hoặc số điện thoại"
          autocomplete="username"
          autocapitalize="none"
          spellcheck="false"
          @input="clearFieldError('login')"
        />
        <p v-if="fieldErrors.login" class="sg-lovebirds-error">{{ fieldErrors.login }}</p>
      </div>

      <!-- Field: Password -->
      <div class="sg-lovebirds-field">
        <label for="admin-password" class="sg-lovebirds-label">Mật khẩu</label>
        <div class="sg-lovebirds-password-wrap">
          <input
            id="admin-password"
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
            <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
              <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
              <path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
              <line x1="2" x2="22" y1="2" y2="22"/>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div class="flex justify-end mt-1.5">
          <router-link to="/admin/forgot-password" class="sg-lovebirds-forgot">Quên mật khẩu quản trị?</router-link>
        </div>
        <p v-if="fieldErrors.password" class="sg-lovebirds-error">{{ fieldErrors.password }}</p>
      </div>

      <!-- Submit Action Button: Pill Shape -->
      <div class="flex justify-center mt-2">
        <button class="sg-lovebirds-submit-btn" type="submit" :disabled="isLoading">
          <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
          <span>{{ isLoading ? 'Đang đăng nhập...' : 'Đăng nhập Quản trị' }}</span>
        </button>
      </div>

      <!-- Security Notice Badge -->
      <div class="text-center mt-3">
        <span class="inline-flex items-center gap-1.5 text-[11px] text-slate-500 bg-slate-50 border border-slate-200/70 rounded-full px-3 py-1 font-medium">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Cổng bảo mật dành riêng cho Quản trị viên
        </span>
      </div>

      <!-- Switch to Client/Owner Login -->
      <p class="sg-lovebirds-switch text-center mt-3">
        Bạn là khách hàng hoặc chủ sân? <router-link to="/login">Đăng nhập tại đây</router-link>
      </p>
    </form>
  </AuthLayout>
</template>

<script>
import { adminLogin } from '../../stores/auth.js';
import AuthLayout from '../../components/ui/AuthLayout.vue';

export default {
  name: 'AdminLogin',
  components: {
    AuthLayout,
  },
  data() {
    return {
      authVisual: '/images/auth/sportgo_art.png',
      loginValue: '',
      password: '',
      showPassword: false,
      error: '',
      fieldErrors: {
        login: '',
        password: '',
      },
      isLoading: false,
    };
  },
  mounted() {
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
    async handleSubmit() {
      this.error = '';
      this.fieldErrors.login = '';
      this.fieldErrors.password = '';

      if (!this.loginValue.trim()) {
        this.setFieldError('login', 'Vui lòng nhập tài khoản quản trị.');
        return;
      }
      if (!this.password) {
        this.setFieldError('password', 'Vui lòng nhập mật khẩu quản trị.');
        return;
      }

      this.isLoading = true;

      try {
        const auth = await adminLogin(
          this.loginValue.trim(),
          this.password
        );
        this.$router.push(auth.redirect_to || '/admin/venue-clusters');
      } catch (requestError) {
        const details = requestError.data || {};
        let lockedUntilFormatted = null;
        if (details.locked_until) {
          try {
            const d = new Date(details.locked_until);
            const pad = (n) => (n < 10 ? '0' + n : n);
            lockedUntilFormatted = `Khóa đến: ${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
          } catch (e) {
            lockedUntilFormatted = `Khóa đến: ${details.locked_until}`;
          }
        }

        const lockDetails = [
          details.status_reason,
          details.lock_type ? `Loại khóa: ${details.lock_type}` : null,
          lockedUntilFormatted,
        ]
          .filter(Boolean)
          .join(' - ');

        const message = lockDetails
          ? `${requestError.message} ${lockDetails}`
          : requestError.message || 'Không thể đăng nhập quản trị.';

        this.setFieldError('login', message);
      } finally {
        this.isLoading = false;
      }
    },
  },
};
</script>

<style scoped>
.shake-enter-active {
  animation: shakeAnim 0.4s ease;
}
@keyframes shakeAnim {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-6px); }
  40% { transform: translateX(6px); }
  60% { transform: translateX(-4px); }
  80% { transform: translateX(4px); }
}
</style>

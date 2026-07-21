<template>
  <AuthLayout
    class="sg-account-auth"
    title="Đăng nhập SportGo"
    subtitle="Tiếp tục đặt sân, theo dõi lịch chơi và kết nối cộng đồng"
    :image-src="authVisual"
    quote-text="Sẵn sàng cho trận đấu tiếp theo? Đăng nhập và đặt sân trong vài phút."
    back-to="/"
  >
    <form @submit.prevent="handleLogin" class="flex flex-col gap-5 w-full text-left mt-2" autocomplete="off" novalidate>
      <!-- Error message -->
      <transition name="shake">
        <div v-if="error" class="flex items-center gap-2.5 p-3 rounded-lg border border-red-500/20 bg-red-500/10 text-red-400 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
          <span>{{ error }}</span>
        </div>
      </transition>

      <div class="flex flex-col gap-4">
        <!-- Email Input Group -->
        <div class="flex flex-col gap-2">
          <label for="login" class="text-sm font-medium text-zinc-200 text-left">
            Email / Số điện thoại / Username
          </label>
          <input
            id="login"
            v-model="loginValue"
            type="text"
            placeholder="m@example.com"
            autocomplete="username"
            class="flex h-10 w-full rounded-md border border-zinc-800 !bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
          />
        </div>

        <!-- Password Input Group -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-zinc-200 text-left">Mật khẩu</label>
            <router-link to="/forgot-password" class="text-xs text-zinc-400 hover:text-zinc-200 transition-colors">
              Quên mật khẩu?
            </router-link>
          </div>
          <PasswordInput
            v-model="password"
            placeholder="Mật khẩu"
            autocomplete="current-password"
          />
        </div>
      </div>

      <div class="sg-auth-field">
        <label for="login">Tài khoản</label>
        <input
          id="login"
          ref="loginInput"
          v-model.trim="loginValue"
          class="sg-auth-input"
          :class="{ 'sg-auth-input--error': fieldErrors.login }"
          type="text"
          placeholder="Email, số điện thoại hoặc tên đăng nhập"
          autocomplete="username"
          autocapitalize="none"
          spellcheck="false"
          :aria-invalid="Boolean(fieldErrors.login)"
          :aria-describedby="fieldErrors.login ? 'login-error' : undefined"
          @input="clearFieldError('login')"
        />
        <p v-if="fieldErrors.login" id="login-error" class="sg-auth-field-error">
          {{ fieldErrors.login }}
        </p>
      </div>

      <div class="sg-auth-field">
        <div class="sg-auth-label-row">
          <label>Mật khẩu</label>
          <router-link to="/forgot-password">Quên mật khẩu?</router-link>
        </div>
        <PasswordInput
          v-model="password"
          class="sg-auth-password"
          :class="{ 'sg-auth-password--error': fieldErrors.password }"
          placeholder="Nhập mật khẩu"
          autocomplete="current-password"
          @update:model-value="clearFieldError('password')"
        />
        <p v-if="fieldErrors.password" class="sg-auth-field-error">
          {{ fieldErrors.password }}
        </p>
      </div>

      <button class="sg-auth-submit" type="submit" :disabled="isLoading">
        <span v-if="isLoading" class="sg-auth-spinner" aria-hidden="true"></span>
        <span>{{ isLoading ? 'Đang đăng nhập...' : 'Đăng nhập' }}</span>
      </button>

      <p class="sg-auth-switch">
        Chưa có tài khoản?
        <router-link to="/register">Đăng ký ngay</router-link>
      </p>

      <div class="sg-auth-divider"><span>Hoặc</span></div>

      <button class="sg-auth-secondary" type="button" @click="handleGoogleLogin">
        <span class="sg-auth-provider-mark" aria-hidden="true">G</span>
        Tiếp tục với Google
      </button>
    </form>
  </AuthLayout>
</template>

<script>
import { login, loginWithGoogle } from '../stores/auth.js';
import AppIcon from '../components/AppIcon.vue';
import AuthLayout from '../components/ui/AuthLayout.vue';
import PasswordInput from '../components/ui/PasswordInput.vue';

export default {
  name: 'LoginView',
  components: {
    AppIcon,
    AuthLayout,
    PasswordInput,
  },
  data() {
    return {
      authVisual: '/images/home/badminton-cover.webp',
      loginValue: '',
      password: '',
      error: '',
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

<style scoped src="/resources/css/views/client-auth-base.css"></style>

<template>
  <AuthLayout
    title="Đăng nhập tài khoản"
    subtitle="Nhập email của bạn bên dưới để đăng nhập"
    imageSrc="https://i.ibb.co/XrkdGrrv/original-ccdd6d6195fff2386a31b684b7abdd2e-removebg-preview.png"
    quoteText="Bảo bối à, tôi cho phép em được đăng nhập vào hệ thống của tôi."
    backTo="/"
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
            class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
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

      <!-- Sign In Button -->
      <button
        type="submit"
        :disabled="isLoading"
        class="flex h-10 w-full items-center justify-center rounded-md !border !border-solid !border-zinc-700 !bg-zinc-900 text-zinc-100 hover:!bg-zinc-800 hover:!border-zinc-600 transition-all font-medium text-sm cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span v-if="!isLoading">Đăng nhập</span>
        <span v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
      </button>

      <!-- Don't have an account? Sign up -->
      <div class="text-center text-sm text-zinc-400">
        Chưa có tài khoản?
        <router-link to="/register" class="font-medium text-zinc-100 hover:underline pl-1">
          Đăng ký
        </router-link>
      </div>

      <!-- Divider -->
      <div class="relative flex py-1 items-center">
        <div class="flex-grow border-t border-zinc-800"></div>
        <span class="flex-shrink mx-3 text-xs text-zinc-500 uppercase tracking-wider font-medium">HOẶC TIẾP TỤC VỚI</span>
        <div class="flex-grow border-t border-zinc-800"></div>
      </div>

      <!-- Google Button -->
      <button
        type="button"
        @click="handleGoogleLogin"
        class="flex h-10 w-full items-center justify-center gap-2 rounded-md !border !border-solid !border-zinc-700 !bg-zinc-950 text-zinc-100 hover:!bg-zinc-900 hover:!border-zinc-600 transition-all font-medium text-sm cursor-pointer"
      >
        <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google icon" class="h-4 w-4" />
        Tiếp tục với Google
      </button>
    </form>
  </AuthLayout>
</template>

<script>
import { login, loginWithGoogle } from '../stores/auth.js';
import AuthLayout from '../components/ui/AuthLayout.vue';
import PasswordInput from '../components/ui/PasswordInput.vue';

export default {
  name: 'LoginView',
  components: {
    AuthLayout,
    PasswordInput,
  },
  data() {
    return {
      loginValue: '',
      password: '',
      error: '',
      isLoading: false,
    };
  },
  methods: {
    async handleLogin() {
      this.error = '';

      if (!this.loginValue.trim()) {
        this.error = 'Vui lòng nhập Email / Số điện thoại / Username.';
        return;
      }
      if (!this.password) {
        this.error = 'Vui lòng nhập mật khẩu.';
        return;
      }

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

<style scoped>
.shake-enter-active {
  animation: shakeAnim .4s ease;
}
@keyframes shakeAnim {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-6px); }
  40% { transform: translateX(6px); }
  60% { transform: translateX(-4px); }
  80% { transform: translateX(4px); }
}
</style>

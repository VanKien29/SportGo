<template>
  <AuthLayout
    title="Quản trị hệ thống"
    subtitle="Đăng nhập tài khoản quản trị để tiếp tục"
    imageSrc="https://i.ibb.co/XrkdGrrv/original-ccdd6d6195fff2386a31b684b7abdd2e-removebg-preview.png"
    quoteText="Chỉ dành cho quản trị viên và nhân viên hệ thống."
    quoteAuthor="SportGo Admin"
    backTo="/"
  >
    <form @submit.prevent="handleSubmit" class="flex flex-col gap-5 w-full text-left mt-2" autocomplete="off" novalidate>
      <!-- Error message -->
      <transition name="shake">
        <div v-if="error" class="flex items-center gap-2.5 p-3 rounded-lg border border-red-500/20 bg-red-500/10 text-red-400 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
          <span>{{ error }}</span>
        </div>
      </transition>

      <div class="flex flex-col gap-4">
        <!-- Username/Email/Phone Input Group -->
        <div class="flex flex-col gap-2">
          <label for="admin-login" class="text-sm font-medium text-zinc-200 text-left">
            Tên đăng nhập / Email / Số điện thoại
          </label>
          <input
            id="admin-login"
            v-model.trim="loginValue"
            type="text"
            placeholder="Nhập tài khoản quản trị"
            autocomplete="username"
            class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
          />
        </div>

        <!-- Password Input Group -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-zinc-200 text-left">Mật khẩu</label>
            <router-link to="/admin/forgot-password" class="text-xs text-zinc-400 hover:text-zinc-200 transition-colors">
              Quên mật khẩu admin?
            </router-link>
          </div>
          <PasswordInput
            v-model="password"
            placeholder="Nhập mật khẩu"
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
        <span v-if="!isLoading">Đăng nhập quản trị</span>
        <span v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
      </button>
    </form>
  </AuthLayout>
</template>

<script>
import { adminLogin } from "../../stores/auth.js";
import AuthLayout from "../../components/ui/AuthLayout.vue";
import PasswordInput from "../../components/ui/PasswordInput.vue";

export default {
  name: "AdminLogin",
  components: {
    AuthLayout,
    PasswordInput,
  },
  data() {
    return {
      loginValue: "",
      password: "",
      isLoading: false,
      error: "",
    };
  },
  methods: {
    async handleSubmit() {
      this.error = "";

      if (!this.loginValue.trim()) {
        this.error = "Vui lòng nhập tài khoản quản trị.";
        return;
      }
      if (!this.password) {
        this.error = "Vui lòng nhập mật khẩu quản trị.";
        return;
      }

      this.isLoading = true;

      try {
        const auth = await adminLogin(this.loginValue.trim(), this.password);
        this.$router.push(auth.redirect_to || "/admin/dashboard");
      } catch (error) {
        const details = error.data || {};
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
          .join(" - ");

        this.error = lockDetails
          ? `${error.message} ${lockDetails}`
          : error.message || "Không thể đăng nhập quản trị.";
      } finally {
        this.isLoading = false;
      }
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

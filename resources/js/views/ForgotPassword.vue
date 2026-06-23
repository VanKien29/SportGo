<template>
  <AuthLayout
    :title="titleText"
    :subtitle="subtitleText"
    imageSrc="https://i.ibb.co/XrkdGrrv/original-ccdd6d6195fff2386a31b684b7abdd2e-removebg-preview.png"
    quoteText="Reset your password. We've got you covered."
    quoteAuthor="EaseMize UI"
    backTo="/"
  >
    <!-- Success / Message -->
    <transition name="fade">
      <div v-if="successMsg" class="flex items-center gap-2.5 p-3 rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-400 text-sm mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 16 16 12 12 8"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        <span>{{ successMsg }}</span>
      </div>
    </transition>

    <!-- Error message -->
    <transition name="shake">
      <div v-if="error" class="flex items-center gap-2.5 p-3 rounded-lg border border-red-500/20 bg-red-500/10 text-red-400 text-sm mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- STEP 1: IDENTIFY -->
    <form v-if="step === 'identify'" @submit.prevent="handleIdentify" class="flex flex-col gap-5 w-full text-left mt-2">
      <div style="width: 0; height: 0; overflow: hidden; position: absolute; z-index: -1;">
        <input type="text" autocomplete="username" tabindex="-1" />
        <input type="email" autocomplete="email" tabindex="-1" />
      </div>

      <div class="flex flex-col gap-2">
        <label for="identifier" class="text-sm font-medium text-zinc-200 text-left">
          Tên đăng nhập / Email / Số điện thoại
        </label>
        <input
          id="identifier"
          v-model="identifier"
          type="text"
          placeholder="Nhập tên đăng nhập, email hoặc số điện thoại"
          required
          autocomplete="username"
          class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
        />
      </div>

      <button
        type="submit"
        :disabled="isLoading"
        class="flex h-10 w-full items-center justify-center rounded-md border border-zinc-700 bg-zinc-900 text-zinc-100 hover:bg-zinc-800 hover:border-zinc-600 transition-all font-medium text-sm mt-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span v-if="!isLoading">Gửi mã OTP</span>
        <span v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
      </button>

      <div class="text-center text-sm text-zinc-400 mt-6 pt-5 border-t border-zinc-900">
        <router-link to="/login" class="font-semibold text-zinc-100 hover:underline">
          Quay lại đăng nhập
        </router-link>
      </div>
    </form>

    <!-- STEP 2: OTP VERIFICATION -->
    <form v-if="step === 'otp'" @submit.prevent="handleVerifyOtp" class="flex flex-col gap-5 w-full text-left mt-2">
      <div style="width: 0; height: 0; overflow: hidden; position: absolute; z-index: -1;">
        <input type="text" autocomplete="one-time-code" tabindex="-1" />
      </div>

      <div class="flex flex-col gap-2">
        <label for="otp" class="text-sm font-medium text-zinc-200 text-left">
          Mã OTP <span class="text-red-500">*</span>
        </label>
        <input
          id="otp"
          v-model.trim="otp"
          type="text"
          inputmode="numeric"
          maxlength="6"
          placeholder="Mã OTP"
          required
          autocomplete="one-time-code"
          class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 tracking-widest text-center font-bold"
        />
      </div>

      <button
        type="submit"
        :disabled="isLoading"
        class="flex h-10 w-full items-center justify-center rounded-md border border-zinc-700 bg-zinc-900 text-zinc-100 hover:bg-zinc-800 hover:border-zinc-600 transition-all font-medium text-sm mt-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span v-if="!isLoading">Xác nhận</span>
        <span v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
      </button>

      <button
        type="button"
        @click.prevent="resendOtp"
        class="flex h-10 w-full items-center justify-center gap-2 rounded-md border border-zinc-700 bg-zinc-950 text-zinc-100 hover:bg-zinc-900 hover:border-zinc-600 transition-all font-medium text-sm cursor-pointer"
      >
        <span>Gửi lại mã OTP</span>
      </button>

      <div class="text-center text-sm text-zinc-400 mt-6 pt-5 border-t border-zinc-900">
        <router-link to="/login" class="font-semibold text-zinc-100 hover:underline">
          Quay lại đăng nhập
        </router-link>
      </div>
    </form>

    <!-- STEP 3: RESET PASSWORD -->
    <form v-if="step === 'reset'" @submit.prevent="handleReset" class="flex flex-col gap-5 w-full text-left mt-2">
      <div style="width: 0; height: 0; overflow: hidden; position: absolute; z-index: -1;">
        <input type="password" autocomplete="new-password" tabindex="-1" />
      </div>

      <div class="flex flex-col gap-4">
        <PasswordInput
          v-model="password"
          label="Mật khẩu mới"
          placeholder="Mật khẩu mới"
          required
          autocomplete="new-password"
        />

        <PasswordInput
          v-model="password_confirmation"
          label="Xác nhận mật khẩu"
          placeholder="Xác nhận mật khẩu"
          required
          autocomplete="new-password"
        />
      </div>

      <button
        type="submit"
        :disabled="isLoading"
        class="flex h-10 w-full items-center justify-center rounded-md border border-zinc-700 bg-zinc-900 text-zinc-100 hover:bg-zinc-800 hover:border-zinc-600 transition-all font-medium text-sm mt-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span v-if="!isLoading">Đặt lại mật khẩu</span>
        <span v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
      </button>
    </form>
  </AuthLayout>
</template>

<script>
import { resetPassword, sendForgotOtp, verifyForgotOtp } from '../stores/auth.js';
import AuthLayout from '../components/ui/AuthLayout.vue';
import PasswordInput from '../components/ui/PasswordInput.vue';

export default {
  name: 'ForgotPasswordView',
  components: {
    AuthLayout,
    PasswordInput,
  },
  data() {
    return {
      step: 'identify', // identify, otp, reset
      identifier: '',
      otp: '',
      password: '',
      password_confirmation: '',
      error: '',
      successMsg: '',
      isLoading: false,
    };
  },
  computed: {
    titleText() {
      if (this.step === 'identify') return 'Quên mật khẩu';
      if (this.step === 'otp') return 'Xác thực OTP';
      return 'Đặt lại mật khẩu';
    },
    subtitleText() {
      if (this.step === 'identify') return 'Nhập tên đăng nhập, email hoặc số điện thoại của bạn';
      if (this.step === 'otp') return 'Nhập mã OTP gồm 6 chữ số đã được gửi tới email của bạn';
      return 'Nhập mật khẩu mới của bạn bên dưới';
    },
  },
  methods: {
    async handleIdentify() {
      this.error = '';
      this.successMsg = '';
      if (!this.identifier) return;

      this.isLoading = true;
      try {
        const response = await sendForgotOtp(this.identifier);
        this.step = 'otp';
        this.successMsg = response.message || 'Mã OTP đã được gửi đến email đăng ký.';
      } catch (error) {
        this.error = error.message || 'Không thể gửi OTP.';
      } finally {
        this.isLoading = false;
      }
    },
    async handleVerifyOtp() {
      this.error = '';
      this.successMsg = '';
      if (!this.otp) return;

      this.isLoading = true;
      try {
        const response = await verifyForgotOtp(this.identifier, this.otp);
        this.step = 'reset';
        this.successMsg = response.message || 'Xác thực OTP thành công.';
      } catch (error) {
        this.error = error.message || 'Mã OTP không đúng.';
      } finally {
        this.isLoading = false;
      }
    },
    async resendOtp() {
      this.error = '';
      this.successMsg = '';
      try {
        const response = await sendForgotOtp(this.identifier);
        this.successMsg = response.message || 'Mã OTP mới đã được gửi.';
      } catch (error) {
        this.error = error.message || 'Không thể gửi lại OTP.';
      }
    },
    async handleReset() {
      this.error = '';
      this.successMsg = '';

      if (!this.password) {
        this.error = 'Vui lòng nhập mật khẩu mới.';
        return;
      }
      if (this.password !== this.password_confirmation) {
        this.error = 'Xác nhận mật khẩu không khớp.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await resetPassword(this.identifier, this.otp, this.password, this.password_confirmation);
        this.successMsg = response.message || 'Đặt lại mật khẩu thành công.';
        setTimeout(() => this.$router.push('/login'), 1500);
      } catch (error) {
        this.error = error.message || 'Không thể đặt lại mật khẩu.';
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
.fade-enter-active, .fade-leave-active {
  transition: opacity .3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>

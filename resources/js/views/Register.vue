<template>
  <AuthLayout
    :title="step === 'register' ? 'Đăng ký tài khoản' : 'Xác thực OTP'"
    :subtitle="step === 'register' ? 'Nhập thông tin bên dưới để đăng ký tài khoản' : 'Nhập mã OTP gồm 6 chữ số đã được gửi tới email của bạn'"
    imageSrc="https://i.ibb.co/HTZ6DPsS/original-33b8479c324a5448d6145b3cad7c51e7-removebg-preview.png"
    quoteText="Vkien bán 32tv giá 1l :D"
    backTo="/"
  >
    <!-- STEP 1: REGISTER FORM -->
    <form v-if="step === 'register'" @submit.prevent="handleRegister" class="flex flex-col gap-5 w-full text-left mt-2" autocomplete="off">
      <!-- Error message -->
      <transition name="shake">
        <div v-if="error" class="flex items-center gap-2.5 p-3 rounded-lg border border-red-500/20 bg-red-500/10 text-red-400 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
          <span>{{ error }}</span>
        </div>
      </transition>

      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="username" class="text-sm font-medium text-zinc-200 text-left">
            Tên đăng nhập <span class="text-red-500">*</span>
          </label>
          <input
            id="username"
            v-model.trim="form.username"
            type="text"
            placeholder="Tên đăng nhập"
            required
            autocomplete="username"
            class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
          />
        </div>

        <div class="flex flex-col gap-2">
          <label for="full_name" class="text-sm font-medium text-zinc-200 text-left">
            Họ và tên <span class="text-red-500">*</span>
          </label>
          <input
            id="full_name"
            v-model.trim="form.full_name"
            type="text"
            placeholder="Họ và tên"
            required
            autocomplete="name"
            class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
          />
        </div>

        <div class="flex flex-col gap-2">
          <label for="phone" class="text-sm font-medium text-zinc-200 text-left">
            Số điện thoại <span class="text-red-500">*</span>
          </label>
          <input
            id="phone"
            v-model.trim="form.phone"
            type="tel"
            placeholder="Số điện thoại"
            required
            autocomplete="tel"
            class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
          />
        </div>

        <div class="flex flex-col gap-2">
          <label for="email" class="text-sm font-medium text-zinc-200 text-left">
            Email <span class="text-red-500">*</span>
          </label>
          <input
            id="email"
            v-model.trim="form.email"
            type="email"
            placeholder="m@example.com"
            required
            autocomplete="email"
            class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
          />
        </div>

        <div class="flex flex-col gap-2">
          <PasswordInput
            v-model="form.password"
            label="Mật khẩu"
            placeholder="Mật khẩu"
            required
            autocomplete="new-password"
          />
        </div>

        <div class="flex flex-col gap-2">
          <PasswordInput
            v-model="form.password_confirmation"
            label="Xác nhận mật khẩu"
            placeholder="Xác nhận mật khẩu"
            required
            autocomplete="new-password"
          />
        </div>
      </div>

      <button
        type="submit"
        :disabled="isLoading"
        class="flex h-10 w-full items-center justify-center rounded-md !border !border-solid !border-zinc-700 !bg-zinc-900 text-zinc-100 hover:!bg-zinc-800 hover:!border-zinc-600 transition-all font-medium text-sm mt-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span v-if="!isLoading">Đăng ký</span>
        <span v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
      </button>

      <div class="relative flex py-1 items-center">
        <div class="flex-grow border-t border-zinc-800"></div>
        <span class="flex-shrink mx-3 text-xs text-zinc-500 uppercase tracking-wider font-medium">HOẶC TIẾP TỤC VỚI</span>
        <div class="flex-grow border-t border-zinc-800"></div>
      </div>

      <button
        type="button"
        @click.prevent="handleGoogleLogin"
        class="flex h-10 w-full items-center justify-center gap-2 rounded-md !border !border-solid !border-zinc-700 !bg-zinc-950 text-zinc-100 hover:!bg-zinc-900 hover:!border-zinc-600 transition-all font-medium text-sm cursor-pointer"
      >
        <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google icon" class="h-4 w-4" />
        Tiếp tục với Google
      </button>

      <div class="text-center text-sm text-zinc-400 mt-4">
        Đã có tài khoản?
        <router-link to="/login" class="font-semibold text-zinc-100 hover:underline pl-1">
          Đăng nhập
        </router-link>
      </div>
    </form>

    <!-- STEP 2: OTP VERIFICATION FORM -->
    <form v-else @submit.prevent="handleVerifyOtp" class="flex flex-col gap-5 w-full text-left mt-2">
      <!-- Success / Message -->
      <transition name="fade">
        <div v-if="successMsg" class="flex items-center gap-2.5 p-3 rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-400 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 16 16 12 12 8"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          <span>{{ successMsg }}</span>
        </div>
      </transition>

      <!-- Error message -->
      <transition name="shake">
        <div v-if="error" class="flex items-center gap-2.5 p-3 rounded-lg border border-red-500/20 bg-red-500/10 text-red-400 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
          <span>{{ error }}</span>
        </div>
      </transition>

      <p class="text-sm text-zinc-400 leading-relaxed">
        Vui lòng nhập mã OTP gồm 6 chữ số được gửi tới email của bạn <strong class="text-zinc-200">{{ form.email }}</strong>.
      </p>

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
        :disabled="isLoading || isResending"
        class="flex h-10 w-full items-center justify-center rounded-md !border !border-solid !border-zinc-700 !bg-zinc-900 text-zinc-100 hover:!bg-zinc-800 hover:!border-zinc-600 transition-all font-medium text-sm mt-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span v-if="!isLoading">Xác nhận tài khoản</span>
        <span v-else class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
      </button>

      <button
        type="button"
        :disabled="isResending"
        @click="handleResendOtp"
        class="flex h-10 w-full items-center justify-center gap-2 rounded-md !border !border-solid !border-zinc-700 !bg-zinc-950 text-zinc-100 hover:!bg-zinc-900 hover:!border-zinc-600 transition-all font-medium text-sm cursor-pointer"
      >
        <span>{{ isResending ? 'Đang gửi lại...' : 'Gửi lại mã OTP' }}</span>
      </button>
    </form>
  </AuthLayout>
</template>

<script>
import { register, verifyRegisterOtp, resendRegisterOtp, loginWithGoogle } from '../stores/auth.js';
import AuthLayout from '../components/ui/AuthLayout.vue';
import PasswordInput from '../components/ui/PasswordInput.vue';

export default {
  name: 'RegisterView',
  components: {
    AuthLayout,
    PasswordInput,
  },
  data() {
    return {
      form: {
        username: '',
        full_name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
      },
      step: 'register',
      otp: '',
      error: '',
      successMsg: '',
      isLoading: false,
      isResending: false,
    };
  },
  methods: {
    async handleRegister() {
      this.error = '';
      this.successMsg = '';

      if (!this.form.username || !this.form.full_name || !this.form.phone || !this.form.email || !this.form.password || !this.form.password_confirmation) {
        this.error = 'Vui lòng nhập đầy đủ các trường bắt buộc.';
        return;
      }

      if (this.form.password !== this.form.password_confirmation) {
        this.error = 'Xác nhận mật khẩu không khớp.';
        return;
      }

      this.isLoading = true;
      try {
        const response = await register(this.form);
        this.step = 'otp';
        this.successMsg = response.message || 'Mã xác thực đã được gửi về email.';
      } catch (error) {
        this.error = error.message || 'Đăng ký không thành công.';
      } finally {
        this.isLoading = false;
      }
    },
    async handleVerifyOtp() {
      this.error = '';
      this.successMsg = '';
      this.isLoading = true;

      try {
        const response = await verifyRegisterOtp(this.form.email, this.otp);
        this.successMsg = response.message || 'Xác thực thành công.';
        setTimeout(() => this.$router.push('/login'), 1200);
      } catch (error) {
        this.error = error.message || 'Mã OTP không đúng.';
      } finally {
        this.isLoading = false;
      }
    },
    async handleResendOtp() {
      this.error = '';
      this.successMsg = '';
      this.isResending = true;

      try {
        const response = await resendRegisterOtp(this.form.email);
        this.successMsg = response.message || 'Đã gửi lại mã OTP.';
      } catch (error) {
        this.error = error.message || 'Không thể gửi lại mã OTP.';
      } finally {
        this.isResending = false;
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
.fade-enter-active, .fade-leave-active {
  transition: opacity .3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>

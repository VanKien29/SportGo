<template>
  <teleport to="body">
    <div v-if="show" class="modal-overlay" @click.self="handleSkip">
      <div class="modal-card">
        <div class="modal-header">
          <div class="modal-brand">Sport<span>Go</span></div>
          <div class="modal-badge">Đăng nhập Google thành công</div>
          <h2>Thiết lập mật khẩu</h2>
          <p>Đặt mật khẩu để có thể đăng nhập bằng email, số điện thoại hoặc username sau này.</p>
        </div>

        <div v-if="error" class="error-msg">{{ error }}</div>

        <form class="modal-form" @submit.prevent="handleSubmit" autocomplete="off">
          <label for="sp-password">Mật khẩu mới</label>
          <div class="input-wrap" :class="{ focused: focused === 'password' }">
            <input
              id="sp-password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="Nhập mật khẩu mới"
              autocomplete="new-password"
              required
              @focus="focused = 'password'"
              @blur="focused = ''"
            />
            <button type="button" @click="showPassword = !showPassword">
              {{ showPassword ? 'Ẩn' : 'Hiện' }}
            </button>
          </div>

          <label for="sp-confirm">Xác nhận mật khẩu</label>
          <div class="input-wrap" :class="{ focused: focused === 'confirm' }">
            <input
              id="sp-confirm"
              v-model="confirm"
              :type="showConfirm ? 'text' : 'password'"
              placeholder="Nhập lại mật khẩu"
              autocomplete="new-password"
              required
              @focus="focused = 'confirm'"
              @blur="focused = ''"
            />
            <button type="button" @click="showConfirm = !showConfirm">
              {{ showConfirm ? 'Ẩn' : 'Hiện' }}
            </button>
          </div>

          <ul class="checklist">
            <li :class="{ pass: checks.length }">{{ checks.length ? 'OK' : '--' }} Từ 8 đến 50 ký tự</li>
            <li :class="{ pass: checks.upper }">{{ checks.upper ? 'OK' : '--' }} Có ít nhất 1 chữ hoa</li>
            <li :class="{ pass: checks.number }">{{ checks.number ? 'OK' : '--' }} Có ít nhất 1 chữ số</li>
            <li :class="{ pass: checks.special }">{{ checks.special ? 'OK' : '--' }} Có ít nhất 1 ký tự đặc biệt</li>
            <li v-if="confirm" :class="{ pass: checks.match }">{{ checks.match ? 'OK' : '--' }} Mật khẩu khớp nhau</li>
          </ul>

          <button type="submit" class="submit-btn" :disabled="!allPass || isLoading">
            <span v-if="!isLoading">Đặt mật khẩu</span>
            <span v-else class="spinner"></span>
          </button>

          <button type="button" class="skip-btn" @click="handleSkip">Bỏ qua, làm sau</button>
        </form>
      </div>
    </div>
  </teleport>
</template>

<script>
import { setPassword, clearPasswordSetupFlag } from '../stores/auth.js';

export default {
  name: 'SetPasswordModal',
  emits: ['done'],
  data() {
    return {
      show: true,
      password: '',
      confirm: '',
      showPassword: false,
      showConfirm: false,
      focused: '',
      isLoading: false,
      error: '',
    };
  },
  computed: {
    checks() {
      const password = this.password;
      return {
        length: password.length >= 8 && password.length <= 50,
        upper: /[A-Z]/.test(password),
        number: /\d/.test(password),
        special: /[^A-Za-z\d]/.test(password),
        match: Boolean(password && this.confirm && password === this.confirm),
      };
    },
    allPass() {
      const checks = this.checks;
      return checks.length && checks.upper && checks.number && checks.special && checks.match;
    },
  },
  methods: {
    async handleSubmit() {
      if (!this.allPass) return;

      this.error = '';
      this.isLoading = true;

      try {
        await setPassword(this.password, this.confirm);
        clearPasswordSetupFlag();
        this.show = false;
        this.$emit('done');
      } catch (error) {
        this.error = error?.data?.errors?.password?.[0]
          || error?.data?.message
          || 'Không thể thiết lập mật khẩu. Vui lòng thử lại.';
      } finally {
        this.isLoading = false;
      }
    },
    handleSkip() {
      clearPasswordSetupFlag();
      this.show = false;
      this.$emit('done');
    },
  },
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.62);
}

.modal-card {
  width: 100%;
  max-width: 440px;
  padding: 32px;
  border-radius: 16px;
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #fff;
  box-shadow: 0 24px 70px rgba(0, 0, 0, 0.45);
}

.modal-header {
  text-align: center;
  margin-bottom: 22px;
}

.modal-brand {
  font-size: 24px;
  font-weight: 800;
}

.modal-brand span {
  color: #22c55e;
}

.modal-badge {
  display: inline-block;
  margin: 12px 0;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(59, 130, 246, 0.14);
  color: #93c5fd;
  font-size: 12px;
  font-weight: 700;
}

.modal-header h2 {
  margin: 0 0 8px;
  font-size: 22px;
}

.modal-header p {
  margin: 0;
  color: rgba(255, 255, 255, 0.62);
  font-size: 13px;
  line-height: 1.5;
}

.error-msg {
  margin-bottom: 16px;
  padding: 11px 14px;
  border-radius: 10px;
  color: #fecaca;
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.22);
  font-size: 13px;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.modal-form label {
  color: rgba(255, 255, 255, 0.72);
  font-size: 13px;
  font-weight: 700;
}

.input-wrap {
  display: flex;
  align-items: center;
  height: 46px;
  padding: 0 12px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.input-wrap.focused {
  border-color: #22c55e;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
}

.input-wrap input {
  flex: 1;
  min-width: 0;
  height: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  color: #fff;
}

.input-wrap button,
.skip-btn {
  color: rgba(255, 255, 255, 0.62);
  font-weight: 700;
}

.checklist {
  margin: 4px 0 0;
  padding: 12px 14px;
  list-style: none;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.04);
  color: rgba(255, 255, 255, 0.48);
  font-size: 12px;
  line-height: 1.8;
}

.checklist .pass {
  color: #86efac;
}

.submit-btn {
  height: 46px;
  margin-top: 4px;
  border-radius: 10px;
  background: #22c55e;
  color: #fff;
  font-weight: 800;
}

.submit-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.skip-btn {
  padding: 6px;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 3px solid rgba(255, 255, 255, 0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>

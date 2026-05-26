<template>
  <teleport to="body">
    <transition name="modal-fade">
      <div v-if="show" class="modal-overlay" @click.self="handleSkip">
        <transition name="modal-slide">
          <div v-if="show" class="modal-card">
            <!-- Header -->
            <div class="modal-header">
              <div class="modal-brand">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                  <circle cx="16" cy="16" r="15" stroke="#22c55e" stroke-width="2"/>
                  <path d="M16 4C20 8 22 12 22 16C22 20 20 24 16 28" stroke="#22c55e" stroke-width="1.5" fill="none"/>
                  <path d="M16 4C12 8 10 12 10 16C10 20 12 24 16 28" stroke="#22c55e" stroke-width="1.5" fill="none"/>
                  <line x1="4" y1="12" x2="28" y2="12" stroke="#22c55e" stroke-width="1.5"/>
                  <line x1="4" y1="20" x2="28" y2="20" stroke="#22c55e" stroke-width="1.5"/>
                </svg>
                <span class="modal-brand-name">Sport<span class="accent">Go</span></span>
              </div>
              <div class="modal-google-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                  <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                  <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                  <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Đăng nhập Google thành công
              </div>
              <h2 class="modal-title">Thiết lập mật khẩu</h2>
              <p class="modal-desc">
                Bạn vừa tạo tài khoản qua Google. Hãy đặt mật khẩu để có thể đăng nhập thông thường sau này.
              </p>
            </div>

            <!-- Error -->
            <transition name="shake">
              <div v-if="error" class="error-msg">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                {{ error }}
              </div>
            </transition>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" autocomplete="off" class="modal-form">
              <!-- Password -->
              <div class="input-group">
                <label for="sp-password">Mật khẩu mới <span class="req">*</span></label>
                <div class="input-wrap" :class="{ focused: focused === 'pw' }">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                  <input
                    id="sp-password"
                    v-model="password"
                    :type="showPw ? 'text' : 'password'"
                    placeholder="Nhập mật khẩu mới"
                    autocomplete="new-password"
                    required
                    @focus="focused = 'pw'"
                    @blur="focused = ''"
                  />
                  <button type="button" class="toggle-pw" @click="showPw = !showPw" tabindex="-1">
                    <svg v-if="!showPw" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                  </button>
                </div>

                <!-- Strength bar -->
                <div class="strength-bar" v-if="password">
                  <div class="strength-track">
                    <div class="strength-fill" :class="strengthClass" :style="{ width: strengthPct + '%' }"></div>
                  </div>
                  <span class="strength-label" :class="strengthClass">{{ strengthLabel }}</span>
                </div>
              </div>

              <!-- Confirm -->
              <div class="input-group">
                <label for="sp-confirm">Xác nhận mật khẩu <span class="req">*</span></label>
                <div class="input-wrap" :class="{ focused: focused === 'confirm' }">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
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
                  <button type="button" class="toggle-pw" @click="showConfirm = !showConfirm" tabindex="-1">
                    <svg v-if="!showConfirm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Checklist -->
              <ul class="checklist">
                <li :class="{ pass: checks.length }">
                  <span class="check-icon">{{ checks.length ? '✓' : '○' }}</span>
                  Từ 8 đến 50 ký tự
                </li>
                <li :class="{ pass: checks.upper }">
                  <span class="check-icon">{{ checks.upper ? '✓' : '○' }}</span>
                  Ít nhất 1 chữ hoa (A–Z)
                </li>
                <li :class="{ pass: checks.number }">
                  <span class="check-icon">{{ checks.number ? '✓' : '○' }}</span>
                  Ít nhất 1 chữ số (0–9)
                </li>
                <li :class="{ pass: checks.special }">
                  <span class="check-icon">{{ checks.special ? '✓' : '○' }}</span>
                  Ít nhất 1 ký tự đặc biệt (!@#$%...)
                </li>
                <li :class="{ pass: checks.match }" v-if="confirm">
                  <span class="check-icon">{{ checks.match ? '✓' : '○' }}</span>
                  Mật khẩu khớp nhau
                </li>
              </ul>

              <!-- Actions -->
              <button
                type="submit"
                class="submit-btn"
                :disabled="!allPass || isLoading"
                :class="{ loading: isLoading }"
              >
                <span v-if="!isLoading">Đặt mật khẩu</span>
                <span v-else class="spinner"></span>
              </button>

              <button type="button" class="skip-btn" @click="handleSkip">
                Bỏ qua, làm sau
              </button>
            </form>
          </div>
        </transition>
      </div>
    </transition>
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
      showPw: false,
      showConfirm: false,
      focused: '',
      isLoading: false,
      error: '',
    };
  },
  computed: {
    checks() {
      const p = this.password;
      return {
        length: p.length >= 8 && p.length <= 50,
        upper: /[A-Z]/.test(p),
        number: /\d/.test(p),
        special: /[^A-Za-z\d]/.test(p),
        match: p && this.confirm && p === this.confirm,
      };
    },
    allPass() {
      const c = this.checks;
      return c.length && c.upper && c.number && c.special && c.match;
    },
    strengthScore() {
      const c = this.checks;
      return [c.length, c.upper, c.number, c.special].filter(Boolean).length;
    },
    strengthPct() {
      return (this.strengthScore / 4) * 100;
    },
    strengthClass() {
      const s = this.strengthScore;
      if (s <= 1) return 'weak';
      if (s <= 2) return 'fair';
      if (s === 3) return 'good';
      return 'strong';
    },
    strengthLabel() {
      return { weak: 'Yếu', fair: 'Trung bình', good: 'Khá', strong: 'Mạnh' }[this.strengthClass];
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
      } catch (err) {
        const msg = err?.data?.errors?.password?.[0]
          || err?.data?.message
          || 'Đã xảy ra lỗi. Vui lòng thử lại.';
        this.error = msg;
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
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-card {
  background: linear-gradient(145deg, rgba(18, 24, 18, 0.97), rgba(10, 16, 10, 0.99));
  border: 1px solid rgba(34, 197, 94, 0.2);
  border-radius: 24px;
  padding: 40px 36px;
  width: 100%;
  max-width: 460px;
  box-shadow:
    0 0 0 1px rgba(34, 197, 94, 0.08),
    0 24px 60px rgba(0, 0, 0, 0.5),
    0 0 80px rgba(34, 197, 94, 0.08);
}

/* Brand */
.modal-header { text-align: center; margin-bottom: 28px; }
.modal-brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}
.modal-brand-name {
  font-size: 22px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.5px;
}
.accent { color: #22c55e; }

.modal-google-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: rgba(66, 133, 244, 0.12);
  border: 1px solid rgba(66, 133, 244, 0.25);
  color: #93c5fd;
  font-size: 12px;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 20px;
  margin-bottom: 16px;
}

.modal-title {
  font-size: 22px;
  font-weight: 800;
  color: #fff;
  margin-bottom: 10px;
  letter-spacing: -0.3px;
}
.modal-desc {
  font-size: 13.5px;
  color: rgba(255, 255, 255, 0.5);
  line-height: 1.6;
}

/* Error */
.error-msg {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 10px;
  color: #fca5a5;
  font-size: 13px;
  margin-bottom: 18px;
  animation: shakeAnim 0.4s ease;
}
@keyframes shakeAnim {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-5px); }
  40% { transform: translateX(5px); }
  60% { transform: translateX(-3px); }
  80% { transform: translateX(3px); }
}

/* Form */
.modal-form { display: flex; flex-direction: column; gap: 18px; }
.input-group label {
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 7px;
}
.req { color: #ef4444; margin-left: 2px; }
.input-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 14px;
  height: 46px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  transition: all 0.2s ease;
}
.input-wrap svg { color: rgba(255, 255, 255, 0.3); min-width: 17px; }
.input-wrap.focused {
  border-color: #22c55e;
  background: rgba(34, 197, 94, 0.06);
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
}
.input-wrap.focused svg { color: #22c55e; }
.input-wrap input {
  flex: 1; height: 100%; border: none; outline: none;
  background: transparent; font-size: 14px; color: #fff;
}
.input-wrap input::placeholder { color: rgba(255, 255, 255, 0.2); }
.toggle-pw { padding: 4px; color: rgba(255, 255, 255, 0.3); transition: color 0.2s; }
.toggle-pw:hover { color: rgba(255, 255, 255, 0.6); }

/* Strength */
.strength-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}
.strength-track {
  flex: 1; height: 4px;
  background: rgba(255,255,255,0.1);
  border-radius: 99px;
  overflow: hidden;
}
.strength-fill {
  height: 100%; border-radius: 99px;
  transition: width 0.3s ease, background 0.3s ease;
}
.strength-fill.weak { background: #ef4444; }
.strength-fill.fair { background: #f59e0b; }
.strength-fill.good { background: #3b82f6; }
.strength-fill.strong { background: #22c55e; }
.strength-label { font-size: 11px; font-weight: 700; min-width: 52px; text-align: right; }
.strength-label.weak { color: #ef4444; }
.strength-label.fair { color: #f59e0b; }
.strength-label.good { color: #3b82f6; }
.strength-label.strong { color: #22c55e; }

/* Checklist */
.checklist {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 14px 16px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
}
.checklist li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: rgba(255,255,255,0.4);
  transition: color 0.2s;
}
.checklist li.pass { color: #86efac; }
.check-icon {
  font-size: 12px;
  font-weight: 700;
  width: 16px;
  text-align: center;
  color: rgba(255,255,255,0.2);
}
.checklist li.pass .check-icon { color: #22c55e; }

/* Buttons */
.submit-btn {
  height: 48px;
  background: #22c55e;
  color: #fff;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 700;
  transition: all 0.2s ease;
  display: flex; align-items: center; justify-content: center;
}
.submit-btn:not(:disabled):hover {
  background: #16a34a;
  box-shadow: 0 4px 20px rgba(34,197,94,0.4);
  transform: translateY(-1px);
}
.submit-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.spinner {
  width: 20px; height: 20px;
  border: 3px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.skip-btn {
  font-size: 13px;
  color: rgba(255,255,255,0.35);
  text-align: center;
  transition: color 0.2s;
  padding: 4px;
}
.skip-btn:hover { color: rgba(255,255,255,0.6); }

/* Transitions */
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.3s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-slide-enter-active, .modal-slide-leave-active { transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-slide-enter-from { opacity: 0; transform: scale(0.88) translateY(20px); }
.modal-slide-leave-to { opacity: 0; transform: scale(0.95) translateY(10px); }

.shake-enter-active { animation: shakeAnim 0.4s ease; }

@media (max-width: 480px) {
  .modal-card { padding: 32px 24px; }
  .modal-title { font-size: 20px; }
}
</style>

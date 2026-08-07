<template>
  <teleport to="body">
    <div v-if="open" class="pf-settings-backdrop" @click.self="close">
      <section
        class="pf-settings-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pf-settings-title"
      >
        <header class="pf-settings-header">
          <div>
            <p class="pf-settings-kicker">Phí nền tảng</p>
            <h2 id="pf-settings-title">Cài đặt nhắc phí</h2>
          </div>
          <button class="pf-settings-close" type="button" aria-label="Đóng" @click="close">
            <AppIcon name="x" size="18" />
          </button>
        </header>

        <div v-if="loading" class="pf-settings-state" role="status">
          <span class="pf-settings-spinner" aria-hidden="true"></span>
          <span>Đang tải cài đặt...</span>
        </div>

        <form v-else class="pf-settings-form" @submit.prevent="save">
          <div v-if="error" class="pf-settings-error" role="alert">
            <AppIcon name="alert" size="16" />
            <span>{{ error }}</span>
          </div>

          <label class="pf-settings-field">
            <span>Gửi nhắc trước hạn thanh toán (ngày)</span>
            <input v-model.number="form.default_due_days" type="number" min="1" max="30" step="1" required />
            <small v-if="fieldError('default_due_days')" class="pf-settings-error-text">{{ fieldError('default_due_days') }}</small>
          </label>

          <label class="pf-settings-field">
            <span>Lý do khóa cụm sân mặc định</span>
            <textarea v-model.trim="form.lock_reason" rows="4" minlength="3" maxlength="500" required></textarea>
            <small>{{ form.lock_reason.length }}/500</small>
            <small v-if="fieldError('lock_reason')" class="pf-settings-error-text">{{ fieldError('lock_reason') }}</small>
          </label>

          <footer class="pf-settings-actions">
            <button class="pf-settings-button secondary" type="button" :disabled="saving" @click="close">
              Hủy
            </button>
            <button class="pf-settings-button primary" type="submit" :disabled="saving || !hasChanges">
              <AppIcon name="check" size="16" />
              <span>{{ saving ? 'Đang lưu...' : 'Lưu cài đặt' }}</span>
            </button>
          </footer>
        </form>
      </section>
    </div>
  </teleport>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { api } from '../../services/api.js';

const defaults = () => ({
  default_due_days: 7,
  lock_reason: 'Quá hạn phí duy trì hệ thống',
});

export default {
  name: 'PlatformFeeSettingsDialog',
  components: { AppIcon },
  props: {
    open: { type: Boolean, default: false },
  },
  emits: ['close', 'saved'],
  data() {
    return {
      form: defaults(),
      savedForm: null,
      loading: false,
      saving: false,
      error: '',
      fieldErrors: {},
      loadedForOpen: false,
    };
  },
  computed: {
    hasChanges() {
      return Boolean(this.savedForm) && JSON.stringify(this.form) !== JSON.stringify(this.savedForm);
    },
  },
  watch: {
    open(value) {
      if (value) this.load();
      else this.loadedForOpen = false;
    },
  },
  methods: {
    close() {
      if (!this.saving) this.$emit('close');
    },
    async load() {
      if (this.loading || this.loadedForOpen) return;
      this.loading = true;
      this.error = '';
      this.fieldErrors = {};
      try {
        const response = await api('/api/admin/platform-fee-settings');
        const settings = response?.data || response || defaults();
        this.form = {
          default_due_days: Number(settings.default_due_days || 7),
          lock_reason: String(settings.lock_reason || defaults().lock_reason),
        };
        this.savedForm = { ...this.form };
        this.loadedForOpen = true;
      } catch (error) {
        this.error = error?.message || 'Không thể tải cài đặt nhắc phí.';
      } finally {
        this.loading = false;
      }
    },
    validate() {
      const errors = {};
      const days = Number(this.form.default_due_days);
      const reason = String(this.form.lock_reason || '').trim();
      if (!Number.isInteger(days) || days < 1 || days > 30) {
        errors.default_due_days = ['Số ngày nhắc phải là số nguyên từ 1 đến 30.'];
      }
      if (reason.length < 3 || reason.length > 500) {
        errors.lock_reason = ['Lý do khóa phải có từ 3 đến 500 ký tự.'];
      }
      this.fieldErrors = errors;
      return Object.keys(errors).length === 0;
    },
    fieldError(field) {
      return this.fieldErrors[field]?.[0] || '';
    },
    async save() {
      if (!this.validate() || this.saving) return;
      this.saving = true;
      this.error = '';
      try {
        const response = await api('/api/admin/platform-fee-settings', {
          method: 'PUT',
          body: JSON.stringify({
            ...this.form,
            lock_reason: this.form.lock_reason.trim(),
          }),
        });
        const settings = response?.data || response;
        this.form = {
          default_due_days: Number(settings.default_due_days || this.form.default_due_days),
          lock_reason: String(settings.lock_reason || this.form.lock_reason),
        };
        this.savedForm = { ...this.form };
        this.fieldErrors = {};
        this.$emit('saved', settings);
      } catch (error) {
        this.fieldErrors = error?.data?.errors || {};
        this.error = error?.message || 'Không thể lưu cài đặt nhắc phí.';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.pf-settings-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1100;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.56);
}

.pf-settings-dialog {
  width: min(520px, 100%);
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #101c15);
  box-shadow: var(--admin-shadow-lg, 0 24px 70px rgba(23, 34, 27, 0.16));
}

.pf-settings-header,
.pf-settings-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.pf-settings-header {
  padding: 22px 24px 16px;
}

.pf-settings-kicker {
  margin: 0 0 4px;
  color: var(--admin-primary-dark, #15733a);
  font-size: 12px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.pf-settings-header h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 400;
}

.pf-settings-close {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border: 0;
  background: transparent;
  color: var(--admin-muted, #2f3d34);
  cursor: pointer;
}

.pf-settings-form {
  display: grid;
  gap: 18px;
  padding: 8px 24px 24px;
}

.pf-settings-field {
  display: grid;
  gap: 7px;
  color: var(--admin-text, #101c15);
  font-size: 14px;
}

.pf-settings-field input,
.pf-settings-field textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid var(--admin-border, #cfded1);
  border-radius: 4px;
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #101c15);
  padding: 11px 12px;
  font: inherit;
}

.pf-settings-field input:focus,
.pf-settings-field textarea:focus,
.pf-settings-close:focus-visible,
.pf-settings-button:focus-visible {
  outline: 3px solid var(--admin-primary-ring, rgba(34, 166, 83, 0.22));
  outline-offset: 2px;
}

.pf-settings-field small {
  color: var(--admin-muted, #2f3d34);
  font-size: 12px;
}

.pf-settings-field .pf-settings-error-text {
  color: var(--admin-danger-text, #991b1b);
}

.pf-settings-error {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  background: var(--admin-danger-soft, #fef2f2);
  color: var(--admin-danger-text, #991b1b);
  font-size: 13px;
}

.pf-settings-actions {
  justify-content: flex-end;
  padding-top: 8px;
}

.pf-settings-button {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border: 0;
  border-radius: 4px;
  padding: 0 16px;
  font: inherit;
  cursor: pointer;
}

.pf-settings-button.primary {
  background: var(--admin-primary, #22a653);
  color: var(--admin-primary-text, #fff);
}

.pf-settings-button.secondary {
  background: var(--admin-surface-muted, #f3f7f4);
  color: var(--admin-text, #101c15);
}

.pf-settings-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.pf-settings-state {
  display: flex;
  align-items: center;
  gap: 9px;
  min-height: 140px;
  justify-content: center;
  padding: 24px;
  color: var(--admin-muted, #2f3d34);
}

.pf-settings-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid var(--admin-border, #cfded1);
  border-left-color: var(--admin-primary, #22a653);
  border-radius: 50%;
  animation: pf-settings-spin 700ms linear infinite;
}

@keyframes pf-settings-spin {
  to { transform: rotate(360deg); }
}
</style>

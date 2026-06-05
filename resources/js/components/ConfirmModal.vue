<template>
  <Teleport to="body">
    <div v-if="modelValue" class="confirm-backdrop" @click.self="cancel">
      <section class="confirm-modal" :class="`confirm-${type}`" role="dialog" aria-modal="true">
        <header class="confirm-header">
          <div class="confirm-mark" aria-hidden="true">{{ mark }}</div>
          <div>
            <h3>{{ title }}</h3>
            <p v-if="message">{{ message }}</p>
          </div>
        </header>

        <div v-if="consequence" class="confirm-note">
          {{ consequence }}
        </div>

        <footer class="confirm-actions">
          <button class="btn secondary" type="button" @click="cancel">
            {{ cancelText }}
          </button>
          <button class="btn primary" :class="`btn-${type}`" type="button" @click="confirm">
            {{ confirmText }}
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<script>
export default {
  name: 'ConfirmModal',
  props: {
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: 'Xác nhận thao tác' },
    message: { type: String, default: 'Bạn có chắc chắn muốn thực hiện thao tác này?' },
    consequence: { type: String, default: '' },
    confirmText: { type: String, default: 'Xác nhận' },
    cancelText: { type: String, default: 'Hủy' },
    type: { type: String, default: 'warning' },
  },
  emits: ['update:modelValue', 'confirm', 'cancel'],
  computed: {
    mark() {
      if (this.type === 'danger') return '!';
      if (this.type === 'info') return 'i';
      return '?';
    },
  },
  methods: {
    confirm() {
      this.$emit('confirm');
      this.$emit('update:modelValue', false);
    },
    cancel() {
      this.$emit('cancel');
      this.$emit('update:modelValue', false);
    },
  },
};
</script>

<style scoped>
.confirm-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.55);
}

.confirm-modal {
  width: min(480px, calc(100vw - 32px));
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 22px 70px rgba(15, 23, 42, 0.28);
  overflow: hidden;
}

.confirm-header {
  display: flex;
  gap: 14px;
  padding: 22px 24px 16px;
}

.confirm-mark {
  display: grid;
  place-items: center;
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
  font-weight: 800;
}

.confirm-danger .confirm-mark {
  background: #fee2e2;
  color: #991b1b;
}

.confirm-info .confirm-mark {
  background: #dbeafe;
  color: #1d4ed8;
}

h3 {
  margin: 0;
  color: #0f172a;
  font-size: 18px;
}

p {
  margin: 6px 0 0;
  color: #475569;
  line-height: 1.55;
}

.confirm-note {
  margin: 0 24px 18px;
  padding: 12px 14px;
  border: 1px solid #fde68a;
  border-radius: 8px;
  background: #fffbeb;
  color: #92400e;
  line-height: 1.5;
}

.confirm-danger .confirm-note {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 24px 20px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.btn {
  border: 0;
  border-radius: 8px;
  padding: 9px 16px;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.btn.secondary {
  background: #e2e8f0;
  color: #334155;
}

.btn.primary {
  background: #16a34a;
  color: #fff;
}

.btn-danger {
  background: #dc2626 !important;
}

.btn-warning {
  background: #d97706 !important;
}
</style>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="confirm-backdrop" @click.self="cancel">
      <section class="confirm-modal" :class="`confirm-${type}`" role="dialog" aria-modal="true">
        <div class="confirm-body">
          <h3>{{ title }}</h3>
          <p v-if="message">{{ message }}</p>
          <div v-if="consequence" class="confirm-note">
            {{ consequence }}
          </div>
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
  width: min(420px, calc(100vw - 32px));
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  box-shadow: 0 16px 48px rgba(15, 23, 42, 0.16);
  overflow: hidden;
  padding: 24px;
}

.confirm-body {
  padding: 0;
  margin-bottom: 20px;
}

h3 {
  margin: 0;
  color: #0f172a;
  font-size: 17px;
  font-weight: 500;
}

p {
  margin: 8px 0 0;
  color: #334155;
  font-size: 14px;
  line-height: 1.55;
  font-weight: 400;
}

.confirm-note {
  margin-top: 12px;
  padding: 10px 14px;
  border-radius: 6px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #991b1b;
  font-size: 13px;
  line-height: 1.5;
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 0;
  background: #ffffff;
  border-top: none;
}

.btn {
  border-radius: 6px;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn.secondary {
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #1e293b;
}

.btn.secondary:hover {
  background: #f8fafc;
}

.btn.primary {
  border: 1px solid #15803d;
  background: #15803d;
  color: #ffffff;
}

.btn.primary:hover {
  background: #166534;
}

.btn-danger {
  border-color: #dc2626 !important;
  background: #dc2626 !important;
  color: #ffffff !important;
}

.btn-danger:hover {
  background: #b91c1c !important;
}
</style>

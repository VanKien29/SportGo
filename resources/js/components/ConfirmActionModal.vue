<template>
  <div v-if="isOpen" class="confirm-overlay" role="presentation" @click.self="close">
    <section class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
      <header>
        <div class="confirm-icon" :class="tone"><AppIcon :name="iconName" size="20" /></div>
        <div>
          <h2 id="confirm-title">{{ title }}</h2>
          <p>{{ description }}</p>
        </div>
      </header>

      <label v-if="requireReason" class="reason-field">
        <span>{{ reasonLabel }}</span>
        <textarea v-model.trim="reason" rows="4" :maxlength="maxLength" :placeholder="reasonPlaceholder"></textarea>
        <small>{{ reason.length }}/{{ maxLength }}</small>
      </label>

      <p v-if="error" class="confirm-error" role="alert">{{ error }}</p>

      <footer>
        <SgButton type="secondary" :disabled="loading" @click="close">Quay lại</SgButton>
        <SgButton :type="tone === 'danger' ? 'danger' : 'primary'" :loading="loading" :disabled="requireReason && !reason" @click="confirm">
          {{ confirmText }}
        </SgButton>
      </footer>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import SgButton from './common/SgButton.vue';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  confirmText: { type: String, default: 'Xác nhận' },
  tone: { type: String, default: 'danger' },
  requireReason: { type: Boolean, default: false },
  reasonLabel: { type: String, default: 'Lý do' },
  reasonPlaceholder: { type: String, default: 'Nhập lý do thực hiện thao tác' },
  initialReason: { type: String, default: '' },
  maxLength: { type: Number, default: 1000 },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
});
const emit = defineEmits(['close', 'confirm']);
const reason = ref('');
const iconName = computed(() => props.tone === 'danger' ? 'alert' : 'circleCheck');

function close() {
  if (!props.loading) emit('close');
}

function confirm() {
  if (props.requireReason && !reason.value) return;
  emit('confirm', reason.value);
}

watch(() => props.isOpen, (isOpen) => {
  if (isOpen) reason.value = props.initialReason;
  else if (!props.loading) reason.value = '';
});
</script>

<style scoped>
.confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 1100;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(9, 9, 11, 0.64);
}

.confirm-modal {
  width: min(100%, 480px);
  padding: 20px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
  color: var(--admin-text);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.confirm-modal header,
.confirm-modal footer {
  display: flex;
  align-items: flex-start;
}

.confirm-modal header {
  gap: 12px;
}

.confirm-icon {
  display: grid;
  width: 38px;
  height: 38px;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 50%;
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

.confirm-icon.danger {
  background: color-mix(in srgb, var(--admin-danger) 10%, var(--admin-surface));
  color: var(--admin-danger-text);
}

.confirm-modal h2 {
  margin: 0;
  font-size: var(--admin-font-size-xl);
}

.confirm-modal header p {
  margin: 6px 0 0;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
  line-height: 1.5;
}

.reason-field {
  display: grid;
  gap: 7px;
  margin-top: 18px;
}

.reason-field > span {
  font-size: var(--admin-font-size-base);
  font-weight: 600;
}

.reason-field textarea {
  width: 100%;
  min-height: 98px;
  box-sizing: border-box;
  padding: 10px 12px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font: inherit;
  font-size: var(--admin-font-size-base);
  outline: none;
  resize: vertical;
}

.reason-field textarea:focus {
  border-color: var(--admin-primary);
}

.reason-field small {
  justify-self: end;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-xs);
}

.confirm-error {
  margin: 12px 0 0;
  padding: 9px 11px;
  border: 1px solid var(--admin-danger);
  border-radius: var(--admin-radius);
  background: color-mix(in srgb, var(--admin-danger) 8%, var(--admin-surface));
  color: var(--admin-danger-text);
  font-size: var(--admin-font-size-sm);
}

.confirm-modal footer {
  justify-content: flex-end;
  gap: 9px;
  margin-top: 18px;
  padding-top: 16px;
  border-top: 1px solid var(--admin-border-soft);
}
</style>

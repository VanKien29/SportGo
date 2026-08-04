<template>
  <div class="combo-wrapper" tabindex="-1" @focusout="handleFocusOut">
    <div class="relative">
      <input
        :value="query"
        :placeholder="placeholder"
        :disabled="disabled"
        class="form-select"
        :class="invalid ? 'has-error' : ''"
        @focus="!disabled && (open = true)"
        @blur="onBlur"
        @input="onInput"
      />
      <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
      </svg>
    </div>
    <div v-if="open && !disabled" class="combo-list">
      <button
        v-for="o in filtered"
        :key="optionValue(o)"
        type="button"
        class="combo-item"
        :class="optionValue(o) === String(modelValue) ? 'active' : ''"
        @mousedown.prevent="choose(o)"
      >
        <span class="truncate">{{ optionLabel(o) }}</span>
        <svg v-if="optionValue(o) === String(modelValue)" class="ml-3 h-3.5 w-3.5 shrink-0 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.005L3.29 9.27a1 1 0 1 1 1.42-1.41l4.04 4.04 6.54-6.604a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd"/>
        </svg>
      </button>
      <p v-if="filtered.length === 0" class="combo-empty">Không tìm thấy lựa chọn phù hợp.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Chọn' },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select']);

const open = ref(false);
const query = ref('');

const removeAccents = (str) => {
  if (!str) return '';
  return String(str)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase();
};

const optionValue = (o) => (typeof o === 'object' ? String(o.value) : String(o));
const optionLabel = (o) => (typeof o === 'object' ? o.label : o);

const selected = computed(() => props.options.find(o => optionValue(o) === String(props.modelValue)));

const filtered = computed(() => {
  if (!query.value || (selected.value && query.value === optionLabel(selected.value))) {
    return props.options;
  }
  const q = removeAccents(query.value);
  return props.options.filter(o => {
    const label = optionLabel(o);
    return removeAccents(label).includes(q) || String(label).toLowerCase().includes(query.value.toLowerCase());
  });
});

const handleFocusOut = (e) => {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    open.value = false;
  }
};

watch([selected, () => props.options], ([o]) => {
  if (!open.value) query.value = o ? optionLabel(o) : (props.modelValue ? String(props.modelValue) : '');
}, { immediate: true });

const choose = (o) => {
  emit('update:modelValue', optionValue(o));
  emit('select', o);
  query.value = optionLabel(o);
  open.value = false;
};

const onInput = (e) => {
  query.value = e.target.value;
  open.value = true;
};

const onBlur = () => {
  window.setTimeout(() => {
    open.value = false;
    query.value = selected.value ? optionLabel(selected.value) : '';
  }, 130);
};
</script>

<style scoped>
.combo-wrapper {
  position: relative;
  width: 100%;
}
.combo-list {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--admin-surface, #18181b);
  color: var(--admin-text, #f4f4f5);
  border: 1px solid var(--admin-border, #3f3f46);
  border-radius: 8px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.35);
  max-height: 220px;
  overflow-y: auto;
  z-index: 100;
  padding: 4px;
}
.combo-item {
  width: 100%;
  padding: 8px 12px;
  font-size: 13.5px;
  text-align: left;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--admin-text, #f4f4f5);
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  transition: background 150ms;
}
.combo-item:hover,
.combo-item.active {
  background: var(--admin-hover, rgba(255, 255, 255, 0.08));
  color: var(--admin-text, #f4f4f5);
}
.combo-empty {
  padding: 10px 12px;
  font-size: 13px;
  color: var(--admin-faint, #94a3b8);
  margin: 0;
}
</style>

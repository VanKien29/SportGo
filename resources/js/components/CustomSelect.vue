<template>
  <div ref="root" class="custom-select" :class="{ open: isOpen, disabled }">
    <button
      type="button"
      class="select-trigger"
      :disabled="disabled"
      :aria-expanded="isOpen"
      aria-haspopup="listbox"
      @click="isOpen = !isOpen"
    >
      <span class="selected-text">{{ selectedLabel }}</span>
      <AppIcon :name="disabled ? 'lock' : 'chevronDown'" size="15" />
    </button>

    <Transition name="select-fade">
      <ul v-if="isOpen" class="options-list" role="listbox">
        <li
          v-for="option in normalizedOptions"
          :key="option.value"
          role="option"
          tabindex="0"
          :aria-selected="sameValue(modelValue, option.value)"
          :class="{ selected: sameValue(modelValue, option.value) }"
          @click="selectOption(option)"
          @keydown.enter.prevent="selectOption(option)"
          @keydown.space.prevent="selectOption(option)"
        >
          <span>{{ option.label }}</span>
          <AppIcon v-if="sameValue(modelValue, option.value)" name="check" size="15" />
        </li>
      </ul>
    </Transition>
  </div>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'CustomSelect',
  components: { AppIcon },
  props: {
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Chọn...' },
    disabled: { type: Boolean, default: false },
  },
  emits: ['update:modelValue', 'change'],
  data() {
    return { isOpen: false };
  },
  computed: {
    normalizedOptions() {
      return this.options.map((option) => (
        typeof option === 'object' ? option : { label: option, value: option }
      ));
    },
    selectedLabel() {
      return this.normalizedOptions.find((option) => this.sameValue(option.value, this.modelValue))?.label
        || this.placeholder;
    },
  },
  watch: {
    disabled(disabled) {
      if (disabled) this.isOpen = false;
    },
  },
  mounted() {
    document.addEventListener('click', this.onOutsideClick);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.onOutsideClick);
  },
  methods: {
    sameValue(left, right) {
      return String(left ?? '') === String(right ?? '');
    },
    selectOption(option) {
      this.$emit('update:modelValue', option.value);
      this.$emit('change', option.value);
      this.isOpen = false;
    },
    onOutsideClick(event) {
      if (this.isOpen && this.$refs.root && !this.$refs.root.contains(event.target)) {
        this.isOpen = false;
      }
    },
  },
};
</script>

<style scoped>
.custom-select {
  position: relative;
  min-width: 160px;
  font-size: var(--custom-select-font-size, var(--admin-font-size-base, 14px));
}

.select-trigger {
  display: flex;
  width: 100%;
  min-height: var(--custom-select-height, 40px);
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 11px;
  border: 1px solid var(--custom-select-border, var(--admin-border, #cfded1));
  border-radius: var(--custom-select-radius, var(--admin-radius, 8px));
  background: var(--custom-select-surface, var(--admin-surface, #fff));
  color: var(--custom-select-text, var(--admin-text, #101c15));
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.custom-select.open .select-trigger {
  border-color: var(--custom-select-primary, var(--admin-primary, #22a653));
  box-shadow: var(--custom-select-focus-ring, none);
}

.select-trigger:disabled {
  background: var(--custom-select-muted-surface, var(--admin-surface-muted, #f3f8f1));
  color: var(--custom-select-faint, var(--admin-faint, #718078));
  cursor: not-allowed;
  opacity: 0.72;
}

.selected-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.options-list {
  position: absolute;
  top: calc(100% + 5px);
  right: 0;
  left: 0;
  z-index: 120;
  max-height: 240px;
  overflow-y: auto;
  margin: 0;
  padding: 5px;
  border: 1px solid var(--custom-select-border, var(--admin-border, #cfded1));
  border-radius: var(--custom-select-radius, var(--admin-radius, 8px));
  background: var(--custom-select-surface, var(--admin-surface, #fff));
  box-shadow: var(--custom-select-shadow, 0 14px 34px rgba(15, 35, 24, 0.12));
  list-style: none;
}

.options-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 9px 10px;
  border-radius: calc(var(--custom-select-radius, var(--admin-radius, 8px)) - 2px);
  color: var(--custom-select-text, var(--admin-text, #101c15));
  cursor: pointer;
  outline: none;
}

.options-list li:hover,
.options-list li:focus-visible,
.options-list li.selected {
  background: var(--custom-select-primary-soft, var(--admin-primary-soft, #e2f6e8));
  color: var(--custom-select-primary-dark, var(--admin-primary-dark, #15733a));
}

.select-fade-enter-active,
.select-fade-leave-active {
  transition: opacity var(--custom-select-transition, var(--admin-transition-fast, 160ms)) ease,
    transform var(--custom-select-transition, var(--admin-transition-fast, 160ms)) ease;
}

.select-fade-enter-from,
.select-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>

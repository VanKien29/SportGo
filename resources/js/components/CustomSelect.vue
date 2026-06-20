<template>
  <div class="custom-select" :class="{ 'is-open': isOpen }" v-click-outside="closeDropdown">
    <div class="select-trigger" @click="toggleDropdown">
      <span class="selected-text">{{ selectedLabel }}</span>
      <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </div>
    <transition name="dropdown-fade">
      <ul v-if="isOpen" class="options-list">
        <li 
          v-for="option in normalizedOptions" 
          :key="option.value"
          class="option-item"
          :class="{ 'is-selected': modelValue === option.value }"
          @click="selectOption(option)"
        >
          {{ option.label }}
        </li>
      </ul>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'CustomSelect',
  props: {
    modelValue: {
      type: [String, Number],
      default: ''
    },
    options: {
      type: Array,
      default: () => []
    },
    placeholder: {
      type: String,
      default: 'Chọn...'
    }
  },
  emits: ['update:modelValue', 'change'],
  data() {
    return {
      isOpen: false
    };
  },
  computed: {
    normalizedOptions() {
      // If options are strings, convert to { label, value }
      if (this.options.length > 0 && typeof this.options[0] !== 'object') {
        return this.options.map(opt => ({ label: opt, value: opt }));
      }
      return this.options;
    },
    selectedLabel() {
      const selected = this.normalizedOptions.find(opt => opt.value === this.modelValue);
      return selected ? selected.label : this.placeholder;
    }
  },
  methods: {
    toggleDropdown() {
      this.isOpen = !this.isOpen;
    },
    closeDropdown() {
      this.isOpen = false;
    },
    selectOption(option) {
      this.$emit('update:modelValue', option.value);
      this.$emit('change', option.value);
      this.isOpen = false;
    }
  },
  directives: {
    clickOutside: {
      mounted(el, binding) {
        el.clickOutsideEvent = function(event) {
          if (!(el === event.target || el.contains(event.target))) {
            binding.value(event);
          }
        };
        document.addEventListener('click', el.clickOutsideEvent);
      },
      unmounted(el) {
        document.removeEventListener('click', el.clickOutsideEvent);
      }
    }
  }
};
</script>

<style scoped>
.custom-select {
  position: relative;
  min-width: 160px;
  user-select: none;
  font-size: 13px;
  font-weight: 500;
}

.select-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #fff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  height: 40px;
  box-sizing: border-box;
}

.custom-select.is-open .select-trigger,
.select-trigger:hover {
  border-color: #94a3b8;
}

.selected-text {
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-right: 8px;
}

.chevron {
  color: #64748b;
  transition: transform 0.2s ease;
  flex-shrink: 0;
}

.custom-select.is-open .chevron {
  transform: rotate(180deg);
}

.options-list {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  list-style: none;
  padding: 4px;
  margin: 0;
  max-height: 250px;
  overflow-y: auto;
  z-index: 100;
}

.option-item {
  padding: 8px 12px;
  border-radius: 4px;
  cursor: pointer;
  color: #334155;
  transition: background 0.15s ease;
}

.option-item:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.option-item.is-selected {
  background: #eff6ff;
  color: #1e40af;
  font-weight: 700;
}

.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.2s, transform 0.2s;
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}
</style>

<template>
  <div ref="rootRef" class="ccs-wrap" :class="{ 'is-open': isOpen, 'is-disabled': disabled }">
    <button
      type="button"
      class="ccs-trigger"
      :disabled="disabled"
      @click="toggle"
    >
      <div class="ccs-left">
        <svg v-if="icon === 'clock'" class="ccs-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        <svg v-else-if="icon === 'court'" class="ccs-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="3" width="20" height="18" rx="2"></rect>
          <line x1="12" y1="3" x2="12" y2="21"></line>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
        <span class="ccs-val">{{ selectedLabel }}</span>
      </div>
      <svg class="ccs-chevron" :class="{ 'is-flipped': isOpen }" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </button>

    <transition name="ccs-pop">
      <div v-if="isOpen" class="ccs-menu" @click.stop>
        <div class="ccs-menu-scroll" ref="scrollRef">
          <div
            v-for="opt in normalizedOptions"
            :key="opt.value"
            class="ccs-opt"
            :class="{ 'is-selected': isSelected(opt.value), 'has-sublabel': opt.sublabel }"
            @click="onSelect(opt.value)"
          >
            <div class="ccs-opt-body">
              <span class="ccs-opt-label">{{ opt.label }}</span>
              <span v-if="opt.sublabel" class="ccs-opt-sub">{{ opt.sublabel }}</span>
            </div>
            <svg v-if="isSelected(opt.value)" class="ccs-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'ClientCustomSelect',
  props: {
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Chọn...' },
    icon: { type: String, default: '' },
    disabled: { type: Boolean, default: false }
  },
  emits: ['update:modelValue', 'change'],
  data() {
    return {
      isOpen: false
    };
  },
  computed: {
    normalizedOptions() {
      return this.options.map(opt => {
        if (typeof opt === 'object' && opt !== null) {
          return {
            label: opt.label ?? opt.name ?? String(opt.value),
            sublabel: opt.sublabel || null,
            value: opt.value
          };
        }
        return { label: String(opt), sublabel: null, value: opt };
      });
    },
    selectedLabel() {
      const found = this.normalizedOptions.find(o => this.isSelected(o.value));
      return found ? found.label : this.placeholder;
    }
  },
  mounted() {
    document.addEventListener('click', this.onOutsideClick);
    window.addEventListener('ccs-opened', this.onOtherOpened);
    window.addEventListener('adp-opened', this.close);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.onOutsideClick);
    window.removeEventListener('ccs-opened', this.onOtherOpened);
    window.removeEventListener('adp-opened', this.close);
  },
  methods: {
    isSelected(val) {
      return String(this.modelValue ?? '') === String(val ?? '');
    },
    toggle() {
      if (this.disabled) return;
      this.isOpen = !this.isOpen;
      if (this.isOpen) {
        window.dispatchEvent(new CustomEvent('ccs-opened', { detail: this }));
        this.$nextTick(() => {
          this.scrollToSelected();
        });
      }
    },
    close() {
      this.isOpen = false;
    },
    onOtherOpened(e) {
      if (e.detail !== this) {
        this.isOpen = false;
      }
    },
    onOutsideClick(e) {
      if (this.$refs.rootRef && !this.$refs.rootRef.contains(e.target)) {
        this.isOpen = false;
      }
    },
    onSelect(val) {
      this.$emit('update:modelValue', val);
      this.$emit('change', val);
      this.isOpen = false;
    },
    scrollToSelected() {
      if (!this.$refs.scrollRef) return;
      const selectedEl = this.$refs.scrollRef.querySelector('.ccs-opt.is-selected');
      if (selectedEl) {
        selectedEl.scrollIntoView({ block: 'nearest' });
      }
    }
  }
};
</script>

<style scoped>
.ccs-wrap {
  position: relative;
  width: 100%;
  box-sizing: border-box;
}

.ccs-trigger {
  width: 100%;
  height: 38px;
  padding: 0 10px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  cursor: pointer;
  box-sizing: border-box;
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 500;
  transition: border-color 0.15s ease;
  text-align: left;
}

.ccs-trigger:hover:not(:disabled) {
  border-color: #15803d;
}

.ccs-wrap.is-open .ccs-trigger {
  border-color: #15803d;
}

.ccs-trigger:disabled {
  background: #f8fafc;
  color: #94a3b8;
  cursor: not-allowed;
  opacity: 0.7;
}

.ccs-left {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
  flex: 1;
}

.ccs-icon {
  flex-shrink: 0;
  color: #15803d;
}

.ccs-val {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 13.5px;
  color: #0f172a;
}

.ccs-chevron {
  flex-shrink: 0;
  color: #475569;
  transition: transform 0.15s ease;
}

.ccs-chevron.is-flipped {
  transform: rotate(180deg);
}

.ccs-menu {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 1000;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1), 0 4px 10px rgba(15, 23, 42, 0.05);
  overflow: hidden;
}

.ccs-menu-scroll {
  max-height: 210px;
  overflow-y: auto;
  padding: 4px;
}

.ccs-opt {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.1s ease, color 0.1s ease;
  gap: 10px;
}

.ccs-opt-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.ccs-opt-label {
  font-size: 13px;
  color: #0f172a;
  font-weight: 500;
  line-height: 1.3;
}

.ccs-opt-sub {
  font-size: 11.5px;
  color: #475569;
  line-height: 1.2;
}

.ccs-opt:hover:not(.is-selected) {
  background: #f8fafc;
}

.ccs-opt:hover:not(.is-selected) .ccs-opt-label {
  color: #15803d;
}

.ccs-opt.is-selected {
  background: #15803d;
  color: #ffffff;
}

.ccs-opt.is-selected .ccs-opt-label {
  color: #ffffff;
  font-weight: 500;
}

.ccs-opt.is-selected .ccs-opt-sub {
  color: rgba(255, 255, 255, 0.88);
}

.ccs-check {
  flex-shrink: 0;
  stroke: #ffffff;
}

/* Animations */
.ccs-pop-enter-active,
.ccs-pop-leave-active {
  transition: opacity 0.12s ease, transform 0.12s ease;
}

.ccs-pop-enter-from,
.ccs-pop-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>

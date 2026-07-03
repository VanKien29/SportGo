<template>
  <div class="theme-toggle-container" v-click-outside="closeDropdown">
    <!-- Trigger Button -->
    <button
      type="button"
      class="theme-toggle-btn"
      @click="toggleDropdown"
      :title="`Giao diện: ${currentThemeLabel}`"
      aria-label="Toggle theme"
    >
      <!-- Current Theme Icon -->
      <svg v-if="activeTheme === 'light'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M22 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
      <svg v-else-if="activeTheme === 'dark'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-moon"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-monitor"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
    </button>

    <!-- Dropdown Menu -->
    <transition name="fade-slide">
      <div v-if="isOpen" class="theme-dropdown">
        <button
          v-for="opt in options"
          :key="opt.value"
          type="button"
          class="theme-dropdown-item"
          :class="{ active: activeTheme === opt.value }"
          @click="selectTheme(opt.value)"
        >
          <!-- Option Icon -->
          <span class="theme-icon-wrapper">
            <svg v-if="opt.value === 'light'" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M22 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
            <svg v-else-if="opt.value === 'dark'" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
          </span>
          <span class="theme-label">{{ opt.label }}</span>
        </button>
      </div>
    </transition>
  </div>
</template>

<script>
import { applyCustomThemeStyles } from '../../utils/theme.js';

export default {
  name: 'ThemeToggle',
  directives: {
    'click-outside': {
      beforeMount(el, binding) {
        el.clickOutsideEvent = function(event) {
          if (!(el === event.target || el.contains(event.target))) {
            binding.value(event);
          }
        };
        document.body.addEventListener('click', el.clickOutsideEvent);
      },
      unmounted(el) {
        document.body.removeEventListener('click', el.clickOutsideEvent);
      }
    }
  },
  data() {
    return {
      isOpen: false,
      activeTheme: 'system',
      options: [
        { value: 'light', label: 'Light' },
        { value: 'dark', label: 'Dark' },
        { value: 'system', label: 'System' }
      ]
    };
  },
  computed: {
    currentThemeLabel() {
      return this.options.find(o => o.value === this.activeTheme)?.label || 'System';
    }
  },
  created() {
    this.activeTheme = localStorage.getItem('admin-theme') || 'system';
    this.applyTheme(this.activeTheme);
    applyCustomThemeStyles();

    this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    if (this.mediaQuery.addEventListener) {
      this.mediaQuery.addEventListener('change', this.handleSystemThemeChange);
    } else if (this.mediaQuery.addListener) {
      this.mediaQuery.addListener(this.handleSystemThemeChange);
    }
  },
  beforeUnmount() {
    if (this.mediaQuery) {
      if (this.mediaQuery.removeEventListener) {
        this.mediaQuery.removeEventListener('change', this.handleSystemThemeChange);
      } else if (this.mediaQuery.removeListener) {
        this.mediaQuery.removeListener(this.handleSystemThemeChange);
      }
    }
  },
  methods: {
    toggleDropdown() {
      this.isOpen = !this.isOpen;
    },
    closeDropdown() {
      this.isOpen = false;
    },
    selectTheme(theme) {
      this.activeTheme = theme;
      localStorage.setItem('admin-theme', theme);
      this.applyTheme(theme);
      this.closeDropdown();
      this.$emit('theme-changed', theme);
    },
    applyTheme(theme) {
      let resolvedTheme = theme;
      if (theme === 'system') {
        resolvedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      }
      document.documentElement.setAttribute('data-theme', resolvedTheme);
      applyCustomThemeStyles();
    },
    handleSystemThemeChange() {
      if (this.activeTheme === 'system') {
        this.applyTheme('system');
      }
    }
  }
};
</script>

<style scoped>
.theme-toggle-container {
  position: relative;
  display: inline-block;
}

.theme-toggle-btn {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-muted);
  box-shadow: var(--admin-shadow-sm);
  cursor: pointer;
  outline: none;
  transition: border-color 180ms ease, background-color 180ms ease, color 180ms ease, transform 180ms ease;
}

.theme-toggle-btn:hover {
  border-color: rgba(47, 158, 68, 0.32);
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
  transform: translateY(-1px);
}

.theme-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 90;
  width: 130px;
  padding: 6px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
  box-shadow: var(--admin-shadow-lg);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.theme-dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 10px;
  border: 0;
  border-radius: var(--admin-radius);
  background: transparent;
  color: var(--admin-muted);
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
  transition: background-color 150ms ease, color 150ms ease;
}

.theme-dropdown-item:hover {
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

.theme-dropdown-item.active {
  background: var(--admin-hover);
  color: var(--admin-primary-dark);
}

.theme-icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
}

.theme-label {
  flex-grow: 1;
}

/* Animations */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 160ms ease, transform 160ms ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
</style>

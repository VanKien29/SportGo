<template>
  <div
    ref="container"
    class="float-menu-btn"
    :class="{ 'has-scroll-top': hasScrollTop }"
  >
    <!-- Toggle button (3-bar icon) -->
    <button
      class="float-menu-trigger"
      :class="{ 'is-open': isOpen }"
      @click.stop="toggleMenu"
      title="Menu hành động"
      type="button"
    >
      <!-- Hamburger icon -->
      <svg
        v-if="!isOpen"
        xmlns="http://www.w3.org/2000/svg"
        width="20"
        height="20"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2.2"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
      <!-- X icon when open -->
      <svg
        v-else
        xmlns="http://www.w3.org/2000/svg"
        width="20"
        height="20"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2.2"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <!-- Dropdown menu -->
    <Transition name="float-menu-fade">
      <div v-if="isOpen" class="float-menu-dropdown">
        <button
          v-for="action in actions"
          :key="action.key"
          type="button"
          class="float-menu-item"
          :disabled="action.disabled"
          @click="handleAction(action)"
        >
          <AppIcon v-if="action.icon" :name="action.icon" size="14" />
          <span>{{ action.label }}</span>
        </button>
      </div>
    </Transition>
  </div>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'FloatMenuButton',
  components: { AppIcon },
  emits: ['action'],
  props: {
    /**
     * Array of action objects:
     * { key: string, label: string, icon?: string, disabled?: boolean }
     */
    actions: {
      type: Array,
      default: () => [],
    },
    /** Scroll threshold before shifting left to avoid scroll-to-top button */
    threshold: {
      type: Number,
      default: 250,
    },
  },
  data() {
    return {
      isOpen: false,
      hasScrollTop: false,
    };
  },
  mounted() {
    document.addEventListener('click', this.handleOutsideClick);
    window.addEventListener('scroll', this.handleScroll);
    this.handleScroll();
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleOutsideClick);
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    toggleMenu() {
      this.isOpen = !this.isOpen;
    },
    handleAction(action) {
      if (action.disabled) return;
      this.isOpen = false;
      this.$emit('action', action.key);
    },
    handleOutsideClick(e) {
      if (this.isOpen && this.$refs.container && !this.$refs.container.contains(e.target)) {
        this.isOpen = false;
      }
    },
    handleScroll() {
      this.hasScrollTop = window.scrollY > this.threshold;
    },
  },
};
</script>

<style scoped>
.float-menu-btn {
  position: fixed;
  bottom: 30px;
  right: 30px;
  z-index: 9998;
  transition: right 0.25s ease;
}

.float-menu-btn.has-scroll-top {
  right: 86px;
}

/* Trigger button */
.float-menu-trigger {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--admin-floating-bg, #27272a);
  color: var(--admin-floating-fg, #ffffff);
  border: 1px solid var(--admin-floating-border, rgba(255, 255, 255, 0.14));
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.float-menu-trigger:hover,
.float-menu-trigger.is-open {
  transform: translateY(-2px);
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
  background-color: var(--admin-floating-hover, #3f3f46);
}

/* Dropdown */
.float-menu-dropdown {
  position: absolute;
  bottom: calc(100% + 10px);
  right: 0;
  background: var(--admin-floating-panel-bg, var(--admin-surface, #18181b));
  border: 1px solid var(--admin-floating-border, rgba(255, 255, 255, 0.14));
  border-radius: 10px;
  box-shadow: 0 18px 44px rgba(0, 0, 0, 0.32);
  z-index: 9999;
  min-width: 220px;
  padding: 6px 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.float-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 16px;
  border: 0;
  background: transparent;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--admin-floating-fg, #ffffff);
  cursor: pointer;
  transition: background 0.18s ease;
}

.float-menu-item:hover:not(:disabled) {
  background: var(--admin-floating-hover, rgba(255, 255, 255, 0.08));
}

.float-menu-item:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Transition */
.float-menu-fade-enter-active,
.float-menu-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.float-menu-fade-enter-from,
.float-menu-fade-leave-to {
  opacity: 0;
  transform: translateY(6px) scale(0.97);
}

/* Mobile */
@media (max-width: 768px) {
  .float-menu-btn {
    bottom: 20px;
    right: 20px;
  }
  .float-menu-btn.has-scroll-top {
    right: 72px;
  }
  .float-menu-trigger {
    width: 40px;
    height: 40px;
  }
}
</style>

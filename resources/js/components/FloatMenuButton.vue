<template>
  <div ref="container" class="float-menu" :class="{ shifted: hasScrollTop }">
    <Transition name="float-menu-fade">
      <div v-if="isOpen" class="float-menu-panel" role="menu">
        <button
          v-for="action in actions"
          :key="action.key"
          type="button"
          role="menuitem"
          :disabled="action.disabled"
          @click="selectAction(action)"
        >
          <AppIcon v-if="action.icon" :name="action.icon" size="16" />
          <span>{{ action.label }}</span>
        </button>
      </div>
    </Transition>

    <button
      type="button"
      class="float-menu-trigger"
      :class="{ open: isOpen }"
      :aria-expanded="isOpen"
      aria-label="Mở menu tạo nội dung"
      @click.stop="isOpen = !isOpen"
    >
      <AppIcon :name="isOpen ? 'close' : 'plus'" size="20" />
    </button>
  </div>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'FloatMenuButton',
  components: { AppIcon },
  emits: ['action'],
  props: {
    actions: { type: Array, default: () => [] },
    threshold: { type: Number, default: 250 },
  },
  data() {
    return { isOpen: false, hasScrollTop: false };
  },
  mounted() {
    document.addEventListener('click', this.onOutsideClick);
    window.addEventListener('scroll', this.onScroll, { passive: true });
    this.onScroll();
  },
  beforeUnmount() {
    document.removeEventListener('click', this.onOutsideClick);
    window.removeEventListener('scroll', this.onScroll);
  },
  methods: {
    selectAction(action) {
      if (action.disabled) return;
      this.isOpen = false;
      this.$emit('action', action.key);
    },
    onOutsideClick(event) {
      if (this.isOpen && this.$refs.container && !this.$refs.container.contains(event.target)) {
        this.isOpen = false;
      }
    },
    onScroll() {
      this.hasScrollTop = window.scrollY > this.threshold;
    },
  },
};
</script>

<style scoped>
.float-menu {
  position: fixed;
  right: 28px;
  bottom: 28px;
  z-index: 90;
  transition: right var(--admin-transition-fast) ease;
}

.float-menu.shifted {
  right: 82px;
}

.float-menu-trigger {
  display: grid;
  width: 46px;
  height: 46px;
  place-items: center;
  border: 1px solid var(--admin-floating-border);
  border-radius: 50%;
  background: var(--admin-floating-bg);
  color: var(--admin-floating-fg);
  box-shadow: 0 4px 16px var(--admin-primary-ring);
  cursor: pointer;
}

.float-menu-trigger:hover,
.float-menu-trigger.open {
  background: var(--admin-floating-hover);
}

.float-menu-panel {
  position: absolute;
  right: 0;
  bottom: calc(100% + 10px);
  display: grid;
  min-width: 230px;
  overflow: hidden;
  padding: 6px;
  border: 1px solid var(--admin-floating-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-floating-panel-bg);
}

.float-menu-panel button {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 0;
  border-radius: var(--admin-radius);
  background: transparent;
  color: var(--admin-floating-fg);
  font-size: var(--admin-font-size-base);
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.float-menu-panel button:hover:not(:disabled) {
  background: var(--admin-floating-hover);
}

.float-menu-panel button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.float-menu-fade-enter-active,
.float-menu-fade-leave-active {
  transition: opacity var(--admin-transition-fast) ease, transform var(--admin-transition-fast) ease;
}

.float-menu-fade-enter-from,
.float-menu-fade-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

@media (max-width: 640px) {
  .float-menu {
    right: 18px;
    bottom: 18px;
  }

  .float-menu.shifted {
    right: 68px;
  }
}
</style>

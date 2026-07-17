<template>
  <div class="float-menu" @keydown.esc="closeMenu">
    <button
      type="button"
      class="float-menu__trigger"
      :aria-expanded="isOpen ? 'true' : 'false'"
      aria-label="Mở menu tạo mới"
      @click="toggleMenu"
    >
      <AppIcon :name="isOpen ? 'x' : 'plus'" size="22" />
    </button>

    <transition name="float-menu">
      <div v-if="isOpen" class="float-menu__panel">
        <button
          v-for="action in actions"
          :key="action.key"
          type="button"
          class="float-menu__item"
          @click="selectAction(action.key)"
        >
          <span class="float-menu__icon">
            <AppIcon :name="action.icon || 'plus'" size="18" />
          </span>
          <span>{{ action.label }}</span>
        </button>
      </div>
    </transition>
  </div>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'FloatMenuButton',
  components: { AppIcon },
  props: {
    actions: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['action'],
  data() {
    return {
      isOpen: false,
    };
  },
  mounted() {
    document.addEventListener('click', this.handleOutsideClick);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleOutsideClick);
  },
  methods: {
    toggleMenu(event) {
      event.stopPropagation();
      this.isOpen = !this.isOpen;
    },
    closeMenu() {
      this.isOpen = false;
    },
    selectAction(actionKey) {
      this.$emit('action', actionKey);
      this.closeMenu();
    },
    handleOutsideClick(event) {
      if (!this.$el.contains(event.target)) {
        this.closeMenu();
      }
    },
  },
};
</script>

<style scoped>
.float-menu {
  position: relative;
  display: inline-flex;
  align-items: flex-end;
  flex-direction: column;
  gap: 10px;
}

.float-menu__trigger {
  width: 52px;
  height: 52px;
  border: 0;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: #16a34a;
  box-shadow: 0 16px 30px rgba(22, 163, 74, 0.28);
  cursor: pointer;
  transition: transform 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
}

.float-menu__trigger:hover {
  background: #15803d;
  transform: translateY(-1px);
  box-shadow: 0 18px 34px rgba(22, 163, 74, 0.34);
}

.float-menu__panel {
  position: absolute;
  right: 0;
  bottom: 64px;
  min-width: 240px;
  padding: 8px;
  border: 1px solid rgba(22, 163, 74, 0.18);
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
  z-index: 20;
}

.float-menu__item {
  width: 100%;
  border: 0;
  border-radius: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  color: #0f172a;
  background: transparent;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

.float-menu__item:hover {
  color: #047857;
  background: #ecfdf5;
}

.float-menu__icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #047857;
  background: #d1fae5;
}

.float-menu-enter-active,
.float-menu-leave-active {
  transition: opacity 0.16s ease, transform 0.16s ease;
}

.float-menu-enter-from,
.float-menu-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
</style>

<template>
  <div class="cluster-action-floating" :class="{ 'has-scroll-top': hasScrollTop }" v-if="!isLocked">
    <button 
      class="btn-floating" 
      :class="{ 'is-open': isOpen }"
      @click.stop="toggleMenu" 
      title="Menu yêu cầu hành động"
      type="button"
      :aria-expanded="isOpen ? 'true' : 'false'"
      aria-label="Menu yeu cau hanh dong"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    <div v-if="isOpen" class="floating-dropdown">
      <button type="button" @click="triggerAction('info')">
        <AppIcon name="pencil" size="14" />
        Yêu cầu sửa thông tin sân
      </button>
      <button type="button" @click="triggerAction('location')">
        <AppIcon name="pencil" size="14" />
        Yêu cầu sửa vị trí
      </button>
      <button type="button" @click="triggerAction('scale')">
        <AppIcon name="plus" size="14" />
        Yêu cầu sửa quy mô sân
      </button>
      <button type="button" @click="triggerAction('amenity')">
        <AppIcon name="shopping-bag" size="14" />
        Yêu cầu thêm tiện ích
      </button>
      <button type="button" @click="triggerAction('court_type')">
        <AppIcon name="court" size="14" />
        Yêu cầu thêm loại sân
      </button>
    </div>
  </div>
</template>

<script>
import AppIcon from '../AppIcon.vue';

export default {
  name: 'ClusterActionFloating',
  components: { AppIcon },
  props: {
    isLocked: {
      type: Boolean,
      default: false
    },
    threshold: {
      type: Number,
      default: 250
    }
  },
  data() {
    return {
      isOpen: false,
      hasScrollTop: false
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
    triggerAction(type) {
      this.isOpen = false;
      this.$emit('action', type);
    },
    handleOutsideClick(e) {
      if (this.isOpen && !this.$el.contains(e.target)) {
        this.isOpen = false;
      }
    },
    handleScroll() {
      this.hasScrollTop = window.scrollY > this.threshold;
    }
  }
};
</script>

<style scoped>
.cluster-action-floating {
  position: fixed;
  bottom: 30px;
  right: 30px;
  z-index: 9998;
  transition: right 0.25s ease, transform 0.25s ease;
}

.cluster-action-floating.has-scroll-top {
  right: 86px;
}

.btn-floating {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--admin-floating-bg, #1f2937);
  color: var(--admin-floating-fg, #ffffff);
  border: 1px solid var(--admin-floating-border, rgba(255, 255, 255, 0.14));
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
  cursor: pointer;
  transition: background-color 120ms ease-out, transform 80ms ease-out, box-shadow 120ms ease-out;
}

.btn-floating:hover,
.btn-floating.is-open {
  transform: translateY(-2px);
  background-color: var(--admin-floating-hover, #111827);
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
}

.btn-floating:active {
  transform: translateY(0) scale(0.97);
  background-color: var(--admin-floating-active, #0f172a);
}

.btn-floating:focus-visible {
  outline: 2px solid var(--admin-primary, #22c55e);
  outline-offset: 3px;
}

.floating-dropdown {
  position: absolute;
  bottom: calc(100% + 10px);
  right: 0;
  background: var(--admin-floating-panel-bg, #18181b);
  border: 1px solid var(--admin-floating-border, rgba(255, 255, 255, 0.14));
  border-radius: 8px;
  box-shadow: 0 18px 44px rgba(0, 0, 0, 0.32);
  z-index: 9999;
  min-width: 220px;
  padding: 6px 0;
  display: flex;
  flex-direction: column;
}

.floating-dropdown button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 16px;
  border: none;
  background: transparent;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--admin-floating-fg, #ffffff);
  cursor: pointer;
  transition: background-color 120ms ease-out;
}

.floating-dropdown button:hover {
  background: var(--admin-floating-hover, rgba(255, 255, 255, 0.08));
}

.floating-dropdown button:focus-visible {
  outline: 2px solid var(--admin-primary, #22c55e);
  outline-offset: -2px;
}

@media (max-width: 768px) {
  .cluster-action-floating {
    bottom: 20px;
    right: 20px;
  }
  .cluster-action-floating.has-scroll-top {
    right: 72px;
  }
  .btn-floating {
    width: 40px;
    height: 40px;
  }
}
</style>

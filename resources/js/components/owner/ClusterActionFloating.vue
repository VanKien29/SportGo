<template>
  <div class="cluster-action-floating" :class="{ 'has-scroll-top': hasScrollTop }" v-if="!isLocked">
    <button 
      class="btn-floating" 
      @click.stop="toggleMenu" 
      title="Menu yêu cầu hành động"
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
  background-color: #fff;
  color: var(--sg-text, #0f172a);
  border: 1px solid var(--sg-border, #e2e8f0);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.25s ease;
}

.btn-floating:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
  background-color: var(--sg-surface, #f1f5f9);
}

.floating-dropdown {
  position: absolute;
  bottom: calc(100% + 10px);
  right: 0;
  background: #fff;
  border: 1px solid var(--sg-border, #e2e8f0);
  border-radius: 8px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
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
  color: var(--sg-text, #0f172a);
  cursor: pointer;
  transition: background 0.2s;
}

.floating-dropdown button:hover {
  background: var(--sg-surface, #f1f5f9);
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

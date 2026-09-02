<template>
  <button
    v-if="showScrollTop"
    class="scroll-to-top-btn"
    @click="scrollToTop"
    title="Lên đầu trang"
    aria-label="Lên đầu trang"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 19V5M5 12l7-7 7 7"/>
    </svg>
  </button>
</template>

<script>
export default {
  name: 'ScrollToTopButton',
  props: {
    threshold: {
      type: Number,
      default: 250,
    },
  },
  data() {
    return {
      showScrollTop: false,
    }
  },
  mounted() {
    window.addEventListener('scroll', this.handleScroll);
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    handleScroll() {
      this.showScrollTop = window.scrollY > this.threshold;
    },
    scrollToTop() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
  },
}
</script>

<style scoped>
.scroll-to-top-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--admin-floating-bg, #1e293b);
  color: var(--admin-floating-fg, #ffffff);
  border: 1px solid var(--admin-floating-border, rgba(255, 255, 255, 0.15));
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.16);
  cursor: pointer;
  transition: background-color 150ms ease-out, transform 150ms ease-out, box-shadow 150ms ease-out;
}

.scroll-to-top-btn:hover {
  transform: translateY(-2px);
  background-color: #0f172a;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.scroll-to-top-btn:active {
  transform: translateY(0) scale(0.96);
  background-color: #020617;
}

.scroll-to-top-btn:focus-visible {
  outline: 2px solid #22c55e;
  outline-offset: 2px;
}

@media (max-width: 768px) {
  .scroll-to-top-btn {
    width: 42px;
    height: 42px;
  }
}
</style>

<template>
  <div class="floating-actions">
    <button 
      v-if="canGoBack" 
      class="action-btn back-btn" 
      @click="goBack"
      title="Quay lại"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </button>
    <button 
      v-if="showScrollTop" 
      class="action-btn scroll-btn" 
      @click="scrollToTop"
      title="Lên đầu trang"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
    </button>
  </div>
</template>

<script>
export default {
  name: 'FloatingActions',
  data() {
    return {
      showScrollTop: false,
    }
  },
  computed: {
    canGoBack() {
      if (!this.$route) return false;
      const path = this.$route.path;
      if (path === '/' || path === '/login' || path === '/register' || path.startsWith('/auth')) return false;
      
      const segments = path.split('/').filter(Boolean);
      const isDetail = Object.keys(this.$route.params).length > 0;
      
      // If it's under /admin or /owner, depth > 2 means it's a subpage (like /admin/settings/xxx)
      // Otherwise depth > 1 is a subpage
      let isDeep = false;
      if (segments[0] === 'admin' || segments[0] === 'owner') {
        isDeep = segments.length > 2;
      } else {
        isDeep = segments.length > 1;
      }

      return isDetail || isDeep;
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
      // Show scroll-to-top when scrolled down 250px
      this.showScrollTop = window.scrollY > 250;
    },
    scrollToTop() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    goBack() {
      if (window.history.length > 1) {
        this.$router.back();
      } else {
        // Fallback route if opened directly
        const segments = this.$route.path.split('/').filter(Boolean);
        if (segments.length > 1) {
          segments.pop();
          this.$router.push('/' + segments.join('/'));
        } else {
          this.$router.push('/');
        }
      }
    }
  }
}
</script>

<style scoped>
.floating-actions {
  position: fixed;
  bottom: 30px;
  right: 30px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  z-index: 9999;
}

.action-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--sg-text, #0f172a);
  color: #fff;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.25s ease;
}

.action-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
  background-color: #1e293b;
}

.back-btn {
  background-color: #fff;
  color: var(--sg-text, #0f172a);
  border: 1px solid var(--sg-border, #e2e8f0);
}

.back-btn:hover {
  background-color: #f8fafc;
  color: #0f172a;
}

@media (max-width: 768px) {
  .floating-actions {
    bottom: 20px;
    right: 20px;
  }
  .action-btn {
    width: 40px;
    height: 40px;
  }
}
</style>

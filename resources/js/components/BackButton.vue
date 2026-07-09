<template>
  <button
    class="action-btn back-btn"
    :class="{ 'is-floating': floating }"
    :title="title"
    @click="handleClick"
    type="button"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
  </button>
</template>

<script>
export default {
  name: 'BackButton',
  props: {
    to: {
      type: [String, Object],
      default: null
    },
    floating: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      default: 'Quay lại'
    }
  },
  methods: {
    handleClick(event) {
      this.$emit('click', event);
      if (event.defaultPrevented) return;

      if (this.to) {
        this.$router.push(this.to);
      } else {
        if (window.history.length > 1) {
          this.$router.back();
        } else {
          this.$router.push('/');
        }
      }
    }
  }
}
</script>

<style scoped>
.action-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background-color: var(--admin-floating-bg, #1f2937);
  color: var(--admin-floating-fg, #ffffff);
  border: 1px solid var(--admin-floating-border, rgba(255, 255, 255, 0.14));
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
  cursor: pointer;
  transition: background-color 120ms ease-out, transform 80ms ease-out, box-shadow 120ms ease-out;
}

.action-btn.never-hover-class-placeholder {
  transform: translateY(-2px);
  background-color: var(--admin-floating-hover, #111827);
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
}

.action-btn:active {
  transform: translateY(0) scale(0.97);
  background-color: var(--admin-floating-active, #0f172a);
}

.action-btn:focus-visible {
  outline: 2px solid var(--admin-primary, #22c55e);
  outline-offset: 3px;
}

.back-btn {
  background-color: var(--admin-floating-bg, #1f2937);
  color: var(--admin-floating-fg, #ffffff);
}

.back-btn.never-hover-class-placeholder {
  background-color: var(--admin-floating-hover, #111827);
  color: var(--admin-floating-fg, #ffffff);
}

.is-floating {
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
}

@media (max-width: 768px) {
  .action-btn {
    width: 40px;
    height: 40px;
  }
}
</style>

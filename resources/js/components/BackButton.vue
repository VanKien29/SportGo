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
  background-color: var(--sg-text, #0f172a);
  color: #fff;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.25s ease;
}

.action-btn:hover {
  transform: translateY(-2px);
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

.is-floating {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

@media (max-width: 768px) {
  .action-btn {
    width: 40px;
    height: 40px;
  }
}
</style>

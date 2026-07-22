<template>
  <section class="sg-table-card">
    <div v-if="title || $slots['header-actions']" class="sg-table-card-head">
      <h4 v-if="title" class="sg-table-card-title">{{ title }}</h4>
      <div v-if="$slots['header-actions']" class="sg-table-card-actions">
        <slot name="header-actions" />
      </div>
    </div>

    <div v-if="loading" class="sg-table-card-state">
      <div class="spinner"></div>
      <span>Đang tải dữ liệu...</span>
    </div>

    <div v-else-if="empty" class="sg-table-card-state empty">
      <span>{{ emptyText || 'Không có dữ liệu.' }}</span>
    </div>

    <div v-else class="sg-table-card-content">
      <slot />
    </div>
  </section>
</template>

<script>
export default {
  name: 'AppTableCard',
  props: {
    title: {
      type: String,
      default: '',
    },
    loading: {
      type: Boolean,
      default: false,
    },
    empty: {
      type: Boolean,
      default: false,
    },
    emptyText: {
      type: String,
      default: 'Không có dữ liệu.',
    },
  },
};
</script>

<style scoped>
.sg-table-card {
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: var(--admin-radius-lg, 12px);
  overflow: hidden;
  margin-bottom: var(--sg-space-lg, 24px);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.sg-table-card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--sg-space-md, 16px);
  padding: var(--sg-space-sm, 12px) var(--sg-space-md, 16px);
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
  flex-wrap: wrap;
}

.sg-table-card-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--admin-text, #1e293b);
}

.sg-table-card-actions {
  display: flex;
  align-items: center;
  gap: var(--sg-space-xs, 8px);
}

.sg-table-card-state {
  padding: var(--sg-space-lg, 24px);
  text-align: center;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--sg-space-xs, 8px);
}

.sg-table-card-content {
  overflow-x: auto;
}
</style>

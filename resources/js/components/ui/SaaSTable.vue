<template>
  <div class="saas-table-container">
    <div v-if="loading" class="state-box animate-fade-in">
      <div class="spinner"></div>
      <p>{{ loadingText }}</p>
    </div>

    <div v-else-if="data.length === 0" class="state-box animate-fade-in">
      <p class="empty-msg">
        <slot name="empty">{{ emptyText }}</slot>
      </p>
    </div>

    <div v-else class="saas-table-scroll">
      <table class="saas-table">
        <thead>
          <tr>
            <th 
              v-for="col in columns" 
              :key="col.key" 
              :style="col.style || {}"
              :class="[col.class || '', col.align ? 'align-' + col.align : '']"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="(row, rIndex) in data" 
            :key="row.id || rIndex"
            @click="$emit('row-click', row)"
            :class="{ 'clickable-row': clickable }"
          >
            <td 
              v-for="col in columns" 
              :key="col.key"
              :class="[col.class || '', col.align ? 'align-' + col.align : '']"
              :style="col.style || {}"
            >
              <slot :name="col.key" :row="row" :value="row[col.key]">
                {{ row[col.key] }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SaaSTable',
  props: {
    columns: {
      type: Array,
      required: true
    },
    data: {
      type: Array,
      required: true
    },
    clickable: {
      type: Boolean,
      default: false
    },
    loading: {
      type: Boolean,
      default: false
    },
    loadingText: {
      type: String,
      default: 'Đang tải dữ liệu...'
    },
    emptyText: {
      type: String,
      default: 'Không có dữ liệu'
    }
  }
};
</script>

<style scoped>
.saas-table-container {
  width: 100%;
  background: transparent;
  border-radius: 0;
  border: none;
  box-shadow: none;
  overflow: hidden;
  box-sizing: border-box;
}

.saas-table-scroll {
  width: 100%;
  overflow-x: auto;
  scrollbar-width: thin;
  scrollbar-color: var(--admin-border, #cbd5e1) transparent;
}

/* Custom Scrollbar for Webkit browsers to match the elegant mockup style */
.saas-table-scroll::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}

.saas-table-scroll::-webkit-scrollbar-track {
  background: transparent;
}

.saas-table-scroll::-webkit-scrollbar-thumb {
  background: var(--admin-border, #cbd5e1);
  border-radius: 99px;
}

.saas-table-scroll::-webkit-scrollbar-thumb:hover {
  background: var(--admin-faint, #94a3b8);
}

.saas-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
  font-family: var(--admin-font, inherit);
}

.saas-table th {
  padding: 14px 20px;
  color: var(--admin-muted, #64748b);
  font-weight: 500;
  font-size: 13px;
  border-bottom: 1px solid var(--admin-border-soft, #f1f5f9);
  background: var(--admin-surface, #ffffff);
  white-space: nowrap;
}

.saas-table td {
  padding: 16px 20px;
  color: var(--admin-text, #1e293b);
  border-bottom: 1px solid var(--admin-border-soft, #f1f5f9);
  transition: background-color 150ms ease;
  white-space: nowrap;
}

.saas-table tr:last-child td {
  border-bottom: none;
}

/* Row states */
.clickable-row {
  cursor: pointer;
}

.clickable-row.never-hover-class-placeholder td {
  background-color: var(--admin-hover, #f8fafc);
}

/* Alignment utilities */
.align-left {
  text-align: left;
}
.align-center {
  text-align: center;
}
.align-right {
  text-align: right;
}

.empty-cell {
  text-align: center;
  padding: 32px;
  color: var(--admin-faint, #94a3b8);
}
</style>

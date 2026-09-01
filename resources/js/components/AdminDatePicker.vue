<template>
  <div class="adp-wrap" ref="wrapRef">
    <button type="button" class="adp-trigger" :class="{ 'adp-trigger--open': open }" @click="toggle" :title="displayValue">
      <svg class="adp-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      <span class="adp-label">{{ displayValue }}</span>
    </button>
    <transition name="adp-pop">
      <div v-if="open" class="adp-dropdown" @click.stop>
        <MiniCalendar :model-value="modelValue" mode="single" @update:model-value="onSelect" />
        <div class="adp-footer">
          <button type="button" class="adp-clear" @click="onClear">Xóa</button>
          <button type="button" class="adp-today" @click="onToday">Hôm nay</button>
        </div>
      </div>
    </transition>
  </div>
</template>
<script>
import MiniCalendar from './MiniCalendar.vue';
import { businessDateString } from '../utils/businessTime.js';
function isoToday() { return businessDateString(); }
function formatDisplay(iso) {
  if (!iso) return 'Chọn ngày';
  const d = new Date(iso + 'T00:00:00');
  if (isNaN(d)) return 'Chọn ngày';
  return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
}
export default {
  name: 'AdminDatePicker', components: { MiniCalendar },
  props: { modelValue: { type: String, default: '' }, placeholder: { type: String, default: 'Chọn ngày' } },
  emits: ['update:modelValue'],
  data() { return { open: false }; },
  computed: { displayValue() { return this.modelValue ? formatDisplay(this.modelValue) : this.placeholder; } },
  mounted() {
    document.addEventListener('click', this.onOutsideClick);
    window.addEventListener('adp-opened', this.onOtherOpened);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.onOutsideClick);
    window.removeEventListener('adp-opened', this.onOtherOpened);
  },
  methods: {
    toggle() {
      this.open = !this.open;
      if (this.open) {
        window.dispatchEvent(new CustomEvent('adp-opened', { detail: this }));
      }
    },
    onOtherOpened(e) {
      if (e.detail !== this) {
        this.open = false;
      }
    },
    onSelect(iso) { this.$emit('update:modelValue', iso); this.open = false; },
    onClear() { this.$emit('update:modelValue', ''); this.open = false; },
    onToday() { this.$emit('update:modelValue', isoToday()); this.open = false; },
    onOutsideClick(e) { if (this.$refs.wrapRef && !this.$refs.wrapRef.contains(e.target)) this.open = false; },
  },
};
</script>
<style scoped>
.adp-wrap { position: relative; display: inline-flex; align-items: center; }

/* Trigger */
.adp-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: var(--admin-radius, 8px);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #0f172a);
  font-size: var(--admin-font-size-sm, 12px);
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  white-space: nowrap;
}
.adp-trigger:hover,
.adp-trigger--open {
  border-color: var(--admin-primary, #15803d);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--admin-primary, #15803d) 18%, transparent);
}
.adp-icon { flex-shrink: 0; opacity: 0.65; }

/* Dropdown shell */
.adp-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  z-index: 9999;
  min-width: 270px;
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.12), 0 4px 10px rgba(0,0,0,0.06);
  overflow: hidden;
  color: var(--admin-text, #0f172a);
}

/* ── MiniCalendar overrides inside DatePicker ── */
.adp-dropdown :deep(.mini-cal-card),
.adp-dropdown :deep(.mini-cal) {
  background: transparent !important;
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 12px 14px 8px !important;
  width: 100% !important;
  max-width: 100% !important;
  color: inherit !important;
  box-sizing: border-box !important;
}

.adp-dropdown :deep(.mini-cal-months-body) {
  width: 100% !important;
  gap: 0 !important;
}

.adp-dropdown :deep(.mini-cal-month-panel) {
  width: 100% !important;
}

/* Header nav bar */
.adp-dropdown :deep(.mini-cal-nav-bar) {
  margin-bottom: 8px !important;
}

.adp-dropdown :deep(.mini-cal-arrow-btn),
.adp-dropdown :deep(.mini-cal__nav) {
  background: var(--admin-bg-soft, #f8fafc) !important;
  border: 1px solid var(--admin-border-soft, #e2e8f0) !important;
  color: var(--admin-text, #0f172a) !important;
  border-radius: 6px !important;
  width: 28px !important;
  height: 28px !important;
  padding: 0 !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  cursor: pointer !important;
}

.adp-dropdown :deep(.mini-cal-arrow-btn:hover) {
  background: var(--admin-hover, #f1f5f9) !important;
}

/* Month / year labels */
.adp-dropdown :deep(.mini-cal-top-title),
.adp-dropdown :deep(.mini-cal-month-heading),
.adp-dropdown :deep(.mini-cal__title),
.adp-dropdown :deep(.mini-cal__month) {
  color: var(--admin-text, #0f172a) !important;
  font-size: 13.5px !important;
  font-weight: 600 !important;
}

/* Weekday header */
.adp-dropdown :deep(.mini-cal-weekdays span),
.adp-dropdown :deep(.mini-cal__weekdays span) {
  color: var(--admin-muted, #64748b) !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  height: 24px !important;
}

/* Day cells — base */
.adp-dropdown :deep(.mini-cal-day-btn),
.adp-dropdown :deep(.mini-cal__day) {
  background: transparent !important;
  color: var(--admin-text, #0f172a) !important;
  font-size: 13px !important;
  border-radius: 9999px !important;
  box-shadow: none !important;
  width: 32px !important;
  height: 32px !important;
  margin: 0 auto !important;
}

/* Hover state */
.adp-dropdown :deep(.mini-cal-day-btn:hover:not(:disabled):not(.selected):not(.range-start):not(.range-end)),
.adp-dropdown :deep(.mini-cal__day:hover:not(:disabled):not(.selected):not(.range-start):not(.range-end)) {
  background: var(--admin-hover, #f1f5f9) !important;
  color: var(--admin-text, #0f172a) !important;
}

/* Outside / disabled */
.adp-dropdown :deep(.mini-cal-day-btn:disabled),
.adp-dropdown :deep(.mini-cal__day:disabled),
.adp-dropdown :deep(.mini-cal__day.outside) {
  color: var(--admin-faint, #cbd5e1) !important;
  opacity: 0.4 !important;
  cursor: not-allowed !important;
}

/* Today indicator */
.adp-dropdown :deep(.mini-cal-day-btn.today:not(.selected)) {
  color: var(--admin-primary, #15803d) !important;
  font-weight: 700 !important;
}

/* Selected day */
.adp-dropdown :deep(.mini-cal-day-btn.selected),
.adp-dropdown :deep(.mini-cal-day-btn.range-start),
.adp-dropdown :deep(.mini-cal-day-btn.range-end),
.adp-dropdown :deep(.mini-cal__day.selected) {
  background: var(--admin-primary, #15803d) !important;
  color: #ffffff !important;
  font-weight: 600 !important;
  border-radius: 9999px !important;
  box-shadow: 0 2px 6px rgba(21,128,61,0.25) !important;
}

.adp-dropdown :deep(.mini-cal-day-btn.selected .mini-cal-day-num),
.adp-dropdown :deep(.mini-cal-day-btn.range-start .mini-cal-day-num),
.adp-dropdown :deep(.mini-cal-day-btn.range-end .mini-cal-day-num) {
  color: #ffffff !important;
}

/* Footer */
.adp-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-top: 1px solid var(--admin-border-soft, #e2e8f0);
  background: var(--admin-surface, #ffffff);
}
.adp-clear,
.adp-today {
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: var(--admin-font-size-sm, 12px);
  font-weight: 500;
  padding: 4px 8px;
  border-radius: 6px;
  transition: background 0.15s ease, color 0.15s ease;
}
.adp-clear { color: var(--admin-danger, #ef4444); }
.adp-clear:hover { background: var(--admin-danger-soft, rgba(239,68,68,0.1)); }
.adp-today { color: var(--admin-primary, #15803d); }
.adp-today:hover { background: var(--admin-primary-soft, rgba(21,128,61,0.1)); }

/* Transition */
.adp-pop-enter-active,
.adp-pop-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.adp-pop-enter-from,
.adp-pop-leave-to { opacity: 0; transform: translateY(-4px) scale(0.97); }
</style>

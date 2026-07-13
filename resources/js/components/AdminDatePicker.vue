<template>
  <div class="adp-wrap" ref="wrapRef">
    <button type="button" class="adp-trigger" :class="{ 'adp-trigger--open': open }" @click.stop="toggle" :title="displayValue">
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
function isoToday() { const d = new Date(); const offset = d.getTimezoneOffset(); return new Date(d.getTime() - offset * 60000).toISOString().slice(0, 10); }
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
  mounted() { document.addEventListener('click', this.onOutsideClick); },
  beforeUnmount() { document.removeEventListener('click', this.onOutsideClick); },
  methods: {
    toggle() { this.open = !this.open; },
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
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font-size: var(--admin-font-size-sm, 12px);
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  white-space: nowrap;
}
.adp-trigger:hover,
.adp-trigger--open {
  border-color: var(--admin-primary);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--admin-primary) 18%, transparent);
}
.adp-icon { flex-shrink: 0; opacity: 0.65; }

/* Dropdown shell */
.adp-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  z-index: 9999;
  min-width: 260px;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.1);
  overflow: hidden;
  /* Inject CSS vars so MiniCalendar (child) can inherit them */
  color: var(--admin-text);
}

/* ── MiniCalendar overrides ── */
.adp-dropdown :deep(.mini-cal) {
  background: var(--admin-surface) !important;
  border: none !important;
  border-radius: 0 !important;
  padding: 12px !important;
  max-width: 100% !important;
  color: var(--admin-text) !important;
}

/* Header nav buttons */
.adp-dropdown :deep(.mini-cal__nav) {
  background: var(--admin-bg-soft, rgba(255,255,255,0.06)) !important;
  border: 1px solid var(--admin-border-soft) !important;
  color: var(--admin-text) !important;
  border-radius: 6px !important;
}

/* Month / year labels */
.adp-dropdown :deep(.mini-cal__month) {
  color: var(--admin-text) !important;
  font-size: var(--admin-font-size-md, 13px) !important;
}
.adp-dropdown :deep(.mini-cal__year) {
  color: var(--admin-muted) !important;
  font-size: var(--admin-font-size-sm, 12px) !important;
}
.adp-dropdown :deep(.mini-cal__title) {
  background: transparent !important;
  color: var(--admin-text) !important;
}

/* Weekday header */
.adp-dropdown :deep(.mini-cal__weekdays span) {
  color: var(--admin-faint) !important;
  font-size: var(--admin-font-size-xs, 11px) !important;
}

/* Day cells — base */
.adp-dropdown :deep(.mini-cal__day) {
  background: transparent !important;
  color: var(--admin-text) !important;
  font-size: var(--admin-font-size-sm, 12px) !important;
  border-radius: var(--admin-radius) !important;
  box-shadow: none !important;
}

/* Hover state — override hardcoded #f1f5f9 */
.adp-dropdown :deep(.mini-cal__day:hover:not(:disabled):not(.selected):not(.range-start):not(.range-end)) {
  background: var(--admin-hover, rgba(255,255,255,0.08)) !important;
  color: var(--admin-text) !important;
}

/* Outside / disabled */
.adp-dropdown :deep(.mini-cal__day.outside) {
  color: var(--admin-faint) !important;
  opacity: 0.4 !important;
}
.adp-dropdown :deep(.mini-cal__day:disabled) {
  color: var(--admin-faint) !important;
  opacity: 0.35 !important;
  cursor: not-allowed !important;
}

/* Today underline */
.adp-dropdown :deep(.mini-cal__day.today .mini-cal__day-num::after) {
  background: var(--admin-success, #22a653) !important;
}

/* Selected day — use success green so it stays visible in both light/dark */
.adp-dropdown :deep(.mini-cal__day.selected),
.adp-dropdown :deep(.mini-cal__day.range-start),
.adp-dropdown :deep(.mini-cal__day.range-end) {
  background: var(--admin-success, #22a653) !important;
  color: #fff !important;
  box-shadow: 0 2px 8px rgba(34,166,83,0.3) !important;
  border-radius: var(--admin-radius) !important;
}

/* Footer */
.adp-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-top: 1px solid var(--admin-border-soft);
}
.adp-clear,
.adp-today {
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: var(--admin-font-size-sm, 12px);
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 6px;
  transition: background 0.15s ease;
}
.adp-clear { color: var(--admin-danger, #ef4444); }
.adp-clear:hover { background: var(--admin-danger-soft, rgba(239,68,68,0.1)); }
.adp-today { color: var(--admin-primary); }
.adp-today:hover { background: var(--admin-primary-soft); }

/* Transition */
.adp-pop-enter-active,
.adp-pop-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.adp-pop-enter-from,
.adp-pop-leave-to { opacity: 0; transform: translateY(-4px) scale(0.97); }
</style>
<style>
/* Global overrides — .adp-dropdown as namespace to avoid polluting global styles */

/* Fix browser default button background on MiniCalendar day cells */
.adp-dropdown button.mini-cal__day {
  -webkit-appearance: none;
  appearance: none;
  background: transparent !important;
  background-color: transparent !important;
  border: none !important;
  color: var(--admin-text, #f4f4f5) !important;
}

.adp-dropdown .mini-cal {
  background: var(--admin-surface, #18181b) !important;
  border: none !important;
  color: var(--admin-text, #f4f4f5) !important;
}
.adp-dropdown .mini-cal__nav {
  background: var(--admin-bg-soft, rgba(255,255,255,0.06)) !important;
  border-color: var(--admin-border-soft, rgba(255,255,255,0.08)) !important;
  color: var(--admin-text, #f4f4f5) !important;
}
.adp-dropdown .mini-cal__month {
  color: var(--admin-text, #f4f4f5) !important;
}
.adp-dropdown .mini-cal__year {
  color: var(--admin-muted, #94a3b8) !important;
}
.adp-dropdown .mini-cal__weekdays span {
  color: var(--admin-faint, #64748b) !important;
}
.adp-dropdown .mini-cal__day.outside {
  color: var(--admin-faint, #64748b) !important;
  opacity: 0.45 !important;
}
.adp-dropdown button.mini-cal__day:disabled {
  background: transparent !important;
  background-color: transparent !important;
  color: var(--admin-faint, #64748b) !important;
  opacity: 0.35 !important;
}
.adp-dropdown button.mini-cal__day:hover:not(:disabled):not(.selected):not(.range-start):not(.range-end) {
  background: var(--admin-hover, rgba(255,255,255,0.08)) !important;
  background-color: var(--admin-hover, rgba(255,255,255,0.08)) !important;
}
/* Today indicator underline */
.adp-dropdown .mini-cal__day.today .mini-cal__day-num::after {
  background: var(--admin-text, #f4f4f5) !important;
  opacity: 0.6;
}
/* Selected: no background, use outline ring to indicate */
.adp-dropdown button.mini-cal__day.selected,
.adp-dropdown button.mini-cal__day.range-start,
.adp-dropdown button.mini-cal__day.range-end {
  background: transparent !important;
  background-color: transparent !important;
  color: var(--admin-text, #f4f4f5) !important;
  font-weight: 400 !important;
  box-shadow: inset 0 0 0 1.5px var(--admin-text, #f4f4f5) !important;
  border-radius: 8px !important;
}
</style>

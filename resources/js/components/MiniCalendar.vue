<template>
  <div class="mini-cal" :class="{ 'mini-cal--range': mode === 'range' }">
    <!-- Header: tháng + mũi tên điều hướng -->
    <div class="mini-cal__header">
      <button type="button" class="mini-cal__nav" @click="prevMonth" aria-label="Tháng trước">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <span class="mini-cal__title">{{ monthTitle }}</span>
      <button type="button" class="mini-cal__nav" @click="nextMonth" aria-label="Tháng sau">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>

    <!-- Tên thứ -->
    <div class="mini-cal__weekdays">
      <span v-for="day in weekDayLabels" :key="day">{{ day }}</span>
    </div>

    <!-- Các ô ngày -->
    <div class="mini-cal__grid">
      <button
        v-for="cell in calendarCells"
        :key="cell.key"
        type="button"
        class="mini-cal__cell"
        :class="cellClass(cell)"
        :disabled="isCellDisabled(cell)"
        :tabindex="cell.isCurrentMonth ? 0 : -1"
        @click="handleCellClick(cell)"
        @mouseenter="handleCellHover(cell)"
      >
        <span class="mini-cal__day">{{ cell.day }}</span>
        <!-- Dot indicators -->
        <span v-if="cell.isCurrentMonth && getDots(cell).length" class="mini-cal__dots">
          <i
            v-for="dot in getDots(cell)"
            :key="dot.type"
            class="mini-cal__dot"
            :class="`mini-cal__dot--${dot.type}`"
          ></i>
        </span>
      </button>
    </div>

    <!-- Range hint (chỉ hiện ở mode range) -->
    <div v-if="mode === 'range' && (modelValue.start || modelValue.end)" class="mini-cal__range-info">
      <span>{{ rangeInfoText }}</span>
      <button v-if="modelValue.start || modelValue.end" type="button" class="mini-cal__clear" @click="clearRange">
        Xóa
      </button>
    </div>
  </div>
</template>

<script>
/**
 * MiniCalendar – Component lịch mini dùng chung
 *
 * Props:
 *  mode: 'single' | 'range'  (default: 'single')
 *  modelValue:
 *    - mode=single: String 'YYYY-MM-DD'
 *    - mode=range:  Object { start: 'YYYY-MM-DD', end: 'YYYY-MM-DD' }
 *  minDate: String 'YYYY-MM-DD' – không cho chọn trước ngày này
 *  maxDate: String 'YYYY-MM-DD'
 *  dots: Object { 'YYYY-MM-DD': ['booking', 'lock', ...] }
 *        Hiện dot màu dưới ô ngày
 *
 * Emits:
 *  update:modelValue – emit value mới khi chọn ngày
 */
export default {
  name: 'MiniCalendar',
  props: {
    mode: { type: String, default: 'single' }, // 'single' | 'range'
    modelValue: { type: [String, Object], default: null },
    minDate: { type: String, default: null },
    maxDate: { type: String, default: null },
    // dots: { 'YYYY-MM-DD': ['booking'] | ['lock'] | ['booking','lock'] }
    dots: { type: Object, default: () => ({}) },
  },
  emits: ['update:modelValue'],
  data() {
    const today = this.todayStr();
    // Khởi tạo viewYear/viewMonth theo giá trị hiện tại hoặc today
    const init = this.mode === 'range'
      ? (this.modelValue?.start || today)
      : (this.modelValue || today);
    const d = init ? new Date(init + 'T00:00:00') : new Date();
    return {
      viewYear: d.getFullYear(),
      viewMonth: d.getMonth(), // 0-11
      hoverDate: null, // dùng trong range mode để preview
    };
  },
  computed: {
    weekDayLabels() {
      return ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    },
    monthTitle() {
      return `Tháng ${this.viewMonth + 1} / ${this.viewYear}`;
    },
    calendarCells() {
      const year = this.viewYear;
      const month = this.viewMonth;

      // Ngày đầu tháng, xem nó là thứ mấy (0=CN,1=T2,...6=T7)
      const firstDay = new Date(year, month, 1).getDay();
      // Đưa CN = 6 (index cuối), T2=0, T3=1,...
      const startOffset = (firstDay === 0) ? 6 : firstDay - 1;

      const daysInMonth = new Date(year, month + 1, 0).getDate();
      const daysInPrevMonth = new Date(year, month, 0).getDate();

      const cells = [];

      // Ngày tháng trước (filler)
      for (let i = startOffset - 1; i >= 0; i--) {
        const day = daysInPrevMonth - i;
        const prevMonth = month === 0 ? 11 : month - 1;
        const prevYear = month === 0 ? year - 1 : year;
        cells.push({
          key: `prev-${day}`,
          day,
          dateStr: this.toDateStr(prevYear, prevMonth, day),
          isCurrentMonth: false,
        });
      }

      // Ngày tháng này
      for (let day = 1; day <= daysInMonth; day++) {
        cells.push({
          key: `cur-${day}`,
          day,
          dateStr: this.toDateStr(year, month, day),
          isCurrentMonth: true,
        });
      }

      // Ngày tháng sau (filler) để đủ hàng
      const remaining = 42 - cells.length;
      const nextMonth = month === 11 ? 0 : month + 1;
      const nextYear = month === 11 ? year + 1 : year;
      for (let day = 1; day <= remaining; day++) {
        cells.push({
          key: `next-${day}`,
          day,
          dateStr: this.toDateStr(nextYear, nextMonth, day),
          isCurrentMonth: false,
        });
      }

      return cells;
    },
    // Dải ngày đang được chọn (range mode)
    rangeStart() {
      return this.mode === 'range' ? (this.modelValue?.start || null) : null;
    },
    rangeEnd() {
      return this.mode === 'range' ? (this.modelValue?.end || null) : null;
    },
    // Ngày đang "preview" khi hover (chỉ khi đã có start, chưa có end)
    effectiveRangeEnd() {
      if (this.mode !== 'range') return null;
      if (this.rangeStart && !this.rangeEnd && this.hoverDate) {
        return this.hoverDate >= this.rangeStart ? this.hoverDate : this.rangeStart;
      }
      return this.rangeEnd;
    },
    effectiveRangeStart() {
      if (this.mode !== 'range') return null;
      if (this.rangeStart && !this.rangeEnd && this.hoverDate) {
        return this.hoverDate < this.rangeStart ? this.hoverDate : this.rangeStart;
      }
      return this.rangeStart;
    },
    rangeInfoText() {
      const s = this.modelValue?.start;
      const e = this.modelValue?.end;
      if (s && e) return `${this.formatDate(s)} – ${this.formatDate(e)}`;
      if (s) return `Từ ${this.formatDate(s)} — chọn ngày kết thúc`;
      return '';
    },
  },
  methods: {
    todayStr() {
      const d = new Date();
      return this.toDateStr(d.getFullYear(), d.getMonth(), d.getDate());
    },
    toDateStr(year, month, day) {
      return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    },
    formatDate(str) {
      if (!str) return '';
      const [y, m, d] = str.split('-');
      return `${d}/${m}/${y}`;
    },
    prevMonth() {
      if (this.viewMonth === 0) {
        this.viewMonth = 11;
        this.viewYear--;
      } else {
        this.viewMonth--;
      }
    },
    nextMonth() {
      if (this.viewMonth === 11) {
        this.viewMonth = 0;
        this.viewYear++;
      } else {
        this.viewMonth++;
      }
    },
    isCellDisabled(cell) {
      if (!cell.isCurrentMonth) return true;
      if (this.minDate && cell.dateStr < this.minDate) return true;
      if (this.maxDate && cell.dateStr > this.maxDate) return true;
      return false;
    },
    cellClass(cell) {
      const cls = [];
      if (!cell.isCurrentMonth) cls.push('mini-cal__cell--filler');
      if (cell.dateStr === this.todayStr()) cls.push('mini-cal__cell--today');

      if (this.mode === 'single') {
        if (cell.dateStr === this.modelValue) cls.push('mini-cal__cell--selected');
      } else {
        // Range mode
        const rs = this.effectiveRangeStart;
        const re = this.effectiveRangeEnd;
        if (rs && cell.dateStr === rs) cls.push('mini-cal__cell--range-start');
        if (re && cell.dateStr === re) cls.push('mini-cal__cell--range-end');
        if (rs && re && cell.dateStr > rs && cell.dateStr < re) {
          cls.push('mini-cal__cell--in-range');
        }
        // Khi mới chọn start, hover preview
        if (this.rangeStart && !this.rangeEnd && this.hoverDate) {
          if (cell.dateStr === this.rangeStart && this.hoverDate > this.rangeStart) {
            cls.push('mini-cal__cell--range-start');
          }
        }
      }

      return cls;
    },
    handleCellClick(cell) {
      if (this.isCellDisabled(cell)) return;

      if (this.mode === 'single') {
        this.$emit('update:modelValue', cell.dateStr);
        return;
      }

      // Range mode
      const { start, end } = this.modelValue || {};
      if (!start || (start && end)) {
        // Bắt đầu chọn mới
        this.$emit('update:modelValue', { start: cell.dateStr, end: null });
      } else {
        // Đã có start, chọn end
        if (cell.dateStr >= start) {
          this.$emit('update:modelValue', { start, end: cell.dateStr });
        } else {
          // Nếu click ngày trước start → đổi start
          this.$emit('update:modelValue', { start: cell.dateStr, end: null });
        }
        this.hoverDate = null;
      }
    },
    handleCellHover(cell) {
      if (this.mode !== 'range') return;
      if (this.rangeStart && !this.rangeEnd) {
        this.hoverDate = cell.dateStr;
      }
    },
    clearRange() {
      this.$emit('update:modelValue', { start: null, end: null });
      this.hoverDate = null;
    },
    getDots(cell) {
      const types = this.dots[cell.dateStr] || [];
      // Trả về unique types để vẽ dot
      return [...new Set(types)].slice(0, 3).map((type) => ({ type }));
    },
  },
  watch: {
    modelValue(val) {
      // Khi modelValue thay đổi từ bên ngoài, cuộn lịch về tháng có giá trị đó
      const dateStr = this.mode === 'range' ? val?.start : val;
      if (!dateStr) return;
      const d = new Date(dateStr + 'T00:00:00');
      if (!isNaN(d)) {
        this.viewYear = d.getFullYear();
        this.viewMonth = d.getMonth();
      }
    },
  },
};
</script>

<style scoped>
.mini-cal {
  display: grid;
  gap: 0;
  width: 100%;
  font-size: 13px;
  user-select: none;
}

/* ── Header ── */
.mini-cal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 8px 8px;
  gap: 8px;
}

.mini-cal__title {
  flex: 1;
  text-align: center;
  font-size: 13px;
  font-weight: 900;
  color: #1a2e1c;
  letter-spacing: .01em;
}

.mini-cal__nav {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border: 1px solid #d7ead9;
  border-radius: 7px;
  background: #fff;
  color: #3d6645;
  cursor: pointer;
  transition: background .13s, border-color .13s;
  flex-shrink: 0;
}

.mini-cal__nav:hover {
  background: #eef8f0;
  border-color: #16a34a;
  color: #16a34a;
}

/* ── Weekday header ── */
.mini-cal__weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  padding: 0 4px 4px;
}

.mini-cal__weekdays span {
  text-align: center;
  font-size: 11px;
  font-weight: 900;
  color: #7a9580;
  padding: 4px 0;
}

/* ── Grid ── */
.mini-cal__grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
  padding: 0 4px 6px;
}

/* ── Cell ── */
.mini-cal__cell {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  aspect-ratio: 1;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  color: #1e3322;
  font: inherit;
  font-size: 13px;
  font-weight: 800;
  transition: background .12s, color .12s, border-color .12s;
  padding: 0;
  min-height: 32px;
}

.mini-cal__cell:hover:not(:disabled):not(.mini-cal__cell--filler) {
  background: #eef8f0;
  border-color: #b4dab9;
}

.mini-cal__cell:disabled {
  cursor: not-allowed;
  opacity: .35;
}

.mini-cal__cell--filler {
  color: #b0c4b5;
  font-weight: 700;
  cursor: default;
  pointer-events: none;
}

.mini-cal__cell--today .mini-cal__day {
  color: #16a34a;
  font-weight: 950;
}

.mini-cal__cell--today::after {
  content: '';
  position: absolute;
  bottom: 3px;
  left: 50%;
  transform: translateX(-50%);
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #16a34a;
}

/* Single mode – selected */
.mini-cal__cell--selected {
  background: #16a34a !important;
  border-color: #15803d !important;
  color: #fff !important;
}

.mini-cal__cell--selected .mini-cal__day {
  color: #fff;
}

/* Range mode */
.mini-cal__cell--range-start,
.mini-cal__cell--range-end {
  background: #16a34a !important;
  border-color: #15803d !important;
  color: #fff !important;
  z-index: 1;
}

.mini-cal__cell--range-start .mini-cal__day,
.mini-cal__cell--range-end .mini-cal__day {
  color: #fff;
}

.mini-cal__cell--in-range {
  background: #dcfce7;
  border-color: #86efac;
  border-radius: 0;
  color: #14532d;
}

/* Bo góc cho ô đầu / cuối range trong 1 hàng */
.mini-cal__cell--range-start {
  border-radius: 8px 8px 8px 8px;
}
.mini-cal__cell--range-end {
  border-radius: 8px 8px 8px 8px;
}

/* ── Dots ── */
.mini-cal__dots {
  display: flex;
  gap: 2px;
  justify-content: center;
  height: 5px;
  margin-top: 1px;
}

.mini-cal__dot {
  display: block;
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #94a3b8;
  flex-shrink: 0;
}

.mini-cal__dot--booking {
  background: #16a34a;
}

.mini-cal__dot--lock {
  background: #dc2626;
}

.mini-cal__dot--conflict {
  background: #f59e0b;
}

/* ── Range info footer ── */
.mini-cal__range-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 10px;
  margin: 0 4px 4px;
  border-radius: 7px;
  background: #f0faf2;
  font-size: 12px;
  font-weight: 800;
  color: #1a6b2e;
}

.mini-cal__clear {
  border: 0;
  background: transparent;
  color: #64748b;
  font: inherit;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 5px;
  white-space: nowrap;
  transition: background .12s;
}

.mini-cal__clear:hover {
  background: #e2e8f0;
  color: #334155;
}
</style>

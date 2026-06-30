<template>
    <div class="mini-cal" :class="{ 'mini-cal--range': mode === 'range' }">
        <header class="mini-cal__header">
            <button
                type="button"
                class="mini-cal__nav"
                title="Tháng trước"
                @click="prevMonth"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <button
                type="button"
                class="mini-cal__title"
                @click="goToday"
                title="Về hôm nay"
            >
                <span class="mini-cal__month">{{ monthLabel }}</span>
                <span class="mini-cal__year">{{ viewYear }}</span>
            </button>
            <button
                type="button"
                class="mini-cal__nav"
                title="Tháng sau"
                @click="nextMonth"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </header>

        <div class="mini-cal__weekdays">
            <span v-for="day in weekDayLabels" :key="day">{{ day }}</span>
        </div>

        <div class="mini-cal__grid">
            <button
                v-for="cell in calendarCells"
                :key="cell.key"
                type="button"
                class="mini-cal__day"
                :class="dayClasses(cell)"
                :disabled="cell.disabled"
                :title="cell.iso"
                @click="selectDay(cell)"
                @mouseenter="onDayHover(cell)"
            >
                <span class="mini-cal__day-num">{{ cell.day }}</span>
                <span
                    v-if="cell.dots.length"
                    class="mini-cal__dots"
                >
                    <i
                        v-for="(dot, idx) in cell.dots.slice(0, 3)"
                        :key="idx"
                        :style="{ background: dot }"
                    />
                </span>
            </button>
        </div>
    </div>
</template>

<script>
const MONTH_NAMES = [
    'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4',
    'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8',
    'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12',
];
const WEEKDAY_LABELS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

function toIso(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function parseIso(str) {
    if (!str) return null;
    const d = new Date(`${str}T00:00:00`);
    return Number.isNaN(d.getTime()) ? null : d;
}

export default {
    name: 'MiniCalendar',
    props: {
        /** Single mode: selected date (YYYY-MM-DD) */
        modelValue: { type: String, default: '' },
        /** Range mode: start date */
        startDate: { type: String, default: '' },
        /** Range mode: end date */
        endDate: { type: String, default: '' },
        /** 'single' | 'range' */
        mode: { type: String, default: 'single' },
        /** Min selectable date (YYYY-MM-DD) */
        minDate: { type: String, default: '' },
        /** Max selectable date (YYYY-MM-DD) */
        maxDate: { type: String, default: '' },
        /**
         * Array of { date: 'YYYY-MM-DD', color: '#hex' }
         * Shows colored dots under the day number
         */
        markedDates: { type: Array, default: () => [] },
    },
    emits: ['update:modelValue', 'update:startDate', 'update:endDate', 'select', 'range-change'],
    data() {
        const ref = this.mode === 'range'
            ? parseIso(this.startDate) || new Date()
            : parseIso(this.modelValue) || new Date();
        return {
            viewMonth: ref.getMonth(),
            viewYear: ref.getFullYear(),
            hoverDate: '',
            rangeSelecting: false,
            weekDayLabels: WEEKDAY_LABELS,
        };
    },
    computed: {
        monthLabel() {
            return MONTH_NAMES[this.viewMonth];
        },
        todayIso() {
            return toIso(new Date());
        },
        markedMap() {
            const map = {};
            (this.markedDates || []).forEach(item => {
                if (!map[item.date]) map[item.date] = [];
                map[item.date].push(item.color || '#16a34a');
            });
            return map;
        },
        calendarCells() {
            const cells = [];
            const firstDay = new Date(this.viewYear, this.viewMonth, 1);
            // Monday = 0, Sunday = 6
            const startWeekday = (firstDay.getDay() + 6) % 7;
            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();

            // Previous month padding
            const prevMonthDays = new Date(this.viewYear, this.viewMonth, 0).getDate();
            for (let i = startWeekday - 1; i >= 0; i--) {
                const day = prevMonthDays - i;
                const d = new Date(this.viewYear, this.viewMonth - 1, day);
                const iso = toIso(d);
                cells.push({
                    key: `prev-${day}`,
                    day,
                    iso,
                    outside: true,
                    disabled: this.isDisabled(iso),
                    dots: this.markedMap[iso] || [],
                });
            }

            // Current month
            for (let day = 1; day <= daysInMonth; day++) {
                const d = new Date(this.viewYear, this.viewMonth, day);
                const iso = toIso(d);
                cells.push({
                    key: `cur-${day}`,
                    day,
                    iso,
                    outside: false,
                    disabled: this.isDisabled(iso),
                    dots: this.markedMap[iso] || [],
                });
            }

            // Next month padding to fill 6 rows
            const remaining = 42 - cells.length;
            for (let i = 1; i <= remaining; i++) {
                const d = new Date(this.viewYear, this.viewMonth + 1, i);
                const iso = toIso(d);
                cells.push({
                    key: `next-${i}`,
                    day: i,
                    iso,
                    outside: true,
                    disabled: this.isDisabled(iso),
                    dots: this.markedMap[iso] || [],
                });
            }

            return cells;
        },
        effectiveRangeStart() {
            return this.startDate || '';
        },
        effectiveRangeEnd() {
            if (this.rangeSelecting && this.hoverDate) {
                // While hovering, show preview range
                const start = this.effectiveRangeStart;
                if (start && this.hoverDate >= start) return this.hoverDate;
                if (start && this.hoverDate < start) return start;
            }
            return this.endDate || this.effectiveRangeStart;
        },
    },
    watch: {
        modelValue(newVal) {
            if (this.mode === 'single' && newVal) {
                const d = parseIso(newVal);
                if (d) {
                    this.viewMonth = d.getMonth();
                    this.viewYear = d.getFullYear();
                }
            }
        },
        startDate(newVal) {
            if (this.mode === 'range' && newVal) {
                const d = parseIso(newVal);
                if (d) {
                    this.viewMonth = d.getMonth();
                    this.viewYear = d.getFullYear();
                }
            }
        },
    },
    methods: {
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
        goToday() {
            const now = new Date();
            this.viewMonth = now.getMonth();
            this.viewYear = now.getFullYear();
        },
        isDisabled(iso) {
            if (this.minDate && iso < this.minDate) return true;
            if (this.maxDate && iso > this.maxDate) return true;
            return false;
        },
        dayClasses(cell) {
            const classes = [];
            if (cell.outside) classes.push('outside');
            if (cell.iso === this.todayIso) classes.push('today');

            if (this.mode === 'single') {
                if (cell.iso === this.modelValue) classes.push('selected');
            } else {
                // Range mode
                const start = this.effectiveRangeStart;
                let end = this.effectiveRangeEnd;

                // Handle hover preview
                if (this.rangeSelecting && this.hoverDate) {
                    end = this.hoverDate >= start ? this.hoverDate : start;
                    const actualStart = this.hoverDate < start ? this.hoverDate : start;
                    if (cell.iso === actualStart) classes.push('range-start');
                    if (cell.iso === end) classes.push('range-end');
                    if (cell.iso >= actualStart && cell.iso <= end && !cell.outside) classes.push('in-range');
                } else {
                    if (start && cell.iso === start) classes.push('range-start', 'selected');
                    if (end && cell.iso === end && end !== start) classes.push('range-end', 'selected');
                    if (start && end && cell.iso >= start && cell.iso <= end && !cell.outside) classes.push('in-range');
                }
            }

            return classes;
        },
        selectDay(cell) {
            if (cell.disabled) return;

            if (this.mode === 'single') {
                this.$emit('update:modelValue', cell.iso);
                this.$emit('select', cell.iso);
            } else {
                // Range mode
                if (!this.rangeSelecting) {
                    // First click: set start
                    this.$emit('update:startDate', cell.iso);
                    this.$emit('update:endDate', cell.iso);
                    this.rangeSelecting = true;
                } else {
                    // Second click: set end
                    const start = this.effectiveRangeStart;
                    let rangeStart = start;
                    let rangeEnd = cell.iso;

                    if (rangeEnd < rangeStart) {
                        [rangeStart, rangeEnd] = [rangeEnd, rangeStart];
                    }

                    this.$emit('update:startDate', rangeStart);
                    this.$emit('update:endDate', rangeEnd);
                    this.$emit('range-change', { start: rangeStart, end: rangeEnd });
                    this.rangeSelecting = false;
                    this.hoverDate = '';
                }
            }
        },
        onDayHover(cell) {
            if (this.mode === 'range' && this.rangeSelecting && !cell.disabled) {
                this.hoverDate = cell.iso;
            }
        },
    },
};
</script>

<style scoped>
.mini-cal {
    display: grid;
    gap: 6px;
    width: 100%;
    max-width: 320px;
    padding: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    user-select: none;
}

.mini-cal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.mini-cal__nav {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}

.mini-cal__nav:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
    color: #0f172a;
}

.mini-cal__title {
    display: flex;
    align-items: baseline;
    gap: 6px;
    border: 0;
    background: transparent;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background 0.15s ease;
}

.mini-cal__title:hover {
    background: #f1f5f9;
}

.mini-cal__month {
    color: #0f172a;
    font-size: 15px;
    font-weight: 900;
}

.mini-cal__year {
    color: #64748b;
    font-size: 13px;
    font-weight: 750;
}

.mini-cal__weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    margin-top: 4px;
}

.mini-cal__weekdays span {
    display: grid;
    place-items: center;
    height: 28px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.03em;
}

.mini-cal__grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.mini-cal__day {
    position: relative;
    display: grid;
    place-items: center;
    gap: 2px;
    min-height: 36px;
    padding: 2px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #1e293b;
    font: inherit;
    font-size: 13px;
    font-weight: 750;
    cursor: pointer;
    transition: all 0.12s ease;
}

.mini-cal__day:hover:not(:disabled):not(.selected):not(.range-start):not(.range-end) {
    background: #f1f5f9;
}

.mini-cal__day:disabled {
    color: #cbd5e1;
    cursor: not-allowed;
}

.mini-cal__day.outside {
    color: #cbd5e1;
}

.mini-cal__day.today .mini-cal__day-num {
    position: relative;
}

.mini-cal__day.today .mini-cal__day-num::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 14px;
    height: 2px;
    border-radius: 2px;
    background: var(--admin-primary, #16a34a);
}

.mini-cal__day.selected,
.mini-cal__day.range-start,
.mini-cal__day.range-end {
    background: var(--admin-primary, #16a34a);
    color: #fff;
    font-weight: 900;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25);
}

.mini-cal__day.selected .mini-cal__day-num::after,
.mini-cal__day.range-start .mini-cal__day-num::after,
.mini-cal__day.range-end .mini-cal__day-num::after {
    background: rgba(255, 255, 255, 0.7);
}

.mini-cal__day.in-range:not(.range-start):not(.range-end):not(.selected) {
    background: var(--admin-primary-soft, #dcfce7);
    color: var(--admin-primary-dark, #166534);
    border-radius: 4px;
}

.mini-cal__day.range-start {
    border-radius: 8px 4px 4px 8px;
}

.mini-cal__day.range-end {
    border-radius: 4px 8px 8px 4px;
}

.mini-cal__day.range-start.range-end {
    border-radius: 8px;
}

.mini-cal__day-num {
    line-height: 1;
}

.mini-cal__dots {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    height: 6px;
}

.mini-cal__dots i {
    width: 4px;
    height: 4px;
    border-radius: 999px;
    flex: 0 0 auto;
}

.mini-cal__day.selected .mini-cal__dots i,
.mini-cal__day.range-start .mini-cal__dots i,
.mini-cal__day.range-end .mini-cal__dots i {
    opacity: 0.7;
}
</style>

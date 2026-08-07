<template>
    <div
        class="mini-cal-card"
        :class="{
            'mini-cal-card--dual': isDual,
            'mini-cal-card--range': mode === 'range',
            'mini-cal-card--multiple': mode === 'multiple',
        }"
    >
        <!-- Top Navigation Bar -->
        <header class="mini-cal-nav-bar">
            <button
                type="button"
                class="mini-cal-arrow-btn"
                title="Tháng trước"
                @click="prevMonth"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>

            <div class="mini-cal-top-title">
                {{ primaryMonthYearLabel }}
            </div>

            <button
                type="button"
                class="mini-cal-arrow-btn"
                title="Tháng sau"
                @click="nextMonth"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </button>
        </header>

        <!-- Months Container (Side-by-side Dual Month View) -->
        <div class="mini-cal-months-body">
            <!-- Month 1 -->
            <div class="mini-cal-month-panel">
                <div v-if="isDual" class="mini-cal-month-heading">
                    {{ month1Title }}
                </div>
                <div class="mini-cal-weekdays">
                    <span v-for="day in weekDayLabels" :key="day">{{ day }}</span>
                </div>
                <div class="mini-cal-days-grid">
                    <template v-for="cell in month1Cells" :key="cell.key">
                        <div v-if="cell.blank" class="mini-cal-day-blank"></div>
                        <button
                            v-else
                            type="button"
                            class="mini-cal-day-btn"
                            :class="dayClasses(cell)"
                            :disabled="cell.disabled"
                            :title="cell.iso"
                            :aria-pressed="isDaySelected(cell)"
                            @click="selectDay(cell)"
                            @mouseenter="onDayHover(cell)"
                        >
                            <span class="mini-cal-day-num">{{ cell.day }}</span>
                            <span v-if="cell.dots.length" class="mini-cal-dots">
                                <i v-for="(dot, idx) in cell.dots.slice(0, 3)" :key="idx" :style="{ background: dot }" />
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Month 2 (Dual View) -->
            <div v-if="isDual" class="mini-cal-month-panel">
                <div class="mini-cal-month-heading">
                    {{ month2Title }}
                </div>
                <div class="mini-cal-weekdays">
                    <span v-for="day in weekDayLabels" :key="day">{{ day }}</span>
                </div>
                <div class="mini-cal-days-grid">
                    <template v-for="cell in month2Cells" :key="cell.key">
                        <div v-if="cell.blank" class="mini-cal-day-blank"></div>
                        <button
                            v-else
                            type="button"
                            class="mini-cal-day-btn"
                            :class="dayClasses(cell)"
                            :disabled="cell.disabled"
                            :title="cell.iso"
                            :aria-pressed="isDaySelected(cell)"
                            @click="selectDay(cell)"
                            @mouseenter="onDayHover(cell)"
                        >
                            <span class="mini-cal-day-num">{{ cell.day }}</span>
                            <span v-if="cell.dots.length" class="mini-cal-dots">
                                <i v-for="(dot, idx) in cell.dots.slice(0, 3)" :key="idx" :style="{ background: dot }" />
                            </span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const MONTH_NAMES_VI = [
    'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
    'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'
];
const WEEKDAY_LABELS_VI = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

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
        modelValue: { type: String, default: '' },
        startDate: { type: String, default: '' },
        endDate: { type: String, default: '' },
        selectedDates: { type: Array, default: () => [] },
        mode: { type: String, default: 'single' },
        minDate: { type: String, default: '' },
        maxDate: { type: String, default: '' },
        highlightStartDate: { type: String, default: '' },
        highlightEndDate: { type: String, default: '' },
        markedDates: { type: Array, default: () => [] },
        dualMonth: { type: Boolean, default: false },
    },
    emits: [
        'update:modelValue',
        'update:startDate',
        'update:endDate',
        'update:selectedDates',
        'select',
        'range-change',
    ],
    data() {
        const referenceDate = this.mode === 'range'
            ? this.startDate
            : this.mode === 'multiple'
                ? this.selectedDates[0]
                : this.modelValue;
        const ref = parseIso(referenceDate) || new Date();
        return {
            viewMonth: ref.getMonth(),
            viewYear: ref.getFullYear(),
            hoverDate: '',
            rangeSelecting: false,
            weekDayLabels: WEEKDAY_LABELS_VI,
        };
    },
    computed: {
        isDual() {
            return this.dualMonth;
        },
        primaryMonthYearLabel() {
            return `${MONTH_NAMES_VI[this.viewMonth]} năm ${this.viewYear}`;
        },
        month1Title() {
            return `${MONTH_NAMES_VI[this.viewMonth]} năm ${this.viewYear}`;
        },
        month2Title() {
            const nextM = (this.viewMonth + 1) % 12;
            const nextY = this.viewMonth === 11 ? this.viewYear + 1 : this.viewYear;
            return `${MONTH_NAMES_VI[nextM]} năm ${nextY}`;
        },
        todayIso() {
            return toIso(new Date());
        },
        markedMap() {
            const map = {};
            (this.markedDates || []).forEach(item => {
                if (!map[item.date]) map[item.date] = [];
                map[item.date].push(item.color || '#10b981');
            });
            return map;
        },
        month1Cells() {
            return this.buildMonthCells(this.viewYear, this.viewMonth);
        },
        month2Cells() {
            const nextM = (this.viewMonth + 1) % 12;
            const nextY = this.viewMonth === 11 ? this.viewYear + 1 : this.viewYear;
            return this.buildMonthCells(nextY, nextM);
        },
        effectiveRangeStart() {
            return this.startDate || '';
        },
        effectiveRangeEnd() {
            if (this.rangeSelecting && this.hoverDate) {
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
        buildMonthCells(year, month) {
            const cells = [];
            const firstDay = new Date(year, month, 1);
            const startWeekday = firstDay.getDay(); // 0 = Sunday
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            // Blank leading cells
            for (let i = 0; i < startWeekday; i++) {
                cells.push({
                    key: `blank-${year}-${month}-${i}`,
                    blank: true,
                });
            }

            // Days in current month
            for (let day = 1; day <= daysInMonth; day++) {
                const d = new Date(year, month, day);
                const iso = toIso(d);
                cells.push({
                    key: `day-${year}-${month}-${day}`,
                    day,
                    iso,
                    blank: false,
                    disabled: this.isDisabled(iso),
                    dots: this.markedMap[iso] || [],
                });
            }

            return cells;
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
        isDisabled(iso) {
            if (this.minDate && iso < this.minDate) return true;
            if (this.maxDate && iso > this.maxDate) return true;
            return false;
        },
        dayClasses(cell) {
            if (cell.blank) return [];
            const classes = [];
            if (cell.iso === this.todayIso) classes.push('today');

            if (
                this.highlightStartDate &&
                this.highlightEndDate &&
                cell.iso >= this.highlightStartDate &&
                cell.iso <= this.highlightEndDate
            ) {
                classes.push('template-window');
            }

            if (this.mode === 'single') {
                if (cell.iso === this.modelValue) classes.push('selected');
            } else if (this.mode === 'multiple') {
                if (this.selectedDates.includes(cell.iso)) {
                    classes.push('selected');
                }
            } else {
                // Range mode
                const start = this.effectiveRangeStart;
                let end = this.effectiveRangeEnd;

                if (this.rangeSelecting && this.hoverDate) {
                    end = this.hoverDate >= start ? this.hoverDate : start;
                    const actualStart = this.hoverDate < start ? this.hoverDate : start;
                    if (cell.iso === actualStart) classes.push('range-start', 'selected');
                    if (cell.iso === end) classes.push('range-end', 'selected');
                    if (cell.iso >= actualStart && cell.iso <= end) classes.push('in-range');
                } else {
                    if (start && cell.iso === start) classes.push('range-start', 'selected');
                    if (end && cell.iso === end && end !== start) classes.push('range-end', 'selected');
                    if (start && end && cell.iso >= start && cell.iso <= end) classes.push('in-range');
                }
            }

            return classes;
        },
        isDaySelected(cell) {
            if (cell.blank) return false;
            if (this.mode === 'single') return cell.iso === this.modelValue;
            if (this.mode === 'multiple') return this.selectedDates.includes(cell.iso);
            return (
                Boolean(this.effectiveRangeStart) &&
                cell.iso >= this.effectiveRangeStart &&
                cell.iso <= this.effectiveRangeEnd
            );
        },
        selectDay(cell) {
            if (cell.blank || cell.disabled) return;

            if (this.mode === 'single') {
                this.$emit('update:modelValue', cell.iso);
                this.$emit('select', cell.iso);
            } else if (this.mode === 'multiple') {
                const selected = this.selectedDates.includes(cell.iso)
                    ? this.selectedDates.filter((date) => date !== cell.iso)
                    : [...this.selectedDates, cell.iso];
                const sorted = [...selected].sort();
                this.$emit('update:selectedDates', sorted);
                this.$emit('select', cell.iso);
            } else {
                // Range mode
                if (!this.rangeSelecting) {
                    this.$emit('update:startDate', cell.iso);
                    this.$emit('update:endDate', cell.iso);
                    this.rangeSelecting = true;
                } else {
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
            if (this.mode === 'range' && this.rangeSelecting && !cell.blank && !cell.disabled) {
                this.hoverDate = cell.iso;
            }
        },
    },
};
</script>

<style scoped>
.mini-cal-card {
    display: flex;
    flex-direction: column;
    width: fit-content;
    padding: 12px 16px;
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
    user-select: none;
    font-family: inherit;
}

.mini-cal-nav-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.mini-cal-arrow-btn {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: var(--admin-text, #0f172a);
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.15s ease;
}

.mini-cal-arrow-btn:hover {
    background: var(--admin-hover, #f8fafc);
}

.mini-cal-top-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
}

.mini-cal-months-body {
    display: flex;
    gap: 20px;
}

@media (max-width: 640px) {
    .mini-cal-months-body {
        flex-direction: column;
        gap: 16px;
    }
}

.mini-cal-month-panel {
    display: flex;
    flex-direction: column;
    width: 252px;
}

.mini-cal-month-heading {
    text-align: center;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
    margin-bottom: 10px;
}

.mini-cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    margin-bottom: 8px;
    text-align: center;
}

.mini-cal-weekdays span {
    font-size: 13px;
    font-weight: 400;
    color: var(--admin-muted, #64748b);
    height: 24px;
    display: grid;
    place-items: center;
}

.mini-cal-days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px 0;
}

.mini-cal-day-blank {
    height: 36px;
}

.mini-cal-day-btn {
    position: relative;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    margin: 0 auto;
    border: none;
    border-radius: 9999px;
    background: transparent;
    color: var(--admin-text, #0f172a);
    font-size: 14px;
    font-weight: 400;
    cursor: pointer;
    transition: all 0.15s ease;
}

.mini-cal-day-btn:hover:not(:disabled):not(.selected) {
    background: var(--admin-hover, #f1f5f9);
}

.mini-cal-day-btn:disabled {
    color: var(--admin-faint, #cbd5e1);
    cursor: not-allowed;
}

.mini-cal-day-btn.today:not(.selected) .mini-cal-day-num {
    font-weight: 700;
    color: var(--admin-accent, #10b981);
}

.mini-cal-day-btn.selected,
.mini-cal-day-btn.range-start,
.mini-cal-day-btn.range-end {
    background: var(--admin-accent, #10b981) !important;
    color: #ffffff !important;
    font-weight: 600;
}

.mini-cal-day-btn.selected .mini-cal-day-num,
.mini-cal-day-btn.range-start .mini-cal-day-num,
.mini-cal-day-btn.range-end .mini-cal-day-num {
    color: #ffffff !important;
}

.mini-cal-day-btn.in-range:not(.range-start):not(.range-end):not(.selected) {
    background: #ecfdf5;
    color: #047857;
    border-radius: 0;
}

.mini-cal-day-num {
    line-height: 1;
}

.mini-cal-dots {
    position: absolute;
    bottom: 3px;
    display: flex;
    gap: 2px;
}

.mini-cal-dots i {
    width: 4px;
    height: 4px;
    border-radius: 50%;
}
</style>

<template>
  <div
    ref="root"
    class="booking-datetime"
    :class="{ 'booking-datetime--compact': compact, 'booking-datetime--above': openAbove }"
  >
    <button
      type="button"
      class="booking-datetime__trigger"
      :aria-expanded="open ? 'true' : 'false'"
      @click="toggle"
    >
      <svg class="booking-datetime__icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M8 2v4M16 2v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/>
      </svg>
      <span class="booking-datetime__value">
        <strong>{{ displayDate }}</strong>
        <span>{{ displayTime }}</span>
      </span>
      <svg class="booking-datetime__chevron" viewBox="0 0 24 24" aria-hidden="true">
        <path d="m6 9 6 6 6-6"/>
      </svg>
    </button>

    <div v-if="open" class="booking-datetime__backdrop" @click="cancel"></div>

    <div v-if="open" class="booking-datetime__popover" role="dialog" aria-label="Chọn thời gian chơi">
      <div class="booking-datetime__header">
        <button type="button" :disabled="!canGoPreviousMonth" @click="changeMonth(-1)" aria-label="Tháng trước">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <strong>{{ monthLabel }}</strong>
        <button type="button" @click="changeMonth(1)" aria-label="Tháng sau">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>

      <div class="booking-datetime__quick">
        <button
          v-for="option in quickOptions"
          :key="option.label"
          type="button"
          :class="{ active: draftDate === option.value }"
          @click="selectDate(option.value)"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="booking-datetime__body">
        <section class="booking-datetime__calendar" aria-label="Lịch">
          <div class="booking-datetime__weekdays">
            <span v-for="day in weekdays" :key="day">{{ day }}</span>
          </div>
          <div class="booking-datetime__days">
            <span v-for="blank in leadingBlanks" :key="`blank-${blank}`"></span>
            <button
              v-for="day in monthDays"
              :key="day.value"
              type="button"
              :disabled="day.disabled"
              :class="{ selected: draftDate === day.value, today: day.today }"
              @click="selectDate(day.value)"
            >
              {{ day.day }}
            </button>
          </div>
        </section>

        <section class="booking-datetime__times" aria-label="Giờ bắt đầu">
          <h3>Giờ bắt đầu</h3>
          <div>
            <button
              v-for="option in normalizedTimeOptions"
              :key="option.value"
              type="button"
              :class="{ selected: draftTime === option.value }"
              @click="selectTime(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
        </section>
      </div>

      <div class="booking-datetime__footer">
        <button type="button" class="booking-datetime__secondary" @click="cancel">Hủy</button>
        <button type="button" class="booking-datetime__primary" @click="applySelection">Áp dụng</button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "BookingDateTimePicker",
  props: {
    date: {
      type: String,
      required: true,
    },
    time: {
      type: String,
      required: true,
    },
    minDate: {
      type: String,
      default: "",
    },
    timeOptions: {
      type: Array,
      default: () => [],
    },
    compact: {
      type: Boolean,
      default: false,
    },
  },
  emits: ["update:date", "update:time", "change"],
  data() {
    const initialDate = this.date || this.todayValue();
    return {
      open: false,
      openAbove: false,
      draftDate: initialDate,
      draftTime: this.normalizeTime(this.time),
      visibleMonth: this.firstDayOfMonth(initialDate),
    };
  },
  computed: {
    displayDate() {
      const parsed = this.parseDate(this.date);
      if (!parsed) return "Chọn ngày";
      return new Intl.DateTimeFormat("vi-VN", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      }).format(parsed);
    },
    displayTime() {
      return this.normalizeTime(this.time).slice(0, 5);
    },
    monthLabel() {
      return new Intl.DateTimeFormat("vi-VN", {
        month: "long",
        year: "numeric",
      }).format(this.visibleMonth);
    },
    weekdays() {
      return ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];
    },
    leadingBlanks() {
      const firstDay = this.visibleMonth.getDay();
      const mondayOffset = (firstDay + 6) % 7;
      return Array.from({ length: mondayOffset }, (_, index) => index);
    },
    monthDays() {
      const year = this.visibleMonth.getFullYear();
      const month = this.visibleMonth.getMonth();
      const totalDays = new Date(year, month + 1, 0).getDate();
      const today = this.todayValue();

      return Array.from({ length: totalDays }, (_, index) => {
        const date = new Date(year, month, index + 1);
        const value = this.dateValue(date);

        return {
          day: index + 1,
          value,
          disabled: this.isDateDisabled(value),
          today: value === today,
        };
      });
    },
    normalizedTimeOptions() {
      const options = this.timeOptions.length
        ? this.timeOptions
        : ["05:00", "06:00", "07:00", "08:00", "09:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00", "16:00", "17:00", "18:00", "19:00", "20:00", "21:00"];

      return options.map((option) => {
        const label = String(option).slice(0, 5);
        return {
          label,
          value: `${label}:00`,
        };
      });
    },
    quickOptions() {
      const today = this.parseDate(this.todayValue());
      const tomorrow = new Date(today);
      tomorrow.setDate(today.getDate() + 1);

      const weekend = new Date(today);
      const daysToSaturday = (6 - today.getDay() + 7) % 7;
      weekend.setDate(today.getDate() + daysToSaturday);

      return [
        { label: "Hôm nay", value: this.dateValue(today) },
        { label: "Ngày mai", value: this.dateValue(tomorrow) },
        { label: "Cuối tuần", value: this.dateValue(weekend) },
      ].filter((option, index, items) => {
        return !this.isDateDisabled(option.value) && items.findIndex((item) => item.value === option.value) === index;
      });
    },
    canGoPreviousMonth() {
      if (!this.minDate) return true;
      const previousMonth = new Date(this.visibleMonth.getFullYear(), this.visibleMonth.getMonth() - 1, 1);
      const minMonth = this.firstDayOfMonth(this.minDate);
      return previousMonth >= minMonth;
    },
  },
  watch: {
    date(value) {
      if (!this.open) {
        this.draftDate = value;
        this.visibleMonth = this.firstDayOfMonth(value);
      }
    },
    time(value) {
      if (!this.open) {
        this.draftTime = this.normalizeTime(value);
      }
    },
  },
  mounted() {
    document.addEventListener("pointerdown", this.handleOutsideClick);
    document.addEventListener("keydown", this.handleKeydown);
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.handleOutsideClick);
    document.removeEventListener("keydown", this.handleKeydown);
  },
  methods: {
    toggle() {
      if (this.open) {
        this.cancel();
        return;
      }

      this.draftDate = this.date || this.todayValue();
      this.draftTime = this.normalizeTime(this.time);
      this.visibleMonth = this.firstDayOfMonth(this.draftDate);
      this.openAbove = this.shouldOpenAbove();
      this.open = true;
    },
    cancel() {
      this.open = false;
    },
    applySelection() {
      if (!this.draftDate || this.isDateDisabled(this.draftDate)) return;

      this.$emit("update:date", this.draftDate);
      this.$emit("update:time", this.draftTime);
      this.$emit("change", {
        date: this.draftDate,
        time: this.draftTime,
      });
      this.open = false;
    },
    selectDate(value) {
      if (this.isDateDisabled(value)) return;
      this.draftDate = value;
      this.visibleMonth = this.firstDayOfMonth(value);
    },
    selectTime(value) {
      this.draftTime = this.normalizeTime(value);
    },
    changeMonth(offset) {
      if (offset < 0 && !this.canGoPreviousMonth) return;
      this.visibleMonth = new Date(this.visibleMonth.getFullYear(), this.visibleMonth.getMonth() + offset, 1);
    },
    handleOutsideClick(event) {
      if (!this.open || this.$refs.root?.contains(event.target)) return;
      this.cancel();
    },
    handleKeydown(event) {
      if (this.open && event.key === "Escape") this.cancel();
    },
    shouldOpenAbove() {
      if (window.innerWidth <= 700) return false;

      const rect = this.$refs.root?.getBoundingClientRect();
      if (!rect) return false;

      const estimatedPopoverHeight = 430;
      const spaceBelow = window.innerHeight - rect.bottom;
      const spaceAbove = rect.top;

      return spaceBelow < estimatedPopoverHeight && spaceAbove > estimatedPopoverHeight;
    },
    isDateDisabled(value) {
      return Boolean(this.minDate && value < this.minDate);
    },
    normalizeTime(value) {
      const label = String(value || "18:00:00").slice(0, 5);
      return `${label}:00`;
    },
    todayValue() {
      return this.dateValue(new Date());
    },
    firstDayOfMonth(value) {
      const date = this.parseDate(value) || new Date();
      return new Date(date.getFullYear(), date.getMonth(), 1);
    },
    parseDate(value) {
      const parts = String(value || "").split("-").map(Number);
      if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) return null;
      return new Date(parts[0], parts[1] - 1, parts[2]);
    },
    dateValue(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    },
  },
};
</script>

<style scoped>
.booking-datetime {
  position: relative;
  min-width: 0;
}

.booking-datetime__trigger {
  display: grid;
  grid-template-columns: 18px minmax(0, 1fr) 18px;
  align-items: center;
  gap: 12px;
  width: 100%;
  height: 46px;
  padding: 0 14px;
  border: 1px solid #d8e3dc;
  border-radius: 10px;
  background: #fff;
  color: #111827;
  cursor: pointer;
  text-align: left;
}

.booking-datetime--compact .booking-datetime__trigger {
  height: 44px;
  border-radius: 8px;
}

.booking-datetime__trigger:focus-visible {
  border-color: #12864f;
  box-shadow: 0 0 0 4px rgba(18, 134, 79, .12);
  outline: none;
}

.booking-datetime__icon,
.booking-datetime__chevron,
.booking-datetime__header svg {
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.booking-datetime__icon,
.booking-datetime__chevron {
  width: 18px;
  height: 18px;
}

.booking-datetime__icon {
  color: #0b7a46;
}

.booking-datetime__chevron {
  color: #5e6f64;
}

.booking-datetime__value {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
  color: #111827;
  font-size: 14px;
  font-weight: 850;
  white-space: nowrap;
}

.booking-datetime__value strong,
.booking-datetime__value span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.booking-datetime__value span {
  color: #526156;
  font-weight: 800;
}

.booking-datetime__backdrop {
  display: none;
}

.booking-datetime__popover {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  z-index: 80;
  width: min(420px, calc(100vw - 32px));
  padding: 14px;
  border: 1px solid #d8e3dc;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .14);
}

.booking-datetime--above .booking-datetime__popover {
  top: auto;
  bottom: calc(100% + 10px);
}

.booking-datetime__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.booking-datetime__header strong {
  color: #102015;
  font-size: 15px;
  font-weight: 900;
  text-transform: capitalize;
}

.booking-datetime__header button {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border: 1px solid #dfe8e2;
  border-radius: 8px;
  background: #fff;
  color: #15231a;
  cursor: pointer;
}

.booking-datetime__header button:disabled {
  cursor: not-allowed;
  opacity: .42;
}

.booking-datetime__header svg {
  width: 17px;
  height: 17px;
}

.booking-datetime__quick {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin: 12px 0;
}

.booking-datetime__quick button,
.booking-datetime__times button {
  border: 1px solid #dfe8e2;
  border-radius: 8px;
  background: #fff;
  color: #233226;
  cursor: pointer;
  font-weight: 800;
}

.booking-datetime__quick button {
  min-height: 34px;
  padding: 0 8px;
  font-size: 12px;
}

.booking-datetime__quick button.active,
.booking-datetime__times button.selected {
  border-color: #0d8c51;
  background: #e8f8ef;
  color: #05603a;
}

.booking-datetime__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 104px;
  gap: 14px;
}

.booking-datetime__weekdays,
.booking-datetime__days {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 4px;
}

.booking-datetime__weekdays {
  margin-bottom: 6px;
}

.booking-datetime__weekdays span {
  color: #667568;
  font-size: 11px;
  font-weight: 850;
  text-align: center;
}

.booking-datetime__days button {
  display: grid;
  min-width: 0;
  height: 34px;
  place-items: center;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: #17231b;
  cursor: pointer;
  font-size: 13px;
  font-weight: 850;
}

.booking-datetime__days button:hover:not(:disabled) {
  background: #f2f7f4;
}

.booking-datetime__days button.today {
  border-color: #bad8c8;
}

.booking-datetime__days button.selected {
  border-color: #0d8c51;
  background: #0d8c51;
  color: #fff;
}

.booking-datetime__days button:disabled {
  color: #b8c2bc;
  cursor: not-allowed;
}

.booking-datetime__times {
  min-width: 0;
}

.booking-datetime__times h3 {
  margin: 0 0 8px;
  color: #233226;
  font-size: 12px;
  font-weight: 900;
}

.booking-datetime__times div {
  display: grid;
  gap: 6px;
  max-height: 246px;
  overflow: auto;
  padding-right: 2px;
}

.booking-datetime__times button {
  min-height: 32px;
  padding: 0 8px;
  font-size: 12px;
}

.booking-datetime__footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid #edf2ef;
}

.booking-datetime__footer button {
  min-height: 36px;
  padding: 0 14px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 900;
}

.booking-datetime__secondary {
  border: 1px solid #dfe8e2;
  background: #fff;
  color: #233226;
}

.booking-datetime__primary {
  border: 1px solid #0d8c51;
  background: #0d8c51;
  color: #fff;
}

@media (max-width: 700px) {
  .booking-datetime__value {
    gap: 10px;
    font-size: 16px;
  }

  .booking-datetime__trigger {
    height: 90px;
    border-radius: 14px;
    padding: 0 28px;
    grid-template-columns: 22px minmax(0, 1fr) 22px;
  }

  .booking-datetime__icon,
  .booking-datetime__chevron {
    width: 22px;
    height: 22px;
  }

  .booking-datetime__backdrop {
    position: fixed;
    inset: 0;
    z-index: 190;
    display: block;
    background: rgba(15, 23, 42, .36);
  }

  .booking-datetime__popover {
    position: fixed;
    top: auto;
    right: 10px;
    bottom: 10px;
    left: 10px;
    z-index: 200;
    width: auto;
    max-height: calc(100vh - 28px);
    overflow: auto;
    border-radius: 14px;
    padding: 14px;
  }

  .booking-datetime__body {
    grid-template-columns: 1fr;
  }

  .booking-datetime__times div {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    max-height: none;
    padding-right: 0;
  }

  .booking-datetime__footer {
    position: sticky;
    bottom: 0;
    background: #fff;
  }
}
</style>

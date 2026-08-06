<template>
  <div class="sg-date-wrapper" tabindex="-1" @focusout="handleFocusOut">
    <!-- Preset Pills -->
    <div class="sg-date-presets">
      <button
        type="button"
        class="sg-preset-pill"
        :class="{ 'is-active': isTodaySelected }"
        @click="selectPreset('today')"
      >
        Hôm nay
      </button>
      <button
        type="button"
        class="sg-preset-pill"
        :class="{ 'is-active': isTomorrowSelected }"
        @click="selectPreset('tomorrow')"
      >
        Ngày mai
      </button>
      <button
        type="button"
        class="sg-preset-pill"
        :class="{ 'is-active': isWeekendSelected }"
        @click="selectPreset('weekend')"
      >
        Cuối tuần
      </button>
    </div>

    <!-- Date Trigger Input -->
    <div class="sg-date-trigger" :class="{ 'is-open': isOpen }" @click="toggleCalendar">
      <span class="sg-date-text">{{ formattedDisplayDate }}</span>
      <span class="sg-date-caret">▼</span>
    </div>

    <!-- Calendar Popover Panel -->
    <div v-if="isOpen" class="sg-calendar-panel">
      <!-- Month Navigation Header -->
      <div class="sg-calendar-header">
        <button type="button" class="sg-cal-nav-btn" @click.stop="prevMonth">◀</button>
        <span class="sg-cal-month-title">{{ monthYearTitle }}</span>
        <button type="button" class="sg-cal-nav-btn" @click.stop="nextMonth">▶</button>
      </div>

      <!-- Weekday Labels -->
      <div class="sg-calendar-weekdays">
        <span v-for="day in weekDays" :key="day">{{ day }}</span>
      </div>

      <!-- Calendar Days Grid -->
      <div class="sg-calendar-days">
        <!-- Empty padding cells -->
        <span v-for="pad in firstDayOfWeekOffset" :key="'pad-' + pad" class="sg-day-cell is-empty"></span>

        <!-- Month Day Cells -->
        <button
          v-for="d in daysInMonth"
          :key="'day-' + d"
          type="button"
          class="sg-day-cell"
          :class="{
            'is-selected': isDaySelected(d),
            'is-disabled': isDayDisabled(d),
            'is-today': isDayToday(d)
          }"
          :disabled="isDayDisabled(d)"
          @click.stop="selectDay(d)"
        >
          {{ d }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";

function toLocalDateString(d) {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

const props = defineProps({
  modelValue: { type: String, default: "" },
  min: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "change"]);

const isOpen = ref(false);
const todayObj = new Date();

// Internal state for calendar navigation
const viewDate = ref(new Date());

const weekDays = ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];

const currentDateObj = computed(() => {
  if (!props.modelValue) return new Date();
  const [y, m, d] = props.modelValue.split("-").map(Number);
  return new Date(y, m - 1, d);
});

const formattedDisplayDate = computed(() => {
  const d = currentDateObj.value;
  const daysMap = ["Chủ Nhật", "Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy"];
  const dayName = daysMap[d.getDay()];
  const dayStr = String(d.getDate()).padStart(2, "0");
  const monthStr = String(d.getMonth() + 1).padStart(2, "0");
  return `${dayName}, ${dayStr}/${monthStr}/${d.getFullYear()}`;
});

const monthYearTitle = computed(() => {
  const monthNames = [
    "Tháng 1", "Tháng 2", "Tháng 3", "Tháng 4", "Tháng 5", "Tháng 6",
    "Tháng 7", "Tháng 8", "Tháng 9", "Tháng 10", "Tháng 11", "Tháng 12"
  ];
  return `${monthNames[viewDate.value.getMonth()]} - ${viewDate.value.getFullYear()}`;
});

const daysInMonth = computed(() => {
  const year = viewDate.value.getFullYear();
  const month = viewDate.value.getMonth();
  return new Date(year, month + 1, 0).getDate();
});

const firstDayOfWeekOffset = computed(() => {
  const year = viewDate.value.getFullYear();
  const month = viewDate.value.getMonth();
  let day = new Date(year, month, 1).getDay(); // 0 is Sunday
  return day === 0 ? 6 : day - 1; // 0 = Mon, 6 = Sun
});

const todayStr = toLocalDateString(todayObj);

const isTodaySelected = computed(() => props.modelValue === todayStr);

const tomorrowStr = computed(() => {
  const tm = new Date();
  tm.setDate(tm.getDate() + 1);
  return toLocalDateString(tm);
});

const isTomorrowSelected = computed(() => props.modelValue === tomorrowStr.value);

const weekendStr = computed(() => {
  const d = new Date();
  const day = d.getDay();
  const diff = day === 6 ? 0 : day === 0 ? 0 : (6 - day);
  d.setDate(d.getDate() + diff);
  return toLocalDateString(d);
});

const isWeekendSelected = computed(() => props.modelValue === weekendStr.value);

const toggleCalendar = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    viewDate.value = new Date(currentDateObj.value);
  }
};

const handleFocusOut = (e) => {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    isOpen.value = false;
  }
};

const prevMonth = () => {
  const v = new Date(viewDate.value);
  v.setMonth(v.getMonth() - 1);
  viewDate.value = v;
};

const nextMonth = () => {
  const v = new Date(viewDate.value);
  v.setMonth(v.getMonth() + 1);
  viewDate.value = v;
};

const isDaySelected = (d) => {
  const y = viewDate.value.getFullYear();
  const m = viewDate.value.getMonth();
  const checkStr = toLocalDateString(new Date(y, m, d));
  return checkStr === props.modelValue;
};

const isDayToday = (d) => {
  const y = viewDate.value.getFullYear();
  const m = viewDate.value.getMonth();
  return toLocalDateString(new Date(y, m, d)) === todayStr;
};

const isDayDisabled = (d) => {
  if (!props.min) return false;
  const y = viewDate.value.getFullYear();
  const m = viewDate.value.getMonth();
  const checkStr = toLocalDateString(new Date(y, m, d));
  return checkStr < props.min;
};

const selectDay = (d) => {
  const y = viewDate.value.getFullYear();
  const m = viewDate.value.getMonth();
  const val = toLocalDateString(new Date(y, m, d));
  emit("update:modelValue", val);
  emit("change", val);
  isOpen.value = false;
};

const selectPreset = (type) => {
  let val = todayStr;
  if (type === "tomorrow") val = tomorrowStr.value;
  if (type === "weekend") val = weekendStr.value;
  emit("update:modelValue", val);
  emit("change", val);
};
</script>

<style scoped>
.sg-date-wrapper {
  position: relative;
  width: 100%;
  outline: none !important;
}

.sg-date-presets {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}

.sg-preset-pill {
  background: #ffffff;
  border: 1.5px solid #1e293b;
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 12.5px;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
}

.sg-preset-pill.is-active {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
  font-weight: 700;
}

.sg-date-trigger {
  width: 100%;
  background: #ffffff;
  border: 1.5px solid #1e293b;
  border-radius: 8px;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  user-select: none;
  font-size: 14px;
  color: #0f172a;
  font-weight: 600;
}

.sg-date-trigger.is-open {
  border-color: #15803d;
}

.sg-date-caret {
  font-size: 10px;
  color: #0f172a;
  font-weight: 700;
}

.sg-calendar-panel {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.14);
  z-index: 1050;
  padding: 16px;
}

.sg-calendar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.sg-cal-month-title {
  font-size: 14.5px;
  font-weight: 700;
  color: #111827;
}

.sg-cal-nav-btn {
  background: #f1f5f9;
  border: none;
  border-radius: 6px;
  width: 28px;
  height: 28px;
  font-size: 11px;
  color: #475569;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sg-calendar-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 8px;
}

.sg-calendar-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.sg-day-cell {
  background: transparent;
  border: none;
  height: 36px;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 500;
  color: #1e293b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sg-day-cell.is-empty {
  cursor: default;
}

.sg-day-cell.is-today {
  border: 1px dashed #15803d;
  color: #15803d;
  font-weight: 700;
}

.sg-day-cell.is-selected {
  background: #15803d !important;
  color: #ffffff !important;
  font-weight: 700;
}

.sg-day-cell.is-disabled {
  color: #cbd5e1;
  cursor: not-allowed;
}
</style>

<template>
  <div class="sg-time-slots-container">
    <div class="sg-time-chips-grid">
      <button
        v-for="slot in availableSlots"
        :key="slot.value"
        type="button"
        class="sg-time-chip"
        :class="{ 'is-selected': isSelected(slot.value) }"
        @click="selectSlot(slot.value)"
      >
        <span>{{ slot.label }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: String, default: "18:00:00" },
});

const emit = defineEmits(["update:modelValue", "change"]);

const defaultTimes = [
  "05:00", "06:00", "07:00", "08:00", "09:00", "10:00", "11:00", "12:00",
  "13:00", "14:00", "15:00", "16:00", "17:00", "18:00", "19:00", "20:00",
  "21:00", "22:00", "23:00"
];

const availableSlots = computed(() =>
  defaultTimes.map((t) => ({
    label: t,
    value: `${t}:00`,
  }))
);

const isSelected = (val) => {
  if (!props.modelValue) return false;
  return String(props.modelValue).slice(0, 5) === String(val).slice(0, 5);
};

const selectSlot = (val) => {
  emit("update:modelValue", val);
  emit("change", val);
};
</script>

<style scoped>
.sg-time-slots-container {
  width: 100%;
}

.sg-time-chips-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(68px, 1fr));
  gap: 8px;
}

.sg-time-chip {
  background: #ffffff;
  border: 1.5px solid #1e293b;
  border-radius: 6px;
  padding: 8px 0;
  font-size: 13.5px;
  font-weight: 700;
  color: #0f172a;
  cursor: pointer;
  text-align: center;
  user-select: none;
}

.sg-time-chip.is-selected {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
  font-weight: 700;
}
</style>

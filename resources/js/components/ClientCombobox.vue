<template>
  <div class="sg-combo-wrapper" tabindex="-1" @focusout="handleFocusOut">
    <div
      class="sg-combo-trigger"
      :class="{ 'is-disabled': disabled, 'is-open': isOpen }"
      @click="toggleDropdown"
    >
      <span class="sg-combo-text" :class="{ 'is-placeholder': !selectedLabel }">
        {{ selectedLabel || placeholder }}
      </span>
      <span class="sg-combo-caret">▼</span>
    </div>

    <!-- Custom Dropdown Panel -->
    <div v-if="isOpen && !disabled" class="sg-combo-panel">
      <!-- Search input if options > 5 -->
      <div v-if="options.length > 5" class="sg-combo-search-wrap">
        <input
          ref="searchInput"
          v-model="searchQuery"
          type="text"
          class="sg-combo-search-input"
          placeholder="Tìm kiếm..."
          @click.stop
        />
      </div>

      <!-- Options List -->
      <div class="sg-combo-list">
        <button
          v-for="opt in filteredOptions"
          :key="getOptionValue(opt)"
          type="button"
          class="sg-combo-option"
          :class="{ 'is-selected': isSelected(opt) }"
          @click.stop="selectOption(opt)"
        >
          <span>{{ getOptionLabel(opt) }}</span>
        </button>

        <div v-if="filteredOptions.length === 0" class="sg-combo-empty">
          Không tìm thấy kết quả phù hợp
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from "vue";

const props = defineProps({
  modelValue: { type: [String, Number], default: "" },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: "Chọn..." },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "change", "select"]);

const isOpen = ref(false);
const searchQuery = ref("");
const searchInput = ref(null);

const removeAccents = (str) => {
  if (!str) return "";
  return String(str)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/Đ/g, "D")
    .toLowerCase();
};

const getOptionValue = (opt) => (typeof opt === "object" && opt !== null ? String(opt.value) : String(opt));
const getOptionLabel = (opt) => (typeof opt === "object" && opt !== null ? opt.label : opt);

const isSelected = (opt) => getOptionValue(opt) === String(props.modelValue);

const selectedOption = computed(() =>
  props.options.find((opt) => isSelected(opt))
);

const selectedLabel = computed(() => {
  if (selectedOption.value) return getOptionLabel(selectedOption.value);
  if (props.modelValue !== "" && props.modelValue !== null) return String(props.modelValue);
  return "";
});

const filteredOptions = computed(() => {
  if (!searchQuery.value) return props.options;
  const q = removeAccents(searchQuery.value);
  return props.options.filter((opt) => {
    const label = getOptionLabel(opt);
    return removeAccents(label).includes(q);
  });
});

const toggleDropdown = () => {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    searchQuery.value = "";
    nextTick(() => {
      if (searchInput.value) searchInput.value.focus();
    });
  }
};

const selectOption = (opt) => {
  const val = getOptionValue(opt);
  emit("update:modelValue", val);
  emit("change", val);
  emit("select", opt);
  isOpen.value = false;
  searchQuery.value = "";
};

const handleFocusOut = (e) => {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    isOpen.value = false;
  }
};

watch(() => props.disabled, (newVal) => {
  if (newVal) isOpen.value = false;
});
</script>

<style scoped>
.sg-combo-wrapper {
  position: relative;
  width: 100%;
  outline: none !important;
}

.sg-combo-trigger {
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
  font-weight: 400;
  transition: border-color 0.15s ease;
}

.sg-combo-trigger.is-open {
  border-color: #15803d;
}

.sg-combo-trigger.is-disabled {
  background: #f1f5f9;
  border-color: #94a3b8;
  cursor: not-allowed;
  opacity: 0.8;
}

.sg-combo-text.is-placeholder {
  color: #475569;
  font-weight: 400;
}

.sg-combo-caret {
  font-size: 10px;
  color: #0f172a;
  font-weight: 400;
  margin-left: 8px;
}

.sg-combo-panel {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #ffffff;
  border: 1.5px solid #1e293b;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  z-index: 1050;
  overflow: hidden;
}

.sg-combo-search-wrap {
  padding: 8px;
  background: #f8fafc;
}

.sg-combo-search-input {
  width: 100%;
  border: 1.5px solid #1e293b !important;
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 13.5px;
  outline: none !important;
  box-shadow: none !important;
  color: #0f172a;
  font-weight: 400;
}

.sg-combo-list {
  max-height: 200px;
  overflow-y: auto;
  padding: 4px;
}

.sg-combo-option {
  width: 100%;
  padding: 9px 12px;
  font-size: 13.5px;
  font-weight: 400;
  color: #0f172a;
  background: transparent;
  border: none;
  text-align: left;
  cursor: pointer;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sg-combo-option.is-selected {
  background: #f0fdf4;
  color: #15803d;
  font-weight: 500;
}

.sg-combo-empty {
  padding: 12px;
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
}
</style>

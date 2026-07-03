<template>
  <div class="flex flex-col w-full gap-1.5 text-left">
    <label v-if="label" :for="inputId" class="text-sm font-medium text-zinc-200 text-left">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </label>
    <div class="relative">
      <input
        :id="inputId"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :type="type"
        :placeholder="placeholder"
        :required="required"
        v-bind="$attrs"
        :class="[
          'flex w-full rounded-md border border-zinc-800 bg-zinc-950 text-zinc-100 placeholder:text-zinc-500 transition-all',
          sizeClasses,
          focusClasses,
          customClass
        ]"
      />
    </div>
    <span v-if="error" class="text-red-500 text-xs mt-1">{{ error }}</span>
  </div>
</template>

<script>
import { computed } from 'vue';

export default {
  name: 'BaseInput',
  inheritAttrs: false,
  props: {
    modelValue: {
      type: [String, Number],
      default: '',
    },
    label: {
      type: String,
      default: '',
    },
    placeholder: {
      type: String,
      default: '',
    },
    type: {
      type: String,
      default: 'text',
    },
    required: {
      type: Boolean,
      default: false,
    },
    size: {
      type: String,
      default: 'md',
      validator: (val) => ['sm', 'md', 'lg'].includes(val),
    },
    noRing: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
    },
    customClass: {
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue'],
  setup(props) {
    const inputId = 'base-input-' + Math.random().toString(36).substr(2, 9);

    const sizeClasses = computed(() => {
      switch (props.size) {
        case 'sm':
          return 'h-8 px-3 py-1 text-xs';
        case 'lg':
          return 'h-12 px-4 py-3 text-base';
        case 'md':
        default:
          return 'h-10 px-3 py-2 text-sm';
      }
    });

    const focusClasses = computed(() => {
      if (props.noRing) {
        return 'focus:outline-none focus:border-zinc-800 focus:ring-0';
      }
      return 'focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700';
    });

    return {
      inputId,
      sizeClasses,
      focusClasses,
    };
  },
};
</script>

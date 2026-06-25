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
        :type="showPassword ? 'text' : 'password'"
        :placeholder="placeholder"
        :required="required"
        :autocomplete="autocomplete"
        class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 !px-3 !py-2 !pe-10 text-sm text-zinc-100 placeholder:text-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-700 focus:border-zinc-700 transition-all"
      />
      <button
        type="button"
        @click="togglePasswordVisibility"
        class="absolute inset-y-0 end-0 flex h-full w-10 items-center justify-center text-zinc-500 hover:text-zinc-300 transition-colors focus:outline-none"
        :aria-label="showPassword ? 'Hide password' : 'Show password'"
      >
        <!-- EyeOff icon -->
        <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye-off"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
        <!-- Eye icon -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>
  </div>
</template>

<script>
import { ref } from 'vue';

export default {
  name: 'PasswordInput',
  props: {
    modelValue: {
      type: String,
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
    required: {
      type: Boolean,
      default: false,
    },
    autocomplete: {
      type: String,
      default: 'current-password',
    },
  },
  emits: ['update:modelValue'],
  setup() {
    const showPassword = ref(false);
    const inputId = 'pw-input-' + Math.random().toString(36).substr(2, 9);

    const togglePasswordVisibility = () => {
      showPassword.value = !showPassword.value;
    };

    return {
      showPassword,
      inputId,
      togglePasswordVisibility,
    };
  },
};
</script>

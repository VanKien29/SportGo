<template>
  <div style="border-radius: 12px; padding: 16px; background: #f8fafc;" :style="{ border: error ? '1px dashed #f87171' : '1px dashed var(--border-color)' }" :class="error ? 'border-red-400' : ''">
    <label class="block cursor-pointer">
      <span class="text-xs font-medium text-gray-600">{{ title }}<span v-if="required" class="ml-1 text-red-500">*</span></span>
      <input style="margin-top: 8px; width: 100%; font-size: 13px;" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" @change="emit('change', $event)" />
    </label>
    <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
    <ul v-if="files.length" class="mt-3 space-y-1.5">
      <li v-for="(file, idx) in files" :key="file.name + idx" class="flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-1.5 text-xs border border-gray-100">
        <span class="truncate text-gray-600">{{ file.name }} · {{ fileSize(file) }}</span>
        <button type="button" class="shrink-0 text-xs font-medium text-red-500 hover:text-red-700" @click="emit('remove', idx)">Xóa</button>
      </li>
    </ul>
    <p v-else class="mt-2 text-xs text-gray-400">Chưa chọn file.</p>
  </div>
</template>

<script setup>
const props = defineProps({
  title: { type: String, required: true },
  required: { type: Boolean, default: false },
  files: { type: Array, default: () => [] },
  error: { type: String, default: '' },
});

const emit = defineEmits(['change', 'remove']);

const fileSize = (file) => {
  const bytes = Number(file?.size || 0);
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
};
</script>

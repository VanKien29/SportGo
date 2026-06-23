<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

    <div class="relative w-full max-w-lg transform overflow-hidden rounded-xl bg-white shadow-2xl transition-all sm:my-8">
      <div class="border-b border-gray-100 px-6 py-4 flex justify-between items-center bg-gray-50/50">
        <h3 class="text-lg font-semibold text-gray-900">Ký điện tử</h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-500">
          <span class="sr-only">Đóng</span>
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="px-6 py-5">
        <p class="text-sm text-gray-500 mb-3">Vui lòng dùng chuột hoặc ngón tay để vẽ chữ ký của bạn vào khung bên dưới.</p>
        
        <div class="border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 overflow-hidden relative">
          <canvas 
            ref="canvas" 
            class="w-full h-48 cursor-crosshair touch-none bg-white"
            @mousedown="startDrawing"
            @mousemove="draw"
            @mouseup="stopDrawing"
            @mouseleave="stopDrawing"
            @touchstart.prevent="startDrawingTouch"
            @touchmove.prevent="drawTouch"
            @touchend.prevent="stopDrawing"
          ></canvas>
          <div v-if="isEmpty" class="absolute inset-0 flex items-center justify-center pointer-events-none text-gray-300 font-medium text-lg">
            Ký vào đây
          </div>
        </div>
        
        <div class="mt-3 flex justify-end">
          <button @click="clear" type="button" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            Ký lại
          </button>
        </div>
      </div>

      <div class="bg-gray-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-gray-100">
        <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50" @click="$emit('close')">
          Hủy bỏ
        </button>
        <button 
          type="button" 
          class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          :disabled="isEmpty || saving"
          @click="save"
        >
          <span v-if="saving" class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
          Xác nhận ký
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';

const props = defineProps({
  show: Boolean,
  saving: Boolean
});

const emit = defineEmits(['close', 'confirm']);

const canvas = ref(null);
const ctx = ref(null);
const isDrawing = ref(false);
const isEmpty = ref(true);
let lastX = 0;
let lastY = 0;

watch(() => props.show, (newVal) => {
  if (newVal) {
    nextTick(() => {
      initCanvas();
    });
  }
});

function initCanvas() {
  if (!canvas.value) return;
  const c = canvas.value;
  // Make it visually fill the positioned parent
  c.style.width = '100%';
  c.style.height = '100%';
  // ...then set the internal size to match
  c.width = c.offsetWidth;
  c.height = c.offsetHeight;
  
  ctx.value = c.getContext('2d');
  ctx.value.lineWidth = 3;
  ctx.value.lineCap = 'round';
  ctx.value.lineJoin = 'round';
  ctx.value.strokeStyle = '#0f172a'; // slate-900
  
  // Fill white background (useful when converting to image)
  ctx.value.fillStyle = '#ffffff';
  ctx.value.fillRect(0, 0, c.width, c.height);
  
  isEmpty.value = true;
}

function clear() {
  if (!ctx.value || !canvas.value) return;
  ctx.value.fillStyle = '#ffffff';
  ctx.value.fillRect(0, 0, canvas.value.width, canvas.value.height);
  isEmpty.value = true;
}

function startDrawing(e) {
  isDrawing.value = true;
  isEmpty.value = false;
  const rect = canvas.value.getBoundingClientRect();
  lastX = e.clientX - rect.left;
  lastY = e.clientY - rect.top;
}

function draw(e) {
  if (!isDrawing.value) return;
  const rect = canvas.value.getBoundingClientRect();
  const currentX = e.clientX - rect.left;
  const currentY = e.clientY - rect.top;
  
  ctx.value.beginPath();
  ctx.value.moveTo(lastX, lastY);
  ctx.value.lineTo(currentX, currentY);
  ctx.value.stroke();
  
  lastX = currentX;
  lastY = currentY;
}

function startDrawingTouch(e) {
  if (e.touches.length > 0) {
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousedown', {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    startDrawing(mouseEvent);
  }
}

function drawTouch(e) {
  if (e.touches.length > 0) {
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousemove', {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    draw(mouseEvent);
  }
}

function stopDrawing() {
  isDrawing.value = false;
}

function save() {
  if (isEmpty.value || !canvas.value) return;
  const dataUrl = canvas.value.toDataURL('image/png');
  emit('confirm', dataUrl);
}
</script>

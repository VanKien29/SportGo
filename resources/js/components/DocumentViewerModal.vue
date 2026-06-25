<template>
  <div v-if="show" class="fixed inset-0 z-[600] flex items-center justify-center p-4 sm:p-6">
    <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

    <div class="relative w-full h-full max-h-[90vh] max-w-6xl flex flex-col md:flex-row overflow-hidden rounded-xl bg-white shadow-2xl transition-all">
      <!-- Document Area -->
      <div class="flex-1 flex flex-col min-w-0 bg-gray-100 border-r border-gray-200">
        <div class="border-b border-gray-200 px-4 py-3 bg-white flex justify-between items-center shrink-0">
          <h3 class="text-base font-semibold text-gray-900 truncate pr-4">{{ document?.title || 'Xem trước văn bản' }}</h3>
          <div class="flex items-center gap-2">
            <button v-if="document?.download_url" @click="downloadDocument" class="text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-md text-sm font-medium transition">
              Tải xuống
            </button>
            <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 md:hidden">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
        </div>
        
        <div class="flex-1 overflow-auto relative p-4 flex justify-center" ref="scrollContainer">
          <div v-if="loading" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-100/80 z-10">
            <span class="h-8 w-8 rounded-full border-4 border-blue-200 border-t-blue-600 animate-spin mb-3"></span>
            <span class="text-sm font-medium text-gray-600">Đang tải nội dung văn bản...</span>
          </div>
          <div v-else-if="error" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-100 z-10 px-6 text-center">
            <svg class="w-12 h-12 text-red-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <p class="text-gray-800 font-medium mb-1">Không thể hiển thị văn bản</p>
            <p class="text-sm text-gray-500 mb-4">{{ error }}</p>
            <button v-if="document?.download_url" @click="downloadDocument" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
              Tải xuống để xem
            </button>
          </div>
          
          <div v-show="fileType === 'docx'" ref="docxContainer" class="bg-white shadow-sm ring-1 ring-gray-900/5 min-h-[800px] w-full max-w-[800px] p-0" :class="{ 'opacity-0': loading }"></div>
          
          <iframe v-if="fileType === 'pdf'" :src="fileUrl" class="w-full min-h-[800px] border-0 rounded bg-white shadow-sm ring-1 ring-gray-900/5" :class="{ 'opacity-0': loading }"></iframe>
          
          <div v-if="fileType === 'image'" class="flex items-start justify-center min-h-[800px] w-full bg-transparent p-4">
            <img :src="fileUrl" class="max-w-full h-auto shadow-sm ring-1 ring-gray-900/5 rounded bg-white" :class="{ 'opacity-0': loading }" alt="Preview" />
          </div>
          
          <div v-if="fileType === 'unsupported'" class="flex flex-col items-center justify-center min-h-[400px] w-full bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5 p-8 text-center" :class="{ 'opacity-0': loading }">
            <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
            <h4 class="text-lg font-medium text-gray-900 mb-2">Định dạng tệp không được hỗ trợ để xem trước</h4>
            <p class="text-gray-500 mb-6 max-w-md">Vui lòng tải tệp xuống để xem nội dung.</p>
            <button v-if="document?.download_url" @click="downloadDocument" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition">
              Tải xuống
            </button>
          </div>
        </div>
      </div>
      
      <!-- Info & Signatures Area -->
      <div class="w-full md:w-80 bg-white flex flex-col shrink-0">
        <div class="border-b border-gray-100 px-5 py-4 flex justify-between items-center shrink-0">
          <h3 class="text-base font-semibold text-gray-900">Thông tin chữ ký</h3>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-500 hidden md:block">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-5">
          <div v-if="!document?.signatures || document.signatures.length === 0" class="text-center py-10">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            <p class="mt-3 text-sm text-gray-500">Chưa có chữ ký nào.</p>
          </div>
          
          <div v-else class="space-y-4">
            <div v-for="sig in document.signatures" :key="sig.id" class="rounded-lg border border-gray-200 bg-gray-50 p-4 relative overflow-hidden">
              <div class="absolute top-0 right-0 p-2">
                <span v-if="sig.status === 'signed'" class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Đã ký</span>
                <span v-else class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-inset ring-yellow-600/20">Chờ ký</span>
              </div>
              
              <div class="flex items-center gap-2 mb-3">
                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold uppercase text-xs">
                  {{ sig.signer_side === 'owner' ? 'CS' : 'SG' }}
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">{{ sig.signer_full_name || (sig.signer_side === 'owner' ? 'Chủ sân' : 'Đại diện SportGo') }}</p>
                  <p class="text-xs text-gray-500">{{ sig.signer_title }} {{ sig.signer_organization ? `(${sig.signer_organization})` : '' }}</p>
                </div>
              </div>
              
              <div v-if="sig.status === 'signed'" class="space-y-1 mt-2 pt-2 border-t border-gray-200/60">
                <p class="text-xs text-gray-500 flex items-center gap-1.5">
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  {{ formatDate(sig.signed_at) }}
                </p>
                <p class="text-xs text-gray-500 flex items-center gap-1.5" v-if="sig.ip_address">
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  IP: {{ sig.ip_address }}
                </p>
              </div>
            </div>
          </div>
        </div>
        
        <div v-if="$slots.actions" class="p-4 border-t border-gray-100 bg-gray-50 mt-auto shrink-0">
          <slot name="actions"></slot>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue';
import { renderAsync } from 'docx-preview';
import { apiDownload, readToken } from '../services/api.js';

const props = defineProps({
  show: Boolean,
  document: Object
});

const emit = defineEmits(['close']);

const docxContainer = ref(null);
const loading = ref(false);
const error = ref(null);
const fileType = ref(null);
const fileUrl = ref(null);

watch(() => props.show, async (newVal) => {
  if (newVal && props.document) {
    loadDocument();
  } else {
    error.value = null;
    cleanup();
  }
});

watch(() => props.document, (newDoc) => {
  if (props.show && newDoc) {
    loadDocument();
  }
});

async function loadDocument() {
  if (!props.document?.download_url) {
    error.value = 'Không tìm thấy đường dẫn văn bản.';
    return;
  }
  
  loading.value = true;
  error.value = null;
  if (docxContainer.value) docxContainer.value.innerHTML = '';
  
  try {
    const token = readToken();
    const response = await fetch(props.document.download_url, {
      headers: token ? { 'Authorization': `Bearer ${token}` } : {}
    });
    
    if (!response.ok) throw new Error(`HTTP ${response.status} - Lỗi tải file`);
    
    const blob = await response.blob();
    const mimeType = blob.type.toLowerCase();
    
    await nextTick();
    
    if (mimeType === 'application/pdf') {
      fileType.value = 'pdf';
      fileUrl.value = URL.createObjectURL(blob);
    } else if (mimeType.startsWith('image/')) {
      fileType.value = 'image';
      fileUrl.value = URL.createObjectURL(blob);
    } else if (mimeType.includes('officedocument.wordprocessingml') || mimeType.includes('msword')) {
      fileType.value = 'docx';
      if (docxContainer.value) {
        await renderAsync(blob, docxContainer.value, null, {
          className: 'docx',
          inWrapper: true,
          ignoreWidth: false,
          ignoreHeight: true,
          ignoreFonts: false,
          breakPages: true,
          ignoreLastRenderedPageBreak: true,
          experimental: false,
          trimXmlDeclaration: true,
          debug: false,
        });
      }
    } else {
      fileType.value = 'unsupported';
    }
  } catch (err) {
    console.error('Error rendering DOCX:', err);
    error.value = err.message || 'Lỗi không xác định khi xem văn bản.';
  } finally {
    loading.value = false;
  }
}

function cleanup() {
  if (fileUrl.value) {
    URL.revokeObjectURL(fileUrl.value);
    fileUrl.value = null;
  }
  fileType.value = null;
  if (docxContainer.value) docxContainer.value.innerHTML = '';
}

onUnmounted(() => {
  cleanup();
});

function downloadDocument() {
  if (!props.document?.download_url) return;
  apiDownload(props.document.download_url);
}

function formatDate(dateString) {
  if (!dateString) return '';
  const d = new Date(dateString);
  return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<style>
/* Custom overrides for docx-preview if needed */
.docx-wrapper {
  background: transparent !important;
  padding: 0 !important;
}
.docx {
  box-shadow: none !important;
  margin-bottom: 0 !important;
}
</style>

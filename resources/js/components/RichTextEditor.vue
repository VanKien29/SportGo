<template>
  <div class="rich-text-editor border border-slate-200 rounded-lg overflow-hidden bg-white shadow-sm focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all duration-200">
    <!-- Toolbar -->
    <div class="editor-toolbar flex flex-wrap gap-1 items-center bg-slate-50 border-b border-slate-200 p-2 select-none">
      <!-- Headings -->
      <button type="button" @click="exec('formatBlock', 'H1')" class="tool-btn" title="Heading 1" :class="{ active: currentBlock === 'h1' }">
        <span class="font-bold text-xs">H1</span>
      </button>
      <button type="button" @click="exec('formatBlock', 'H2')" class="tool-btn" title="Heading 2" :class="{ active: currentBlock === 'h2' }">
        <span class="font-bold text-xs">H2</span>
      </button>
      <button type="button" @click="exec('formatBlock', 'H3')" class="tool-btn" title="Heading 3" :class="{ active: currentBlock === 'h3' }">
        <span class="font-bold text-xs">H3</span>
      </button>
      <div class="divider-v"></div>

      <!-- Text formatting -->
      <button type="button" @click="exec('bold')" class="tool-btn" title="In đậm (Bold)" :class="{ active: isBold }">
        <i class="fas fa-bold"></i>
      </button>
      <button type="button" @click="exec('italic')" class="tool-btn" title="In nghiêng (Italic)" :class="{ active: isItalic }">
        <i class="fas fa-italic"></i>
      </button>
      <div class="divider-v"></div>

      <!-- Lists -->
      <button type="button" @click="exec('insertUnorderedList')" class="tool-btn" title="Danh sách không thứ tự (Bullet List)">
        <i class="fas fa-list-ul"></i>
      </button>
      <button type="button" @click="exec('insertOrderedList')" class="tool-btn" title="Danh sách có thứ tự (Numbered List)">
        <i class="fas fa-list-ol"></i>
      </button>
      <div class="divider-v"></div>

      <!-- Actions -->
      <button type="button" @click="insertLink" class="tool-btn" title="Chèn liên kết (Insert Link)">
        <i class="fas fa-link"></i>
      </button>
      <button type="button" @click="triggerImageUpload" class="tool-btn" title="Chèn ảnh (Upload Image)" :disabled="uploading">
        <i v-if="uploading" class="fas fa-spinner fa-spin"></i>
        <i v-else class="fas fa-image"></i>
      </button>
      <input type="file" ref="imageInput" @change="handleImageUpload" accept="image/*" class="hidden" />
    </div>

    <!-- Editable Area -->
    <div
      ref="editorRef"
      contenteditable="true"
      @input="onInput"
      @keyup="updateState"
      @click="updateState"
      class="editor-content p-4 min-h-[220px] max-h-[500px] overflow-y-auto outline-none prose max-w-none text-slate-800 text-sm leading-relaxed"
      :placeholder="placeholder"
    ></div>

    <!-- Status bar / Info -->
    <div class="editor-footer flex justify-between items-center text-xs text-slate-500 bg-slate-50 border-t border-slate-100 px-3 py-1.5 select-none">
      <span v-if="uploading" class="text-emerald-600 flex items-center gap-1">
        <i class="fas fa-spinner fa-spin"></i> Đang tải ảnh lên...
      </span>
      <span v-else>Định dạng: HTML</span>
      
      <div class="flex gap-4">
        <span>Ký tự thực tế: <strong :class="{ 'text-error': plainTextLength < 100 }">{{ plainTextLength }}</strong> / min 100</span>
        <span>Từ: {{ wordCount }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Nhập nội dung bài viết...'
  }
});

const emit = defineEmits(['update:modelValue']);

const editorRef = ref(null);
const imageInput = ref(null);
const uploading = ref(false);

const isBold = ref(false);
const isItalic = ref(false);
const currentBlock = ref('');

// Plain text calculations for validation
const plainTextLength = ref(0);
const wordCount = ref(0);

onMounted(() => {
  if (editorRef.value && props.modelValue !== editorRef.value.innerHTML) {
    editorRef.value.innerHTML = props.modelValue;
    updateStats();
  }
});

watch(() => props.modelValue, (newVal) => {
  if (editorRef.value && newVal !== editorRef.value.innerHTML) {
    editorRef.value.innerHTML = newVal || '';
    updateStats();
  }
});

const onInput = () => {
  const html = editorRef.value.innerHTML;
  // If it's just empty paragraphs/br, normalize to empty
  if (html === '<p><br></p>' || html === '<br>' || html === '') {
    emit('update:modelValue', '');
  } else {
    emit('update:modelValue', html);
  }
  updateStats();
};

const exec = (command, value = null) => {
  editorRef.value.focus();
  document.execCommand(command, false, value);
  updateState();
  onInput();
};

const updateState = () => {
  isBold.value = document.queryCommandState('bold');
  isItalic.value = document.queryCommandState('italic');
  
  // Find current block element type at cursor
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    let parent = selection.getRangeAt(0).startContainer.parentNode;
    let block = '';
    while (parent && parent !== editorRef.value) {
      const tag = parent.tagName.toLowerCase();
      if (['h1', 'h2', 'h3', 'p'].includes(tag)) {
        block = tag;
        break;
      }
      parent = parent.parentNode;
    }
    currentBlock.value = block;
  }
};

const updateStats = () => {
  if (!editorRef.value) return;
  const text = editorRef.value.innerText || '';
  const trimmed = text.trim();
  plainTextLength.value = trimmed.length;
  wordCount.value = trimmed === '' ? 0 : trimmed.split(/\s+/).length;
};

const insertLink = () => {
  const url = prompt('Nhập địa chỉ URL cho liên kết:', 'https://');
  if (url && url !== 'https://') {
    exec('createLink', url);
  }
};

const triggerImageUpload = () => {
  imageInput.value.click();
};

const handleImageUpload = async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  if (file.size > 5 * 1024 * 1024) {
    alert('Ảnh không được vượt quá 5MB.');
    return;
  }

  const formData = new FormData();
  formData.append('image', file);

  try {
    uploading.value = true;
    const res = await axios.post('/api/owner/venue-posts/upload-editor-image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    
    // Clear input
    e.target.value = '';
    
    const imageUrl = res.data.url;
    
    editorRef.value.focus();
    // Insert image tag at cursor
    document.execCommand('insertImage', false, imageUrl);
    onInput();
  } catch (err) {
    console.error('Lỗi khi tải ảnh lên editor', err);
    alert(err.response?.data?.message || 'Không thể tải ảnh lên, vui lòng thử lại.');
  } finally {
    uploading.value = false;
  }
};
</script>

<style scoped>
.tool-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  color: #475569;
  border: 1px solid transparent;
  background: transparent;
  cursor: pointer;
  transition: all 0.15s ease;
}

.tool-btn:hover:not(:disabled) {
  background: #e2e8f0;
  color: #0f172a;
}

.tool-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.tool-btn.active {
  background: #ecfdf5;
  color: #059669;
  border-color: #a7f3d0;
}

.divider-v {
  width: 1px;
  height: 20px;
  background: #cbd5e1;
  margin: 0 4px;
}

.editor-content:empty::before {
  content: attr(placeholder);
  color: #94a3b8;
  pointer-events: none;
  display: block;
}

/* Base style resets inside editor-content block */
.editor-content :deep(h1) {
  font-size: 1.5rem;
  font-weight: 800;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}

.editor-content :deep(h2) {
  font-size: 1.25rem;
  font-weight: 700;
  margin-top: 0.8rem;
  margin-bottom: 0.4rem;
}

.editor-content :deep(h3) {
  font-size: 1.1rem;
  font-weight: 600;
  margin-top: 0.6rem;
  margin-bottom: 0.3rem;
}

.editor-content :deep(ul) {
  list-style-type: disc;
  padding-left: 1.5rem;
  margin-bottom: 1rem;
}

.editor-content :deep(ol) {
  list-style-type: decimal;
  padding-left: 1.5rem;
  margin-bottom: 1rem;
}

.editor-content :deep(p) {
  margin-bottom: 0.75rem;
}

.editor-content :deep(a) {
  color: #059669;
  text-decoration: underline;
  font-weight: 500;
}

.editor-content :deep(img) {
  max-width: 100%;
  border-radius: 8px;
  margin: 1rem 0;
  display: block;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
</style>

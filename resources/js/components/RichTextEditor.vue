<template>
  <div class="rich-text-editor border border-slate-200 rounded-lg overflow-hidden bg-white shadow-sm transition-all duration-200" :class="{ 'opacity-75 cursor-not-allowed pointer-events-none': disabled }">
    <ckeditor
      v-if="editorReady"
      :editor="editor"
      v-model="editorData"
      :config="editorConfig"
      :disabled="disabled"
    ></ckeditor>
    <div v-else class="p-4 text-center text-slate-500 flex justify-center items-center gap-2" style="min-height: 350px;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
      Đang tải trình soạn thảo...
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, shallowRef } from 'vue';
import { Ckeditor } from '@ckeditor/ckeditor5-vue';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import axios from 'axios';

const ckeditor = Ckeditor;

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Nhập nội dung bài viết...'
  },
  disabled: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

const editorData = ref(props.modelValue);
const editorReady = ref(false);
const editor = shallowRef(ClassicEditor);

// Custom Upload Adapter Plugin
function CustomUploadAdapterPlugin(editorInstance) {
  editorInstance.plugins.get('FileRepository').createUploadAdapter = (loader) => {
    return {
      upload: () => {
        return loader.file.then(file => {
          return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('image', file);
            axios.post('/api/owner/venue-posts/upload-editor-image', formData, {
              headers: { 'Content-Type': 'multipart/form-data' }
            }).then(response => {
              resolve({
                default: response.data.url
              });
            }).catch(error => {
              reject(error.response?.data?.message || 'Lỗi khi tải ảnh lên');
            });
          });
        });
      },
      abort: () => {}
    };
  };
}

const editorConfig = ref({
  placeholder: props.placeholder,
  extraPlugins: [CustomUploadAdapterPlugin],
  toolbar: {
    items: [
      'heading',
      '|',
      'bold',
      'italic',
      'link',
      'bulletedList',
      'numberedList',
      '|',
      'outdent',
      'indent',
      '|',
      'imageUpload',
      'blockQuote',
      'insertTable',
      'mediaEmbed',
      'undo',
      'redo'
    ]
  },
  image: {
    toolbar: [
      'imageTextAlternative',
      'toggleImageCaption',
      'imageStyle:inline',
      'imageStyle:block',
      'imageStyle:side'
    ]
  },
  table: {
    contentToolbar: [
      'tableColumn',
      'tableRow',
      'mergeTableCells'
    ]
  }
});

onMounted(() => {
  editorReady.value = true;
});

watch(editorData, (newValue) => {
  emit('update:modelValue', newValue);
});

watch(() => props.modelValue, (newValue) => {
  if (newValue !== editorData.value) {
    editorData.value = newValue;
  }
});
</script>

<style scoped>
.rich-text-editor {
  max-width: 100%;
}
.rich-text-editor :deep(.ck-editor__editable_inline) {
  min-height: 350px;
  max-height: 600px;
  overflow-y: auto;
}
.rich-text-editor :deep(.ck-toolbar) {
  border-top-left-radius: 8px !important;
  border-top-right-radius: 8px !important;
  background: #f8fafc !important;
  border-bottom: 1px solid #e2e8f0 !important;
  flex-wrap: wrap !important;
}
.rich-text-editor :deep(.ck.ck-toolbar__items) {
  flex-wrap: wrap !important;
}
.rich-text-editor :deep(.ck-editor) {
  width: 100%;
  max-width: 100%;
}
</style>

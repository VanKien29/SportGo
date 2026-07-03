<template>
  <div class="news-detail-container">
    <PublicNavbar />

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải bài viết...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button class="btn primary" @click="$router.push('/news')">Quay lại danh sách</button>
    </div>

    <!-- Content State -->
    <div v-else-if="post" class="news-detail-content">
      <div class="news-header">
        <div class="breadcrumb">
          <router-link to="/news">Tin tức</router-link>
          <i class="fas fa-chevron-right"></i>
          <span>{{ post.category || 'Bài viết' }}</span>
        </div>
        
        <h1 class="post-title">{{ post.title }}</h1>
        
        <div class="post-meta">
          <div class="author" v-if="post.author">
            <img v-if="post.author.avatar_url" :src="post.author.avatar_url" alt="Author" class="author-avatar" />
            <div v-else class="author-avatar-fallback">
              {{ post.author.full_name ? post.author.full_name.charAt(0).toUpperCase() : (post.author.username ? post.author.username.charAt(0).toUpperCase() : '?') }}
            </div>
            <span>{{ post.author.full_name || post.author.username }}</span>
          </div>
          <span class="dot" v-if="post.author">•</span>
          <span class="date"><i class="far fa-calendar-alt"></i> {{ formatDate(post.published_at) }}</span>
          <span class="dot">•</span>
          <span class="views"><i class="far fa-eye"></i> {{ post.view_count }} lượt xem</span>
        </div>
      </div>

      <div class="post-thumbnail" v-if="post.thumbnail_path">
        <img :src="post.thumbnail_path" :alt="post.title" />
      </div>

      <div class="post-body ql-editor" v-html="post.content"></div>
      
      <div class="post-footer">
        <button class="btn ghost" @click="$router.push('/news')">
          <i class="fas fa-arrow-left"></i> Quay lại danh sách
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const route = useRoute();
const router = useRouter();

const post = ref(null);
const loading = ref(true);
const error = ref(null);

const fetchPostDetail = async () => {
  loading.value = true;
  error.value = null;
  try {
    const slug = route.params.slug;
    const data = await api(`/api/system-news/${slug}`);
    post.value = data.data;
    
    // Update document title
    if (post.value && post.value.title) {
      document.title = `${post.value.title} - SportGo`;
    }
  } catch (err) {
    if (err.response && err.response.status === 404) {
      error.value = 'Bài viết không tồn tại hoặc đã bị ẩn.';
    } else {
      error.value = 'Đã có lỗi xảy ra khi tải bài viết.';
    }
    console.error(err);
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date(dateString));
};

onMounted(() => {
  fetchPostDetail();
});
</script>

<style scoped>
.news-detail-container {
  background-color: #f8fafc;
  min-height: 100vh;
  padding-top: 64px;
}

.news-detail-content {
  max-width: 800px;
  margin: 0 auto;
  padding: 40px 24px;
  background: white;
  min-height: calc(100vh - 64px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #64748b;
  margin-bottom: 24px;
}

.breadcrumb a {
  color: #10b981;
  text-decoration: none;
  font-weight: 500;
}

.breadcrumb a:hover {
  text-decoration: underline;
}

.breadcrumb i {
  font-size: 10px;
}

.post-title {
  font-size: 32px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.3;
  margin-bottom: 24px;
}

@media (min-width: 768px) {
  .post-title {
    font-size: 40px;
  }
}

.post-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 14px;
  color: #64748b;
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid #f1f5f9;
}

.author {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #334155;
  font-weight: 500;
}

.author-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.author-avatar-fallback {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #10b981;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
}

.dot {
  color: #cbd5e1;
}

.post-meta i {
  margin-right: 4px;
}

.post-thumbnail {
  margin-bottom: 40px;
  border-radius: 12px;
  overflow: hidden;
}

.post-thumbnail img {
  width: 100%;
  height: auto;
  max-height: 500px;
  object-fit: cover;
  display: block;
}

.post-body {
  font-size: 16px;
  line-height: 1.8;
  color: #334155;
  margin-bottom: 40px;
}

/* Base styles for rich text content */
.post-body :deep(h1), .post-body :deep(h2), .post-body :deep(h3), .post-body :deep(h4) {
  color: #0f172a;
  margin-top: 32px;
  margin-bottom: 16px;
  font-weight: 700;
}

.post-body :deep(h2) { font-size: 24px; }
.post-body :deep(h3) { font-size: 20px; }

.post-body :deep(p) {
  margin-bottom: 16px;
}

.post-body :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  margin: 16px 0;
}

.post-body :deep(ul), .post-body :deep(ol) {
  margin-bottom: 16px;
  padding-left: 24px;
}

.post-body :deep(li) {
  margin-bottom: 8px;
}

.post-body :deep(blockquote) {
  border-left: 4px solid #10b981;
  padding-left: 16px;
  margin: 24px 0;
  color: #475569;
  font-style: italic;
  background: #f8fafc;
  padding: 16px;
  border-radius: 0 8px 8px 0;
}

.post-footer {
  padding-top: 32px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: center;
}

/* States */
.loading-state, .error-state {
  text-align: center;
  padding: 120px 20px;
  color: #64748b;
  background: white;
  min-height: calc(100vh - 64px);
}

.loading-state .spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-state i {
  font-size: 48px;
  color: #ef4444;
  margin-bottom: 16px;
}
</style>

<style>
/* Dark Mode Support (Unscoped) */
.dark .news-detail-container {
  background-color: #09090b !important;
}
.dark .news-detail-content {
  background-color: #18181b !important;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5) !important;
}
.dark .breadcrumb {
  color: #a1a1aa !important;
}
.dark .post-title {
  color: #ffffff !important;
}
.dark .post-meta {
  color: #a1a1aa !important;
  border-bottom-color: #27272a !important;
}
.dark .author {
  color: #e4e4e7 !important;
}
.dark .dot {
  color: #52525b !important;
}
.dark .post-body {
  color: #e4e4e7 !important;
}
.dark .post-body h1, 
.dark .post-body h2, 
.dark .post-body h3, 
.dark .post-body h4 {
  color: #ffffff !important;
}
.dark .post-body blockquote {
  background: #27272a !important;
  color: #a1a1aa !important;
  border-left-color: #10b981 !important;
}
.dark .post-footer {
  border-top-color: #27272a !important;
}
.dark .loading-state, 
.dark .error-state {
  background-color: #18181b !important;
  color: #a1a1aa !important;
}
.dark .loading-state .spinner {
  border-color: #27272a !important;
  border-top-color: #10b981 !important;
}
</style>

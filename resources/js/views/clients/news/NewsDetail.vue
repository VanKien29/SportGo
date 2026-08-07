<template>
  <div class="nd-page">
    <PublicNavbar />

    <main class="nd-main">
      <!-- Breadcrumb -->
      <nav class="nd-breadcrumb" aria-label="Dieu huong tin tuc">
        <router-link :to="{ name: 'ClientNewsList' }" class="nd-back-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
          Quay lại tin tức
        </router-link>
      </nav>

      <!-- Loading -->
      <div v-if="loading" class="nd-state">
        <span class="nd-spinner"></span>
        <p>Đang tải bài viết...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="nd-state nd-state--error" role="alert">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <strong>Không thể mở bài viết</strong>
        <p>{{ error }}</p>
        <router-link class="nd-btn nd-btn--primary" :to="{ name: 'ClientNewsList' }">Quay lại danh sách</router-link>
      </div>

      <!-- Article -->
      <article v-else-if="post" class="nd-article">
        <!-- Article Header -->
        <header class="nd-article__header">
          <span v-if="post.category" class="nd-cat-badge" :style="{ background: catColor(post.category) }">{{ categoryLabel(post.category) }}</span>
          <h1 class="nd-article__title">{{ post.title }}</h1>

          <div class="nd-article__meta">
            <div class="nd-author">
              <div class="nd-author__avatar">{{ authorInitial }}</div>
              <div class="nd-author__info">
                <strong>{{ authorName }}</strong>
                <span>{{ formatDate(post.published_at) }} &middot; {{ Number(post.view_count || 0).toLocaleString('vi-VN') }} lượt xem</span>
              </div>
            </div>
          </div>
        </header>

        <!-- Cover image -->
        <div v-if="post.thumbnail_path" class="nd-article__cover">
          <img
            :src="mediaUrl(post.thumbnail_path)"
            :alt="post.title"
            @error="post.thumbnail_path = ''"
          />
        </div>

        <!-- Content -->
        <section class="nd-article__body ql-editor" v-html="post.content"></section>

        <!-- Footer -->
        <footer class="nd-article__footer">
          <router-link class="nd-btn nd-btn--outline" :to="{ name: 'ClientNewsList' }">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
            Quay lại danh sách
          </router-link>
        </footer>
      </article>
    </main>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import { normalizeMediaUrl } from '@/utils/mediaUrl.js';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const route = useRoute();
const post = ref(null);
const loading = ref(true);
const error = ref('');
const originalTitle = document.title;
const authorName = computed(() => post.value?.author?.full_name || post.value?.author?.username || 'Đội ngũ SportGo');
const authorInitial = computed(() => authorName.value.charAt(0).toUpperCase());

const catColors = { announcement: '#3b82f6', guide: '#10b981', news: '#f59e0b', event: '#8b5cf6' };
function catColor(v) { return catColors[v] || '#16a34a'; }

function categoryLabel(value) {
  return { announcement: 'Thông báo', guide: 'Hướng dẫn', news: 'Tin tức', event: 'Sự kiện' }[value] || value;
}

async function fetchPostDetail() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api(`/api/system-news/${route.params.slug}`);
    post.value = response.data;
    if (post.value?.title) document.title = `${post.value.title} - SportGo`;
  } catch (requestError) {
    post.value = null;
    error.value = requestError.status === 404
      ? 'Bài viết không tồn tại hoặc đã bị ẩn.'
      : requestError.message || 'Không thể tải bài viết.';
  } finally {
    loading.value = false;
  }
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(value));
}

function mediaUrl(path) {
  return normalizeMediaUrl({ file_path: path });
}

watch(() => route.params.slug, fetchPostDetail, { immediate: true });
onBeforeUnmount(() => { document.title = originalTitle; });
</script>

<style scoped>
/* PAGE */
.nd-page {
  min-height: 100vh;
  background: #f8fafc;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* MAIN */
.nd-main {
  max-width: 800px;
  margin: 0 auto;
  padding: 32px 24px 80px;
}

/* BREADCRUMB */
.nd-breadcrumb {
  margin-bottom: 28px;
}

.nd-back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #475569;
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  padding: 7px 14px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  transition: all 0.15s ease;
}

.nd-back-link:hover {
  border-color: #16a34a;
  color: #16a34a;
  background: #f0fdf4;
}

/* STATES */
.nd-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  padding: 100px 24px;
  text-align: center;
  color: #64748b;
}

.nd-state strong {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
}

.nd-state p {
  font-size: 14.5px;
  margin: 0;
}

.nd-state--error {
  color: #dc2626;
}

.nd-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #dcfce7;
  border-top-color: #16a34a;
  border-radius: 50%;
  animation: nd-spin 0.75s linear infinite;
}

@keyframes nd-spin { to { transform: rotate(360deg); } }

/* ARTICLE */
.nd-article {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  overflow: hidden;
}

.nd-article__header {
  padding: 36px 40px 28px;
  border-bottom: 1px solid #f1f5f9;
}

.nd-cat-badge {
  display: inline-block;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 9999px;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  margin-bottom: 16px;
}

.nd-article__title {
  font-size: clamp(22px, 4vw, 32px);
  font-weight: 800;
  color: #0f172a;
  line-height: 1.3;
  margin: 0 0 20px;
}

.nd-article__meta {
  display: flex;
  align-items: center;
  gap: 16px;
}

.nd-author {
  display: flex;
  align-items: center;
  gap: 12px;
}

.nd-author__avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, #16a34a, #22c55e);
  color: #ffffff;
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.nd-author__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nd-author__info strong {
  font-size: 13.5px;
  font-weight: 600;
  color: #0f172a;
}

.nd-author__info span {
  font-size: 12px;
  color: #94a3b8;
}

/* COVER */
.nd-article__cover {
  width: 100%;
  max-height: 460px;
  overflow: hidden;
}

.nd-article__cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* BODY */
.nd-article__body {
  padding: 36px 40px;
  font-size: 16px;
  line-height: 1.8;
  color: #334155;
}

/* Override quill snow styles for reading */
.nd-article__body :deep(h1),
.nd-article__body :deep(h2),
.nd-article__body :deep(h3) {
  font-weight: 700;
  color: #0f172a;
  margin-top: 28px;
  margin-bottom: 12px;
}

.nd-article__body :deep(p) {
  margin-bottom: 16px;
}

.nd-article__body :deep(img) {
  max-width: 100%;
  border-radius: 10px;
  margin: 16px 0;
}

.nd-article__body :deep(blockquote) {
  border-left: 4px solid #16a34a;
  background: #f0fdf4;
  margin: 20px 0;
  padding: 14px 20px;
  border-radius: 0 8px 8px 0;
  color: #374151;
}

.nd-article__body :deep(a) {
  color: #16a34a;
  text-decoration: underline;
}

/* FOOTER */
.nd-article__footer {
  padding: 24px 40px;
  border-top: 1px solid #f1f5f9;
}

/* BUTTONS */
.nd-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 20px;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  border: none;
}

.nd-btn--primary {
  background: #16a34a;
  color: #ffffff;
}

.nd-btn--primary:hover {
  background: #15803d;
}

.nd-btn--outline {
  border: 1.5px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
}

.nd-btn--outline:hover {
  border-color: #16a34a;
  color: #16a34a;
  background: #f0fdf4;
}

@media (max-width: 640px) {
  .nd-article__header,
  .nd-article__body,
  .nd-article__footer {
    padding: 24px 20px;
  }

  .nd-main {
    padding: 20px 16px 60px;
  }
}
</style>

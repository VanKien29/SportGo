<template>
  <div class="nl-page">
    <PublicNavbar />

    <!-- Hero Header -->
    <section class="nl-hero">
      <div class="nl-hero__inner">

        <h1 class="nl-hero__title">Tin tức & Hướng dẫn</h1>
        <p class="nl-hero__desc">Cập nhật thông báo hệ thống, hướng dẫn đặt sân và hoạt động thể thao mới nhất từ SportGo.</p>

        <!-- Search Bar -->
        <form class="nl-search" @submit.prevent="fetchPosts(1)">
          <span class="nl-search__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          </span>
          <label class="nl-sr-only" for="nl-search-input">Tim kiem bai viet</label>
          <input
            id="nl-search-input"
            v-model.trim="searchQuery"
            type="search"
            class="nl-search__input"
            placeholder="Tìm kiếm bài viết..."
            @keydown.enter.prevent="fetchPosts(1)"
          />
          <button class="nl-search__btn" type="submit">Tìm kiếm</button>
        </form>
      </div>
    </section>

    <main class="nl-main">
      <!-- Category Tabs -->
      <div class="nl-tabs" role="tablist" aria-label="Danh muc tin tuc">
        <button
          type="button"
          class="nl-tab"
          :class="{ 'nl-tab--active': !selectedCategory }"
          @click="setCategory('')"
        >Tất cả</button>
        <button
          v-for="cat in categories"
          :key="cat.value"
          type="button"
          class="nl-tab"
          :class="{ 'nl-tab--active': selectedCategory === cat.value }"
          @click="setCategory(cat.value)"
        >
          {{ cat.label }}
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="nl-state">
        <span class="nl-spinner"></span>
        <p>Đang tải tin tức...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="nl-state nl-state--error" role="alert">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <strong>Không thể tải tin tức</strong>
        <p>{{ error }}</p>
        <button class="nl-btn nl-btn--primary" type="button" @click="fetchPosts(pagination.current_page)">Thử lại</button>
      </div>

      <!-- Empty -->
      <div v-else-if="!posts.length" class="nl-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg>
        <strong>Chưa có bài viết phù hợp</strong>
        <p>Hãy thử từ khóa hoặc danh mục khác.</p>
      </div>

      <!-- News Grid -->
      <div v-else class="nl-grid">
        <!-- Featured first card -->
        <article
          v-if="posts[0]"
          class="nl-card nl-card--featured"
          @click="openPost(posts[0].slug)"
        >
          <div class="nl-card__media">
            <img
              v-if="posts[0].thumbnail_path && !brokenImages.has(posts[0].id)"
              :src="mediaUrl(posts[0].thumbnail_path)"
              :alt="posts[0].title"
              @error="hideBrokenImage(posts[0].id)"
            />
            <div v-else class="nl-card__placeholder">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg>
            </div>
            <span v-if="posts[0].category" class="nl-cat-badge" :style="{ background: catColor(posts[0].category) }">{{ categoryLabel(posts[0].category) }}</span>
          </div>
          <div class="nl-card__body">
            <div class="nl-card__meta">
              <span>{{ formatDate(posts[0].published_at) }}</span>
              <span class="nl-meta-dot"></span>
              <span>{{ Number(posts[0].view_count || 0).toLocaleString('vi-VN') }} lượt xem</span>
            </div>
            <h2 class="nl-card__title">{{ posts[0].title }}</h2>
            <p class="nl-card__desc">{{ posts[0].short_description || 'Thông tin mới từ hệ thống SportGo.' }}</p>
            <span class="nl-card__cta">
              Đọc bài viết
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
            </span>
          </div>
        </article>

        <!-- Remaining cards -->
        <article
          v-for="post in posts.slice(1)"
          :key="post.id"
          class="nl-card"
          @click="openPost(post.slug)"
        >
          <div class="nl-card__media">
            <img
              v-if="post.thumbnail_path && !brokenImages.has(post.id)"
              :src="mediaUrl(post.thumbnail_path)"
              :alt="post.title"
              @error="hideBrokenImage(post.id)"
            />
            <div v-else class="nl-card__placeholder">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8z"/></svg>
            </div>
            <span v-if="post.category" class="nl-cat-badge" :style="{ background: catColor(post.category) }">{{ categoryLabel(post.category) }}</span>
          </div>
          <div class="nl-card__body">
            <div class="nl-card__meta">
              <span>{{ formatDate(post.published_at) }}</span>
              <span class="nl-meta-dot"></span>
              <span>{{ Number(post.view_count || 0).toLocaleString('vi-VN') }} lượt xem</span>
            </div>
            <h2 class="nl-card__title">{{ post.title }}</h2>
            <p class="nl-card__desc">{{ post.short_description || 'Thông tin mới từ hệ thống SportGo.' }}</p>
            <span class="nl-card__cta">
              Đọc bài viết
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
            </span>
          </div>
        </article>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="nl-pagination">
        <button
          class="nl-page-btn"
          :disabled="pagination.current_page <= 1"
          @click="changePage(pagination.current_page - 1)"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
          Trước
        </button>
        <span class="nl-page-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          class="nl-page-btn"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="changePage(pagination.current_page + 1)"
        >
          Tiếp
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import { normalizeMediaUrl } from '@/utils/mediaUrl.js';

const router = useRouter();
const posts = ref([]);
const loading = ref(true);
const error = ref('');
const searchQuery = ref('');
const selectedCategory = ref('');
const brokenImages = ref(new Set());

const categories = [
  { value: 'announcement', label: 'Thông báo', color: '#3b82f6' },
  { value: 'guide',        label: 'Hướng dẫn', color: '#10b981' },
  { value: 'news',         label: 'Tin tức',   color: '#f59e0b' },
  { value: 'event',        label: 'Sự kiện',   color: '#8b5cf6' },
];

const catColors = { announcement: '#3b82f6', guide: '#10b981', news: '#f59e0b', event: '#8b5cf6' };
function catColor(v) { return catColors[v] || '#16a34a'; }

const pagination = ref({ current_page: 1, last_page: 1 });

async function fetchPosts(page = 1) {
  loading.value = true;
  error.value = '';
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '9' });
    if (searchQuery.value) params.set('keyword', searchQuery.value);
    if (selectedCategory.value) params.set('category', selectedCategory.value);
    const response = await api(`/api/system-news?${params.toString()}`);
    posts.value = Array.isArray(response.data) ? response.data : [];
    pagination.value = {
      current_page: Number(response.current_page || page),
      last_page: Number(response.last_page || 1),
    };
  } catch (requestError) {
    posts.value = [];
    error.value = requestError.message || 'Không thể tải danh sách tin tức.';
  } finally {
    loading.value = false;
  }
}

function setCategory(category) {
  selectedCategory.value = category;
  fetchPosts(1);
}

function changePage(page) {
  fetchPosts(page);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function openPost(slug) {
  router.push({ name: 'ClientNewsDetail', params: { slug } });
}

function categoryLabel(value) {
  return { announcement: 'Thông báo', guide: 'Hướng dẫn', news: 'Tin tức', event: 'Sự kiện' }[value] || value;
}

function mediaUrl(path) {
  return normalizeMediaUrl({ file_path: path });
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
}

function hideBrokenImage(postId) {
  const next = new Set(brokenImages.value);
  next.add(postId);
  brokenImages.value = next;
}

onMounted(() => fetchPosts());
</script>

<style scoped>
.nl-sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  border: 0;
}

/* PAGE */
.nl-page {
  min-height: 100vh;
  background: #f8fafc;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* HERO */
.nl-hero {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #14532d 100%);
  padding: 72px 24px 64px;
  text-align: center;
}

.nl-hero__inner {
  max-width: 680px;
  margin: 0 auto;
}

.nl-hero__eyebrow {
  display: inline-block;
  background: rgba(255, 255, 255, 0.1);
  color: #86efac;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  padding: 5px 14px;
  border-radius: 9999px;
  margin-bottom: 16px;
}

.nl-hero__title {
  font-size: clamp(28px, 5vw, 44px);
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 14px;
  line-height: 1.2;
}

.nl-hero__desc {
  color: #94a3b8;
  font-size: 16px;
  line-height: 1.6;
  margin: 0 0 32px;
}

/* SEARCH */
.nl-search {
  display: flex;
  align-items: center;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 6px 6px 6px 14px;
  gap: 10px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  max-width: 560px;
  margin: 0 auto;
}

.nl-search__icon {
  color: #94a3b8;
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.nl-search__input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 14.5px;
  color: #0f172a;
  background: transparent;
  min-width: 0;
}

.nl-search__input::placeholder {
  color: #94a3b8;
}

.nl-search__btn {
  background: #16a34a;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 600;
  padding: 9px 20px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: background 0.15s ease;
}

.nl-search__btn:hover {
  background: #15803d;
}

/* MAIN */
.nl-main {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 24px 80px;
}

/* TABS */
.nl-tabs {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 36px;
}

.nl-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: 9999px;
  border: 1.5px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.nl-tab:hover {
  border-color: #16a34a;
  color: #16a34a;
}

.nl-tab--active {
  background: #16a34a;
  border-color: #16a34a;
  color: #ffffff;
}

.nl-tab__dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

.nl-tab--active .nl-tab__dot {
  background: rgba(255,255,255,0.7) !important;
}

/* STATES */
.nl-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 80px 24px;
  text-align: center;
  color: #64748b;
}

.nl-state strong {
  font-size: 17px;
  font-weight: 600;
  color: #1e293b;
}

.nl-state p {
  font-size: 14px;
  margin: 0;
}

.nl-state--error {
  color: #dc2626;
}

.nl-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #dcfce7;
  border-top-color: #16a34a;
  border-radius: 50%;
  animation: nl-spin 0.75s linear infinite;
}

@keyframes nl-spin { to { transform: rotate(360deg); } }

/* GRID */
.nl-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

/* Featured card spans 2 cols */
.nl-card--featured {
  grid-column: span 2;
}

/* CARD */
.nl-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  display: flex;
  flex-direction: column;
}

/* Featured card media taller */
.nl-card--featured .nl-card__media {
  height: 280px;
}

.nl-card__media {
  position: relative;
  height: 200px;
  overflow: hidden;
  background: #f1f5f9;
  flex-shrink: 0;
}

.nl-card__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.nl-card__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
}

.nl-cat-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 9999px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.nl-card__body {
  padding: 20px 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
}

.nl-card__meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #94a3b8;
}

.nl-meta-dot {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #cbd5e1;
  flex-shrink: 0;
}

.nl-card__title {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.4;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.nl-card--featured .nl-card__title {
  font-size: 20px;
  -webkit-line-clamp: 3;
}

.nl-card__desc {
  font-size: 13.5px;
  color: #64748b;
  line-height: 1.55;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  flex: 1;
}

.nl-card__cta {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  color: #16a34a;
}

/* PAGINATION */
.nl-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 48px;
}

.nl-page-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 20px;
  border: 1.5px solid #e2e8f0;
  border-radius: 9999px;
  background: #ffffff;
  color: #475569;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.nl-page-btn:hover:not(:disabled) {
  border-color: #16a34a;
  color: #16a34a;
}

.nl-page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.nl-page-info {
  font-size: 13.5px;
  color: #64748b;
  font-weight: 500;
}

/* BUTTONS */
.nl-btn {
  padding: 9px 22px;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.nl-btn--primary {
  background: #16a34a;
  color: #ffffff;
}

.nl-btn--primary:hover {
  background: #15803d;
}

/* RESPONSIVE */
@media (max-width: 900px) {
  .nl-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .nl-card--featured {
    grid-column: span 2;
  }
}

@media (max-width: 600px) {
  .nl-grid {
    grid-template-columns: 1fr;
  }

  .nl-card--featured {
    grid-column: span 1;
  }

  .nl-hero {
    padding: 48px 16px 40px;
  }

  .nl-search {
    flex-wrap: wrap;
  }

  .nl-search__btn {
    width: 100%;
  }
}
</style>

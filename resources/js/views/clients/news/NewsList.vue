<template>
  <div class="news-page-container">
    <PublicNavbar />

    <div class="news-content">
      <div class="news-header text-center">
        <h1>Tin tức & Thông báo</h1>
        <p>Cập nhật những thông tin mới nhất từ hệ thống SportGo</p>
      </div>

      <!-- Filters & Search -->
      <div class="filters-section">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Tìm kiếm bài viết..." 
            @keyup.enter="handleSearch"
          />
        </div>
        
        <div class="category-filters">
          <button 
            class="cat-btn" 
            :class="{ active: !selectedCategory }" 
            @click="setCategory('')"
          >
            Tất cả
          </button>
          <button 
            v-for="cat in categories" 
            :key="cat"
            class="cat-btn" 
            :class="{ active: selectedCategory === cat }" 
            @click="setCategory(cat)"
          >
            {{ cat }}
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Đang tải tin tức...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <button class="btn primary" @click="fetchPosts">Thử lại</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="posts.length === 0" class="empty-state">
        <i class="fas fa-newspaper"></i>
        <p>Không tìm thấy bài viết nào phù hợp.</p>
      </div>

      <!-- News Grid -->
      <div v-else class="news-grid">
        <div v-for="post in posts" :key="post.id" class="news-card" @click="goToDetail(post.slug)">
          <div class="news-image">
            <img :src="post.thumbnail_path || 'https://placehold.co/600x400/e2e8f0/475569?text=SportGo+News'" :alt="post.title" />
            <span v-if="post.category" class="news-badge">{{ post.category }}</span>
          </div>
          <div class="news-info">
            <div class="news-meta">
              <span class="date"><i class="far fa-calendar-alt"></i> {{ formatDate(post.published_at) }}</span>
              <span class="views"><i class="far fa-eye"></i> {{ post.view_count }}</span>
            </div>
            <h3 class="news-title">{{ post.title }}</h3>
            <p class="news-summary">{{ post.short_description || 'Không có tóm tắt.' }}</p>
            <div class="news-readmore">
              Đọc tiếp <i class="fas fa-arrow-right"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="pagination-wrapper">
        <PaginationBar 
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          @page-changed="changePage"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import PublicNavbar from '@/components/PublicNavbar.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { api } from '@/services/api.js';

const router = useRouter();

const posts = ref([]);
const loading = ref(true);
const error = ref(null);
const searchQuery = ref('');
const selectedCategory = ref('');
const categories = ref(['Thông báo', 'Hướng dẫn', 'Tin tức', 'Sự kiện']);

const pagination = ref({
  current_page: 1,
  last_page: 1
});

const fetchPosts = async (page = 1) => {
  loading.value = true;
  error.value = null;
  try {
    const params = new URLSearchParams({
      page,
      per_page: 9
    });
    
    if (searchQuery.value) params.append('keyword', searchQuery.value);
    if (selectedCategory.value) params.append('category', selectedCategory.value);

    const data = await api(`/api/system-news?${params.toString()}`);
    posts.value = data.data;
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page
    };
  } catch (err) {
    error.value = 'Đã có lỗi xảy ra khi tải tin tức.';
    console.error(err);
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  fetchPosts(1);
};

const setCategory = (cat) => {
  selectedCategory.value = cat;
  fetchPosts(1);
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchPosts(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

const goToDetail = (slug) => {
  router.push(`/news/${slug}`);
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  }).format(new Date(dateString));
};

onMounted(() => {
  fetchPosts();
});
</script>

<style scoped>
.news-page-container {
  background-color: #f8fafc;
  min-height: 100vh;
  padding-top: 64px;
}

.news-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 24px;
}

.news-header {
  margin-bottom: 40px;
}

.news-header h1 {
  font-size: 36px;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 12px;
}

.news-header p {
  font-size: 16px;
  color: #64748b;
}

.filters-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 32px;
}

@media (min-width: 768px) {
  .filters-section {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.search-box {
  position: relative;
  width: 100%;
  max-width: 400px;
}

.search-box i {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.search-box input {
  width: 100%;
  padding: 12px 16px 12px 40px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  outline: none;
  font-size: 15px;
  transition: all 0.2s;
  background: white;
}

.search-box input:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.category-filters {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.cat-btn {
  padding: 8px 16px;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.cat-btn:hover {
  background: #f1f5f9;
  color: #334155;
}

.cat-btn.active {
  background: #10b981;
  color: white;
  border-color: #10b981;
}

/* Grid */
.news-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 24px;
}

@media (min-width: 768px) {
  .news-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .news-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.news-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
}

.news-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.news-card:hover .news-image img {
  transform: scale(1.05);
}

.news-card:hover .news-readmore {
  color: #10b981;
}

.news-card:hover .news-readmore i {
  transform: translateX(4px);
}

.news-image {
  position: relative;
  height: 200px;
  overflow: hidden;
}

.news-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}

.news-badge {
  position: absolute;
  top: 16px;
  left: 16px;
  background: rgba(16, 185, 129, 0.9);
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  backdrop-filter: blur(4px);
}

.news-info {
  padding: 24px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.news-meta {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: #94a3b8;
  margin-bottom: 12px;
}

.news-meta i {
  margin-right: 4px;
}

.news-title {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 12px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.news-summary {
  font-size: 14px;
  color: #64748b;
  line-height: 1.6;
  margin-bottom: 24px;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.news-readmore {
  font-size: 14px;
  font-weight: 600;
  color: #334155;
  display: flex;
  align-items: center;
  transition: color 0.2s;
}

.news-readmore i {
  margin-left: 8px;
  font-size: 12px;
  transition: transform 0.2s;
}

/* States */
.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 80px 20px;
  color: #64748b;
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

.error-state i, .empty-state i {
  font-size: 48px;
  color: #cbd5e1;
  margin-bottom: 16px;
}

.error-state button {
  margin-top: 16px;
}

.pagination-wrapper {
  margin-top: 40px;
  display: flex;
  justify-content: center;
}
</style>

<style>
/* Dark Mode Support (Unscoped) */
.dark .news-page-container {
  background-color: #09090b !important;
}
.dark .news-header h1 {
  color: #ffffff !important;
}
.dark .news-header p,
.dark .news-summary {
  color: #a1a1aa !important;
}
.dark .search-box input {
  background: #18181b !important;
  border-color: #27272a !important;
  color: #ffffff !important;
}
.dark .cat-btn {
  background: #18181b !important;
  border-color: #27272a !important;
  color: #a1a1aa !important;
}
.dark .cat-btn:hover {
  background: #27272a !important;
  color: #ffffff !important;
}
.dark .cat-btn.active {
  background: #10b981 !important;
  color: white !important;
  border-color: #10b981 !important;
}
.dark .news-card {
  background: #18181b !important;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5) !important;
}
.dark .news-title {
  color: #ffffff !important;
}
.dark .news-readmore {
  color: #a1a1aa !important;
}
.dark .news-card:hover .news-readmore {
  color: #10b981 !important;
}
.dark .loading-state, 
.dark .error-state, 
.dark .empty-state {
  color: #a1a1aa !important;
}
.dark .loading-state .spinner {
  border-color: #27272a !important;
  border-top-color: #10b981 !important;
}
</style>

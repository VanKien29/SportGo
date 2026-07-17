<template>
  <div class="sg-client-page sg-news-page">
    <PublicNavbar />

    <main class="sg-client-shell">
      <header class="sg-news-heading">
        <span class="sg-client-eyebrow">Trung tâm nội dung SportGo</span>
        <h1>Tin tức &amp; hướng dẫn</h1>
        <p>Cập nhật thông báo hệ thống, hướng dẫn đặt sân và hoạt động thể thao mới nhất.</p>
      </header>

      <section class="sg-client-card sg-news-filter" aria-label="Tìm và lọc tin tức">
        <form @submit.prevent="fetchPosts(1)">
          <AppIcon name="search" size="18" />
          <label class="sg-client-sr-only" for="news-search">Tìm kiếm bài viết</label>
          <input id="news-search" v-model.trim="searchQuery" type="search" placeholder="Tìm theo tiêu đề bài viết" />
          <button class="sg-client-button sg-client-button--primary" type="submit">Tìm kiếm</button>
        </form>
        <div class="sg-news-categories" aria-label="Danh mục tin tức">
          <button type="button" :class="{ active: !selectedCategory }" @click="setCategory('')">Tất cả</button>
          <button
            v-for="category in categories"
            :key="category.value"
            type="button"
            :class="{ active: selectedCategory === category.value }"
            @click="setCategory(category.value)"
          >
            {{ category.label }}
          </button>
        </div>
      </section>

      <div v-if="loading" class="sg-client-state">
        <span class="sg-community-spinner" aria-hidden="true"></span>
        <strong>Đang tải tin tức...</strong>
      </div>
      <div v-else-if="error" class="sg-client-state sg-community-state-error" role="alert">
        <AppIcon name="alert" size="30" />
        <strong>Không thể tải tin tức</strong>
        <p>{{ error }}</p>
        <button class="sg-client-button" type="button" @click="fetchPosts(pagination.current_page)">Thử lại</button>
      </div>
      <div v-else-if="!posts.length" class="sg-client-state">
        <AppIcon name="newspaper" size="32" />
        <strong>Chưa có bài viết phù hợp</strong>
        <p>Hãy thử một từ khóa hoặc danh mục khác.</p>
      </div>

      <section v-else class="sg-news-grid" aria-label="Danh sách tin tức">
        <article v-for="post in posts" :key="post.id" class="sg-client-card sg-news-card">
          <button class="sg-news-media" type="button" @click="openPost(post.slug)">
            <img
              v-if="post.thumbnail_path && !brokenImages.has(post.id)"
              :src="mediaUrl(post.thumbnail_path)"
              :alt="post.title"
              @error="hideBrokenImage(post.id)"
            />
            <span v-else class="sg-news-media-placeholder">
              <AppIcon name="newspaper" size="34" />
              SportGo
            </span>
            <span v-if="post.category" class="sg-client-status">{{ categoryLabel(post.category) }}</span>
          </button>
          <div class="sg-news-card-copy">
            <div class="sg-news-meta">
              <span><AppIcon name="calendar" size="14" /> {{ formatDate(post.published_at) }}</span>
              <span><AppIcon name="eye" size="14" /> {{ Number(post.view_count || 0).toLocaleString('vi-VN') }}</span>
            </div>
            <h2>{{ post.title }}</h2>
            <p>{{ post.short_description || 'Thông tin mới từ hệ thống SportGo.' }}</p>
            <button type="button" @click="openPost(post.slug)">
              Đọc bài viết <AppIcon name="chevronRight" size="16" />
            </button>
          </div>
        </article>
      </section>

      <PaginationBar v-if="pagination.last_page > 1" :meta="pagination" @change="changePage" />
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PaginationBar from '@/components/PaginationBar.vue';
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
  { value: 'announcement', label: 'Thông báo' },
  { value: 'guide', label: 'Hướng dẫn' },
  { value: 'news', label: 'Tin tức' },
  { value: 'event', label: 'Sự kiện' },
];
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
  return {
    announcement: 'Thông báo',
    guide: 'Hướng dẫn',
    news: 'Tin tức',
    event: 'Sự kiện',
  }[value] || value;
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

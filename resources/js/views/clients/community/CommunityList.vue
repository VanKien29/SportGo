<template>
  <div class="community-page">
    <PublicNavbar />

    <main class="community-container">
      <header class="community-header">
        <div>
          <span class="eyebrow">Cộng đồng SportGo</span>
          <h1>Kết nối người yêu thể thao</h1>
          <p>Chia sẻ kinh nghiệm, tìm người chơi cùng và khám phá hoạt động tại các cụm sân.</p>
        </div>
        <button v-if="isPlayer" type="button" class="create-primary" @click="showMeetupModal = true">
          <AppIcon name="users" size="17" />
          Tạo bài giao lưu
        </button>
      </header>

      <section v-if="matchmakingPosts.length || matchmakingLoading" class="meetup-section">
        <header class="section-header">
          <div>
            <h2>Tìm người ghép kèo</h2>
            <p>Các buổi chơi sắp tới vẫn còn chỗ.</p>
          </div>
        </header>

        <div v-if="matchmakingLoading" class="horizontal-state">
          <span class="spinner" aria-hidden="true"></span>
          Đang tải bài giao lưu...
        </div>
        <div v-else class="meetup-scroll">
          <article v-for="post in matchmakingPosts" :key="post.id" class="meetup-card">
            <header>
              <button type="button" class="author" @click="goToUser(post.author?.id)">
                <span class="author-avatar">
                  <img v-if="post.author?.avatar" :src="assetUrl(post.author.avatar)" :alt="post.author.name" />
                  <span v-else>{{ initial(post.author?.name) }}</span>
                </span>
                <span><strong>{{ post.author?.name || 'Người dùng' }}</strong><small>{{ timeAgo(post.created_at) }}</small></span>
              </button>
              <span class="needed">Cần {{ post.needed_players }} người</span>
            </header>
            <div class="meetup-facts">
              <span><AppIcon name="mapPin" size="15" /> {{ post.booking?.venue_name || 'Cụm sân' }}</span>
              <span><AppIcon name="clock" size="15" /> {{ formatDate(post.booking?.date) }} · {{ post.booking?.time }}</span>
            </div>
            <p v-if="post.description">{{ post.description }}</p>
            <footer>
              <button
                v-if="!isOwnPost(post)"
                type="button"
                :disabled="joiningPostId === post.id || Boolean(post.user_status)"
                @click="joinMatchmaking(post)"
              >
                {{ joinLabel(post) }}
              </button>
              <router-link v-else :to="`/matchmaking-posts/${post.id}/manage`">Quản lý yêu cầu</router-link>
            </footer>
          </article>
        </div>
      </section>

      <section class="community-feed">
        <header class="section-header feed-header">
          <div>
            <h2>Bài viết cộng đồng</h2>
            <p>Nội dung đã được xuất bản công khai.</p>
          </div>
          <form class="search-form" @submit.prevent="fetchPosts(1)">
            <AppIcon name="search" size="17" />
            <input v-model.trim="searchQuery" type="search" placeholder="Tìm theo tiêu đề" />
            <button type="submit">Tìm</button>
          </form>
        </header>

        <div class="category-list" aria-label="Chủ đề bài viết">
          <button type="button" :class="{ active: !selectedCategory }" @click="setCategory('')">Tất cả</button>
          <button
            v-for="category in categories"
            :key="category"
            type="button"
            :class="{ active: selectedCategory === category }"
            @click="setCategory(category)"
          >
            {{ category }}
          </button>
        </div>

        <div v-if="loading" class="feed-state">
          <span class="spinner" aria-hidden="true"></span>
          <p>Đang tải bài viết...</p>
        </div>
        <div v-else-if="error" class="feed-state error" role="alert">
          <AppIcon name="alert" size="26" />
          <strong>Không thể tải bài viết</strong>
          <p>{{ error }}</p>
          <button type="button" @click="fetchPosts(pagination.current_page)">Thử lại</button>
        </div>
        <div v-else-if="!posts.length" class="feed-state empty">
          <AppIcon name="newspaper" size="28" />
          <strong>Chưa có bài viết phù hợp</strong>
          <p>Hãy thử chủ đề hoặc từ khóa khác.</p>
        </div>
        <div v-else class="post-grid">
          <article v-for="post in posts" :key="post.id" class="post-card">
            <button type="button" class="post-image" @click="goToDetail(post.slug)">
              <img :src="postImage(post)" :alt="post.title" @error="handlePostImageError" />
            </button>
            <div class="post-copy">
              <div class="post-meta">
                <span><AppIcon name="calendar" size="14" /> {{ formatDate(post.published_at) }}</span>
                <span><AppIcon name="eye" size="14" /> {{ post.view_count || 0 }}</span>
              </div>
              <h3>{{ post.title }}</h3>
              <p>{{ post.short_description || 'Bài chia sẻ từ cộng đồng SportGo.' }}</p>
              <button type="button" @click="goToDetail(post.slug)">
                Đọc bài viết <AppIcon name="chevronRight" size="15" />
              </button>
            </div>
          </article>
        </div>

        <PaginationBar v-if="pagination.last_page > 1" :meta="pagination" @change="changePage" />
      </section>
    </main>

    <MeetupPostModal :is-open="showMeetupModal" @close="showMeetupModal = false" @success="handlePostCreated" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import MeetupPostModal from '@/components/MeetupPostModal.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import { getAuth } from '@/stores/auth.js';

const router = useRouter();
const toast = useToast();
const user = getAuth();
const isPlayer = computed(() => user?.role_group === 'user');
const showMeetupModal = ref(false);
const posts = ref([]);
const loading = ref(true);
const error = ref('');
const searchQuery = ref('');
const selectedCategory = ref('');
const categories = ['Kinh nghiệm', 'Giao lưu', 'Hỏi đáp', 'Sự kiện', 'Cụm sân mới', 'Ưu đãi'];
const pagination = ref({ current_page: 1, last_page: 1 });
const matchmakingPosts = ref([]);
const matchmakingLoading = ref(true);
const joiningPostId = ref(null);
const fallbackPostImage = '/images/home/badminton-cover.webp';
async function fetchMatchmakingPosts() {
  matchmakingLoading.value = true;
  try {
    const response = await api('/api/matchmaking-posts');
    matchmakingPosts.value = Array.isArray(response.data) ? response.data : [];
  } catch {
    matchmakingPosts.value = [];
  } finally {
    matchmakingLoading.value = false;
  }
}

async function fetchPosts(page = 1) {
  loading.value = true;
  error.value = '';
  try {
    const params = new URLSearchParams({ page: String(page), per_page: '9' });
    if (searchQuery.value) params.set('keyword', searchQuery.value);
    if (selectedCategory.value) params.set('category', selectedCategory.value);
    const response = await api(`/api/venue-posts?${params.toString()}`);
    posts.value = Array.isArray(response.data) ? response.data : [];
    pagination.value = {
      current_page: Number(response.current_page || page),
      last_page: Number(response.last_page || 1),
    };
  } catch (requestError) {
    posts.value = [];
    error.value = requestError.message || 'Không thể tải bài viết cộng đồng.';
  } finally {
    loading.value = false;
  }
}

async function joinMatchmaking(post) {
  if (!user) {
    toast.info('Vui lòng đăng nhập để tham gia giao lưu.');
    router.push({ name: 'login' });
    return;
  }
  if (!isPlayer.value) {
    toast.info('Chức năng này dành cho tài khoản người dùng.');
    return;
  }
  joiningPostId.value = post.id;
  try {
    await api(`/api/matchmaking-posts/${post.id}/join`, { method: 'POST' });
    post.user_status = 'pending';
    toast.success('Đã gửi yêu cầu tham gia.');
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể gửi yêu cầu tham gia.');
  } finally {
    joiningPostId.value = null;
  }
}

function joinLabel(post) {
  if (joiningPostId.value === post.id) return 'Đang gửi...';
  return {
    pending: 'Đang chờ duyệt',
    approved: 'Đã tham gia',
    rejected: 'Đã bị từ chối',
  }[post.user_status] || 'Xin tham gia';
}

function isOwnPost(post) {
  return String(user?.id || '') === String(post.author?.id || '');
}

function setCategory(category) {
  selectedCategory.value = category;
  fetchPosts(1);
}

function changePage(page) {
  fetchPosts(page);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToDetail(slug) {
  router.push({ name: 'community-post-detail', params: { slug } });
}

function goToUser(id) {
  if (id) router.push(`/user/${id}`);
}

function handlePostCreated() {
  showMeetupModal.value = false;
  fetchPosts(1);
  fetchMatchmakingPosts();
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
}

function timeAgo(value) {
  if (!value) return '';
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
  if (seconds < 60) return 'Vừa xong';
  if (seconds < 3600) return `${Math.floor(seconds / 60)} phút trước`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} giờ trước`;
  return formatDate(value);
}

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

function postImage(post) {
  const media = Array.isArray(post?.media)
    ? post.media.find((item) => item.collection === 'thumbnail') || post.media[0]
    : null;
  const path = media?.url || media?.file_url || media?.full_url || media?.file_path || media?.path
    || post?.thumbnail || post?.image_path || post?.cover_image;
  return assetUrl(path) || fallbackPostImage;
}

function handlePostImageError(event) {
  const image = event.currentTarget;
  if (image?.getAttribute('src') !== fallbackPostImage) {
    image.src = fallbackPostImage;
  }
}

function initial(name) {
  return String(name || 'N').charAt(0).toUpperCase();
}

onMounted(() => {
  fetchPosts();
  fetchMatchmakingPosts();
});
</script>

<style scoped>
.community-page {
  min-height: 100vh;
  background: var(--admin-bg);
  color: var(--admin-text);
}

.community-container {
  width: min(100%, 1200px);
  margin: 0 auto;
  padding: 94px 24px 56px;
}

.community-header,
.section-header,
.feed-header,
.create-primary,
.meetup-card > header,
.author,
.author-avatar,
.meetup-facts,
.meetup-facts span,
.meetup-card footer,
.post-meta,
.post-copy button,
.search-form,
.category-list {
  display: flex;
  align-items: center;
}

.community-header {
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.eyebrow {
  display: block;
  margin-bottom: 6px;
  color: var(--admin-primary);
  font-size: var(--admin-font-size-xs);
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.community-header h1 {
  margin: 0;
  font-size: calc(var(--admin-font-size-2xl) + 8px);
}

.community-header p,
.section-header p {
  margin: 7px 0 0;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
}

.create-primary {
  flex: 0 0 auto;
  justify-content: center;
  gap: 7px;
  min-height: 42px;
  padding: 9px 14px;
  border: 1px solid var(--admin-primary);
  border-radius: var(--admin-radius);
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  font-size: var(--admin-font-size-base);
  font-weight: 600;
  cursor: pointer;
}

.meetup-section,
.community-feed {
  padding: 18px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
}

.meetup-section {
  margin-bottom: 18px;
}

.section-header {
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}

.section-header h2 {
  margin: 0;
  font-size: var(--admin-font-size-xl);
}

.meetup-scroll {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 6px;
  scrollbar-width: thin;
}

.meetup-card {
  width: min(88vw, 350px);
  flex: 0 0 350px;
  padding: 14px;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-bg-soft);
}

.meetup-card > header {
  justify-content: space-between;
  gap: 10px;
}

.author {
  min-width: 0;
  gap: 9px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--admin-text);
  text-align: left;
  cursor: pointer;
}

.author-avatar {
  width: 36px;
  height: 36px;
  flex: 0 0 auto;
  justify-content: center;
  overflow: hidden;
  border-radius: 50%;
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  font-weight: 600;
}

.author-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.author > span:last-child {
  display: grid;
  min-width: 0;
  gap: 2px;
}

.author strong {
  overflow: hidden;
  font-size: var(--admin-font-size-base);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.author small,
.meetup-facts,
.post-meta {
  color: var(--admin-muted);
  font-size: var(--admin-font-size-xs);
}

.needed {
  flex: 0 0 auto;
  padding: 5px 8px;
  border-radius: 999px;
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
  font-size: var(--admin-font-size-xs);
  font-weight: 600;
}

.meetup-facts {
  display: grid;
  gap: 7px;
  margin: 13px 0;
}

.meetup-facts span {
  gap: 5px;
}

.meetup-card > p {
  display: -webkit-box;
  overflow: hidden;
  margin: 0;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
  line-height: 1.5;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.meetup-card footer {
  justify-content: flex-end;
  margin-top: 13px;
  padding-top: 12px;
  border-top: 1px solid var(--admin-border-soft);
}

.meetup-card footer button,
.meetup-card footer a {
  width: 100%;
  min-height: 38px;
  padding: 9px 12px;
  border: 1px solid var(--admin-primary);
  border-radius: var(--admin-radius);
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  font-size: var(--admin-font-size-sm);
  font-weight: 600;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
}

.meetup-card footer button:disabled {
  border-color: var(--admin-border);
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
  cursor: not-allowed;
}

.feed-header {
  align-items: flex-end;
}

.search-form {
  width: min(100%, 390px);
  gap: 7px;
  padding: 4px 4px 4px 10px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-muted);
}

.search-form input {
  min-width: 0;
  flex: 1;
  border: 0;
  background: transparent;
  color: var(--admin-text);
  font: inherit;
  font-size: var(--admin-font-size-base);
  outline: none;
}

.search-form button,
.feed-state button {
  min-height: 34px;
  padding: 7px 11px;
  border: 1px solid var(--admin-primary);
  border-radius: var(--admin-radius);
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  font-size: var(--admin-font-size-sm);
  font-weight: 600;
  cursor: pointer;
}

.category-list {
  flex-wrap: wrap;
  gap: 7px;
  margin-bottom: 16px;
}

.category-list button {
  padding: 7px 11px;
  border: 1px solid var(--admin-border);
  border-radius: 999px;
  background: var(--admin-surface);
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
  font-weight: 500;
  cursor: pointer;
}

.category-list button.active {
  border-color: var(--admin-primary);
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

.post-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.post-card {
  overflow: hidden;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-bg-soft);
}

.post-image {
  display: block;
  width: 100%;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  padding: 0;
  border: 0;
  background: var(--admin-surface-muted);
  cursor: pointer;
}

.post-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.post-copy {
  padding: 14px;
}

.post-meta {
  gap: 12px;
}

.post-meta span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.post-copy h3 {
  display: -webkit-box;
  overflow: hidden;
  margin: 9px 0 6px;
  font-size: var(--admin-font-size-lg);
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.post-copy p {
  display: -webkit-box;
  min-height: 42px;
  overflow: hidden;
  margin: 0;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
  line-height: 1.5;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.post-copy button {
  gap: 4px;
  margin-top: 12px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--admin-primary-dark);
  font-size: var(--admin-font-size-sm);
  font-weight: 600;
  cursor: pointer;
}

.feed-state,
.horizontal-state {
  display: grid;
  place-items: center;
  align-content: center;
  gap: 9px;
  color: var(--admin-muted);
  text-align: center;
}

.feed-state {
  min-height: 290px;
}

.horizontal-state {
  min-height: 130px;
}

.feed-state p {
  margin: 0;
  font-size: var(--admin-font-size-base);
}

.feed-state.error {
  color: var(--admin-danger-text);
}

.spinner {
  width: 26px;
  height: 26px;
  border: 3px solid var(--admin-border);
  border-top-color: var(--admin-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 900px) {
  .post-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .community-container {
    padding: 84px 16px 40px;
  }

  .community-header,
  .feed-header {
    align-items: stretch;
    flex-direction: column;
  }

  .create-primary,
  .search-form {
    width: 100%;
  }

  .meetup-card {
    flex-basis: 88vw;
  }

  .post-grid {
    grid-template-columns: 1fr;
  }
}
</style>

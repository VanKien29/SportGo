<template>
  <div class="news-page-container">
    <PublicNavbar />

    <div class="news-content">
      <div class="news-header text-center">
        <h1>Cộng đồng</h1>
        <p>Giao lưu và chia sẻ kinh nghiệm từ các cụm sân</p>
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

      <!-- Matchmaking Posts Section -->
      <div v-if="matchmakingPosts.length > 0" class="matchmaking-section">
        <h2 class="section-title">Tìm người ghép kèo</h2>
        <div class="matchmaking-scroll">
          <div class="matchmaking-card" v-for="mPost in matchmakingPosts" :key="mPost.id">
            <div class="m-card-header">
              <div class="m-author cursor-pointer hover:opacity-80 transition-opacity" @click.stop="mPost.author && mPost.author.id ? $router.push('/user/' + mPost.author.id) : null" :title="mPost.author && mPost.author.id ? 'Xem trang cá nhân' : ''">
                <div class="m-avatar" v-if="mPost.author.avatar">
                  <img :src="getPostImage({image_path: mPost.author.avatar})" alt="avatar">
                </div>
                <div class="m-avatar placeholder" v-else>
                  {{ mPost.author.name.charAt(0).toUpperCase() }}
                </div>
                <span class="m-name">{{ mPost.author.name }}</span>
              </div>
              <span class="m-time">{{ formatTimeAgo(mPost.created_at) }}</span>
            </div>
            <div class="m-card-body">
              <div class="m-info-row">
                <i class="fas fa-map-marker-alt"></i>
                <span>{{ mPost.booking.venue_name }}</span>
              </div>
              <div class="m-info-row">
                <i class="far fa-clock"></i>
                <span>{{ mPost.booking.time }} - {{ formatDate(mPost.booking.date) }}</span>
              </div>
              <div class="m-needed">
                <span>Cần thêm:</span>
                <strong>{{ mPost.needed_players }} người</strong>
              </div>
              <p class="m-desc" v-if="mPost.description">{{ mPost.description }}</p>
            </div>
            <div class="m-card-footer">
              <button 
                class="btn-join" 
                v-if="!user || user.id !== mPost.author.id"
                :disabled="joiningPostId === mPost.id || mPost.user_status"
                :class="{
                  'btn-joined': mPost.user_status === 'pending',
                  'btn-approved': mPost.user_status === 'approved',
                  'btn-rejected': mPost.user_status === 'rejected'
                }"
                @click="joinMatchmaking(mPost)"
              >
                {{ 
                  joiningPostId === mPost.id ? 'Đang gửi...' : 
                  (mPost.user_status === 'pending' ? 'Đã gửi yêu cầu' :
                  (mPost.user_status === 'approved' ? 'Đã tham gia' :
                  (mPost.user_status === 'rejected' ? 'Bị từ chối' : 'Tham gia ngay'))) 
                }}
              </button>
              <button 
                class="btn-manage" 
                v-else
                @click="$router.push(`/matchmaking-posts/${mPost.id}/manage`)"
              >
                Quản lý yêu cầu
              </button>

            </div>
          </div>
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
      <div v-else-if="posts.length > 0" class="news-grid">
        <div v-for="post in posts" :key="post.id" class="news-card" @click="goToDetail(post.slug)">
          <div class="news-image">
            <img :src="getPostImage(post)" :alt="post.title" />
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
          :meta="pagination"
          @change="changePage"
        />
      </div>
    </div>
    
    <!-- Floating Action Button for Create Post -->
    <div v-if="isLoggedIn" class="floating-add-container">
      <FloatMenuButton 
        :actions="floatMenuActions"
        @action="handleFloatMenuAction"
      />
    </div>
    
    <!-- Modal -->
    <CommunityPostModal 
      :isOpen="showCommunityModal" 
      @close="showCommunityModal = false" 
      @success="handlePostCreated" 
    />
    
    <MeetupPostModal 
      :isOpen="showMeetupModal" 
      @close="showMeetupModal = false" 
      @success="handlePostCreated" 
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import PublicNavbar from '@/components/PublicNavbar.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import CommunityPostModal from '@/components/CommunityPostModal.vue';
import MeetupPostModal from '@/components/MeetupPostModal.vue';
import FloatMenuButton from '@/components/FloatMenuButton.vue';
import { api } from '@/services/api.js';
import { getAuth } from '@/stores/auth.js';
import { useToast } from 'vue-toastification';

const router = useRouter();
const toast = useToast();

const user = getAuth();
const isLoggedIn = computed(() => !!user);
const userInitial = computed(() => user?.fullName?.charAt(0)?.toUpperCase() || '?');
const userFirstName = computed(() => {
  if (!user || !user.fullName) return '';
  const parts = user.fullName.split(' ');
  return parts[parts.length - 1];
});

const showCommunityModal = ref(false);
const showMeetupModal = ref(false);

const floatMenuActions = [
  { key: 'community', label: 'Tạo bài viết cộng đồng', icon: 'edit' },
  { key: 'meetup', label: 'Tạo bài giao lưu tại sân', icon: 'users' },
];

const handleFloatMenuAction = (actionKey) => {
  if (actionKey === 'community') {
    showCommunityModal.value = true;
  } else if (actionKey === 'meetup') {
    showMeetupModal.value = true;
  }
};

const posts = ref([]);
const loading = ref(true);
const error = ref(null);
const searchQuery = ref('');
const selectedCategory = ref('');
const categories = ref(['Kinh nghiệm', 'Giao lưu', 'Hỏi đáp', 'Sự kiện', 'Cụm sân mới', 'Ưu đãi']);

const pagination = ref({
  current_page: 1,
  last_page: 1
});

const matchmakingPosts = ref([]);
const joiningPostId = ref(null);

const fetchMatchmakingPosts = async () => {
  try {
    const data = await api('/api/matchmaking-posts');
    matchmakingPosts.value = data.data; // Server already maps user_status
  } catch (err) {
    console.error('Failed to load matchmaking posts', err);
  }
};

const joinMatchmaking = async (post) => {
  if (!isLoggedIn.value) {
    toast.info('Vui lòng đăng nhập để tham gia ghép kèo!');
    router.push('/login');
    return;
  }

  joiningPostId.value = post.id;
  try {
    await api(`/api/matchmaking-posts/${post.id}/join`, {
      method: 'POST'
    });
    post.user_status = 'pending';
    toast.success('Đã gửi yêu cầu ghép kèo thành công! Chủ bài viết sẽ liên hệ với bạn nếu đồng ý.');
  } catch (err) {
    toast.error(err.message || 'Đã có lỗi xảy ra. Vui lòng thử lại.');
  } finally {
    joiningPostId.value = null;
  }
};

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

    const data = await api(`/api/venue-posts?${params.toString()}`);
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
  // Use window.location.href to guarantee the page loads and bypass any Vue Router silent transition failures
  window.location.href = `/community/${slug}`;
};

const handlePostCreated = () => {
  fetchPosts(1);
  fetchMatchmakingPosts();
};

const formatTimeAgo = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  const now = new Date();
  const diffInSeconds = Math.floor((now - date) / 1000);
  
  if (diffInSeconds < 60) return 'Vừa xong';
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} phút trước`;
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} giờ trước`;
  return formatDate(dateString);
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  }).format(new Date(dateString));
};

const getPostImage = (post) => {
  const media = Array.isArray(post.media) ? post.media.find((item) => item.collection === "thumbnail") || post.media[0] : null;
  let path = null;
  if (media) {
    path = media.url || media.file_url || media.full_url || media.file_path || media.path;
  }
  path = path || post.thumbnail || post.image_path || post.cover_image;
  if (!path) return 'https://placehold.co/600x400/e2e8f0/475569?text=SportGo+Community';
  if (/^https?:\/\//.test(path)) return path;
  if (path.startsWith('/')) return path;
  return `/storage/${path}`;
};

onMounted(() => {
  fetchPosts();
  fetchMatchmakingPosts();
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
  padding: 40px 20px;
}

/* Matchmaking Section */
.matchmaking-section {
  margin-bottom: 40px;
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.section-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 20px;
}

.matchmaking-scroll {
  display: flex;
  gap: 20px;
  overflow-x: auto;
  padding-bottom: 12px;
  scrollbar-width: thin;
}

.matchmaking-scroll::-webkit-scrollbar {
  height: 6px;
}
.matchmaking-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.matchmaking-card {
  min-width: 320px;
  flex-shrink: 0;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
  transition: transform 0.2s, box-shadow 0.2s;
}

.matchmaking-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
  border-color: #cbd5e1;
}

.m-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.m-author {
  display: flex;
  align-items: center;
  gap: 10px;
}

.m-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
}

.m-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.m-avatar.placeholder {
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.1rem;
}

.m-name {
  font-weight: 600;
  font-size: 0.95rem;
  color: #334155;
}

.m-time {
  font-size: 0.8rem;
  color: #64748b;
}

.m-card-body {
  margin-bottom: 16px;
}

.m-info-row {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #475569;
  font-size: 0.9rem;
  margin-bottom: 8px;
}

.m-info-row i {
  color: #3b82f6;
  width: 16px;
  text-align: center;
}

.m-needed {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #eff6ff;
  color: #1d4ed8;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.9rem;
  margin-top: 4px;
}

.m-desc {
  margin-top: 12px;
  font-size: 0.9rem;
  color: #64748b;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.m-card-footer {
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}

.btn-join {
  width: 100%;
  padding: 10px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-join:hover:not(:disabled) {
  background: #2563eb;
}

.btn-join:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-join.btn-joined {
  background: #10b981; /* Green */
}

.btn-join.btn-approved {
  background: #3b82f6; /* Blue but disabled */
  opacity: 0.8;
}

.btn-join.btn-rejected {
  background: #ef4444; /* Red */
  opacity: 0.7;
}

.btn-manage {
  width: 100%;
  padding: 10px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-manage:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.approved-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  border-top: 1px solid #f1f5f9;
  padding-top: 12px;
}

.action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
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
  display: flex;
  align-items: center;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: white;
  padding-left: 16px;
  overflow: hidden;
  transition: all 0.2s;
}

.search-box:focus-within {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.search-box i {
  color: #94a3b8;
  font-size: 15px;
  flex-shrink: 0;
}

.search-box input {
  width: 100%;
  padding: 12px 16px;
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  font-size: 15px;
  background: transparent !important;
  appearance: none !important;
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

.cat-btn.never-hover-class-placeholder {
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

.news-card.never-hover-class-placeholder {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.news-card.never-hover-class-placeholder .news-image img {
  transform: scale(1.05);
}

.news-card.never-hover-class-placeholder .news-readmore {
  color: #10b981;
}

.news-card.never-hover-class-placeholder .news-readmore i {
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
.dark .cat-btn.never-hover-class-placeholder {
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
.dark .news-card.never-hover-class-placeholder .news-readmore {
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

.floating-add-container {
  position: fixed;
  bottom: 40px;
  right: 40px;
  z-index: 99;
}

@media (max-width: 768px) {
  .floating-add-container {
    bottom: 24px;
    right: 24px;
  }
}
</style>

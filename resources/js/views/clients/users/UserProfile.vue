<template>
  <div class="user-profile-page">
    <PublicNavbar />

    <div class="up-container">
      
      <!-- Profile Header (Loading) -->
      <div v-if="profileLoading" class="up-card loading-skeleton">
        <div class="skeleton-avatar"></div>
        <div class="skeleton-line w-1/3 mt-4"></div>
        <div class="skeleton-line w-1/4 mt-2"></div>
      </div>
      
      <!-- Profile Header (Error) -->
      <div v-else-if="profileError" class="up-card error">
        <i class="fas fa-exclamation-triangle"></i>
        <p>{{ profileError }}</p>
      </div>
      
      <!-- Profile Header (Data) -->
      <div v-else-if="profileData" class="up-card">
        <div class="up-avatar-wrap">
          <img v-if="profileData.user.avatar_url" :src="profileData.user.avatar_url" :alt="profileData.user.full_name">
          <div v-else class="up-avatar-placeholder">
            {{ (profileData.user.full_name || profileData.user.username || 'U').charAt(0).toUpperCase() }}
          </div>
        </div>
        <h1 class="up-name">{{ profileData.user.full_name || profileData.user.username || 'Người dùng' }}</h1>
        <p class="up-joined"><i class="far fa-calendar-check"></i> Tham gia từ: {{ formatDateOnly(profileData.user.created_at) }}</p>
        
        <div class="up-stats">
          <div class="up-stat-item">
            <span class="up-stat-val">{{ profileData.stats.total_community_posts }}</span>
            <span class="up-stat-lbl">Bài chia sẻ</span>
          </div>
          <div class="up-stat-item">
            <span class="up-stat-val">{{ profileData.stats.total_matchmaking_posts }}</span>
            <span class="up-stat-lbl">Ghép kèo</span>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="up-tabs">
        <button class="up-tab-btn" :class="{ active: activeTab === 'community' }" @click="activeTab = 'community'">
          <i class="fas fa-newspaper"></i> Bài chia sẻ
        </button>
        <button class="up-tab-btn" :class="{ active: activeTab === 'matchmaking' }" @click="activeTab = 'matchmaking'">
          <i class="fas fa-users"></i> Bài ghép kèo
        </button>
      </div>

      <!-- Tab Content: Community Posts -->
      <div v-if="activeTab === 'community'" class="up-content-section">
        <div v-if="loading" class="up-loading">
          <div class="spinner"></div>
          <p>Đang tải bài viết...</p>
        </div>
        <div v-else-if="error" class="up-error">
          <p>{{ error }}</p>
          <button @click="fetchPosts">Thử lại</button>
        </div>
        <div v-else-if="posts.length === 0" class="up-empty">
          <i class="fas fa-file-alt"></i>
          <p>Người dùng này chưa có bài chia sẻ nào.</p>
        </div>
        <div v-else class="news-grid">
          <div v-for="post in posts" :key="post.id" class="news-card" @click="goToDetail(post.slug)">
            <div class="news-image">
              <img :src="getPostImage(post)" :alt="post.title" @error="$event.target.src = 'https://placehold.co/600x400/e2e8f0/475569?text=SportGo+Community'" />
            </div>
            <div class="news-info">
              <div class="news-meta">
                <span class="date"><i class="far fa-calendar-alt"></i> {{ formatDateOnly(post.published_at) }}</span>
                <span class="views"><i class="far fa-eye"></i> {{ post.view_count || 0 }}</span>
              </div>
              <h3 class="news-title">{{ post.title }}</h3>
              <p class="news-summary">{{ post.short_description || 'Không có tóm tắt.' }}</p>
              <div class="news-readmore">
                Đọc tiếp <i class="fas fa-arrow-right"></i>
              </div>
            </div>
          </div>
        </div>
        <div v-if="pagination.last_page > 1" class="up-pagination">
          <PaginationBar :meta="pagination" @change="changePage" />
        </div>
      </div>

      <!-- Tab Content: Matchmaking Posts -->
      <div v-if="activeTab === 'matchmaking'" class="up-content-section">
        <div v-if="loadingMatchmaking" class="up-loading">
          <div class="spinner"></div>
          <p>Đang tải bài ghép kèo...</p>
        </div>
        <div v-else-if="matchmakingPosts.length === 0" class="up-empty">
          <i class="fas fa-users-slash"></i>
          <p>Người dùng này chưa có bài ghép kèo nào đang mở.</p>
        </div>
        <div v-else class="news-grid">
          <div class="m-card" v-for="mPost in matchmakingPosts" :key="mPost.id">
            <div class="m-card-header">
              <div class="author-info">
                <div class="author-avatar">
                  <img v-if="mPost.author.avatar" :src="mPost.author.avatar" :alt="mPost.author.name">
                  <div v-else class="avatar-placeholder-small">
                    {{ (mPost.author.name || 'U').charAt(0).toUpperCase() }}
                  </div>
                </div>
                <div class="author-meta">
                  <span class="author-name">{{ mPost.author.name }}</span>
                  <span class="post-time">{{ formatTimeAgo(mPost.created_at) }}</span>
                </div>
              </div>
            </div>
            
            <div class="m-card-body">
              <h3 class="m-title">{{ mPost.title }}</h3>
              
              <div class="m-details">
                <div class="m-detail-item">
                  <i class="fas fa-map-marker-alt text-red-500"></i>
                  <span>{{ mPost.booking?.venue_name || 'Sân chưa xác định' }}</span>
                </div>
                <div class="m-detail-item">
                  <i class="far fa-clock text-blue-500"></i>
                  <span>{{ mPost.booking?.time }} - {{ formatDateOnly(mPost.booking?.date) }}</span>
                </div>
              </div>

              <div class="m-status-box" :class="mPost.status === 'open' ? 'open' : 'closed'">
                <span v-if="mPost.status === 'open'">Cần thêm: <strong>{{ mPost.needed_players }} người</strong></span>
                <span v-else>Đã đủ người</span>
              </div>
              
              <p class="m-description">{{ mPost.description }}</p>
            </div>
            
            <div class="m-card-footer">
              <button 
                class="btn-join" 
                :disabled="mPost.status !== 'open' || mPost.user_status"
                v-if="!user || user.id !== mPost.author.id"
                @click="joinMatchmaking(mPost)"
              >
                {{ 
                  mPost.status !== 'open' ? 'Đã đủ người' : 
                  (mPost.user_status === 'pending' ? 'Đang chờ duyệt' : 
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

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import PublicNavbar from '@/components/PublicNavbar.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { api } from '@/services/api.js';
import { getAuth } from '@/stores/auth.js';
import { useToast } from 'vue-toastification';

const router = useRouter();
const route = useRoute();
const toast = useToast();

const user = getAuth();
const isLoggedIn = computed(() => !!user);
const targetUserId = ref(route.params.id);
const activeTab = ref('community');

// Profile Data
const profileData = ref(null);
const profileLoading = ref(true);
const profileError = ref(null);

// Matchmaking
const matchmakingPosts = ref([]);
const loadingMatchmaking = ref(true);

// Community Posts
const posts = ref([]);
const loading = ref(true);
const error = ref(null);
const pagination = ref({ current_page: 1, last_page: 1 });

const fetchProfile = async () => {
  profileLoading.value = true;
  profileError.value = null;
  try {
    const res = await api(`/api/users/${targetUserId.value}/profile`);
    profileData.value = res.data;
  } catch (err) {
    profileError.value = 'Không thể tải thông tin người dùng.';
  } finally {
    profileLoading.value = false;
  }
};

const fetchMatchmakingPosts = async () => {
  loadingMatchmaking.value = true;
  try {
    const data = await api(`/api/matchmaking-posts?author_id=${targetUserId.value}`);
    matchmakingPosts.value = data.data; 
  } catch (err) {
    console.error('Failed to load matchmaking posts', err);
  } finally {
    loadingMatchmaking.value = false;
  }
};

const fetchPosts = async (page = 1) => {
  loading.value = true;
  error.value = null;
  try {
    const data = await api(`/api/venue-posts?page=${page}&author_id=${targetUserId.value}`);
    posts.value = data.data;
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page
    };
  } catch (err) {
    error.value = 'Không thể tải danh sách bài viết. Vui lòng thử lại sau.';
  } finally {
    loading.value = false;
  }
};

const joinMatchmaking = async (post) => {
  if (!isLoggedIn.value) {
    toast.info('Vui lòng đăng nhập để tham gia ghép kèo!');
    router.push('/login');
    return;
  }
  try {
    await api(`/api/matchmaking-posts/${post.id}/join`, { method: 'POST' });
    toast.success('Đã gửi yêu cầu tham gia! Chờ chủ bài duyệt.');
    await fetchMatchmakingPosts();
  } catch (err) {
    toast.error(err.response?.data?.message || 'Có lỗi xảy ra khi xin tham gia.');
  }
};

const changePage = (page) => {
  fetchPosts(page);
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const goToDetail = (slug) => {
  window.location.href = `/community/${slug}`;
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

const formatDateOnly = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('vi-VN');
};

const formatTimeAgo = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  const now = new Date();
  const diffInSeconds = Math.floor((now - date) / 1000);
  
  if (diffInSeconds < 60) return `${diffInSeconds} giây trước`;
  const diffInMinutes = Math.floor(diffInSeconds / 60);
  if (diffInMinutes < 60) return `${diffInMinutes} phút trước`;
  const diffInHours = Math.floor(diffInMinutes / 60);
  if (diffInHours < 24) return `${diffInHours} giờ trước`;
  const diffInDays = Math.floor(diffInHours / 24);
  return `${diffInDays} ngày trước`;
};

watch(() => route.params.id, (newId) => {
  if (newId) {
    targetUserId.value = newId;
    fetchProfile();
    fetchMatchmakingPosts();
    fetchPosts(1);
  }
});

onMounted(() => {
  fetchProfile();
  fetchMatchmakingPosts();
  fetchPosts();
});
</script>

<style scoped>
.user-profile-page {
  background-color: #f8fafc;
  min-height: 100vh;
  padding-bottom: 60px;
}
.up-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px 16px;
}

/* Profile Card */
.up-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
  padding: 40px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  margin-bottom: 32px;
}
.up-avatar-wrap {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  border: 4px solid #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  margin-bottom: 20px;
  background: #10b981;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 48px;
  font-weight: 700;
}
.up-avatar-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.up-name {
  font-size: 26px;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 8px 0;
}
.up-joined {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 24px 0;
  display: flex;
  align-items: center;
  gap: 6px;
}
.up-stats {
  display: flex;
  gap: 40px;
  border-top: 1px solid #f1f5f9;
  padding-top: 24px;
  width: 100%;
  max-width: 400px;
  justify-content: center;
}
.up-stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.up-stat-val {
  font-size: 24px;
  font-weight: 800;
  color: #1e293b;
}
.up-stat-lbl {
  font-size: 13px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
  margin-top: 4px;
}

/* Tabs */
.up-tabs {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-bottom: 32px;
}
.up-tab-btn {
  padding: 12px 28px;
  background: white;
  border: 1px solid #e2e8f0;
  color: #64748b;
  font-weight: 600;
  font-size: 15px;
  border-radius: 30px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.up-tab-btn:hover {
  background: #f8fafc;
  color: #334155;
}
.up-tab-btn.active {
  background: #10b981;
  color: white;
  border-color: #10b981;
  box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
}

/* Content States */
.up-loading {
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f1f5f9;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.up-empty {
  text-align: center;
  padding: 80px 20px;
  background: white;
  border-radius: 16px;
  border: 1px dashed #cbd5e1;
  color: #64748b;
}
.up-empty i {
  font-size: 48px;
  color: #cbd5e1;
  margin-bottom: 16px;
}
.up-error {
  text-align: center;
  padding: 60px 20px;
  background: #fef2f2;
  border-radius: 16px;
  color: #ef4444;
}
.up-error button {
  margin-top: 16px;
  padding: 8px 24px;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}

/* Skeleton */
.loading-skeleton {
  align-items: center;
}
.skeleton-avatar {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: #e2e8f0;
  animation: pulse 1.5s infinite;
  margin-bottom: 20px;
}
.skeleton-line {
  height: 16px;
  background: #e2e8f0;
  border-radius: 8px;
  animation: pulse 1.5s infinite;
}
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }

/* Grid (Reused but isolated) */
.news-grid { 
  display: grid; 
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
  gap: 24px; 
}
.news-card { 
  background: white; 
  border-radius: 16px; 
  overflow: hidden; 
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
  transition: transform 0.2s, box-shadow 0.2s; 
  cursor: pointer; 
  display: flex; 
  flex-direction: column; 
  border: 1px solid #f1f5f9;
}
.news-card:hover { 
  transform: translateY(-4px); 
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); 
}
.news-image { height: 200px; width: 100%; overflow: hidden; background: #f8fafc; }
.news-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.news-card:hover .news-image img { transform: scale(1.05); }
.news-info { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
.news-meta { display: flex; justify-content: space-between; font-size: 13px; color: #64748b; margin-bottom: 12px; }
.news-title { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0 0 12px 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.news-title:hover { color: #10b981; }
.news-summary { font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 16px; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.news-readmore { font-size: 14px; font-weight: 600; color: #10b981; display: flex; align-items: center; gap: 4px; margin-top: auto; }

/* Matchmaking Card */
.m-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
}
.m-card-header { padding: 16px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.author-info { display: flex; align-items: center; gap: 12px; }
.author-avatar { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #10b981; }
.author-avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-placeholder-small { width: 100%; height: 100%; background: #10b981; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; }
.author-meta { display: flex; flex-direction: column; }
.author-name { font-weight: 600; font-size: 14px; color: #1e293b; }
.post-time { font-size: 12px; color: #64748b; }
.m-card-body { padding: 16px; flex-grow: 1; }
.m-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0; line-height: 1.4; }
.m-details { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.m-detail-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; }
.m-status-box { padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 12px; display: inline-block;}
.m-status-box.open { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.m-status-box.closed { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.m-description { font-size: 14px; color: #64748b; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.m-card-footer { padding: 16px; border-top: 1px solid #f1f5f9; margin-top: auto; }
.btn-join { width: 100%; padding: 12px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-join:hover:not(:disabled) { background: #059669; }
.btn-join:disabled { background: #6ee7b7; cursor: not-allowed; }
.btn-manage { width: 100%; padding: 12px; background: white; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-manage:hover { background: #f8fafc; color: #1e293b; }

.up-pagination { margin-top: 32px; display: flex; justify-content: center; }
</style>

<template>
  <div class="user-profile-page">
    <PublicNavbar />

    <main class="profile-container">
      <div v-if="profileLoading" class="page-state">
        <span class="spinner" aria-hidden="true"></span>
        <p>Đang tải hồ sơ cộng đồng...</p>
      </div>

      <div v-else-if="profileError" class="page-state error" role="alert">
        <AppIcon name="alert" size="28" />
        <strong>Không thể hiển thị hồ sơ</strong>
        <p>{{ profileError }}</p>
        <button type="button" @click="loadProfile">Thử lại</button>
      </div>

      <template v-else-if="profileData">
        <section class="profile-hero">
          <div class="profile-avatar">
            <img v-if="profileAvatar" :src="profileAvatar" :alt="displayName" />
            <span v-else>{{ initial(displayName) }}</span>
          </div>
          <div class="public-profile-copy">
            <span class="eyebrow">Thành viên SportGo</span>
            <h1>{{ displayName }}</h1>
            <p><AppIcon name="calendar" size="15" /> Tham gia từ {{ formatDate(profileData.user.created_at) }}</p>
          </div>
          <button v-if="canReportUser" type="button" class="report-user" @click="openUserReport">
            <AppIcon name="alert" size="16" />
            Báo cáo tài khoản
          </button>
        </section>

        <section class="profile-stats" aria-label="Thống kê hoạt động">
          <article>
            <strong>{{ profileData.stats.total_community_posts }}</strong>
            <span>Bài chia sẻ công khai</span>
          </article>
          <article>
            <strong>{{ profileData.stats.total_matchmaking_posts }}</strong>
            <span>Bài giao lưu đã tạo</span>
          </article>
        </section>

        <nav class="profile-tabs" aria-label="Nội dung hồ sơ">
          <button type="button" :class="{ active: activeTab === 'community' }" @click="activeTab = 'community'">
            <AppIcon name="newspaper" size="16" />
            Bài chia sẻ
          </button>
          <button type="button" :class="{ active: activeTab === 'matchmaking' }" @click="activeTab = 'matchmaking'">
            <AppIcon name="users" size="16" />
            Bài giao lưu
          </button>
        </nav>

        <section v-if="activeTab === 'community'" class="content-panel">
          <div v-if="communityLoading" class="content-state">
            <span class="spinner" aria-hidden="true"></span>
            Đang tải bài chia sẻ...
          </div>
          <div v-else-if="communityError" class="content-state error">
            <span>{{ communityError }}</span>
            <button type="button" @click="loadCommunityPosts(page)">Thử lại</button>
          </div>
          <div v-else-if="!communityPosts.length" class="content-state empty">
            <AppIcon name="newspaper" size="28" />
            <strong>Chưa có bài chia sẻ công khai</strong>
          </div>
          <div v-else class="post-grid">
            <article v-for="post in communityPosts" :key="post.id" class="post-card">
              <button type="button" class="post-image" @click="goToPost(post.slug)">
                <img :src="postImage(post)" :alt="post.title" />
              </button>
              <div class="post-copy">
                <div class="post-meta">
                  <span><AppIcon name="calendar" size="14" /> {{ formatDate(post.published_at) }}</span>
                  <span><AppIcon name="eye" size="14" /> {{ post.view_count || 0 }}</span>
                </div>
                <h2>{{ post.title }}</h2>
                <p>{{ post.short_description || 'Bài chia sẻ từ cộng đồng SportGo.' }}</p>
                <button type="button" @click="goToPost(post.slug)">Xem bài viết <AppIcon name="chevronRight" size="15" /></button>
              </div>
            </article>
          </div>

          <PaginationBar v-if="lastPage > 1" :meta="paginationMeta" @change="changePage" />
        </section>

        <section v-else class="content-panel">
          <div v-if="matchmakingLoading" class="content-state">
            <span class="spinner" aria-hidden="true"></span>
            Đang tải bài giao lưu...
          </div>
          <div v-else-if="matchmakingError" class="content-state error">
            <span>{{ matchmakingError }}</span>
            <button type="button" @click="loadMatchmakingPosts">Thử lại</button>
          </div>
          <div v-else-if="!matchmakingPosts.length" class="content-state empty">
            <AppIcon name="users" size="28" />
            <strong>Không có bài giao lưu đang mở</strong>
          </div>
          <div v-else class="meetup-list">
            <article v-for="post in matchmakingPosts" :key="post.id" class="meetup-card">
              <header>
                <div>
                  <span class="eyebrow">Tìm người chơi cùng</span>
                  <h2>{{ post.title }}</h2>
                </div>
                <span class="needed-count">Cần {{ post.needed_players }} người</span>
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
                  class="join-button"
                  :disabled="Boolean(post.user_status) || joiningId === post.id"
                  @click="joinMatchmaking(post)"
                >
                  {{ joinLabel(post) }}
                </button>
                <router-link v-else :to="`/matchmaking-posts/${post.id}/manage`">Quản lý yêu cầu</router-link>
              </footer>
            </article>
          </div>
        </section>
      </template>
    </main>

    <ReportModal
      :is-open="showReportModal"
      target-type="user"
      :target-id="profileData?.user?.id || ''"
      :target-name="displayName"
      @close="showReportModal = false"
      @success="onReportSuccess"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import ReportModal from '@/components/ReportModal.vue';
import { api } from '@/services/api.js';
import { getAuth } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const viewer = getAuth();
const activeTab = ref('community');
const profileData = ref(null);
const profileLoading = ref(true);
const profileError = ref('');
const communityPosts = ref([]);
const communityLoading = ref(true);
const communityError = ref('');
const page = ref(1);
const lastPage = ref(1);
const matchmakingPosts = ref([]);
const matchmakingLoading = ref(true);
const matchmakingError = ref('');
const joiningId = ref(null);
const showReportModal = ref(false);

const displayName = computed(() => profileData.value?.user?.full_name || profileData.value?.user?.username || 'Người dùng');
const profileAvatar = computed(() => assetUrl(profileData.value?.user?.avatar_url));
const canReportUser = computed(() => String(viewer?.id || '') !== String(profileData.value?.user?.id || ''));
const paginationMeta = computed(() => ({ current_page: page.value, last_page: lastPage.value }));

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

function initial(name) {
  return String(name || 'N').charAt(0).toUpperCase();
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  return new Date(value).toLocaleDateString('vi-VN');
}

function postImage(post) {
  const media = Array.isArray(post?.media)
    ? post.media.find((item) => item.collection === 'thumbnail') || post.media[0]
    : null;
  const path = media?.url || media?.file_url || media?.full_url || media?.file_path
    || post?.thumbnail || post?.image_path || post?.cover_image;
  return assetUrl(path) || '/images/home/badminton-cover.webp';
}

async function loadProfile() {
  profileLoading.value = true;
  profileError.value = '';
  try {
    const response = await api(`/api/users/${route.params.id}/profile`);
    profileData.value = response.data;
  } catch (error) {
    profileData.value = null;
    profileError.value = error.message || 'Không thể tải thông tin người dùng.';
  } finally {
    profileLoading.value = false;
  }
}

async function loadCommunityPosts(targetPage = 1) {
  communityLoading.value = true;
  communityError.value = '';
  try {
    const response = await api(`/api/venue-posts?page=${targetPage}&author_id=${route.params.id}`);
    communityPosts.value = Array.isArray(response.data) ? response.data : [];
    page.value = Number(response.current_page || targetPage);
    lastPage.value = Number(response.last_page || 1);
  } catch (error) {
    communityPosts.value = [];
    communityError.value = error.message || 'Không thể tải bài chia sẻ.';
  } finally {
    communityLoading.value = false;
  }
}

async function loadMatchmakingPosts() {
  matchmakingLoading.value = true;
  matchmakingError.value = '';
  try {
    const response = await api(`/api/matchmaking-posts?author_id=${route.params.id}`);
    matchmakingPosts.value = Array.isArray(response.data) ? response.data : [];
  } catch (error) {
    matchmakingPosts.value = [];
    matchmakingError.value = error.message || 'Không thể tải bài giao lưu.';
  } finally {
    matchmakingLoading.value = false;
  }
}

function changePage(targetPage) {
  loadCommunityPosts(targetPage);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToPost(slug) {
  router.push({ name: 'community-post-detail', params: { slug } });
}

function isOwnPost(post) {
  return String(viewer?.id || '') === String(post?.author?.id || '');
}

function joinLabel(post) {
  if (joiningId.value === post.id) return 'Đang gửi yêu cầu...';
  return {
    pending: 'Đang chờ duyệt',
    approved: 'Đã tham gia',
    rejected: 'Đã bị từ chối',
  }[post.user_status] || 'Xin tham gia';
}

async function joinMatchmaking(post) {
  if (!viewer) {
    toast.info('Vui lòng đăng nhập để tham gia giao lưu.');
    router.push({ name: 'login' });
    return;
  }
  if (viewer.role_group !== 'user') {
    toast.info('Chức năng này dành cho tài khoản người dùng.');
    return;
  }
  joiningId.value = post.id;
  try {
    await api(`/api/matchmaking-posts/${post.id}/join`, { method: 'POST' });
    toast.success('Đã gửi yêu cầu tham gia.');
    await loadMatchmakingPosts();
  } catch (error) {
    toast.error(error.message || 'Không thể gửi yêu cầu tham gia.');
  } finally {
    joiningId.value = null;
  }
}

function openUserReport() {
  if (!viewer) {
    toast.info('Vui lòng đăng nhập để gửi báo cáo.');
    router.push({ name: 'login' });
    return;
  }
  if (viewer.role_group !== 'user') {
    toast.info('Chức năng này dành cho tài khoản người dùng.');
    return;
  }
  showReportModal.value = true;
}

function onReportSuccess() {
  showReportModal.value = false;
  toast.success('Báo cáo đã được ghi nhận để SportGo kiểm tra.');
}

async function loadPage() {
  await Promise.all([loadProfile(), loadCommunityPosts(1), loadMatchmakingPosts()]);
}

watch(() => route.params.id, loadPage);
onMounted(loadPage);
</script>

<style scoped>
.user-profile-page {
  min-height: 100vh;
  background: var(--admin-bg);
  color: var(--admin-text);
}

.profile-container {
  width: min(100%, 1120px);
  margin: 0 auto;
  padding: 92px 24px 56px;
}

.profile-hero,
.public-profile-copy p,
.report-user,
.profile-tabs,
.profile-tabs button,
.post-meta,
.post-copy button,
.meetup-card header,
.meetup-facts,
.meetup-facts span,
.meetup-card footer,
.booking-summary {
  display: flex;
  align-items: center;
}

.profile-hero {
  gap: 20px;
  padding: 22px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
}

.profile-avatar {
  display: grid;
  width: 92px;
  height: 92px;
  flex: 0 0 auto;
  overflow: hidden;
  place-items: center;
  border: 3px solid var(--admin-primary-soft);
  border-radius: 50%;
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  font-size: var(--admin-font-size-2xl);
  font-weight: 600;
}

.profile-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.public-profile-copy {
  min-width: 0;
  flex: 1;
}

.eyebrow {
  display: block;
  margin-bottom: 5px;
  color: var(--admin-primary);
  font-size: var(--admin-font-size-xs);
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.public-profile-copy h1 {
  margin: 0;
  font-size: var(--admin-font-size-2xl);
}

.public-profile-copy p {
  gap: 6px;
  margin: 7px 0 0;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
}

.report-user,
.content-state button,
.page-state button {
  justify-content: center;
  gap: 6px;
  min-height: 38px;
  padding: 8px 12px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
  font-weight: 500;
  cursor: pointer;
}

.report-user:hover {
  border-color: var(--admin-danger);
  color: var(--admin-danger-text);
}

.profile-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 14px 0;
}

.profile-stats article {
  display: grid;
  gap: 4px;
  padding: 16px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
}

.profile-stats strong {
  font-size: var(--admin-font-size-2xl);
}

.profile-stats span {
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
}

.profile-tabs {
  gap: 8px;
  margin: 22px 0 12px;
  border-bottom: 1px solid var(--admin-border);
}

.profile-tabs button {
  gap: 7px;
  padding: 11px 14px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
  font-weight: 500;
  cursor: pointer;
}

.profile-tabs button.active {
  border-bottom-color: var(--admin-primary);
  color: var(--admin-primary-dark);
}

.content-panel {
  padding: 18px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
}

.content-state,
.page-state {
  display: grid;
  place-items: center;
  align-content: center;
  gap: 9px;
  color: var(--admin-muted);
  text-align: center;
}

.content-state {
  min-height: 250px;
}

.page-state {
  min-height: 430px;
}

.page-state p,
.content-state span {
  margin: 0;
  font-size: var(--admin-font-size-base);
}

.page-state.error,
.content-state.error {
  color: var(--admin-danger-text);
}

.post-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.post-card,
.meetup-card {
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
  color: var(--admin-muted);
  font-size: var(--admin-font-size-xs);
}

.post-meta span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.post-copy h2,
.meetup-card h2 {
  margin: 10px 0 6px;
  font-size: var(--admin-font-size-lg);
}

.post-copy p,
.meetup-card > p {
  margin: 0;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
  line-height: 1.5;
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

.meetup-list {
  display: grid;
  gap: 12px;
}

.meetup-card {
  padding: 16px;
}

.meetup-card header {
  justify-content: space-between;
  gap: 14px;
}

.meetup-card h2 {
  margin: 0;
}

.needed-count {
  flex: 0 0 auto;
  padding: 6px 9px;
  border-radius: 999px;
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
  font-size: var(--admin-font-size-sm);
  font-weight: 600;
}

.meetup-facts {
  flex-wrap: wrap;
  gap: 8px 16px;
  margin: 12px 0;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
}

.meetup-facts span {
  gap: 5px;
}

.meetup-card footer {
  justify-content: flex-end;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--admin-border-soft);
}

.meetup-card footer button,
.meetup-card footer a {
  min-height: 38px;
  padding: 9px 13px;
  border: 1px solid var(--admin-primary);
  border-radius: var(--admin-radius);
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  font-size: var(--admin-font-size-sm);
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.meetup-card footer button:disabled {
  border-color: var(--admin-border);
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
  cursor: not-allowed;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--admin-border);
  border-top-color: var(--admin-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 720px) {
  .profile-container {
    padding: 84px 16px 40px;
  }

  .profile-hero {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .report-user {
    width: 100%;
  }

  .post-grid {
    grid-template-columns: 1fr;
  }
}
</style>

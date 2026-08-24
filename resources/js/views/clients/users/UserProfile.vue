<template>
  <div class="user-profile-page sg-client-page">
    <PublicNavbar />

    <main class="sg-client-reading-shell profile-shell">
      <nav class="sg-community-breadcrumb" aria-label="Điều hướng hồ sơ cộng đồng">
        <router-link :to="{ name: 'ClientCommunityList' }">
          <AppIcon name="chevronLeft" size="16" />
          Quay lại cộng đồng
        </router-link>
      </nav>

      <div v-if="profileLoading" class="sg-client-state page-state" aria-live="polite">
        <span class="spinner" aria-hidden="true"></span>
        <p>Đang tải hồ sơ cộng đồng...</p>
      </div>

      <div v-else-if="profileError" class="sg-client-state page-state page-state--error" role="alert">
        <AppIcon name="alert" size="28" />
        <strong>Không thể hiển thị hồ sơ</strong>
        <p>{{ profileError }}</p>
        <button type="button" class="sg-client-button" @click="loadPage">Thử lại</button>
      </div>

      <template v-else-if="profileData">
        <section class="profile-hero sg-client-card">
          <div class="profile-avatar" aria-hidden="true">
            <img v-if="profileAvatar" :src="profileAvatar" :alt="displayName" />
            <span v-else>{{ initial(displayName) }}</span>
          </div>

          <div class="public-profile-copy">
            <span class="sg-client-eyebrow">Thành viên SportGo</span>
            <h1 class="client-author-line">
              {{ displayName }}
              <ClientAuthorBadges :badges="profileData.user.author_badges" />
            </h1>
            <p>
              <AppIcon name="calendar" size="15" />
              Tham gia từ {{ formatDate(profileData.user.created_at) }}
            </p>
          </div>

          <button
            v-if="canReportUser"
            type="button"
            class="sg-client-button report-user"
            @click="openUserReport"
          >
            <AppIcon name="alert" size="16" />
            Báo cáo tài khoản
          </button>
        </section>

        <section class="profile-stats sg-client-card" aria-label="Thống kê hoạt động">
          <article>
            <strong>{{ communityPostCount }}</strong>
            <span>Bài chia sẻ công khai</span>
          </article>
          <article>
            <strong>{{ profileData.stats?.total_matchmaking_posts ?? 0 }}</strong>
            <span>Bài giao lưu đã tạo</span>
          </article>
        </section>

        <nav class="profile-tabs" aria-label="Nội dung hồ sơ">
          <button
            type="button"
            :class="{ active: activeTab === 'community' }"
            :aria-current="activeTab === 'community' ? 'page' : undefined"
            @click="activeTab = 'community'"
          >
            <AppIcon name="newspaper" size="17" />
            Bài chia sẻ
          </button>
          <button
            type="button"
            :class="{ active: activeTab === 'matchmaking' }"
            :aria-current="activeTab === 'matchmaking' ? 'page' : undefined"
            @click="activeTab = 'matchmaking'"
          >
            <AppIcon name="users" size="17" />
            Bài giao lưu
          </button>
        </nav>

        <section v-if="activeTab === 'community'" class="profile-content" aria-label="Bài chia sẻ công khai">
          <div v-if="communityLoading" class="sg-client-state content-state" aria-live="polite">
            <span class="spinner" aria-hidden="true"></span>
            <span>Đang tải bài chia sẻ...</span>
          </div>

          <div v-else-if="communityError" class="sg-client-state content-state content-state--error" role="alert">
            <AppIcon name="alert" size="24" />
            <span>{{ communityError }}</span>
            <button type="button" class="sg-client-button" @click="loadCommunityPosts(page)">Thử lại</button>
          </div>

          <div v-else-if="!communityPosts.length" class="sg-client-state content-state">
            <AppIcon name="newspaper" size="28" />
            <strong>Chưa có bài chia sẻ công khai</strong>
            <span>Khi thành viên đăng bài công khai, nội dung sẽ xuất hiện tại đây.</span>
          </div>

          <div v-else class="post-feed">
            <article v-for="post in communityPosts" :key="post.id" class="post-card sg-client-card">
              <header class="post-header">
                <div class="post-author-avatar" aria-hidden="true">
                  <img v-if="postAuthorAvatar(post)" :src="postAuthorAvatar(post)" :alt="postAuthorName(post)" />
                  <span v-else>{{ initial(postAuthorName(post)) }}</span>
                </div>
                <div class="post-author-copy">
                  <strong class="client-author-line">
                    {{ postAuthorName(post) }}
                    <ClientAuthorBadges :badges="post.author_badges" />
                  </strong>
                  <span>
                    {{ formatDateTime(post.published_at || post.created_at) }}
                    <template v-if="post.venue_cluster?.name"> · {{ post.venue_cluster.name }}</template>
                  </span>
                </div>
                <button
                  type="button"
                  class="sg-client-icon-button post-open-button"
                  :aria-label="`Mở bài viết ${post.title || ''}`"
                  @click="goToPost(post.slug || post.id)"
                >
                  <AppIcon name="chevronRight" size="19" />
                </button>
              </header>

              <div class="post-copy">
                <span class="sg-client-status">{{ postKindLabel(post) }}</span>
                <h2 v-if="post.feed_type !== 'community_post' && post.title">{{ post.title }}</h2>
                <p>{{ postExcerpt(post) }}</p>
              </div>

              <button
                v-if="postMedia(post)"
                type="button"
                class="post-media"
                :aria-label="`Xem chi tiết ${post.title || 'bài viết'}`"
                @click="goToPost(post.slug || post.id)"
              >
                <img
                  :src="postMedia(post)"
                  :alt="post.title || 'Ảnh bài viết'"
                  loading="lazy"
                  @error="markMediaBroken(post.id)"
                />
              </button>

              <footer class="post-footer">
                <span><AppIcon name="heart" size="17" /> {{ postLikeCount(post) }} lượt thích</span>
                <span><AppIcon name="messageCircle" size="17" /> {{ postCommentCount(post) }} bình luận</span>
                <button type="button" @click="goToPost(post.slug || post.id)">
                  Xem và bình luận
                  <AppIcon name="chevronRight" size="16" />
                </button>
              </footer>
            </article>
          </div>

          <PaginationBar v-if="lastPage > 1" :meta="paginationMeta" @change="changePage" />
        </section>

        <section v-else class="profile-content" aria-label="Bài giao lưu">
          <div v-if="matchmakingLoading" class="sg-client-state content-state" aria-live="polite">
            <span class="spinner" aria-hidden="true"></span>
            <span>Đang tải bài giao lưu...</span>
          </div>

          <div v-else-if="matchmakingError" class="sg-client-state content-state content-state--error" role="alert">
            <AppIcon name="alert" size="24" />
            <span>{{ matchmakingError }}</span>
            <button type="button" class="sg-client-button" @click="loadMatchmakingPosts(matchmakingPage)">Thử lại</button>
          </div>

          <div v-else-if="!matchmakingPosts.length" class="sg-client-state content-state">
            <AppIcon name="users" size="28" />
            <strong>Không có bài giao lưu đang mở</strong>
            <span>Các bài đã đủ người, đã đóng hoặc đã hết thời gian sẽ không còn hiển thị.</span>
          </div>

          <div v-else class="meetup-list">
            <article v-for="post in matchmakingPosts" :key="post.id" class="meetup-card sg-client-card">
              <header>
                <div>
                  <span class="sg-client-eyebrow">Tìm người chơi cùng</span>
                  <h2>{{ post.title }}</h2>
                </div>
                <span class="sg-client-status">Cần {{ post.needed_players }} người</span>
              </header>

              <div class="meetup-facts">
                <span><AppIcon name="mapPin" size="16" /> {{ post.booking?.venue_name || 'Cụm sân' }}</span>
                <span><AppIcon name="clock" size="16" /> {{ formatDate(post.booking?.date) }} · {{ post.booking?.time || 'Chưa rõ giờ' }}</span>
              </div>
              <p v-if="post.description">{{ post.description }}</p>

              <footer>
                <span v-if="post.user_status" class="request-status">{{ joinLabel(post) }}</span>
                <button
                  v-if="!isOwnPost(post)"
                  type="button"
                  class="sg-client-button sg-client-button--primary"
                  :disabled="Boolean(post.user_status) || joiningId === post.id"
                  @click="joinMatchmaking(post)"
                >
                  <AppIcon name="users" size="16" />
                  {{ joinLabel(post) }}
                </button>
                <router-link
                  v-else
                  class="sg-client-button sg-client-button--primary"
                  :to="`/matchmaking-posts/${post.id}/manage`"
                >
                  Quản lý yêu cầu
                  <AppIcon name="chevronRight" size="16" />
                </router-link>
              </footer>
            </article>
          </div>

          <PaginationBar
            v-if="matchmakingLastPage > 1"
            :meta="matchmakingPaginationMeta"
            @change="changeMatchmakingPage"
          />
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
import ClientAuthorBadges from '@/components/ClientAuthorBadges.vue';
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
const communityTotal = ref(0);
const page = ref(1);
const lastPage = ref(1);
const matchmakingPosts = ref([]);
const matchmakingLoading = ref(true);
const matchmakingError = ref('');
const matchmakingPage = ref(1);
const matchmakingLastPage = ref(1);
const joiningId = ref(null);
const showReportModal = ref(false);
const brokenMedia = ref(new Set());

const displayName = computed(() => profileData.value?.user?.full_name || profileData.value?.user?.username || 'Người dùng');
const profileAvatar = computed(() => assetUrl(profileData.value?.user?.avatar_url));
const canReportUser = computed(() => String(viewer?.id || '') !== String(profileData.value?.user?.id || ''));
const paginationMeta = computed(() => ({ current_page: page.value, last_page: lastPage.value }));
const communityPostCount = computed(() => Math.max(
  Number(profileData.value?.stats?.total_community_posts || 0),
  communityTotal.value,
));
const matchmakingPaginationMeta = computed(() => ({
  current_page: matchmakingPage.value,
  last_page: matchmakingLastPage.value,
}));

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

function initial(name) {
  return String(name || 'N').trim().charAt(0).toUpperCase();
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 'Chưa rõ ngày' : date.toLocaleDateString('vi-VN');
}

function formatDateTime(value) {
  if (!value) return 'Chưa rõ thời gian';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Chưa rõ thời gian';
  return date.toLocaleString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function plainText(value) {
  return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function postExcerpt(post) {
  return plainText(post?.short_description || post?.content || post?.title)
    || 'Bài chia sẻ từ cộng đồng SportGo.';
}

function postAuthorName(post) {
  return post?.author?.full_name || post?.author?.username || post?.author?.name || displayName.value;
}

function postAuthorAvatar(post) {
  return assetUrl(post?.author?.avatar_url || post?.author?.avatar);
}

function postMedia(post) {
  if (brokenMedia.value.has(String(post?.id))) return '';
  const media = Array.isArray(post?.media)
    ? post.media.find((item) => item.collection === 'thumbnail') || post.media[0]
    : null;
  const path = media?.url || media?.file_url || media?.full_url || media?.file_path
    || post?.thumbnail || post?.image_path || post?.cover_image;
  return assetUrl(path);
}

function markMediaBroken(postId) {
  brokenMedia.value = new Set([...brokenMedia.value, String(postId)]);
}

function postKindLabel(post) {
  return post?.feed_type === 'community_post'
    ? 'Bài chia sẻ'
    : post?.venue_cluster?.name || 'Cộng đồng SportGo';
}

function postLikeCount(post) {
  return Number(post?.like_count || post?.likes_count || 0);
}

function postCommentCount(post) {
  return Number(post?.comment_count || post?.comments_count || 0);
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
    const response = await api(`/api/venue-posts?page=${targetPage}&author_id=${route.params.id}&feed_type=community_post`);
    communityPosts.value = Array.isArray(response.data) ? response.data : [];
    communityTotal.value = Number(response.total ?? communityPosts.value.length);
    page.value = Number(response.current_page || targetPage);
    lastPage.value = Number(response.last_page || 1);
  } catch (error) {
    communityPosts.value = [];
    communityTotal.value = 0;
    communityError.value = error.message || 'Không thể tải bài chia sẻ.';
  } finally {
    communityLoading.value = false;
  }
}

async function loadMatchmakingPosts(targetPage = 1) {
  matchmakingLoading.value = true;
  matchmakingError.value = '';
  try {
    const response = await api(`/api/matchmaking-posts?page=${targetPage}&author_id=${route.params.id}`);
    matchmakingPosts.value = Array.isArray(response.data) ? response.data : [];
    matchmakingPage.value = Number(response.current_page || targetPage);
    matchmakingLastPage.value = Number(response.last_page || 1);
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

function changeMatchmakingPage(targetPage) {
  loadMatchmakingPosts(targetPage);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToPost(slug) {
  if (!slug) return;
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
    cancelled: 'Đã rút yêu cầu',
  }[post.user_status] || 'Xin tham gia';
}

async function joinMatchmaking(post) {
  if (!viewer) {
    toast.info('Vui lòng đăng nhập để tham gia giao lưu.');
    router.push({ name: 'login', query: { redirect: route.fullPath } });
    return;
  }

  joiningId.value = post.id;
  try {
    await api(`/api/matchmaking-posts/${post.id}/join`, { method: 'POST' });
    toast.success('Đã gửi yêu cầu tham gia.');
    await loadMatchmakingPosts(matchmakingPage.value);
  } catch (error) {
    toast.error(error.message || 'Không thể gửi yêu cầu tham gia.');
  } finally {
    joiningId.value = null;
  }
}

function openUserReport() {
  if (!viewer) {
    toast.info('Vui lòng đăng nhập để gửi báo cáo.');
    router.push({ name: 'login', query: { redirect: route.fullPath } });
    return;
  }
  showReportModal.value = true;
}

function onReportSuccess() {
  showReportModal.value = false;
  toast.success('Báo cáo đã được ghi nhận để SportGo kiểm tra.');
}

async function loadPage() {
  brokenMedia.value = new Set();
  await Promise.all([loadProfile(), loadCommunityPosts(1), loadMatchmakingPosts(1)]);
}

watch(() => route.params.id, () => {
  activeTab.value = 'community';
  loadPage();
});

onMounted(loadPage);
</script>

<style scoped>
.user-profile-page {
  --profile-ink: #1e293b;
  --profile-muted: #64748b;
  --profile-border: #e2e8f0;
  --profile-soft: #f8fafc;
  --profile-green: #54656f;
  --profile-green-dark: #5c7e6e;
  min-height: 100vh;
  background: #f8fafc;
  color: var(--profile-ink);
  font-family: var(--sportgo-font-body, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
}

.profile-shell {
  width: min(1120px, calc(100% - 48px));
  margin: 0 auto;
  padding: 28px 0 72px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sg-community-breadcrumb {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  width: fit-content;
  color: var(--profile-muted);
  font-size: 13px;
  font-weight: 600;
}

.sg-community-breadcrumb a {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: inherit;
  text-decoration: none;
  transition: color .16s ease;
}

.sg-community-breadcrumb a:hover {
  color: var(--profile-green-dark);
}

.profile-hero,
.profile-stats,
.profile-content,
.meetup-card,
.post-card {
  border: 1.5px solid var(--profile-border) !important;
  border-radius: 12px !important;
  box-shadow: 0 4px 16px rgba(15, 23, 42, .03) !important;
}

.profile-hero {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 20px;
  min-height: 154px;
  padding: 24px 26px;
  background: #fff;
}

.profile-avatar,
.post-author-avatar {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  overflow: hidden;
  color: #fff;
  background: var(--profile-green);
  font-weight: 750;
}

.profile-avatar {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  font-size: 30px;
  box-shadow: 0 0 0 6px #edf4f0;
}

.profile-avatar img,
.post-author-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.public-profile-copy {
  min-width: 0;
}

.public-profile-copy h1 {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 5px 0 8px;
  color: var(--profile-ink);
  font-size: clamp(22px, 3vw, 30px);
  line-height: 1.2;
  letter-spacing: -.02em;
}

.public-profile-copy p {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  color: var(--profile-muted);
  font-size: 13px;
}

.report-user {
  white-space: nowrap;
}

.profile-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  overflow: hidden;
  background: #fff;
}

.profile-stats article {
  display: grid;
  gap: 5px;
  min-height: 94px;
  padding: 20px 26px;
}

.profile-stats article + article {
  border-left: 1px solid var(--profile-border);
}

.profile-stats strong {
  color: var(--profile-green-dark);
  font-size: 26px;
  line-height: 1;
}

.profile-stats span {
  color: var(--profile-muted);
  font-size: 13px;
}

.profile-tabs {
  display: flex;
  gap: 4px;
  width: 100%;
  padding: 4px;
  border: 1px solid var(--profile-border);
  border-radius: 10px;
  background: #eaf3ed;
}

.profile-tabs button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex: 1 1 0;
  min-height: 42px;
  padding: 9px 14px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #52645a;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: background .16s ease, color .16s ease, box-shadow .16s ease;
}

.profile-tabs button:hover {
  color: var(--profile-green-dark);
}

.profile-tabs button.active {
  color: var(--profile-green-dark);
  background: #fff;
  box-shadow: 0 2px 8px rgba(18, 37, 28, .08);
}

.profile-content {
  width: min(860px, 100%);
  margin: 0 auto;
  padding: 0;
  background: transparent;
  border: 0 !important;
  box-shadow: none !important;
}

.post-feed,
.meetup-list {
  display: grid;
  gap: 14px;
}

.post-card,
.meetup-card {
  overflow: hidden;
  padding: 0;
  background: #fff;
}

.post-header {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 19px 21px 12px;
}

.post-author-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  font-size: 15px;
}

.post-author-copy {
  display: grid;
  min-width: 0;
  gap: 3px;
}

.post-author-copy strong {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  color: var(--profile-ink);
  font-size: 13px;
}

.post-author-copy > span {
  color: #829087;
  font-size: 11.5px;
}

.post-open-button {
  margin-left: auto;
}

.post-copy {
  padding: 0 21px 18px;
}

.post-copy h2 {
  margin: 9px 0 6px;
  color: var(--profile-ink);
  font-size: 17px;
  line-height: 1.35;
}

.post-copy p {
  margin: 8px 0 0;
  color: #405148;
  font-size: 14px;
  line-height: 1.65;
}

.sg-client-status {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  min-height: 24px;
  padding: 4px 9px;
  border: 1px solid #cfe6d7;
  border-radius: 999px;
  background: #f0faf3;
  color: var(--profile-green-dark);
  font-size: 11px;
  font-weight: 750;
}

.post-media {
  display: block;
  width: 100%;
  aspect-ratio: 16 / 7;
  padding: 0;
  border: 0;
  background: #e8f3eb;
  cursor: pointer;
}

.post-media img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.post-footer,
.meetup-card > footer {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 13px 21px;
  border-top: 1px solid #edf3ee;
  color: #718077;
  font-size: 12px;
}

.post-footer span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.post-footer button {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-left: auto;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--profile-green-dark);
  font: inherit;
  font-weight: 750;
  cursor: pointer;
}

.meetup-card {
  padding: 21px;
}

.meetup-card > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.meetup-card h2 {
  margin: 5px 0 0;
  color: var(--profile-ink);
  font-size: 18px;
}

.meetup-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 18px;
  margin: 18px 0 12px;
  color: #52645a;
  font-size: 13px;
}

.meetup-facts span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.meetup-card > p {
  margin: 0;
  color: #52645a;
  font-size: 14px;
  line-height: 1.6;
}

.meetup-card > footer {
  justify-content: flex-end;
  margin: 18px -21px -21px;
  padding: 13px 21px;
}

.request-status {
  color: var(--profile-muted);
  font-size: 12px;
  font-weight: 700;
}

.sg-client-state {
  display: grid;
  place-items: center;
  gap: 10px;
  min-height: 220px;
  padding: 28px;
  border: 1px solid var(--profile-border);
  border-radius: 12px;
  background: #fff;
  color: var(--profile-muted);
  text-align: center;
}

.page-state {
  min-height: 360px;
}

.page-state strong,
.content-state strong {
  color: var(--profile-ink);
  font-size: 15px;
}

.page-state p,
.content-state span {
  max-width: 460px;
  margin: 0;
  line-height: 1.55;
}

.page-state--error,
.content-state--error {
  color: #a33b32;
  border-color: #f0d0cc;
  background: #fffafa;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #dceee2;
  border-top-color: var(--profile-green);
  border-radius: 50%;
  animation: profile-spin .75s linear infinite;
}

.sg-client-icon-button {
  display: inline-grid;
  place-items: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--profile-border);
  border-radius: 7px;
  background: #fff;
  color: #52645a;
  cursor: pointer;
  transition: border-color .16s ease, color .16s ease, background .16s ease;
}

.sg-client-icon-button:hover {
  border-color: #afd2bb;
  background: #f1faf4;
  color: var(--profile-green-dark);
}

@keyframes profile-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 720px) {
  .profile-shell {
    width: min(100% - 24px, 640px);
    padding-top: 18px;
    padding-bottom: 48px;
  }

  .profile-hero {
    grid-template-columns: auto minmax(0, 1fr);
    gap: 14px;
    padding: 20px;
  }

  .profile-avatar {
    width: 64px;
    height: 64px;
    font-size: 23px;
    box-shadow: 0 0 0 4px #e6f3ea;
  }

  .public-profile-copy h1 {
    font-size: 21px;
  }

  .report-user {
    grid-column: 1 / -1;
    justify-content: center;
    width: 100%;
  }

  .profile-stats article {
    min-height: 82px;
    padding: 16px 18px;
  }

  .profile-stats strong {
    font-size: 22px;
  }

  .post-header,
  .post-copy,
  .post-footer {
    padding-left: 16px;
    padding-right: 16px;
  }

  .post-footer {
    flex-wrap: wrap;
    gap: 8px 13px;
  }

  .post-footer button {
    width: 100%;
    justify-content: flex-end;
    margin-left: 0;
  }

  .meetup-card {
    padding: 17px;
  }

  .meetup-card > header {
    display: grid;
  }

  .meetup-card > footer {
    margin: 16px -17px -17px;
    padding: 12px 17px;
  }
}

@media (max-width: 420px) {
  .profile-tabs button {
    gap: 5px;
    padding-inline: 8px;
    font-size: 12px;
  }

  .profile-tabs button :deep(svg) {
    width: 15px;
    height: 15px;
  }
}
</style>


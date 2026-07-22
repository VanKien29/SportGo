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
  if (viewer.role_group !== 'user') {
    toast.info('Chức năng này dành cho tài khoản người dùng.');
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
  brokenMedia.value = new Set();
  await Promise.all([loadProfile(), loadCommunityPosts(1), loadMatchmakingPosts(1)]);
}

watch(() => route.params.id, () => {
  activeTab.value = 'community';
  loadPage();
});

onMounted(loadPage);
</script>



<template>
  <div class="user-profile-page">
    <PublicNavbar />

    <main class="profile-shell">
      <!-- BREADCRUMB -->
      <nav class="breadcrumb-nav" aria-label="Điều hướng">
        <router-link class="back-link" :to="{ name: 'ClientCommunityList' }">
          <AppIcon name="chevronLeft" size="16" />
          <span>Cộng đồng SportGo</span>
        </router-link>
      </nav>

      <!-- TRẠNG THÁI LOADING / LỖI -->
      <div v-if="profileLoading" class="profile-state-card surface" aria-live="polite">
        <span class="loader loader-small"></span>
        <p>Đang tải hồ sơ thành viên...</p>
      </div>

      <div v-else-if="profileError" class="profile-state-card surface state-card--error" role="alert">
        <AppIcon name="alert" size="28" />
        <strong>Không thể hiển thị hồ sơ</strong>
        <p>{{ profileError }}</p>
        <button type="button" class="sg-client-button sg-client-button--secondary" @click="loadPage">
          Thử lại
        </button>
      </div>

      <!-- GIAO DIỆN HỒ SƠ 2 CỘT (INFOGRAPHIC, ILLUSTRATION & ISOMETRIC - PHẲNG, KHÔNG LỒNG CARD) -->
      <div v-else-if="profileData" class="profile-layout-grid">
        <!-- CỘT CHÍNH (BÊN TRÁI - 68%) -->
        <div class="profile-main-col">
          <!-- 1. HERO BANNER & ISOMETRIC SPORTS ART -->
          <section class="profile-hero-card surface">
            <!-- BANNER NỀN: HIỂN THỊ ẢNH BÌA TÙY CHỌN HOẶC ĐỒ HỌA ISOMETRIC 2.5D MẶC ĐỊNH -->
            <div class="iso-banner-wrap">
              <!-- ẢNH BÌA TÙY CHỈNH NẾU ĐÃ CẬP NHẬT -->
              <img v-if="profileCover" :src="profileCover" alt="Ảnh bìa hồ sơ" class="custom-cover-img" />

              <!-- ĐỒ HỌA ISOMETRIC STADIUM MẶC ĐỊNH -->
              <div v-else class="iso-stadium-scene">
                <div class="iso-plane-grid">
                  <div class="iso-court-shape iso-court-badminton"></div>
                  <div class="iso-court-shape iso-court-pickleball"></div>
                  <div class="iso-court-shape iso-court-football"></div>
                </div>
                <div class="iso-floating-elements">
                  <span class="iso-float-orb orb-1"></span>
                  <span class="iso-float-orb orb-2"></span>
                </div>
              </div>

              <div class="iso-banner-overlay"></div>

              <!-- NÚT ĐỔI ẢNH BÌA (CHỈ HIỂN THỊ KHI LÀ CHÍNH CHỦ) -->
              <button
                v-if="isOwnProfile"
                type="button"
                class="cover-upload-trigger"
                :disabled="uploadingCover"
                title="Đổi ảnh bìa hồ sơ"
                @click="triggerCoverPick"
              >
                <AppIcon name="camera" size="14" />
                <span>{{ uploadingCover ? 'Đang tải lên...' : 'Đổi ảnh bìa' }}</span>
              </button>

              <input
                ref="coverFileInput"
                type="file"
                accept="image/png,image/jpeg,image/webp,image/jpg"
                class="hidden-file-input"
                @change="onCoverFileChange"
              />
            </div>

            <!-- PROFILE IDENTITY BODY (HOÀN TOÀN TRÊN NỀN TRẮNG SÁNG RÕ) -->
            <div class="profile-identity-body">
              <div class="profile-avatar-outer">
                <div class="profile-avatar">
                  <img v-if="profileAvatar" :src="profileAvatar" :alt="displayName" />
                  <span v-else>{{ initial(displayName) }}</span>
                </div>
                <span class="status-indicator-dot" title="Tài khoản đang hoạt động"></span>

                <!-- NÚT ĐỔI AVATAR (CHỈ HIỂN THỊ KHI LÀ CHÍNH CHỦ) -->
                <button
                  v-if="isOwnProfile"
                  type="button"
                  class="avatar-upload-trigger"
                  :disabled="uploadingAvatar"
                  title="Đổi ảnh đại diện"
                  @click="triggerAvatarPick"
                >
                  <AppIcon name="camera" size="13" />
                </button>

                <input
                  ref="avatarFileInput"
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/jpg"
                  class="hidden-file-input"
                  @change="onAvatarFileChange"
                />
              </div>

              <div class="profile-identity-info">
                <div class="name-badge-row">
                  <h1 class="user-fullname">{{ displayName }}</h1>
                  <ClientAuthorBadges :badges="profileData.user.author_badges" />
                </div>

                <div class="meta-infographic-chips">
                  <span class="info-chip">
                    <AppIcon name="shieldCheck" size="13" />
                    <span>Thành viên xác thực</span>
                  </span>

                  <span class="info-chip">
                    <AppIcon name="calendar" size="13" />
                    <span>Gia nhập: {{ formatDate(profileData.user.created_at) }}</span>
                  </span>
                </div>
              </div>
            </div>
          </section>

          <!-- 2. BỘ TABS NỘI DUNG PHẲNG -->
          <nav class="profile-tabs-nav surface" aria-label="Nội dung hồ sơ">
            <button
              type="button"
              class="tab-btn"
              :class="{ 'is-active': activeTab === 'community' }"
              :aria-current="activeTab === 'community' ? 'page' : undefined"
              @click="activeTab = 'community'"
            >
              <AppIcon name="newspaper" size="16" />
              <span>Bài chia sẻ ({{ communityPostCount }})</span>
            </button>

            <button
              type="button"
              class="tab-btn"
              :class="{ 'is-active': activeTab === 'matchmaking' }"
              :aria-current="activeTab === 'matchmaking' ? 'page' : undefined"
              @click="activeTab = 'matchmaking'"
            >
              <AppIcon name="users" size="16" />
              <span>Kèo giao lưu ({{ profileData.stats?.total_matchmaking_posts ?? 0 }})</span>
            </button>
          </nav>

          <!-- 3. DANH SÁCH BÀI CHIA SẺ -->
          <section v-if="activeTab === 'community'" class="profile-content-stream" aria-label="Bài chia sẻ công khai">
            <div v-if="communityLoading" class="profile-state-card surface">
              <span class="loader loader-small"></span>
              <p>Đang tải bài chia sẻ...</p>
            </div>

            <div v-else-if="communityError" class="profile-state-card surface state-card--error" role="alert">
              <AppIcon name="alert" size="24" />
              <p>{{ communityError }}</p>
              <button type="button" class="sg-client-button sg-client-button--secondary" @click="loadCommunityPosts(page)">Thử lại</button>
            </div>

            <div v-else-if="!communityPosts.length" class="profile-empty-card surface">
              <div class="manage-empty-state">
                <div class="empty-illustration-icon">
                  <AppIcon name="newspaper" size="36" />
                </div>
                <h3>Chưa có bài chia sẻ nào</h3>
                <p>Khi thành viên này đăng bài viết hoặc kinh nghiệm thể thao, nội dung sẽ hiển thị tại đây.</p>
              </div>
            </div>

            <div v-else class="stream-stack">
              <article v-for="post in communityPosts" :key="post.id" class="post-card surface">
                <header class="post-header">
                  <div class="author-avatar-wrap">
                    <img v-if="postAuthorAvatar(post)" :src="postAuthorAvatar(post)" :alt="postAuthorName(post)" />
                    <span v-else>{{ initial(postAuthorName(post)) }}</span>
                  </div>

                  <div class="author-details">
                    <strong class="author-title">
                      {{ postAuthorName(post) }}
                      <ClientAuthorBadges :badges="post.author_badges" />
                    </strong>
                    <small class="post-meta-time">
                      {{ formatDateTime(post.published_at || post.created_at) }}
                      <template v-if="post.venue_cluster?.name">, {{ post.venue_cluster.name }}</template>
                    </small>
                  </div>

                  <span class="post-kind-tag">{{ postKindLabel(post) }}</span>
                </header>

                <div class="post-body-click" @click="goToPost(post.slug || post.id)">
                  <h2 v-if="post.feed_type !== 'community_post' && post.title" class="post-title">{{ post.title }}</h2>
                  <p class="post-text">{{ postExcerpt(post) }}</p>

                  <div v-if="postMedia(post)" class="post-media-box">
                    <img
                      :src="postMedia(post)"
                      :alt="post.title || 'Ảnh bài viết'"
                      loading="lazy"
                      @error="markMediaBroken(post.id)"
                    />
                  </div>
                </div>

                <footer class="post-footer">
                  <div class="post-stats-row">
                    <span class="stat-item"><AppIcon name="heart" size="15" /> {{ postLikeCount(post) }} thích</span>
                    <span class="stat-item"><AppIcon name="messageCircle" size="15" /> {{ postCommentCount(post) }} bình luận</span>
                  </div>

                  <button type="button" class="view-detail-link" @click="goToPost(post.slug || post.id)">
                    <span>Xem bài viết</span>
                    <AppIcon name="chevronRight" size="14" />
                  </button>
                </footer>
              </article>
            </div>

            <PaginationBar v-if="lastPage > 1" :meta="paginationMeta" @change="changePage" />
          </section>

          <!-- 4. DANH SÁCH BÀI GIAO LƯU -->
          <section v-else class="profile-content-stream" aria-label="Bài giao lưu thể thao">
            <div v-if="matchmakingLoading" class="profile-state-card surface">
              <span class="loader loader-small"></span>
              <p>Đang tải danh sách kèo...</p>
            </div>

            <div v-else-if="matchmakingError" class="profile-state-card surface state-card--error" role="alert">
              <AppIcon name="alert" size="24" />
              <p>{{ matchmakingError }}</p>
              <button type="button" class="sg-client-button sg-client-button--secondary" @click="loadMatchmakingPosts(matchmakingPage)">Thử lại</button>
            </div>

            <div v-else-if="!matchmakingPosts.length" class="profile-empty-card surface">
              <div class="manage-empty-state">
                <div class="empty-illustration-icon">
                  <AppIcon name="users" size="36" />
                </div>
                <h3>Chưa có bài giao lưu công khai</h3>
                <p>Hiện không có bài giao lưu nào đang mở. Các bài đã đủ người hoặc đã kết thúc sẽ tự động ẩn.</p>
              </div>
            </div>

            <div v-else class="stream-stack">
              <article v-for="post in matchmakingPosts" :key="post.id" class="meetup-card surface">
                <div class="meetup-header-row">
                  <div class="meta-tags">
                    <span v-if="post.booking?.sport_name" class="sport-tag">
                      <AppIcon :name="post.booking.sport_icon || 'activity'" size="13" />
                      <strong>{{ post.booking.sport_name }}</strong>
                      <span v-if="post.booking.court_type_name">({{ cleanCourtType(post.booking.court_type_name, post.booking.sport_name) }})</span>
                    </span>
                    <span class="cost-badge">{{ costLabel(post) }}</span>
                  </div>

                  <span class="needed-pill">Cần {{ post.needed_players }} người</span>
                </div>

                <div class="meetup-main-info">
                  <h2 class="venue-name">
                    {{ post.booking?.venue_name || 'Cụm sân thể thao' }}
                    <span v-if="post.booking?.court_name" class="court-sub">({{ post.booking.court_name }})</span>
                  </h2>

                  <div class="facts-grid">
                    <span class="fact-col">
                      <AppIcon name="clock" size="14" />
                      <span>{{ formatDate(post.booking?.date) }}, {{ post.booking?.time || 'Chưa rõ giờ' }}</span>
                    </span>
                    <span v-if="post.booking?.venue_address" class="fact-col">
                      <AppIcon name="mapPin" size="14" />
                      <span>{{ post.booking.venue_address }}</span>
                    </span>
                  </div>

                  <p v-if="post.description" class="post-snippet">{{ post.description }}</p>

                  <div v-if="post.image_url" class="card-cover-preview">
                    <img :src="post.image_url" :alt="post.booking?.venue_name || 'Ảnh sân'" />
                  </div>
                </div>

                <footer class="meetup-footer">
                  <span v-if="post.user_status" class="status-pill status-pill--pending">
                    {{ joinLabel(post) }}
                  </span>

                  <button
                    v-if="!isOwnPost(post)"
                    type="button"
                    class="sg-client-button sg-client-button--primary"
                    :disabled="Boolean(post.user_status) || joiningId === post.id"
                    @click="joinMatchmaking(post)"
                  >
                    <AppIcon name="users" size="15" />
                    <span>{{ joinLabel(post) }}</span>
                  </button>

                  <router-link
                    v-else
                    class="sg-client-button sg-client-button--primary"
                    :to="`/matchmaking-posts/${post.id}/manage`"
                  >
                    <span>Quản lý yêu cầu</span>
                    <AppIcon name="chevronRight" size="15" />
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
        </div>

        <!-- CỘT PHỤ (BÊN PHẢI - 340px SIDEBAR - THIẾT KẾ PHẲNG, KHÔNG LỒNG CARD) -->
        <aside class="profile-sidebar">
          <!-- 1. CHỈ SỐ HOẠT ĐỘNG THỂ THAO (FLAT METRICS DASHBOARD) -->
          <section class="sidebar-card surface">
            <h3 class="sidebar-title">
              <AppIcon name="barChart" size="16" />
              <span>Chỉ số hoạt động thể thao</span>
            </h3>

            <!-- HÀNG SỐ LIỆU PHẲNG (KHÔNG LỒNG BOX/CARD) -->
            <div class="flat-stats-row">
              <div class="flat-stat-col">
                <span class="flat-stat-number">{{ communityPostCount }}</span>
                <span class="flat-stat-label">Bài viết chia sẻ</span>
              </div>

              <div class="flat-stat-divider"></div>

              <div class="flat-stat-col">
                <span class="flat-stat-number">{{ profileData.stats?.total_matchmaking_posts ?? 0 }}</span>
                <span class="flat-stat-label">Kèo giao lưu đã tạo</span>
              </div>
            </div>

            <!-- TỶ LỆ HOÀN THÀNH KÈO -->
            <div class="reputation-bar-card">
              <div class="rep-header">
                <span class="rep-title">Độ tin cậy tổ chức kèo</span>
                <strong class="rep-score">100%</strong>
              </div>
              <div class="rep-track">
                <div class="rep-progress" style="width: 100%;"></div>
              </div>
              <small class="rep-sub">Thành viên tuân thủ nghiêm túc lịch đặt sân</small>
            </div>
          </section>

          <!-- 2. BỘ MÔN THỂ THAO (FLAT LIST, KHÔNG LỒNG BOX) -->
          <section class="sidebar-card surface">
            <h3 class="sidebar-title">
              <AppIcon name="award" size="16" />
              <span>Bộ môn & Khu vực</span>
            </h3>

            <div class="flat-sports-list">
              <!-- BADMINTON -->
              <div class="flat-sport-item">
                <div class="sport-badge-icon badge--badminton">
                  <AppIcon name="badminton" size="16" />
                </div>
                <div class="sport-item-text">
                  <strong>Cầu lông phong trào</strong>
                  <span>Mọi trình độ, đánh đôi</span>
                </div>
              </div>

              <!-- PICKLEBALL -->
              <div class="flat-sport-item">
                <div class="sport-badge-icon badge--pickleball">
                  <AppIcon name="pickleball" size="16" />
                </div>
                <div class="sport-item-text">
                  <strong>Pickleball</strong>
                  <span>Giao lưu học hỏi, rèn thể lực</span>
                </div>
              </div>
            </div>
          </section>

          <!-- 3. VĂN HÓA THỂ THAO & AN TOÀN -->
          <section class="sidebar-card surface">
            <h3 class="sidebar-title">
              <AppIcon name="heart" size="16" />
              <span>Văn hóa thể thao</span>
            </h3>

            <p class="fairplay-text">
              SportGo khuyến khích tinh thần giao lưu lành mạnh, tôn trọng đối thủ và đúng giờ khi tham gia các buổi ghép kèo.
            </p>

            <button
              v-if="canReportUser"
              type="button"
              class="report-account-btn"
              @click="openUserReport"
            >
              <AppIcon name="flag" size="14" />
              <span>Báo cáo tài khoản này</span>
            </button>
          </section>
        </aside>
      </div>
    </main>

    <!-- MODAL BÁO CÁO TÀI KHOẢN -->
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
import { getAuth, saveAuth } from '@/stores/auth.js';

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

// Upload Avatar & Cover Image
const avatarFileInput = ref(null);
const coverFileInput = ref(null);
const uploadingAvatar = ref(false);
const uploadingCover = ref(false);

const isOwnProfile = computed(() => {
  return String(viewer?.id || '') !== '' && String(viewer?.id || '') === String(profileData.value?.user?.id || '');
});

const displayName = computed(() => profileData.value?.user?.full_name || profileData.value?.user?.username || 'Người dùng');
const profileAvatar = computed(() => assetUrl(profileData.value?.user?.avatar_url));
const profileCover = computed(() => assetUrl(profileData.value?.user?.cover_image_url));

const canReportUser = computed(() => !isOwnProfile.value && Boolean(profileData.value?.user?.id));
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

function costLabel(post) {
  if (post?.cost_type === 'free') return 'Miễn phí (Chủ bao sân)';
  if (Number(post?.cost_per_player) > 0) {
    const k = Math.round(Number(post.cost_per_player) / 1000);
    return `~${k}k / người`;
  }
  return 'Chia đều tiền sân';
}

function cleanCourtType(typeName, sportName) {
  if (!typeName) return 'Sân tiêu chuẩn';
  const match = String(typeName).match(/\((.*?)\)/);
  if (match && match[1]) return match[1].trim();
  if (sportName && String(typeName).toLowerCase().startsWith(String(sportName).toLowerCase())) {
    const cleaned = String(typeName).slice(sportName.length).trim().replace(/^[-·:() ]+/, '').replace(/\)$/, '');
    if (cleaned) return cleaned;
  }
  return typeName;
}

function postLikeCount(post) {
  return Number(post?.like_count || post?.likes_count || 0);
}

function postCommentCount(post) {
  return Number(post?.comment_count || post?.comments_count || 0);
}

// XỬ LÝ UPLOAD AVATAR & ẢNH BÌA
function triggerAvatarPick() {
  avatarFileInput.value?.click();
}

function triggerCoverPick() {
  coverFileInput.value?.click();
}

async function onAvatarFileChange(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  if (file.size > 4 * 1024 * 1024) {
    toast.error('Ảnh đại diện không được vượt quá 4MB.');
    event.target.value = '';
    return;
  }

  const formData = new FormData();
  formData.append('avatar', file);

  uploadingAvatar.value = true;
  try {
    const res = await api('/api/profile/avatar', {
      method: 'POST',
      body: formData,
    });

    if (profileData.value?.user) {
      profileData.value.user.avatar_url = res.avatar_url;
    }
    if (viewer) {
      saveAuth({ ...viewer, avatar_url: res.avatar_url });
    }
    toast.success('Đã cập nhật ảnh đại diện thành công!');
  } catch (err) {
    toast.error(err.message || 'Không thể tải lên ảnh đại diện.');
  } finally {
    uploadingAvatar.value = false;
    event.target.value = '';
  }
}

async function onCoverFileChange(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  if (file.size > 6 * 1024 * 1024) {
    toast.error('Ảnh bìa không được vượt quá 6MB.');
    event.target.value = '';
    return;
  }

  const formData = new FormData();
  formData.append('cover_image', file);

  uploadingCover.value = true;
  try {
    const res = await api('/api/profile/cover', {
      method: 'POST',
      body: formData,
    });

    if (profileData.value?.user) {
      profileData.value.user.cover_image_url = res.cover_image_url;
    }
    if (viewer) {
      saveAuth({ ...viewer, cover_image_url: res.cover_image_url });
    }
    toast.success('Đã cập nhật ảnh bìa thành công!');
  } catch (err) {
    toast.error(err.message || 'Không thể tải lên ảnh bìa.');
  } finally {
    uploadingCover.value = false;
    event.target.value = '';
  }
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
  --community-ink: #1e293b;
  --community-muted: #64748b;
  --community-soft: #f8fafc;
  --community-surface: #ffffff;
  --community-line: #e2e8f0;
  --community-accent: #5c7e6e;
  --community-accent-dark: #446153;
  --community-accent-soft: #edf4f0;
  --community-danger: #b42318;
  --community-danger-soft: #fff2f0;

  min-height: 100vh;
  background: var(--community-soft);
  color: var(--community-ink);
  font-family: var(--sportgo-font-body, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
}

.profile-shell {
  width: min(1280px, calc(100% - 48px));
  margin: 0 auto;
  padding: 24px 0 64px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.breadcrumb-nav {
  margin-bottom: -4px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--community-muted);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.15s ease;
}

.back-link:hover {
  color: var(--community-accent-dark);
}

.surface {
  background: var(--community-surface);
  border: 1.5px solid var(--community-line);
  border-radius: 14px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

/* LAYOUT 2 CỘT */
.profile-layout-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  align-items: start;
  gap: 24px;
}

.profile-main-col {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.profile-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
  position: sticky;
  top: 22px;
}

/* 1. HERO CARD & ISOMETRIC BANNER */
.profile-hero-card {
  overflow: hidden;
  position: relative;
}

.iso-banner-wrap {
  height: 140px;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #1e3a2b 0%, #2f4f3e 50%, #446153 100%);
}

.custom-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.iso-stadium-scene {
  position: absolute;
  inset: 0;
  perspective: 600px;
  overflow: hidden;
}

.iso-plane-grid {
  position: absolute;
  width: 140%;
  height: 200%;
  top: -30%;
  left: -20%;
  transform: rotateX(55deg) rotateZ(-25deg);
  background-image: 
    linear-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
  background-size: 32px 32px;
  opacity: 0.6;
}

.iso-court-shape {
  position: absolute;
  border: 1.5px solid rgba(255, 255, 255, 0.25);
  border-radius: 4px;
  background: rgba(92, 126, 110, 0.15);
}

.iso-court-badminton {
  width: 120px;
  height: 70px;
  top: 40px;
  left: 180px;
}

.iso-court-pickleball {
  width: 100px;
  height: 60px;
  top: 130px;
  left: 360px;
}

.iso-court-football {
  width: 160px;
  height: 90px;
  top: 60px;
  left: 540px;
}

.iso-floating-elements {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.iso-float-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(24px);
}

.orb-1 {
  width: 180px;
  height: 180px;
  background: rgba(92, 126, 110, 0.4);
  top: -40px;
  right: 10%;
}

.orb-2 {
  width: 140px;
  height: 140px;
  background: rgba(234, 179, 8, 0.2);
  bottom: -20px;
  left: 20%;
}

.iso-banner-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 40%, rgba(15, 23, 42, 0.35) 100%);
}

.cover-upload-trigger {
  position: absolute;
  top: 12px;
  right: 14px;
  z-index: 5;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(8px);
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cover-upload-trigger:hover {
  background: rgba(15, 23, 42, 0.85);
  border-color: rgba(255, 255, 255, 0.6);
}

.hidden-file-input {
  display: none !important;
}

.profile-identity-body {
  padding: 0 28px 22px;
  display: flex;
  align-items: flex-start;
  gap: 22px;
  position: relative;
  z-index: 2;
  background: var(--community-surface);
}

.profile-avatar-outer {
  position: relative;
  flex-shrink: 0;
  margin-top: -48px;
  z-index: 3;
}

.profile-avatar {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  overflow: hidden;
  background: #54656f;
  color: #fff;
  display: inline-grid;
  place-items: center;
  font-size: 36px;
  font-weight: 700;
  border: 4px solid var(--community-surface);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
}

.profile-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-upload-trigger {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #54656f;
  color: #ffffff;
  border: 2px solid #ffffff;
  display: inline-grid;
  place-items: center;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
  transition: all 0.15s ease;
}

.avatar-upload-trigger:hover {
  background: #405059;
}

.status-indicator-dot {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #22c55e;
  border: 2.5px solid #ffffff;
}

.profile-identity-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 14px;
}

.name-badge-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.user-fullname {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: var(--community-ink);
  line-height: 1.2;
}

.meta-infographic-chips {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.info-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: var(--community-soft);
  border: 1px solid var(--community-line);
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
  color: var(--community-muted);
}

.info-chip svg {
  color: var(--community-accent);
}

/* 2. TABS NAV */
.profile-tabs-nav {
  display: flex;
  gap: 6px;
  padding: 5px;
}

.tab-btn {
  flex: 1;
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--community-muted);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  color: var(--community-ink);
  background: var(--community-soft);
}

.tab-btn.is-active {
  color: var(--community-accent-dark);
  background: var(--community-accent-soft);
  font-weight: 700;
}

/* 3. STREAM & CARDS */
.stream-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.post-card,
.meetup-card {
  padding: 22px 24px;
}

/* POST CARD */
.post-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.author-avatar-wrap {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #54656f;
  color: #fff;
  display: inline-grid;
  place-items: center;
  font-size: 15px;
  font-weight: 700;
  overflow: hidden;
  flex-shrink: 0;
}

.author-avatar-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.author-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.author-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--community-ink);
  display: flex;
  align-items: center;
  gap: 6px;
}

.post-meta-time {
  font-size: 12px;
  color: var(--community-muted);
}

.post-kind-tag {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  background: var(--community-soft);
  border: 1px solid var(--community-line);
  border-radius: 6px;
  color: var(--community-muted);
  font-size: 11.5px;
  font-weight: 500;
}

.post-body-click {
  cursor: pointer;
  margin-bottom: 14px;
}

.post-title {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 700;
  color: var(--community-ink);
}

.post-text {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.6;
  color: var(--community-ink);
}

.post-media-box {
  margin-top: 12px;
  border-radius: 8px;
  overflow: hidden;
  max-height: 320px;
  border: 1px solid var(--community-line);
}

.post-media-box img {
  width: 100%;
  height: 100%;
  max-height: 320px;
  object-fit: cover;
  display: block;
}

.post-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-top: 12px;
  border-top: 1px solid var(--community-line);
}

.post-stats-row {
  display: flex;
  align-items: center;
  gap: 16px;
  color: var(--community-muted);
  font-size: 12.5px;
}

.stat-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.view-detail-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--community-accent-dark);
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
}

.view-detail-link:hover {
  opacity: 0.85;
}

/* MEETUP CARD */
.meetup-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.meta-tags {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.sport-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: var(--community-accent-soft);
  border-radius: 6px;
  color: var(--community-accent-dark);
  font-size: 12px;
}

.sport-tag strong {
  font-weight: 600;
}

.sport-tag span {
  color: var(--community-accent);
}

.cost-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 500;
  background: #fef3c7;
  color: #92400e;
}

.needed-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 600;
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
}

.venue-name {
  margin: 0 0 8px;
  font-size: 16px;
  font-weight: 700;
  color: var(--community-ink);
}

.court-sub {
  font-size: 14px;
  font-weight: 500;
  color: var(--community-muted);
  margin-left: 4px;
}

.facts-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 24px;
  color: var(--community-muted);
  font-size: 13px;
  margin-bottom: 8px;
}

.fact-col {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.fact-col svg {
  color: var(--community-accent);
}

.post-snippet {
  margin: 10px 0 0;
  font-size: 13px;
  line-height: 1.55;
  color: var(--community-ink);
}

.card-cover-preview {
  margin-top: 12px;
  border-radius: 8px;
  overflow: hidden;
  max-height: 160px;
  border: 1px solid var(--community-line);
}

.card-cover-preview img {
  width: 100%;
  height: 100%;
  max-height: 160px;
  object-fit: cover;
  display: block;
}

.meetup-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid var(--community-line);
}

.status-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 11px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.status-pill--pending {
  background: #fef3c7;
  color: #92400e;
}

/* SIDEBAR WIDGETS (FLAT DESIGN - NO CARD-IN-CARD) */
.sidebar-card {
  padding: 22px;
}

.sidebar-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 18px;
  font-size: 14.5px;
  font-weight: 700;
  color: var(--community-ink);
}

.sidebar-title svg {
  color: var(--community-accent);
}

/* FLAT STATS ROW (KHÔNG CÓ CARD HỘP CON LỒNG NHAU) */
.flat-stats-row {
  display: flex;
  align-items: center;
  padding: 12px 0 16px;
  margin-bottom: 14px;
}

.flat-stat-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 2px;
}

.flat-stat-number {
  font-size: 28px;
  font-weight: 800;
  color: var(--community-accent-dark);
  line-height: 1.1;
}

.flat-stat-label {
  font-size: 12px;
  color: var(--community-muted);
  font-weight: 500;
}

.flat-stat-divider {
  width: 1px;
  height: 36px;
  background: var(--community-line);
}

.reputation-bar-card {
  padding-top: 14px;
  border-top: 1px solid var(--community-line);
}

.rep-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.rep-title {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--community-ink);
}

.rep-score {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--community-accent-dark);
}

.rep-track {
  height: 6px;
  background: var(--community-line);
  border-radius: 999px;
  overflow: hidden;
  margin-bottom: 6px;
}

.rep-progress {
  height: 100%;
  background: var(--community-accent);
  border-radius: 999px;
}

.rep-sub {
  font-size: 11px;
  color: var(--community-muted);
  display: block;
}

/* FLAT SPORTS LIST (DANH SÁCH PHẲNG, KHÔNG CARD LỒNG CARD) */
.flat-sports-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.flat-sport-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--community-line);
}

.flat-sport-item:last-child {
  padding-bottom: 0;
  border-bottom: none;
}

.sport-badge-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: inline-grid;
  place-items: center;
  flex-shrink: 0;
}

.badge--badminton {
  background: #ecfdf5;
  color: #059669;
}

.badge--pickleball {
  background: #fffbeb;
  color: #d97706;
}

.sport-item-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sport-item-text strong {
  font-size: 13.5px;
  color: var(--community-ink);
}

.sport-item-text span {
  font-size: 12px;
  color: var(--community-muted);
}

/* FAIR-PLAY & SAFETY */
.fairplay-text {
  margin: 0 0 16px;
  font-size: 12.5px;
  line-height: 1.55;
  color: var(--community-muted);
}

.report-account-btn {
  width: 100%;
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 0 12px;
  border: 1px solid rgba(180, 35, 24, 0.2);
  border-radius: 8px;
  background: #ffffff;
  color: var(--community-danger);
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.report-account-btn:hover {
  background: var(--community-danger-soft);
  border-color: var(--community-danger);
}

/* BUTTONS & EMPTY */
.sg-client-button {
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 0 14px;
  border: 1px solid var(--community-line);
  border-radius: 8px;
  background: var(--community-surface);
  color: var(--community-ink);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sg-client-button:hover {
  background: var(--community-soft);
  border-color: #cbd5e1;
}

.sg-client-button--primary {
  background: #54656f;
  border-color: #54656f;
  color: #ffffff;
}

.sg-client-button--primary:hover {
  background: #405059;
  border-color: #405059;
}

.profile-state-card,
.profile-empty-card {
  padding: 48px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--community-muted);
}

.state-card--error {
  border-color: var(--community-danger);
  color: var(--community-danger);
}

.manage-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  border: none !important;
  background: transparent !important;
}

.empty-illustration-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
  display: inline-grid;
  place-items: center;
  margin-bottom: 12px;
}

.manage-empty-state h3 {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 600;
  color: var(--community-ink);
}

.manage-empty-state p {
  margin: 0;
  font-size: 13px;
  color: var(--community-muted);
  max-width: 380px;
  line-height: 1.5;
}

.loader {
  width: 24px;
  height: 24px;
  display: inline-block;
  border: 3px solid #d7e8dc;
  border-top-color: var(--community-accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.loader-small {
  width: 18px;
  height: 18px;
  border-width: 2px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 990px) {
  .profile-shell {
    width: 100%;
    padding: 16px 12px 48px;
  }

  .profile-layout-grid {
    grid-template-columns: 1fr;
  }

  .profile-sidebar {
    position: static;
  }

  .profile-identity-body {
    flex-direction: column;
    align-items: flex-start;
    padding: 0 18px 18px;
    margin-top: -36px;
  }
}
</style>

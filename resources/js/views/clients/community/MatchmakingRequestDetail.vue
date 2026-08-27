<template>
  <div class="matchmaking-detail-page">
    <PublicNavbar />

    <main class="detail-shell">
      <!-- BREADCRUMB -->
      <router-link class="back-nav-link" :to="{ name: 'ClientMatchmakingRequests' }">
        <AppIcon name="chevronLeft" size="16" />
        <span>Đơn tham gia của tôi</span>
      </router-link>

      <!-- TRẠNG THÁI LOADING / LỖI -->
      <div v-if="loading" class="detail-state-card surface">
        <span class="loader loader-small"></span>
        <p>Đang tải chi tiết đơn tham gia...</p>
      </div>

      <div v-else-if="error" class="detail-state-card surface state-card--error" role="alert">
        <AppIcon name="alert" size="28" />
        <strong>Không thể tải chi tiết đơn</strong>
        <p>{{ error }}</p>
        <button class="sg-client-button sg-client-button--secondary" type="button" @click="load">
          Thử lại
        </button>
      </div>

      <!-- NỘI DUNG CHI TIẾT ĐƠN (2 CỘT) -->
      <template v-else-if="item">
        <div class="detail-layout">
          <!-- CỘT CHÍNH (BÊN TRÁI) -->
          <div class="detail-main-col">
            <!-- 1. THẺ HERO BUỔI GIAO LƯU -->
            <section class="detail-hero-card surface">
              <header class="hero-top-row">
                <div class="meta-tags">
                  <span v-if="item.booking?.sport_name" class="sport-tag">
                    <AppIcon :name="item.booking.sport_icon || 'activity'" size="13" />
                    <strong>{{ item.booking.sport_name }}</strong>
                    <span v-if="item.booking.court_type_name">({{ item.booking.court_type_name }})</span>
                  </span>
                  <span class="cost-badge">{{ costLabel(item.post) }}</span>
                </div>

                <span class="status-pill" :class="`status-pill--${item.status}`">
                  {{ statusLabel(item.status) }}
                </span>
              </header>

              <h1 class="venue-title">
                {{ item.booking?.venue_name || 'Cụm sân thể thao' }}
                <span v-if="item.booking?.court_name" class="court-sub">({{ item.booking.court_name }})</span>
              </h1>

              <!-- DẢI THÔNG SỐ PHẲNG -->
              <div class="metrics-grid">
                <div class="metric-cell">
                  <span class="metric-label">THỜI GIAN</span>
                  <strong class="metric-val">
                    <AppIcon name="clock" size="14" />
                    <span>{{ formatDate(item.booking?.date) }}, {{ item.booking?.time }}</span>
                  </strong>
                </div>

                <div class="metric-cell">
                  <span class="metric-label">ĐỊA ĐIỂM</span>
                  <strong class="metric-val">
                    <AppIcon name="mapPin" size="14" />
                    <span>{{ item.booking?.venue_address || 'Địa chỉ sân' }}</span>
                  </strong>
                </div>

                <div class="metric-cell">
                  <span class="metric-label">TRÌNH ĐỘ YÊU CẦU</span>
                  <strong class="metric-val">
                    <AppIcon name="users" size="14" />
                    <span>{{ skillLabel(item.post?.skill_level) }}</span>
                  </strong>
                </div>
              </div>

              <!-- MÔ TẢ & ẢNH ĐÍNH KÈM -->
              <div v-if="item.post?.description" class="post-desc-block">
                <span class="desc-label">MÔ TẢ TỪ CHỦ KÈO</span>
                <p>{{ item.post.description }}</p>
              </div>

              <div v-if="item.post?.image_url" class="post-image-block">
                <img :src="item.post.image_url" :alt="item.booking?.venue_name || 'Ảnh sân'" />
              </div>
            </section>

            <!-- 2. THẺ TRẠNG THÁI & HƯỚNG DẪN -->
            <section class="detail-guide-card" :class="`guide-card--${item.status}`">
              <AppIcon :name="item.status === 'approved' ? 'circleCheck' : (item.status === 'rejected' ? 'alert' : 'clock')" size="20" />
              <div>
                <strong>{{ guideHeading }}</strong>
                <p>{{ guideMessage }}</p>
              </div>
            </section>
          </div>

          <!-- CỘT PHỤ (BÊN PHẢI - SIDEBAR) -->
          <aside class="detail-sidebar">
            <!-- 1. THAO TÁC NHANH -->
            <section class="sidebar-card surface">
              <h3 class="sidebar-title">
                <AppIcon name="settings" size="16" />
                <span>Thao tác</span>
              </h3>

              <div class="sidebar-stack">
                <router-link
                  v-if="item.status === 'approved' && item.group_chat_id"
                  class="sg-client-button sg-client-button--primary btn-full"
                  :to="{ name: 'client-messages', query: { conversation_id: item.group_chat_id } }"
                >
                  <AppIcon name="messageCircle" size="16" />
                  <span>Mở nhóm chat kèo</span>
                </router-link>

                <a
                  v-if="mapUrl"
                  :href="mapUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="sg-client-button sg-client-button--secondary btn-full"
                >
                  <AppIcon name="mapPin" size="16" />
                  <span>Chỉ đường Google Maps</span>
                </a>

                <button
                  v-if="item.can_leave"
                  type="button"
                  class="sg-client-button sg-client-button--danger btn-full"
                  :disabled="leaving"
                  @click="showLeaveConfirm = true"
                >
                  <span>{{ item.status === 'approved' ? 'Rút khỏi kèo' : 'Hủy yêu cầu' }}</span>
                </button>
              </div>
            </section>

            <!-- 2. THÔNG TIN CHỦ BÀI -->
            <section class="sidebar-card surface">
              <h3 class="sidebar-title">
                <AppIcon name="user" size="16" />
                <span>Chủ bài giao lưu</span>
              </h3>

              <div class="author-profile-row">
                <span class="author-avatar">{{ initial(item.author?.name) }}</span>
                <div class="author-meta">
                  <strong>{{ item.author?.name || 'Người dùng SportGo' }}</strong>
                  <small>Đã gửi đơn: {{ timeAgo(item.created_at) }}</small>
                </div>
              </div>

              <p class="privacy-note">
                <AppIcon name="shield" size="14" />
                <span>Thông tin cá nhân được bảo vệ. Sau khi được duyệt, bạn và chủ kèo có thể trao đổi trong nhóm chat nội bộ.</span>
              </p>
            </section>
          </aside>
        </div>

        <!-- MODAL XÁC NHẬN RÚT YÊU CẦU -->
        <ConfirmModal
          v-model="showLeaveConfirm"
          :title="item.status === 'approved' ? 'Rút khỏi buổi giao lưu' : 'Hủy yêu cầu tham gia'"
          :message="item.status === 'approved' ? 'Bạn đã được duyệt vào buổi giao lưu này. Bạn có chắc chắn muốn rút lui không?' : 'Bạn có chắc chắn muốn hủy yêu cầu tham gia này không?'"
          confirm-text="Xác nhận rút"
          cancel-text="Giữ lại"
          type="danger"
          @confirm="leave"
        />
      </template>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const item = ref(null);
const loading = ref(true);
const error = ref('');
const leaving = ref(false);
const showLeaveConfirm = ref(false);

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api(`/api/matchmaking-requests/${route.params.id}`);
    item.value = response.data;
  } catch (e) {
    error.value = e.message || 'Không thể tải chi tiết đơn.';
  } finally {
    loading.value = false;
  }
}

async function leave() {
  if (!item.value?.post_id || leaving.value) return;
  leaving.value = true;
  try {
    await api(`/api/matchmaking-posts/${item.value.post_id}/leave`, { method: 'POST' });
    toast.success('Đã rút yêu cầu tham gia.');
    await router.replace({ name: 'ClientMatchmakingRequests' });
  } catch (e) {
    toast.error(e.message || 'Không thể rút yêu cầu.');
  } finally {
    leaving.value = false;
    showLeaveConfirm.value = false;
  }
}

function statusLabel(value) {
  return {
    pending: 'Chờ duyệt',
    approved: 'Đã được duyệt',
    rejected: 'Bị từ chối',
    cancelled: 'Đã kết thúc',
  }[value] || value;
}

function skillLabel(level) {
  return {
    all: 'Mọi trình độ',
    beginner: 'Mới chơi',
    intermediate: 'Trung bình',
    advanced: 'Khá / Nâng cao',
  }[level] || 'Mọi trình độ';
}

function costLabel(post) {
  if (post?.cost_type === 'free') return 'Miễn phí (Chủ bao sân)';
  if (Number(post?.cost_per_player) > 0) {
    const k = Math.round(Number(post.cost_per_player) / 1000);
    return `~${k}k / người`;
  }
  return 'Chia đều tiền sân';
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  const [year, month, day] = String(value).slice(0, 10).split('-');
  return day && month && year ? `${day}/${month}/${year}` : value;
}

function timeAgo(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  const diff = Math.floor((Date.now() - date.getTime()) / 1000);
  if (diff < 60) return 'Vừa xong';
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
  return `${Math.floor(diff / 86400)} ngày trước`;
}

function initial(name) {
  return String(name || 'N').trim().charAt(0).toUpperCase();
}

const mapUrl = computed(() => {
  const v = item.value?.booking;
  if (!v?.venue_name) return null;
  const q = encodeURIComponent(`${v.venue_name} ${v.venue_address || ''}`);
  return `https://www.google.com/maps/search/?api=1&query=${q}`;
});

const guideHeading = computed(() => {
  if (item.value?.status === 'approved') return 'Yêu cầu của bạn đã được chấp nhận';
  if (item.value?.status === 'pending') return 'Đang chờ chủ kèo xem xét';
  if (item.value?.status === 'rejected') return 'Yêu cầu không được chấp nhận';
  return 'Buổi giao lưu đã kết thúc';
});

const guideMessage = computed(() => {
  if (item.value?.status === 'approved') {
    return 'Bạn đã có tên trong danh sách tham gia. Hãy mở nhóm chat để trao đổi giờ giấc và trang phục trước trận đấu.';
  }
  if (item.value?.status === 'pending') {
    return 'Chủ kèo đang xem xét thông tin của bạn. Khi được duyệt, hệ thống sẽ gửi thông báo và tự động thêm bạn vào nhóm chat.';
  }
  if (item.value?.status === 'rejected') {
    return 'Rất tiếc chủ kèo chưa thể ghép bạn vào buổi chơi này. Hãy tìm kiếm các kèo giao lưu khác phù hợp hơn nhé!';
  }
  return 'Buổi giao lưu thể thao này đã hoàn thành hoặc đã quá thời hạn đăng ký.';
});

onMounted(load);
</script>

<style scoped>
.matchmaking-detail-page {
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

.detail-shell {
  width: min(1100px, calc(100% - 48px));
  margin: 0 auto;
  padding: 24px 0 64px;
}

.back-nav-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--community-muted);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  margin-bottom: 20px;
  transition: color 0.15s ease;
}

.back-nav-link:hover {
  color: var(--community-accent-dark);
}

.surface {
  background: var(--community-surface);
  border: 1.5px solid var(--community-line);
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

/* LAYOUT 2 CỘT */
.detail-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  align-items: start;
  gap: 24px;
}

.detail-main-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 22px;
}

/* HERO CARD */
.detail-hero-card {
  padding: 24px;
}

.hero-top-row {
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

.status-pill--approved {
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
}

.status-pill--rejected {
  background: #fee2e2;
  color: #991b1b;
}

.status-pill--cancelled {
  background: #f1f5f9;
  color: #475569;
}

.venue-title {
  margin: 0 0 16px;
  font-size: 20px;
  font-weight: 700;
  color: var(--community-ink);
}

.court-sub {
  font-size: 16px;
  font-weight: 500;
  color: var(--community-muted);
  margin-left: 4px;
}

/* DẢI THÔNG SỐ */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  padding: 14px 0;
  border-top: 1px solid var(--community-line);
  border-bottom: 1px solid var(--community-line);
  margin-bottom: 16px;
}

.metric-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.metric-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--community-muted);
  letter-spacing: 0.3px;
}

.metric-val {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--community-ink);
}

.metric-val svg {
  color: var(--community-accent);
}

.post-desc-block {
  margin-top: 14px;
}

.desc-label {
  display: block;
  font-size: 11.5px;
  font-weight: 700;
  color: var(--community-muted);
  letter-spacing: 0.4px;
  margin-bottom: 6px;
}

.post-desc-block p {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.6;
  color: var(--community-ink);
  white-space: pre-line;
}

.post-image-block {
  margin-top: 14px;
  border-radius: 8px;
  overflow: hidden;
  max-height: 260px;
  border: 1px solid var(--community-line);
}

.post-image-block img {
  width: 100%;
  height: 100%;
  max-height: 260px;
  object-fit: cover;
  display: block;
}

/* GUIDE CARD */
.detail-guide-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border: 1.5px solid #d5e2dc;
  border-radius: 10px;
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
}

.detail-guide-card svg {
  color: var(--community-accent);
  flex-shrink: 0;
}

.detail-guide-card strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #2e463a;
}

.detail-guide-card p {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: #4e6559;
}

.guide-card--pending {
  border-color: #fed7aa;
  background: #fffbeb;
  color: #92400e;
}

.guide-card--pending svg {
  color: #d97706;
}

.guide-card--pending strong {
  color: #78350f;
}

.guide-card--pending p {
  color: #92400e;
}

.guide-card--rejected {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.guide-card--rejected svg {
  color: #dc2626;
}

.guide-card--rejected strong {
  color: #7f1d1d;
}

.guide-card--rejected p {
  color: #991b1b;
}

/* SIDEBAR */
.sidebar-card {
  padding: 20px;
}

.sidebar-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 14px;
  font-size: 14px;
  font-weight: 600;
  color: var(--community-ink);
}

.sidebar-title svg {
  color: var(--community-accent);
}

.sidebar-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.btn-full {
  width: 100%;
}

.author-profile-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.author-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #54656f;
  color: #fff;
  display: inline-grid;
  place-items: center;
  font-size: 15px;
  font-weight: 700;
}

.author-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.author-meta strong {
  font-size: 13.5px;
  color: var(--community-ink);
}

.author-meta small {
  font-size: 11.5px;
  color: var(--community-muted);
}

.privacy-note {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  margin: 0;
  padding-top: 12px;
  border-top: 1px solid var(--community-line);
  font-size: 12px;
  line-height: 1.45;
  color: var(--community-muted);
}

.privacy-note svg {
  color: var(--community-accent);
  flex-shrink: 0;
  margin-top: 2px;
}

/* NÚT THAO TÁC */
.sg-client-button {
  min-height: 38px;
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

.sg-client-button--danger {
  color: var(--community-danger);
  border-color: rgba(180, 35, 24, 0.2);
}

.sg-client-button--danger:hover {
  background: var(--community-danger-soft);
  border-color: var(--community-danger);
}

.detail-state-card {
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

@media (max-width: 900px) {
  .detail-layout {
    grid-template-columns: 1fr;
  }

  .detail-sidebar {
    position: static;
  }

  .metrics-grid {
    grid-template-columns: 1fr;
  }
}
</style>

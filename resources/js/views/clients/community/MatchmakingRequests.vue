<template>
  <div class="matchmaking-requests-page">
    <PublicNavbar />

    <main class="requests-shell">
      <!-- BREADCRUMB & HEADER -->
      <header class="requests-header">
        <router-link class="back-nav-link" :to="{ name: 'ClientCommunityList' }">
          <AppIcon name="chevronLeft" size="16" />
          <span>Cộng đồng</span>
        </router-link>

        <div class="header-main-row">
          <div class="header-copy">
            <h1>Đơn tham gia của tôi</h1>
            <p>Theo dõi tình trạng các yêu cầu xin ghép kèo và vào nhóm trao đổi khi được duyệt.</p>
          </div>

          <router-link class="sg-client-button sg-client-button--primary" :to="{ name: 'ClientCommunityList' }">
            <AppIcon name="plus" size="16" />
            <span>Tìm kèo mới</span>
          </router-link>
        </div>
      </header>

      <!-- BỘ LỌC TRẠNG THÁI (TABS) -->
      <nav class="status-tabs surface" aria-label="Lọc đơn tham gia">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          class="tab-item"
          :class="{ 'is-active': status === tab.value }"
          :aria-pressed="status === tab.value"
          @click="changeTab(tab.value)"
        >
          <span>{{ tab.label }}</span>
        </button>
      </nav>

      <!-- TRẠNG THÁI TẢI / LỖI -->
      <div v-if="loading" class="requests-state-card surface">
        <span class="loader loader-small"></span>
        <p>Đang tải danh sách đơn tham gia...</p>
      </div>

      <div v-else-if="error" class="requests-state-card surface state-card--error" role="alert">
        <AppIcon name="alert" size="28" />
        <strong>Không thể tải danh sách đơn</strong>
        <p>{{ error }}</p>
        <button class="sg-client-button sg-client-button--secondary" type="button" @click="load">
          Thử lại
        </button>
      </div>

      <!-- TRẠNG THÁI RỖNG (EMPTY STATE) -->
      <div v-else-if="!items.length" class="requests-empty-panel surface">
        <div class="manage-empty-state">
          <AppIcon name="users" size="44" />
          <h3>Chưa có đơn tham gia nào</h3>
          <p v-if="status === 'all'">
            Bạn chưa gửi yêu cầu xin ghép kèo nào. Hãy khám phá các buổi giao lưu thể thao sắp tới!
          </p>
          <p v-else>
            Không có đơn tham gia nào ở trạng thái "{{ currentTabLabel }}".
          </p>
          <router-link class="sg-client-button sg-client-button--primary" :to="{ name: 'ClientCommunityList' }">
            Khám phá kèo giao lưu
          </router-link>
        </div>
      </div>

      <!-- DANH SÁCH THẺ ĐƠN THAM GIA -->
      <div v-else class="requests-grid">
        <article v-for="item in items" :key="item.id" class="request-card surface">
          <!-- HEADER THẺ: MÔN THỂ THAO & TRẠNG THÁI -->
          <div class="card-top-row">
            <div class="meta-tags">
              <span v-if="item.booking?.sport_name" class="sport-tag">
                <AppIcon :name="item.booking.sport_icon || 'activity'" size="13" />
                <strong>{{ item.booking.sport_name }}</strong>
                <span v-if="item.booking.court_type_name">({{ item.booking.court_type_name }})</span>
              </span>

              <span class="cost-badge">
                {{ costLabel(item.post) }}
              </span>
            </div>

            <span class="status-pill" :class="`status-pill--${item.status}`">
              {{ statusLabel(item.status) }}
            </span>
          </div>

          <!-- THÔNG TIN SÂN & THỜI GIAN -->
          <div class="card-main-body">
            <h2 class="venue-title">
              {{ item.booking?.venue_name || 'Cụm sân thể thao' }}
              <span v-if="item.booking?.court_name" class="court-sub">({{ item.booking.court_name }})</span>
            </h2>

            <div class="facts-list">
              <span class="fact-item">
                <AppIcon name="clock" size="14" />
                <strong>{{ formatDate(item.booking?.date) }}, {{ item.booking?.time }}</strong>
              </span>

              <span v-if="item.booking?.venue_address" class="fact-item">
                <AppIcon name="mapPin" size="14" />
                <span>{{ item.booking.venue_address }}</span>
              </span>
            </div>

            <!-- MÔ TẢ BÀI VIẾT NẾU CÓ -->
            <p v-if="item.post?.description" class="post-snippet">
              {{ item.post.description }}
            </p>

            <!-- ẢNH ĐÍNH KÈM NẾU CÓ -->
            <div v-if="item.post?.image_url" class="card-cover-preview">
              <img :src="item.post.image_url" :alt="item.booking?.venue_name || 'Ảnh sân'" />
            </div>
          </div>

          <!-- FOOTER THẺ: CHỦ BÀI & THAO TÁC -->
          <footer class="card-footer">
            <div class="author-info">
              <span class="author-avatar">{{ initial(item.author?.name) }}</span>
              <div class="author-details">
                <span class="author-name">{{ item.author?.name || 'Chủ kèo' }}</span>
                <small class="join-time">Đã gửi yêu cầu: {{ timeAgo(item.created_at) }}</small>
              </div>
            </div>

            <div class="action-buttons">
              <!-- NÚT MỞ NHÓM CHAT (NẾU ĐÃ ĐƯỢC DUYỆT) -->
              <router-link
                v-if="item.status === 'approved' && item.group_chat_id"
                class="sg-client-button sg-client-button--primary"
                :to="{ name: 'client-messages', query: { conversation_id: item.group_chat_id } }"
              >
                <AppIcon name="messageCircle" size="15" />
                <span>Nhóm chat</span>
              </router-link>

              <!-- NÚT XEM CHI TIẾT -->
              <router-link
                class="sg-client-button sg-client-button--secondary"
                :to="{ name: 'ClientMatchmakingRequestDetail', params: { id: item.id } }"
              >
                <span>Chi tiết</span>
              </router-link>

              <!-- NÚT RÚT YÊU CẦU -->
              <button
                v-if="item.can_leave"
                type="button"
                class="sg-client-button sg-client-button--danger"
                :disabled="leavingId === item.id"
                @click="openLeaveConfirm(item)"
              >
                <span>{{ item.status === 'approved' ? 'Rút khỏi kèo' : 'Hủy yêu cầu' }}</span>
              </button>
            </div>
          </footer>
        </article>
      </div>

      <!-- MODAL XÁC NHẬN RÚT YÊU CẦU -->
      <ConfirmModal
        v-model="showLeaveConfirm"
        :title="confirmModalTitle"
        :message="confirmModalMessage"
        confirm-text="Xác nhận rút"
        cancel-text="Giữ lại"
        type="danger"
        @confirm="handleConfirmLeave"
        @cancel="targetLeaveItem = null"
      />
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const status = ref(String(route.query.status || 'all'));
const items = ref([]);
const loading = ref(true);
const error = ref('');
const leavingId = ref(null);

const showLeaveConfirm = ref(false);
const targetLeaveItem = ref(null);

const tabs = [
  { value: 'all', label: 'Tất cả' },
  { value: 'pending', label: 'Chờ duyệt' },
  { value: 'approved', label: 'Đã duyệt' },
  { value: 'rejected', label: 'Bị từ chối' },
  { value: 'cancelled', label: 'Đã kết thúc' },
];

const currentTabLabel = computed(() => {
  return tabs.find((t) => t.value === status.value)?.label || 'Tất cả';
});

const confirmModalTitle = computed(() => {
  if (targetLeaveItem.value?.status === 'approved') {
    return 'Rút khỏi buổi giao lưu';
  }
  return 'Hủy yêu cầu xin tham gia';
});

const confirmModalMessage = computed(() => {
  if (targetLeaveItem.value?.status === 'approved') {
    return 'Bạn đã được chủ sân duyệt tham gia. Bạn có chắc chắn muốn rút lui không? Vị trí của bạn sẽ được nhường cho người khác.';
  }
  return 'Bạn có chắc chắn muốn hủy yêu cầu tham gia bài giao lưu này không?';
});

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const query = status.value !== 'all' ? `?status=${encodeURIComponent(status.value)}` : '';
    const response = await api(`/api/matchmaking-requests${query}`);
    items.value = response.data || [];
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải danh sách đơn tham gia.';
  } finally {
    loading.value = false;
  }
}

function changeTab(value) {
  status.value = value;
  router.replace({ query: value === 'all' ? {} : { status: value } });
}

function statusLabel(value) {
  return {
    pending: 'Chờ duyệt',
    approved: 'Đã được duyệt',
    rejected: 'Bị từ chối',
    cancelled: 'Đã kết thúc',
  }[value] || value;
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

function openLeaveConfirm(item) {
  targetLeaveItem.value = item;
  showLeaveConfirm.value = true;
}

async function handleConfirmLeave() {
  const item = targetLeaveItem.value;
  if (!item?.post_id) return;
  leavingId.value = item.id;
  try {
    await api(`/api/matchmaking-posts/${item.post_id}/leave`, { method: 'POST' });
    toast.success('Đã rút yêu cầu tham gia.');
    await load();
  } catch (err) {
    toast.error(err.message || 'Không thể rút yêu cầu.');
  } finally {
    leavingId.value = null;
    targetLeaveItem.value = null;
    showLeaveConfirm.value = false;
  }
}

watch(() => route.query.status, (value) => {
  status.value = String(value || 'all');
  load();
}, { immediate: true });
</script>

<style scoped>
.matchmaking-requests-page {
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

.requests-shell {
  width: min(1000px, calc(100% - 48px));
  margin: 0 auto;
  padding: 24px 0 64px;
}

.requests-header {
  margin-bottom: 20px;
}

.back-nav-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--community-muted);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  margin-bottom: 14px;
  transition: color 0.15s ease;
}

.back-nav-link:hover {
  color: var(--community-accent-dark);
}

.header-main-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.header-copy h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: var(--community-ink);
}

.header-copy p {
  margin: 6px 0 0;
  font-size: 13.5px;
  color: var(--community-muted);
}

/* TABS LỌC TRẠNG THÁI */
.status-tabs {
  display: flex;
  gap: 4px;
  padding: 5px;
  margin-bottom: 20px;
  overflow-x: auto;
}

.surface {
  background: var(--community-surface);
  border: 1.5px solid var(--community-line);
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

.tab-item {
  flex: 1;
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 14px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--community-muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.tab-item:hover {
  color: var(--community-ink);
  background: var(--community-soft);
}

.tab-item.is-active {
  color: var(--community-accent-dark);
  background: var(--community-accent-soft);
  font-weight: 700;
}

/* TRẠNG THÁI LOADING / LỖI / RỖNG */
.requests-state-card {
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

.requests-empty-panel {
  padding: 48px 24px;
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

.manage-empty-state svg {
  color: var(--community-accent);
  margin-bottom: 12px;
}

.manage-empty-state h3 {
  margin: 0 0 6px;
  font-size: 17px;
  font-weight: 600;
  color: var(--community-ink);
}

.manage-empty-state p {
  margin: 0 0 20px;
  font-size: 13.5px;
  color: var(--community-muted);
  max-width: 420px;
  line-height: 1.5;
}

/* DANH SÁCH THẺ ĐƠN THAM GIA */
.requests-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.request-card {
  padding: 20px;
  transition: border-color 0.15s ease;
}

.card-top-row {
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

/* NỘI DUNG CHÍNH */
.card-main-body {
  margin-bottom: 16px;
}

.venue-title {
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

.facts-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 24px;
  color: var(--community-muted);
  font-size: 13px;
  margin-bottom: 10px;
}

.fact-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.fact-item svg {
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
  max-height: 140px;
  border: 1px solid var(--community-line);
}

.card-cover-preview img {
  width: 100%;
  height: 100%;
  max-height: 140px;
  object-fit: cover;
  display: block;
}

/* FOOTER THẺ */
.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--community-line);
}

.author-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.author-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #54656f;
  color: #fff;
  display: inline-grid;
  place-items: center;
  font-size: 13px;
  font-weight: 700;
}

.author-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.author-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--community-ink);
}

.join-time {
  font-size: 11.5px;
  color: var(--community-muted);
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* NÚT THAO TÁC CHUNG */
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

.sg-client-button--danger {
  color: var(--community-danger);
  border-color: rgba(180, 35, 24, 0.2);
}

.sg-client-button--danger:hover {
  background: var(--community-danger-soft);
  border-color: var(--community-danger);
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

@media (max-width: 768px) {
  .requests-shell {
    width: 100%;
    padding: 16px 12px 48px;
  }

  .header-main-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .card-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .action-buttons {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>

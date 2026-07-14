<template>
  <div class="matchmaking-manage-page">
    <PublicNavbar />

    <main class="manage-content">
      <router-link :to="{ name: 'ClientCommunityList' }" class="back-link">
        <AppIcon name="chevronLeft" size="16" />
        Cộng đồng
      </router-link>

      <div v-if="loading" class="page-state">
        <span class="spinner" aria-hidden="true"></span>
        <p>Đang tải yêu cầu tham gia...</p>
      </div>

      <div v-else-if="error" class="page-state error" role="alert">
        <AppIcon name="alert" size="26" />
        <strong>Không thể tải bài giao lưu</strong>
        <p>{{ error }}</p>
        <button type="button" @click="fetchParticipants()">Thử lại</button>
      </div>

      <template v-else-if="post">
        <header class="page-header">
          <div>
            <span class="eyebrow">Quản lý bài giao lưu</span>
            <h1>Yêu cầu tham gia</h1>
            <p>Duyệt người chơi phù hợp với buổi giao lưu của bạn.</p>
          </div>
          <span class="post-status" :class="post.status">{{ postStatusLabel }}</span>
        </header>

        <section class="booking-summary">
          <div>
            <AppIcon name="mapPin" size="18" />
            <span><small>Cụm sân</small><strong>{{ post.venue_name }}</strong></span>
          </div>
          <div>
            <AppIcon name="clock" size="18" />
            <span><small>Thời gian</small><strong>{{ post.time }}</strong></span>
          </div>
          <div>
            <AppIcon name="users" size="18" />
            <span><small>Còn cần</small><strong>{{ post.needed_players }} người</strong></span>
          </div>
        </section>

        <section class="participants-panel">
          <header>
            <div>
              <h2>Người xin tham gia</h2>
              <p>{{ participants.length }} yêu cầu đã gửi đến bài giao lưu này.</p>
            </div>
            <button type="button" class="refresh-button" :disabled="refreshing" @click="fetchParticipants(true)">
              <AppIcon name="refresh" size="16" />
              {{ refreshing ? 'Đang tải' : 'Làm mới' }}
            </button>
          </header>

          <div v-if="!participants.length" class="empty-state">
            <AppIcon name="users" size="28" />
            <strong>Chưa có yêu cầu tham gia</strong>
            <span>Các yêu cầu mới sẽ xuất hiện tại đây.</span>
          </div>

          <div v-else class="participant-list">
            <article v-for="participant in participants" :key="participant.user_id" class="participant-card">
              <div class="participant-info">
                <div class="avatar">
                  <img v-if="participant.avatar" :src="getAvatarUrl(participant.avatar)" :alt="participant.name" />
                  <span v-else>{{ initial(participant.name) }}</span>
                </div>
                <div>
                  <router-link :to="`/user/${participant.user_id}`">{{ participant.name }}</router-link>
                  <small>Gửi lúc {{ formatTime(participant.created_at) }}</small>
                </div>
              </div>

              <div v-if="participant.status === 'pending'" class="participant-actions">
                <button
                  type="button"
                  class="approve-button"
                  :disabled="processingId === participant.user_id || post.status !== 'open'"
                  @click="approve(participant.user_id)"
                >
                  <AppIcon name="check" size="16" />
                  Đồng ý
                </button>
                <button
                  type="button"
                  class="reject-button"
                  :disabled="processingId === participant.user_id"
                  @click="reject(participant.user_id)"
                >
                  <AppIcon name="close" size="16" />
                  Từ chối
                </button>
              </div>
              <span v-else class="participant-status" :class="participant.status">
                {{ participantStatusLabel(participant.status) }}
              </span>
            </article>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';

const route = useRoute();
const toast = useToast();
const loading = ref(true);
const refreshing = ref(false);
const error = ref('');
const post = ref(null);
const participants = ref([]);
const processingId = ref(null);

const postStatusLabel = computed(() => ({
  open: 'Đang tuyển',
  full: 'Đã đủ người',
  closed: 'Đã đóng',
  cancelled: 'Đã hủy',
}[post.value?.status] || post.value?.status || 'Không xác định'));

async function fetchParticipants(silent = false) {
  if (silent) refreshing.value = true;
  else loading.value = true;
  error.value = '';
  try {
    const response = await api(`/api/matchmaking-posts/${route.params.id}/participants`);
    post.value = response.post;
    participants.value = Array.isArray(response.participants) ? response.participants : [];
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải dữ liệu.';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

async function updateParticipant(userId, action) {
  if (processingId.value) return;
  processingId.value = userId;
  try {
    await api(`/api/matchmaking-posts/${route.params.id}/participants/${userId}/${action}`, { method: 'POST' });
    toast.success(action === 'approve' ? 'Đã chấp nhận người chơi.' : 'Đã từ chối yêu cầu.');
    await fetchParticipants(true);
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể cập nhật yêu cầu.');
  } finally {
    processingId.value = null;
  }
}

const approve = (userId) => updateParticipant(userId, 'approve');
const reject = (userId) => updateParticipant(userId, 'reject');

function participantStatusLabel(status) {
  return {
    approved: 'Đã chấp nhận',
    rejected: 'Đã từ chối',
    cancelled: 'Đã rút yêu cầu',
  }[status] || status;
}

function formatTime(value) {
  if (!value) return 'không rõ';
  return new Date(value).toLocaleString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function getAvatarUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

function initial(name) {
  return String(name || 'N').charAt(0).toUpperCase();
}

watch(() => route.params.id, () => fetchParticipants());
onMounted(() => fetchParticipants());
</script>

<style scoped>
.matchmaking-manage-page {
  min-height: 100vh;
  background: var(--admin-bg);
  color: var(--admin-text);
}

.manage-content {
  width: min(100%, 980px);
  margin: 0 auto;
  padding: 92px 24px 56px;
}

.back-link,
.page-header,
.booking-summary > div,
.participants-panel > header,
.participant-card,
.participant-info,
.participant-actions,
.refresh-button,
.approve-button,
.reject-button {
  display: flex;
  align-items: center;
}

.back-link {
  width: fit-content;
  gap: 5px;
  margin-bottom: 18px;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
  font-weight: 500;
  text-decoration: none;
}

.back-link:hover {
  color: var(--admin-primary-dark);
}

.page-header {
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 18px;
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

.page-header h1,
.participants-panel h2 {
  margin: 0;
}

.page-header h1 {
  font-size: var(--admin-font-size-2xl);
}

.page-header p,
.participants-panel header p {
  margin: 6px 0 0;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
}

.post-status,
.participant-status {
  flex: 0 0 auto;
  padding: 6px 10px;
  border-radius: 999px;
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
  font-weight: 600;
}

.post-status.open,
.participant-status.approved {
  background: var(--admin-success-soft);
  color: var(--admin-success-text);
}

.participant-status.rejected,
.post-status.cancelled {
  background: color-mix(in srgb, var(--admin-danger) 10%, var(--admin-surface));
  color: var(--admin-danger-text);
}

.booking-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}

.booking-summary > div {
  gap: 10px;
  padding: 14px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
  color: var(--admin-primary-dark);
}

.booking-summary span {
  display: grid;
  gap: 3px;
}

.booking-summary small {
  color: var(--admin-muted);
  font-size: var(--admin-font-size-xs);
}

.booking-summary strong {
  color: var(--admin-text);
  font-size: var(--admin-font-size-base);
}

.participants-panel {
  padding: 18px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
}

.participants-panel > header {
  justify-content: space-between;
  gap: 14px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--admin-border-soft);
}

.participants-panel h2 {
  font-size: var(--admin-font-size-lg);
}

.refresh-button,
.approve-button,
.reject-button,
.page-state button {
  justify-content: center;
  gap: 6px;
  min-height: 36px;
  padding: 8px 11px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  font-size: var(--admin-font-size-sm);
  font-weight: 500;
  cursor: pointer;
}

.refresh-button,
.reject-button {
  background: var(--admin-surface);
  color: var(--admin-muted);
}

.approve-button,
.page-state button {
  background: var(--admin-primary);
  color: var(--admin-primary-text);
}

.reject-button {
  border-color: color-mix(in srgb, var(--admin-danger) 35%, var(--admin-border));
  color: var(--admin-danger-text);
}

.refresh-button:disabled,
.approve-button:disabled,
.reject-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.participant-list {
  display: grid;
  gap: 10px;
  padding-top: 14px;
}

.participant-card {
  justify-content: space-between;
  gap: 14px;
  padding: 12px;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-bg-soft);
}

.participant-info {
  min-width: 0;
  gap: 11px;
}

.avatar {
  display: grid;
  width: 44px;
  height: 44px;
  flex: 0 0 auto;
  overflow: hidden;
  place-items: center;
  border-radius: 50%;
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  font-weight: 600;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.participant-info > div:last-child {
  display: grid;
  min-width: 0;
  gap: 4px;
}

.participant-info a {
  overflow: hidden;
  color: var(--admin-text);
  font-size: var(--admin-font-size-base);
  font-weight: 600;
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.participant-info small {
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
}

.participant-actions {
  flex: 0 0 auto;
  gap: 8px;
}

.empty-state,
.page-state {
  display: grid;
  place-items: center;
  align-content: center;
  gap: 9px;
  color: var(--admin-muted);
  text-align: center;
}

.empty-state {
  min-height: 210px;
}

.empty-state span,
.page-state p {
  margin: 0;
  font-size: var(--admin-font-size-base);
}

.page-state {
  min-height: 420px;
}

.page-state.error {
  color: var(--admin-danger-text);
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
  .manage-content {
    padding: 84px 16px 40px;
  }

  .page-header,
  .participant-card {
    align-items: flex-start;
  }

  .booking-summary {
    grid-template-columns: 1fr;
  }

  .participant-card {
    flex-direction: column;
  }

  .participant-actions {
    width: 100%;
  }

  .participant-actions button {
    flex: 1;
  }
}
</style>

<template>
  <div class="matchmaking-manage-page sg-client-page">
    <PublicNavbar />

    <main class="sg-client-reading-shell manage-content">
      <router-link :to="{ name: 'ClientCommunityList' }" class="back-link">
        <AppIcon name="chevronLeft" size="16" />
        Cộng đồng
      </router-link>

      <div v-if="loading" class="sg-client-state page-state" aria-live="polite">
        <span class="spinner" aria-hidden="true"></span>
        <p>Đang tải yêu cầu tham gia...</p>
      </div>

      <div v-else-if="error" class="sg-client-state page-state page-state--error" role="alert">
        <AppIcon name="alert" size="26" />
        <strong>Không thể tải bài giao lưu</strong>
        <p>{{ error }}</p>
        <button type="button" class="sg-client-button" @click="fetchParticipants()">Thử lại</button>
      </div>

      <template v-else-if="post">
        <header class="page-header">
          <div>
            <span class="sg-client-eyebrow">Quản lý bài giao lưu</span>
            <h1>Yêu cầu tham gia</h1>
            <p>Duyệt người chơi phù hợp với buổi giao lưu của bạn.</p>
          </div>
          <span class="post-status" :class="`post-status--${displayPostStatus}`">{{ postStatusLabel }}</span>
        </header>

        <section class="booking-summary" aria-label="Thông tin buổi giao lưu">
          <article class="sg-client-card">
            <AppIcon name="mapPin" size="19" />
            <span><small>Cụm sân</small><strong>{{ post.venue_name || 'Chưa xác định' }}</strong></span>
          </article>
          <article class="sg-client-card">
            <AppIcon name="clock" size="19" />
            <span><small>Thời gian</small><strong>{{ post.time || 'Chưa xác định' }}</strong></span>
          </article>
          <article class="sg-client-card">
            <AppIcon name="users" size="19" />
            <span><small>Còn cần</small><strong>{{ post.needed_players }} người</strong></span>
          </article>
        </section>

        <aside class="decision-guide" :class="{ 'decision-guide--locked': !canApprove }">
          <AppIcon :name="canApprove ? 'circleCheck' : 'alert'" size="20" />
          <div>
            <strong>{{ decisionGuideTitle }}</strong>
            <p>{{ decisionGuideMessage }}</p>
          </div>
        </aside>

        <section class="participants-panel sg-client-card">
          <header class="panel-header">
            <div>
              <h2>Người xin tham gia</h2>
              <p>{{ participants.length }} yêu cầu đã gửi đến bài giao lưu này.</p>
            </div>
            <button
              type="button"
              class="sg-client-button refresh-button"
              :disabled="refreshing"
              @click="fetchParticipants(true)"
            >
              <AppIcon name="refresh" size="16" :class="{ rotating: refreshing }" />
              {{ refreshing ? 'Đang tải' : 'Làm mới' }}
            </button>
          </header>

          <nav v-if="participants.length" class="request-filters" aria-label="Lọc yêu cầu tham gia">
            <button
              v-for="filter in requestFilters"
              :key="filter.value"
              type="button"
              :class="{ active: activeFilter === filter.value }"
              :aria-pressed="activeFilter === filter.value"
              @click="activeFilter = filter.value"
            >
              <span>{{ filter.label }}</span>
              <strong>{{ filter.count }}</strong>
            </button>
          </nav>

          <div v-if="!participants.length" class="empty-state">
            <AppIcon name="users" size="28" />
            <strong>Chưa có yêu cầu tham gia</strong>
            <span>Các yêu cầu mới sẽ xuất hiện tại đây.</span>
          </div>

          <div v-else-if="!filteredParticipants.length" class="empty-state empty-state--compact">
            <AppIcon name="filter" size="24" />
            <strong>Không có yêu cầu ở trạng thái này</strong>
            <button type="button" class="sg-client-button" @click="activeFilter = 'all'">Xem tất cả</button>
          </div>

          <div v-else class="participant-list">
            <article
              v-for="participant in filteredParticipants"
              :key="participant.user_id"
              class="participant-card"
            >
              <div class="participant-main">
                <div class="participant-info">
                  <div class="avatar" aria-hidden="true">
                    <img
                      v-if="participant.avatar"
                      :src="getAvatarUrl(participant.avatar)"
                      :alt="participant.name"
                    />
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
                    class="sg-client-button sg-client-button--primary"
                    :disabled="isProcessing(participant.user_id) || !canApprove"
                    @click="approve(participant.user_id)"
                  >
                    <AppIcon name="check" size="16" />
                    {{ isProcessing(participant.user_id, 'approve') ? 'Đang duyệt...' : 'Đồng ý' }}
                  </button>
                  <button
                    type="button"
                    class="sg-client-button sg-client-button--danger"
                    :disabled="isProcessing(participant.user_id)"
                    @click="openRejectConfirm(participant.user_id)"
                  >
                    <AppIcon name="close" size="16" />
                    Từ chối
                  </button>
                </div>

                <span v-else class="participant-status" :class="`participant-status--${participant.status}`">
                  <AppIcon :name="participantStatusIcon(participant.status)" size="15" />
                  {{ participantStatusLabel(participant.status) }}
                </span>
              </div>

              <div
                v-if="isRejectConfirmOpen(participant.user_id)"
                class="reject-confirm"
                role="alert"
                aria-live="polite"
              >
                <div>
                  <strong>Xác nhận từ chối {{ participant.name }}?</strong>
                  <span>Yêu cầu sẽ chuyển sang trạng thái “Đã từ chối”.</span>
                </div>
                <div class="reject-confirm-actions">
                  <button
                    type="button"
                    class="sg-client-button"
                    :disabled="isProcessing(participant.user_id)"
                    @click="closeRejectConfirm"
                  >
                    Hủy
                  </button>
                  <button
                    type="button"
                    class="sg-client-button sg-client-button--danger"
                    :disabled="isProcessing(participant.user_id)"
                    @click="reject(participant.user_id)"
                  >
                    <AppIcon name="close" size="16" />
                    {{ isProcessing(participant.user_id, 'reject') ? 'Đang từ chối...' : 'Xác nhận từ chối' }}
                  </button>
                </div>
              </div>
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
const processingAction = ref('');
const confirmRejectId = ref(null);
const activeFilter = ref('all');

const bookingStartAt = computed(() => {
  const match = String(post.value?.time || '').match(/(\d{1,2}):(\d{2})\s*-\s*(\d{2})\/(\d{2})\/(\d{4})/);
  if (!match) return null;
  const [, hour, minute, day, month, year] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute));
  return Number.isNaN(date.getTime()) ? null : date;
});

const isSessionExpired = computed(() => Boolean(
  bookingStartAt.value && bookingStartAt.value.getTime() <= Date.now(),
));

const canApprove = computed(() => post.value?.status === 'open'
  && Number(post.value?.needed_players || 0) > 0
  && !isSessionExpired.value);

const displayPostStatus = computed(() => isSessionExpired.value ? 'expired' : post.value?.status);

const postStatusLabel = computed(() => {
  if (isSessionExpired.value) return 'Đã kết thúc';
  return {
    open: 'Đang tuyển',
    full: 'Đã đủ người',
    closed: 'Đã đóng',
    cancelled: 'Đã hủy',
  }[post.value?.status] || post.value?.status || 'Không xác định';
});

const requestCounts = computed(() => participants.value.reduce((counts, participant) => {
  const status = participant.status || 'pending';
  counts[status] = (counts[status] || 0) + 1;
  return counts;
}, { pending: 0, approved: 0, rejected: 0, cancelled: 0 }));

const requestFilters = computed(() => [
  { value: 'all', label: 'Tất cả', count: participants.value.length },
  { value: 'pending', label: 'Chờ duyệt', count: requestCounts.value.pending },
  { value: 'approved', label: 'Đã đồng ý', count: requestCounts.value.approved },
  { value: 'rejected', label: 'Đã từ chối', count: requestCounts.value.rejected },
]);

const orderedParticipants = computed(() => {
  const order = { pending: 0, approved: 1, rejected: 2, cancelled: 3 };
  return [...participants.value].sort((a, b) => {
    const statusOrder = (order[a.status] ?? 4) - (order[b.status] ?? 4);
    if (statusOrder !== 0) return statusOrder;
    return new Date(b.created_at || 0) - new Date(a.created_at || 0);
  });
});

const filteredParticipants = computed(() => activeFilter.value === 'all'
  ? orderedParticipants.value
  : orderedParticipants.value.filter((participant) => participant.status === activeFilter.value));

const decisionGuideTitle = computed(() => {
  if (isSessionExpired.value) return 'Buổi giao lưu đã qua thời gian diễn ra';
  return canApprove.value
    ? 'Ưu tiên xử lý các yêu cầu đang chờ'
    : 'Bài giao lưu hiện không nhận thêm người';
});

const decisionGuideMessage = computed(() => {
  if (isSessionExpired.value) {
    return 'Không thể duyệt thêm người cho lịch đã kết thúc. Bạn vẫn có thể từ chối yêu cầu còn tồn để hoàn tất danh sách.';
  }
  return canApprove.value
    ? 'Khi đồng ý, số người còn cần sẽ giảm. Hãy kiểm tra hồ sơ trước khi xác nhận.'
    : 'Nút đồng ý được khóa để tránh vượt quá số người cần. Bạn vẫn có thể xem lại lịch sử xử lý.';
});

async function fetchParticipants(silent = false) {
  if (silent) refreshing.value = true;
  else {
    loading.value = true;
    post.value = null;
    participants.value = [];
  }
  error.value = '';

  try {
    const response = await api(`/api/matchmaking-posts/${route.params.id}/participants`);
    post.value = response.post;
    participants.value = Array.isArray(response.participants) ? response.participants : [];
  } catch (requestError) {
    const message = requestError.message || 'Không thể tải dữ liệu.';
    if (silent) toast.error(message);
    else error.value = message;
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

async function updateParticipant(userId, action) {
  if (processingId.value !== null) return;
  processingId.value = userId;
  processingAction.value = action;

  try {
    await api(`/api/matchmaking-posts/${route.params.id}/participants/${userId}/${action}`, { method: 'POST' });
    toast.success(action === 'approve' ? 'Đã chấp nhận người chơi.' : 'Đã từ chối yêu cầu.');
    closeRejectConfirm();
    await fetchParticipants(true);
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể cập nhật yêu cầu.');
  } finally {
    processingId.value = null;
    processingAction.value = '';
  }
}

function approve(userId) {
  updateParticipant(userId, 'approve');
}

function reject(userId) {
  updateParticipant(userId, 'reject');
}

function openRejectConfirm(userId) {
  if (processingId.value !== null) return;
  confirmRejectId.value = userId;
}

function closeRejectConfirm() {
  confirmRejectId.value = null;
}

function isRejectConfirmOpen(userId) {
  return String(confirmRejectId.value ?? '') === String(userId ?? '');
}

function isProcessing(userId, action = '') {
  const sameUser = String(processingId.value ?? '') === String(userId ?? '');
  return sameUser && (!action || processingAction.value === action);
}

function participantStatusLabel(status) {
  return {
    approved: 'Đã chấp nhận',
    rejected: 'Đã từ chối',
    cancelled: 'Đã rút yêu cầu',
    pending: 'Đang chờ duyệt',
  }[status] || status || 'Không xác định';
}

function participantStatusIcon(status) {
  return {
    approved: 'circleCheck',
    rejected: 'circleX',
    cancelled: 'clock',
    pending: 'clock',
  }[status] || 'alert';
}

function formatTime(value) {
  if (!value) return 'không rõ';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'không rõ';
  return date.toLocaleString('vi-VN', {
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
  return String(name || 'N').trim().charAt(0).toUpperCase();
}

function resetAndFetch() {
  activeFilter.value = 'all';
  confirmRejectId.value = null;
  fetchParticipants();
}

watch(() => route.params.id, resetAndFetch);
onMounted(fetchParticipants);
</script>

<style scoped src="../../../../css/client-matchmaking-manage.css"></style>

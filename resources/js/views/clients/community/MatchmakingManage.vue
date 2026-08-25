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
          <div class="manage-actions">
            <button v-if="canManagePost" type="button" class="sg-client-button" @click="openEditor"><AppIcon name="pencil" size="16" /> Sửa bài</button>
            <button v-if="canManagePost" type="button" class="sg-client-button sg-client-button--danger" :disabled="savingPost" @click="closePost"><AppIcon name="close" size="16" /> Đóng tuyển</button>
            <router-link v-if="post.group_chat_id" class="sg-client-button" :to="{ name: 'client-messages', query: { conversation_id: post.group_chat_id } }"><AppIcon name="messageCircle" size="16" /> Mở nhóm chat</router-link>
            <button v-if="canDissolve" type="button" class="sg-client-button sg-client-button--danger" :disabled="savingPost" @click="dissolveGroup"><AppIcon name="trash" size="16" /> Giải tán nhóm</button>
          </div>
        </header>

        <section class="booking-summary" aria-label="Thông tin buổi giao lưu">
          <article class="sg-client-card">
            <AppIcon :name="post.sport_icon || 'activity'" size="19" />
            <span><small>{{ post.sport_name || 'Thể thao' }}</small><strong>{{ post.court_type_name || 'Sân tiêu chuẩn' }}<template v-if="post.court_name"> ({{ post.court_name }})</template></strong></span>
          </article>
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
            <span><small>Còn cần · Trình độ</small><strong>{{ post.needed_players }} người · {{ skillLabel(post.skill_level) }}</strong></span>
          </article>
          <article class="sg-client-card">
            <AppIcon name="shield" size="19" />
            <span><small>Chi phí</small><strong>{{ costLabel(post) }}</strong></span>
          </article>
        </section>

        <section v-if="post.image_url || post.description" class="post-preview-card sg-client-card">
          <div v-if="post.image_url" class="post-preview-cover">
            <img :src="assetUrl(post.image_url)" alt="Ảnh bài giao lưu" />
          </div>
          <div v-if="post.description" class="post-preview-desc">
            <small>Mô tả bài đăng</small>
            <p>{{ post.description }}</p>
          </div>
        </section>

        <form v-if="editing" class="post-editor sg-client-card" @submit.prevent="savePost">
          <div>
            <h2>Chỉnh sửa nội dung</h2>
            <p>Cập nhật mô tả để người chơi hiểu rõ trình độ và nội dung giao lưu.</p>
          </div>
          <textarea v-model.trim="editContent" class="sg-client-input" rows="4" minlength="10" maxlength="2000" required></textarea>
          <div class="post-editor-actions">
            <button type="button" class="sg-client-button" @click="editing = false">Hủy</button>
            <button type="submit" class="sg-client-button sg-client-button--primary" :disabled="savingPost">{{ savingPost ? 'Đang lưu...' : 'Lưu thay đổi' }}</button>
          </div>
        </form>

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

    <!-- MODAL XÁC NHẬN GIẢI TÁN NHÓM -->
    <div v-if="showDissolveConfirmModal" class="sg-modal-overlay" @click.self="showDissolveConfirmModal = false">
      <div class="sg-confirm-card" role="dialog" aria-modal="true">
        <div class="sg-confirm-icon sg-confirm-icon--danger">
          <AppIcon name="alert" size="28" />
        </div>
        <h3>Giải tán nhóm giao lưu?</h3>
        <p>Cuộc trò chuyện nhóm sẽ bị xóa và bài giao lưu sẽ được đóng. Hành động này không thể hoàn tác.</p>
        <div class="sg-confirm-actions">
          <button type="button" class="sg-client-button" :disabled="savingPost" @click="showDissolveConfirmModal = false">
            Hủy bỏ
          </button>
          <button type="button" class="sg-client-button sg-client-button--danger" :disabled="savingPost" @click="confirmDissolveGroup">
            {{ savingPost ? 'Đang giải tán...' : 'Xác nhận giải tán' }}
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL XÁC NHẬN ĐÓNG BÀI TUYỂN -->
    <div v-if="showCloseConfirmModal" class="sg-modal-overlay" @click.self="showCloseConfirmModal = false">
      <div class="sg-confirm-card" role="dialog" aria-modal="true">
        <div class="sg-confirm-icon sg-confirm-icon--warning">
          <AppIcon name="alert" size="28" />
        </div>
        <h3>Đóng tuyển giao lưu?</h3>
        <p>Người chơi mới sẽ không thể gửi thêm yêu cầu tham gia vào buổi giao lưu này nữa.</p>
        <div class="sg-confirm-actions">
          <button type="button" class="sg-client-button" :disabled="savingPost" @click="showCloseConfirmModal = false">
            Hủy bỏ
          </button>
          <button type="button" class="sg-client-button sg-client-button--danger" :disabled="savingPost" @click="confirmClosePost">
            {{ savingPost ? 'Đang đóng...' : 'Xác nhận đóng bài' }}
          </button>
        </div>
      </div>
    </div>
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
const editing = ref(false);
const editContent = ref('');
const savingPost = ref(false);
const showDissolveConfirmModal = ref(false);
const showCloseConfirmModal = ref(false);

function parseBookingAt(dateValue, timeValue) {
  const dateMatch = String(dateValue || '').match(/(\d{4})-(\d{2})-(\d{2})/);
  const timeMatch = String(timeValue || '').match(/(\d{1,2}):(\d{2})/);
  if (!dateMatch || !timeMatch) return null;
  const [, year, month, day] = dateMatch;
  const [, hour, minute] = timeMatch;
  const date = new Date(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute));
  return Number.isNaN(date.getTime()) ? null : date;
}

const fallbackBookingAt = computed(() => {
  const match = String(post.value?.time || '').match(/(\d{1,2}):(\d{2})\s*-\s*(?:(\d{1,2}):(\d{2})\s*·\s*)?(\d{2})\/(\d{2})\/(\d{4})/);
  if (!match) return null;
  const [, hour, minute, , , day, month, year] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute));
  return Number.isNaN(date.getTime()) ? null : date;
});

const bookingStartAt = computed(() => parseBookingAt(post.value?.booking_date, post.value?.start_time) || fallbackBookingAt.value);
const bookingEndAt = computed(() => parseBookingAt(post.value?.booking_date, post.value?.end_time) || bookingStartAt.value);

const isSessionExpired = computed(() => Boolean(
  bookingEndAt.value && bookingEndAt.value.getTime() <= Date.now(),
));

const canApprove = computed(() => post.value?.status === 'open'
  && Number(post.value?.needed_players || 0) > 0
  && !isSessionExpired.value);

const canManagePost = computed(() => ['open', 'full'].includes(post.value?.status) && !isSessionExpired.value);
const canDissolve = computed(() => Boolean(post.value?.group_chat_id || post.value?.status !== 'closed'));

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
  { value: 'cancelled', label: 'Đã hủy', count: requestCounts.value.cancelled },
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
    return 'Các yêu cầu chờ duyệt đã được tự động hủy khi buổi giao lưu kết thúc. Bạn vẫn có thể xem lại toàn bộ lịch sử xử lý.';
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

function openEditor() {
  editContent.value = post.value?.description || '';
  editing.value = true;
}

async function savePost() {
  if (savingPost.value) return;
  savingPost.value = true;
  try {
    await api(`/api/matchmaking-posts/${route.params.id}`, { method: 'PATCH', body: JSON.stringify({ content: editContent.value }) });
    toast.success('Đã cập nhật bài giao lưu.');
    editing.value = false;
    await fetchParticipants(true);
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể cập nhật bài giao lưu.');
  } finally {
    savingPost.value = false;
  }
}

function closePost() {
  showCloseConfirmModal.value = true;
}

async function confirmClosePost() {
  if (savingPost.value) return;
  savingPost.value = true;
  try {
    await api(`/api/matchmaking-posts/${route.params.id}`, { method: 'DELETE' });
    showCloseConfirmModal.value = false;
    await fetchParticipants(true);
  } catch (requestError) {
    console.error('Không thể đóng bài giao lưu', requestError);
  } finally {
    savingPost.value = false;
  }
}

function dissolveGroup() {
  showDissolveConfirmModal.value = true;
}

async function confirmDissolveGroup() {
  if (savingPost.value) return;
  savingPost.value = true;
  try {
    await api(`/api/matchmaking-posts/${route.params.id}/group/dissolve`, { method: 'POST' });
    showDissolveConfirmModal.value = false;
    await fetchParticipants(true);
  } catch (requestError) {
    console.error('Không thể giải tán nhóm giao lưu', requestError);
  } finally {
    savingPost.value = false;
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

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
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

<style scoped>
.post-preview-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
}

.post-preview-cover {
  width: 100%;
  max-height: 260px;
  border-radius: 8px;
  overflow: hidden;
  background: #f8fafc;
}

.post-preview-cover img {
  width: 100%;
  height: 100%;
  max-height: 260px;
  object-fit: cover;
  display: block;
}

.post-preview-desc small {
  display: block;
  font-size: 11.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #64748b;
  margin-bottom: 4px;
}

.post-preview-desc p {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.55;
  color: #1e293b;
  white-space: pre-line;
}
.matchmaking-manage-page {
  min-height: 100vh;
  background: #f5f7f6;
  color: #10251a;
}

.manage-content {
  width: min(100% - 40px, 1120px);
  margin: 0 auto;
  padding: 30px 0 72px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 18px;
  color: #166534;
  font-size: 13px;
  font-weight: 700;
}

.page-header {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: start;
  gap: 14px 20px;
  margin-bottom: 20px;
}

.sg-client-eyebrow {
  display: block;
  margin-bottom: 7px;
  color: #15803d;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.page-header h1 {
  margin: 0;
  color: #10251a;
  font-size: clamp(25px, 3vw, 34px);
  font-weight: 700;
  letter-spacing: -.02em;
  line-height: 1.15;
}

.page-header p {
  margin: 8px 0 0;
  color: #64756b;
  font-size: 14px;
}

.post-status {
  align-self: center;
  padding: 7px 11px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.post-status--open {
  background: #dcfce7;
  color: #166534;
}

.post-status--full {
  background: #fef3c7;
  color: #92400e;
}

.post-status--closed,
.post-status--expired {
  background: #e2e8f0;
  color: #475569;
}

.manage-actions {
  grid-column: 1 / -1;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.sg-client-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 13px;
  border: 1px solid #d3e0d7;
  border-radius: 8px;
  background: #ffffff;
  color: #31453a;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: border-color .16s ease, background .16s ease, color .16s ease;
}

.sg-client-button:hover:not(:disabled) {
  border-color: #84b991;
  background: #f0fdf4;
}

.sg-client-button--primary {
  border-color: #15803d;
  background: #15803d;
  color: #ffffff;
}

.sg-client-button--primary:hover:not(:disabled) {
  border-color: #166534;
  background: #166534;
}

.sg-client-button--danger {
  border-color: #fecaca;
  color: #b91c1c;
}

.sg-client-button--danger:hover:not(:disabled) {
  border-color: #fca5a5;
  background: #fff7f7;
}

.sg-client-button:disabled {
  cursor: not-allowed;
  opacity: .58;
}

.booking-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.sg-client-card {
  border: 1px solid #dbe5de;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 7px 24px rgba(15, 23, 42, .04);
}

.booking-summary .sg-client-card {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  min-width: 0;
  padding: 16px;
  color: #15803d;
}

.booking-summary span {
  display: grid;
  min-width: 0;
  gap: 4px;
}

.booking-summary small {
  color: #718178;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
}

.booking-summary strong {
  overflow: hidden;
  color: #1d3326;
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.decision-guide {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  background: #f0fdf4;
  color: #166534;
}

.decision-guide--locked {
  border-color: #e2e8f0;
  background: #f8fafc;
  color: #64748b;
}

.decision-guide strong,
.decision-guide p {
  display: block;
  margin: 0;
}

.decision-guide strong {
  font-size: 13px;
  font-weight: 700;
}

.decision-guide p {
  margin-top: 3px;
  color: #5f7367;
  font-size: 12.5px;
  line-height: 1.5;
}

.participants-panel {
  overflow: hidden;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 20px 20px 14px;
  border-bottom: 1px solid #edf2ee;
}

.panel-header h2 {
  margin: 0;
  color: #1d3326;
  font-size: 18px;
  font-weight: 700;
}

.panel-header p {
  margin: 5px 0 0;
  color: #718178;
  font-size: 13px;
}

.refresh-button {
  min-width: 94px;
}

.rotating {
  animation: manage-spin .8s linear infinite;
}

@keyframes manage-spin {
  to { transform: rotate(360deg); }
}

.request-filters {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding: 12px 20px;
  border-bottom: 1px solid #edf2ee;
  background: #fbfdfb;
}

.request-filters button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 34px;
  padding: 0 10px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: transparent;
  color: #607268;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.request-filters button strong {
  display: inline-grid;
  min-width: 20px;
  min-height: 20px;
  place-items: center;
  border-radius: 999px;
  background: #e8f0ea;
  color: #476050;
  font-size: 11px;
}

.request-filters button.active {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #166534;
}

.participant-list {
  display: grid;
  gap: 0;
}

.participant-card {
  padding: 17px 20px;
  border-bottom: 1px solid #edf2ee;
}

.participant-card:last-child {
  border-bottom: 0;
}

.participant-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.participant-info {
  display: flex;
  align-items: center;
  min-width: 0;
  gap: 11px;
}

.participant-info .avatar {
  display: grid;
  width: 42px;
  height: 42px;
  flex: 0 0 auto;
  place-items: center;
  overflow: hidden;
  border-radius: 50%;
  background: #dcfce7;
  color: #166534;
  font-size: 14px;
  font-weight: 700;
}

.participant-info .avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.participant-info > div:last-child {
  display: grid;
  min-width: 0;
  gap: 3px;
}

.participant-info a {
  overflow: hidden;
  color: #1d3326;
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.participant-info small {
  color: #7b8b82;
  font-size: 12px;
}

.participant-actions,
.reject-confirm-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 7px;
}

.participant-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.participant-status--approved {
  background: #dcfce7;
  color: #166534;
}

.participant-status--rejected,
.participant-status--cancelled {
  background: #fef2f2;
  color: #b91c1c;
}

.participant-status--pending {
  background: #fef3c7;
  color: #92400e;
}

.reject-confirm {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-top: 13px;
  padding: 12px;
  border: 1px solid #fecaca;
  border-radius: 9px;
  background: #fff7f7;
}

.reject-confirm strong,
.reject-confirm span {
  display: block;
}

.reject-confirm strong {
  color: #991b1b;
  font-size: 13px;
}

.reject-confirm span {
  margin-top: 3px;
  color: #7f1d1d;
  font-size: 12px;
}

.post-editor {
  display: grid;
  gap: 12px;
  margin-bottom: 16px;
  padding: 18px;
}

.post-editor h2 {
  margin: 0;
  color: #1d3326;
  font-size: 16px;
  font-weight: 700;
}

.post-editor p {
  margin: 5px 0 0;
  color: #718178;
  font-size: 13px;
}

.sg-client-input {
  width: 100%;
  min-height: 110px;
  padding: 11px 12px;
  border: 1px solid #cbdace;
  border-radius: 8px;
  background: #ffffff;
  color: #1d3326;
  font-size: 13px;
  line-height: 1.55;
  resize: vertical;
}

.sg-client-input:focus {
  border-color: #15803d;
  outline: 3px solid rgba(21, 128, 61, .13);
}

.post-editor-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.empty-state,
.page-state {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding: 52px 20px;
  text-align: center;
  color: #718178;
}

.empty-state strong,
.page-state strong {
  color: #30483a;
  font-size: 15px;
}

.empty-state span,
.page-state p {
  margin: 0;
  color: #7b8b82;
  font-size: 13px;
}

.page-state--error {
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fffafa;
  color: #b91c1c;
}

.page-state--error p {
  color: #7f1d1d;
}

@media (max-width: 760px) {
  .manage-content {
    width: min(100% - 24px, 620px);
    padding-top: 20px;
  }

  .page-header {
    grid-template-columns: 1fr;
  }

  .post-status {
    justify-self: start;
  }

  .booking-summary {
    grid-template-columns: 1fr;
  }

  .panel-header,
  .participant-main,
  .reject-confirm {
    align-items: stretch;
    flex-direction: column;
  }

  .panel-header {
    display: flex;
  }

  .refresh-button {
    align-self: flex-start;
  }

  .participant-actions,
  .reject-confirm-actions {
    justify-content: flex-start;
  }

  .participant-actions .sg-client-button {
    flex: 1;
  }
}

/* POPUP CONFIRM MODAL */
.sg-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  animation: fadeInOverlay 0.18s ease-out;
}

.sg-confirm-card {
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  border-radius: 16px;
  padding: 28px 24px 22px;
  text-align: center;
  box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.25);
  animation: scaleUpCard 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.sg-confirm-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  margin: 0 auto 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sg-confirm-icon--danger {
  background: #fee2e2;
  color: #dc2626;
}

.sg-confirm-icon--warning {
  background: #fef3c7;
  color: #d97706;
}

.sg-confirm-card h3 {
  margin: 0 0 8px;
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
}

.sg-confirm-card p {
  margin: 0 0 24px;
  font-size: 14px;
  line-height: 1.55;
  color: #64748b;
}

.sg-confirm-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.sg-confirm-actions button {
  flex: 1;
  min-height: 42px;
}

@keyframes fadeInOverlay {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes scaleUpCard {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
</style>

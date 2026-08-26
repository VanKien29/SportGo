<template>
  <div class="matchmaking-manage-page sg-client-page">
    <PublicNavbar />

    <main class="manage-content">
      <div class="manage-breadcrumb">
        <router-link :to="{ name: 'ClientCommunityList' }" class="back-link">
          <AppIcon name="chevronLeft" size="16" />
          <span>Cộng đồng</span>
        </router-link>
      </div>

      <div v-if="loading" class="page-state" aria-live="polite">
        <span class="spinner" aria-hidden="true"></span>
        <p>Đang tải bài giao lưu...</p>
      </div>

      <div v-else-if="error" class="page-state page-state--error" role="alert">
        <AppIcon name="alert" size="26" />
        <strong>Không thể tải bài giao lưu</strong>
        <p>{{ error }}</p>
        <button type="button" class="sg-client-button" @click="fetchParticipants()">Thử lại</button>
      </div>

      <div v-else-if="post" class="manage-grid-layout">
        <!-- CỘT TRÁI: NỘI DUNG CHÍNH (MAIN COLUMN) -->
        <div class="manage-main-column">
          <!-- THẺ TỔNG QUAN BÀI GIAO LƯU (HERO CARD) -->
          <section class="manage-hero-card">
            <div class="hero-top-row">
              <div class="hero-sport-tag">
                <AppIcon :name="post.sport_icon || 'activity'" size="15" />
                <span>{{ post.sport_name || 'Thể thao' }} ({{ cleanCourtType(post.court_type_name, post.sport_name) }})</span>
              </div>
              <span class="hero-status-pill" :class="`status--${displayPostStatus}`">
                {{ postStatusLabel }}
              </span>
            </div>

            <h1 class="hero-venue-title">
              {{ post.venue_name }}
              <span v-if="post.court_name" class="hero-court-detail">({{ post.court_name }})</span>
            </h1>

            <!-- DẢI THÔNG SỐ PHẲNG (KHÔNG HỘP XÁM) -->
            <div class="hero-metrics-grid">
              <div class="metric-item">
                <span class="metric-label">Thời gian</span>
                <span class="metric-value">
                  <AppIcon name="clock" size="14" />
                  <span>{{ post.time }}</span>
                </span>
              </div>

              <div class="metric-item">
                <span class="metric-label">Số người & Trình độ</span>
                <span class="metric-value">
                  <AppIcon name="users" size="14" />
                  <span>Còn cần {{ post.needed_players }} người ({{ skillLabel(post.skill_level) }})</span>
                </span>
              </div>

              <div class="metric-item">
                <span class="metric-label">Chi phí tham gia</span>
                <span class="metric-value">
                  <AppIcon name="shield" size="14" />
                  <span>{{ costLabel(post) }}</span>
                </span>
              </div>
            </div>

            <!-- MÔ TẢ & ẢNH BÀI ĐĂNG (NẾU CÓ) -->
            <div v-if="post.image_url || post.description" class="post-detail-section">
              <div v-if="post.image_url" class="post-detail-cover">
                <img :src="assetUrl(post.image_url)" alt="Ảnh bài giao lưu" />
              </div>
              <div v-if="post.description" class="post-detail-desc">
                <span class="desc-heading">Mô tả bài đăng</span>
                <p>{{ post.description }}</p>
              </div>
            </div>
          </section>

          <!-- FORM SỬA BÀI NẾU ĐANG EDIT -->
          <form v-if="editing" class="post-editor surface" @submit.prevent="savePost">
            <div>
              <h2>Chỉnh sửa nội dung</h2>
              <p>Cập nhật mô tả để người chơi hiểu rõ trình độ và nội dung giao lưu.</p>
            </div>
            <textarea v-model.trim="editContent" class="sg-client-input" rows="4" minlength="10" maxlength="2000" required></textarea>
            <div class="post-editor-actions">
              <button type="button" class="sg-client-button" @click="editing = false">Hủy</button>
              <button type="submit" class="sg-client-button sg-client-button--primary" :disabled="savingPost">
                {{ savingPost ? 'Đang lưu...' : 'Lưu thay đổi' }}
              </button>
            </div>
          </form>

          <!-- LƯU Ý DUYỆT YÊU CẦU -->
          <aside class="decision-guide" :class="{ 'decision-guide--locked': !canApprove }">
            <AppIcon :name="canApprove ? 'circleCheck' : 'alert'" size="20" />
            <div>
              <strong>{{ decisionGuideTitle }}</strong>
              <p>{{ decisionGuideMessage }}</p>
            </div>
          </aside>

          <!-- DANH SÁCH NGƯỜI XIN THAM GIA -->
          <section class="participants-panel surface">
            <header class="panel-header">
              <div>
                <h2>Người xin tham gia</h2>
                <p>{{ participants.length }} yêu cầu đã gửi đến bài giao lưu này.</p>
              </div>
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

            <div v-if="!participants.length" class="manage-empty-state">
              <AppIcon name="users" size="32" />
              <p class="empty-title">Chưa có yêu cầu tham gia</p>
              <span class="empty-desc">Các yêu cầu mới sẽ xuất hiện tại đây và tự động cập nhật realtime.</span>
            </div>

            <div v-else-if="!filteredParticipants.length" class="manage-empty-state">
              <AppIcon name="filter" size="26" />
              <p class="empty-title">Không có yêu cầu ở trạng thái này</p>
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
                      {{ isProcessing(participant.user_id, 'reject') ? 'Đang từ chối...' : 'Xác nhận từ chối' }}
                    </button>
                  </div>
                </div>
              </article>
            </div>
          </section>
        </div>

        <!-- CỘT PHẢI: SIDEBAR TIỆN ÍCH & ĐỘI HÌNH (SIDEBAR) -->
        <aside class="manage-sidebar">
          <!-- 1. THẺ PHÍM TẮT THAO TÁC (ACTION HUB) -->
          <section class="sidebar-card surface">
            <h3 class="sidebar-title">
              <AppIcon name="settings" size="16" />
              <span>Thao tác quản lý</span>
            </h3>

            <div class="sidebar-actions-stack">
              <router-link
                v-if="post.group_chat_id"
                class="sidebar-btn sidebar-btn--primary"
                :to="{ name: 'client-messages', query: { conversation_id: post.group_chat_id } }"
              >
                <AppIcon name="messageCircle" size="17" />
                <span>Mở nhóm chat kèo</span>
              </router-link>

              <button
                v-if="canManagePost"
                type="button"
                class="sidebar-btn sidebar-btn--secondary"
                @click="openEditor"
              >
                <AppIcon name="pencil" size="15" />
                <span>Sửa nội dung bài</span>
              </button>

              <button
                v-if="canManagePost"
                type="button"
                class="sidebar-btn sidebar-btn--muted"
                :disabled="savingPost"
                @click="closePost"
              >
                <AppIcon name="close" size="15" />
                <span>Đóng nhận thêm người</span>
              </button>

              <button
                v-if="canDissolve"
                type="button"
                class="sidebar-btn sidebar-btn--danger"
                :disabled="savingPost"
                @click="dissolveGroup"
              >
                <AppIcon name="trash" size="15" />
                <span>Giải tán nhóm giao lưu</span>
              </button>
            </div>
          </section>

          <!-- 2. THẺ ĐỘI HÌNH & TIẾN ĐỘ GHÉP (ROSTER) -->
          <section class="sidebar-card surface">
            <div class="sidebar-header-row">
              <h3 class="sidebar-title">
                <AppIcon name="users" size="16" />
                <span>Đội hình hiện tại</span>
              </h3>
              <span class="roster-count-badge">
                {{ approvedParticipants.length + 1 }}/{{ totalTeamSize }}
              </span>
            </div>

            <div class="roster-progress-box">
              <div class="progress-bar-bg">
                <div class="progress-bar-fill" :style="{ width: `${rosterProgressPercent}%` }"></div>
              </div>
              <div class="progress-text-row">
                <span>{{ post.needed_players > 0 ? `Còn thiếu ${post.needed_players} người` : 'Đã đủ thành viên' }}</span>
                <strong>{{ rosterProgressPercent }}%</strong>
              </div>
            </div>

            <div class="roster-members-list">
              <!-- CHỦ PHÒNG -->
              <div class="roster-member-item">
                <div class="member-avatar member-avatar--host">
                  <img
                    v-if="post.author?.avatar"
                    :src="getAvatarUrl(post.author.avatar)"
                    :alt="post.author.name"
                  />
                  <span v-else>{{ initial(post.author?.name || 'Bạn') }}</span>
                </div>
                <div class="member-info">
                  <span class="member-name">{{ post.author?.name || 'Bạn' }}</span>
                  <span class="member-tag member-tag--host">Chủ bài</span>
                </div>
              </div>

              <!-- CÁC THÀNH VIÊN ĐÃ ĐƯỢC DUYỆT -->
              <div
                v-for="member in approvedParticipants"
                :key="member.user_id"
                class="roster-member-item"
              >
                <div class="member-avatar">
                  <img
                    v-if="member.avatar"
                    :src="getAvatarUrl(member.avatar)"
                    :alt="member.name"
                  />
                  <span v-else>{{ initial(member.name) }}</span>
                </div>
                <div class="member-info">
                  <router-link :to="`/user/${member.user_id}`" class="member-name">{{ member.name }}</router-link>
                  <span class="member-tag member-tag--member">Đã tham gia</span>
                </div>
              </div>

              <!-- CÁC SLOT TRỐNG CÒN LẠI (NẾU CÒN THIẾU) -->
              <div
                v-for="slot in Math.min(post.needed_players, 5)"
                :key="`empty-slot-${slot}`"
                class="roster-member-item roster-member-item--empty"
              >
                <div class="empty-slot-icon">
                  <AppIcon name="userPlus" size="14" />
                </div>
                <span class="empty-slot-text">Vị trí còn trống</span>
              </div>
            </div>
          </section>

          <!-- 3. THÔNG TIN SÂN & ĐỊA ĐIỂM (VENUE SNAPSHOT) -->
          <section class="sidebar-card surface">
            <h3 class="sidebar-title">
              <AppIcon name="mapPin" size="16" />
              <span>Thông tin địa điểm</span>
            </h3>

            <div class="venue-snapshot-info">
              <strong>{{ post.venue_name }}</strong>
              <p v-if="post.venue_address">{{ post.venue_address }}</p>
              <div v-if="post.booking_id" class="booking-code-row">
                <span>Mã đặt sân:</span>
                <code>#{{ post.booking_id }}</code>
              </div>
            </div>

            <a
              v-if="post.venue_address"
              :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(post.venue_name + ' ' + post.venue_address)}`"
              target="_blank"
              rel="noopener noreferrer"
              class="venue-map-link"
            >
              <AppIcon name="navigation" size="14" />
              <span>Xem chỉ đường Google Maps</span>
            </a>
          </section>

          <!-- 4. LƯU Ý TỔ CHỨC GIAO LƯU -->
          <section class="sidebar-card sidebar-card--tips surface">
            <h3 class="sidebar-title">
              <AppIcon name="info" size="16" />
              <span>Lưu ý cho chủ kèo</span>
            </h3>
            <ul class="tips-list">
              <li>Chủ động nhắn tin trong nhóm chat trước 30 - 60 phút để nhắc giờ.</li>
              <li>Thống nhất tiền sân và cách thức thanh toán trước khi vào trận.</li>
              <li>Tôn trọng tinh thần thể thao và đến đúng giờ đã hẹn.</li>
            </ul>
          </section>
        </aside>
      </div>
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import echo from '../../../echo.js';

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
  const match = String(post.value?.time || '').match(/(\d{1,2}):(\d{2})\s*-\s*(?:(\d{1,2}):(\d{2})\s*,\s*)?(\d{2})\/(\d{2})\/(\d{4})/);
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

const approvedParticipants = computed(() => participants.value.filter(p => p.status === 'approved'));

const totalTeamSize = computed(() => {
  const needed = Number(post.value?.needed_players ?? 0);
  return approvedParticipants.value.length + 1 + needed;
});

const rosterProgressPercent = computed(() => {
  const current = approvedParticipants.value.length + 1;
  const total = totalTeamSize.value;
  return total > 0 ? Math.min(100, Math.round((current / total) * 100)) : 100;
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

function initial(name) {
  return String(name || 'N').trim().charAt(0).toUpperCase();
}

let echoChannel = null;

function setupRealtime() {
  teardownRealtime();
  const postId = route.params.id;
  if (!postId) return;
  try {
    echoChannel = echo.channel(`matchmaking.${postId}`);
    echoChannel.listen('.MatchmakingUpdated', () => {
      fetchParticipants(true);
    });
  } catch (e) {
    console.warn('Không thể kết nối realtime:', e);
  }
}

function teardownRealtime() {
  if (echoChannel && route.params.id) {
    try {
      echo.leave(`matchmaking.${route.params.id}`);
    } catch {}
    echoChannel = null;
  }
}

function resetAndFetch() {
  activeFilter.value = 'all';
  confirmRejectId.value = null;
  fetchParticipants();
  setupRealtime();
}

watch(() => route.params.id, resetAndFetch);

onMounted(() => {
  fetchParticipants();
  setupRealtime();
});

onBeforeUnmount(() => {
  teardownRealtime();
});
</script>

<style scoped>
.matchmaking-manage-page {
  --community-ink: #1e293b;
  --community-muted: #64748b;
  --community-soft: #f8fafc;
  --community-surface: #ffffff;
  --community-line: #e2e8f0;
  --community-accent: #5c7e6e;
  --community-accent-dark: #446153;
  --community-accent-soft: #edf4f0;
  --community-danger: #dc2626;
  --community-danger-soft: #fef2f2;
  min-height: 100vh;
  color: var(--community-ink);
  background: var(--community-soft);
  font-family: var(--sportgo-font-body, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
}

.manage-content {
  width: min(1200px, calc(100% - 48px));
  margin: 0 auto;
  padding: 24px 0 64px;
}

.manage-breadcrumb {
  margin-bottom: 18px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--community-accent-dark);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.15s ease;
}

.back-link:hover {
  color: var(--community-accent);
}

/* BỐ CỤC 2 CỘT (GRID LAYOUT) */
.manage-grid-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 24px;
  align-items: start;
}

.manage-main-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.manage-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 80px;
}

/* THẺ TỔNG QUAN HERO CARD */
.manage-hero-card {
  background: var(--community-surface);
  border: 1.5px solid var(--community-line);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

.hero-top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.hero-sport-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--community-accent-dark);
}

.hero-status-pill {
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.2px;
}

.status--open {
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
}

.status--full {
  background: #fef3c7;
  color: #92400e;
}

.status--closed,
.status--expired {
  background: #f1f5f9;
  color: var(--community-muted);
}

.hero-venue-title {
  margin: 0 0 16px 0;
  font-size: 20px;
  font-weight: 500;
  color: #0f172a;
  line-height: 1.35;
}

.hero-court-detail {
  font-size: 16px;
  font-weight: 400;
  color: var(--community-muted);
  margin-left: 6px;
}

/* DẢI THÔNG SỐ PHẲNG */
.hero-metrics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  padding: 14px 0;
  border-top: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
  background: transparent;
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.metric-label {
  font-size: 11px;
  font-weight: 400;
  color: var(--community-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.metric-value {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: var(--community-ink);
}

.metric-value svg {
  color: var(--community-accent);
}

/* KHỐI MÔ TẢ & ẢNH */
.post-detail-section {
  padding-top: 16px;
}

.post-detail-cover {
  width: 100%;
  max-height: 260px;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 12px;
  background: #f1f5f9;
}

.post-detail-cover img {
  width: 100%;
  height: 100%;
  max-height: 260px;
  object-fit: cover;
  display: block;
}

.desc-heading {
  display: block;
  font-size: 11px;
  font-weight: 500;
  color: var(--community-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin-bottom: 6px;
}

.post-detail-desc p {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.6;
  color: var(--community-ink);
  white-space: pre-line;
}

/* KHỐI CHỈNH SỬA NỘI DUNG */
.post-editor {
  background: var(--community-surface);
  border: 1.5px solid var(--community-line);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

.post-editor h2 {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  color: var(--community-ink);
}

.post-editor p {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: var(--community-muted);
}

.sg-client-input {
  width: 100%;
  min-height: 90px;
  padding: 10px 12px;
  border: 1.5px solid var(--community-line);
  border-radius: 8px;
  background: var(--community-surface);
  color: var(--community-ink);
  font-size: 13px;
  line-height: 1.5;
  resize: vertical;
}

.sg-client-input:focus {
  border-color: var(--community-accent);
  outline: 2px solid rgba(92, 126, 110, 0.18);
}

.post-editor-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

/* HỘP HƯỚNG DẪN / MẸO */
.decision-guide {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border: 1.5px solid #d5e2dc;
  border-radius: 10px;
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
}

.decision-guide svg {
  color: var(--community-accent);
  flex-shrink: 0;
}

.decision-guide strong {
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  color: #2e463a;
}

.decision-guide p {
  margin: 2px 0 0;
  font-size: 12px;
  color: #4e6559;
}

.decision-guide--locked {
  border-color: var(--community-line);
  background: #f8fafc;
  color: var(--community-muted);
}

.decision-guide--locked svg {
  color: var(--community-muted);
}

.decision-guide--locked strong {
  color: var(--community-ink);
}

.decision-guide--locked p {
  color: var(--community-muted);
}

/* BẢNG QUẢN LÝ NGƯỜI XIN THAM GIA */
.participants-panel {
  background: var(--community-surface);
  border: 1.5px solid var(--community-line);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 18px 22px;
  border-bottom: 1px solid var(--community-line);
}

.panel-header h2 {
  margin: 0;
  color: var(--community-ink);
  font-size: 16px;
  font-weight: 500;
}

.panel-header p {
  margin: 3px 0 0;
  color: var(--community-muted);
  font-size: 12.5px;
}

.request-filters {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding: 10px 22px;
  border-bottom: 1px solid var(--community-line);
  background: var(--community-soft);
}

.request-filters button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  padding: 0 12px;
  border: 1px solid var(--community-line);
  border-radius: 20px;
  background: var(--community-surface);
  color: var(--community-muted);
  font-size: 12px;
  font-weight: 400;
  cursor: pointer;
  white-space: nowrap;
}

.request-filters button strong {
  display: inline-grid;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  place-items: center;
  border-radius: 10px;
  background: #f1f5f9;
  color: var(--community-muted);
  font-size: 10.5px;
  font-weight: 500;
}

.request-filters button.active {
  border-color: var(--community-accent);
  background: var(--community-accent);
  color: #ffffff;
}

.request-filters button.active strong {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

.participant-list {
  display: flex;
  flex-direction: column;
}

.participant-card {
  padding: 16px 22px;
  border-bottom: 1px solid var(--community-line);
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
  gap: 12px;
}

.participant-info .avatar {
  display: grid;
  width: 40px;
  height: 40px;
  flex: 0 0 auto;
  place-items: center;
  overflow: hidden;
  border-radius: 50%;
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
  font-size: 13px;
  font-weight: 500;
}

.participant-info .avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.participant-info > div:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.participant-info a {
  color: var(--community-ink);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.participant-info small {
  color: var(--community-muted);
  font-size: 11.5px;
}

.participant-actions,
.reject-confirm-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.participant-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 500;
  white-space: nowrap;
}

.participant-status--approved {
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
}

.participant-status--rejected,
.participant-status--cancelled {
  background: var(--community-danger-soft);
  color: var(--community-danger);
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
  margin-top: 12px;
  padding: 12px 14px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fffafa;
}

.reject-confirm strong {
  display: block;
  color: #991b1b;
  font-size: 12.5px;
  font-weight: 500;
}

.reject-confirm span {
  display: block;
  margin-top: 2px;
  color: #7f1d1d;
  font-size: 11.5px;
}

.manage-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 56px 20px;
  text-align: center;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  border-radius: 0 !important;
  color: var(--community-muted);
}

.manage-empty-state svg {
  color: #94a3b8;
  margin-bottom: 4px;
}

.empty-title {
  margin: 0;
  color: var(--community-ink);
  font-size: 14.5px;
  font-weight: 500;
}

.empty-desc {
  margin: 0;
  color: var(--community-muted);
  font-size: 12.5px;
}

.page-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 56px 20px;
  text-align: center;
  color: var(--community-muted);
}

.page-state strong {
  color: var(--community-ink);
  font-size: 14px;
  font-weight: 500;
}

.page-state p {
  margin: 0;
  color: var(--community-muted);
  font-size: 12.5px;
}

.page-state--error {
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fffafa;
  color: var(--community-danger);
}

.page-state--error p {
  color: #7f1d1d;
}

/* ====================================================
   SIDEBAR TIỆN ÍCH & ĐỘI HÌNH (RIGHT COLUMN)
   ==================================================== */
.sidebar-card {
  background: var(--community-surface);
  border: 1.5px solid var(--community-line);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

.sidebar-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 14px 0;
  font-size: 14px;
  font-weight: 500;
  color: var(--community-ink);
}

.sidebar-title svg {
  color: var(--community-accent);
}

.sidebar-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.sidebar-header-row .sidebar-title {
  margin-bottom: 0;
}

.roster-count-badge {
  padding: 2px 8px;
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
  border-radius: 12px;
  font-size: 11.5px;
  font-weight: 500;
}

/* TIẾN ĐỘ ĐỘI HÌNH */
.roster-progress-box {
  margin-bottom: 16px;
}

.progress-bar-bg {
  width: 100%;
  height: 6px;
  background: #f1f5f9;
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 6px;
}

.progress-bar-fill {
  height: 100%;
  background: var(--community-accent);
  border-radius: 3px;
  transition: width 0.3s ease;
}

.progress-text-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11.5px;
  color: var(--community-muted);
}

.progress-text-row strong {
  color: var(--community-accent-dark);
}

/* DANH SÁCH THÀNH VIÊN ĐỘI HÌNH */
.roster-members-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.roster-member-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 8px;
  border-radius: 8px;
  background: #fafcfb;
}

.member-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  background: #e2e8f0;
  display: grid;
  place-items: center;
  font-size: 11px;
  font-weight: 500;
  color: #475569;
  flex: 0 0 auto;
}

.member-avatar--host {
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
}

.member-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.member-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.member-name {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--community-ink);
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-tag {
  font-size: 10px;
  font-weight: 500;
  padding: 1px 6px;
  border-radius: 4px;
  white-space: nowrap;
}

.member-tag--host {
  background: #fef3c7;
  color: #92400e;
}

.member-tag--member {
  background: var(--community-accent-soft);
  color: var(--community-accent-dark);
}

.roster-member-item--empty {
  background: transparent;
  border: 1px dashed #cbd5e1;
  padding: 6px 8px;
}

.empty-slot-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  color: #94a3b8;
  background: #f8fafc;
}

.empty-slot-text {
  font-size: 12px;
  color: #94a3b8;
  font-style: italic;
}

/* STACK PHÍM TẮT THAO TÁC (ACTION BUTTONS) */
.sidebar-actions-stack {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sidebar-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 38px;
  padding: 0 14px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  border: 1px solid transparent;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.sidebar-btn--primary {
  background: var(--community-accent);
  color: #ffffff;
  border-color: var(--community-accent);
}

.sidebar-btn--primary:hover {
  background: var(--community-accent-dark);
  border-color: var(--community-accent-dark);
}

.sidebar-btn--secondary {
  background: var(--community-surface);
  color: var(--community-ink);
  border-color: var(--community-line);
}

.sidebar-btn--secondary:hover {
  background: var(--community-soft);
  border-color: #cbd5e1;
}

.sidebar-btn--muted {
  background: var(--community-surface);
  color: var(--community-muted);
  border-color: var(--community-line);
}

.sidebar-btn--muted:hover {
  background: var(--community-soft);
  color: var(--community-ink);
}

.sidebar-btn--danger {
  background: var(--community-surface);
  color: var(--community-danger);
  border-color: #fecaca;
}

.sidebar-btn--danger:hover {
  background: var(--community-danger-soft);
}

.sidebar-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* THÔNG TIN SÂN & CHỈ ĐƯỜNG */
.venue-snapshot-info {
  margin-bottom: 12px;
}

.venue-snapshot-info strong {
  display: block;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--community-ink);
  margin-bottom: 4px;
}

.venue-snapshot-info p {
  margin: 0 0 8px;
  font-size: 12px;
  line-height: 1.45;
  color: var(--community-muted);
}

.booking-code-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--community-muted);
}

.booking-code-row code {
  font-family: monospace;
  font-weight: 600;
  color: var(--community-accent-dark);
  background: var(--community-accent-soft);
  padding: 1px 5px;
  border-radius: 4px;
}

.venue-map-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: var(--community-accent);
  text-decoration: none;
  transition: color 0.15s ease;
}

.venue-map-link:hover {
  color: var(--community-accent-dark);
  text-decoration: underline;
}

/* MẸO CHO CHỦ KÈO */
.sidebar-card--tips {
  background: #fbfdfc;
  border-color: #e2ede7;
}

.tips-list {
  margin: 0;
  padding-left: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.tips-list li {
  font-size: 12px;
  line-height: 1.5;
  color: #475569;
}

/* BUTTONS CHUNG */
.sg-client-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 34px;
  padding: 0 14px;
  border: 1.5px solid var(--community-line);
  border-radius: 7px;
  background: var(--community-surface);
  color: var(--community-ink);
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.sg-client-button--primary {
  background: var(--community-accent);
  border-color: var(--community-accent);
  color: #ffffff;
}

.sg-client-button--primary:hover {
  background: var(--community-accent-dark);
  border-color: var(--community-accent-dark);
}

.sg-client-button--danger {
  background: var(--community-surface);
  border-color: #fecaca;
  color: var(--community-danger);
}

.sg-client-button--danger:hover {
  background: var(--community-danger-soft);
}

.sg-client-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* RESPONSIVE LAYOUT */
@media (max-width: 960px) {
  .manage-grid-layout {
    grid-template-columns: 1fr;
    gap: 20px;
  }

  .manage-sidebar {
    position: static;
  }
}

@media (max-width: 760px) {
  .manage-content {
    width: min(100% - 24px, 620px);
    padding-top: 16px;
  }

  .hero-metrics-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .panel-header,
  .participant-main,
  .reject-confirm {
    align-items: stretch;
    flex-direction: column;
    gap: 12px;
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
  max-width: 420px;
  background: var(--community-surface);
  border-radius: 14px;
  padding: 24px 22px 20px;
  text-align: center;
  box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.25);
  animation: scaleUpCard 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.sg-confirm-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  margin: 0 auto 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sg-confirm-icon--danger {
  background: #fee2e2;
  color: var(--community-danger);
}

.sg-confirm-icon--warning {
  background: #fef3c7;
  color: #d97706;
}

.sg-confirm-card h3 {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.sg-confirm-card p {
  margin: 0 0 20px;
  font-size: 13px;
  line-height: 1.5;
  color: var(--community-muted);
}

.sg-confirm-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.sg-confirm-actions button {
  flex: 1;
  min-height: 38px;
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

<template>
  <section class="account-matchmaking-page">
    <header class="account-page-header">
      <div>
        <p class="page-kicker">CỘNG ĐỒNG SPORTGO</p>
        <h1>Giao lưu của tôi</h1>
        <p class="page-description">Theo dõi các bài bạn đã đăng và những yêu cầu tham gia các buổi giao lưu.</p>
      </div>
      <router-link class="client-button client-button--primary" :to="{ name: 'ClientCommunityList' }">
        Tìm kèo mới
      </router-link>
    </header>

    <div class="summary-grid">
      <article class="summary-card">
        <span class="summary-label">Bài đã đăng</span>
        <strong>{{ ownPosts.length }}</strong>
        <small>Những buổi bạn đang tổ chức</small>
      </article>
      <article class="summary-card">
        <span class="summary-label">Yêu cầu chờ duyệt</span>
        <strong>{{ pendingRequestCount }}</strong>
        <small>Đang chờ chủ bài phản hồi</small>
      </article>
      <article class="summary-card">
        <span class="summary-label">Đã được duyệt</span>
        <strong>{{ approvedRequestCount }}</strong>
        <small>Đã có tên trong nhóm giao lưu</small>
      </article>
    </div>

    <div v-if="loading" class="state-card">
      <span class="spinner" aria-hidden="true"></span>
      <strong>Đang tải thông tin giao lưu...</strong>
    </div>

    <div v-else-if="error" class="state-card state-card--error" role="alert">
      <strong>Không thể tải thông tin giao lưu</strong>
      <p>{{ error }}</p>
      <button type="button" class="client-button" @click="load">Thử lại</button>
    </div>

    <template v-else>
      <section class="management-section">
        <div class="section-heading">
          <div>
            <p class="page-kicker">QUẢN LÝ BÀI ĐĂNG</p>
            <h2>Bài giao lưu của tôi</h2>
            <p>Quản lý nội dung, xem người xin tham gia và xử lý nhóm cho từng booking.</p>
          </div>
        </div>

        <div v-if="!ownPosts.length" class="empty-card">
          <strong>Bạn chưa tạo bài giao lưu nào</strong>
          <p>Chọn một booking đã được xác nhận để tìm thêm người chơi cùng.</p>
          <router-link class="client-button client-button--primary" :to="{ name: 'ClientCommunityList' }">
            Tạo bài giao lưu
          </router-link>
        </div>

        <div v-else class="post-list">
          <article v-for="post in ownPosts" :key="post.id" class="management-card">
            <header class="card-header">
              <div>
                <h3>{{ post.title || 'Tìm người giao lưu' }}</h3>
                <span class="muted-text">Đăng {{ formatDateTime(post.created_at) }}</span>
              </div>
              <span class="status-pill" :class="`status-pill--${post.status}`">{{ postStatusLabel(post.status) }}</span>
            </header>

            <div class="info-grid">
              <div class="info-item">
                <span>Địa điểm</span>
                <strong>{{ post.booking?.venue_name || 'Cụm sân thể thao' }}</strong>
                <small>{{ post.booking?.court_name || 'Chưa rõ sân' }}</small>
              </div>
              <div class="info-item">
                <span>Thời gian</span>
                <strong>{{ formatDate(post.booking?.date) }}</strong>
                <small>{{ post.booking?.time || 'Chưa rõ khung giờ' }}</small>
              </div>
              <div class="info-item">
                <span>Tiêu chí</span>
                <strong>{{ skillLabel(post.skill_level) }}</strong>
                <small>{{ costLabel(post) }}</small>
              </div>
              <div class="info-item">
                <span>Tiến độ ghép</span>
                <strong>{{ post.approved_players || 0 }}/{{ post.total_players || 0 }} người</strong>
                <small>{{ post.pending_requests || 0 }} yêu cầu chờ xử lý · còn {{ post.needed_players || 0 }} chỗ</small>
              </div>
            </div>

            <p v-if="post.description" class="post-description">{{ post.description }}</p>

            <footer class="card-footer">
              <span v-if="post.status_reason" class="status-note">{{ postReasonLabel(post.status_reason) }}</span>
              <span v-else class="status-note">Booking và trạng thái bài được cập nhật theo hệ thống.</span>
              <router-link class="client-button client-button--primary" :to="{ name: 'ClientMatchmakingManage', params: { id: post.id } }">
                Quản lý bài
              </router-link>
            </footer>
          </article>
        </div>
      </section>

      <section class="management-section">
        <div class="section-heading section-heading--with-action">
          <div>
            <p class="page-kicker">THEO DÕI THAM GIA</p>
            <h2>Các yêu cầu tham gia</h2>
            <p>Xem bạn đã xin tham gia bài nào, thời gian chơi và kết quả xử lý.</p>
          </div>
          <router-link class="client-button" :to="{ name: 'ClientMatchmakingRequests' }">Xem đầy đủ</router-link>
        </div>

        <div v-if="!requests.length" class="empty-card">
          <strong>Bạn chưa gửi yêu cầu tham gia</strong>
          <p>Khám phá các bài giao lưu đang tuyển để tìm người chơi phù hợp.</p>
          <router-link class="client-button client-button--primary" :to="{ name: 'ClientCommunityList' }">
            Khám phá kèo giao lưu
          </router-link>
        </div>

        <div v-else class="request-list">
          <article v-for="item in requests" :key="item.id" class="request-card">
            <div class="request-main">
              <div class="request-title-row">
                <div>
                  <h3>{{ item.post?.title || 'Bài giao lưu' }}</h3>
                  <span class="muted-text">Chủ bài: {{ item.author?.name || 'Người chơi SportGo' }}</span>
                </div>
                <span class="status-pill" :class="`status-pill--${item.status}`">{{ requestStatusLabel(item.status) }}</span>
              </div>
              <div class="request-facts">
                <span><strong>Sân:</strong> {{ item.booking?.venue_name || 'Cụm sân thể thao' }}<template v-if="item.booking?.court_name"> · {{ item.booking.court_name }}</template></span>
                <span><strong>Chơi:</strong> {{ formatDate(item.booking?.date) }} · {{ item.booking?.time || 'Chưa rõ giờ' }}</span>
                <span><strong>Gửi lúc:</strong> {{ formatDateTime(item.created_at) }}</span>
              </div>
              <p v-if="item.post?.description" class="request-description">{{ item.post.description }}</p>
            </div>
            <router-link class="client-button" :to="{ name: 'ClientMatchmakingRequestDetail', params: { id: item.id } }">
              Xem chi tiết
            </router-link>
          </article>
        </div>
      </section>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '@/services/api.js';

const ownPosts = ref([]);
const requests = ref([]);
const loading = ref(true);
const error = ref('');

const pendingRequestCount = computed(() => requests.value.filter((item) => item.status === 'pending').length);
const approvedRequestCount = computed(() => requests.value.filter((item) => item.status === 'approved').length);

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const [postsResponse, requestsResponse] = await Promise.all([
      api('/api/matchmaking-posts/mine?per_page=50'),
      api('/api/matchmaking-requests?per_page=50'),
    ]);
    ownPosts.value = Array.isArray(postsResponse?.data) ? postsResponse.data : [];
    requests.value = Array.isArray(requestsResponse?.data) ? requestsResponse.data : [];
  } catch (requestError) {
    error.value = requestError.message || 'Vui lòng thử lại sau.';
  } finally {
    loading.value = false;
  }
}

function postStatusLabel(status) {
  return { open: 'Đang tuyển', full: 'Đã đủ người', closed: 'Đã đóng', cancelled: 'Đã hủy' }[status] || status || 'Chưa rõ';
}

function requestStatusLabel(status) {
  return { pending: 'Chờ duyệt', approved: 'Đã được duyệt', rejected: 'Bị từ chối', cancelled: 'Đã kết thúc', expired: 'Hết hạn', left: 'Đã rời' }[status] || status || 'Chưa rõ';
}

function postReasonLabel(reason) {
  return {
    closed_by_author: 'Bạn đã đóng bài.',
    matchmaking_deadline_reached: 'Bài đã tự đóng khi đến hạn chốt đăng ký.',
    matchmaking_session_started: 'Bài đã tự đóng khi booking bắt đầu.',
    matchmaking_session_ended: 'Buổi giao lưu đã kết thúc.',
    matchmaking_group_dissolved: 'Nhóm giao lưu đã được giải tán.',
  }[reason] || 'Bài đã được cập nhật trạng thái.';
}

function skillLabel(level) {
  return { all: 'Mọi trình độ', beginner: 'Mới chơi', intermediate: 'Trung bình', advanced: 'Khá / nâng cao' }[level] || 'Mọi trình độ';
}

function costLabel(post) {
  if (post?.cost_type === 'free') return 'Miễn phí';
  if (Number(post?.cost_per_player) > 0) return `~${Math.round(Number(post.cost_per_player) / 1000)}k / người`;
  return 'Chia đều tiền sân';
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  const [year, month, day] = String(value).slice(0, 10).split('-');
  return day && month && year ? `${day}/${month}/${year}` : String(value);
}

function formatDateTime(value) {
  if (!value) return 'chưa rõ';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return formatDate(value);
  return `${formatDate(date.toISOString())} ${date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}`;
}

onMounted(load);
</script>

<style scoped>
.account-matchmaking-page{width:100%;min-width:0;color:#172033}.account-page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:22px}.account-page-header h1,.section-heading h2{margin:0;color:#172033}.account-page-header h1{font-size:26px;letter-spacing:-.02em}.page-description,.section-heading p{margin:6px 0 0;color:#64748b;font-size:13px;line-height:1.5}.page-kicker{margin:0 0 6px;color:#64748b;font-size:10px;font-weight:700;letter-spacing:.08em}.summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:28px}.summary-card,.management-card,.request-card,.empty-card,.state-card{border:1px solid #dfe7e2;border-radius:12px;background:#fff}.summary-card{display:grid;gap:4px;padding:15px 16px}.summary-label{color:#64748b;font-size:12px}.summary-card strong{font-size:25px;line-height:1.1;color:#2f6d51}.summary-card small{color:#94a3b8;font-size:11px}.management-section{margin-top:28px}.section-heading{margin-bottom:13px}.section-heading--with-action{display:flex;align-items:flex-end;justify-content:space-between;gap:15px}.post-list,.request-list{display:grid;gap:12px}.management-card{overflow:hidden}.card-header,.card-footer,.request-card{display:flex;align-items:center;justify-content:space-between;gap:16px}.card-header{padding:16px 18px;border-bottom:1px solid #edf2ef}.card-header h3,.request-card h3{margin:0;color:#1e293b;font-size:15px}.muted-text{display:block;margin-top:4px;color:#94a3b8;font-size:11px}.status-pill{display:inline-flex;flex:0 0 auto;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:700;background:#f1f5f9;color:#64748b}.status-pill--open,.status-pill--approved{background:#e8f5ed;color:#227047}.status-pill--full{background:#fff3d6;color:#996300}.status-pill--pending{background:#fff3d6;color:#996300}.status-pill--rejected,.status-pill--cancelled{background:#feeceb;color:#a33a32}.info-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;padding:5px 0}.info-item{display:grid;gap:4px;padding:12px 18px;border-right:1px solid #edf2ef}.info-item:last-child{border-right:0}.info-item span,.info-item small{color:#94a3b8;font-size:11px}.info-item strong{color:#334155;font-size:12px;line-height:1.35}.post-description,.request-description{margin:0;padding:0 18px 14px;color:#475569;font-size:12.5px;line-height:1.55}.card-footer{padding:12px 18px;border-top:1px solid #edf2ef;background:#fbfdfc}.status-note{color:#64748b;font-size:11px}.request-card{align-items:flex-end;padding:16px 18px}.request-main{min-width:0}.request-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.request-facts{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:12px;color:#64748b;font-size:12px}.request-facts strong{color:#475569}.request-description{padding:12px 0 0}.client-button{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 13px;border:1px solid #cbd8d1;border-radius:8px;background:#fff;color:#315a47;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;cursor:pointer}.client-button:hover{background:#f4faf6}.client-button--primary{border-color:#5c806e;background:#5c806e;color:#fff}.client-button--primary:hover{background:#476c5a}.empty-card,.state-card{display:grid;justify-items:center;gap:8px;padding:32px 20px;text-align:center;color:#64748b}.empty-card strong,.state-card strong{color:#334155;font-size:14px}.empty-card p,.state-card p{margin:0;max-width:460px;font-size:12.5px;line-height:1.5}.state-card{min-height:150px;place-content:center}.state-card--error{border-color:#efb5b0}.state-card--error strong{color:#a33a32}.spinner{width:22px;height:22px;border:3px solid #dcebe2;border-top-color:#5c806e;border-radius:50%;animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:900px){.info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.info-item:nth-child(2){border-right:0}.info-item:nth-child(-n+2){border-bottom:1px solid #edf2ef}}
@media(max-width:640px){.account-page-header,.section-heading--with-action,.request-card,.card-footer{align-items:stretch;flex-direction:column}.account-page-header .client-button,.section-heading--with-action .client-button,.request-card>.client-button,.card-footer .client-button{width:100%}.summary-grid{grid-template-columns:1fr}.info-grid{grid-template-columns:1fr}.info-item{border-right:0!important;border-bottom:1px solid #edf2ef}.info-item:last-child{border-bottom:0}.request-title-row{align-items:stretch;flex-direction:column}.request-facts{display:grid}}
</style>

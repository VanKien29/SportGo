<template>
  <div class="sg-client-page sg-matchmaking-page">
    <PublicNavbar />
    <main class="sg3-container sg-matchmaking-main">
      <header class="sg3-page-head sg-matchmaking-head">
        <div>
          <div class="sg3-breadcrumbs"><router-link to="/community">Cộng đồng</router-link><span>/</span><strong>Tuyển giao lưu</strong></div>
          <p class="sg3-kicker">Ghép người cho lịch đã đặt</p>
          <h1>Tìm người chơi cùng</h1>
          <p>Chỉ những booking đã được xác nhận mới có thể mở bài tuyển. Người tham gia gửi yêu cầu, chủ bài sẽ xem hồ sơ rồi duyệt hoặc từ chối.</p>
        </div>
        <button v-if="user" type="button" class="sg3-button sg3-button--primary" @click="showCreate = true"><AppIcon name="plus" :size="16" /> Tạo bài giao lưu</button>
        <router-link v-else class="sg3-button sg3-button--primary" to="/login"><AppIcon name="key" :size="16" /> Đăng nhập để đăng bài</router-link>
      </header>

      <section class="sg-matchmaking-stats" aria-label="Tổng quan tuyển giao lưu">
        <article class="sg3-card"><span class="sg-matchmaking-stat__icon"><AppIcon name="users" :size="18" /></span><div><small>Kèo đang mở</small><strong>{{ posts.length }}</strong></div></article>
        <article class="sg3-card"><span class="sg-matchmaking-stat__icon"><AppIcon name="calendar" :size="18" /></span><div><small>Bài có booking hợp lệ</small><strong>{{ confirmedCount }}</strong></div></article>
        <article class="sg3-card"><span class="sg-matchmaking-stat__icon"><AppIcon name="circleCheck" :size="18" /></span><div><small>Yêu cầu của tôi</small><strong>{{ myRequestCount }}</strong></div></article>
        <article class="sg3-card sg-matchmaking-rule"><AppIcon name="shieldCheck" :size="19" /><span><strong>Minh bạch trạng thái</strong><small>Duyệt xong người chơi mới được tính vào số người đã ghép.</small></span></article>
      </section>

      <section class="sg-matchmaking-layout">
        <div>
          <div class="sg-matchmaking-toolbar"><div><p class="sg3-kicker">Danh sách công khai</p><h2>Kèo sắp tới</h2></div><button type="button" class="sg3-button sg3-button--secondary" :disabled="loading" @click="loadPosts"><AppIcon name="refresh" :size="16" /> Làm mới</button></div>
          <div v-if="loading" class="sg3-empty"><div><span class="spinner"></span><strong>Đang tải các kèo giao lưu...</strong></div></div>
          <div v-else-if="error" class="sg3-error"><div><strong>Không tải được danh sách</strong><p>{{ error }}</p><button class="sg3-button sg3-button--primary" type="button" @click="loadPosts">Thử lại</button></div></div>
          <div v-else-if="!posts.length" class="sg3-card sg-matchmaking-empty"><AppIcon name="users" :size="30" /><strong>Chưa có bài tuyển phù hợp</strong><p>Hãy tạo một bài từ booking sắp tới của bạn để tìm người chơi cùng.</p><button v-if="user" type="button" class="sg3-button sg3-button--primary" @click="showCreate = true">Tạo bài đầu tiên</button></div>
          <div v-else class="sg-matchmaking-list">
            <article v-for="post in posts" :key="post.id" class="sg3-card sg-matchmaking-card">
              <header class="sg-matchmaking-card__head"><div class="sg-matchmaking-author"><span class="sg-matchmaking-avatar"><img v-if="post.author?.avatar" :src="assetUrl(post.author.avatar)" :alt="post.author.name" /><span v-else>{{ initial(post.author?.name) }}</span></span><span><strong>{{ post.author?.name || 'Người chơi SportGo' }}</strong><small>Đăng {{ formatDate(post.created_at) }}</small></span></div><span class="sg-matchmaking-status" :class="`is-${post.status}`">{{ statusLabel(post.status) }}</span></header>
              <div class="sg-matchmaking-card__body"><div><p class="sg-matchmaking-card__title">{{ post.title || 'Tìm người giao lưu' }}</p><p v-if="post.description" class="sg-matchmaking-card__description">{{ post.description }}</p><div class="sg-matchmaking-meta"><span><AppIcon name="mapPin" :size="14" />{{ post.booking?.venue_name || 'Sân thể thao' }}</span><span><AppIcon name="calendar" :size="14" />{{ formatDate(post.booking?.date) }} · {{ post.booking?.time }}</span></div></div><div class="sg-matchmaking-progress"><strong>{{ post.approved_players || 0 }}/{{ post.total_players || post.needed_players || 0 }}</strong><span>đã ghép</span><div><i :style="{ width: `${progress(post)}%` }"></i></div><small>Còn {{ post.needed_players || 0 }} người</small></div></div>
              <footer class="sg-matchmaking-card__foot"><span v-if="post.user_status" class="sg-matchmaking-request" :class="`is-${post.user_status}`"><AppIcon :name="post.user_status === 'approved' ? 'circleCheck' : 'clock'" :size="15" /> {{ requestLabel(post.user_status) }}</span><span v-else></span><div><router-link v-if="isOwn(post)" class="sg3-button sg3-button--secondary" :to="`/matchmaking-posts/${post.id}/manage`"><AppIcon name="users" :size="15" /> Xem người tham gia</router-link><button v-if="!isOwn(post) && post.user_status && !['cancelled','left','expired','rejected'].includes(post.user_status)" type="button" class="sg3-button sg3-button--secondary" :disabled="joining === post.id" @click="leave(post)"><AppIcon name="close" :size="15" /> Rút yêu cầu</button><button v-else-if="!isOwn(post) && ['open','full'].includes(post.status)" type="button" class="sg3-button sg3-button--primary" :disabled="joining === post.id || (post.user_status && !['cancelled','left','expired','rejected'].includes(post.user_status))" @click="join(post)"><AppIcon name="users" :size="15" /> {{ joining === post.id ? 'Đang gửi...' : ['cancelled','left','expired','rejected'].includes(post.user_status) ? 'Xin tham gia lại' : 'Xin tham gia' }}</button></div></footer>
            </article>
          </div>
        </div>
        <aside class="sg3-card sg-matchmaking-guide"><p class="sg3-kicker">Cách hoạt động</p><h2>Một booking, một kèo rõ ràng</h2><div v-for="(step, index) in steps" :key="step.title" class="sg-matchmaking-step"><span>{{ index + 1 }}</span><div><strong>{{ step.title }}</strong><p>{{ step.description }}</p></div></div><router-link class="sg3-button sg3-button--secondary" to="/bookings">Xem booking của tôi <AppIcon name="arrowRight" :size="15" /></router-link></aside>
      </section>
    </main>
    <MeetupPostModal :is-open="showCreate" @close="showCreate = false" @success="handleCreated" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import MeetupPostModal from '@/components/MeetupPostModal.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import { getAuth } from '@/stores/auth.js';

const toast = useToast();
const user = getAuth();
const posts = ref([]);
const loading = ref(true);
const error = ref('');
const joining = ref(null);
const showCreate = ref(false);
let postsRequestController = null;
let postsRequestId = 0;
let lifecycleRefreshTimer = null;
const steps = [
  { title: 'Chọn booking đã xác nhận', description: 'Thanh toán đủ, đặt cọc hoặc trả sau nhưng đã được chủ sân duyệt đều có thể tạo bài.' },
  { title: 'Đăng số người cần thêm', description: 'Mô tả trình độ, thời gian và lưu ý để người chơi phù hợp dễ quyết định.' },
  { title: 'Chủ bài duyệt người tham gia', description: 'Chỉ người đã được chấp nhận mới được tính vào ô tiến độ của kèo.' },
];

const confirmedCount = computed(() => posts.value.filter((post) => post.booking?.date && post.status === 'open').length);
const myRequestCount = computed(() => posts.value.filter((post) => post.user_status).length);

async function loadPosts() {
  postsRequestController?.abort();
  const requestId = ++postsRequestId;
  const controller = new AbortController();
  postsRequestController = controller;
  loading.value = true; error.value = '';
  try {
    const response = await api('/api/matchmaking-posts?per_page=30', {
      signal: controller.signal,
      dedupe: false,
    });
    if (requestId !== postsRequestId) return;
    const payload = response?.data;
    posts.value = Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data)
        ? payload.data
        : [];
  } catch (requestError) {
    if (controller.signal.aborted || requestId !== postsRequestId) return;
    error.value = requestError.message || 'Vui lòng thử lại.';
  } finally {
    if (requestId === postsRequestId) {
      loading.value = false;
      postsRequestController = null;
    }
  }
}

async function join(post) {
  if (!user || joining.value) return;
  joining.value = post.id;
  try { await api(`/api/matchmaking-posts/${post.id}/join`, { method: 'POST' }); toast.success('Đã gửi yêu cầu tham gia.'); await loadPosts(); }
  catch (requestError) { toast.error(requestError.message || 'Không thể gửi yêu cầu tham gia.'); }
  finally { joining.value = null; }
}

async function leave(post) {
  if (!user || joining.value) return;
  joining.value = post.id;
  try { await api(`/api/matchmaking-posts/${post.id}/leave`, { method: 'POST' }); toast.success('Đã rút yêu cầu tham gia.'); await loadPosts(); }
  catch (requestError) { toast.error(requestError.message || 'Không thể rút yêu cầu tham gia.'); }
  finally { joining.value = null; }
}

async function handleCreated() {
  showCreate.value = false;
  toast.success('Bài giao lưu đã được đăng.');
  // The modal is already closed; a slow list refresh must not block the
  // create flow or make the submit action appear stuck.
  void loadPosts();
}
function isOwn(post) { return String(post.author?.id || '') === String(user?.id || ''); }
function progress(post) { const total = Number(post.total_players || 0); return total ? Math.min(100, (Number(post.approved_players || 0) / total) * 100) : 0; }
function statusLabel(status) { return { open: 'Đang tuyển', full: 'Đã đủ người', closed: 'Đã đóng', cancelled: 'Đã hủy' }[status] || status; }
function requestLabel(status) { return { pending: 'Đang chờ chủ bài duyệt', approved: 'Đã được chấp nhận', rejected: 'Đã bị từ chối', cancelled: 'Đã rút yêu cầu', expired: 'Hết hạn', left: 'Đã rời' }[status] || status; }
function formatDate(value) { if (!value) return 'chưa rõ'; const date = new Date(value); return Number.isNaN(date.getTime()) ? String(value).slice(0, 10) : date.toLocaleDateString('vi-VN'); }
function assetUrl(path) { return !path || path.startsWith('/') || /^https?:\/\//.test(path) ? path : `/storage/${path}`; }
function initial(name) { return String(name || 'N').trim().charAt(0).toUpperCase(); }
onMounted(() => {
  loadPosts();
  // Reconcile the feed around booking start without requiring a manual reload.
  lifecycleRefreshTimer = setInterval(() => void loadPosts(), 60_000);
});
onBeforeUnmount(() => {
  postsRequestId += 1;
  postsRequestController?.abort();
  if (lifecycleRefreshTimer) clearInterval(lifecycleRefreshTimer);
});
</script>

<style scoped>
.sg-matchmaking-main{padding-bottom:64px}.sg-matchmaking-head{padding-bottom:22px}.sg-matchmaking-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) minmax(280px,1.4fr);gap:11px;margin-bottom:22px}.sg-matchmaking-stats article{display:flex;align-items:center;gap:11px;padding:15px}.sg-matchmaking-stats article>div{display:grid;gap:3px}.sg-matchmaking-stats small{color:var(--sg3-muted);font-size:11px}.sg-matchmaking-stats strong{font-size:22px}.sg-matchmaking-stat__icon{display:grid;width:36px;height:36px;place-items:center;border-radius:10px;background:var(--sg3-green-soft);color:var(--sg3-green-dark)}.sg-matchmaking-rule{background:linear-gradient(135deg,#0b7c42,#0a5f37)!important;color:#fff}.sg-matchmaking-rule>svg{flex:0 0 auto;color:#a4ebbb}.sg-matchmaking-rule span{display:grid;gap:3px}.sg-matchmaking-rule small{color:rgba(255,255,255,.72)}.sg-matchmaking-layout{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:18px;align-items:start}.sg-matchmaking-toolbar{display:flex;align-items:end;justify-content:space-between;gap:15px;margin-bottom:13px}.sg-matchmaking-toolbar h2{margin:0;font-size:22px}.sg-matchmaking-list{display:grid;gap:11px}.sg-matchmaking-card{overflow:hidden}.sg-matchmaking-card__head,.sg-matchmaking-card__foot{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px 18px}.sg-matchmaking-card__head{border-bottom:1px solid var(--sg3-line)}.sg-matchmaking-author{display:flex;align-items:center;gap:10px}.sg-matchmaking-author>span:last-child{display:grid;gap:3px}.sg-matchmaking-author small{color:var(--sg3-muted);font-size:11px}.sg-matchmaking-avatar{display:grid;width:38px;height:38px;place-items:center;overflow:hidden;border-radius:12px;background:var(--sg3-green);color:#fff;font-size:13px;font-weight:900}.sg-matchmaking-avatar img{width:100%;height:100%;object-fit:cover}.sg-matchmaking-status,.sg-matchmaking-request{display:inline-flex;align-items:center;gap:5px;padding:6px 9px;border-radius:999px;background:#eff3f0;color:var(--sg3-muted);font-size:11px;font-weight:800}.sg-matchmaking-status.is-open,.sg-matchmaking-request.is-approved{background:var(--sg3-green-soft);color:var(--sg3-green-dark)}.sg-matchmaking-status.is-full{background:#fff3dc;color:#9a6400}.sg-matchmaking-card__body{display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:20px;padding:18px}.sg-matchmaking-card__title{margin:0;color:var(--sg3-ink);font-size:16px;font-weight:800}.sg-matchmaking-card__description{margin:8px 0 0;color:var(--sg3-muted);font-size:13px;line-height:1.55}.sg-matchmaking-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:13px}.sg-matchmaking-meta span{display:inline-flex;align-items:center;gap:5px;color:var(--sg3-muted);font-size:12px}.sg-matchmaking-meta svg{color:var(--sg3-green-dark)}.sg-matchmaking-progress{display:grid;align-content:center;gap:5px;text-align:right}.sg-matchmaking-progress strong{font-size:25px;letter-spacing:-.04em}.sg-matchmaking-progress>span,.sg-matchmaking-progress small{color:var(--sg3-muted);font-size:11px}.sg-matchmaking-progress>div{height:7px;overflow:hidden;border-radius:999px;background:#e7efe9}.sg-matchmaking-progress i{display:block;height:100%;border-radius:inherit;background:var(--sg3-green)}.sg-matchmaking-card__foot{border-top:1px solid var(--sg3-line);background:#fbfefc}.sg-matchmaking-card__foot>div{display:flex;gap:8px}.sg-matchmaking-guide{position:sticky;top:96px;padding:20px}.sg-matchmaking-guide h2{margin:0;font-size:19px}.sg-matchmaking-step{display:grid;grid-template-columns:27px 1fr;gap:10px;margin-top:19px}.sg-matchmaking-step>span{display:grid;width:27px;height:27px;place-items:center;border-radius:8px;background:var(--sg3-green-soft);color:var(--sg3-green-dark);font-size:12px;font-weight:900}.sg-matchmaking-step div{display:grid;gap:4px}.sg-matchmaking-step p{margin:0;color:var(--sg3-muted);font-size:12px;line-height:1.5}.sg-matchmaking-guide>a{width:100%;margin-top:22px}.sg-matchmaking-empty{display:grid;place-items:center;gap:9px;min-height:260px;padding:30px;text-align:center}.sg-matchmaking-empty>svg{color:var(--sg3-green-dark)}.sg-matchmaking-empty p{max-width:380px;margin:0;color:var(--sg3-muted);font-size:13px}@media(max-width:1000px){.sg-matchmaking-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.sg-matchmaking-layout{grid-template-columns:1fr}.sg-matchmaking-guide{position:static}}@media(max-width:560px){.sg-matchmaking-stats{grid-template-columns:1fr}.sg-matchmaking-toolbar{align-items:start;flex-direction:column}.sg-matchmaking-card__body{grid-template-columns:1fr}.sg-matchmaking-progress{justify-items:start;text-align:left}.sg-matchmaking-card__head,.sg-matchmaking-card__foot{align-items:start;flex-direction:column}.sg-matchmaking-card__foot{gap:10px}.sg-matchmaking-card__foot>div{width:100%}.sg-matchmaking-card__foot .sg3-button{flex:1}}
</style>

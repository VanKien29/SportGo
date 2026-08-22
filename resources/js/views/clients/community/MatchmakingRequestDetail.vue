<template>
  <div class="matchmaking-detail-page sg-client-page">
    <PublicNavbar />
    <main class="detail-shell">
      <router-link class="detail-back" :to="{ name: 'ClientMatchmakingRequests' }">← Đơn tham gia của tôi</router-link>
      <section v-if="loading" class="detail-state">Đang tải chi tiết đơn...</section>
      <section v-else-if="error" class="detail-state detail-state--error" role="alert"><strong>Không thể tải đơn</strong><span>{{ error }}</span></section>
      <template v-else-if="item">
        <header class="detail-heading">
          <div><span class="detail-kicker">CHI TIẾT ĐƠN GIAO LƯU</span><h1>{{ item.post.title }}</h1><p>{{ item.post.description || 'Không có mô tả thêm.' }}</p></div>
          <span class="detail-status" :class="`detail-status--${item.status}`">{{ statusLabel(item.status) }}</span>
        </header>
        <div class="detail-grid">
          <section class="detail-panel"><h2>Thông tin buổi giao lưu</h2><dl><div><dt>Sân</dt><dd>{{ item.booking.venue_name }}</dd></div><div><dt>Địa chỉ</dt><dd>{{ item.booking.venue_address || 'Chưa cập nhật' }}</dd></div><div><dt>Thời gian</dt><dd>{{ formatDate(item.booking.date) }} · {{ item.booking.start_time }} - {{ item.booking.end_time }}</dd></div><div v-if="item.left_at"><dt>Đã rời lúc</dt><dd>{{ formatDateTime(item.left_at) }}</dd></div></dl></section>
          <section class="detail-panel"><h2>Chủ bài viết</h2><div class="author"><span class="author-avatar">{{ initial(item.author.name) }}</span><div><strong>{{ item.author.name }}</strong><span>Thông tin liên hệ riêng tư được bảo vệ</span></div></div><p class="privacy-note">Sau khi được duyệt, bạn sử dụng chat nội bộ để trao đổi. Hệ thống không hiển thị email, số điện thoại, QR hoặc mã booking.</p></section>
        </div>
        <div class="detail-actions"><router-link v-if="item.status === 'approved' && item.group_chat_id" class="detail-button detail-button--primary" :to="{ name: 'client-messages', query: { conversation_id: item.group_chat_id } }">Mở nhóm chat</router-link><button v-if="item.can_leave" type="button" class="detail-button detail-button--danger" :disabled="leaving" @click="leave">{{ leaving ? 'Đang rút...' : 'Rời kèo giao lưu' }}</button><router-link class="detail-button" :to="{ name: 'ClientCommunityList' }">Quay lại cộng đồng</router-link></div>
      </template>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import { useToast } from 'vue-toastification';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const item = ref(null); const loading = ref(true); const error = ref(''); const leaving = ref(false);
async function load() { try { const response = await api(`/api/matchmaking-requests/${route.params.id}`); item.value = response.data; } catch (e) { error.value = e.message || 'Vui lòng thử lại.'; } finally { loading.value = false; } }
async function leave() { if (!item.value?.post_id || leaving.value) return; leaving.value = true; try { await api(`/api/matchmaking-posts/${item.value.post_id}/leave`, { method: 'POST' }); toast.success('Đã rút yêu cầu tham gia.'); await router.replace({ name: 'ClientMatchmakingRequests' }); } catch (e) { toast.error(e.message || 'Không thể rút yêu cầu.'); } finally { leaving.value = false; } }
function statusLabel(value) { return { pending: 'Đang chờ duyệt', approved: 'Đã được duyệt', rejected: 'Bị từ chối', cancelled: 'Đã kết thúc' }[value] || value; }
function formatDate(value) { return value ? new Date(`${value}T00:00:00`).toLocaleDateString('vi-VN') : '-'; }
function formatDateTime(value) { return value ? new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : '-'; }
function initial(value) { return String(value || 'N').trim().charAt(0).toUpperCase(); }
onMounted(load);
</script>

<style scoped>
.matchmaking-detail-page { min-height: 100vh; background: #f5f8f6; color: #10251a; }
.detail-shell { width: min(100% - 40px, 1000px); margin: 0 auto; padding: 30px 0 72px; }
.detail-back { color: #166534; font-size: 13px; font-weight: 800; }
.detail-heading { display: flex; align-items: start; justify-content: space-between; gap: 20px; margin: 24px 0; }
.detail-kicker { color: #15803d; font-size: 11px; font-weight: 800; letter-spacing: .12em; }
.detail-heading h1 { margin: 8px 0 0; font-size: clamp(26px, 4vw, 36px); line-height: 1.15; }
.detail-heading p { margin: 9px 0 0; color: #607268; font-size: 14px; }
.detail-status { padding: 7px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; white-space: nowrap; }
.detail-status--pending { background: #fef3c7; color: #92400e; }.detail-status--approved { background: #dcfce7; color: #166534; }.detail-status--rejected { background: #fee2e2; color: #991b1b; }.detail-status--cancelled { background: #e2e8f0; color: #475569; }
.detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }.detail-panel { padding: 20px; border: 1px solid #dbe5de; border-radius: 12px; background: #fff; }.detail-panel h2 { margin: 0 0 14px; font-size: 17px; }.detail-panel dl { display: grid; gap: 12px; margin: 0; }.detail-panel dl div { display: grid; gap: 3px; }.detail-panel dt { color: #7b8b82; font-size: 11px; font-weight: 800; text-transform: uppercase; }.detail-panel dd { margin: 0; color: #30483a; font-size: 14px; }.author { display: flex; align-items: center; gap: 11px; }.author-avatar { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 50%; background: #dcfce7; color: #166534; font-weight: 800; }.author div { display: grid; gap: 3px; }.author div span { color: #718178; font-size: 12px; }.privacy-note { margin: 18px 0 0; padding-top: 13px; border-top: 1px solid #edf2ee; color: #607268; font-size: 12px; line-height: 1.55; }.detail-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }.detail-button { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; padding: 0 14px; border: 1px solid #cbdace; border-radius: 8px; background: #fff; color: #31453a; font-size: 13px; font-weight: 800; cursor: pointer; }.detail-button--danger { border-color: #fecaca; color: #b91c1c; }.detail-state { display: grid; justify-items: center; gap: 8px; padding: 58px 20px; border: 1px solid #dbe5de; border-radius: 12px; background: #fff; color: #718178; }.detail-state--error { border-color: #fecaca; color: #991b1b; }
@media (max-width: 680px) { .detail-shell { width: min(100% - 24px, 560px); padding-top: 22px; }.detail-heading { display: grid; }.detail-grid { grid-template-columns: 1fr; } }
.detail-button--primary { border-color: #15803d; background: #15803d; color: #fff; }
</style>

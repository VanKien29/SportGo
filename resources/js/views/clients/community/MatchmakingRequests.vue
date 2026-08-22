<template>
  <div class="matchmaking-requests-page sg-client-page">
    <PublicNavbar />
    <main class="requests-shell">
      <div class="requests-heading">
        <div>
          <span class="requests-kicker">GIAO LƯU</span>
          <h1>Đơn tham gia của tôi</h1>
          <p>Theo dõi các kèo bạn đã xin tham gia và thông tin sân được phép xem.</p>
        </div>
        <router-link class="requests-button requests-button--primary" :to="{ name: 'ClientCommunityList' }">Tìm kèo mới</router-link>
      </div>

      <nav class="requests-tabs" aria-label="Lọc đơn tham gia">
        <button v-for="tab in tabs" :key="tab.value" type="button" :class="{ active: status === tab.value }" @click="changeTab(tab.value)">
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="loading" class="requests-state">Đang tải các đơn tham gia...</section>
      <section v-else-if="error" class="requests-state requests-state--error" role="alert">
        <strong>Không thể tải đơn tham gia</strong>
        <span>{{ error }}</span>
        <button class="requests-button" type="button" @click="load">Thử lại</button>
      </section>
      <section v-else-if="!items.length" class="requests-state">
        <strong>Chưa có đơn trong mục này</strong>
        <span>Các yêu cầu bạn gửi sẽ được lưu lại để tiện theo dõi.</span>
      </section>
      <section v-else class="request-list" aria-label="Danh sách đơn tham gia">
        <article v-for="item in items" :key="item.id" class="request-card">
          <div class="request-card__top">
            <div>
              <span class="request-status" :class="`request-status--${item.status}`">{{ statusLabel(item.status) }}</span>
              <h2>{{ item.post.title }}</h2>
            </div>
            <router-link class="request-detail-link" :to="{ name: 'ClientMatchmakingRequestDetail', params: { id: item.id } }">Xem chi tiết</router-link>
          </div>
          <p class="request-description">{{ item.post.description || 'Bài giao lưu không có mô tả thêm.' }}</p>
          <div class="request-facts">
            <span><AppIcon name="calendar" size="15" />{{ formatDate(item.booking.date) }}</span>
            <span><AppIcon name="clock" size="15" />{{ item.booking.start_time }} - {{ item.booking.end_time }}</span>
            <span><AppIcon name="mapPin" size="15" />{{ item.booking.venue_name }}</span>
          </div>
          <footer class="request-card__footer">
            <span>Chủ bài: {{ item.author.name }}</span>
            <span>{{ formatDateTime(item.created_at) }}</span>
            <router-link v-if="item.status === 'approved' && item.group_chat_id" class="request-chat-link" :to="{ name: 'client-messages', query: { conversation_id: item.group_chat_id } }">Mở nhóm chat</router-link>
          </footer>
        </article>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';

const route = useRoute();
const router = useRouter();
const status = ref(String(route.query.status || 'all'));
const items = ref([]);
const loading = ref(true);
const error = ref('');
const tabs = [
  { value: 'all', label: 'Tất cả' },
  { value: 'pending', label: 'Đang chờ' },
  { value: 'approved', label: 'Đã duyệt' },
  { value: 'rejected', label: 'Bị từ chối' },
  { value: 'cancelled', label: 'Đã kết thúc' },
];

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const query = status.value !== 'all' ? `?status=${encodeURIComponent(status.value)}` : '';
    const response = await api(`/api/matchmaking-requests${query}`);
    items.value = response.data || [];
  } catch (requestError) {
    error.value = requestError.message || 'Vui lòng thử lại.';
  } finally {
    loading.value = false;
  }
}

function changeTab(value) {
  status.value = value;
  router.replace({ query: value === 'all' ? {} : { status: value } });
}

function statusLabel(value) {
  return { pending: 'Đang chờ duyệt', approved: 'Đã được duyệt', rejected: 'Bị từ chối', cancelled: 'Đã kết thúc' }[value] || value;
}

function formatDate(value) {
  return value ? new Date(`${value}T00:00:00`).toLocaleDateString('vi-VN') : '-';
}

function formatDateTime(value) {
  return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
}

watch(() => route.query.status, (value) => {
  status.value = String(value || 'all');
  load();
}, { immediate: true });
</script>

<style scoped>
.matchmaking-requests-page { min-height: 100vh; background: #f5f8f6; color: #10251a; }
.requests-shell { width: min(100% - 40px, 1000px); margin: 0 auto; padding: 34px 0 72px; }
.requests-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 24px; }
.requests-kicker { color: #15803d; font-size: 11px; font-weight: 800; letter-spacing: .12em; }
.requests-heading h1 { margin: 8px 0 0; font-size: clamp(26px, 4vw, 36px); line-height: 1.15; }
.requests-heading p { margin: 9px 0 0; color: #607268; font-size: 14px; }
.requests-button { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; padding: 0 14px; border: 1px solid #cbdace; border-radius: 8px; background: #fff; color: #31453a; font-size: 13px; font-weight: 700; cursor: pointer; }
.requests-button--primary { border-color: #15803d; background: #15803d; color: #fff; }
.requests-tabs { display: flex; gap: 4px; overflow-x: auto; margin-bottom: 14px; padding: 4px; border: 1px solid #dbe5de; border-radius: 10px; background: #fff; }
.requests-tabs button { min-height: 36px; padding: 0 13px; border: 0; border-radius: 7px; background: transparent; color: #607268; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; }
.requests-tabs button.active { background: #15803d; color: #fff; }
.request-list { display: grid; gap: 12px; }
.request-card { padding: 18px 20px; border: 1px solid #dbe5de; border-radius: 12px; background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, .04); }
.request-card__top, .request-card__footer { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
.request-card h2 { margin: 9px 0 0; font-size: 17px; }
.request-status { display: inline-flex; padding: 5px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; }
.request-status--pending { background: #fef3c7; color: #92400e; }
.request-status--approved { background: #dcfce7; color: #166534; }
.request-status--rejected { background: #fee2e2; color: #991b1b; }
.request-status--cancelled { background: #e2e8f0; color: #475569; }
.request-detail-link { color: #166534; font-size: 13px; font-weight: 800; white-space: nowrap; }
.request-description { margin: 11px 0; color: #52645a; font-size: 13px; line-height: 1.6; }
.request-facts { display: flex; flex-wrap: wrap; gap: 10px 18px; color: #52645a; font-size: 12px; }
.request-facts span { display: inline-flex; align-items: center; gap: 5px; }
.request-card__footer { margin-top: 15px; padding-top: 12px; border-top: 1px solid #edf2ee; color: #7b8b82; font-size: 12px; }
.requests-state { display: grid; justify-items: center; gap: 8px; padding: 58px 20px; border: 1px solid #dbe5de; border-radius: 12px; background: #fff; color: #718178; text-align: center; }
.requests-state strong { color: #30483a; font-size: 16px; }
.requests-state--error { border-color: #fecaca; color: #991b1b; }
@media (max-width: 640px) { .requests-shell { width: min(100% - 24px, 560px); padding-top: 22px; } .requests-heading { display: grid; align-items: start; } .request-card { padding: 15px; } .request-card__top, .request-card__footer { flex-direction: column; } .request-card__footer { gap: 5px; } }
.request-chat-link { color: #166534; font-weight: 800; }
</style>

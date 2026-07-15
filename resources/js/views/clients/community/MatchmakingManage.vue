<template>
  <div class="matchmaking-manage-page">
    <PublicNavbar />
    <div class="manage-content">
      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Đang tải dữ liệu...</p>
      </div>
      
      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <button class="btn primary" @click="fetchParticipants">Thử lại</button>
      </div>

      <template v-else>
        <!-- Header Info -->
        <div class="post-header">
          <h2>Quản lý yêu cầu ghép kèo</h2>
          <div class="post-info" v-if="post">
            <p><i class="fas fa-map-marker-alt"></i> {{ post.venue_name }}</p>
            <p><i class="far fa-clock"></i> {{ post.time }}</p>
            <p>
              <i class="fas fa-users"></i> Đang cần tuyển: 
              <strong>{{ post.needed_players }} người</strong>
            </p>
          </div>
        </div>

        <!-- Participants List -->
        <div class="participants-section">
          <h3>Danh sách xin tham gia ({{ participants.length }})</h3>
          
          <div v-if="participants.length === 0" class="empty-state">
            <i class="fas fa-user-friends"></i>
            <p>Hiện chưa có ai gửi yêu cầu tham gia.</p>
          </div>

          <div v-else class="participant-list">
            <div class="participant-card" v-for="p in participants" :key="p.user_id">
              <div class="p-info">
                <div class="p-avatar">
                  <img v-if="p.avatar" :src="getAvatarUrl(p.avatar)" alt="avatar" />
                  <div v-else class="p-avatar-placeholder">{{ p.name.charAt(0).toUpperCase() }}</div>
                </div>
                <div class="p-details">
                  <span class="p-name">{{ p.name }}</span>
                  <span class="p-time">{{ formatTime(p.created_at) }}</span>
                </div>
              </div>
              
              <div class="p-actions" v-if="p.status === 'pending'">
                <button class="btn-approve" @click="approve(p.user_id)" :disabled="processingId === p.user_id">
                  <i class="fas fa-check"></i> Đồng ý
                </button>
                <button class="btn-reject" @click="reject(p.user_id)" :disabled="processingId === p.user_id">
                  <i class="fas fa-times"></i> Từ chối
                </button>
              </div>
              <div class="p-status" v-else>
                <span class="status-badge approved" v-if="p.status === 'approved'">Đã chấp nhận</span>
                <span class="status-badge rejected" v-if="p.status === 'rejected'">Đã từ chối</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import { useToast } from 'vue-toastification';

const route = useRoute();
const toast = useToast();
const postId = route.params.id;

const loading = ref(true);
const error = ref(null);
const post = ref(null);
const participants = ref([]);
const processingId = ref(null);

const fetchParticipants = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await api(`/api/matchmaking-posts/${postId}/participants`);
    post.value = res.post;
    participants.value = res.participants;
  } catch (err) {
    error.value = err.message || 'Lỗi tải dữ liệu.';
  } finally {
    loading.value = false;
  }
};

const approve = async (userId) => {
  processingId.value = userId;
  try {
    await api(`/api/matchmaking-posts/${postId}/participants/${userId}/approve`, { method: 'POST' });
    toast.success('Đã chấp nhận người chơi này.');
    const p = participants.value.find(x => x.user_id === userId);
    if (p) p.status = 'approved';
  } catch (err) {
    toast.error(err.message || 'Lỗi thao tác.');
  } finally {
    processingId.value = null;
  }
};

const reject = async (userId) => {
  processingId.value = userId;
  try {
    await api(`/api/matchmaking-posts/${postId}/participants/${userId}/reject`, { method: 'POST' });
    toast.success('Đã từ chối yêu cầu.');
    const p = participants.value.find(x => x.user_id === userId);
    if (p) p.status = 'rejected';
  } catch (err) {
    toast.error(err.message || 'Lỗi thao tác.');
  } finally {
    processingId.value = null;
  }
};

const formatTime = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
};

const getAvatarUrl = (path) => {
  if (!path) return '';
  if (/^https?:\/\//.test(path)) return path;
  if (path.startsWith('/')) return path;
  return `/storage/${path}`;
};

onMounted(() => {
  fetchParticipants();
});
</script>

<style scoped>
.matchmaking-manage-page {
  background-color: #f8fafc;
  min-height: 100vh;
  padding-top: 80px;
}
.manage-content {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}
.post-header {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  margin-bottom: 24px;
}
.post-header h2 {
  margin: 0 0 16px 0;
  font-size: 1.5rem;
  color: #1e293b;
}
.post-info p {
  margin: 8px 0;
  color: #475569;
  display: flex;
  align-items: center;
  gap: 10px;
}
.post-info i {
  color: #3b82f6;
  width: 20px;
}

.participants-section h3 {
  font-size: 1.2rem;
  margin-bottom: 16px;
  color: #334155;
}

.participant-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  padding: 16px;
  border-radius: 12px;
  margin-bottom: 12px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.p-info {
  display: flex;
  align-items: center;
  gap: 16px;
}
.p-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
}
.p-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.p-avatar-placeholder {
  width: 100%;
  height: 100%;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  font-weight: bold;
}
.p-details {
  display: flex;
  flex-direction: column;
}
.p-name {
  font-weight: 600;
  color: #1e293b;
}
.p-time {
  font-size: 0.85rem;
  color: #64748b;
  margin-top: 4px;
}
.p-actions {
  display: flex;
  gap: 8px;
}
.btn-approve, .btn-reject {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.btn-approve {
  background: #10b981;
  color: white;
}
.btn-approve:hover:not(:disabled) { background: #059669; }
.btn-reject {
  background: #ef4444;
  color: white;
}
.btn-reject:hover:not(:disabled) { background: #dc2626; }
.btn-approve:disabled, .btn-reject:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.status-badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
}
.status-badge.approved {
  background: #d1fae5;
  color: #047857;
}
.status-badge.rejected {
  background: #fee2e2;
  color: #b91c1c;
}
.empty-state {
  text-align: center;
  padding: 40px;
  color: #64748b;
  background: white;
  border-radius: 12px;
}
.empty-state i {
  font-size: 3rem;
  color: #cbd5e1;
  margin-bottom: 16px;
}
.loading-state, .error-state {
  text-align: center;
  padding: 40px;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

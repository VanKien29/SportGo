<template>
  <div class="complaint-detail-page">
    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải chi tiết khiếu nại...</p>
    </div>

    <div v-else-if="error" class="notice error">{{ error }}</div>

    <template v-else-if="complaint">
      <!-- Header -->
      <div class="detail-header card">
        <div class="header-main">
          <router-link :to="{ name: 'owner-complaints' }" class="btn ghost icon-only">
            <AppIcon name="arrowLeft" size="20" />
          </router-link>
          <div>
            <h1 class="page-title">Chi tiết khiếu nại</h1>
            <p class="subtitle">
              Mã khiếu nại: <strong>{{ complaint.id.split('-')[0] }}</strong> ·
              Tạo lúc: {{ formatDate(complaint.created_at) }}
            </p>
          </div>
        </div>
        <span class="status-badge" :class="getStatusClass(complaint.status)">
          {{ getStatusLabel(complaint.status) }}
        </span>
      </div>

      <div class="detail-content">
        <!-- Sidebar: Info -->
        <div class="detail-sidebar">
          <div class="card info-card">
            <h3>Thông tin khách hàng</h3>
            <div class="info-row">
              <span class="label">Họ tên:</span>
              <span class="value">{{ complaint.customer?.full_name || 'N/A' }}</span>
            </div>
            <div class="info-row">
              <span class="label">SĐT:</span>
              <span class="value">{{ complaint.customer?.phone || 'N/A' }}</span>
            </div>
            <div class="info-row">
              <span class="label">Email:</span>
              <span class="value">{{ complaint.customer?.email || 'N/A' }}</span>
            </div>
          </div>

          <div class="card info-card" v-if="complaint.booking_detail">
            <h3>Thông tin Booking liên quan</h3>
            <div class="info-row">
              <span class="label">Mã Booking:</span>
              <span class="value booking-code">{{ complaint.booking_detail.booking_code }}</span>
            </div>
            <div class="info-row">
              <span class="label">Thời gian:</span>
              <span class="value">
                {{ formatDateShort(complaint.booking_detail.booking_date) }}<br/>
                {{ formatTime(complaint.booking_detail.start_time) }} - {{ formatTime(complaint.booking_detail.end_time) }}
              </span>
            </div>
            <div class="info-row">
              <span class="label">Cụm sân:</span>
              <span class="value">{{ complaint.booking_detail.venue_cluster?.name }}</span>
            </div>
            <div class="info-row">
              <span class="label">Sân:</span>
              <span class="value">{{ complaint.booking_detail.venue_court?.name }}</span>
            </div>
          </div>
          
          <div class="card info-card" v-else-if="complaint.venue_cluster">
            <h3>Thông tin cụm sân</h3>
            <div class="info-row">
              <span class="label">Tên:</span>
              <span class="value">{{ complaint.venue_cluster.name }}</span>
            </div>
          </div>
        </div>

        <!-- Main Content: Timeline -->
        <div class="detail-main">
          <!-- Initial Complaint -->
          <div class="timeline-item card initial">
            <div class="timeline-header">
              <div class="timeline-user">
                <div class="avatar customer-avatar">
                  <AppIcon name="user" size="16" />
                </div>
                <div class="user-meta">
                  <strong>{{ complaint.customer?.full_name || 'Khách hàng' }}</strong>
                  <span>đã gửi khiếu nại ({{ getComplaintTypeLabel(complaint.complaint_type) }})</span>
                </div>
              </div>
              <div class="timeline-time">{{ formatDateTime(complaint.created_at) }}</div>
            </div>
            <div class="timeline-body">
              <p class="content-text">{{ complaint.content }}</p>
              
              <div class="evidence-list" v-if="complaint.evidence?.length">
                <p class="evidence-title">Bằng chứng đính kèm:</p>
                <div class="media-grid">
                  <a v-for="media in complaint.evidence" :key="media.id" :href="media.file_path" target="_blank" class="media-item">
                    <img v-if="media.mime_type?.startsWith('image/')" :src="media.file_path" :alt="media.file_name" />
                    <div v-else class="media-doc">
                      <AppIcon name="fileText" size="24" />
                      <span>{{ media.file_name }}</span>
                    </div>
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Timeline Items (Audit Logs & Replies) -->
          <div v-for="item in timeline" :key="item.type + item.id" class="timeline-item card" :class="item.type">
            <template v-if="item.type === 'log'">
              <div class="timeline-header system-log">
                <div class="timeline-user">
                  <div class="avatar admin-avatar">
                    <AppIcon name="shield" size="16" />
                  </div>
                  <div class="user-meta">
                    <strong>Hệ thống / Admin</strong>
                    <span>đã chuyển trạng thái: <strong>{{ getStatusLabelFromAction(item.action) }}</strong></span>
                  </div>
                </div>
                <div class="timeline-time">{{ formatDateTime(item.created_at) }}</div>
              </div>
              <div class="timeline-body" v-if="item.details?.reason">
                <div class="system-note">
                  <strong>Ghi chú:</strong> {{ item.details.reason }}
                </div>
              </div>
            </template>

            <template v-else-if="item.type === 'reply'">
              <div class="timeline-header">
                <div class="timeline-user">
                  <div class="avatar owner-avatar">
                    <AppIcon name="building" size="16" />
                  </div>
                  <div class="user-meta">
                    <strong>{{ item.user?.full_name || 'Chủ sân' }}</strong>
                    <span>đã phản hồi giải trình</span>
                  </div>
                </div>
                <div class="timeline-time">{{ formatDateTime(item.created_at) }}</div>
              </div>
              <div class="timeline-body">
                <p class="content-text">{{ item.content }}</p>
                
                <div class="evidence-list" v-if="item.evidence?.length">
                  <div class="media-grid">
                    <a v-for="media in item.evidence" :key="media.id" :href="media.file_path" target="_blank" class="media-item">
                      <img v-if="media.file_name?.match(/\.(jpg|jpeg|png)$/i)" :src="media.file_path" :alt="media.file_name" />
                      <div v-else class="media-doc">
                        <AppIcon name="fileText" size="24" />
                        <span>{{ media.file_name }}</span>
                      </div>
                    </a>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <!-- Reply Form -->
          <div class="card reply-card" v-if="!isFinishedStatus(complaint.status)">
            <h3>Thêm giải trình / Phản hồi</h3>
            <p class="text-muted text-sm mb-4">Gửi phản hồi hoặc bằng chứng liên quan đến khiếu nại này để BQT xem xét.</p>
            
            <form @submit.prevent="submitReply">
              <div class="form-group">
                <label>Nội dung giải trình *</label>
                <textarea 
                  v-model="replyForm.content" 
                  rows="4" 
                  placeholder="Nhập nội dung giải trình..."
                  required
                ></textarea>
              </div>

              <div class="form-group">
                <label>Đính kèm bằng chứng (Hình ảnh/PDF)</label>
                <div class="file-upload">
                  <input type="file" multiple accept="image/*,.pdf" @change="onFilesSelected" ref="fileInput" />
                  <div class="file-list" v-if="replyForm.evidenceFiles.length">
                    <div v-for="(file, i) in replyForm.evidenceFiles" :key="i" class="file-item">
                      <span>{{ file.name }}</span>
                      <button type="button" @click="removeFile(i)" class="btn ghost danger icon-only btn-sm">
                        <AppIcon name="trash" size="14" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn primary" :disabled="submitting || !replyForm.content.trim()">
                  <span v-if="submitting" class="spinner-sm"></span>
                  <span>Gửi phản hồi</span>
                </button>
              </div>
              <div v-if="replyError" class="notice error mt-2">{{ replyError }}</div>
            </form>
          </div>
          
          <div class="card notice text-center" v-else>
            Khiếu nại này đã kết thúc ({{ getStatusLabel(complaint.status) }}), không thể gửi thêm phản hồi.
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/services/api';
import AppIcon from '@/components/ui/AppIcon.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const complaint = ref(null);
const timeline = ref([]);
const error = ref('');

const submitting = ref(false);
const replyError = ref('');
const fileInput = ref(null);
const replyForm = ref({
  content: '',
  evidenceFiles: [],
});

const loadData = async () => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get(`/owner/complaints/${route.params.id}`);
    complaint.value = response.data.data.complaint;
    timeline.value = response.data.data.timeline;
  } catch (err) {
    console.error(err);
    error.value = 'Không thể tải chi tiết khiếu nại.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadData();
});

const onFilesSelected = (e) => {
  const files = Array.from(e.target.files);
  if (replyForm.value.evidenceFiles.length + files.length > 5) {
    alert('Tối đa đính kèm 5 file.');
    return;
  }
  replyForm.value.evidenceFiles = [...replyForm.value.evidenceFiles, ...files];
  fileInput.value.value = '';
};

const removeFile = (index) => {
  replyForm.value.evidenceFiles.splice(index, 1);
};

const submitReply = async () => {
  if (!replyForm.value.content.trim()) return;
  submitting.value = true;
  replyError.value = '';

  try {
    const formData = new FormData();
    formData.append('content', replyForm.value.content);
    replyForm.value.evidenceFiles.forEach((file) => {
      formData.append('evidence[]', file);
    });

    const res = await api.post(`/owner/complaints/${complaint.value.id}/reply`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    // Reset form
    replyForm.value.content = '';
    replyForm.value.evidenceFiles = [];
    
    // Add new reply to timeline and update status if needed
    timeline.value.push(res.data.data);
    if (complaint.value.status === 'open') {
        complaint.value.status = 'processing';
    }

  } catch (err) {
    console.error(err);
    replyError.value = err.response?.data?.message || 'Có lỗi xảy ra khi gửi phản hồi.';
  } finally {
    submitting.value = false;
  }
};

// Utils
const formatDate = (d) => d ? new Date(d).toLocaleDateString('vi-VN') : '';
const formatDateShort = (d) => d ? new Date(d).toLocaleDateString('vi-VN', {day: '2-digit', month: '2-digit'}) : '';
const formatTime = (t) => t ? t.substring(0, 5) : '';
const formatDateTime = (d) => d ? new Date(d).toLocaleString('vi-VN') : '';

const isFinishedStatus = (status) => ['resolved', 'rejected', 'closed'].includes(status);

const getStatusLabel = (status) => {
  const map = {
    open: 'Chờ xử lý',
    processing: 'Đang xử lý',
    resolved: 'Đã giải quyết',
    rejected: 'Bị từ chối',
    closed: 'Đã đóng',
  };
  return map[status] || status;
};

const getStatusClass = (status) => {
  const map = {
    open: 'status-warning',
    processing: 'status-info',
    resolved: 'status-success',
    rejected: 'status-danger',
    closed: 'status-muted',
  };
  return map[status] || 'status-muted';
};

const getComplaintTypeLabel = (type) => type === 'venue' ? 'Sân bãi' : 'Hệ thống';

const getStatusLabelFromAction = (action) => {
  const parts = action.split('.');
  const status = parts[parts.length - 1];
  return getStatusLabel(status);
};
</script>

<style scoped>
.complaint-detail-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
}

.header-main {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-title {
  font-size: 18px;
  margin: 0;
}

.subtitle {
  margin: 4px 0 0 0;
  font-size: 13px;
  color: var(--admin-muted);
}

.detail-content {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

.detail-sidebar {
  width: 300px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.detail-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-card {
  padding: 16px;
}
.info-card h3 {
  font-size: 14px;
  margin: 0 0 16px 0;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--admin-border);
}

.info-row {
  display: flex;
  flex-direction: column;
  margin-bottom: 12px;
}
.info-row:last-child {
  margin-bottom: 0;
}
.info-row .label {
  font-size: 11px;
  text-transform: uppercase;
  color: var(--admin-faint);
  font-weight: 700;
  margin-bottom: 4px;
}
.info-row .value {
  font-size: 14px;
  color: var(--admin-text);
  word-break: break-word;
}
.booking-code {
  font-weight: 600;
  color: var(--admin-primary);
}

/* Timeline */
.timeline-item {
  padding: 16px;
  border-left: 4px solid var(--admin-border);
}
.timeline-item.initial {
  border-left-color: var(--admin-danger);
}
.timeline-item.reply {
  border-left-color: var(--admin-primary);
}
.timeline-item.log {
  border-left-color: var(--admin-warning);
  background: var(--admin-surface-muted);
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.timeline-user {
  display: flex;
  align-items: center;
  gap: 12px;
}
.avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}
.customer-avatar { background: #dc2626; }
.owner-avatar { background: #2563eb; }
.admin-avatar { background: #d97706; }

.user-meta {
  display: flex;
  flex-direction: column;
  font-size: 13px;
}
.user-meta strong { color: var(--admin-text); }
.user-meta span { color: var(--admin-muted); }

.timeline-time {
  font-size: 12px;
  color: var(--admin-faint);
}

.timeline-body {
  padding-left: 44px; /* Align with text */
}
.content-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  white-space: pre-wrap;
}
.system-note {
  font-size: 14px;
  padding: 8px 12px;
  background: var(--admin-surface);
  border-radius: var(--admin-radius-sm);
  border: 1px dashed var(--admin-border);
}

.evidence-list {
  margin-top: 16px;
}
.evidence-title {
  font-size: 13px;
  font-weight: 600;
  margin: 0 0 8px 0;
}
.media-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.media-item {
  width: 100px;
  height: 100px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  display: block;
}
.media-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.media-doc {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: var(--admin-surface-hover);
  color: var(--admin-muted);
  gap: 8px;
  padding: 8px;
  text-align: center;
  font-size: 10px;
}

/* Form */
.reply-card {
  padding: 20px;
  margin-top: 20px;
}
.reply-card h3 {
  margin: 0 0 4px 0;
  font-size: 16px;
}

.form-group {
  margin-bottom: 16px;
}
.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 8px;
}
.form-group textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-sm);
  background: var(--admin-surface);
  color: var(--admin-text);
  font-family: inherit;
  resize: vertical;
}

.file-upload {
  border: 1px dashed var(--admin-border);
  padding: 12px;
  border-radius: var(--admin-radius-sm);
}
.file-list {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.file-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  padding: 8px;
  background: var(--admin-surface-hover);
  border-radius: 4px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}
.spinner-sm {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-right: 8px;
}
.mt-2 { margin-top: 8px; }
.mb-4 { margin-bottom: 16px; }
.text-muted { color: var(--admin-muted); }
.text-sm { font-size: 13px; }
</style>

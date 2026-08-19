<template>
  <div class="w2-white-content">
    <!-- BACK LINK & TOP TITLE -->
          <div class="cp-clean-header">
            <button type="button" class="cp-back-btn" @click="$router.push({ name: 'client-complaints' })">
              <AppIcon name="arrowLeft" :size="16" /> Quay lại danh sách khiếu nại
            </button>

            <div class="cp-title-wrap">
              <div>
                <h1 class="cp-title">Khiếu nại #{{ complaint?.id }}</h1>
                <p class="cp-subtext">{{ typeLabel(complaint?.complaint_type) }} · {{ formatDate(complaint?.created_at) }}</p>
              </div>
              <div v-if="complaint" class="cp-status-tag" :class="`status-${complaint.status}`">
                {{ statusLabel(complaint.status) }}
              </div>
            </div>
          </div>

          <!-- LOADING & ERROR STATES -->
          <div v-if="loading" class="cp-state-text">
            <span class="spinner"></span> Đang tải thông tin...
          </div>
          <div v-else-if="error" class="cp-state-text" role="alert">
            <AppIcon name="alert" :size="20" />
            <span>{{ error }}</span>
            <button type="button" class="w2-btn w2-btn--outline" @click="load">Thử lại</button>
          </div>

          <!-- CLEAN UNCLUTTERED DETAIL WORKSPACE -->
          <template v-else-if="complaint">
            <div class="cp-clean-workspace">
              <!-- MAIN DISCUSSION CONTENT -->
              <div class="cp-main-panel">
                <!-- ORIGINAL REQUEST CONTENT -->
                <div class="cp-request-card">
                  <div class="cp-card-label">Nội dung phản ánh từ khách hàng</div>
                  <p class="cp-request-body">{{ complaint.content }}</p>

                  <!-- EVIDENCE GALLERY -->
                  <div v-if="complaint.evidence && complaint.evidence.length" class="cp-gallery">
                    <span class="cp-gallery-label">Ảnh minh chứng đính kèm ({{ complaint.evidence.length }} ảnh):</span>
                    <div class="cp-gallery-items">
                      <a
                        v-for="img in complaint.evidence"
                        :key="img.id"
                        :href="img.file_path"
                        target="_blank"
                        rel="noopener"
                        class="cp-gallery-link"
                      >
                        <img :src="img.file_path" :alt="img.file_name" class="cp-gallery-img" />
                      </a>
                    </div>
                  </div>
                </div>

                <!-- MESSAGES THREAD STREAM -->
                <div class="cp-thread-section">
                  <h2 class="cp-section-heading">Lịch sử trao đổi</h2>

                  <div class="cp-thread-list">
                    <!-- ORIGINAL SENT ITEM -->
                    <div class="cp-thread-item">
                      <div class="cp-thread-header">
                        <span class="cp-sender-name">Bạn (Khách hàng)</span>
                        <span class="cp-sender-time">{{ formatDate(complaint.created_at) }}</span>
                      </div>
                      <div class="cp-thread-body">{{ complaint.content }}</div>
                    </div>

                    <!-- REPLIES -->
                    <div
                      v-for="item in timeline"
                      :key="item.id"
                      class="cp-thread-item"
                      :class="{ 'is-support': item.user?.id !== complaint.customer_id }"
                    >
                      <div class="cp-thread-header">
                        <span class="cp-sender-name">
                          {{ item.user?.id === complaint.customer_id ? 'Bạn' : (item.user?.full_name || 'Bộ phận hỗ trợ SportGo') }}
                        </span>
                        <span class="cp-sender-time">{{ formatDate(item.created_at) }}</span>
                      </div>
                      <div class="cp-thread-body">{{ item.content }}</div>
                    </div>
                  </div>
                </div>

                <!-- REPLY FORM -->
                <div v-if="canReply" class="cp-reply-section">
                  <h2 class="cp-section-heading">Gửi phản hồi bổ sung</h2>
                  <form @submit.prevent="submitReply" class="cp-reply-form">
                    <textarea
                      v-model.trim="replyContent"
                      rows="4"
                      maxlength="4000"
                      placeholder="Nhập nội dung trao đổi thêm hoặc để trống nếu chỉ bổ sung ảnh..."
                      :disabled="sending"
                      class="cp-reply-textarea"
                    ></textarea>
                    <input
                      ref="replyEvidenceInput"
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      multiple
                      :disabled="sending"
                      @change="selectReplyEvidence"
                    />
                    <small class="cp-help-text">Tối đa 5 ảnh cho toàn bộ khiếu nại, mỗi ảnh 5MB.</small>
                    <div v-if="replyEvidenceFiles.length" class="cp-reply-files">
                      <span v-for="file in replyEvidenceFiles" :key="file.name + file.size">{{ file.name }}</span>
                    </div>
                    <p v-if="replyError" class="cp-error-msg">{{ replyError }}</p>
                    <div class="cp-reply-btn-row">
                      <button type="submit" class="w2-btn w2-btn--primary" :disabled="sending || (!replyContent.length && !replyEvidenceFiles.length)">
                        <AppIcon name="send" :size="15" /> {{ sending ? 'Đang gửi...' : 'Gửi phản hồi' }}
                      </button>
                    </div>
                  </form>
                </div>

                <div v-else class="cp-closed-box">
                  <AppIcon name="circleCheck" :size="18" /> Khiếu nại này đã hoàn thành hoặc kết thúc.
                </div>
              </div>

              <!-- RIGHT SIDEBAR METADATA -->
              <div class="cp-side-panel">
                <div class="cp-info-card">
                  <h3 class="cp-info-title">Thông tin yêu cầu</h3>

                  <div class="cp-info-list">
                    <div class="cp-info-row">
                      <span class="cp-info-key">Trạng thái:</span>
                      <strong class="cp-status-tag" :class="`status-${complaint.status}`">{{ statusLabel(complaint.status) }}</strong>
                    </div>
                    <div class="cp-info-row">
                      <span class="cp-info-key">Loại khiếu nại:</span>
                      <span class="cp-info-val">{{ typeLabel(complaint.complaint_type) }}</span>
                    </div>
                    <div class="cp-info-row">
                      <span class="cp-info-key">Mã booking:</span>
                      <span class="cp-info-val">{{ complaint.booking_id ? `#${complaint.booking_id}` : 'Không gắn booking' }}</span>
                    </div>
                    <div v-if="complaint.venue_cluster?.name" class="cp-info-row">
                      <span class="cp-info-key">Cụm sân:</span>
                      <span class="cp-info-val">{{ complaint.venue_cluster.name }}</span>
                    </div>
                    <div class="cp-info-row">
                      <span class="cp-info-key">Ngày gửi:</span>
                      <span class="cp-info-val">{{ formatDate(complaint.created_at) }}</span>
                    </div>
                  </div>

                  <router-link
                    v-if="complaint.booking_id || complaint.booking?.id"
                    :to="{ name: 'booking-detail', params: { id: complaint.booking_id || complaint.booking.id } }"
                    class="w2-btn w2-btn--outline cp-side-btn"
                  >
                    <AppIcon name="calendar" :size="15" /> Xem booking liên quan
                  </router-link>
                </div>
              </div>
            </div>
    </template>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { complaintService } from '../../services/complaintService.js';

export default {
  name: 'ClientComplaintDetail',
  components: { AppIcon },
  data() {
    return {
      complaint: null,
      timeline: [],
      loading: true,
      error: '',
      replyContent: '',
      replyError: '',
      sending: false,
      replyEvidenceFiles: []
    };
  },
  computed: {
    canReply() {
      return !['resolved', 'rejected', 'closed'].includes(this.complaint?.status);
    }
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await complaintService.get(this.$route.params.id);
        const payload = response.data || {};
        this.complaint = payload.complaint || null;
        this.timeline = payload.timeline || [];
      } catch (error) {
        this.error = error.message || 'Không tải được dữ liệu.';
      } finally {
        this.loading = false;
      }
    },
    async submitReply() {
      this.replyError = '';
      if (!this.replyContent.length && !this.replyEvidenceFiles.length) {
        this.replyError = 'Vui lòng nhập nội dung hoặc chọn ảnh bổ sung.';
        return;
      }
      this.sending = true;
      try {
        const response = await complaintService.reply(this.complaint.id, this.replyContent, this.replyEvidenceFiles);
        this.timeline.push(response.data);
        this.replyContent = '';
        this.replyEvidenceFiles = [];
        if (this.$refs.replyEvidenceInput) this.$refs.replyEvidenceInput.value = '';
        this.complaint.status = 'processing';
      } catch (error) {
        this.replyError = error.message || 'Không thể gửi phản hồi.';
      } finally {
        this.sending = false;
      }
    },
    selectReplyEvidence(event) {
      const files = Array.from(event.target.files || []);
      if (this.replyEvidenceFiles.length + files.length > 5) {
        this.replyError = 'Chỉ được đính kèm tối đa 5 ảnh cho một khiếu nại.';
        return;
      }
      if (files.some((file) => file.size > 5 * 1024 * 1024)) {
        this.replyError = 'Mỗi ảnh minh chứng không được vượt quá 5MB.';
        return;
      }
      const totalSize = files.reduce((sum, file) => sum + file.size, 0);
      if (totalSize > 20 * 1024 * 1024) {
        this.replyError = 'Tổng dung lượng ảnh minh chứng không được vượt quá 20MB.';
        return;
      }
      this.replyEvidenceFiles = files;
      this.replyError = '';
    },
    typeLabel(type) {
      return type === 'venue' ? 'Khiếu nại cụm sân' : 'Khiếu nại hệ thống';
    },
    statusLabel(status) {
      return (
        {
          open: 'Mới gửi',
          processing: 'Đang xử lý',
          resolved: 'Đã xử lý',
          rejected: 'Từ chối',
          closed: 'Đã đóng'
        }[status] ||
        status ||
        'Chưa cập nhật'
      );
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    }
  }
};
</script>

<style scoped>
/* PURE WHITE CONTAINER */
.wallet-white-page {
  min-height: 100vh;
  background: #ffffff;
  color: #0f172a;
}

.wallet-white-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
}

.wallet-layout-grid {
  display: flex;
  gap: 32px;
  align-items: flex-start;
  width: 100%;
}

.w2-white-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* CLEAN HEADER */
.cp-clean-header {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cp-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  background: transparent;
  color: #15803d;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
  width: fit-content;
}

.cp-back-btn:hover {
  text-decoration: underline;
}

.cp-title-wrap {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
}

.cp-title {
  font-size: 22px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.cp-subtext {
  font-size: 13.5px;
  color: #475569;
  margin: 4px 0 0 0;
}

.cp-state-text {
  padding: 40px 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #475569;
  font-size: 14px;
}

/* WORKSPACE GRID */
.cp-clean-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 280px;
  gap: 32px;
  align-items: start;
}

.cp-main-panel {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* ORIGINAL REQUEST CARD */
.cp-request-card {
  background: #f8fafc;
  border-radius: 6px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-left: 3px solid #15803d;
}

.cp-card-label {
  font-size: 13px;
  font-weight: 600;
  color: #15803d;
}

.cp-request-body {
  font-size: 14.5px;
  color: #0f172a;
  margin: 0;
  line-height: 1.6;
  white-space: pre-wrap;
}

/* EVIDENCE GALLERY */
.cp-gallery {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 6px;
  padding-top: 12px;
  border-top: none;
}

.cp-gallery-label {
  font-size: 13px;
  font-weight: 500;
  color: #334155;
}

.cp-gallery-items {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.cp-gallery-link {
  display: inline-block;
}

.cp-gallery-img {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 4px;
  border: none;
  transition: opacity 0.15s ease;
}

.cp-gallery-img:hover {
  opacity: 0.85;
}

/* THREAD SECTION */
.cp-section-heading {
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 14px 0;
}

.cp-thread-section {
  display: flex;
  flex-direction: column;
}

.cp-thread-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.cp-thread-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 0;
  background: transparent;
  border: none;
}

.cp-thread-item.is-support {
  background: transparent;
  border: none;
}

.cp-thread-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13.5px;
}

.cp-sender-name {
  font-weight: 600;
  color: #0f172a;
}

.cp-sender-time {
  font-size: 12px;
  color: #64748b;
}

.cp-thread-body {
  font-size: 14px;
  color: #1e293b;
  line-height: 1.55;
  white-space: pre-wrap;
}

/* REPLY SECTION */
.cp-reply-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cp-reply-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cp-reply-textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 12px 14px;
  font: inherit;
  font-size: 14px;
  color: #0f172a;
  resize: vertical;
  background: #ffffff;
}

.cp-reply-textarea:focus {
  outline: none;
  border-color: #15803d;
}

.cp-help-text {
  color: #64748b;
  font-size: 12px;
}

.cp-reply-files {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  color: #475569;
  font-size: 12px;
}

.cp-reply-files span {
  padding: 4px 8px;
  background: #f1f5f9;
  border-radius: 4px;
}

.cp-reply-btn-row {
  display: flex;
  justify-content: flex-end;
}

.cp-error-msg {
  color: #dc2626;
  font-size: 13px;
  margin: 0;
}

.cp-closed-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px;
  background: #f8fafc;
  color: #475569;
  font-size: 13.5px;
  border-radius: 6px;
}

/* SIDE PANEL */
.cp-side-panel {
  display: flex;
  flex-direction: column;
}

.cp-info-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  background: #f8fafc;
  border-radius: 6px;
}

.cp-info-title {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.cp-info-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  font-size: 13.5px;
}

.cp-info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cp-info-key {
  color: #475569;
}

.cp-info-val {
  color: #0f172a;
  font-weight: 500;
  text-align: right;
}

.cp-side-btn {
  width: 100%;
  justify-content: center;
  margin-top: 4px;
}

/* BUTTON UTILITIES */
.w2-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 16px;
  font-size: 13.5px;
  font-weight: 500;
  border-radius: 4px;
  cursor: pointer;
  text-decoration: none;
  border: 1px solid transparent;
  transition: all 0.15s ease;
}

.w2-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.w2-btn--primary:hover:not(:disabled) {
  background: #166534;
}

.w2-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}

.w2-btn--outline:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #94a3b8;
}

.w2-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* STATUS PILL (HIGH CONTRAST INLINE TEXT) */
.cp-status-tag {
  font-size: 13.5px;
  font-weight: 600;
  background: transparent !important;
  border: none !important;
  padding: 0 !important;
}

.cp-status-tag.status-open {
  color: #1d4ed8; /* Dark high-contrast blue */
}

.cp-status-tag.status-processing {
  color: #b45309; /* Dark high-contrast amber */
}

.cp-status-tag.status-resolved {
  color: #15803d; /* Dark high-contrast green */
}

.cp-status-tag.status-rejected,
.cp-status-tag.status-closed {
  color: #b91c1c; /* Dark high-contrast red */
}

@media (max-width: 900px) {
  .cp-clean-workspace {
    grid-template-columns: 1fr;
  }
}
</style>

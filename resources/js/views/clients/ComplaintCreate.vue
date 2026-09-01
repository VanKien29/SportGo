<template>
  <div class="w2-white-content">
    <!-- TOP HEADER -->
          <div class="cpc-header">
            <button type="button" class="cpc-back-btn" @click="$router.push({ name: 'client-complaints' })">
              <AppIcon name="arrowLeft" :size="16" /> Quay lại danh sách khiếu nại
            </button>
            <div class="cpc-title-row">
              <div>
                <h1 class="cpc-h1">Tạo khiếu nại mới</h1>
                <span class="cpc-subtitle">Gửi thông tin để SportGo hoặc chủ sân hỗ trợ xử lý nhanh nhất</span>
              </div>
            </div>
          </div>

          <!-- FORM BODY GRID -->
          <div class="cpc-body-grid">
            <div class="cpc-form-col">
              <form class="cpc-form-flat" @submit.prevent="submit">
                <div class="cpc-field">
                  <label class="cpc-label">Booking đang hoạt động <b>*</b></label>
                  <select v-model="form.booking_id" class="cpc-input" required @change="selectBooking">
                    <option value="" disabled>
                      {{ bookingLoading ? 'Đang tải booking đủ điều kiện...' : 'Chọn booking đang chơi tại sân' }}
                    </option>
                    <option v-for="item in eligibleBookings" :key="item.id" :value="String(item.id)">
                      {{ bookingOptionLabel(item) }}
                    </option>
                  </select>
                  <small v-if="!bookingLoading && !eligibleBookings.length" class="cpc-help-text">
                    Chỉ có thể gửi khiếu nại trong thời gian booking đang hoạt động tại sân.
                  </small>
                </div>

                <div v-if="booking" class="cpc-context-note">
                  <strong>Booking liên quan: #{{ booking.booking_code }}</strong>
                  <span>{{ booking.start_time }} - {{ booking.end_time }} · {{ booking.minutes_remaining }} phút còn lại trong cửa sổ tiếp nhận</span>
                </div>
                <p v-if="bookingError" class="cpc-field-error">{{ bookingError }}</p>

                <div class="cpc-field">
                  <label class="cpc-label">Cụm sân liên quan</label>
                  <input
                    :value="booking?.venue_cluster?.name || booking?.venueCluster?.name || ''"
                    type="text"
                    placeholder="Chọn booking để tự động điền"
                    readonly
                    class="cpc-input is-readonly"
                  />
                  <small class="cpc-help-text">Thông tin sân được lấy trực tiếp từ booking và không thể chỉnh sửa.</small>
                </div>

                <div class="cpc-field">
                  <label class="cpc-label">Nội dung khiếu nại <b>*</b></label>
                  <textarea
                    v-model.trim="form.content"
                    rows="6"
                    maxlength="2000"
                    placeholder="Mô tả chi tiết vấn đề gặp phải, thời điểm xảy ra và yêu cầu hỗ trợ..."
                    class="cpc-textarea"
                  ></textarea>
                  <div class="cpc-char-count">{{ form.content.length }}/2000 ký tự</div>
                </div>

                <!-- MULTI-IMAGE EVIDENCE UPLOAD -->
                <div class="cpc-field">
                  <label class="cpc-label">Ảnh minh chứng <small>(Tối đa 5 ảnh · 5MB/ảnh · tổng 20MB)</small></label>
                  <input
                    ref="evidenceInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    class="cpc-file-input"
                    @change="selectEvidences"
                  />
                  <div v-if="evidencePreviews.length" class="cpc-preview-grid">
                    <div v-for="(item, idx) in evidencePreviews" :key="idx" class="cpc-thumb-wrap">
                      <img :src="item.url" :alt="item.name" class="cpc-thumb-img" />
                      <button type="button" class="cpc-remove-btn" title="Xóa ảnh" @click="removeEvidence(idx)">✕</button>
                    </div>
                  </div>
                </div>

                <p v-if="error" class="cpc-field-error">{{ error }}</p>
                <router-link
                  v-if="duplicateComplaintId"
                  class="cpc-existing-link"
                  :to="{ name: 'client-complaint-detail', params: { id: duplicateComplaintId } }"
                >
                  Mở khiếu nại hiện tại để bổ sung bằng chứng
                </router-link>

                <div class="cpc-form-actions">
                  <button type="button" class="w2-btn w2-btn--outline" @click="$router.back()">Hủy</button>
                  <button type="submit" class="w2-btn w2-btn--primary" :disabled="submitting || !isValid">
                    <AppIcon name="send" :size="16" /> {{ submitting ? 'Đang gửi...' : 'Gửi khiếu nại' }}
                  </button>
                </div>
              </form>
            </div>

            <!-- SIDEBAR GUIDE -->
            <div class="cpc-side-col">
              <div class="cpc-guide-card">
                <h3 class="cpc-guide-title">Hướng dẫn gửi khiếu nại</h3>
                <ol class="cpc-guide-list">
                  <li>
                    <strong>1. Chọn booking đang hoạt động</strong>
                    <span>Chỉ booking đang trong khung giờ sử dụng sân mới được tiếp nhận.</span>
                  </li>
                  <li>
                    <strong>2. Mô tả vấn đề rõ ràng</strong>
                    <span>Cung cấp chi tiết sự cố, mốc thời gian và yêu cầu xử lý.</span>
                  </li>
                  <li>
                    <strong>3. Bổ sung bằng chứng khi cần</strong>
                    <span>Nếu đã có khiếu nại trùng booking, hãy mở yêu cầu cũ để bổ sung nội dung hoặc ảnh.</span>
                  </li>
                </ol>
                <router-link to="/bookings" class="w2-btn w2-btn--outline cpc-guide-btn">
                  <AppIcon name="calendar" :size="15" /> Chọn từ Lịch đặt sân
                </router-link>
              </div>
            </div>
          </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { complaintService } from '../../services/complaintService.js';
import { businessDateLabel } from '../../utils/businessTime.js';

export default {
  name: 'ComplaintCreate',
  components: { AppIcon },
  data() {
    return {
      form: {
        complaint_type: 'venue',
        booking_id: this.$route.query.booking_id || '',
        venue_cluster_id: '',
        content: ''
      },
      booking: null,
      eligibleBookings: [],
      bookingLoading: false,
      bookingError: '',
      error: '',
      duplicateComplaintId: null,
      submitting: false,
      evidenceFiles: [],
      evidencePreviews: []
    };
  },
  computed: {
    isValid() {
      return (
        this.form.content.length >= 10 &&
        Boolean(this.form.booking_id && this.form.venue_cluster_id)
      );
    }
  },
  mounted() {
    this.loadEligibleBookings();
  },
  methods: {
    async loadEligibleBookings() {
      this.bookingLoading = true;
      this.bookingError = '';
      try {
        const response = await complaintService.eligibleBookings();
        this.eligibleBookings = response.data || [];
        const requestedId = String(this.form.booking_id || '');
        const selected = this.eligibleBookings.find((item) => String(item.id) === requestedId)
          || this.eligibleBookings[0];
        if (selected) {
          this.form.booking_id = String(selected.id);
          this.selectBooking();
        } else if (requestedId) {
          this.form.booking_id = '';
          this.bookingError = 'Booking không còn trong thời gian tiếp nhận khiếu nại.';
        }
      } catch (error) {
        this.bookingError = error.message || 'Không tải được booking đang hoạt động.';
      } finally {
        this.bookingLoading = false;
      }
    },
    selectBooking() {
      const selected = this.eligibleBookings.find((item) => String(item.id) === String(this.form.booking_id));
      this.booking = selected || null;
      this.form.venue_cluster_id = selected?.venue_cluster_id || selected?.venue_cluster?.id || '';
    },
    bookingOptionLabel(booking) {
      const date = booking.booking_date
        ? businessDateLabel(booking.booking_date)
        : 'Chưa rõ ngày';
      const cluster = booking.venue_cluster?.name || booking.venueCluster?.name || 'Cụm sân';
      return `${date} · ${booking.start_time || '--'} - ${booking.end_time || '--'} · ${cluster} · ${booking.booking_code || booking.id}`;
    },
    selectEvidences(event) {
      const files = Array.from(event.target.files || []);
      if (!files.length) return;

      if (this.evidenceFiles.length + files.length > 5) {
        this.error = 'Chỉ được tải lên tối đa 5 ảnh minh chứng.';
        return;
      }

      for (const file of files) {
        if (file.size > 5 * 1024 * 1024) {
          this.error = `Ảnh ${file.name} vượt quá dung lượng 5MB.`;
          return;
        }
      }

      const totalSize = [...this.evidenceFiles, ...files].reduce((sum, file) => sum + file.size, 0);
      if (totalSize > 20 * 1024 * 1024) {
        this.error = 'Tổng dung lượng ảnh minh chứng không được vượt quá 20MB.';
        return;
      }

      this.error = '';
      files.forEach((file) => {
        this.evidenceFiles.push(file);
        this.evidencePreviews.push({
          name: file.name,
          url: URL.createObjectURL(file)
        });
      });

      if (this.$refs.evidenceInput) {
        this.$refs.evidenceInput.value = '';
      }
    },
    removeEvidence(index) {
      if (this.evidencePreviews[index]?.url) {
        URL.revokeObjectURL(this.evidencePreviews[index].url);
      }
      this.evidenceFiles.splice(index, 1);
      this.evidencePreviews.splice(index, 1);
    },
    async submit() {
      this.error = '';
      this.duplicateComplaintId = null;
      if (this.form.content.length < 10) {
        this.error = 'Nội dung cần ít nhất 10 ký tự.';
        return;
      }
      if (!this.form.booking_id || !this.form.venue_cluster_id) {
        this.error = 'Cần chọn booking đang hoạt động tại sân.';
        return;
      }
      this.submitting = true;
      try {
        const payload = new FormData();
        Object.entries(this.form).forEach(([key, value]) => {
          if (value !== '' && value !== null && value !== undefined) {
            payload.append(key, value);
          }
        });
        this.evidenceFiles.forEach((file) => {
          payload.append('evidence_images[]', file);
        });
        const idempotencyKey = typeof crypto !== 'undefined' && crypto.randomUUID
          ? crypto.randomUUID()
          : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const response = await complaintService.create(payload, {
          headers: { 'Idempotency-Key': idempotencyKey }
        });
        const id = response.data?.id || response.data?.complaint?.id;
        if (id) {
          this.$router.replace({ name: 'client-complaint-detail', params: { id } });
        } else {
          this.$router.replace({ name: 'client-complaints' });
        }
      } catch (error) {
        this.duplicateComplaintId = error?.data?.existing_complaint_id || null;
        this.error = this.duplicateComplaintId
          ? `${error.message || 'Booking đã có khiếu nại.'} Mã khiếu nại: #${this.duplicateComplaintId}.`
          : (error.message || 'Không thể gửi khiếu nại.');
      } finally {
        this.submitting = false;
      }
    }
  }
};
</script>

<style scoped>
.wallet-white-page {
  min-height: 100vh;
  background: #ffffff;
}

.wallet-white-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
  color: #0f172a;
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

.cpc-header {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cpc-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  background: transparent;
  color: #15803d;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  width: fit-content;
}

.cpc-back-btn:hover {
  text-decoration: underline;
}

.cpc-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.cpc-h1 {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.cpc-subtitle {
  font-size: 13.5px;
  color: #64748b;
}

.cpc-body-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 280px;
  gap: 32px;
  align-items: start;
}

.cpc-form-col {
  display: flex;
  flex-direction: column;
}

.cpc-form-flat {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.cpc-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cpc-label {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.cpc-label b {
  color: #dc2626;
}

.cpc-label small {
  font-weight: normal;
  color: #64748b;
}

.cpc-input,
.cpc-textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 10px 14px;
  font: inherit;
  font-size: 14px;
  color: #0f172a;
  background: #ffffff;
}

.cpc-input:focus,
.cpc-textarea:focus {
  outline: none;
  border-color: #15803d;
}

.cpc-input.is-readonly {
  background: #f8fafc;
  color: #64748b;
}

.cpc-textarea {
  resize: vertical;
}

.cpc-char-count {
  font-size: 12px;
  color: #94a3b8;
  text-align: right;
}

.cpc-help-text {
  font-size: 12px;
  color: #64748b;
}

.cpc-file-input {
  font-size: 13.5px;
}

.cpc-preview-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 8px;
}

.cpc-thumb-wrap {
  position: relative;
  width: 64px;
  height: 64px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid #cbd5e1;
}

.cpc-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cpc-remove-btn {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.7);
  color: #ffffff;
  border: none;
  font-size: 11px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cpc-context-note {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 16px;
  background: #f0fdf4;
  border-left: 3px solid #15803d;
  font-size: 13.5px;
  color: #166534;
  border-radius: 0 4px 4px 0;
}

.cpc-field-error {
  color: #dc2626;
  font-size: 13px;
  margin: 0;
}

.cpc-existing-link {
  color: #166534;
  font-size: 13px;
  font-weight: 600;
}

.cpc-form-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 10px;
}

.cpc-side-col {
  display: flex;
  flex-direction: column;
}

.cpc-guide-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  background: #f8fafc;
  border-radius: 8px;
}

.cpc-guide-title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.cpc-guide-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 0;
  margin: 0;
  list-style: none;
}

.cpc-guide-list li {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cpc-guide-list strong {
  font-size: 13.5px;
  color: #0f172a;
}

.cpc-guide-list span {
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.45;
}

.cpc-guide-btn {
  width: 100%;
  justify-content: center;
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

@media (max-width: 900px) {
  .cpc-body-grid {
    grid-template-columns: 1fr;
  }
}
</style>

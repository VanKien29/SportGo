<template>
  <div class="profile-wrapper">
    <template v-if="role === 'owner'">
      <section class="page-head">
        <div>
          <h2>Thông tin cá nhân</h2>
          <p>Quản lý thông tin tài khoản chủ sân đang đăng nhập.</p>
        </div>
      </section>
      <div class="profile-content owner-profile-content">
        <ProfileCard :user="user" @go-back="goBack" />
      </div>
    </template>

    <template v-else>
      <PublicNavbar />
      <div class="profile-public-container">
        <div class="profile-public-stack">
          <ProfileCard :user="user" @go-back="goBack" />

          <section class="partner-card">
            <div class="partner-head">
              <div>
                <h3>Đơn đăng ký chủ sân</h3>
                <p>Theo dõi hồ sơ đối tác và ký hợp đồng khi SportGo duyệt.</p>
              </div>
              <button class="mini-btn" type="button" @click="loadPartnerApplication">Làm mới</button>
            </div>

            <div v-if="partnerLoading" class="muted">Đang tải hồ sơ...</div>
            <div v-else-if="!partnerApplication" class="muted">Bạn chưa có đơn đăng ký chủ sân.</div>
            <template v-else>
              <div class="partner-status">
                <span>{{ partnerApplication.venue_name }}</span>
                <strong>{{ statusLabel(partnerApplication.status) }}</strong>
              </div>
              <p v-if="partnerApplication.status === 'rejected'" class="reject-reason">
                <strong>Lý do từ chối:</strong> {{ partnerApplication.status_reason || 'Chưa có lý do.' }}
              </p>
              <div v-if="pendingContract" class="sign-banner">
                <span>Hợp đồng của bạn đã sẵn sàng. Vui lòng ký để hoàn tất.</span>
                <button class="mini-btn dark" type="button" @click="openSignModal">Xem & ký hợp đồng</button>
              </div>
              <div class="mini-timeline">
                <div v-for="item in partnerHistory" :key="`${item.id}-${item.created_at}`" class="mini-timeline-item">
                  <span></span>
                  <p>{{ statusLabel(item.status) }} · {{ formatDate(item.submitted_at) }}</p>
                </div>
              </div>
            </template>
          </section>
        </div>
      </div>
    </template>

    <div v-if="signModal.open" class="modal-backdrop" @click.self="closeSignModal">
      <form class="sign-modal" @submit.prevent="submitSignature">
        <div class="sign-modal-head">
          <h3>Ký hợp đồng đối tác</h3>
          <button class="mini-btn" type="button" @click="closeSignModal">Đóng</button>
        </div>
        <div class="contract-preview">
          {{ pendingContract?.contract?.contract_title || 'Hợp đồng hợp tác đối tác SportGo' }}
        </div>
        <canvas ref="signatureCanvas" class="signature-pad" width="620" height="190" @pointerdown="startDraw" @pointermove="draw" @pointerup="stopDraw" @pointerleave="stopDraw"></canvas>
        <button class="mini-btn" type="button" @click="clearSignature">Xóa chữ ký</button>
        <label class="check-line">
          <input v-model="signModal.accepted" type="checkbox" />
          <span>Tôi đã đọc và đồng ý với toàn bộ nội dung hợp đồng</span>
        </label>
        <div class="sign-modal-actions">
          <button class="mini-btn" type="button" @click="closeSignModal">Hủy</button>
          <button class="mini-btn dark" type="submit" :disabled="signing || !signModal.accepted">Xác nhận ký</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import PublicNavbar from '../components/PublicNavbar.vue';
import ProfileCard from '../components/ProfileCard.vue';
import { api } from '../services/api.js';
import { getAuth } from '../stores/auth.js';

export default {
  name: 'ProfileView',
  components: { PublicNavbar, ProfileCard },
  data() {
    const user = getAuth();
    return {
      user,
      role: user?.role || 'guest',
      partnerLoading: false,
      partnerApplication: null,
      partnerHistory: [],
      pendingContract: null,
      signModal: { open: false, accepted: false },
      signing: false,
      drawing: false,
    };
  },
  created() {
    if (!this.user) {
      this.$router.replace({ name: 'login' });
    }
  },
  mounted() {
    if (this.user && this.role !== 'owner') {
      this.loadPartnerApplication();
    }
  },
  methods: {
    goBack() {
      if (this.role === 'owner') {
        this.$router.push('/owner/dashboard');
        return;
      }

      this.$router.push('/');
    },
    async loadPartnerApplication() {
      this.partnerLoading = true;
      try {
        const [applicationResponse, contractResponse] = await Promise.all([
          api('/api/user/partner-application'),
          api('/api/user/partner-application/pending-contract'),
        ]);
        this.partnerApplication = applicationResponse.data?.latest || null;
        this.partnerHistory = applicationResponse.data?.history || [];
        this.pendingContract = contractResponse.data || null;
      } catch {
        this.partnerApplication = null;
        this.partnerHistory = [];
        this.pendingContract = null;
      } finally {
        this.partnerLoading = false;
      }
    },
    openSignModal() {
      this.signModal = { open: true, accepted: false };
      this.$nextTick(this.prepareCanvas);
    },
    closeSignModal() {
      this.signModal.open = false;
    },
    async submitSignature() {
      this.signing = true;
      try {
        await api('/api/user/partner-application/sign-contract', {
          method: 'POST',
          body: JSON.stringify({
            contract_id: this.pendingContract?.contract?.id,
            signature_image: this.$refs.signatureCanvas?.toDataURL('image/png'),
          }),
        });
        this.closeSignModal();
        await this.loadPartnerApplication();
      } finally {
        this.signing = false;
      }
    },
    prepareCanvas() {
      const canvas = this.$refs.signatureCanvas;
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.strokeStyle = '#0f172a';
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
    },
    pointerPosition(event) {
      const canvas = this.$refs.signatureCanvas;
      const rect = canvas.getBoundingClientRect();
      return {
        x: ((event.clientX - rect.left) / rect.width) * canvas.width,
        y: ((event.clientY - rect.top) / rect.height) * canvas.height,
      };
    },
    startDraw(event) {
      this.drawing = true;
      const point = this.pointerPosition(event);
      const ctx = this.$refs.signatureCanvas.getContext('2d');
      ctx.beginPath();
      ctx.moveTo(point.x, point.y);
    },
    draw(event) {
      if (!this.drawing) return;
      const point = this.pointerPosition(event);
      const ctx = this.$refs.signatureCanvas.getContext('2d');
      ctx.lineTo(point.x, point.y);
      ctx.stroke();
    },
    stopDraw() {
      this.drawing = false;
    },
    clearSignature() {
      this.prepareCanvas();
    },
    statusLabel(status) {
      return {
        pending: 'Chờ duyệt',
        submitted: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        need_supplement: 'Cần bổ sung',
        contract_pending_owner_signature: 'Đang chờ ký hợp đồng',
        contract_pending_sportgo_signature: 'Chờ SportGo ký',
        completed: 'Đang hoạt động',
        rejected: 'Từ chối',
      }[status] || status || '-';
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
.profile-wrapper {
  min-height: 100vh;
}

.profile-content {
  max-width: 600px;
}

.owner-profile-content {
  display: flex;
  align-items: flex-start;
}

.profile-public-container {
  min-height: 100vh;
  background: var(--sg-surface);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 100px 24px 60px;
}

.profile-public-stack {
  width: min(960px, 100%);
  display: grid;
  grid-template-columns: minmax(280px, 420px) minmax(320px, 1fr);
  gap: 18px;
  align-items: start;
}

.partner-card,
.sign-modal {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 18px;
}

.partner-head,
.partner-status,
.sign-banner,
.sign-modal-head,
.sign-modal-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.partner-head h3,
.sign-modal h3 {
  margin: 0;
}

.partner-head p,
.muted,
.mini-timeline-item p {
  color: #64748b;
  font-size: 13px;
}

.partner-status {
  margin: 14px 0;
  padding: 12px;
  border-radius: 8px;
  background: #f8fafc;
}

.reject-reason {
  padding: 12px;
  border-radius: 8px;
  background: #fee2e2;
  color: #991b1b;
}

.sign-banner {
  padding: 12px;
  border-radius: 8px;
  background: #fef3c7;
  color: #92400e;
  font-weight: 800;
}

.mini-btn {
  min-height: 34px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  background: #fff;
  padding: 0 12px;
  font-weight: 900;
  cursor: pointer;
}

.mini-btn.dark {
  background: #0f172a;
  color: #fff;
  border-color: #0f172a;
}

.mini-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.mini-timeline {
  margin-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.mini-timeline-item {
  display: grid;
  grid-template-columns: 12px 1fr;
  gap: 8px;
}

.mini-timeline-item span {
  width: 8px;
  height: 8px;
  margin-top: 5px;
  border-radius: 50%;
  background: #0f172a;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.5);
}

.sign-modal {
  width: min(720px, 100%);
}

.contract-preview {
  max-height: 160px;
  overflow: auto;
  padding: 12px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  background: #f8fafc;
  margin: 14px 0;
  font-weight: 800;
}

.signature-pad {
  width: 100%;
  max-width: 620px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  touch-action: none;
  display: block;
  margin-bottom: 10px;
}

.check-line {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  font-weight: 800;
}

.sign-modal-actions {
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 900px) {
  .profile-public-stack {
    grid-template-columns: 1fr;
  }
}
</style>

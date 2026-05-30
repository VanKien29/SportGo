<template>
  <div v-if="visible" class="policy-popup-overlay">
    <div class="policy-popup">
      <div class="popup-icon">
        <span class="material-icons">gavel</span>
      </div>
      <h2>Chính sách hệ thống</h2>
      <p class="subtitle">Vui lòng đọc và chấp thuận điều khoản cập nhật của chúng tôi để tiếp tục.</p>
      
      <div class="policy-content" v-html="policy.content"></div>
      
      <div class="popup-footer">
        <button class="btn-accept" :disabled="loading" @click="handleAccept">
          {{ loading ? 'Đang xử lý...' : 'Tôi đã đọc và đồng ý' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { systemPolicyService } from '../services/systemPolicyService.js';

export default {
  name: 'PolicyPopup',
  data() {
    return {
      visible: false,
      loading: false,
      policy: null,
    };
  },
  mounted() {
    this.checkPolicy();
  },
  methods: {
    async checkPolicy() {
      try {
        const response = await systemPolicyService.checkAcceptance();
        if (response.needs_acceptance) {
          this.policy = response.policy;
          this.visible = true;
          // Prevent scrolling
          document.body.style.overflow = 'hidden';
        }
      } catch (err) {
        console.error('Failed to check policy acceptance:', err);
      }
    },
    async handleAccept() {
      if (!this.policy) return;
      this.loading = true;
      try {
        await systemPolicyService.accept(this.policy.id);
        this.visible = false;
        document.body.style.overflow = 'auto';
      } catch (err) {
        alert('Có lỗi xảy ra, vui lòng thử lại sau.');
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

<style scoped>
.policy-popup-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.8);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

.policy-popup {
  background: white;
  width: 100%;
  max-width: 600px;
  border-radius: 20px;
  padding: 30px;
  display: flex;
  flex-direction: column;
  gap: 15px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  animation: popupFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes popupFadeIn {
  from { opacity: 0; transform: translateY(20px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.popup-icon {
  width: 60px;
  height: 60px;
  background: #f0fdf4;
  color: #22c55e;
  border-radius: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 5px;
}
.popup-icon .material-icons { font-size: 32px; }

h2 { margin: 0; font-size: 24px; color: #1e293b; }
.subtitle { margin: 0; color: #64748b; font-size: 15px; }

.policy-content {
  margin-top: 10px;
  max-height: 350px;
  overflow-y: auto;
  padding-right: 10px;
  font-size: 14px;
  line-height: 1.6;
  color: #334155;
  border-top: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
  padding: 15px 0;
}

.policy-content::-webkit-scrollbar { width: 6px; }
.policy-content::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

.popup-footer {
  margin-top: 10px;
  display: flex;
  justify-content: center;
}

.btn-accept {
  width: 100%;
  padding: 14px;
  background: #22c55e;
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);
}

.btn-accept:hover {
  background: #16a34a;
  transform: translateY(-1px);
  box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.3);
}

.btn-accept:active { transform: translateY(0); }
.btn-accept:disabled { opacity: 0.7; cursor: not-allowed; }
</style>

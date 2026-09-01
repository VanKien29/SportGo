<template>
  <div class="policy-backdrop">
    <section class="policy-modal" role="dialog" aria-modal="true" aria-labelledby="policy-title">
      <header class="policy-header">
        <p class="eyebrow">CHÍNH SÁCH HỆ THỐNG</p>
        <h2 id="policy-title">Cần xác nhận chính sách mới</h2>
        <p class="header-desc">
          Vui lòng đọc và đồng ý với các chính sách đang có hiệu lực trước khi tiếp tục sử dụng SportGo.
        </p>
      </header>

      <div ref="policyList" class="policy-list" @scroll="onScroll">
        <div v-for="policy in policies" :key="policy.id" class="policy-section">
          <div class="policy-title-line">
            <h3 class="policy-heading">{{ policy.title }}</h3>
            <span class="version-tag">v{{ policy.version }}</span>
          </div>

          <p class="policy-sub-info">
            {{ typeLabel(policy) }} • Hiệu lực từ {{ formatDate(policy.effective_from) }}
          </p>

          <p v-if="policy.change_summary" class="policy-summary-text">
            Tóm tắt thay đổi: {{ policy.change_summary }}
          </p>

          <div class="policy-text-body">{{ policy.content }}</div>
        </div>
      </div>

      <p v-if="!scrolledToBottom" class="scroll-warning">
        Vui lòng cuộn xuống hết danh sách chính sách để có thể bấm chọn đồng ý.
      </p>

      <div class="modal-footer">
        <label class="agree-option" :class="{ disabled: !scrolledToBottom, checked: agreed }">
          <input v-model="agreed" type="checkbox" :disabled="!scrolledToBottom || submitting" />
          <span class="agree-text">Tôi đã đọc và đồng ý với các chính sách trên.</span>
        </label>

        <p v-if="error" class="policy-error-msg">{{ error }}</p>

        <button class="confirm-btn" type="button" :disabled="!agreed || submitting" @click="acceptAll">
          <span>{{ submitting ? 'Đang xử lý...' : 'Xác nhận và tiếp tục' }}</span>
        </button>
      </div>
    </section>
  </div>
</template>

<script>
import { policyService } from '../services/policies.js';

export default {
  name: 'PolicyAcceptanceModal',
  props: {
    policies: {
      type: Array,
      required: true,
    },
  },
  emits: ['accepted'],
  data() {
    return {
      agreed: false,
      submitting: false,
      error: '',
      scrolledToBottom: false,
    };
  },
  mounted() {
    this.$nextTick(this.allowIfNoScroll);
  },
  methods: {
    allowIfNoScroll() {
      const el = this.$refs.policyList;
      if (el && el.scrollHeight <= el.clientHeight + 10) {
        this.scrolledToBottom = true;
      }
    },
    onScroll() {
      const el = this.$refs.policyList;
      if (!el) return;
      if (el.scrollTop + el.clientHeight >= el.scrollHeight - 8) {
        this.scrolledToBottom = true;
      }
    },
    async acceptAll() {
      if (!this.agreed || this.submitting) return;
      this.submitting = true;
      this.error = '';

      try {
        for (const policy of this.policies) {
          await policyService.accept(policy.id);
        }
        this.$emit('accepted');
      } catch (error) {
        this.error = error.message || 'Không thể lưu xác nhận chính sách.';
      } finally {
        this.submitting = false;
      }
    },
    typeLabel(policy) {
      if (policy.policy_type_label) return policy.policy_type_label;

      const type = policy.policy_type || policy.type;
      return {
        general: 'Chung',
        refund: 'Hủy lịch và hoàn tiền',
        booking: 'Đặt sân',
        moderation: 'Kiểm duyệt và báo cáo',
        account: 'Tài khoản',
        platform_fee: 'Phí duy trì cụm sân',
        terms: 'Điều khoản sử dụng',
      }[type] || 'Chính sách';
    },
    formatDate(value) {
      if (!value) return 'ngay lập tức';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(value));
    },
  },
};
</script>

<style scoped>
.policy-backdrop {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(46, 66, 56, 0.75);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.policy-modal {
  width: min(680px, 100%);
  max-height: min(760px, calc(100vh - 48px));
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px;
  border-radius: 20px;
  background: #ffffff;
  border: 2px solid #9ebcb0;
  box-shadow: 0 20px 50px rgba(46, 66, 56, 0.25);
  font-weight: 400;
}

/* Header */
.policy-header {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-bottom: 8px;
}

.eyebrow {
  margin: 0;
  color: #5c7e6e;
  font-size: 13px;
  font-weight: 400;
  letter-spacing: 0.8px;
}

.policy-header h2 {
  margin: 0;
  color: #2e4238;
  font-size: 22px;
  font-weight: 400;
}

.header-desc {
  margin: 0;
  color: #4d6e5f;
  font-size: 14px;
  font-weight: 400;
  line-height: 1.5;
}

/* Policy List - Flat Layout */
.policy-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
  overflow-y: auto;
  padding-right: 8px;
  max-height: 360px;
}

.policy-list::-webkit-scrollbar {
  width: 6px;
}

.policy-list::-webkit-scrollbar-track {
  background: #f2f7f4;
  border-radius: 999px;
}

.policy-list::-webkit-scrollbar-thumb {
  background: #7a9c8c;
  border-radius: 999px;
}

/* Flat Section - No border-bottom or border-top */
.policy-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 12px;
}

.policy-title-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.policy-heading {
  margin: 0;
  color: #2e4238;
  font-size: 17px;
  font-weight: 400;
}

.version-tag {
  padding: 3px 10px;
  border-radius: 999px;
  background: #eef4f1;
  border: 1px solid #9ebcb0;
  color: #4d6e5f;
  font-size: 12px;
  font-weight: 400;
}

.policy-sub-info {
  margin: 0;
  color: #5c7e6e;
  font-size: 13px;
  font-weight: 400;
}

.policy-summary-text {
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: #eef4f1;
  border-left: 3px solid #5c7e6e;
  color: #2e4238;
  font-size: 13.5px;
  font-weight: 400;
  line-height: 1.5;
}

.policy-text-body {
  margin-top: 4px;
  color: #2e4238;
  font-size: 14px;
  font-weight: 400;
  line-height: 1.65;
  white-space: pre-wrap;
}

/* Scroll Warning */
.scroll-warning {
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  font-size: 13px;
  font-weight: 400;
  text-align: center;
}

/* Modal Footer & Actions */
.modal-footer {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.agree-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 0;
  background: transparent;
  border: none;
  cursor: pointer;
  user-select: none;
}

.agree-option.disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.agree-option input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #5c7e6e;
  cursor: pointer;
}

.agree-option.disabled input[type="checkbox"] {
  cursor: not-allowed;
}

.agree-text {
  color: #2e4238;
  font-size: 14px;
  font-weight: 400;
}

.policy-error-msg {
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 13px;
  font-weight: 400;
}

/* Button */
.confirm-btn {
  height: 48px;
  border: none;
  border-radius: 12px;
  background: #5c7e6e;
  color: #ffffff;
  font-size: 15px;
  font-weight: 400;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.confirm-btn:hover:not(:disabled) {
  background: #4d6e5f;
}

.confirm-btn:disabled {
  background: #dce8e2;
  color: #7a9c8c;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .policy-backdrop {
    padding: 16px;
  }

  .policy-modal {
    max-height: calc(100vh - 32px);
    padding: 20px;
  }

  .policy-list {
    max-height: 300px;
  }
}
</style>



<template>
  <div class="policy-backdrop">
    <section class="policy-modal" role="dialog" aria-modal="true" aria-labelledby="policy-title">
      <header class="policy-header">
        <p class="eyebrow">Chính sách hệ thống</p>
        <h2 id="policy-title">Cần xác nhận chính sách mới</h2>
        <p>Vui lòng đọc và đồng ý với các chính sách đang có hiệu lực trước khi tiếp tục sử dụng SportGo.</p>
      </header>

      <div ref="policyList" class="policy-list" @scroll="onScroll">
        <article v-for="policy in policies" :key="policy.id" class="policy-item">
          <div class="policy-item-head">
            <h3>{{ policy.title }}</h3>
            <span>v{{ policy.version }}</span>
          </div>
          <p class="policy-meta">{{ typeLabel(policy.type) }} · Hiệu lực {{ formatDate(policy.effective_from) }}</p>
          <div class="policy-content">{{ policy.content }}</div>
        </article>
      </div>

      <p v-if="!scrolledToBottom" class="scroll-hint">↓ Cuộn xuống để đọc hết các chính sách trước khi xác nhận</p>

      <label class="agree-row" :class="{ disabled: !scrolledToBottom }">
        <input v-model="agreed" type="checkbox" :disabled="!scrolledToBottom" />
        <span>Tôi đã đọc và đồng ý với các chính sách trên.</span>
      </label>

      <p v-if="error" class="policy-error">{{ error }}</p>

      <button class="accept-btn" type="button" :disabled="!agreed || submitting" @click="acceptAll">
        {{ submitting ? 'Đang lưu...' : 'Xác nhận và tiếp tục' }}
      </button>
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
    this.$nextTick(() => {
      const el = this.$refs.policyList;
      if (el) {
        // Nếu nội dung không cần scroll (ít chính sách) thì cho phép luôn
        if (el.scrollHeight <= el.clientHeight + 10) {
          this.scrolledToBottom = true;
        }
      }
    });
  },
  methods: {
    onScroll() {
      const el = this.$refs.policyList;
      if (!el) return;
      // Cho phép sai số 8px để dễ trigger hơn trên mobile
      if (el.scrollTop + el.clientHeight >= el.scrollHeight - 8) {
        this.scrolledToBottom = true;
      }
    },
    async acceptAll() {
      if (!this.agreed) return;
      this.submitting = true;
      this.error = '';

      try {
        await Promise.all(this.policies.map((policy) => policyService.accept(policy.id)));
        this.$emit('accepted');
      } catch (error) {
        this.error = error.message || 'Không thể lưu xác nhận chính sách.';
      } finally {
        this.submitting = false;
      }
    },
    typeLabel(type) {
      return {
        general: 'Chung',
        refund: 'Hoàn tiền',
        booking: 'Đặt sân',
        moderation: 'Kiểm duyệt',
      }[type] || type;
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
  background: rgba(15, 23, 42, 0.62);
}

.policy-modal {
  width: min(720px, 100%);
  max-height: min(760px, calc(100vh - 48px));
  display: grid;
  gap: 18px;
  overflow: hidden;
  padding: 24px;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
}

.policy-header {
  display: grid;
  gap: 8px;
}

.eyebrow {
  color: #15803d;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

.policy-header h2 {
  margin: 0;
  color: #0f172a;
  font-size: 24px;
  font-weight: 900;
}

.policy-header p,
.policy-meta {
  margin: 0;
  color: #64748b;
  font-size: 14px;
  line-height: 1.5;
}

.policy-list {
  display: grid;
  gap: 12px;
  overflow: auto;
  padding-right: 4px;
}

.policy-item {
  display: grid;
  gap: 8px;
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

.policy-item-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.policy-item-head h3 {
  margin: 0;
  color: #111827;
  font-size: 16px;
  font-weight: 900;
}

.policy-item-head span {
  padding: 4px 8px;
  border-radius: 999px;
  background: #dcfce7;
  color: #166534;
  font-size: 12px;
  font-weight: 900;
}

.policy-content {
  max-height: 180px;
  overflow: auto;
  white-space: pre-wrap;
  color: #334155;
  font-size: 14px;
  line-height: 1.65;
}

.scroll-hint {
  margin: 0;
  padding: 8px 12px;
  border-radius: 8px;
  background: #fefce8;
  border: 1px solid #fde68a;
  color: #92400e;
  font-size: 13px;
  font-weight: 700;
  text-align: center;
  animation: pulse-hint 2s ease-in-out infinite;
}

@keyframes pulse-hint {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.65; }
}

.agree-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  color: #0f172a;
  font-size: 14px;
  font-weight: 800;
  transition: opacity 0.3s;
}

.agree-row.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.agree-row input {
  width: 18px;
  height: 18px;
  margin-top: 1px;
  accent-color: #16a34a;
}

.policy-error {
  margin: 0;
  padding: 10px 12px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 13px;
  font-weight: 800;
}

.accept-btn {
  height: 46px;
  border-radius: 8px;
  background: #16a34a;
  color: #fff;
  font-weight: 900;
}

.accept-btn:disabled {
  opacity: .58;
  cursor: not-allowed;
}
</style>

<template>
  <section class="settings-page">
    <header class="page-head">
      <h2>Thiết lập chung</h2>
    </header>

    <form class="panel form" @submit.prevent="saveSettings">
      <label>
        Số ngày mặc định trước hạn thanh toán
        <input v-model.number="settings.default_due_days" type="number" min="0" />
      </label>
      <label class="check-row">
        <input v-model="settings.auto_mark_overdue" type="checkbox" />
        <span>Tự động chuyển chờ thanh toán sang quá hạn khi chạy kiểm tra nhắc phí</span>
      </label>
      <label>
        Lý do khóa cụm sân mặc định
        <textarea v-model.trim="settings.lock_reason" rows="3"></textarea>
      </label>
      <div class="actions">
        <button class="btn primary" type="submit">Lưu cài đặt</button>
        <button class="btn secondary" type="button" @click="reset">Khôi phục mặc định</button>
      </div>
    </form>

    <div v-if="toast" class="toast">{{ toast }}</div>
  </section>
</template>

<script>
import { platformFeeStore } from '../../stores/platformFee.store.js';

export default {
  name: 'AdminPlatformFeeSettings',
  data() {
    return {
      settings: { ...platformFeeStore.state.settings },
      toast: '',
    };
  },
  methods: {
    saveSettings() {
      platformFeeStore.state.settings = { ...this.settings };
      platformFeeStore.save();
      this.show('Đã lưu cài đặt phí duy trì.');
    },
    reset() {
      this.settings = {
        default_due_days: 7,
        auto_mark_overdue: true,
        lock_reason: 'Quá hạn phí duy trì hệ thống',
      };
      this.saveSettings();
    },
    show(message) {
      this.toast = message;
      setTimeout(() => { this.toast = ''; }, 3000);
    },
  },
};
</script>

<style scoped>
.settings-page { display: flex; min-width: 0; flex-direction: column; gap: 16px; }
.page-head { display: flex; align-items: center; min-height: 36px; }
.panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
h2, p { margin: 0; }
.form { display: grid; grid-template-columns: 1fr; gap: 16px; width: min(100%, 720px); }
label { display: flex; flex-direction: column; gap: 6px; font-weight: 700; color: #334155; font-size: 14px; }
input, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font: inherit; }
.check-row { flex-direction: row; align-items: center; }
.check-row input { width: auto; }
.actions { display: flex; gap: 10px; }
.btn { border: 0; border-radius: 8px; padding: 9px 16px; font-weight: 700; cursor: pointer; font-size: 14px; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #e2e8f0; color: #334155; }
.toast { border-radius: 8px; padding: 10px 13px; font-weight: 700; background: #ecfdf5; color: #047857; }
@media (max-width: 640px) {
  .actions { flex-direction: column; }
  .btn { width: 100%; }
}
</style>

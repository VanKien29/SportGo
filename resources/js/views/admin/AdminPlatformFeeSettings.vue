<template>
  <section class="settings-page">
    <header class="panel">
      <p class="eyebrow">Cai dat phi duy tri</p>
      <h2>Cau hinh xu ly phi duy tri he thong</h2>
      <p>Cac gia tri nay duoc luu trong shared mock store frontend.</p>
    </header>

    <form class="panel form" @submit.prevent="saveSettings">
      <label>
        So ngay mac dinh truoc han thanh toan
        <input v-model.number="settings.default_due_days" type="number" min="0" />
      </label>
      <label class="check-row">
        <input v-model="settings.auto_mark_overdue" type="checkbox" />
        <span>Tu dong chuyen pending sang overdue khi chay kiem tra nhac phi</span>
      </label>
      <label>
        Ly do khoa cum san mac dinh
        <textarea v-model.trim="settings.lock_reason" rows="3"></textarea>
      </label>
      <div class="actions">
        <button class="btn primary" type="submit">Luu cai dat</button>
        <button class="btn secondary" type="button" @click="reset">Khoi phuc mac dinh</button>
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
      this.show('Da luu cai dat phi duy tri.');
    },
    reset() {
      this.settings = {
        default_due_days: 7,
        auto_mark_overdue: true,
        lock_reason: 'Qua han phi duy tri he thong',
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
.settings-page { display: flex; flex-direction: column; gap: 16px; }
.panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; font-weight: 900; text-transform: uppercase; }
h2, p { margin: 0; }
.form { display: grid; grid-template-columns: 1fr; gap: 14px; max-width: 720px; }
label { display: flex; flex-direction: column; gap: 6px; font-weight: 800; color: #334155; }
input, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font: inherit; }
.check-row { flex-direction: row; align-items: center; }
.check-row input { width: auto; }
.actions { display: flex; gap: 10px; }
.btn { border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 900; cursor: pointer; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #e2e8f0; color: #334155; }
.toast { border-radius: 8px; padding: 11px 13px; font-weight: 800; background: #ecfdf5; color: #047857; }
</style>

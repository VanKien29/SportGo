<template>
  <section class="lock-policy-page">
    <header class="page-head">
      <div>
        <h2>Cấu hình chính sách khóa tự động</h2>
        <p>Thiết lập ngưỡng và thời hạn tự động khóa tài khoản khi vượt số lượt báo cáo.</p>
      </div>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="state-card">Đang tải cấu hình...</div>

    <template v-else>
      <!-- Cấu hình hiện tại -->
      <div v-if="currentPolicy.id" class="current-config">
        <h3>Cấu hình hiện tại</h3>
        <div class="config-grid">
          <div class="config-item">
            <span>Trạng thái</span>
            <strong :class="{ 'text-green': currentPolicy.auto_lock_enabled, 'text-red': !currentPolicy.auto_lock_enabled }">
              {{ currentPolicy.auto_lock_enabled ? 'Đang bật' : 'Đang tắt' }}
            </strong>
          </div>
          <div class="config-item">
            <span>Ngưỡng báo cáo</span>
            <strong>{{ currentPolicy.report_threshold }}</strong>
          </div>
          <div class="config-item">
            <span>Thời hạn khóa</span>
            <strong>{{ durationLabel(currentPolicy.lock_duration_hours) }}</strong>
          </div>
          <div class="config-item">
            <span>Người cập nhật</span>
            <strong>{{ currentPolicy.created_by_name || '-' }}</strong>
          </div>
          <div class="config-item">
            <span>Thời gian cập nhật</span>
            <strong>{{ dateTime(currentPolicy.updated_at || currentPolicy.created_at) }}</strong>
          </div>
        </div>
      </div>

      <!-- Form cấu hình -->
      <form class="config-form" @submit.prevent="savePolicy">
        <h3>Chỉnh sửa cấu hình</h3>

        <label class="toggle-label">
          <span>Bật khóa tự động</span>
          <div class="toggle-wrap">
            <input type="checkbox" v-model="form.auto_lock_enabled" class="toggle-input" />
            <div class="toggle-slider" :class="{ on: form.auto_lock_enabled }"></div>
          </div>
        </label>

        <div :class="{ 'fields-disabled': !form.auto_lock_enabled }">
          <label>
            <span>Số lượt báo cáo để khóa</span>
            <input type="number" v-model.number="form.report_threshold" min="1" :disabled="!form.auto_lock_enabled" />
          </label>

          <label>
            <span>Thời hạn khóa tự động</span>
            <select v-model="form.lock_duration_hours" :disabled="!form.auto_lock_enabled">
              <option :value="1">1 giờ</option>
              <option :value="24">24 giờ</option>
              <option :value="168">7 ngày</option>
              <option :value="720">30 ngày</option>
              <option :value="null">Vĩnh viễn</option>
            </select>
          </label>
        </div>

        <footer>
          <button type="submit" class="btn" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu cấu hình' }}
          </button>
        </footer>
      </form>
    </template>
  </section>
</template>

<script>
import { adminUserService } from '../../services/adminUserService.js';

export default {
  name: 'AdminUserLockPolicy',
  data() {
    return {
      currentPolicy: {},
      form: {
        auto_lock_enabled: false,
        report_threshold: 5,
        lock_duration_hours: 24,
      },
      loading: false,
      saving: false,
      error: '',
      success: '',
    };
  },
  mounted() {
    this.loadPolicy();
  },
  methods: {
    async loadPolicy() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminUserService.getLockPolicy();
        const data = response.data || {};
        this.currentPolicy = data;
        this.form = {
          auto_lock_enabled: data.auto_lock_enabled || false,
          report_threshold: data.report_threshold || 5,
          lock_duration_hours: data.lock_duration_hours ?? null,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được cấu hình.';
      } finally {
        this.loading = false;
      }
    },
    async savePolicy() {
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        const response = await adminUserService.saveLockPolicy({
          auto_lock_enabled: this.form.auto_lock_enabled,
          report_threshold: this.form.report_threshold,
          lock_duration_hours: this.form.lock_duration_hours,
        });
        this.success = response.message || 'Lưu cấu hình thành công.';
        if (response.data) {
          this.currentPolicy = response.data;
        }
        // Auto-hide success after 3s
        setTimeout(() => { this.success = ''; }, 3000);
      } catch (err) {
        this.error = err.message || 'Không thể lưu cấu hình.';
      } finally {
        this.saving = false;
      }
    },
    durationLabel(hours) {
      if (!hours) return 'Vĩnh viễn';
      if (hours === 1) return '1 giờ';
      if (hours === 24) return '24 giờ';
      if (hours === 168) return '7 ngày';
      if (hours === 720) return '30 ngày';
      return `${hours} giờ`;
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
  },
};
</script>

<style scoped>
.lock-policy-page { display: grid; gap: 16px; max-width: 800px; }
.page-head h2 { margin: 0 0 6px; }
.page-head p { margin: 0; color: #64748b; }

.current-config, .config-form, .state-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;
}
.current-config h3, .config-form h3 { margin: 0 0 14px; }
.config-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.config-item { padding: 12px; background: #f8fafc; border-radius: 8px; display: grid; gap: 4px; }
.config-item span { font-size: 12px; color: #64748b; }
.text-green { color: #16a34a; }
.text-red { color: #b91c1c; }

.config-form { display: grid; gap: 16px; }
.toggle-label { display: flex; justify-content: space-between; align-items: center; font-weight: 800; }
.toggle-wrap { position: relative; }
.toggle-input { opacity: 0; position: absolute; }
.toggle-slider {
  width: 48px; height: 26px; border-radius: 13px; background: #e2e8f0; cursor: pointer;
  transition: background 0.2s; position: relative;
}
.toggle-slider::after {
  content: ''; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px;
  border-radius: 50%; background: #fff; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle-slider.on { background: #16a34a; }
.toggle-slider.on::after { transform: translateX(22px); }

.fields-disabled { opacity: 0.5; pointer-events: none; }

label { display: grid; gap: 6px; font-weight: 800; color: #334155; }
label span { font-size: 13px; }
input, select { border: 1px solid #dbe3ef; border-radius: 8px; padding: 10px; font: inherit; background: #fff; }
input:disabled, select:disabled { background: #f1f5f9; cursor: not-allowed; }

.config-form footer { display: flex; justify-content: flex-end; }
.btn { border: 0; border-radius: 8px; font-weight: 800; cursor: pointer; padding: 10px 18px; background: #16a34a; color: #fff; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.alert { padding: 12px; border-radius: 10px; font-weight: 700; }
.error { background: #fee2e2; color: #b91c1c; }
.success { background: #dcfce7; color: #166534; }
.state-card { color: #64748b; text-align: center; }
</style>

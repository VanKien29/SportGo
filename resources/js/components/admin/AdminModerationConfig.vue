<template>
  <div class="moderation-config-wrapper">
    <div class="config-head">
      <div>
        <span class="config-kicker">Kiểm duyệt & Báo cáo</span>
        <h3>Cấu hình Ngưỡng & Hình phạt</h3>
        <p>Thiết lập điểm tự động xử lý và các mức phạt vi phạm theo từng đối tượng cụ thể.</p>
      </div>
    </div>

    <!-- Tabs dọc (Sidebar) -->
    <div class="moderation-tabs-layout">
      <div class="moderation-sidebar">
        <button
          v-for="target in TARGET_TYPE_LABELS"
          :key="target.key"
          class="target-tab-btn"
          :class="{ active: activeTarget === target.key }"
          @click="activeTarget = target.key"
        >
          {{ target.label }}
        </button>
      </div>

      <!-- Nội dung chính bên phải -->
      <div class="moderation-content">
        <div v-if="loading" class="empty-state">Đang tải cấu hình...</div>
        <div v-else>
          <!-- Phần 1: Cấu hình điểm -->
          <div class="score-card">
            <div class="card-header">
              <h4>Ngưỡng điểm ({{ getTargetLabel(activeTarget) }})</h4>
              <button class="btn secondary" :disabled="!canEditConfig" @click="openScoreModal">
                Sửa ngưỡng điểm
              </button>
            </div>
            <div class="score-grid" v-if="currentScoreConfig">
              <div class="stat-box">
                <span>{{ dynamicLabels.autoHide }}</span>
                <strong>{{ currentScoreConfig.auto_hide_score }}</strong>
              </div>
              <div class="stat-box">
                <span>{{ dynamicLabels.adminAlert }}</span>
                <strong>{{ currentScoreConfig.admin_alert_score }}</strong>
              </div>
              <div class="stat-box">
                <span>Thời gian tính (Ngày)</span>
                <strong>{{ currentScoreConfig.score_window_days }}</strong>
              </div>
            </div>
            
            <div class="empty-state sm" v-else>
              Chưa cấu hình ngưỡng điểm cho đối tượng này.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal sửa ngưỡng điểm -->
    <div v-if="showScoreModal" class="mod-modal-bg" @click.self="showScoreModal = false">
      <form class="mod-modal-box" @submit.prevent="saveScoreConfig">
        <div class="mod-modal-head">
          <h3>Sửa Ngưỡng điểm ({{ getTargetLabel(activeTarget) }})</h3>
          <button class="mod-icon-btn" type="button" @click="showScoreModal = false">✕</button>
        </div>
        <div class="mod-modal-body">
          <div class="mod-form-group">
            <label>{{ dynamicLabels.autoHide }}</label>
            <input class="mod-input" type="number" v-model.number="scoreDraft.auto_hide_score" required min="1" />
          </div>
          <div class="mod-form-group">
            <label>{{ dynamicLabels.adminAlert }}</label>
            <input class="mod-input" type="number" v-model.number="scoreDraft.admin_alert_score" required min="1" />
          </div>
          <div class="mod-form-group">
            <label>Thời gian tính (Ngày)</label>
            <input class="mod-input" type="number" v-model.number="scoreDraft.score_window_days" required min="1" />
          </div>
          <p class="mod-error-text" v-if="scoreError">{{ scoreError }}</p>
        </div>
        <div class="mod-modal-foot">
          <button class="btn secondary" type="button" @click="showScoreModal = false">Hủy</button>
          <button class="btn primary" type="submit" :disabled="savingScore">Lưu cấu hình</button>
        </div>
      </form>
    </div>

  </div>
</template>

<script>
import { TARGET_TYPE_LABELS, ESCALATION_ACTION_LABELS } from '../../utils/labelMaps.js';
import { api } from '../../services/api.js';

export default {
  name: 'AdminModerationConfig',
  props: {
    policyId: { type: String, required: true },
    canEditConfig: { type: Boolean, default: false }
  },
  data() {
    return {
      loading: true,
      activeTarget: 'community_post',
      scoreThresholds: [],
      
      showScoreModal: false,
      scoreDraft: null,
      scoreError: '',
      savingScore: false,
    };
  },
  computed: {
    TARGET_TYPE_LABELS() {
      return Object.entries(TARGET_TYPE_LABELS).map(([key, label]) => ({ key, label }));
    },
    currentScoreConfig() {
      return this.scoreThresholds.find(s => s.target_type === this.activeTarget);
    },
    allowedActionsForTarget() {
      const all = Object.entries(ESCALATION_ACTION_LABELS).map(([key, label]) => ({ key, label }));
      if (['community_post', 'venue_post', 'comment'].includes(this.activeTarget)) {
        return all.filter(a => ['warn', 'hide_content', 'delete_content'].includes(a.key));
      }
      if (this.activeTarget === 'user') {
        return all.filter(a => ['warn', 'lock_temp', 'lock_permanent'].includes(a.key));
      }
      if (this.activeTarget === 'venue_cluster') {
        return all.filter(a => ['warn', 'limit_venue', 'block_venue', 'terminate_contract'].includes(a.key));
      }
      return all;
    },
    dynamicLabels() {
      const target = this.activeTarget;
      let autoHide = 'Số lượt báo cáo (tối thiểu)';
      let adminAlert = 'Số người báo cáo khác nhau';
      
      if (['community_post', 'venue_post', 'comment'].includes(target)) {
        autoHide = 'Số lượt báo cáo (để tự động ẩn)';
        adminAlert = 'Số người báo cáo khác nhau (cảnh báo)';
      } else if (target === 'user') {
        autoHide = 'Ngưỡng tự động khóa (Số người báo cáo khác nhau)';
        adminAlert = 'Ngưỡng cảnh báo Admin (Số người báo cáo khác nhau)';
      } else if (target === 'venue_cluster') {
        autoHide = 'Ngưỡng tự động phạt sân (Số người báo cáo khác nhau)';
        adminAlert = 'Ngưỡng cảnh báo Admin (Số người báo cáo khác nhau)';
      }
      
      return { autoHide, adminAlert };
    }
  },
  mounted() {
    this.fetchScoreThresholds();
  },
  methods: {
    getTargetLabel(key) {
      return TARGET_TYPE_LABELS[key] || key;
    },
    getActionLabel(key) {
      return ESCALATION_ACTION_LABELS[key] || key;
    },
    requiresDuration(actionType) {
      return ['lock_temp', 'limit_venue', 'block_venue'].includes(actionType);
    },
    getDisabledDurationLabel(actionType) {
      if (['lock_permanent', 'terminate_contract'].includes(actionType)) return 'Vĩnh viễn (Cả đời)';
      if (actionType === 'warn') return '1 lần (Chỉ thông báo)';
      if (['hide_content', 'delete_content'].includes(actionType)) return 'Xử lý ngay lập tức';
      if (actionType === 'manual_review') return 'Chờ Admin duyệt';
      return 'Không áp dụng';
    },
    async fetchScoreThresholds() {
      this.loading = true;
      try {
        const res = await api(`/api/admin/policies/${this.policyId}/moderation-thresholds`);
        this.scoreThresholds = res.data || [];
      } catch (e) {
        console.error(e);
      } finally {
        this.loading = false;
      }
    },
    
    // --- Score Modal ---
    openScoreModal() {
      this.scoreError = '';
      if (this.currentScoreConfig) {
        this.scoreDraft = { ...this.currentScoreConfig };
      } else {
        this.scoreDraft = {
          target_type: this.activeTarget,
          auto_hide_score: 5,
          admin_alert_score: 3,
          score_window_days: 7
        };
      }
      this.showScoreModal = true;
    },
    async saveScoreConfig() {
      this.savingScore = true;
      this.scoreError = '';
      try {
        let action_type = 'warn';
        if (['community_post', 'venue_post', 'comment'].includes(this.scoreDraft.target_type)) {
          action_type = 'hide_content';
        } else if (this.scoreDraft.target_type === 'user') {
          action_type = 'lock_temp';
        } else if (this.scoreDraft.target_type === 'venue_cluster') {
          action_type = 'limit_venue';
        }

        const payload = { 
          score_thresholds: [
            {
              ...this.scoreDraft,
              action_type: action_type,
              duration_days: this.scoreDraft.duration_days || 7,
              score_reset_days: this.scoreDraft.score_reset_days || 30
            }
          ] 
        };
        await api(`/api/admin/policies/${this.policyId}/moderation-thresholds`, {
          method: 'PUT',
          body: JSON.stringify(payload)
        });
        await this.fetchScoreThresholds();
        this.showScoreModal = false;
      } catch (e) {
        this.scoreError = e.message || 'Lỗi khi lưu ngưỡng điểm.';
      } finally {
        this.savingScore = false;
      }
    }
  }
};
</script>

<style scoped>
.moderation-config-wrapper {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  margin-top: 24px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
}

.config-head {
  padding: 24px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}
.config-kicker {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  margin-bottom: 8px;
  display: block;
}
.config-head h3 {
  margin: 0 0 8px 0;
  font-size: 1.25rem;
  color: #0f172a;
}
.config-head p {
  margin: 0;
  color: #64748b;
  font-size: 0.95rem;
}

.moderation-tabs-layout {
  display: flex;
  min-height: 400px;
}

.moderation-sidebar {
  width: 260px;
  background: #fdfdfd;
  border-right: 1px solid #e2e8f0;
  padding: 16px 0;
  display: flex;
  flex-direction: column;
}

.target-tab-btn {
  background: none;
  border: none;
  padding: 14px 24px;
  text-align: left;
  font-size: 0.95rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
  font-weight: 500;
  position: relative;
}
.target-tab-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}
.target-tab-btn.active {
  color: #10b981;
  background: #ecfdf5;
  border-left-color: #10b981;
  font-weight: 600;
}

.moderation-content {
  flex: 1;
  padding: 32px;
  background: #ffffff;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}
.score-card, .escalation-card {
  margin-bottom: 48px;
}
.score-card h4, .escalation-card h4 {
  margin: 0;
  font-size: 1.1rem;
  color: #1e293b;
  font-weight: 600;
}

.score-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}
.stat-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 20px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s, box-shadow 0.2s;
}
.stat-box:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
}
.stat-box span {
  font-size: 0.85rem;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 12px;
}
.stat-box strong {
  font-size: 1.75rem;
  font-weight: 700;
  color: #0f172a;
}

.empty-state.sm {
  padding: 32px;
  font-size: 0.95rem;
  border-radius: 12px;
  background: #f8fafc;
  color: #64748b;
  text-align: center;
  border: 1px dashed #cbd5e1;
}

.config-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.config-table th, .config-table td {
  padding: 16px;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}
.config-table th {
  background: #f8fafc;
  font-weight: 600;
  color: #475569;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.config-table tr:hover td {
  background: #fcfcfc;
}

/* Modals */
.mod-modal-bg {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.mod-modal-box {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  animation: modSlideUp 0.3s ease-out;
}
.mod-modal-box.mod-wide {
  max-width: 800px;
}

@keyframes modSlideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.mod-modal-head {
  padding: 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f8fafc;
}
.mod-modal-head h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #0f172a;
}

.mod-icon-btn {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  transition: background 0.2s, color 0.2s;
}
.mod-icon-btn:hover {
  background: #e2e8f0;
  color: #334155;
}
.mod-icon-btn.danger {
  color: #ef4444;
}
.mod-icon-btn.danger:hover {
  background: #fef2f2;
}

.mod-modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.mod-form-group {
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
}
.mod-form-group label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 8px;
}
.mod-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.95rem;
  color: #0f172a;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: #fff;
  box-sizing: border-box;
}
.mod-input:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}
.mod-input-disabled {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  background: #f1f5f9;
  color: #94a3b8;
  box-sizing: border-box;
  cursor: not-allowed;
  user-select: none;
}

.mod-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: #475569;
  cursor: pointer;
  margin-top: 10px;
  font-weight: 500;
}
.mod-checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #10b981;
}

.mod-edit-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 20px;
}

.mod-config-row {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
}

.mod-escalation-grid {
  display: grid;
  grid-template-columns: 120px 1fr 140px 40px;
  gap: 16px;
  align-items: center;
}
.mod-escalation-grid .mod-form-group {
  margin-bottom: 0;
}

.mod-step-badge {
  background: #f1f5f9;
  color: #475569;
  padding: 8px 12px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  text-align: center;
  border: 1px solid #e2e8f0;
}
.mod-catch-all {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px dashed #cbd5e1;
}
.mod-hint-text {
  font-size: 0.95rem;
  color: #64748b;
  margin-bottom: 24px;
  line-height: 1.5;
}
.mt-3 {
  margin-top: 16px;
}

.mod-error-text {
  color: #ef4444;
  font-size: 0.9rem;
  margin-top: 16px;
}

.mod-modal-foot {
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn.primary {
  background: #10b981;
  color: #fff;
  box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}
.btn.primary:hover:not(:disabled) {
  background: #059669;
}
.btn.primary.outline {
  background: transparent;
  color: #10b981;
  border: 1px solid #10b981;
  box-shadow: none;
}
.btn.primary.outline:hover:not(:disabled) {
  background: #ecfdf5;
}
.btn.secondary {
  background: #ffffff;
  color: #475569;
  border: 1px solid #cbd5e1;
}
.btn.secondary:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #94a3b8;
}
</style>

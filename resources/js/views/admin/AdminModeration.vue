<template>
  <div class="moderation-page">
    <header class="page-head" style="margin-bottom: 20px;">
      <div>
        <h2>Kiểm duyệt & Báo cáo</h2>
        <p>Quản lý kiểm duyệt nội dung và giải quyết các báo cáo vi phạm cộng đồng.</p>
      </div>
      <!-- Nút hành động tương ứng với tab đang hoạt động -->
      <div class="head-actions" style="display: flex; gap: 10px; align-items: center;">
        <div v-if="activeModuleTab === 'moderation'" class="auto-approve-wrapper">
          <label class="switch">
            <input type="checkbox" :checked="autoApproveEnabled" @change="toggleModerationAutoApprove" />
            <span class="slider"></span>
          </label>
          <span class="switch-label">Duyệt tự động (5s)</span>
        </div>
        <button
          v-if="activeModuleTab === 'reports'"
          type="button"
          class="btn"
          style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; background: #fff; padding: 10px 14px; font-weight: 800; border-radius: 8px; cursor: pointer; transition: all 0.2s;"
          @click="triggerReportsAutoResolve"
        >
          <AppIcon name="settings" size="16" /> Cấu hình tự động xử lý báo cáo
        </button>
        <ActionIconButton
          v-if="activeModuleTab === 'reports'"
          icon="refresh"
          label="Tải lại"
          @click="triggerReportsRefresh"
        />
        <ActionIconButton
          v-if="activeModuleTab === 'complaints'"
          icon="refresh"
          label="Tải lại"
          @click="triggerComplaintsRefresh"
        />
      </div>
    </header>


    <!-- Render tab tương ứng -->
    <keep-alive>
      <AdminContentModeration 
        v-if="activeModuleTab === 'moderation'" 
        ref="moderationTab"
        @auto-approve-changed="onAutoApproveChanged"
      />
      <AdminReports ref="reportsTab" v-else-if="activeModuleTab === 'reports'" />
      <AdminComplaints ref="complaintsTab" v-else-if="activeModuleTab === 'complaints'" />
    </keep-alive>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ActionIconButton from '../../components/ActionIconButton.vue';
import AdminContentModeration from './AdminContentModeration.vue';
import AdminReports from './AdminReports.vue';
import AdminComplaints from './AdminComplaints.vue';

export default {
  name: 'AdminModeration',
  components: {
    AppIcon,
    ActionIconButton,
    AdminContentModeration,
    AdminReports,
    AdminComplaints,
  },
  data() {
    return {
      activeModuleTab: 'moderation',
      autoApproveEnabled: false,
      moduleTabs: [
        { label: 'Bài đăng', value: 'moderation', icon: 'eye' },
        { label: 'Báo cáo', value: 'reports', icon: 'messageWarning' },
        { label: 'Khiếu nại', value: 'complaints', icon: 'shieldCheck' },
      ],
    };
  },
  created() {
    // Đọc tab hoạt động từ query param
    const tab = this.$route.query.tab;
    if (tab && this.moduleTabs.some(t => t.value === tab)) {
      this.activeModuleTab = tab;
    }
  },
  watch: {
    '$route.query.tab'(newTab) {
      if (newTab && this.moduleTabs.some(t => t.value === newTab)) {
        this.activeModuleTab = newTab;
      }
    },
  },
  methods: {
    selectModuleTab(tabValue) {
      this.activeModuleTab = tabValue;
      // Đẩy tab value lên URL query param để giữ trạng thái khi reload
      this.$router.push({
        path: this.$route.path,
        query: { ...this.$route.query, tab: tabValue },
      }).catch(err => {
        // Bỏ qua lỗi NavigationDuplicated của vue-router
        if (err && err.name !== 'NavigationDuplicated') {
          console.error(err);
        }
      });
    },
    triggerReportsAutoResolve() {
      this.$refs.reportsTab?.openAutoResolveModal();
    },
    triggerReportsRefresh() {
      this.$refs.reportsTab?.loadReports();
    },
    triggerComplaintsRefresh() {
      this.$refs.complaintsTab?.loadComplaints();
    },
    onAutoApproveChanged(val) {
      this.autoApproveEnabled = val;
    },
    toggleModerationAutoApprove(event) {
      const checked = event.target.checked;
      this.autoApproveEnabled = checked;
      const child = this.$refs.moderationTab;
      if (child) {
        child.autoApproveEnabled = checked;
        child.toggleAutoApprove();
      }
    },
  },
};
</script>

<style scoped>
.moderation-page {
  display: flex !important;
  min-width: 0;
  flex-direction: column;
  width: 100%;
}


/* AUTO APPROVE TOGGLE */
.auto-approve-wrapper {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #f8fafc;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
}

.switch {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 22px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: .4s;
  border-radius: 22px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #10b981;
}

input:focus + .slider {
  box-shadow: 0 0 1px #10b981;
}

input:checked + .slider:before {
  transform: translateX(18px);
}

.switch-label {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}
</style>

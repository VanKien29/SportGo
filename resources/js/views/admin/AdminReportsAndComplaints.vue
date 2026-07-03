<template>
  <div class="moderation-page">
    <div class="toolbar card" style="margin-bottom: 24px; padding: 16px; border-radius: 12px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div class="tabs-header" style="display: flex; gap: 8px;">
        <button
          class="tab-btn"
          :class="{ active: activeModuleTab === 'reports' }"
          @click="selectModuleTab('reports')"
        >
          <AppIcon name="messageWarning" size="16" />
          <span>Báo cáo</span>
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeModuleTab === 'complaints' }"
          @click="selectModuleTab('complaints')"
        >
          <AppIcon name="shieldCheck" size="16" />
          <span>Khiếu nại</span>
        </button>
      </div>
    </div>

    <!-- Render tab tương ứng -->
    <keep-alive>
      <AdminReports ref="reportsTab" v-if="activeModuleTab === 'reports'" />
      <AdminComplaints ref="complaintsTab" v-else-if="activeModuleTab === 'complaints'" />
    </keep-alive>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import AdminReports from './AdminReports.vue';
import AdminComplaints from './AdminComplaints.vue';

export default {
  name: 'AdminReportsAndComplaints',
  components: {
    AppIcon,
    AdminReports,
    AdminComplaints,
  },
  data() {
    return {
      activeModuleTab: 'reports',
    };
  },
  created() {
    const tab = this.$route.query.tab;
    if (tab === 'reports' || tab === 'complaints') {
      this.activeModuleTab = tab;
    }
  },
  watch: {
    '$route.query.tab'(newTab) {
      if (newTab === 'reports' || newTab === 'complaints') {
        this.activeModuleTab = newTab;
      }
    },
  },
  methods: {
    selectModuleTab(tabValue) {
      this.activeModuleTab = tabValue;
      this.$router.push({
        path: this.$route.path,
        query: { ...this.$route.query, tab: tabValue },
      }).catch(err => {
        if (err && err.name !== 'NavigationDuplicated') {
          console.error(err);
        }
      });
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

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  background: white;
  color: #64748b;
  border: 1px solid #e2e8f0;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  background: #f8fafc;
  color: #334155;
  border-color: #cbd5e1;
}

.tab-btn.active {
  background: #10b981;
  color: white;
  border-color: #10b981;
}
</style>

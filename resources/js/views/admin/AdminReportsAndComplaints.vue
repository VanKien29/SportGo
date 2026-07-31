<template>
  <div class="cluster-profile-surface standalone">
    <div class="profile-section-card reports-complaints-main-content">
      <div class="moderation-page">
        <div style="display: flex; gap: 12px; margin: 0;">
          <button class="tab-btn" :class="{ active: activeModuleTab === 'reports' }" @click="selectModuleTab('reports')">
            <AppIcon name="flag" size="18" /> Báo cáo
          </button>
          <button class="tab-btn" :class="{ active: activeModuleTab === 'complaints' }" @click="selectModuleTab('complaints')">
            <AppIcon name="message-square" size="18" /> Khiếu nại
          </button>
        </div>

        <!-- Render tab tương ứng -->
        <keep-alive>
          <AdminReports ref="reportsTab" v-if="activeModuleTab === 'reports'" />
          <AdminComplaints ref="complaintsTab" v-else-if="activeModuleTab === 'complaints'" />
        </keep-alive>
      </div>
    </div>
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
  font-weight: 400;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn.never-hover-class-placeholder {
  background: #f8fafc;
  color: #334155;
  border-color: #cbd5e1;
}

.tab-btn.active {
  background: #10b981;
  color: white;
  border-color: #10b981;
}

.profile-section-card.reports-complaints-main-content {
  background: var(--admin-surface, #ffffff);
  border: none !important;
  border-radius: 0;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.profile-section-card.reports-complaints-main-content :is(.card, .table-container, .saas-table-container, .state-box, .filter-toolbar, .moderation-page, .complaints-page) {
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 0 !important;
  margin: 0 !important;
}
</style>

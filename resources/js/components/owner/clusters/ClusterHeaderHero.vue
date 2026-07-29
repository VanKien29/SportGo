<template>
  <div class="cluster-header-hero-wrapper">
    <!-- Combined Master Hero Header Card (Profile & Navigation in 1 Unit) -->
    <section v-if="cluster" class="cluster-hero-surface">
      <div class="cluster-hero-main">
        <div class="cluster-hero-copy">
          <div class="cluster-hero-kicker">
            <span class="status-chip" :class="statusClass">
              {{ statusLabel }}
            </span>
            <span v-if="cluster.phone_contact" class="phone-tag">Hotline: {{ cluster.phone_contact }}</span>

          </div>

          <h1 class="cluster-title">{{ cluster.name }}</h1>
          <p class="cluster-address">{{ fullAddress }}</p>
        </div>

        <div class="cluster-hero-right-col">
          <div class="cluster-hero-media" :class="{ empty: !primaryImage }">
            <img v-if="primaryImage" :src="primaryImage" :alt="'Ảnh cụm sân ' + cluster.name" />
            <div v-else class="cluster-media-placeholder">
              <AppIcon name="building" size="32" />
              <span>Chưa có ảnh</span>
            </div>
          </div>

        </div>
      </div>

      <!-- Restriction Banner -->
      <div
        v-if="isTerminationRestricted || isClusterArchived"
        class="cluster-restriction-banner"
        :class="{ archived: isClusterArchived }"
      >
        <AppIcon :name="isClusterArchived ? 'archive' : 'lock'" size="18" />
        <div>
          <strong>{{ isClusterArchived ? 'Cụm sân đã chấm dứt hợp tác' : 'Cụm sân đang trong quy trình chấm dứt' }}</strong>
          <p v-if="isClusterArchived">Dữ liệu và lịch sử được giữ để tra cứu; các thao tác vận hành đã khóa.</p>
          <p v-else>Chỉ xử lý booking, hoàn tiền, số dư và hồ sơ chấm dứt tại các màn được phép.</p>
        </div>
      </div>

      <!-- Seamless Integrated Tab Navigation Bar inside the Header Card -->
      <div v-if="tabs && tabs.length > 0" class="hero-integrated-tabs">
        <AppTabs :tabs="tabs" :model-value="activeTab" @update:model-value="$emit('tab-change', $event)" />
      </div>
    </section>
  </div>
</template>

<script>
import AppIcon from '../../AppIcon.vue';
import AppTabs from '../../common/AppTabs.vue';

export default {
  name: 'ClusterHeaderHero',
  components: { AppIcon, AppTabs },
  props: {
    cluster: { type: Object, default: null },
    clusters: { type: Array, default: () => [] },
    tabs: { type: Array, default: () => [] },
    activeTab: { type: String, default: '' },
    courtCount: { type: Number, default: 0 },
    pendingApprovalCount: { type: Number, default: 0 },
    pendingLocationCount: { type: Number, default: 0 },
    pendingUnlockCount: { type: Number, default: 0 },
    isClusterArchived: { type: Boolean, default: false },
    isClusterLocked: { type: Boolean, default: false },
    isTerminationRestricted: { type: Boolean, default: false },
    isModerationLocked: { type: Boolean, default: false },
    statusClass: { type: String, default: '' },
    statusLabel: { type: String, default: '' },
    fullAddress: { type: String, default: '' },
    primaryImage: { type: String, default: '' },
  },
  emits: ['tab-change', 'select-cluster', 'open-termination', 'open-partner-profile'],
};
</script>

<style scoped>
.cluster-header-hero-wrapper {
  display: flex;
  flex-direction: column;
}

.cluster-hero-surface {
  background: var(--admin-surface, #ffffff);
  border-radius: 12px;
  padding: 24px 24px 16px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.cluster-hero-main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
}

.cluster-hero-copy {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cluster-hero-kicker {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  color: var(--admin-muted, #64748b);
}

.status-chip {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 500;
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-text, #0f172a);
}

.status-chip.active { background: #dcfce7; color: #166534; }
.status-chip.pending { background: #fef9c3; color: #854d0e; }
.status-chip.locked, .status-chip.archived { background: #fee2e2; color: #991b1b; }

.phone-tag {
  color: var(--admin-muted, #64748b);
  font-weight: 500;
}

.cluster-title {
  margin: 0;
  font-size: 22px;
  font-weight: 400;
  color: var(--admin-text, #0f172a);
}

.cluster-address {
  margin: 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
  line-height: 1.4;
}

.cluster-hero-right-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  flex-shrink: 0;
}

.cluster-hero-media {
  width: 120px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--admin-hover, #f1f5f9);
}

.cluster-hero-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cluster-media-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: var(--admin-muted, #94a3b8);
  font-size: 10px;
}

.cluster-hero-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-sm {
  height: 32px;
  padding: 0 10px;
  font-size: 12px;
}

.hero-integrated-tabs {
  padding-top: 12px;
  border-top: 1px solid var(--admin-border-soft, rgba(255, 255, 255, 0.08));
}

.cluster-restriction-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.08);
  color: var(--admin-text, #0f172a);
  font-size: 13px;
}

.cluster-restriction-banner.archived {
  background: var(--admin-hover, #f1f5f9);
}

@media (max-width: 768px) {
  .cluster-hero-main {
    flex-direction: column;
  }
  .cluster-hero-right-col {
    align-items: flex-start;
    width: 100%;
  }
  .cluster-hero-media {
    width: 100%;
    height: 120px;
  }
}
</style>

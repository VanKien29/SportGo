<template>
  <aside class="sidebar" :class="sidebarStyle" :aria-label="`${workspaceLabel} navigation`">
    <!-- One-Level Sidebar -->
    <template v-if="sidebarStyle === 'one-level'">
      <RouterLink v-if="showUtilityNavigation" class="admin-brand" :to="homeUrl" @click="$emit('navigate')">
        <span class="admin-brand-mark">SG</span>
        <span v-if="!collapsed" class="admin-brand-copy">
          <strong>SportGo</strong>
          <small>{{ workspaceLabel }}</small>
        </span>
      </RouterLink>



      <nav class="sidebar-nav">
        <section v-for="section in sections" :key="section.label" class="admin-nav-section">
          <p v-if="!collapsed" class="nav-group">{{ section.label }}</p>
          <div v-else class="nav-group-dot"></div>
          <RouterLink
            v-for="item in section.items"
            :key="item.to"
            class="nav-item"
            :class="{ 'nav-active': isActive(item) }"
            :to="item.to"
            @click="$emit('navigate')"
          >
            <AppIcon :name="item.icon" size="17" />
            <span v-if="!collapsed">{{ item.label }}</span>
          </RouterLink>
        </section>
      </nav>

      <!-- Bottom Actions matching mockup -->
      <div v-if="showUtilityNavigation" class="sidebar-bottom">
        <div class="sidebar-divider"></div>
        
        <RouterLink
          v-if="showUtilityNavigation"
          class="nav-item"
          :class="{ 'nav-active': activeRouteName === 'owner-chat' }"
          to="/owner/chat"
          @click="$emit('navigate')"
        >
          <span class="nav-item-left">
            <AppIcon name="messageSquare" size="17" />
            <span v-if="!collapsed" class="nav-item-label">Tin nhắn</span>
          </span>
        </RouterLink>

        <RouterLink
          v-if="showUtilityNavigation"
          class="nav-item"
          to="/"
          @click="$emit('navigate')"
        >
          <span class="nav-item-left">
            <AppIcon name="eye" size="17" />
            <span v-if="!collapsed" class="nav-item-label">Xem trang khách</span>
          </span>
        </RouterLink>

        <RouterLink
          v-if="showUtilityNavigation"
          class="nav-item"
          :class="{ 'nav-active': activeRouteName === 'owner-settings' }"
          to="/owner/settings"
          @click="$emit('navigate')"
        >
          <span class="nav-item-left">
            <AppIcon name="settings" size="17" />
            <span v-if="!collapsed" class="nav-item-label">Cài đặt giao diện</span>
          </span>
        </RouterLink>

        <button class="nav-item logout-btn" type="button" @click="handleLogout">
          <span class="nav-item-left">
            <AppIcon name="logOut" size="17" />
            <span v-if="!collapsed" class="nav-item-label">Đăng xuất</span>
          </span>
        </button>
      </div>

      <div v-if="showUtilityNavigation" class="sidebar-user">
        <div class="user-avatar">{{ userInitial }}</div>
        <div v-if="!collapsed" class="user-info">
          <div class="user-name">{{ userName }}</div>
          <div class="user-role">{{ roleLabel }}</div>
        </div>
      </div>
    </template>

    <!-- Two-Level Sidebar -->
    <template v-else>
      <div class="sidebar-two-level-container">
        <!-- Left Rail -->
        <div class="icon-nav-rail">
          <div class="rail-icons">
            <button
              v-for="(sec, idx) in sections"
              :key="sec.label"
              type="button"
              class="rail-icon-btn"
              :class="{ active: currentSectionIndex === idx }"
              :title="sec.label"
              @click="setSection(idx)"
            >
              <AppIcon :name="getSectionIcon(sec.label)" size="18" />
            </button>
          </div>

          <div v-if="showUtilityNavigation" class="rail-bottom">
            <RouterLink
              v-if="showUtilityNavigation"
              class="rail-icon-btn"
              :class="{ active: activeRouteName === 'owner-chat' }"
              to="/owner/chat"
              title="Tin nhắn"
            >
              <AppIcon name="messageSquare" size="18" />
            </RouterLink>
            <RouterLink
              v-if="showUtilityNavigation"
              class="rail-icon-btn"
              to="/"
              title="Xem trang khách"
            >
              <AppIcon name="eye" size="18" />
            </RouterLink>
            <RouterLink
              v-if="showUtilityNavigation"
              class="rail-icon-btn"
              :class="{ active: activeRouteName === 'owner-settings' }"
              to="/owner/settings"
              title="Cấu hình giao diện"
            >
              <AppIcon name="settings" size="18" />
            </RouterLink>
            <button
              type="button"
              class="rail-icon-btn"
              title="Đăng xuất"
              @click="handleLogout"
            >
              <AppIcon name="logOut" size="18" />
            </button>
          </div>
        </div>

        <!-- Right Detail Sidebar -->
        <div v-if="!collapsed" class="detail-sidebar">
          <div class="detail-sidebar-header-title">
            <span>{{ sections[currentSectionIndex].label }}</span>
          </div>


          
          <div class="detail-sidebar-nav">
            <RouterLink
              v-for="item in sections[currentSectionIndex].items"
              :key="item.to"
              class="nav-item"
              :class="{ 'nav-active': isActive(item) }"
              :to="item.to"
              @click="$emit('navigate')"
            >
              <AppIcon :name="item.icon" size="17" />
              <span>{{ item.label }}</span>
            </RouterLink>
          </div>
        </div>
      </div>
    </template>
  </aside>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { getAuth, logout } from '../../stores/auth.js';

export default {
  name: 'OwnerSidebar',
  components: { AppIcon },
  props: {
    sections: { type: Array, required: true },
    activeRouteName: { type: String, default: '' },
    collapsed: { type: Boolean, default: false },
    userRoleLabel: { type: String, default: '' },
    workspaceLabel: { type: String, default: 'Owner Console' },
    homeUrl: { type: String, default: '/owner/dashboard' },
    showUtilityNavigation: { type: Boolean, default: true },
  },
  emits: ['cluster-change', 'navigate'],
  data() {
    return {
      sidebarStyle: localStorage.getItem('owner-sidebar-style') || 'one-level',
      localActiveSectionIndex: null,
    };
  },
  mounted() {
    window.addEventListener('owner-sidebar-style-changed', this.loadSidebarStyle);
  },
  beforeUnmount() {
    window.removeEventListener('owner-sidebar-style-changed', this.loadSidebarStyle);
  },
  computed: {
    user() {
      return getAuth() || {};
    },
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'Chủ sân';
    },
    userInitial() {
      return this.userName.charAt(0).toUpperCase();
    },
    roleLabel() {
      return this.userRoleLabel || 'Chủ sân';
    },
    currentSectionIndex() {
      if (this.localActiveSectionIndex !== null) {
        return this.localActiveSectionIndex;
      }
      const idx = this.sections.findIndex(sec => 
        sec.items.some(item => item.activeNames?.includes(this.activeRouteName))
      );
      return idx >= 0 ? idx : 0;
    },
  },
  methods: {
    async handleLogout() {
      if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
        await logout();
        this.$router.push('/login');
      }
    },
    isActive(item) {
      return item.activeNames?.includes(this.activeRouteName);
    },
    loadSidebarStyle() {
      this.sidebarStyle = localStorage.getItem('owner-sidebar-style') || 'one-level';
      this.localActiveSectionIndex = null;
    },
    getSectionIcon(label) {
      const iconMap = {
        'Tổng quan': 'dashboard',
        'Vận hành sân': 'building',
        'Kinh doanh': 'banknote',
        'Nhân sự': 'users',
        'Công việc': 'dashboard',
        'Tin nhắn': 'messageSquare',
        'Hệ thống': 'settings',
      };
      return iconMap[label] || 'alert';
    },
    setSection(idx) {
      this.localActiveSectionIndex = idx;
      const targetItem = this.sections[idx].items[0];
      if (targetItem && targetItem.to) {
        const isCurrentSection = this.sections[idx].items.some(item => 
          item.activeNames?.includes(this.activeRouteName)
        );
        if (!isCurrentSection) {
          this.$router.push(targetItem.to);
        }
      }
    }
  },
};
</script>

<style scoped>
.owner-cluster-card {
  display: grid;
  gap: 8px;
  margin: 16px 14px 2px;
  padding: 12px;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-header-gradient);
  box-shadow: var(--admin-shadow-sm);
}

.owner-cluster-card span {
  color: var(--admin-faint);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.owner-cluster-card strong {
  color: var(--admin-text);
  font-size: 13px;
  font-weight: 800;
}

.custom-select-wrapper {
  position: relative;
  width: 100%;
}

.custom-select-trigger {
  min-height: 38px;
  width: 100%;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font-size: 13px;
  font-weight: 700;
  padding: 8px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  user-select: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.custom-select-trigger.never-hover-class-placeholder {
  border-color: var(--admin-primary);
}

.custom-select-trigger.active {
  border-color: var(--admin-primary);
  box-shadow: 0 0 0 2px var(--admin-primary-ring);
}

.custom-select-trigger .arrow {
  font-size: 8px;
  color: var(--admin-faint);
  transition: transform 0.2s;
}

.custom-select-trigger .arrow.open {
  transform: rotate(180deg);
}

.custom-options-container {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  box-shadow: var(--admin-shadow-lg, 0 10px 15px -3px rgba(0, 0, 0, 0.1));
  z-index: 100;
  max-height: 200px;
  overflow-y: auto;
  padding: 4px 0;
}

.custom-option {
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--admin-text);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  text-align: left;
}

.custom-option.never-hover-class-placeholder {
  background: var(--admin-bg-soft);
}

.custom-option.selected {
  background: var(--admin-primary-soft);
  color: var(--admin-primary);
  font-weight: 700;
}

.owner-cluster-card p {
  margin: 0;
  color: var(--admin-danger);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.45;
}
</style>

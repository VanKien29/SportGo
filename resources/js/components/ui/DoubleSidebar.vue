<template>
  <aside class="double-sidebar sidebar" aria-label="Main Navigation">
    <!-- Left Column: Icon Navigation -->
    <div class="sidebar-left">
      <div class="logo-container" @click="handleBrandClick">
        <!-- Custom logo if configured -->
        <img v-if="customLogo" :src="customLogo" alt="SportGo" class="custom-sidebar-logo" />
        <!-- Double-block premium logo -->
        <svg v-else class="brand-logo-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <rect width="18" height="6" x="3" y="4" rx="1.5" fill="currentColor" />
          <rect width="18" height="6" x="3" y="14" rx="1.5" fill="currentColor" />
        </svg>
      </div>

      <nav class="left-nav">
        <div
          v-for="(section, idx) in sections"
          :key="section.label"
          class="left-nav-item-wrapper"
        >
          <button
            class="left-nav-item"
            :class="{ active: activeSectionIndex === idx }"
            @click="activeSectionIndex = idx"
            :aria-label="section.label"
            type="button"
          >
            <AppIcon :name="getSectionIcon(section)" size="20" />
          </button>
          <span class="sidebar-tooltip">{{ section.label }}</span>
        </div>
      </nav>

      <div class="left-bottom">
        <!-- Settings button at the bottom -->
        <div class="left-nav-item-wrapper">
          <router-link :to="settingsRoute" class="left-nav-item" aria-label="Cài đặt">
            <AppIcon name="settings" size="20" />
          </router-link>
          <span class="sidebar-tooltip">Cài đặt</span>
        </div>
        <!-- User quick avatar at absolute bottom -->
        <div class="left-nav-item-wrapper">
          <button class="left-nav-item user-avatar-btn" @click="toggleUserDropdown" aria-label="Tài khoản" type="button">
            <div class="user-avatar-mini">{{ userInitial }}</div>
          </button>
          <span class="sidebar-tooltip">Tài khoản</span>
        </div>
      </div>
    </div>

    <!-- Right Column: Detail List Navigation -->
    <div class="sidebar-right">
      <div class="right-header">
        <svg class="brand-logo-svg-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <rect width="18" height="6" x="3" y="4" rx="1.5" fill="currentColor" />
          <rect width="18" height="6" x="3" y="14" rx="1.5" fill="currentColor" />
        </svg>
        <span class="brand-title">SportGo</span>
      </div>

      <!-- Active Section Label -->
      <div class="active-section-header">
        <h2 class="active-section-title">{{ currentSectionTitle }}</h2>
        <span class="chevron-decor">
          <AppIcon name="chevronRight" size="14" />
        </span>
      </div>

      <!-- Search Input -->
      <div class="search-box-container">
        <div class="search-box">
          <AppIcon name="search" size="14" class="search-icon" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search..."
            class="search-input"
          />
          <button v-if="searchQuery" @click="searchQuery = ''" class="search-clear" type="button">
            <AppIcon name="x" size="12" />
          </button>
        </div>
      </div>

      <!-- Custom slot for headers, dropdowns (e.g. Cluster Selector) -->
      <div class="extra-content">
        <slot name="extra"></slot>
      </div>

      <!-- Navigation links list -->
      <nav class="right-nav">
        <!-- When Search is empty: group items inside the active section -->
        <template v-if="!searchQuery">
          <div class="nav-group-section">
            <p class="nav-group-title">Danh mục chính</p>
            <div class="nav-items-list">
              <router-link
                v-for="item in activeSectionItems"
                :key="item.to"
                class="dsb-nav-item"
                :class="{ 'dsb-nav-active': isActive(item) }"
                :to="item.to"
                @click="$emit('navigate')"
              >
                <AppIcon :name="item.icon" size="16" class="item-icon" />
                <span class="item-label">{{ item.label }}</span>
                <!-- Decor chevron if item has custom styling or to match image -->
                <AppIcon name="chevronDown" size="12" class="chevron-down-decor" />
              </router-link>
            </div>
          </div>
        </template>

        <!-- When Search is active: show matching items across all sections grouped -->
        <template v-else>
          <div
            v-for="section in searchedSections"
            :key="section.label"
            class="nav-group-section"
          >
            <p class="nav-group-title">{{ section.label }}</p>
            <div class="nav-items-list">
              <router-link
                v-for="item in section.items"
                :key="item.to"
                class="dsb-nav-item"
                :class="{ 'dsb-nav-active': isActive(item) }"
                :to="item.to"
                @click="$emit('navigate')"
              >
                <AppIcon :name="item.icon" size="16" class="item-icon" />
                <span class="item-label">{{ item.label }}</span>
                <AppIcon name="chevronDown" size="12" class="chevron-down-decor" />
              </router-link>
            </div>
          </div>
          <div v-if="searchedSections.length === 0" class="no-results">
            Không tìm thấy kết quả
          </div>
        </template>
      </nav>

      <!-- Bottom User details block -->
      <div class="sidebar-user-block">
        <div class="user-block-trigger" @click="toggleUserDropdown">
          <div class="user-avatar-circle">{{ userInitial }}</div>
          <div class="user-text-details">
            <div class="user-display-name">{{ userName }}</div>
            <div class="user-display-role">{{ roleLabel }}</div>
          </div>
          <button class="more-menu-btn" type="button" aria-label="Tài khoản menu">
            <AppIcon name="moreHorizontal" size="16" />
          </button>
        </div>

        <!-- Dropdown Dialog Menu -->
        <transition name="dropdown-fade">
          <div v-if="showUserDropdown" class="user-dropdown-menu" v-click-outside="closeUserDropdown">
            <router-link :to="profileRoute" class="dropdown-menu-item" @click="showUserDropdown = false">
              <AppIcon name="users" size="14" />
              <span>Thông tin cá nhân</span>
            </router-link>

            <router-link v-if="showViewUserBtn" :to="viewUserRoute" class="dropdown-menu-item" @click="showUserDropdown = false">
              <AppIcon name="eye" size="14" />
              <span>{{ viewUserLabel }}</span>
            </router-link>

            <button class="dropdown-menu-item logout-btn" @click="handleLogout" type="button">
              <AppIcon name="power" size="14" />
              <span>Đăng xuất</span>
            </button>
          </div>
        </transition>
      </div>
    </div>
  </aside>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { adminLogout, logout } from '../../stores/auth.js';

export default {
  name: 'DoubleSidebar',
  components: { AppIcon },
  props: {
    sections: {
      type: Array,
      required: true,
    },
    activeRouteName: {
      type: String,
      default: '',
    },
    user: {
      type: Object,
      required: true,
    },
    roleLabel: {
      type: String,
      default: '',
    },
    showViewUserBtn: {
      type: Boolean,
      default: false,
    },
    viewUserRoute: {
      type: String,
      default: '/',
    },
    viewUserLabel: {
      type: String,
      default: 'Xem trang người dùng',
    },
  },
  emits: ['navigate'],
  data() {
    return {
      activeSectionIndex: 0,
      searchQuery: '',
      showUserDropdown: false,
      customLogo: '',
    };
  },
  mounted() {
    this.loadCustomLogo();
    window.addEventListener('sportgo-settings-updated', this.loadCustomLogo);
  },
  beforeUnmount() {
    window.removeEventListener('sportgo-settings-updated', this.loadCustomLogo);
  },
  directives: {
    'click-outside': {
      beforeMount(el, binding) {
        el.clickOutsideEvent = (event) => {
          if (!(el === event.target || el.contains(event.target))) {
            binding.value(event);
          }
        };
        document.body.addEventListener('click', el.clickOutsideEvent);
      },
      unmounted(el) {
        document.body.removeEventListener('click', el.clickOutsideEvent);
      },
    },
  },
  computed: {
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'User';
    },
    userInitial() {
      return this.userName.charAt(0).toUpperCase();
    },
    settingsRoute() {
      const role = this.user.role || this.user.role_group;
      return role === 'admin'
        ? '/admin/settings'
        : '/owner/booking-settings';
    },
    profileRoute() {
      const role = this.user.role || this.user.role_group;
      return role === 'admin'
        ? '/admin/profile'
        : '/owner/profile';
    },
    currentSectionTitle() {
      return this.sections[this.activeSectionIndex]?.label || 'Menu';
    },
    activeSectionItems() {
      return this.sections[this.activeSectionIndex]?.items || [];
    },
    searchedSections() {
      if (!this.searchQuery) return [];
      const query = this.searchQuery.toLowerCase();
      return this.sections
        .map((sec) => {
          const matchedItems = sec.items.filter((item) =>
            item.label.toLowerCase().includes(query)
          );
          return { ...sec, items: matchedItems };
        })
        .filter((sec) => sec.items.length > 0);
    },
  },
  watch: {
    $route: {
      immediate: true,
      handler() {
        const matchingIndex = this.sections.findIndex((sec) =>
          sec.items.some((item) => this.isActive(item))
        );
        if (matchingIndex !== -1) {
          this.activeSectionIndex = matchingIndex;
        }
      },
    },
  },
  methods: {
    getSectionIcon(section) {
      if (section.icon) return section.icon;
      
      const sectionIconsMap = {
        'Tổng quan': 'dashboard',
        'Vận hành sân': 'building',
        'Người dùng & quyền': 'users',
        'Kinh doanh': 'layers',
        'Nhân sự': 'users',
        'Tài chính': 'banknote',
        'Nội dung & cấu hình': 'settings',
        'Kiểm duyệt & hỗ trợ': 'shieldCheck',
      };

      const mapped = sectionIconsMap[section.label];
      if (mapped) return mapped;

      if (section.items && section.items[0]) {
        return section.items[0].icon;
      }
      return 'layers';
    },
    isActive(item) {
      const nameMatch = item.activeNames?.includes(this.activeRouteName);
      if (!nameMatch) return false;

      // Handle Admin moderation tab matching specifically
      if (this.activeRouteName === 'admin-moderation') {
        const currentTab = this.$route.query.tab || 'moderation';
        if (item.to.includes('tab=moderation')) {
          return currentTab === 'moderation';
        }
        if (item.to.includes('tab=reports')) {
          return currentTab === 'reports';
        }
        if (item.to.includes('tab=complaints')) {
          return currentTab === 'complaints';
        }
        if (!item.to.includes('tab=')) {
          return currentTab === 'moderation';
        }
      }
      return true;
    },
    toggleUserDropdown(event) {
      event.stopPropagation();
      this.showUserDropdown = !this.showUserDropdown;
    },
    closeUserDropdown() {
      this.showUserDropdown = false;
    },
    handleBrandClick() {
      const role = this.user.role || this.user.role_group;
      const dashboardPath = role === 'admin'
        ? '/admin/dashboard'
        : '/owner/dashboard';
      this.$router.push(dashboardPath);
      this.$emit('navigate');
    },
    loadCustomLogo() {
      const stored = localStorage.getItem('sportgo_general_settings');
      if (stored) {
        try {
          const settings = JSON.parse(stored);
          this.customLogo = settings.app_logo || '';
        } catch (e) {
          console.error(e);
        }
      }
    },
    async handleLogout() {
      this.closeUserDropdown();
      const role = this.user.role || this.user.role_group;
      const isAdmin = role === 'admin';
      if (isAdmin) {
        await adminLogout();
        this.$router.push('/admin/login');
      } else {
        await logout();
        this.$router.push('/login');
      }
    },
  },
};
</script>

<style scoped>
.double-sidebar {
  display: flex;
  flex-direction: row !important;
  height: 100vh;
  min-height: 100vh;
  position: sticky;
  top: 0;
  z-index: 70;
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif;
  box-sizing: border-box;
}

.double-sidebar *, .double-sidebar *::before, .double-sidebar *::after {
  box-sizing: border-box;
}

/* Left Column Styling */
.sidebar-left {
  width: 72px;
  background-color: #000000;
  border-right: 1px solid #1f1f22;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20px 0;
  flex-shrink: 0;
}

.logo-container {
  color: #ffffff;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 28px;
  cursor: pointer;
  transition: transform 0.2s ease;
}

.logo-container:hover {
  transform: scale(1.05);
}

.brand-logo-svg {
  width: 26px;
  height: 26px;
}

.left-nav {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  align-items: center;
}

.left-nav-item-wrapper {
  position: relative;
  display: flex;
  justify-content: center;
  width: 100%;
}

.left-nav-item {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: transparent;
  border: none;
  color: #a1a1aa;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  text-decoration: none;
  padding: 0;
}

.left-nav-item:hover {
  background-color: #27272a;
  color: #ffffff;
}

.left-nav-item.active {
  background-color: #ffffff;
  color: #000000;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
}

.left-bottom {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  align-items: center;
}

.user-avatar-btn {
  border: none;
  background: transparent;
}

.user-avatar-mini {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #ffffff;
  color: #000000;
  font-weight: 700;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Sidebar Tooltip */
.sidebar-tooltip {
  position: absolute;
  left: 80px;
  top: 50%;
  transform: translateY(-50%) translateX(6px);
  background-color: #18181b;
  color: #ffffff;
  font-size: 12px;
  font-weight: 500;
  padding: 6px 10px;
  border-radius: 6px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
  border: 1px solid #27272a;
  z-index: 100;
}

.left-nav-item-wrapper:hover .sidebar-tooltip {
  opacity: 1;
  transform: translateY(-50%) translateX(0);
}

/* Right Column Styling */
.sidebar-right {
  width: 240px;
  background-color: #0c0c0e;
  border-right: 1px solid #1f1f22;
  display: flex;
  flex-direction: column;
  padding: 20px 0;
  flex-shrink: 0;
  position: relative;
}

.right-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 18px;
  margin-bottom: 22px;
  color: #ffffff;
}

.brand-logo-svg-small {
  width: 18px;
  height: 18px;
}

.brand-title {
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.active-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 18px;
  margin-bottom: 12px;
}

.active-section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: -0.3px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chevron-decor {
  color: #71717a;
}

.search-box-container {
  padding: 0 16px;
  margin-bottom: 16px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
  background-color: #141416;
  border: 1px solid #27272a;
  border-radius: 8px;
  padding: 0 10px;
  height: 36px;
  transition: border-color 0.2s ease;
}

.search-box:focus-within {
  border-color: #a1a1aa;
}

.search-icon {
  color: #a1a1aa;
  margin-right: 8px;
}

.search-input {
  background: transparent;
  border: none;
  color: #ffffff;
  font-size: 13px;
  width: 100%;
  outline: none;
  padding: 4px 0;
}

.search-input::placeholder {
  color: #71717a;
}

.search-clear {
  background: transparent;
  border: none;
  color: #a1a1aa;
  padding: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.search-clear:hover {
  color: #ffffff;
}

.extra-content {
  padding: 0 16px;
  margin-bottom: 12px;
}

.right-nav {
  flex: 1;
  overflow-y: auto;
  padding: 0 12px;
}

.right-nav::-webkit-scrollbar {
  width: 4px;
}

.right-nav::-webkit-scrollbar-thumb {
  background-color: #27272a;
  border-radius: 99px;
}

.nav-group-section {
  margin-bottom: 18px;
}

.nav-group-title {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: #a1a1aa;
  margin: 0 0 8px 8px;
  letter-spacing: 0.5px;
}

.nav-items-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dsb-nav-item {
  display: flex;
  align-items: center;
  padding: 9px 12px;
  border-radius: 8px;
  color: #e4e4e7;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.15s ease;
  cursor: pointer;
}

.dsb-nav-item:hover {
  background-color: #1f1f22;
  color: #ffffff;
}

.dsb-nav-item:hover .item-icon {
  color: #ffffff;
}

.dsb-nav-item.dsb-nav-active {
  background-color: #1f1f22;
  color: #ffffff;
  font-weight: 600;
}

.dsb-nav-item.dsb-nav-active .item-icon {
  color: #ffffff;
}

.item-icon {
  color: #a1a1aa;
  margin-right: 10px;
  transition: color 0.15s ease;
}

.item-label {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chevron-down-decor {
  color: #71717a;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.dsb-nav-item:hover .chevron-down-decor,
.dsb-nav-item.dsb-nav-active .chevron-down-decor {
  opacity: 1;
}

.no-results {
  font-size: 12px;
  color: #52525b;
  text-align: center;
  padding: 20px 0;
}

/* User block details */
.sidebar-user-block {
  margin-top: auto;
  border-top: 1px solid #1f1f22;
  padding: 16px 12px 0 12px;
  position: relative;
}

.user-block-trigger {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px;
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.user-block-trigger:hover {
  background-color: #141416;
}

.user-avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #ffffff;
  color: #000000;
  font-weight: 700;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-text-details {
  flex: 1;
  min-width: 0;
}

.user-display-name {
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-display-role {
  color: #a1a1aa;
  font-size: 11px;
  font-weight: 500;
  margin-top: 2px;
}

.more-menu-btn {
  background: transparent;
  border: none;
  color: #a1a1aa;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: color 0.2s, background-color 0.2s;
}

.more-menu-btn:hover {
  color: #ffffff;
  background-color: #27272a;
}

/* User Dropdown Dialog */
.user-dropdown-menu {
  position: absolute;
  bottom: calc(100% + 8px);
  left: 12px;
  right: 12px;
  background-color: #141416;
  border: 1px solid #1f1f22;
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
  padding: 4px;
  z-index: 120;
}

.dropdown-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  background: transparent;
  border: none;
  color: #a1a1aa;
  font-size: 13px;
  font-weight: 500;
  text-align: left;
  border-radius: 6px;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.15s ease;
}

.dropdown-menu-item:hover {
  background-color: #1f1f22;
  color: #ffffff;
}

.dropdown-menu-item.logout-btn {
  border-top: 1px solid #1f1f22;
  border-radius: 0 0 6px 6px;
  margin-top: 4px;
}

.dropdown-menu-item.logout-btn:hover {
  background-color: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

/* Dropdown Animation */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

.custom-sidebar-logo {
  width: 32px;
  height: 32px;
  object-fit: contain;
  border-radius: 4px;
  background: #ffffff;
}
</style>

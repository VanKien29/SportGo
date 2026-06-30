<template>
  <aside class="sidebar" :class="sidebarStyle" aria-label="Admin navigation">
    <!-- One-Level Sidebar -->
    <template v-if="sidebarStyle === 'one-level'">
      <!-- Workspace Switcher style brand -->
      <div class="workspace-switcher-wrapper">
        <div class="workspace-switcher" @click.stop="toggleWsDropdown">
          <div class="ws-avatar">S</div>
          <div v-if="!collapsed" class="ws-info">
            <span class="ws-name">Hệ thống SportGo</span>
            <span class="ws-plan">Gói Quản trị</span>
          </div>
          <AppIcon v-if="!collapsed" name="chevronDown" size="14" class="ws-chevron" />
        </div>

        <!-- Dropdown matching mockup -->
        <transition name="fade-slide">
          <div v-if="wsDropdownOpen && !collapsed" class="ws-dropdown">
            <div class="ws-dropdown-item active">
              Hệ thống SportGo
            </div>
            <div class="ws-dropdown-item" @click="selectWorkspace('Không gian cá nhân')">
              Không gian cá nhân
            </div>
            <div class="ws-dropdown-item" @click="selectWorkspace('Môi trường thử nghiệm')">
              Môi trường thử nghiệm
            </div>
            <div class="ws-dropdown-divider"></div>
            <div class="ws-dropdown-item create-ws" @click="selectWorkspace('Workspace mới')">
              <span>+</span> Tạo Workspace mới
            </div>
          </div>
        </transition>
      </div>

      <!-- Navigation -->
      <nav class="sidebar-nav">
        <section v-for="section in sections" :key="section.label" class="admin-nav-section">
          <p v-if="!collapsed" class="nav-group">{{ section.label }}</p>
          <div v-else class="nav-group-dot"></div>
          <AdminNavItem
            v-for="item in section.items"
            :key="item.to"
            :item="item"
            :active-route-name="activeRouteName"
            @navigate="$emit('navigate')"
          />
        </section>
      </nav>

      <!-- Bottom Actions matching mockup -->
      <div class="sidebar-bottom">
        <div class="sidebar-divider"></div>
        
        <RouterLink
          class="nav-item"
          :class="{ 'nav-active': activeRouteName === 'admin-settings' }"
          to="/admin/settings"
          @click="$emit('navigate')"
        >
          <span class="nav-item-left">
            <AppIcon name="settings" size="17" />
            <span v-if="!collapsed" class="nav-item-label">Cài đặt</span>
          </span>
        </RouterLink>

        <button class="nav-item logout-btn" type="button" @click="handleLogout">
          <span class="nav-item-left">
            <AppIcon name="logOut" size="17" />
            <span v-if="!collapsed" class="nav-item-label">Đăng xuất</span>
          </span>
        </button>
      </div>
    </template>

    <!-- Two-Level Sidebar -->
    <template v-else>
      <div class="sidebar-two-level-container">
        <!-- Left Rail -->
        <div class="icon-nav-rail">
          <div class="rail-logo" title="SportGo Admin">SG</div>
          
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

          <div class="rail-bottom">
            <RouterLink
              class="rail-icon-btn"
              :class="{ active: activeRouteName === 'admin-settings' }"
              to="/admin/settings"
              title="Cài đặt giao diện"
            >
              <AppIcon name="settings" size="18" />
            </RouterLink>
            <button type="button" class="rail-icon-btn" title="Đăng xuất" @click="handleLogout">
              <AppIcon name="logOut" size="18" />
            </button>
          </div>
        </div>

        <!-- Right Detail Sidebar -->
        <div v-if="!collapsed" class="detail-sidebar">
          <!-- Workspace Switcher style brand in two-level -->
          <div class="workspace-switcher-wrapper two-level-header">
            <div class="workspace-switcher" @click.stop="toggleWsDropdown">
              <div class="ws-avatar">S</div>
              <div class="ws-info">
                <span class="ws-name">Hệ thống SportGo</span>
                <span class="ws-plan">Gói Quản trị</span>
              </div>
              <AppIcon name="chevronDown" size="14" class="ws-chevron" />
            </div>

            <!-- Dropdown matching mockup -->
            <transition name="fade-slide">
              <div v-if="wsDropdownOpen" class="ws-dropdown">
                <div class="ws-dropdown-item active">
                  Hệ thống SportGo
                </div>
                <div class="ws-dropdown-item" @click="selectWorkspace('Không gian cá nhân')">
                  Không gian cá nhân
                </div>
                <div class="ws-dropdown-item" @click="selectWorkspace('Môi trường thử nghiệm')">
                  Môi trường thử nghiệm
                </div>
                <div class="ws-dropdown-divider"></div>
                <div class="ws-dropdown-item create-ws" @click="selectWorkspace('Workspace mới')">
                  <span>+</span> Tạo Workspace mới
                </div>
              </div>
            </transition>
          </div>

          <div class="detail-sidebar-header-title">
            <span>{{ sections[currentSectionIndex].label }}</span>
          </div>
          
          <div class="detail-sidebar-nav">
            <AdminNavItem
              v-for="item in sections[currentSectionIndex].items"
              :key="item.to"
              :item="item"
              :active-route-name="activeRouteName"
              @navigate="$emit('navigate')"
            />
          </div>
        </div>
      </div>
    </template>
  </aside>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import AdminNavItem from './AdminNavItem.vue';
import { getAuth, adminLogout } from '../../stores/auth.js';

export default {
  name: 'AdminSidebar',
  components: { AppIcon, AdminNavItem },
  props: {
    sections: { type: Array, required: true },
    activeRouteName: { type: String, default: '' },
    collapsed: { type: Boolean, default: false },
  },
  emits: ['navigate'],
  data() {
    return {
      wsDropdownOpen: false,
      sidebarStyle: localStorage.getItem('admin-sidebar-style') || 'one-level',
      localActiveSectionIndex: null,
    };
  },
  computed: {
    user() {
      return getAuth() || {};
    },
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'Admin';
    },
    userInitial() {
      return this.userName.charAt(0).toUpperCase();
    },
    roleLabel() {
      const role = this.user.role || this.user.role_group;
      const labels = {
        admin: 'Quản trị viên',
        super_admin: 'Super admin',
        system_staff: 'Nhân viên hệ thống',
      };
      return labels[role] || 'Admin';
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
  created() {
    window.addEventListener('sidebar-style-changed', this.loadSidebarStyle);
  },
  beforeUnmount() {
    window.removeEventListener('sidebar-style-changed', this.loadSidebarStyle);
    document.removeEventListener('click', this.closeWsDropdown);
  },
  methods: {
    loadSidebarStyle() {
      this.sidebarStyle = localStorage.getItem('admin-sidebar-style') || 'one-level';
      this.localActiveSectionIndex = null;
    },
    toggleWsDropdown() {
      this.wsDropdownOpen = !this.wsDropdownOpen;
      if (this.wsDropdownOpen) {
        document.addEventListener('click', this.closeWsDropdown);
      }
    },
    closeWsDropdown(e) {
      this.wsDropdownOpen = false;
      document.removeEventListener('click', this.closeWsDropdown);
    },
    selectWorkspace(action) {
      this.wsDropdownOpen = false;
      if (action === 'Không gian cá nhân') {
        this.$router.push('/admin/profile');
      } else if (action === 'Môi trường thử nghiệm') {
        this.$router.push('/');
      } else if (action === 'Workspace mới') {
        alert('Tính năng tạo Workspace mới chỉ khả dụng trên phiên bản SportGo Enterprise. Vui lòng liên hệ quản trị viên để nâng cấp hệ thống.');
      }
    },
    async handleLogout() {
      if (confirm('Bạn có chắc chắn muốn đăng xuất khỏi trang quản trị?')) {
        await adminLogout();
        this.$router.push('/admin/login');
      }
    },
    getSectionIcon(label) {
      const iconMap = {
        'Tổng quan': 'dashboard',
        'Vận hành sân': 'building',
        'Người dùng & quyền': 'users',
        'Tài chính': 'creditCard',
        'Nội dung & cấu hình': 'settings',
        'Kiểm duyệt & hỗ trợ': 'eye'
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
  }
};
</script>

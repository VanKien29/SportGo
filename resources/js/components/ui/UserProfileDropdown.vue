<template>
  <div class="user-profile-dropdown" v-click-outside="closeMenu">
    <!-- Trigger: Avatar tròn chứa ảnh hoặc icon user bình thường -->
    <button class="avatar-trigger" type="button" @click="toggleMenu" aria-haspopup="true" :aria-expanded="isOpen" aria-label="Menu người dùng">
      <span class="avatar-circle">
        <img v-if="avatarUrl" :src="avatarUrl" alt="Avatar" class="avatar-img" />
        <span v-else class="avatar-placeholder">
          <!-- Icon user bình thường -->
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="avatar-user-icon">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
        </span>
      </span>
    </button>

    <!-- Dropdown Menu -->
    <transition name="dropdown-fade">
      <div v-if="isOpen" class="dropdown-menu">
        <!-- Item: Profile -->
        <RouterLink class="menu-item" :to="profileUrl" @click="isOpen = false">
          <span class="menu-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <span class="menu-label">Hồ sơ</span>
        </RouterLink>

        <!-- Item: Billing -->
        <RouterLink v-if="showBilling" class="menu-item" :to="billingUrl" @click="isOpen = false">
          <span class="menu-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
          </span>
          <span class="menu-label">Thanh toán</span>
        </RouterLink>

        <!-- Item: Settings -->
        <RouterLink v-if="showSettings" class="menu-item" :to="settingsUrl" @click="isOpen = false">
          <span class="menu-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.52a2 2 0 0 1-1 1.72l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.72v-.52a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z"/><circle cx="12" cy="12" r="3"/></svg>
          </span>
          <span class="menu-label">Cài đặt</span>
        </RouterLink>

        <!-- Dòng kẻ phân cách -->
        <div v-if="showBilling || showSettings" class="menu-divider"></div>

        <!-- Item: Log out -->
        <button class="menu-item logout" type="button" @click="triggerLogout">
          <span class="menu-icon text-red">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
          </span>
          <span class="menu-label text-red">Đăng xuất</span>
        </button>
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'UserProfileDropdown',
  props: {
    user: { type: Object, default: () => ({}) },
    profileUrl: { type: String, default: '/profile' },
    billingUrl: { type: String, default: '/billing' },
    settingsUrl: { type: String, default: '/settings' },
    showBilling: { type: Boolean, default: true },
    showSettings: { type: Boolean, default: true },
  },
  emits: ['logout'],
  data() {
    return {
      isOpen: false,
    };
  },
  computed: {
    avatarUrl() {
      if (!this.user) return null;
      return this.user.avatarUrl || this.user.avatar_url || this.user.avatar || null;
    }
  },
  methods: {
    toggleMenu() {
      this.isOpen = !this.isOpen;
    },
    closeMenu() {
      this.isOpen = false;
    },
    triggerLogout() {
      this.isOpen = false;
      this.$emit('logout');
    },
  },
  directives: {
    'click-outside': {
      beforeMount(el, binding) {
        el.clickOutsideEvent = function(event) {
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
};
</script>

<style scoped>
.user-profile-dropdown {
  position: relative;
  display: inline-flex;
  align-items: center;
  height: 38px;
}
.avatar-trigger {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  outline: none;
  display: block;
}
.avatar-circle {
  width: 38px;
  height: 38px;
  border-radius: var(--admin-radius, 8px);
  border: 1px solid var(--admin-border);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--admin-surface);
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: var(--admin-shadow-sm);
}
.avatar-circle.never-hover-class-placeholder {
  border-color: var(--admin-primary);
  background: var(--admin-primary-soft);
  transform: translateY(-1px);
  box-shadow: var(--admin-shadow-card);
}
.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.avatar-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--admin-faint);
  transition: background 0.2s, color 0.2s;
}
.avatar-circle.never-hover-class-placeholder .avatar-placeholder {
  background: transparent;
  color: var(--admin-primary-dark);
}
.avatar-user-icon {
  width: 18px;
  height: 18px;
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  width: 190px;
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #f1f5f9);
  border-radius: var(--admin-radius-lg, 12px);
  box-shadow: var(--admin-shadow-card, 0 10px 30px -5px rgba(0, 0, 0, 0.08));
  padding: 6px;
  z-index: 999;
  transform-origin: top right;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 8px 12px;
  border-radius: var(--admin-radius, 8px);
  border: none;
  background: none;
  text-align: left;
  cursor: pointer;
  text-decoration: none;
  font-family: inherit;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--admin-muted, #475569);
  transition: background 0.15s, color 0.15s;
  box-sizing: border-box;
}
.menu-item.never-hover-class-placeholder {
  background: var(--admin-hover, #f8fafc);
  color: var(--admin-text, #0f172a);
}
.menu-icon {
  display: flex;
  align-items: center;
  color: var(--admin-faint, #64748b);
  width: 16px;
  height: 16px;
  transition: color 0.15s;
}
.menu-icon svg {
  width: 100%;
  height: 100%;
}
.menu-item.never-hover-class-placeholder .menu-icon {
  color: var(--admin-text, #334155);
}
.menu-label {
  flex-grow: 1;
}

.menu-divider {
  height: 1px;
  background: var(--admin-border-soft, #f1f5f9);
  margin: 4px 0;
}

.menu-item.logout {
  color: var(--admin-danger, #dc2626);
}
.menu-item.logout.never-hover-class-placeholder {
  background: var(--admin-danger-soft, #fef2f2);
  color: var(--admin-danger-text, #991b1b);
}
.menu-icon.text-red {
  color: var(--admin-danger, #dc2626);
}
.menu-item.logout.never-hover-class-placeholder .menu-icon.text-red {
  color: var(--admin-danger-text, #991b1b);
}

.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1), transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: scale(0.96) translateY(-8px);
}
</style>

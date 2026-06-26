<template>
  <div class="user-profile-dropdown" v-click-outside="closeMenu">
    <!-- Trigger: Avatar tròn chứa ảnh hoặc icon user bình thường -->
    <button class="avatar-trigger" type="button" @click="toggleMenu" aria-haspopup="true" :aria-expanded="isOpen">
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
          <span class="menu-label">Profile</span>
        </RouterLink>

        <!-- Item: Billing -->
        <RouterLink class="menu-item" :to="billingUrl" @click="isOpen = false">
          <span class="menu-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
          </span>
          <span class="menu-label">Billing</span>
        </RouterLink>

        <!-- Item: Settings -->
        <RouterLink class="menu-item" :to="settingsUrl" @click="isOpen = false">
          <span class="menu-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.52a2 2 0 0 1-1 1.72l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.72v-.52a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z"/><circle cx="12" cy="12" r="3"/></svg>
          </span>
          <span class="menu-label">Settings</span>
        </RouterLink>

        <!-- Dòng kẻ phân cách -->
        <div class="menu-divider"></div>

        <!-- Item: Log out -->
        <button class="menu-item logout" type="button" @click="triggerLogout">
          <span class="menu-icon text-red">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
          </span>
          <span class="menu-label text-red">Log out</span>
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
  display: inline-block;
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
  border-radius: 50%;
  border: 1.5px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: #ffffff;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.avatar-circle:hover {
  border-color: #cbd5e1;
  transform: translateY(-1px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
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
  background: #f8fafc;
  color: #64748b;
  transition: background 0.2s, color 0.2s;
}
.avatar-circle:hover .avatar-placeholder {
  background: #f1f5f9;
  color: #334155;
}
.avatar-user-icon {
  width: 18px;
  height: 18px;
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  width: 220px;
  background: #ffffff;
  border: 1px solid #f1f5f9;
  border-radius: 12px;
  box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.08), 0 8px 16px -6px rgba(0, 0, 0, 0.03);
  padding: 8px;
  z-index: 999;
  transform-origin: top right;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 14px;
  border-radius: 8px;
  border: none;
  background: none;
  text-align: left;
  cursor: pointer;
  text-decoration: none;
  font-family: inherit;
  font-size: 14.5px;
  font-weight: 500;
  color: #475569;
  transition: background 0.15s, color 0.15s;
  box-sizing: border-box;
}
.menu-item:hover {
  background: #f8fafc;
  color: #0f172a;
}
.menu-icon {
  display: flex;
  align-items: center;
  color: #64748b;
  width: 18px;
  height: 18px;
  transition: color 0.15s;
}
.menu-icon svg {
  width: 100%;
  height: 100%;
}
.menu-item:hover .menu-icon {
  color: #334155;
}
.menu-label {
  flex-grow: 1;
}

.menu-divider {
  height: 1px;
  background: #f1f5f9;
  margin: 6px 0;
}

.menu-item.logout {
  color: #dc2626;
}
.menu-item.logout:hover {
  background: #fef2f2;
  color: #991b1b;
}
.menu-icon.text-red {
  color: #dc2626;
}
.menu-item.logout:hover .menu-icon.text-red {
  color: #991b1b;
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

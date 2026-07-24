<template>
  <header class="home-nav">
    <div class="home-nav__inner">
      <router-link to="/" class="home-nav__brand" aria-label="Về trang chủ">
        <span class="home-nav__brand-mark">
          <img v-if="brandLogo" :src="brandLogo" :alt="brandName" />
          <Trophy v-else :size="24" aria-hidden="true" />
        </span>
        <span class="home-nav__brand-name">
          {{ brandMain }}<b v-if="brandAccent">{{ brandAccent }}</b>
        </span>
      </router-link>

      <nav class="home-nav__links" aria-label="Điều hướng chính">
        <router-link to="/" exact-active-class="is-active">Trang chủ</router-link>
        <router-link :to="{ name: 'venues' }" active-class="is-active">Tìm sân</router-link>
        <a href="#sports">Môn thể thao</a>
        <router-link to="/news" active-class="is-active">Tin tức</router-link>
        <router-link to="/community" active-class="is-active">Cộng đồng</router-link>
        <a href="#support">Hỗ trợ</a>
      </nav>

      <div class="home-nav__actions">
        <router-link to="/become-partner" class="home-nav__partner">
          <Building2 :size="18" aria-hidden="true" />
          <span>Đăng sân</span>
        </router-link>

        <template v-if="!user">
          <router-link to="/login" class="home-nav__login">
            <LogIn :size="18" aria-hidden="true" />
            Đăng nhập
          </router-link>
          <router-link to="/register" class="home-nav__register">Đăng ký</router-link>
        </template>

        <template v-else>
          <router-link
            v-if="user.role === 'user'"
            to="/bookings"
            class="home-nav__icon-button"
            aria-label="Xem lịch đặt sân"
          >
            <CalendarDays :size="20" aria-hidden="true" />
          </router-link>
          <div class="home-nav__account">
            <button
              type="button"
              class="home-nav__avatar"
              :aria-expanded="accountOpen"
              aria-label="Mở menu tài khoản"
              @click.stop="toggleAccount"
            >
              {{ userInitial }}
            </button>
            <transition name="home-nav-pop">
              <div v-if="accountOpen" class="home-nav__account-menu">
                <div class="home-nav__account-head">
                  <strong>{{ user.fullName }}</strong>
                  <span>{{ roleLabel }}</span>
                </div>
                <router-link :to="profileRoute" @click="closeMenus">
                  <UserRound :size="17" aria-hidden="true" />
                  Tài khoản
                </router-link>
                <router-link v-if="user.role === 'user'" to="/bookings" @click="closeMenus">
                  <CalendarDays :size="17" aria-hidden="true" />
                  Lịch đặt sân
                </router-link>
                <button v-if="user.role !== 'user'" type="button" @click="goToManage">
                  <LayoutDashboard :size="17" aria-hidden="true" />
                  Trang quản lý
                </button>
                <button type="button" class="is-danger" @click="handleLogout">
                  <LogOut :size="17" aria-hidden="true" />
                  Đăng xuất
                </button>
              </div>
            </transition>
          </div>
        </template>

        <button
          type="button"
          class="home-nav__menu-button"
          :aria-expanded="mobileOpen"
          aria-controls="home-mobile-menu"
          :aria-label="mobileOpen ? 'Đóng menu' : 'Mở menu'"
          @click.stop="toggleMobile"
        >
          <X v-if="mobileOpen" :size="22" aria-hidden="true" />
          <Menu v-else :size="22" aria-hidden="true" />
        </button>
      </div>
    </div>

    <transition name="home-nav-mobile">
      <nav v-if="mobileOpen" id="home-mobile-menu" class="home-nav__mobile" aria-label="Điều hướng di động">
        <router-link to="/" exact-active-class="is-active" @click="closeMenus">Trang chủ</router-link>
        <router-link :to="{ name: 'venues' }" active-class="is-active" @click="closeMenus">Tìm sân</router-link>
        <a href="#sports" @click="closeMenus">Môn thể thao</a>
        <router-link to="/news" active-class="is-active" @click="closeMenus">Tin tức</router-link>
        <router-link to="/community" active-class="is-active" @click="closeMenus">Cộng đồng</router-link>
        <router-link to="/become-partner" active-class="is-active" @click="closeMenus">Dành cho chủ sân</router-link>
        <a href="#support" @click="closeMenus">Hỗ trợ</a>
        <router-link v-if="user?.role === 'user'" to="/bookings" @click="closeMenus">Lịch đặt sân</router-link>
      </nav>
    </transition>
  </header>
</template>

<script>
import {
  Building2,
  CalendarDays,
  LayoutDashboard,
  LogIn,
  LogOut,
  Menu,
  Trophy,
  UserRound,
  X,
} from "lucide-vue-next";
import { getAuth, logout } from "../../stores/auth.js";
import { resolveSystemAsset, systemName, systemProfileState } from "../../stores/systemProfile.js";

export default {
  name: "HomeHeader",
  components: {
    Building2,
    CalendarDays,
    LayoutDashboard,
    LogIn,
    LogOut,
    Menu,
    Trophy,
    UserRound,
    X,
  },
  data() {
    return {
      user: getAuth(),
      accountOpen: false,
      mobileOpen: false,
    };
  },
  computed: {
    brandName() {
      return systemName() || "SportGo";
    },
    brandLogo() {
      return resolveSystemAsset(systemProfileState.profile.logo_url);
    },
    brandMain() {
      const match = this.brandName.match(/^(.*?)(go)$/i);
      return match ? match[1] : this.brandName;
    },
    brandAccent() {
      const match = this.brandName.match(/^(.*?)(go)$/i);
      return match ? match[2] : "";
    },
    userInitial() {
      return this.user?.fullName?.trim()?.charAt(0)?.toUpperCase() || "?";
    },
    roleLabel() {
      return {
        admin: "Quản trị viên",
        owner: "Chủ sân",
        user: "Người chơi",
      }[this.user?.role] || "";
    },
    profileRoute() {
      return this.user?.role === "owner" ? "/owner/profile" : "/profile";
    },
  },
  watch: {
    "$route.fullPath"() {
      this.closeMenus();
    },
  },
  mounted() {
    document.addEventListener("pointerdown", this.handleOutside);
    document.addEventListener("keydown", this.handleKeydown);
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.handleOutside);
    document.removeEventListener("keydown", this.handleKeydown);
  },
  methods: {
    toggleAccount() {
      this.accountOpen = !this.accountOpen;
      this.mobileOpen = false;
    },
    toggleMobile() {
      this.mobileOpen = !this.mobileOpen;
      this.accountOpen = false;
    },
    closeMenus() {
      this.accountOpen = false;
      this.mobileOpen = false;
    },
    handleOutside(event) {
      if (!event.target.closest?.(".home-nav__account")) this.accountOpen = false;
      if (!event.target.closest?.(".home-nav__menu-button, .home-nav__mobile")) this.mobileOpen = false;
    },
    handleKeydown(event) {
      if (event.key === "Escape") this.closeMenus();
    },
    goToManage() {
      this.closeMenus();
      this.$router.push(this.user?.role === "admin" ? "/admin/dashboard" : "/owner/dashboard");
    },
    async handleLogout() {
      await logout();
      this.user = null;
      this.closeMenus();
      this.$router.push("/login");
    },
  },
};
</script>

<style scoped>
.home-nav {
  position: fixed;
  z-index: 120;
  top: 0;
  right: 0;
  left: 0;
  height: 72px;
  border-bottom: 1px solid #e3ebe6;
  background: rgb(255 255 255 / 96%);
  box-shadow: 0 5px 22px rgb(19 42 29 / 5%);
  backdrop-filter: blur(14px);
}

.home-nav__inner {
  display: flex;
  width: min(calc(100% - 56px), 1320px);
  height: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 28px;
  margin-inline: auto;
}

.home-nav__brand,
.home-nav__links,
.home-nav__actions,
.home-nav__partner,
.home-nav__login,
.home-nav__register,
.home-nav__account-menu a,
.home-nav__account-menu button {
  display: flex;
  align-items: center;
}

.home-nav__brand {
  flex: 0 0 auto;
  gap: 10px;
  color: #13241a;
  text-decoration: none;
}

.home-nav__brand-mark {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  overflow: hidden;
  border: 1px solid #bfe1ca;
  border-radius: 50%;
  background: #eaf8ef;
  color: #148047;
}

.home-nav__brand-mark img {
  width: 100%;
  height: 100%;
  padding: 4px;
  object-fit: contain;
}

.home-nav__brand-name {
  font-family: var(--sg-client-font-display-family);
  font-size: 24px;
  font-weight: 900;
  letter-spacing: 0;
}

.home-nav__brand-name b {
  color: #15944f;
}

.home-nav__links {
  height: 100%;
  gap: 25px;
}

.home-nav__links a {
  position: relative;
  display: flex;
  height: 100%;
  align-items: center;
  color: #27352d;
  font-size: 14px;
  font-weight: 850;
  text-decoration: none;
  white-space: nowrap;
}

.home-nav__links a::after {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 3px;
  background: transparent;
  content: "";
}

.home-nav__links a:hover,
.home-nav__links a.is-active {
  color: #0e7a3d;
}

.home-nav__links a.is-active::after {
  background: #19a253;
}

.home-nav__actions {
  flex: 0 0 auto;
  gap: 9px;
}

.home-nav__partner {
  gap: 7px;
  margin-right: 3px;
  color: #14743e;
  font-size: 13px;
  font-weight: 900;
  text-decoration: none;
}

.home-nav__login,
.home-nav__register {
  min-height: 40px;
  justify-content: center;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 900;
  text-decoration: none;
}

.home-nav__login {
  gap: 7px;
  padding-inline: 14px;
  border: 1px solid #d2ddd6;
  color: #1f2d24;
}

.home-nav__register {
  padding-inline: 17px;
  background: #168b48;
  color: #fff;
}

.home-nav__icon-button,
.home-nav__menu-button,
.home-nav__avatar {
  display: grid;
  place-items: center;
  padding: 0;
  cursor: pointer;
}

.home-nav__icon-button,
.home-nav__menu-button {
  width: 40px;
  height: 40px;
  border: 1px solid #d5e0d9;
  border-radius: 6px;
  background: #fff;
  color: #264034;
  text-decoration: none;
}

.home-nav__menu-button {
  display: none;
}

.home-nav__account {
  position: relative;
}

.home-nav__avatar {
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 50%;
  background: #168b48;
  color: #fff;
  font-size: 14px;
  font-weight: 950;
}

.home-nav__account-menu {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  width: 228px;
  overflow: hidden;
  padding: 8px;
  border: 1px solid #dce6e0;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 22px 52px rgb(15 35 23 / 16%);
}

.home-nav__account-head {
  display: grid;
  gap: 2px;
  margin-bottom: 6px;
  padding: 10px 11px 12px;
  border-bottom: 1px solid #e5ece7;
}

.home-nav__account-head strong {
  color: #17271d;
  font-size: 14px;
}

.home-nav__account-head span {
  color: #728078;
  font-size: 12px;
}

.home-nav__account-menu a,
.home-nav__account-menu button {
  width: 100%;
  min-height: 40px;
  gap: 9px;
  padding: 0 11px;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: #304138;
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
  cursor: pointer;
}

.home-nav__account-menu a:hover,
.home-nav__account-menu button:hover {
  background: #eff7f1;
  color: #0d743a;
}

.home-nav__account-menu .is-danger {
  color: #c73b3b;
}

.home-nav__mobile {
  position: absolute;
  top: calc(100% + 8px);
  right: 20px;
  left: 20px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px;
  max-height: calc(100vh - 96px);
  overflow-y: auto;
  padding: 10px;
  border: 1px solid #dce6e0;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 22px 52px rgb(15 35 23 / 17%);
}

.home-nav__mobile a {
  display: flex;
  min-height: 43px;
  align-items: center;
  padding: 0 13px;
  border-radius: 5px;
  color: #26362d;
  font-size: 14px;
  font-weight: 850;
  text-decoration: none;
}

.home-nav__mobile a:hover,
.home-nav__mobile a.is-active {
  background: #eaf7ee;
  color: #0d753b;
}

.home-nav-pop-enter-active,
.home-nav-pop-leave-active,
.home-nav-mobile-enter-active,
.home-nav-mobile-leave-active {
  transition: opacity .16s ease, transform .16s ease;
}

.home-nav-pop-enter-from,
.home-nav-pop-leave-to,
.home-nav-mobile-enter-from,
.home-nav-mobile-leave-to {
  opacity: 0;
  transform: translateY(-7px);
}

@media (max-width: 1120px) {
  .home-nav__links {
    gap: 16px;
  }

  .home-nav__partner {
    display: none;
  }
}

@media (max-width: 940px) {
  .home-nav__links {
    display: none;
  }

  .home-nav__menu-button {
    display: grid;
  }
}

@media (max-width: 620px) {
  .home-nav {
    height: 62px;
  }

  .home-nav__inner {
    width: calc(100% - 24px);
    gap: 10px;
  }

  .home-nav__brand {
    gap: 7px;
  }

  .home-nav__brand-mark {
    width: 34px;
    height: 34px;
  }

  .home-nav__brand-name {
    font-size: 20px;
  }

  .home-nav__actions {
    gap: 6px;
  }

  .home-nav__login {
    min-height: 36px;
    padding-inline: 10px;
    font-size: 13px;
  }

  .home-nav__register,
  .home-nav__icon-button {
    display: none;
  }

  .home-nav__menu-button,
  .home-nav__avatar {
    width: 36px;
    height: 36px;
  }

  .home-nav__mobile {
    right: 12px;
    left: 12px;
    grid-template-columns: 1fr;
    max-height: calc(100vh - 82px);
  }
}

@media (max-width: 365px) {
  .home-nav__login {
    width: 36px;
    padding: 0;
    font-size: 0;
  }
}
</style>

<template>
  <div class="sg-client-page sg3-profile-page">
    <PublicNavbar />
    <main class="sg3-profile-main sg3-container" aria-label="Tài khoản cá nhân">
      <div class="sg3-page-head">
        <div>
          <div class="sg3-breadcrumbs"><router-link to="/">Trang chủ</router-link><span>/</span><strong>Tài khoản</strong></div>
          <p class="sg3-kicker">Không gian cá nhân</p>
          <h1>Tài khoản của tôi</h1>
          <p>Quản lý thông tin cá nhân, lịch đặt và những quyền lợi đang có trên SportGo.</p>
        </div>
        <router-link class="sg3-button sg3-button--primary" to="/venues">
          <AppIcon name="search" :size="17" />
          Tìm sân mới
        </router-link>
      </div>


      <div class="sg3-profile-layout">
        <section class="sg3-profile-primary">
          <article class="sg3-card sg3-profile-identity">
            <span class="sg3-avatar sg3-avatar--photo">
              <img v-if="avatarUrl" :src="avatarUrl" :alt="`Ảnh đại diện của ${displayName}`" />
              <span v-else aria-hidden="true">{{ userInitial }}</span>
            </span>
            <div>
              <h2>{{ displayName }}</h2>
              <p>{{ user.email || "Chưa cập nhật email" }}<span v-if="user.phone"> · {{ user.phone }}</span></p>
              <span class="sg3-status">Tài khoản đang hoạt động</span>
            </div>
            <div class="sg3-profile-identity__actions">
              <button class="sg3-button sg3-button--secondary" type="button" @click="toggleEditor">
                <AppIcon name="edit" :size="17" />
                {{ editing ? 'Đóng chỉnh sửa' : 'Sửa hồ sơ' }}
              </button>
              <router-link class="sg3-button sg3-button--quiet" to="/vip-membership">
                <AppIcon name="shieldCheck" :size="17" />
                Thành viên
              </router-link>
            </div>
          </article>

          <section v-if="editing" class="sg3-card sg3-profile-editor" aria-labelledby="profile-editor-heading">
            <div class="sg3-profile-editor__head">
              <div>
                <p class="sg3-kicker">Cập nhật hồ sơ</p>
                <h2 id="profile-editor-heading">Thông tin hiển thị của bạn</h2>
              </div>
              <span class="sg3-profile-editor__hint">Email và tên tài khoản không thể đổi tại đây.</span>
            </div>
            <div class="sg3-profile-editor__body">
              <div class="sg3-avatar-uploader">
                <button type="button" class="sg3-avatar-uploader__preview" @click="$refs.avatarInput.click()" aria-label="Chọn ảnh đại diện">
                  <img v-if="avatarPreview" :src="avatarPreview" alt="Ảnh đại diện xem trước" />
                  <span v-else>{{ userInitial }}</span>
                  <i><AppIcon name="camera" :size="15" /></i>
                </button>
                <div>
                  <strong>Ảnh đại diện</strong>
                  <p>JPG, PNG hoặc WEBP · tối đa 2MB</p>
                  <button type="button" class="sg3-text-button" @click="$refs.avatarInput.click()">Chọn ảnh mới</button>
                  <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" hidden @change="selectAvatar" />
                </div>
              </div>
              <div class="sg3-profile-editor__fields">
                <label class="sg3-field"><span>Họ và tên</span><input v-model.trim="profileForm.full_name" type="text" autocomplete="name" /></label>
                <label class="sg3-field"><span>Số điện thoại</span><input v-model.trim="profileForm.phone" type="tel" autocomplete="tel" placeholder="0901234567" /></label>
                <label class="sg3-field"><span>Email</span><input :value="user.email || 'Chưa cập nhật'" type="email" disabled /></label>
                <label class="sg3-field"><span>Tên tài khoản</span><input :value="user.username || 'Chưa cập nhật'" type="text" disabled /></label>
              </div>
            </div>
            <p v-if="profileError" class="sg3-profile-editor__error">{{ profileError }}</p>
            <div class="sg3-profile-editor__footer">
              <button class="sg3-button sg3-button--secondary" type="button" @click="cancelEdit">Hủy</button>
              <button class="sg3-button sg3-button--primary" type="button" :disabled="saving" @click="saveProfile">
                <AppIcon name="check" :size="16" />
                {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
              </button>
            </div>
          </section>

          <div class="sg3-stats" aria-label="Tổng quan tài khoản">
            <article class="sg3-card sg3-stat"><span>Lịch đặt đã tạo</span><strong>{{ bookingCount }}</strong></article>
            <article class="sg3-card sg3-stat"><span>Số dư ví</span><strong>{{ formatCurrency(walletBalance) }}</strong></article>
            <article class="sg3-card sg3-stat"><span>Hạng thành viên</span><strong>{{ membershipLabel }}</strong></article>
          </div>

          <section class="sg3-card sg3-info-card" aria-labelledby="profile-information-heading">
            <h2 id="profile-information-heading">Thông tin cá nhân</h2>
            <div class="sg3-info-grid">
              <div class="sg3-info-item"><span>Họ và tên</span><strong>{{ user.fullName || "Chưa cập nhật" }}</strong></div>
              <div class="sg3-info-item"><span>Tên tài khoản</span><strong>{{ user.username || user.email || "Chưa cập nhật" }}</strong></div>
              <div class="sg3-info-item"><span>Email</span><strong>{{ user.email || "Chưa cập nhật" }}</strong></div>
              <div class="sg3-info-item"><span>Số điện thoại</span><strong>{{ user.phone || "Chưa cập nhật" }}</strong></div>
              <div class="sg3-info-item"><span>Vai trò</span><strong>{{ roleLabel }}</strong></div>
              <div class="sg3-info-item"><span>Trạng thái xác thực</span><strong>{{ user.email_verified_at ? "Email đã xác thực" : "Đang chờ xác thực" }}</strong></div>
            </div>
          </section>

          <section class="sg3-card sg3-security-card" aria-labelledby="profile-security-heading">
            <div>
              <p class="sg3-kicker">BẢO MẬT TÀI KHOẢN</p>
              <h2 id="profile-security-heading">Đổi mật khẩu</h2>
              <p class="sg3-security-copy">Dùng mật khẩu mạnh và không trùng với mật khẩu ở dịch vụ khác.</p>
            </div>
            <form class="sg3-security-form" @submit.prevent="changePassword">
              <label class="sg3-field"><span>Mật khẩu hiện tại</span><input v-model="passwordForm.current_password" type="password" autocomplete="current-password" required /></label>
              <label class="sg3-field"><span>Mật khẩu mới</span><input v-model="passwordForm.password" type="password" autocomplete="new-password" minlength="8" required /></label>
              <label class="sg3-field"><span>Xác nhận mật khẩu mới</span><input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" required /></label>
              <p v-if="passwordError" class="sg3-profile-editor__error">{{ passwordError }}</p>
              <div class="sg3-profile-editor__footer"><button class="sg3-button sg3-button--primary" type="submit" :disabled="passwordSaving">{{ passwordSaving ? 'Đang cập nhật...' : 'Cập nhật mật khẩu' }}</button></div>
            </form>
          </section>
        </section>

        <aside class="sg3-profile-aside">
          <section class="sg3-card sg3-side-card">
            <h2>Đi tới nhanh</h2>
            <p>Các việc bạn thường làm sau khi đăng nhập.</p>
            <div class="sg3-side-links">
              <router-link to="/bookings">Lịch đặt của tôi <AppIcon name="chevronRight" :size="16" /></router-link>
              <router-link to="/wallet">Ví SportGo <AppIcon name="chevronRight" :size="16" /></router-link>
              <router-link to="/refunds">Theo dõi hoàn tiền <AppIcon name="chevronRight" :size="16" /></router-link>
              <router-link to="/notifications">Thông báo <AppIcon name="chevronRight" :size="16" /></router-link>
              <router-link to="/favorites/venues">Sân yêu thích <AppIcon name="chevronRight" :size="16" /></router-link>
            </div>
          </section>

          <section class="sg3-card sg3-side-card">
            <h2>Cần hỗ trợ?</h2>
            <p>Gửi yêu cầu để đội ngũ SportGo hỗ trợ theo đúng lịch sử giao dịch của bạn.</p>
            <router-link class="sg3-button sg3-button--secondary" to="/complaints/new">Gửi yêu cầu hỗ trợ</router-link>
          </section>
        </aside>
      </div>
    </main>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { bookingService } from "../../services/bookingService.js";
import { authService } from "../../services/authService.js";
import { getAuth, saveAuth } from "../../stores/auth.js";
import { useToast } from "vue-toastification";

export default {
  name: "ClientProfile",
  components: { AppIcon, PublicNavbar },
  setup() { return { toast: useToast() }; },
  data() {
    const currentUser = getAuth();
    return {
      user: currentUser,
      bookingCount: 0,
      walletBalance: 0,
      membershipLabel: "Cơ bản",
      editing: false,
      saving: false,
      profileError: "",
      avatarFile: null,
      avatarPreview: currentUser?.avatar_url || currentUser?.user?.avatar_url || "",
      profileForm: {
        full_name: currentUser?.fullName || currentUser?.full_name || currentUser?.user?.full_name || "",
        phone: currentUser?.phone || currentUser?.user?.phone || "",
      },
      passwordForm: { current_password: '', password: '', password_confirmation: '' },
      passwordSaving: false,
      passwordError: '',
    };
  },
  computed: {
    displayName() {
      return this.user?.fullName || this.user?.full_name || this.user?.user?.full_name || "Người chơi SportGo";
    },
    avatarUrl() {
      return this.user?.avatar_url || this.user?.user?.avatar_url || "";
    },
    userInitial() {
      return this.displayName.trim().charAt(0).toUpperCase() || "S";
    },
    roleLabel() {
      return this.user?.role === "owner" ? "Chủ sân" : this.user?.role === "staff" ? "Nhân viên sân" : "Người chơi";
    },
  },
  created() {
    if (!this.user) {
      this.$router.replace({ name: "login", query: { redirect: this.$route.fullPath } });
      return;
    }
    this.loadOverview();
  },
  methods: {
    toggleEditor() {
      this.editing = !this.editing;
      if (this.editing) this.beginEdit();
    },
    beginEdit() {
      this.profileError = "";
      this.avatarFile = null;
      this.avatarPreview = this.avatarUrl;
      this.profileForm = {
        full_name: this.displayName,
        phone: this.user?.phone || this.user?.user?.phone || "",
      };
    },
    cancelEdit() {
      this.editing = false;
      this.profileError = "";
      this.avatarFile = null;
      this.avatarPreview = this.avatarUrl;
    },
    selectAvatar(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) {
        this.profileError = "Ảnh đại diện không được vượt quá 2MB.";
        event.target.value = "";
        return;
      }
      this.avatarFile = file;
      this.avatarPreview = URL.createObjectURL(file);
      this.profileError = "";
    },
    async saveProfile() {
      this.profileError = "";
      this.saving = true;
      try {
        const formData = new FormData();
        formData.append("full_name", this.profileForm.full_name || "");
        formData.append("phone", this.profileForm.phone || "");
        if (this.avatarFile) formData.append("avatar", this.avatarFile);
        const response = await authService.updateProfile(formData);
        this.user = saveAuth({ ...getAuth(), user: response.user });
        this.editing = false;
        this.avatarFile = null;
        this.avatarPreview = this.avatarUrl;
      } catch (error) {
        this.profileError = error.message || "Không thể cập nhật thông tin cá nhân.";
      } finally {
        this.saving = false;
      }
    },
    async changePassword() {
      this.passwordError = '';
      if (this.passwordForm.password !== this.passwordForm.password_confirmation) {
        this.passwordError = 'Xác nhận mật khẩu mới không khớp.';
        return;
      }
      this.passwordSaving = true;
      try {
        await authService.changePassword(this.passwordForm.current_password, this.passwordForm.password, this.passwordForm.password_confirmation);
        this.passwordForm = { current_password: '', password: '', password_confirmation: '' };
        this.toast.success('Đã đổi mật khẩu thành công.');
      } catch (error) {
        this.passwordError = error.message || 'Không thể đổi mật khẩu.';
      } finally {
        this.passwordSaving = false;
      }
    },
    async loadOverview() {
      try {
        const [bookingsResponse, walletResponse] = await Promise.allSettled([bookingService.listBookings({ limit: 1 }), bookingService.getWallet()]);
        if (bookingsResponse.status === "fulfilled") {
          const payload = bookingsResponse.value?.data;
          this.bookingCount = Number(payload?.meta?.total ?? payload?.total ?? (Array.isArray(payload) ? payload.length : 0));
        }
        if (walletResponse.status === "fulfilled") {
          const payload = walletResponse.value?.data || walletResponse.value || {};
          this.walletBalance = Number(payload.balance ?? payload.wallet?.balance ?? 0);
        }
      } catch (error) {
        console.warn("Không thể tải tổng quan tài khoản", error);
      }
    },
    formatCurrency(value) {
      return `${new Intl.NumberFormat("vi-VN").format(Number(value || 0))} đ`;
    },
  },
};
</script>

<style scoped>
.sg3-profile-page :deep(.sg3-account-nav) { margin-bottom: 20px; }
.sg3-profile-page :deep(.sg3-side-links svg) { flex: 0 0 auto; }
</style>

import { login, getAuth, getSelectedCluster, loginWithGoogle } from '../../stores/auth.js';

export default {
  name: 'LoginView',
  data() {
    return {
      username: '',
      password: '',
      error: '',
      isLoading: false,
      showPassword: false,
      focusedField: '',
    };
  },
  mounted() {
    // Để trống hoặc thực hiện logic khởi tạo cần thiết khác
  },
  methods: {
    async handleLogin() {
      this.error = '';
      this.isLoading = true;

      try {
        const auth = await login(this.username.trim(), this.password);
        this.isLoading = false;

        if (auth.role_group === 'owner') {
          const cluster = getSelectedCluster();
          this.$router.push(cluster ? '/owner/dashboard' : auth.redirect_to);
          return;
        }

        this.$router.push(auth.redirect_to || '/');
      } catch (error) {
        this.isLoading = false;
        const details = error.data || {};
        const lockDetails = [
          details.status_reason,
          details.lock_type ? `Loại khóa: ${details.lock_type}` : null,
          details.locked_until ? `Khóa đến: ${details.locked_until}` : null,
        ].filter(Boolean).join(' - ');
        this.error = lockDetails
          ? `${error.message} ${lockDetails}`
          : (error.message || 'Sai tài khoản hoặc mật khẩu.');
      }
    },
    handleGoogleLogin() {
      loginWithGoogle();
    },
  },
};

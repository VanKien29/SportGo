<template>
  <div class="partner-registration-page">
    <div class="page-header text-center">
      <h2>Đăng ký trở thành Đối tác SportGo</h2>
      <p class="muted">Gia nhập mạng lưới các sân thể thao hàng đầu và tiếp cận hàng ngàn khách hàng tiềm năng.</p>
    </div>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải thông tin...</p>
    </div>

    <div v-else-if="successMessage" class="state-box card success-box">
      <AppIcon name="checkCircle" size="48" class="success-icon" />
      <h3>Đăng ký thành công!</h3>
      <p>{{ successMessage }}</p>
      <button class="btn primary mt-4" @click="$router.push('/profile')">Về trang cá nhân</button>
    </div>

    <div v-else class="form-container card">
      <div v-if="error" class="notice error mb-4">{{ error }}</div>

      <form @submit.prevent="submitApplication">
        
        <!-- Thông tin liên hệ -->
        <section class="form-section">
          <h3>1. Thông tin liên hệ</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Loại hình đối tác *</label>
              <select v-model="form.applicant_type" class="input" required>
                <option value="individual">Cá nhân / Hộ kinh doanh</option>
                <option value="business">Doanh nghiệp</option>
              </select>
            </div>
            <div class="form-group">
              <label>Họ và tên người đăng ký *</label>
              <input v-model="form.applicant_full_name" class="input" required />
            </div>
            <div class="form-group">
              <label>Số điện thoại *</label>
              <input v-model="form.applicant_phone" type="tel" class="input" required />
            </div>
            <div class="form-group">
              <label>Email *</label>
              <input v-model="form.applicant_email" type="email" class="input" required />
            </div>
            <div class="form-group full-width">
              <label>Địa chỉ liên hệ *</label>
              <input v-model="form.applicant_address" class="input" required />
            </div>
          </div>
        </section>

        <hr class="divider" />

        <!-- Thông tin kinh doanh -->
        <section class="form-section">
          <h3>2. Thông tin kinh doanh</h3>
          <div class="form-grid">
            <div class="form-group full-width">
              <label>Tên cơ sở kinh doanh / Doanh nghiệp *</label>
              <input v-model="form.business_name" class="input" required />
            </div>
            <div class="form-group">
              <label>Mã số thuế</label>
              <input v-model="form.tax_code" class="input" />
            </div>
            <div class="form-group">
              <label>Mã số doanh nghiệp / ĐKKD</label>
              <input v-model="form.business_code" class="input" />
            </div>
            <div class="form-group">
              <label>Người đại diện pháp luật</label>
              <input v-model="form.business_representative_name" class="input" />
            </div>
            <div class="form-group">
              <label>Chức vụ người đại diện</label>
              <input v-model="form.business_representative_position" class="input" />
            </div>
          </div>
        </section>

        <hr class="divider" />

        <!-- Thông tin cụm sân -->
        <section class="form-section">
          <h3>3. Thông tin cụm sân dự kiến</h3>
          <div class="form-grid">
            <div class="form-group full-width">
              <label>Tên cụm sân *</label>
              <input v-model="form.venue_name" class="input" required />
            </div>
            <div class="form-group full-width">
              <label>Địa chỉ cụm sân *</label>
              <input v-model="form.venue_address" class="input" required />
            </div>
            <div class="form-group">
              <label>Tỉnh/Thành phố *</label>
              <select v-model="form.venue_province" @change="onProvinceChange" class="input" required>
                <option value="" disabled>Chọn Tỉnh/Thành phố</option>
                <option v-for="p in provincesData" :key="p.code" :value="p.name">
                  {{ p.name }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Quận/Huyện *</label>
              <select v-model="form.venue_district" @change="onDistrictChange" class="input" required :disabled="!availableDistricts.length">
                <option value="" disabled>Chọn Quận/Huyện</option>
                <option v-for="d in availableDistricts" :key="d.code" :value="d.name">
                  {{ d.name }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Phường/Xã *</label>
              <select v-model="form.venue_ward" class="input" required :disabled="!availableWards.length">
                <option value="" disabled>Chọn Phường/Xã</option>
                <option v-for="w in availableWards" :key="w.code" :value="w.name">
                  {{ w.name }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Số lượng sân dự kiến *</label>
              <input type="number" v-model="form.court_count_total" class="input" required min="1" />
            </div>
            <div class="form-group">
              <label>Tọa độ Latitude *</label>
              <input type="number" step="any" v-model="form.venue_latitude" class="input" required />
            </div>
            <div class="form-group">
              <label>Tọa độ Longitude *</label>
              <input type="number" step="any" v-model="form.venue_longitude" class="input" required />
            </div>
            <div class="form-group full-width">
              <label>Link Google Maps</label>
              <input type="url" v-model="form.venue_map_url" class="input" />
            </div>
            <div class="form-group full-width">
              <label>Mô tả dịch vụ</label>
              <textarea v-model="form.venue_description" class="input" rows="3"></textarea>
            </div>
          </div>
        </section>

        <hr class="divider" />

        <!-- Tài khoản ngân hàng -->
        <section class="form-section">
          <h3>4. Tài khoản nhận tiền (Đối soát)</h3>
          <p class="muted mb-4">SportGo sẽ chuyển khoản doanh thu vào tài khoản này.</p>
          <div class="form-grid">
            <div class="form-group">
              <label>Ngân hàng *</label>
              <input v-model="form.bank_account.bank_name" class="input" required placeholder="VD: Vietcombank" />
            </div>
            <div class="form-group">
              <label>Mã ngân hàng (Tên viết tắt) *</label>
              <input v-model="form.bank_account.bank_code" class="input" required placeholder="VD: VCB" />
            </div>
            <div class="form-group">
              <label>Số tài khoản *</label>
              <input v-model="form.bank_account.account_number" class="input" required />
            </div>
            <div class="form-group">
              <label>Tên chủ tài khoản *</label>
              <input v-model="form.bank_account.account_holder_name" class="input" required />
            </div>
            <div class="form-group full-width">
              <label>Chi nhánh</label>
              <input v-model="form.bank_account.branch_name" class="input" />
            </div>
          </div>
        </section>

        <div class="form-actions mt-4">
          <button type="submit" class="btn primary btn-large full-width" :disabled="submitting">
            {{ submitting ? 'Đang gửi hồ sơ...' : 'Nộp hồ sơ đăng ký' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';
import { getAuth } from '../../stores/auth.js';

export default {
  name: 'PartnerRegistration',
  components: { AppIcon },
  data() {
    return {
      loading: false,
      submitting: false,
      error: '',
      successMessage: '',
      form: {
        applicant_type: 'individual',
        applicant_full_name: '',
        applicant_phone: '',
        applicant_email: '',
        applicant_address: '',
        
        business_name: '',
        tax_code: '',
        business_code: '',
        business_representative_name: '',
        business_representative_position: '',
        
        venue_name: '',
        venue_address: '',
        venue_province: '',
        venue_district: '',
        venue_ward: '',
        court_count_total: 1,
        venue_latitude: '',
        venue_longitude: '',
        venue_map_url: '',
        venue_description: '',

        bank_account: {
          bank_name: '',
          bank_code: '',
          account_number: '',
          account_holder_name: '',
          branch_name: '',
        }
      },
      provincesData: [],
      availableDistricts: [],
      availableWards: []
    };
  },
  mounted() {
    this.prefillUserInfo();
    this.fetchProvinces();
  },
  methods: {
    prefillUserInfo() {
      const auth = getAuth();
      if (auth && auth.user) {
        this.form.applicant_full_name = auth.user.full_name || '';
        this.form.applicant_email = auth.user.email || '';
        this.form.applicant_phone = auth.user.phone || '';
      }
    },
    async fetchProvinces() {
      try {
        const response = await fetch('https://provinces.open-api.vn/api/?depth=3');
        this.provincesData = await response.json();
      } catch (err) {
        console.error('Error fetching provinces:', err);
      }
    },
    onProvinceChange() {
      this.form.venue_district = '';
      this.form.venue_ward = '';
      this.availableWards = [];
      const province = this.provincesData.find(p => p.name === this.form.venue_province);
      this.availableDistricts = province ? province.districts : [];
    },
    onDistrictChange() {
      this.form.venue_ward = '';
      const district = this.availableDistricts.find(d => d.name === this.form.venue_district);
      this.availableWards = district ? district.wards : [];
    },
    async submitApplication() {
      this.error = '';
      this.submitting = true;
      try {
        const payload = { ...this.form };
        payload.bank_accounts = [this.form.bank_account];
        delete payload.bank_account;

        const response = await api('/api/partner-applications', {
          method: 'POST',
          body: JSON.stringify(payload)
        });
        
        this.successMessage = response.message || 'Hồ sơ đã được gửi thành công.';
        window.scrollTo(0, 0);
      } catch (err) {
        this.error = err.message || 'Có lỗi xảy ra khi nộp hồ sơ đăng ký.';
        window.scrollTo(0, 0);
      } finally {
        this.submitting = false;
      }
    }
  }
};
</script>

<style scoped>
.partner-registration-page {
  max-width: 800px;
  margin: 0 auto;
  padding: 40px 20px;
}

.page-header {
  margin-bottom: 32px;
}

.page-header h2 {
  font-size: 28px;
  font-weight: 800;
  color: var(--sg-text);
  margin-bottom: 8px;
}

.card {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 12px;
  padding: 32px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.form-section {
  margin-bottom: 24px;
}

.form-section h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 16px;
  color: #0f172a;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
  color: #334155;
}

.input {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.2s;
}

.input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.divider {
  border: 0;
  border-top: 1px solid var(--sg-border);
  margin: 32px 0;
}

.btn {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 15px;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.primary:hover:not(:disabled) {
  background: #1e293b;
}

.btn-large {
  padding: 14px 24px;
  font-size: 16px;
}

.full-width {
  width: 100%;
}

.btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.notice.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
  padding: 16px;
  border-radius: 8px;
  font-weight: 500;
}

.success-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
}

.success-icon {
  color: #10b981;
  margin-bottom: 16px;
}

.success-box h3 {
  font-size: 24px;
  margin-bottom: 8px;
  color: #065f46;
}

.mt-4 {
  margin-top: 16px;
}

.mb-4 {
  margin-bottom: 16px;
}

.muted {
  color: #64748b;
}

.text-center {
  text-align: center;
}
</style>

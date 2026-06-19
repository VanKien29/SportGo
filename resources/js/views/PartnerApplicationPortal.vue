<template>
  <div class="partner-portal">
    <PublicNavbar />

    <main class="portal-shell">
      <section class="page-head">
        <div>
          <p class="eyebrow">SportGo Partner</p>
          <h1>Đăng ký đối tác chủ sân</h1>
          <p>Quản lý các lần đăng ký trước, tạo hồ sơ mới và xác nhận đơn trước khi gửi SportGo xét duyệt.</p>
        </div>
        <button class="primary-btn" type="button" :disabled="formOpen || !canRegister" @click="openForm">
          Đăng ký đối tác
        </button>
      </section>

      <p v-if="error" class="alert error">{{ error }}</p>
      <p v-if="success" class="alert success">{{ success }}</p>

      <section class="history-section">
        <div class="section-head">
          <div>
            <h2>Các lần đăng ký trước</h2>
            <p>Theo dõi trạng thái hồ sơ, tài liệu đã nộp và hủy hồ sơ khi chưa được xử lý.</p>
          </div>
          <button class="ghost-btn" type="button" @click="loadApplications">Làm mới</button>
        </div>

        <div v-if="loading" class="state">Đang tải hồ sơ...</div>
        <div v-else-if="applications.length === 0" class="state">Bạn chưa có hồ sơ đăng ký đối tác nào.</div>
        <div v-else class="application-list">
          <article v-for="application in applications" :key="application.id" class="application-row">
            <div>
              <div class="row-title">
                <strong>{{ application.venue_name }}</strong>
                <span :class="['status-pill', application.status]">{{ statusLabel(application.status) }}</span>
              </div>
              <p>{{ application.venue_address }}</p>
              <small>
                Nộp ngày {{ formatDate(application.submitted_at) }} ·
                {{ application.documents?.length || 0 }} tài liệu đính kèm
              </small>
            </div>
            <button v-if="canCancel(application)" class="danger-btn" type="button" @click="cancelApplication(application)">
              Hủy đăng ký
            </button>
          </article>
        </div>
      </section>

      <form v-if="formOpen" class="application-form" @submit.prevent="submit">
        <div class="step-tabs">
          <button
            v-for="(label, index) in steps"
            :key="label"
            type="button"
            :class="{ active: step === index }"
            @click="goToStep(index)"
          >
            <span>{{ index + 1 }}</span>
            {{ label }}
          </button>
        </div>

        <section v-if="step === 0" class="form-section">
          <h2>Thông tin người đăng ký</h2>
          <div class="form-grid">
            <label>
              Họ tên người đăng ký
              <input v-model.trim="form.applicant_full_name" required />
            </label>
            <label>
              Số điện thoại
              <input v-model.trim="form.applicant_phone" required />
            </label>
            <label>
              Email
              <input v-model.trim="form.applicant_email" type="email" required />
            </label>
            <label>
              Loại chủ thể
              <select v-model="form.applicant_type">
                <option value="individual">Cá nhân/hộ kinh doanh</option>
                <option value="business">Hộ kinh doanh có giấy phép</option>
                <option value="company">Doanh nghiệp</option>
              </select>
            </label>
            <label class="full">
              Địa chỉ liên hệ
              <textarea v-model.trim="form.applicant_address" rows="2" required></textarea>
            </label>
            <label>
              Người đại diện
              <input v-model.trim="form.representative_name" required />
            </label>
            <label>
              Loại giấy tờ
              <select v-model="form.representative_identity_type">
                <option value="cccd">CCCD</option>
                <option value="cmnd">CMND</option>
                <option value="passport">Hộ chiếu</option>
              </select>
            </label>
            <label>
              Số giấy tờ
              <input v-model.trim="form.representative_identity_number" required />
            </label>
            <label>
              Ngày cấp
              <input v-model="form.representative_identity_issued_date" type="date" />
            </label>
            <label>
              Nơi cấp
              <input v-model.trim="form.representative_identity_issued_place" />
            </label>
            <label>
              Chức vụ
              <input v-model.trim="form.representative_position" placeholder="Chủ hộ / Giám đốc / Người đại diện" />
            </label>
          </div>

          <h2>Thông tin pháp lý cơ sở</h2>
          <div class="form-grid">
            <label>
              Tên đơn vị/cá nhân kinh doanh
              <input v-model.trim="form.business_name" required />
            </label>
            <label>
              Mã số thuế
              <input v-model.trim="form.tax_code" />
            </label>
            <label>
              Số giấy đăng ký
              <input v-model.trim="form.business_license_number" required />
            </label>
            <label>
              Mã doanh nghiệp/hộ kinh doanh
              <input v-model.trim="form.business_code" />
            </label>
            <label class="full">
              Địa chỉ pháp lý
              <textarea v-model.trim="form.business_address" rows="2" required></textarea>
            </label>
          </div>
        </section>

        <section v-if="step === 1" class="form-section">
          <h2>Địa điểm sân</h2>
          <div class="map-row">
            <label>
              Link Google Maps
              <input v-model.trim="form.venue_map_url" type="url" required @input="mapResolved = false" />
            </label>
            <button class="secondary-btn" type="button" :disabled="resolvingMap || !form.venue_map_url" @click="resolveMap">
              {{ resolvingMap ? 'Đang đọc link...' : 'Lấy tọa độ' }}
            </button>
          </div>
          <p class="hint">Hệ thống sẽ đọc tọa độ từ link và tự điền tỉnh/thành phố, phường/xã theo mô hình 2 cấp.</p>

          <div class="form-grid">
            <label>
              Tên cụm sân
              <input v-model.trim="form.venue_name" required />
            </label>
            <label>
              Số điện thoại sân
              <input v-model.trim="form.venue_phone" required />
            </label>
            <label>
              Email sân
              <input v-model.trim="form.venue_email" type="email" />
            </label>
            <label>
              Tỉnh/Thành phố
              <input v-model.trim="form.venue_province" required />
            </label>
            <label>
              Phường/Xã
              <input v-model.trim="form.venue_ward" required />
            </label>
            <label>
              Giờ mở cửa dự kiến
              <input v-model.trim="form.expected_opening_hours" placeholder="05:00 - 23:00" />
            </label>
            <label class="full">
              Địa chỉ chi tiết
              <textarea v-model.trim="form.venue_address" rows="2" required></textarea>
            </label>
            <label>
              Vĩ độ
              <input v-model.number="form.venue_latitude" type="number" step="0.0000001" required />
            </label>
            <label>
              Kinh độ
              <input v-model.number="form.venue_longitude" type="number" step="0.0000001" required />
            </label>
            <label class="full">
              Mô tả cơ sở
              <textarea v-model.trim="form.venue_description" rows="3"></textarea>
            </label>
            <label class="full">
              Thông tin bãi xe/phụ trợ
              <textarea v-model.trim="form.parking_info" rows="2"></textarea>
            </label>
          </div>

          <div class="amenity-box">
            <span>Tiện ích khả dụng</span>
            <label v-for="amenity in amenities" :key="amenity.id || amenity.name" class="check-chip">
              <input v-model="form.amenities" type="checkbox" :value="amenity.name" />
              {{ amenity.name }}
            </label>
          </div>
        </section>

        <section v-if="step === 2" class="form-section">
          <h2>Tài khoản ngân hàng nhận tiền</h2>
          <div class="form-grid">
            <label>
              Ngân hàng
              <select v-model="form.bank_code" required @change="selectBank">
                <option value="">Chọn ngân hàng</option>
                <option v-for="bank in banks" :key="bank.code" :value="bank.code">
                  {{ bank.short_name || bank.code }} - {{ bank.name }}
                </option>
              </select>
            </label>
            <label>
              Số tài khoản
              <input v-model.trim="form.account_number" required @input="resetBankVerification" />
            </label>
            <label>
              Tên chủ tài khoản
              <input v-model.trim="form.account_holder_name" required @input="resetBankVerification" />
            </label>
            <label>
              Chi nhánh
              <input v-model.trim="form.bank_branch" />
            </label>
          </div>
          <div class="verify-line">
            <button class="secondary-btn" type="button" :disabled="verifyingBank" @click="verifyBank">
              {{ verifyingBank ? 'Đang kiểm tra...' : 'Kiểm tra tài khoản' }}
            </button>
            <span v-if="bankVerification.message" :class="['verify-status', bankVerification.status]">
              {{ bankVerification.message }}
            </span>
          </div>
        </section>

        <section v-if="step === 3" class="form-section">
          <h2>Cấu hình sân ban đầu</h2>
          <div class="court-toolbar">
            <label>
              Quy mô sân con
              <input v-model.number="form.court_count_total" min="1" max="100" type="number" @change="syncCourtRows" />
            </label>
            <button class="secondary-btn" type="button" @click="syncCourtRows">Tạo danh sách sân</button>
          </div>

          <div class="court-list">
            <article v-for="(court, index) in form.courts" :key="index" class="court-row">
              <label>
                Tên sân
                <input v-model.trim="court.name" required />
              </label>
              <label>
                Loại sân
                <select v-model="court.court_type_id" required>
                  <option value="">Chọn loại sân</option>
                  <option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
              </label>
              <label>
                Giá cơ bản/giờ
                <input v-model.number="court.base_price" min="0" step="1000" type="number" required />
              </label>
              <button class="icon-btn" type="button" :disabled="form.courts.length === 1" @click="removeCourt(index)">×</button>
            </article>
          </div>
          <button class="ghost-btn" type="button" @click="addCourt">Thêm sân con</button>
        </section>

        <section v-if="step === 4" class="form-section">
          <h2>Tài liệu kèm theo</h2>
          <div class="upload-grid">
            <label class="upload-box">
              <span>CCCD/CMND/Hộ chiếu</span>
              <small>Có thể tải nhiều mặt/file, định dạng ảnh hoặc PDF.</small>
              <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" @change="setFiles('identity', $event)" />
              <strong>{{ fileNames('identity') }}</strong>
            </label>
            <label class="upload-box">
              <span>Giấy đăng ký kinh doanh/pháp lý cơ sở</span>
              <small>Giấy phép, giấy ủy quyền hoặc tài liệu chứng minh quyền quản lý.</small>
              <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" @change="setFiles('business_license', $event)" />
              <strong>{{ fileNames('business_license') }}</strong>
            </label>
            <label class="upload-box">
              <span>Hình ảnh cơ sở</span>
              <small>Ảnh sân, khu phụ trợ, biển hiệu, bãi xe. Có thể tải nhiều file.</small>
              <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" @change="setFiles('facility', $event)" />
              <strong>{{ fileNames('facility') }}</strong>
            </label>
          </div>
        </section>

        <section v-if="step === 5" class="form-section">
          <h2>Xác nhận đơn đăng ký</h2>
          <div class="preview-paper">
            <h3>Đơn đề nghị đăng ký đối tác chủ sân SportGo</h3>
            <dl>
              <dt>Người đăng ký</dt><dd>{{ form.applicant_full_name }} · {{ form.applicant_phone }} · {{ form.applicant_email }}</dd>
              <dt>Đơn vị kinh doanh</dt><dd>{{ form.business_name }} · MST {{ form.tax_code || '-' }}</dd>
              <dt>Cụm sân</dt><dd>{{ form.venue_name }} · {{ form.venue_address }}</dd>
              <dt>Khu vực 2 cấp</dt><dd>{{ form.venue_ward }} · {{ form.venue_province }}</dd>
              <dt>Tọa độ</dt><dd>{{ form.venue_latitude }}, {{ form.venue_longitude }}</dd>
              <dt>Ngân hàng</dt><dd>{{ form.bank_name }} · {{ form.account_number }} · {{ form.account_holder_name }}</dd>
              <dt>Quy mô</dt><dd>{{ form.courts.length }} sân con · giá cơ bản từ {{ minBasePrice }}</dd>
              <dt>Tài liệu</dt><dd>{{ files.identity.length }} CCCD, {{ files.business_license.length }} giấy đăng ký, {{ files.facility.length }} ảnh cơ sở</dd>
            </dl>
          </div>
          <label class="confirm-line">
            <input v-model="confirmed" type="checkbox" />
            Tôi xác nhận thông tin trong đơn đăng ký là đúng và đồng ý gửi SportGo xét duyệt.
          </label>
        </section>

        <p v-if="formError" class="alert error">{{ formError }}</p>

        <footer class="form-actions">
          <button class="ghost-btn" type="button" @click="closeForm">Hủy</button>
          <button v-if="step > 0" class="secondary-btn" type="button" @click="prevStep">Quay lại</button>
          <button v-if="step < steps.length - 1" class="primary-btn" type="button" @click="nextStep">Tiếp tục</button>
          <button v-else class="primary-btn" type="submit" :disabled="submitting">
            {{ submitting ? 'Đang gửi...' : 'Gửi hồ sơ' }}
          </button>
        </footer>
      </form>
    </main>
  </div>
</template>

<script>
import PublicNavbar from '../components/PublicNavbar.vue';
import { api, apiFormData } from '../services/api.js';
import { getAuth } from '../stores/auth.js';

function blankFiles() {
  return { identity: [], business_license: [], facility: [] };
}

export default {
  name: 'PartnerApplicationPortal',
  components: { PublicNavbar },
  data() {
    const user = getAuth();
    return {
      user,
      loading: false,
      formOpen: false,
      step: 0,
      steps: ['Người đăng ký', 'Địa điểm', 'Ngân hàng', 'Cấu hình sân', 'Tài liệu', 'Xác nhận'],
      applications: [],
      canRegister: true,
      banks: [],
      courtTypes: [],
      amenities: [],
      error: '',
      success: '',
      formError: '',
      resolvingMap: false,
      mapResolved: false,
      verifyingBank: false,
      bankVerification: {},
      submitting: false,
      confirmed: false,
      files: blankFiles(),
      form: this.defaultForm(user),
    };
  },
  computed: {
    minBasePrice() {
      const prices = this.form.courts.map((court) => Number(court.base_price || 0)).filter((price) => price > 0);
      if (!prices.length) return '0đ';
      return new Intl.NumberFormat('vi-VN').format(Math.min(...prices)) + 'đ/giờ';
    },
  },
  created() {
    if (!this.user) {
      this.$router.replace({ name: 'login' });
      return;
    }

    this.loadInitialData();
  },
  methods: {
    defaultForm(user) {
      return {
        applicant_full_name: user?.fullName || '',
        applicant_phone: user?.phone || '',
        applicant_email: user?.email || '',
        applicant_address: '',
        applicant_type: 'individual',
        representative_name: user?.fullName || '',
        representative_identity_type: 'cccd',
        representative_identity_number: '',
        representative_identity_issued_date: '',
        representative_identity_issued_place: '',
        representative_position: 'Chủ cơ sở',
        business_name: '',
        tax_code: '',
        business_code: '',
        business_license_number: '',
        business_address: '',
        venue_name: '',
        venue_address: '',
        venue_province: '',
        venue_district: '',
        venue_ward: '',
        venue_map_url: '',
        venue_latitude: '',
        venue_longitude: '',
        venue_phone: user?.phone || '',
        venue_email: user?.email || '',
        venue_description: '',
        expected_opening_hours: '05:00 - 23:00',
        parking_info: '',
        amenities: [],
        court_count_total: 1,
        courts: [{ name: 'Sân 1', court_type_id: '', base_price: 0, note: '' }],
        bank_name: '',
        bank_code: '',
        bank_bin: '',
        account_number: '',
        account_holder_name: '',
        bank_branch: '',
      };
    },
    async loadInitialData() {
      await Promise.all([this.loadApplications(), this.loadBanks(), this.loadCourtTypes(), this.loadAmenities()]);
    },
    async loadApplications() {
      this.loading = true;
      try {
        const response = await api('/api/user/partner-application');
        this.applications = response.data?.history || [];
        this.canRegister = Boolean(response.data?.can_register);
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },
    async loadBanks() {
      const response = await api('/api/user/partner-application/banks');
      this.banks = response.data || [];
    },
    async loadCourtTypes() {
      const response = await api('/api/court-types');
      this.courtTypes = response.data || [];
    },
    async loadAmenities() {
      const response = await api('/api/amenities?active_only=1');
      this.amenities = response.data || [];
    },
    openForm() {
      this.error = '';
      this.success = '';
      this.formOpen = true;
      this.step = 0;
    },
    closeForm() {
      if (!window.confirm('Hủy thao tác đăng ký hiện tại? Thông tin chưa gửi sẽ không được lưu.')) return;
      this.formOpen = false;
      this.form = this.defaultForm(this.user);
      this.files = blankFiles();
      this.confirmed = false;
      this.formError = '';
      this.bankVerification = {};
    },
    goToStep(index) {
      if (index <= this.step || this.validateStep(this.step)) this.step = index;
    },
    nextStep() {
      if (this.validateStep(this.step)) this.step += 1;
    },
    prevStep() {
      this.formError = '';
      this.step -= 1;
    },
    validateStep(step) {
      this.formError = '';
      const required = (value) => value !== null && value !== undefined && String(value).trim() !== '';

      if (step === 0) {
        const fields = ['applicant_full_name', 'applicant_phone', 'applicant_email', 'applicant_address', 'representative_name', 'representative_identity_number', 'business_name', 'business_license_number', 'business_address'];
        if (fields.some((field) => !required(this.form[field]))) return this.fail('Vui lòng nhập đủ thông tin người đăng ký và pháp lý cơ sở.');
      }

      if (step === 1) {
        const fields = ['venue_map_url', 'venue_name', 'venue_phone', 'venue_address', 'venue_province', 'venue_ward', 'venue_latitude', 'venue_longitude'];
        if (fields.some((field) => !required(this.form[field]))) return this.fail('Vui lòng nhập link Google Maps, tọa độ và địa chỉ 2 cấp của sân.');
      }

      if (step === 2) {
        const fields = ['bank_code', 'account_number', 'account_holder_name'];
        if (fields.some((field) => !required(this.form[field]))) return this.fail('Vui lòng nhập đủ thông tin tài khoản ngân hàng.');
        if (!this.bankVerification.status) return this.fail('Vui lòng bấm kiểm tra tài khoản ngân hàng trước khi tiếp tục.');
        if (['invalid_bank', 'invalid_account_number', 'not_found', 'name_mismatch'].includes(this.bankVerification.status)) {
          return this.fail(this.bankVerification.message || 'Thông tin tài khoản ngân hàng chưa hợp lệ.');
        }
      }

      if (step === 3) {
        if (!this.form.courts.length) return this.fail('Vui lòng cấu hình ít nhất một sân con.');
        const invalid = this.form.courts.some((court) => !required(court.name) || !required(court.court_type_id) || Number(court.base_price) < 0);
        if (invalid) return this.fail('Mỗi sân con cần có tên sân, loại sân và giá cơ bản hợp lệ.');
      }

      if (step === 4) {
        if (!this.files.identity.length || !this.files.business_license.length || !this.files.facility.length) {
          return this.fail('Vui lòng tải lên CCCD/giấy tờ định danh, giấy đăng ký và hình ảnh cơ sở.');
        }
      }

      return true;
    },
    fail(message) {
      this.formError = message;
      return false;
    },
    async resolveMap() {
      this.resolvingMap = true;
      this.formError = '';
      try {
        const response = await api('/api/user/partner-application/resolve-map', {
          method: 'POST',
          body: JSON.stringify({ url: this.form.venue_map_url }),
        });
        const data = response.data || {};
        if (data.latitude && data.longitude) {
          this.form.venue_latitude = data.latitude;
          this.form.venue_longitude = data.longitude;
          this.form.venue_address = data.address || this.form.venue_address;
          this.form.venue_province = data.province || this.form.venue_province;
          this.form.venue_ward = data.ward || this.form.venue_ward;
          this.mapResolved = true;
        } else {
          this.formError = 'Chưa đọc được tọa độ từ link. Bạn có thể dán link Google Maps dạng có @lat,lng hoặc nhập tọa độ thủ công.';
        }
      } catch (error) {
        this.formError = error.message;
      } finally {
        this.resolvingMap = false;
      }
    },
    selectBank() {
      const bank = this.banks.find((item) => item.code === this.form.bank_code);
      this.form.bank_name = bank?.short_name || bank?.name || '';
      this.form.bank_bin = bank?.bin || '';
      this.resetBankVerification();
    },
    resetBankVerification() {
      this.bankVerification = {};
    },
    async verifyBank() {
      this.verifyingBank = true;
      this.formError = '';
      try {
        const response = await api('/api/user/partner-application/verify-bank-account', {
          method: 'POST',
          body: JSON.stringify({
            bank_code: this.form.bank_code,
            bank_bin: this.form.bank_bin,
            account_number: this.form.account_number,
            account_holder_name: this.form.account_holder_name,
          }),
        });
        this.bankVerification = response.data || {};
      } catch (error) {
        this.bankVerification = { status: 'error', message: error.message };
      } finally {
        this.verifyingBank = false;
      }
    },
    syncCourtRows() {
      const total = Math.max(1, Number(this.form.court_count_total || 1));
      while (this.form.courts.length < total) {
        this.form.courts.push({ name: `Sân ${this.form.courts.length + 1}`, court_type_id: this.form.courts[0]?.court_type_id || '', base_price: this.form.courts[0]?.base_price || 0, note: '' });
      }
      this.form.courts = this.form.courts.slice(0, total);
    },
    addCourt() {
      this.form.courts.push({ name: `Sân ${this.form.courts.length + 1}`, court_type_id: '', base_price: 0, note: '' });
      this.form.court_count_total = this.form.courts.length;
    },
    removeCourt(index) {
      this.form.courts.splice(index, 1);
      this.form.court_count_total = this.form.courts.length;
    },
    setFiles(group, event) {
      this.files[group] = Array.from(event.target.files || []);
    },
    fileNames(group) {
      if (!this.files[group].length) return 'Chưa chọn file';
      return this.files[group].map((file) => file.name).join(', ');
    },
    async submit() {
      if (!this.validateStep(5)) return;
      if (!this.confirmed) {
        this.formError = 'Vui lòng xác nhận nội dung đơn trước khi gửi.';
        return;
      }

      this.submitting = true;
      this.formError = '';
      try {
        const formData = new FormData();
        Object.entries(this.form).forEach(([key, value]) => {
          if (key === 'courts' || key === 'amenities') {
            formData.append(key, JSON.stringify(value));
          } else if (value !== null && value !== undefined) {
            formData.append(key, value);
          }
        });
        formData.append('confirmed', '1');
        this.files.identity.forEach((file) => formData.append('identity_documents[]', file));
        this.files.business_license.forEach((file) => formData.append('business_license_documents[]', file));
        this.files.facility.forEach((file) => formData.append('facility_images[]', file));

        await apiFormData('/api/user/partner-application', formData);
        this.success = 'Đã gửi hồ sơ đăng ký đối tác. SportGo sẽ kiểm tra và phản hồi trong thời gian sớm nhất.';
        this.formOpen = false;
        this.form = this.defaultForm(this.user);
        this.files = blankFiles();
        this.confirmed = false;
        await this.loadApplications();
      } catch (error) {
        this.formError = error.message;
      } finally {
        this.submitting = false;
      }
    },
    async cancelApplication(application) {
      if (!window.confirm(`Hủy hồ sơ đăng ký cho ${application.venue_name}?`)) return;
      try {
        await api(`/api/user/partner-application/${application.id}/cancel`, {
          method: 'POST',
          body: JSON.stringify({ reason: 'Người dùng hủy từ màn đăng ký đối tác.' }),
        });
        this.success = 'Đã hủy hồ sơ đăng ký.';
        await this.loadApplications();
      } catch (error) {
        this.error = error.message;
      }
    },
    canCancel(application) {
      return ['pending', 'submitted', 'reviewing', 'need_supplement'].includes(application.status);
    },
    statusLabel(status) {
      return {
        pending: 'Chờ duyệt',
        submitted: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        need_supplement: 'Cần bổ sung',
        contract_pending_owner_signature: 'Chờ ký hợp đồng',
        contract_pending_sportgo_signature: 'Chờ SportGo ký',
        completed: 'Đang hoạt động',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      }[status] || status || '-';
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
.partner-portal {
  min-height: 100vh;
  background: #f8fafc;
}

.portal-shell {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 96px 0 56px;
}

.page-head,
.section-head,
.form-actions,
.row-title,
.verify-line,
.court-toolbar,
.map-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.page-head {
  margin-bottom: 22px;
}

.eyebrow {
  margin: 0 0 6px;
  color: #15803d;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

h1,
h2,
h3,
p {
  margin-top: 0;
}

h1 {
  margin-bottom: 8px;
  color: #0f172a;
  font-size: 32px;
}

h2 {
  margin-bottom: 12px;
  color: #0f172a;
  font-size: 20px;
}

.page-head p,
.section-head p,
.hint,
.state,
.application-row p,
.application-row small,
.upload-box small {
  color: #64748b;
}

.history-section,
.application-form,
.form-section {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.history-section {
  padding: 20px;
}

.application-form {
  margin-top: 20px;
  overflow: hidden;
}

.form-section {
  border: 0;
  border-top: 1px solid #e2e8f0;
  border-radius: 0;
  padding: 22px;
}

.application-list,
.court-list {
  display: grid;
  gap: 12px;
}

.application-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

.status-pill {
  border-radius: 999px;
  padding: 4px 9px;
  background: #e2e8f0;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}

.status-pill.rejected,
.status-pill.cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.status-pill.completed {
  background: #dcfce7;
  color: #166534;
}

.step-tabs {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 1px;
  background: #e2e8f0;
}

.step-tabs button {
  min-height: 64px;
  border: 0;
  background: #fff;
  color: #64748b;
  font-weight: 800;
  cursor: pointer;
}

.step-tabs button.active {
  background: #ecfdf5;
  color: #166534;
}

.step-tabs span {
  display: inline-grid;
  place-items: center;
  width: 24px;
  height: 24px;
  margin-right: 6px;
  border-radius: 50%;
  background: #e2e8f0;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

label {
  display: grid;
  gap: 7px;
  color: #0f172a;
  font-size: 13px;
  font-weight: 900;
}

.full {
  grid-column: 1 / -1;
}

input,
select,
textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  color: #0f172a;
  font: inherit;
  font-weight: 500;
}

textarea {
  resize: vertical;
}

.map-row {
  align-items: end;
}

.map-row label {
  flex: 1;
}

.amenity-box {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.amenity-box > span {
  width: 100%;
  font-weight: 900;
}

.check-chip,
.confirm-line {
  display: flex;
  align-items: center;
  gap: 8px;
}

.check-chip {
  width: auto;
  padding: 8px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f8fafc;
}

.check-chip input,
.confirm-line input {
  width: auto;
}

.verify-status {
  color: #475569;
  font-weight: 800;
}

.verify-status.verified {
  color: #15803d;
}

.verify-status.name_mismatch,
.verify-status.not_found,
.verify-status.invalid_bank,
.verify-status.invalid_account_number,
.verify-status.error {
  color: #b91c1c;
}

.court-toolbar {
  justify-content: flex-start;
  align-items: end;
  margin-bottom: 14px;
}

.court-row {
  display: grid;
  grid-template-columns: 1fr 1fr 180px 40px;
  align-items: end;
  gap: 12px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.upload-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.upload-box {
  min-height: 180px;
  align-content: start;
  border: 1px dashed #94a3b8;
  border-radius: 8px;
  padding: 16px;
  background: #f8fafc;
}

.upload-box span {
  font-size: 15px;
}

.upload-box strong {
  color: #15803d;
  font-size: 12px;
  word-break: break-word;
}

.preview-paper {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 22px;
  background: #fff;
}

.preview-paper h3 {
  text-align: center;
  text-transform: uppercase;
}

.preview-paper dl {
  display: grid;
  grid-template-columns: 190px 1fr;
  gap: 10px 14px;
}

.preview-paper dt {
  color: #64748b;
  font-weight: 900;
}

.preview-paper dd {
  margin: 0;
}

.form-actions {
  justify-content: flex-end;
  padding: 18px 22px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.primary-btn,
.secondary-btn,
.ghost-btn,
.danger-btn,
.icon-btn {
  border-radius: 8px;
  border: 1px solid transparent;
  padding: 10px 14px;
  font-weight: 900;
  cursor: pointer;
}

.primary-btn {
  background: #16a34a;
  color: #fff;
}

.secondary-btn {
  background: #0f172a;
  color: #fff;
}

.ghost-btn {
  border-color: #cbd5e1;
  background: #fff;
  color: #0f172a;
}

.danger-btn {
  background: #fee2e2;
  color: #991b1b;
}

.icon-btn {
  min-height: 42px;
  padding: 0;
  background: #fee2e2;
  color: #991b1b;
  font-size: 22px;
}

button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.alert {
  padding: 12px 14px;
  border-radius: 8px;
  font-weight: 800;
}

.alert.error {
  background: #fee2e2;
  color: #991b1b;
}

.alert.success {
  background: #dcfce7;
  color: #166534;
}

@media (max-width: 920px) {
  .page-head,
  .section-head,
  .application-row,
  .map-row {
    align-items: stretch;
    flex-direction: column;
  }

  .step-tabs {
    grid-template-columns: repeat(2, 1fr);
  }

  .form-grid,
  .upload-grid,
  .court-row,
  .preview-paper dl {
    grid-template-columns: 1fr;
  }
}
</style>

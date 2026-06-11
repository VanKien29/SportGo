<template>
  <section class="pricing-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">LỊCH SÂN VÀ DOANH THU</p>
        <h2>Cấu hình giá</h2>
        <p>Quản lý giá theo loại sân, ngày trong tuần, khung giờ và ngày đặc biệt.</p>
      </div>
      <label class="cluster-select">
        <span>Cụm sân</span>
        <select v-model="selectedClusterId" :disabled="isLoading || !clusters.length">
          <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
        </select>
      </label>
    </header>

    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-if="notice" class="alert alert-success">{{ notice }}</div>

    <section class="pricing-card">
      <div class="section-head">
        <div>
          <p class="eyebrow">BẢNG GIÁ</p>
          <h3>Tất cả cấu hình giá</h3>
          <p>Giá ngày đặc biệt được ưu tiên hơn giá theo tuần khi tính booking.</p>
        </div>
        <button class="btn primary" type="button" :disabled="!selectedClusterId || !courtTypes.length" @click="openCreateModal">
          + Thêm cấu hình giá
        </button>
      </div>

      <div class="filters">
        <label>
          Loại sân
          <select v-model="filters.court_type_id">
            <option value="">Tất cả loại sân</option>
            <option v-for="type in courtTypes" :key="type.id" :value="String(type.id)">{{ type.name }}</option>
          </select>
        </label>
        <label>
          Ngày áp dụng
          <select v-model="filters.day">
            <option value="">Tất cả các ngày</option>
            <option v-for="day in days" :key="day.value" :value="String(day.value)">{{ day.fullLabel }}</option>
          </select>
        </label>
        <label>
          Nhóm giá
          <select v-model="filters.kind">
            <option value="">Tất cả</option>
            <option value="weekly">Giá theo tuần</option>
            <option value="special">Ngày lễ / đặc biệt</option>
          </select>
        </label>
        <label>
          Loại booking
          <select v-model="filters.booking_type">
            <option value="">Tất cả</option>
            <option value="all">Dùng chung</option>
            <option value="single">Đặt lẻ</option>
            <option value="recurring">Đặt cố định</option>
          </select>
        </label>
        <label>
          Trạng thái
          <select v-model="filters.status">
            <option value="">Tất cả</option>
            <option value="active">Đang áp dụng</option>
            <option value="inactive">Đã tắt</option>
          </select>
        </label>
      </div>

      <div v-if="isLoading" class="empty-state">Đang tải cấu hình giá...</div>
      <div v-else-if="loadFailed" class="empty-state load-error">
        <span>Không thể tải dữ liệu cấu hình giá.</span>
        <button class="btn secondary" type="button" @click="loadPricing">Tải lại</button>
      </div>
      <div v-else-if="!filteredRows.length" class="empty-state no-results">
        <span>Không có cấu hình giá phù hợp.</span>
        <button v-if="hasActiveFilters" class="btn secondary" type="button" @click="resetFilters">
          Xóa bộ lọc
        </button>
      </div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nhóm giá</th>
              <th>Loại sân</th>
              <th>Ngày áp dụng</th>
              <th>Khung giờ</th>
              <th>Loại booking</th>
              <th>Giá / giờ</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in filteredRows" :key="`${row.kind}-${row.id}`">
              <td>
                <span class="kind-pill" :class="row.kind">{{ kindLabel(row) }}</span>
                <small v-if="row.kind === 'special' && row.note">{{ row.note }}</small>
              </td>
              <td><strong>{{ row.court_type?.name || courtTypeName(row.court_type_id) }}</strong></td>
              <td>
                <strong>{{ applicationLabel(row) }}</strong>
                <small v-if="row.kind === 'special'">{{ dateTypeLabel(row.date_type) }}</small>
              </td>
              <td><span class="time-pill">{{ time(row.start_time) }} - {{ time(row.end_time) }}</span></td>
              <td>{{ bookingTypeLabel(row.booking_type) }}</td>
              <td class="price">{{ money(row.price) }}</td>
              <td>
                <button
                  class="switch"
                  :class="{ on: row.is_active }"
                  type="button"
                  :aria-pressed="row.is_active"
                  :title="row.is_active ? 'Tắt giá' : 'Bật giá'"
                  @click="toggleRow(row)"
                >
                  <span></span>
                </button>
              </td>
              <td>
                <div class="actions">
                  <button type="button" @click="openEditModal(row)">Sửa</button>
                  <button class="danger" type="button" @click="deleteRow(row)">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="price-modal" @submit.prevent="savePrice">
        <header class="modal-head">
          <div>
            <p class="eyebrow">{{ editingRow ? 'CHỈNH SỬA' : 'THÊM MỚI' }}</p>
            <h3>{{ editingRow ? 'Cập nhật cấu hình giá' : 'Thêm cấu hình giá' }}</h3>
          </div>
          <button type="button" @click="closeModal">Đóng</button>
        </header>

        <div class="form-grid">
          <label>
            Nhóm giá
            <select v-model="form.kind" :disabled="Boolean(editingRow)" required>
              <option value="weekly">Giá theo tuần</option>
              <option value="special">Ngày lễ / đặc biệt</option>
            </select>
          </label>
          <label>
            Loại sân
            <select v-model.number="form.court_type_id" required>
              <option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </label>
        </div>

        <template v-if="form.kind === 'weekly'">
          <label>Ngày trong tuần</label>
          <div class="day-grid">
            <label v-for="day in days" :key="day.value" :class="{ selected: form.apply_to_days.includes(day.value) }">
              <input v-model="form.apply_to_days" type="checkbox" :value="day.value">
              <span>{{ day.label }}</span>
            </label>
          </div>
        </template>

        <div v-else class="form-grid">
          <label>
            Loại ngày
            <select v-model="form.date_type" required>
              <option value="holiday">Ngày lễ</option>
              <option value="special_date">Ngày đặc biệt</option>
            </select>
          </label>
          <label>
            Ngày áp dụng
            <input v-model="form.holiday_date" type="date" required>
          </label>
        </div>

        <div class="form-grid">
          <label>
            Giờ bắt đầu
            <input v-model="form.start_time" type="time" required>
          </label>
          <label>
            Giờ kết thúc
            <input v-model="form.end_time" type="time" required>
          </label>
        </div>

        <div class="form-grid">
          <label>
            Loại booking
            <select v-model="form.booking_type" required>
              <option value="all">Dùng chung</option>
              <option value="single">Đặt lẻ</option>
              <option value="recurring">Đặt cố định</option>
            </select>
          </label>
          <label>
            Giá / giờ
            <input v-model.number="form.price" type="number" min="0" step="1000" required>
          </label>
        </div>

        <label v-if="form.kind === 'special'">
          Ghi chú
          <input v-model.trim="form.note" type="text" maxlength="255" placeholder="Ví dụ: Giá Tết Dương lịch">
        </label>

        <label class="active-row">
          <input v-model="form.is_active" type="checkbox">
          <span>Áp dụng ngay sau khi lưu</span>
        </label>

        <p class="form-note">
          Khung giá đang bật không được chồng giờ với cấu hình cùng loại sân, ngày áp dụng và loại booking.
        </p>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="isSavingPrice">
            {{ isSavingPrice ? 'Đang lưu...' : 'Lưu cấu hình' }}
          </button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import { api } from '../../services/api.js';

export default {
  name: 'OwnerPricing',
  data() {
    return {
      clusters: [],
      courtTypesByCluster: {},
      priceSlots: [],
      holidayPrices: [],
      selectedClusterId: localStorage.getItem('selected_cluster') || '',
      isLoading: true,
      isSavingPrice: false,
      loadFailed: false,
      error: '',
      notice: '',
      showModal: false,
      editingRow: null,
      filters: { court_type_id: '', day: '', kind: '', booking_type: '', status: '' },
      form: this.defaultForm(),
      days: [
        { value: 1, label: 'T2', fullLabel: 'Thứ 2' },
        { value: 2, label: 'T3', fullLabel: 'Thứ 3' },
        { value: 3, label: 'T4', fullLabel: 'Thứ 4' },
        { value: 4, label: 'T5', fullLabel: 'Thứ 5' },
        { value: 5, label: 'T6', fullLabel: 'Thứ 6' },
        { value: 6, label: 'T7', fullLabel: 'Thứ 7' },
        { value: 7, label: 'CN', fullLabel: 'Chủ nhật' },
      ],
    };
  },
  computed: {
    selectedCluster() {
      return this.clusters.find((cluster) => cluster.id === this.selectedClusterId) || null;
    },
    courtTypes() {
      return this.courtTypesByCluster[this.selectedClusterId] || [];
    },
    rows() {
      const weekly = this.priceSlots
        .filter((row) => row.venue_cluster_id === this.selectedClusterId)
        .map((row) => ({ ...row, kind: 'weekly' }));
      const special = this.holidayPrices
        .filter((row) => row.venue_cluster_id === this.selectedClusterId)
        .map((row) => ({ ...row, kind: 'special' }));

      return [...special, ...weekly];
    },
    filteredRows() {
      return this.rows.filter((row) => {
        if (this.filters.court_type_id && String(row.court_type_id) !== this.filters.court_type_id) return false;
        if (this.filters.kind && row.kind !== this.filters.kind) return false;
        if (this.filters.booking_type && row.booking_type !== this.filters.booking_type) return false;
        if (this.filters.status === 'active' && !row.is_active) return false;
        if (this.filters.status === 'inactive' && row.is_active) return false;
        if (this.filters.day) {
          const day = Number(this.filters.day);
          if (row.kind === 'weekly' && !this.normalizeDays(row.apply_to_days).includes(day)) return false;
          if (row.kind === 'special' && this.dayOfWeek(row.holiday_date) !== day) return false;
        }
        return true;
      });
    },
    hasActiveFilters() {
      return Object.values(this.filters).some(Boolean);
    },
  },
  watch: {
    selectedClusterId(value) {
      if (value) localStorage.setItem('selected_cluster', value);
      this.filters.court_type_id = '';
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChanged);
    await this.loadPricing();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChanged);
  },
  methods: {
    defaultForm() {
      return {
        kind: 'weekly',
        court_type_id: null,
        apply_to_days: [1, 2, 3, 4, 5],
        date_type: 'holiday',
        holiday_date: new Date().toISOString().split('T')[0],
        start_time: '06:00',
        end_time: '17:00',
        booking_type: 'all',
        price: 0,
        note: '',
        is_active: true,
      };
    },
    async handleClusterChanged(event) {
      this.selectedClusterId = event.detail?.id || localStorage.getItem('selected_cluster') || '';
    },
    async loadPricing() {
      this.isLoading = true;
      this.loadFailed = false;
      this.error = '';
      const controller = new AbortController();
      const timeout = window.setTimeout(() => controller.abort(), 15000);
      try {
        const data = await api('/api/owner/pricing', { signal: controller.signal });
        this.clusters = data.clusters || [];
        this.courtTypesByCluster = data.court_types_by_cluster || {};
        this.priceSlots = data.price_slots || [];
        this.holidayPrices = data.holiday_prices || [];
        if (!this.clusters.some((cluster) => cluster.id === this.selectedClusterId)) {
          this.selectedClusterId = this.clusters[0]?.id || '';
        }
      } catch (error) {
        this.loadFailed = true;
        this.error = error.name === 'AbortError'
          ? 'Tải cấu hình giá quá lâu. Vui lòng kiểm tra kết nối và thử lại.'
          : (error.message || 'Không thể tải cấu hình giá.');
      } finally {
        window.clearTimeout(timeout);
        this.isLoading = false;
      }
    },
    openCreateModal() {
      this.clearMessages();
      this.editingRow = null;
      this.form = { ...this.defaultForm(), court_type_id: this.courtTypes[0]?.id || null };
      this.showModal = true;
    },
    openEditModal(row) {
      this.clearMessages();
      this.editingRow = row;
      this.form = {
        ...this.defaultForm(),
        kind: row.kind,
        court_type_id: row.court_type_id,
        apply_to_days: this.normalizeDays(row.apply_to_days),
        date_type: row.date_type || 'holiday',
        holiday_date: this.dateOnly(row.holiday_date) || new Date().toISOString().split('T')[0],
        start_time: this.time(row.start_time),
        end_time: this.time(row.end_time),
        booking_type: row.booking_type,
        price: Number(row.price),
        note: row.note || '',
        is_active: Boolean(row.is_active),
      };
      this.showModal = true;
    },
    closeModal() {
      if (this.isSavingPrice) return;
      this.showModal = false;
      this.editingRow = null;
    },
    async savePrice() {
      this.clearMessages();
      if (this.form.kind === 'weekly' && !this.form.apply_to_days.length) {
        this.error = 'Vui lòng chọn ít nhất một ngày trong tuần.';
        return;
      }

      this.isSavingPrice = true;
      const isWeekly = this.form.kind === 'weekly';
      const basePath = isWeekly ? '/api/owner/price-slots' : '/api/owner/holiday-prices';
      const path = this.editingRow ? `${basePath}/${this.editingRow.id}` : basePath;
      const payload = isWeekly
        ? {
            venue_cluster_id: this.selectedClusterId,
            court_type_id: this.form.court_type_id,
            apply_to_days: this.normalizeDays(this.form.apply_to_days),
            start_time: this.form.start_time,
            end_time: this.form.end_time,
            booking_type: this.form.booking_type,
            price: this.form.price,
            is_active: this.form.is_active,
          }
        : {
            venue_cluster_id: this.selectedClusterId,
            court_type_id: this.form.court_type_id,
            date_type: this.form.date_type,
            holiday_date: this.form.holiday_date,
            start_time: this.form.start_time,
            end_time: this.form.end_time,
            booking_type: this.form.booking_type,
            price: this.form.price,
            note: this.form.note || null,
            is_active: this.form.is_active,
          };

      try {
        const saved = await api(path, {
          method: this.editingRow ? 'PATCH' : 'POST',
          body: JSON.stringify(payload),
        });
        this.replaceRow(isWeekly ? 'weekly' : 'special', saved);
        this.notice = 'Đã lưu cấu hình giá.';
        this.showModal = false;
        this.editingRow = null;
      } catch (error) {
        this.error = error.message || 'Không thể lưu cấu hình giá.';
      } finally {
        this.isSavingPrice = false;
      }
    },
    async toggleRow(row) {
      this.clearMessages();
      const basePath = row.kind === 'weekly' ? '/api/owner/price-slots' : '/api/owner/holiday-prices';
      try {
        const saved = await api(`${basePath}/${row.id}`, {
          method: 'PATCH',
          body: JSON.stringify({ is_active: !row.is_active }),
        });
        this.replaceRow(row.kind, saved);
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật trạng thái giá.';
      }
    },
    async deleteRow(row) {
      if (!window.confirm(`Xóa cấu hình ${this.kindLabel(row).toLowerCase()} này?`)) return;
      this.clearMessages();
      const basePath = row.kind === 'weekly' ? '/api/owner/price-slots' : '/api/owner/holiday-prices';
      try {
        await api(`${basePath}/${row.id}`, { method: 'DELETE' });
        if (row.kind === 'weekly') {
          this.priceSlots = this.priceSlots.filter((item) => item.id !== row.id);
        } else {
          this.holidayPrices = this.holidayPrices.filter((item) => item.id !== row.id);
        }
        this.notice = 'Đã xóa cấu hình giá.';
      } catch (error) {
        this.error = error.message || 'Không thể xóa cấu hình giá.';
      }
    },
    replaceRow(kind, saved) {
      const key = kind === 'weekly' ? 'priceSlots' : 'holidayPrices';
      const exists = this[key].some((item) => item.id === saved.id);
      this[key] = exists
        ? this[key].map((item) => (item.id === saved.id ? saved : item))
        : [saved, ...this[key]];
    },
    kindLabel(row) {
      if (row.kind === 'weekly') return 'Giá theo tuần';
      return row.date_type === 'holiday' ? 'Ngày lễ' : 'Ngày đặc biệt';
    },
    dateTypeLabel(type) {
      return type === 'holiday' ? 'Ngày lễ' : 'Ngày đặc biệt';
    },
    applicationLabel(row) {
      return row.kind === 'weekly' ? this.formatDays(row.apply_to_days) : this.formatDate(row.holiday_date);
    },
    bookingTypeLabel(type) {
      return { all: 'Dùng chung', single: 'Đặt lẻ', recurring: 'Đặt cố định' }[type] || type;
    },
    courtTypeName(id) {
      return this.courtTypes.find((type) => type.id === Number(id))?.name || 'Chưa rõ';
    },
    normalizeDays(days) {
      return [...new Set((days || []).map((day) => (Number(day) === 0 ? 7 : Number(day))))].sort((a, b) => a - b);
    },
    formatDays(days) {
      const values = this.normalizeDays(days);
      if (values.join(',') === '1,2,3,4,5') return 'Thứ 2 - Thứ 6';
      if (values.join(',') === '6,7') return 'Thứ 7, Chủ nhật';
      if (values.length === 7) return 'Tất cả các ngày';
      const labels = Object.fromEntries(this.days.map((day) => [day.value, day.fullLabel]));
      return values.map((day) => labels[day]).join(', ');
    },
    dayOfWeek(value) {
      const day = new Date(`${this.dateOnly(value)}T00:00:00`).getDay();
      return day === 0 ? 7 : day;
    },
    formatDate(value) {
      const date = new Date(`${this.dateOnly(value)}T00:00:00`);
      return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('vi-VN').format(date);
    },
    dateOnly(value) {
      return String(value || '').slice(0, 10);
    },
    time(value) {
      return (value || '').slice(0, 5);
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    },
    clearMessages() {
      this.error = '';
      this.notice = '';
    },
    resetFilters() {
      this.filters = { court_type_id: '', day: '', kind: '', booking_type: '', status: '' };
    },
  },
};
</script>

<style src="../../../css/owner/pricing.css" scoped></style>
<style scoped>
.load-error {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #991b1b;
}

.no-results {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}
</style>

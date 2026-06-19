<template>
  <section class="pricing-page">
    <header class="page-head">
      <div>
        <h2>Cấu hình giá</h2>
      </div>
      <label class="cluster-select">
        <span>Cụm sân</span>
        <select v-model="selectedClusterId" :disabled="isLoading || !clusters.length">
          <option v-if="!clusters.length" value="">Chưa có cụm sân</option>
          <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
        </select>
      </label>
    </header>

    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-if="notice" class="alert alert-success">{{ notice }}</div>

    <section class="base-price-card">
      <div class="section-head compact">
        <h3>Giá chung</h3>
      </div>

      <div v-if="isLoading" class="empty-state compact-state">Đang tải...</div>
      <div v-else-if="!selectedClusterId" class="empty-state compact-state">Chưa có cụm sân</div>
      <div v-else-if="!courtTypes.length" class="empty-state compact-state">Chưa có loại sân</div>
      <div v-else class="base-price-grid">
        <div v-for="type in courtTypes" :key="type.id" class="base-price-row">
          <strong>{{ type.name }}</strong>
          <label class="money-input">
            <input
              v-model.number="basePriceDrafts[type.id]"
              type="number"
              min="0"
              step="1000"
              :disabled="savingBasePriceId === type.id"
            >
            <span>đ / giờ</span>
          </label>
          <button
            class="btn primary"
            type="button"
            :disabled="savingBasePriceId === type.id || !isValidBasePrice(basePriceDrafts[type.id])"
            @click="saveBasePrice(type)"
          >
            {{ savingBasePriceId === type.id ? 'Đang lưu...' : 'Lưu' }}
          </button>
        </div>
      </div>
    </section>

    <section class="pricing-card">
      <nav class="price-tabs" aria-label="Nhóm cấu hình giá">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          :class="{ active: activeTab === tab.value }"
          @click="selectTab(tab.value)"
        >
          <span>{{ tab.label }}</span>
          <small>{{ tabCount(tab.value) }}</small>
        </button>
      </nav>

      <div class="table-toolbar">
        <h3>{{ activeTabMeta.title }}</h3>
        <button class="btn primary" type="button" :disabled="!selectedClusterId || !courtTypes.length" @click="openCreateModal">
          + {{ activeTabMeta.addLabel }}
        </button>
      </div>

      <div class="filters" :class="{ weekly: activeTab === 'weekly' }">
        <label>
          Loại sân
          <select v-model="filters.court_type_id">
            <option value="">Tất cả loại sân</option>
            <option v-for="type in courtTypes" :key="type.id" :value="String(type.id)">{{ type.name }}</option>
          </select>
        </label>
        <label v-if="activeTab === 'weekly'">
          Ngày áp dụng
          <select v-model="filters.day">
            <option value="">Tất cả các ngày</option>
            <option v-for="day in days" :key="day.value" :value="String(day.value)">{{ day.fullLabel }}</option>
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
        <span>{{ hasActiveFilters ? 'Không có kết quả' : 'Chưa có dữ liệu' }}</span>
        <button v-if="hasActiveFilters" class="btn secondary" type="button" @click="resetFilters">Xóa bộ lọc</button>
      </div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Loại sân</th>
              <th>{{ activeTab === 'weekly' ? 'Ngày trong tuần' : 'Ngày áp dụng' }}</th>
              <th>Khung giờ</th>
              <th>Loại booking</th>
              <th>Giá / giờ</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in filteredRows" :key="row.id">
              <td>
                <strong>{{ row.court_type?.name || courtTypeName(row.court_type_id) }}</strong>
                <small v-if="row.note">{{ row.note }}</small>
              </td>
              <td>
                <strong>{{ applicationLabel(row) }}</strong>
                <small v-if="activeTab !== 'weekly'">{{ activeTabMeta.label }}</small>
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
                <TableActionGroup>
                  <ActionIconButton icon="pencil" label="Sửa cấu hình giá" @click="openEditModal(row)" />
                  <ActionIconButton icon="trash" label="Xóa cấu hình giá" variant="danger" @click="deleteRow(row)" />
                </TableActionGroup>
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
            <h3>{{ editingRow ? `Cập nhật ${activeTabMeta.label.toLowerCase()}` : activeTabMeta.addLabel }}</h3>
          </div>
          <button type="button" @click="closeModal">Đóng</button>
        </header>

        <div class="form-grid">
          <label>
            Loại sân
            <select v-model.number="form.court_type_id" required>
              <option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </label>
          <label>
            Loại booking
            <select v-model="form.booking_type" required>
              <option value="all">Dùng chung</option>
              <option value="single">Đặt lẻ</option>
              <option value="recurring">Đặt cố định</option>
            </select>
          </label>
        </div>

        <template v-if="activeTab === 'weekly'">
          <label>Ngày trong tuần</label>
          <div class="day-grid">
            <label v-for="day in days" :key="day.value" :class="{ selected: form.apply_to_days.includes(day.value) }">
              <input v-model="form.apply_to_days" type="checkbox" :value="day.value">
              <span>{{ day.label }}</span>
            </label>
          </div>
        </template>

        <label v-else>
          Ngày áp dụng
          <input v-model="form.holiday_date" type="date" required>
        </label>

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
            Giá / giờ
            <input v-model.number="form.price" type="number" min="0" step="1000" required>
          </label>
          <label v-if="activeTab !== 'weekly'">
            Ghi chú
            <input v-model.trim="form.note" type="text" maxlength="255" :placeholder="activeTabMeta.notePlaceholder">
          </label>
        </div>

        <label class="active-row">
          <input v-model="form.is_active" type="checkbox">
          <span>Áp dụng ngay sau khi lưu</span>
        </label>

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
import ActionIconButton from '../../components/ActionIconButton.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerPricing',
  components: { ActionIconButton, TableActionGroup },
  data() {
    return {
      clusters: [],
      courtTypesByCluster: {},
      basePrices: [],
      basePriceDrafts: {},
      systemDefaultPrice: 10000,
      priceSlots: [],
      holidayPrices: [],
      selectedClusterId: localStorage.getItem('selected_cluster') || '',
      activeTab: 'weekly',
      isLoading: true,
      isSavingPrice: false,
      savingBasePriceId: null,
      loadFailed: false,
      error: '',
      notice: '',
      showModal: false,
      editingRow: null,
      filters: { court_type_id: '', day: '', booking_type: '', status: '' },
      tabs: [
        { value: 'weekly', label: 'Giá ngày thường' },
        { value: 'holiday', label: 'Giá ngày lễ' },
        { value: 'special_date', label: 'Giá ngày đặc biệt' },
      ],
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
    activeTabMeta() {
      return {
        weekly: {
          label: 'Giá ngày thường',
          eyebrow: 'LỊCH GIÁ HẰNG TUẦN',
          title: 'Bảng giá ngày thường',
          description: 'Cấu hình theo thứ và khung giờ. Khoảng trống sẽ lấy giá chung.',
          addLabel: 'Thêm giá ngày thường',
          empty: 'Chưa có khung giá ngày thường. Hệ thống đang dùng giá chung.',
          notePlaceholder: '',
        },
        holiday: {
          label: 'Giá ngày lễ',
          eyebrow: 'LỊCH NGÀY LỄ',
          title: 'Bảng giá ngày lễ',
          description: 'Chỉ khung giờ được cấu hình mới dùng giá lễ; giờ còn lại lấy giá ngày thường.',
          addLabel: 'Thêm giá ngày lễ',
          empty: 'Chưa có giá ngày lễ. Các ngày này sẽ dùng giá ngày thường.',
          notePlaceholder: 'Ví dụ: Tết Dương lịch',
        },
        special_date: {
          label: 'Giá ngày đặc biệt',
          eyebrow: 'LỊCH RIÊNG',
          title: 'Bảng giá ngày đặc biệt',
          description: 'Dùng cho giải đấu, sự kiện hoặc ngày có mức giá riêng.',
          addLabel: 'Thêm ngày đặc biệt',
          empty: 'Chưa có ngày đặc biệt.',
          notePlaceholder: 'Ví dụ: Giải đấu nội bộ',
        },
      }[this.activeTab];
    },
    rows() {
      if (this.activeTab === 'weekly') {
        return this.priceSlots.filter((row) => row.venue_cluster_id === this.selectedClusterId);
      }
      return this.holidayPrices.filter((row) => (
        row.venue_cluster_id === this.selectedClusterId
        && row.date_type === this.activeTab
      ));
    },
    filteredRows() {
      return this.rows.filter((row) => {
        if (this.filters.court_type_id && String(row.court_type_id) !== this.filters.court_type_id) return false;
        if (this.filters.booking_type && row.booking_type !== this.filters.booking_type) return false;
        if (this.filters.status === 'active' && !row.is_active) return false;
        if (this.filters.status === 'inactive' && row.is_active) return false;
        if (this.activeTab === 'weekly' && this.filters.day) {
          if (!this.normalizeDays(row.apply_to_days).includes(Number(this.filters.day))) return false;
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
      this.resetFilters();
      this.syncBasePriceDrafts();
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
        court_type_id: null,
        apply_to_days: [1, 2, 3, 4, 5],
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
        this.basePrices = data.base_prices || [];
        this.systemDefaultPrice = Number(data.system_default_price || 10000);
        this.priceSlots = data.price_slots || [];
        this.holidayPrices = data.holiday_prices || [];
        if (!this.clusters.some((cluster) => cluster.id === this.selectedClusterId)) {
          this.selectedClusterId = this.clusters[0]?.id || '';
        }
        this.syncBasePriceDrafts();
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
    selectTab(tab) {
      this.activeTab = tab;
      this.resetFilters();
      this.clearMessages();
    },
    tabCount(tab) {
      if (tab === 'weekly') {
        return this.priceSlots.filter((row) => row.venue_cluster_id === this.selectedClusterId).length;
      }
      return this.holidayPrices.filter((row) => (
        row.venue_cluster_id === this.selectedClusterId && row.date_type === tab
      )).length;
    },
    basePriceRecord(courtTypeId) {
      return this.basePrices.find((row) => (
        row.venue_cluster_id === this.selectedClusterId
        && Number(row.court_type_id) === Number(courtTypeId)
      )) || null;
    },
    hasSavedBasePrice(courtTypeId) {
      return Boolean(this.basePriceRecord(courtTypeId));
    },
    syncBasePriceDrafts() {
      this.basePriceDrafts = Object.fromEntries(this.courtTypes.map((type) => [
        type.id,
        Number(this.basePriceRecord(type.id)?.price ?? this.systemDefaultPrice),
      ]));
    },
    isValidBasePrice(value) {
      return Number.isFinite(Number(value)) && Number(value) >= 0;
    },
    async saveBasePrice(type) {
      if (!this.isValidBasePrice(this.basePriceDrafts[type.id])) return;
      this.clearMessages();
      this.savingBasePriceId = type.id;
      try {
        const saved = await api(`/api/owner/base-prices/${type.id}`, {
          method: 'PUT',
          body: JSON.stringify({
            venue_cluster_id: this.selectedClusterId,
            price: Number(this.basePriceDrafts[type.id]),
          }),
        });
        const exists = this.basePrices.some((item) => item.id === saved.id);
        this.basePrices = exists
          ? this.basePrices.map((item) => (item.id === saved.id ? saved : item))
          : [saved, ...this.basePrices];
        this.basePriceDrafts[type.id] = Number(saved.price);
        this.notice = `Đã lưu giá chung cho ${type.name}.`;
      } catch (error) {
        this.error = error.message || 'Không thể lưu giá chung.';
      } finally {
        this.savingBasePriceId = null;
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
        court_type_id: row.court_type_id,
        apply_to_days: this.normalizeDays(row.apply_to_days),
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
      if (this.activeTab === 'weekly' && !this.form.apply_to_days.length) {
        this.error = 'Vui lòng chọn ít nhất một ngày trong tuần.';
        return;
      }
      if (this.form.start_time >= this.form.end_time) {
        this.error = 'Giờ kết thúc phải sau giờ bắt đầu.';
        return;
      }

      this.isSavingPrice = true;
      const isWeekly = this.activeTab === 'weekly';
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
            date_type: this.activeTab,
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
        this.replaceRow(isWeekly, saved);
        this.notice = `Đã lưu ${this.activeTabMeta.label.toLowerCase()}.`;
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
      const isWeekly = this.activeTab === 'weekly';
      const basePath = isWeekly ? '/api/owner/price-slots' : '/api/owner/holiday-prices';
      try {
        const saved = await api(`${basePath}/${row.id}`, {
          method: 'PATCH',
          body: JSON.stringify({ is_active: !row.is_active }),
        });
        this.replaceRow(isWeekly, saved);
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật trạng thái giá.';
      }
    },
    async deleteRow(row) {
      if (!window.confirm(`Xóa ${this.activeTabMeta.label.toLowerCase()} này?`)) return;
      this.clearMessages();
      const isWeekly = this.activeTab === 'weekly';
      const basePath = isWeekly ? '/api/owner/price-slots' : '/api/owner/holiday-prices';
      try {
        await api(`${basePath}/${row.id}`, { method: 'DELETE' });
        if (isWeekly) {
          this.priceSlots = this.priceSlots.filter((item) => item.id !== row.id);
        } else {
          this.holidayPrices = this.holidayPrices.filter((item) => item.id !== row.id);
        }
        this.notice = 'Đã xóa cấu hình giá.';
      } catch (error) {
        this.error = error.message || 'Không thể xóa cấu hình giá.';
      }
    },
    replaceRow(isWeekly, saved) {
      const key = isWeekly ? 'priceSlots' : 'holidayPrices';
      const exists = this[key].some((item) => item.id === saved.id);
      this[key] = exists
        ? this[key].map((item) => (item.id === saved.id ? saved : item))
        : [saved, ...this[key]];
    },
    applicationLabel(row) {
      return this.activeTab === 'weekly' ? this.formatDays(row.apply_to_days) : this.formatDate(row.holiday_date);
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
      this.filters = { court_type_id: '', day: '', booking_type: '', status: '' };
    },
  },
};
</script>

<style src="../../../css/owner/pricing.css" scoped></style>

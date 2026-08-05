<template>
  <div class="pricing-master-workspace">

    <!-- Toast Notifications -->
    <Transition name="fade">
      <div v-if="error" class="global-toast alert-error">
        <span>{{ error }}</span>
        <button type="button" class="toast-close-btn" @click="error = ''">✕</button>
      </div>
    </Transition>

    <Transition name="fade">
      <div v-if="notice" class="global-toast alert-success">
        <span>{{ notice }}</span>
        <button type="button" class="toast-close-btn" @click="notice = ''">✕</button>
      </div>
    </Transition>

    <!-- Master Unified Surface Container -->
    <div class="cluster-profile-surface standalone">

      <!-- PART 1: Top Hero Surface -->
      <PricingHeaderHero
        :tabs="tabsForAppTabs"
        :active-tab="activeTab"
        @tab-change="selectTab"
      />

      <!-- PART 2: Single Unified Content Surface Card -->
      <div class="profile-section-card pricing-main-content">
        <!-- Section A: Giá chung (Giá cơ bản) -->
        <PricingBaseSection
          :court-types="courtTypes"
          :base-price-drafts="basePriceDrafts"
          :base-prices="basePrices"
          :saving-base-price-id="savingBasePriceId"
          :is-loading="isLoading"
          :selected-cluster-id="selectedClusterId"
          @update-draft="updateBasePriceDraft"
          @save-base-price="saveBasePrice"
        />

        <!-- Section B: Bảng quy tắc giá nâng cao -->
        <PricingRulesTable
          :active-tab="activeTab"
          :active-tab-meta="activeTabMeta"
          :court-types="courtTypes"
          :days="days"
          :filtered-rows="rows"
          :is-loading="isLoading"
          :load-failed="loadFailed"
          @open-create-modal="openCreateModal"
          @open-edit-modal="openEditModal"
          @toggle-row="toggleRow"
          @delete-row="deleteRow"
        />
      </div>

    </div>

    <!-- Teleported Modal Dialog -->
    <PricingRuleModal
      :show="showModal"
      :editing-row="editingRow"
      :active-tab="activeTab"
      :active-tab-meta="activeTabMeta"
      :court-types="courtTypes"
      :days="days"
      :form="form"
      :is-saving-price="isSavingPrice"
      :error-message="modalError"
      @close="closeModal"
      @save-price="savePrice"
      @update:form="form = $event"
    />
  </div>
</template>

<script>
import PricingBaseSection from '../../components/owner/pricing/PricingBaseSection.vue';
import PricingHeaderHero from '../../components/owner/pricing/PricingHeaderHero.vue';
import PricingRuleModal from '../../components/owner/pricing/PricingRuleModal.vue';
import PricingRulesTable from '../../components/owner/pricing/PricingRulesTable.vue';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerPricing',
  components: {
    PricingHeaderHero,
    PricingBaseSection,
    PricingRulesTable,
    PricingRuleModal,
  },
  data() {
    return {
      clusters: [],
      courtTypesByCluster: {},
      basePrices: [],
      basePriceDrafts: {},
      systemDefaultPrice: 100000,
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
      modalError: '',
      showModal: false,
      editingRow: null,
      tabs: [
        { key: 'weekly', value: 'weekly', label: 'Giá ngày thường' },
        { key: 'holiday', value: 'holiday', label: 'Giá ngày lễ' },
        { key: 'special_date', value: 'special_date', label: 'Giá ngày đặc biệt' },
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
    tabsForAppTabs() {
      return [
        { key: 'weekly', value: 'weekly', label: 'Giá ngày thường' },
        { key: 'holiday', value: 'holiday', label: 'Giá ngày lễ' },
        { key: 'special_date', value: 'special_date', label: 'Giá ngày đặc biệt' },
      ];
    },
    selectedCluster() {
      return this.clusters.find((cluster) => String(cluster.id) === String(this.selectedClusterId)) || null;
    },
    courtTypes() {
      return this.courtTypesByCluster[this.selectedClusterId] || [];
    },
    activeTabMeta() {
      return {
        weekly: {
          label: 'Giá ngày thường',
          eyebrow: 'LỊCH GIÁ HẰNG TUẦN',
          title: 'Bảng quy tắc giá ngày thường',
          description: 'Cấu hình khung giờ theo thứ trong tuần. Các khoảng trống chưa tạo quy tắc sẽ áp dụng Giá chung.',
          addLabel: 'Thêm giá ngày thường',
          empty: 'Chưa có quy tắc giá ngày thường. Tất cả các giờ đang áp dụng Giá chung.',
          notePlaceholder: '',
        },
        holiday: {
          label: 'Giá ngày lễ',
          eyebrow: 'LỊCH NGÀY LỄ',
          title: 'Bảng quy tắc giá ngày lễ',
          description: 'Áp dụng cho các ngày lễ cố định trong năm. Chỉ các khung giờ được cài mới đổi giá, giờ còn lại giữ nguyên.',
          addLabel: 'Thêm giá ngày lễ',
          empty: 'Chưa có giá ngày lễ nào được thiết lập.',
          notePlaceholder: 'Ví dụ: Tết Dương lịch, Giỗ Tổ Hùng Vương',
        },
        special_date: {
          label: 'Giá ngày đặc biệt',
          eyebrow: 'LỊCH RIÊNG',
          title: 'Bảng quy tắc giá ngày đặc biệt',
          description: 'Dành riêng cho ngày có giải đấu, sự kiện hoặc khung giờ cao điểm đột xuất.',
          addLabel: 'Thêm ngày đặc biệt',
          empty: 'Chưa có quy tắc ngày đặc biệt nào được tạo.',
          notePlaceholder: 'Ví dụ: Giải đấu nội bộ CLB',
        },
      }[this.activeTab];
    },
    rows() {
      if (this.activeTab === 'weekly') {
        return this.priceSlots.filter((row) => this.belongsToSelectedCluster(row));
      }
      return this.holidayPrices.filter((row) => (
        this.belongsToSelectedCluster(row)
        && row.date_type === this.activeTab
      ));
    },
  },
  watch: {
    selectedClusterId(newVal) {
      if (newVal) {
        localStorage.setItem('selected_cluster', newVal);
        this.syncBasePriceDrafts();
      }
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    defaultForm() {
      return {
        court_type_id: null,
        apply_to_days: [1, 2, 3, 4, 5],
        holiday_date: new Date().toISOString().split('T')[0],
        start_time: '06:00',
        end_time: '22:00',
        booking_type: 'all',
        price: 100000,
        note: '',
        is_active: true,
      };
    },
    async fetchData() {
      this.isLoading = true;
      this.loadFailed = false;
      this.clearMessages();

      const controller = new AbortController();
      const timeout = window.setTimeout(() => controller.abort(), 15000);

      try {
        const data = await api('/api/owner/pricing-rules', { signal: controller.signal });
        this.clusters = data.clusters || [];
        this.courtTypesByCluster = data.court_types_by_cluster || {};
        this.basePrices = data.base_prices || [];
        this.systemDefaultPrice = Number(data.system_default_price || 100000);
        this.priceSlots = data.price_slots || [];
        this.holidayPrices = data.holiday_prices || [];
        if (!this.clusters.some((cluster) => String(cluster.id) === String(this.selectedClusterId))) {
          this.selectedClusterId = this.clusters[0]?.id ? String(this.clusters[0].id) : '';
        }
        this.syncBasePriceDrafts();
      } catch (error) {
        this.loadFailed = true;
        this.error = error.name === 'AbortError'
          ? 'Tải cấu hình giá quá lâu. Vui lòng kiểm tra kết nối mạng và thử lại.'
          : (error.message || 'Không thể tải cấu hình giá.');
      } finally {
        window.clearTimeout(timeout);
        this.isLoading = false;
      }
    },
    selectTab(tab) {
      this.activeTab = tab;
      this.clearMessages();
    },
    basePriceRecord(courtTypeId) {
      return this.basePrices.find((row) => (
        this.belongsToSelectedCluster(row)
        && Number(row.court_type_id) === Number(courtTypeId)
      )) || null;
    },
    belongsToSelectedCluster(row) {
      return String(row?.venue_cluster_id ?? '') === String(this.selectedClusterId ?? '');
    },
    syncBasePriceDrafts() {
      this.basePriceDrafts = Object.fromEntries(this.courtTypes.map((type) => [
        type.id,
        Number(this.basePriceRecord(type.id)?.price ?? this.systemDefaultPrice),
      ]));
    },
    updateBasePriceDraft({ courtTypeId, price }) {
      this.basePriceDrafts[courtTypeId] = price;
    },
    isValidBasePrice(value) {
      return Number.isFinite(Number(value)) && Number(value) > 0;
    },
    async saveBasePrice(type) {
      this.clearMessages();
      const val = this.basePriceDrafts[type.id];
      if (!this.isValidBasePrice(val)) {
        this.error = 'Giá chung phải là số hợp lệ lớn hơn 0.';
        return;
      }
      this.savingBasePriceId = type.id;
      try {
        const saved = await api(`/api/owner/base-prices/${type.id}`, {
          method: 'PUT',
          body: JSON.stringify({
            venue_cluster_id: this.selectedClusterId,
            price: Number(val),
          }),
        });
        const exists = this.basePrices.some((item) => item.id === saved.id);
        this.basePrices = exists
          ? this.basePrices.map((item) => (item.id === saved.id ? saved : item))
          : [saved, ...this.basePrices];
        this.basePriceDrafts[type.id] = Number(saved.price);
        this.notice = `Đã lưu thành công giá chung cho loại sân "${type.name}".`;
      } catch (error) {
        this.error = error.message || 'Không thể lưu giá chung.';
      } finally {
        this.savingBasePriceId = null;
      }
    },
    openCreateModal() {
      this.clearMessages();
      this.modalError = '';
      this.editingRow = null;
      this.form = { ...this.defaultForm(), court_type_id: this.courtTypes[0]?.id || null };
      this.showModal = true;
    },
    openEditModal(row) {
      this.clearMessages();
      this.modalError = '';
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
      this.modalError = '';
    },
    async savePrice() {
      this.clearMessages();
      this.modalError = '';

      if (this.activeTab === 'weekly' && (!this.form.apply_to_days || !this.form.apply_to_days.length)) {
        this.modalError = 'Vui lòng chọn ít nhất một ngày trong tuần.';
        return;
      }
      if (this.form.start_time >= this.form.end_time) {
        this.modalError = 'Giờ kết thúc phải lớn hơn giờ bắt đầu.';
        return;
      }
      if (!Number.isFinite(Number(this.form.price)) || Number(this.form.price) <= 0) {
        this.modalError = 'Giá / giờ phải là số lớn hơn 0.';
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
        this.notice = `Đã lưu thành công quy tắc ${this.activeTabMeta.label.toLowerCase()}.`;
        this.showModal = false;
        this.editingRow = null;
      } catch (error) {
        this.modalError = error.message || 'Không thể lưu cấu hình giá.';
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
        this.notice = `Đã ${saved.is_active ? 'bật' : 'tắt'} quy tắc giá.`;
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật trạng thái giá.';
      }
    },
    async deleteRow(row) {
      if (!window.confirm(`Bạn có chắc chắn muốn xóa quy tắc ${this.activeTabMeta.label.toLowerCase()} này?`)) return;
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
        this.notice = 'Đã xóa thành công quy tắc giá.';
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
    courtTypeName(id) {
      return this.courtTypes.find((type) => Number(type.id) === Number(id))?.name || 'Chưa rõ';
    },
    normalizeDays(days) {
      if (typeof days === 'string') {
        try { days = JSON.parse(days); } catch (e) { days = []; }
      }
      return [...new Set((days || []).map((day) => (Number(day) === 0 ? 7 : Number(day))))].sort((a, b) => a - b);
    },
    dateOnly(value) {
      return String(value || '').slice(0, 10);
    },
    time(value) {
      return (value || '').slice(0, 5);
    },
    clearMessages() {
      this.error = '';
      this.notice = '';
    },
  },
};
</script>

<style scoped>
.pricing-master-workspace {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cluster-profile-surface.standalone {
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  border: none;
  box-shadow: none;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 0 !important;
}

.pricing-main-content {
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 32px;
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  border: none;
  box-shadow: none;
  margin-top: 0 !important;
}

/* Global Toasts */
.global-toast {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 400;
  box-shadow: var(--admin-shadow-sm, 0 1px 2px rgba(23, 34, 27, 0.06));
}

.global-toast.alert-error {
  background: var(--admin-danger-soft, #fef2f2);
  border: 1px solid var(--admin-danger, #dc2626);
  color: var(--admin-danger-text, #991b1b);
}

.global-toast.alert-success {
  background: var(--admin-success-soft, #e2f6e8);
  border: 1px solid var(--admin-success, #22a653);
  color: var(--admin-success-text, #15733a);
}

.toast-close-btn {
  margin-left: auto;
  background: transparent;
  border: none;
  color: inherit;
  cursor: pointer;
  font-size: 14px;
  opacity: 0.8;
}

.toast-close-btn:hover {
  opacity: 1;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>

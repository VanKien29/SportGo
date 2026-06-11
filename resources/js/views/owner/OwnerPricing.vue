<template>
  <div class="pricing-page">
    <section class="page-head">
      <div>
        <h2>Cấu hình giá</h2>
        <p>Thiết lập thời lượng booking và khung giá cho cụm sân đang chọn.</p>
      </div>
    </section>

    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-if="notice" class="alert alert-success">{{ notice }}</div>

    <section class="duration-card">
      <div>
        <h2>Cấu hình thời lượng booking</h2>
        <p>Mỗi cụm sân có thời lượng tối thiểu từ 30 phút, thời lượng tối đa tùy cấu hình.</p>
      </div>
      <form class="duration-form" @submit.prevent="saveDuration">
        <label>
          <span>Tối thiểu (phút)</span>
          <input v-model.number="durationForm.min_duration_minutes" type="number" min="30" step="30" />
        </label>
        <label>
          <span>Tối đa (phút)</span>
          <input v-model.number="durationForm.max_duration_minutes" type="number" min="30" step="30" placeholder="Không giới hạn" />
        </label>
        <button class="btn btn-primary" type="submit" :disabled="isSavingDuration || !selectedClusterId">
          {{ isSavingDuration ? 'Đang lưu...' : 'Lưu thời lượng' }}
        </button>
      </form>
    </section>

    <div class="tabs">
      <button :class="{ active: activeTab === 'weekday' }" @click="activeTab = 'weekday'">Giá ngày thường</button>
      <button :class="{ active: activeTab === 'holiday' }" @click="activeTab = 'holiday'">Giá ngày lễ / đặc biệt</button>
      <button :class="{ active: activeTab === 'preview' }" @click="activeTab = 'preview'">Xem trước tính giá</button>
    </div>

    <section v-if="activeTab === 'weekday'" class="price-section">
      <div class="section-heading">
        <h2>Danh sách giá theo tuần</h2>
        <button class="btn btn-primary" :disabled="!selectedClusterId || courtTypes.length === 0" @click="openCreateModal">
          <AppIcon name="plus" size="16" />
          <span>Thêm khung giá</span>
        </button>
      </div>

      <div class="table-card">
        <div v-if="isLoading" class="empty-state">Đang tải cấu hình giá...</div>
        <div v-else-if="filteredSlots.length === 0" class="empty-state">Chưa có khung giá ngày thường cho cụm sân này.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Loại sân</th>
              <th>Ngày áp dụng</th>
              <th>Khung giờ</th>
              <th>Loại đặt</th>
              <th>Giá / Giờ</th>
              <th>Trạng thái</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in filteredSlots" :key="slot.id">
              <td class="strong">{{ slot.court_type?.name || courtTypeName(slot.court_type_id) }}</td>
              <td>{{ formatDays(slot.apply_to_days) }}</td>
              <td><span class="time-pill">{{ formatTime(slot.start_time) }} - {{ formatTime(slot.end_time) }}</span></td>
              <td><span class="type-pill">{{ bookingTypeLabel(slot.booking_type) }}</span></td>
              <td class="price">{{ formatCurrency(slot.price) }}</td>
              <td>
                <button class="switch" :class="{ on: slot.is_active }" :aria-pressed="slot.is_active" @click="toggleSlot(slot)">
                  <span></span>
                </button>
              </td>
              <td>
                <TableActionGroup>
                  <ActionIconButton icon="pencil" label="Sửa khung giá" @click="openEditModal(slot)" />
                  <ActionIconButton icon="trash" label="Xóa khung giá" variant="danger" @click="deleteSlot(slot)" />
                </TableActionGroup>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-else-if="activeTab === 'holiday'" class="placeholder-panel">
      <h2>Giá ngày lễ / đặc biệt</h2>
    </section>

    <section v-else class="placeholder-panel">
      <h2>Xem trước tính giá</h2>
    </section>

    <div v-if="showSlotModal" class="modal-backdrop" @click.self="closeSlotModal">
      <form class="slot-modal" @submit.prevent="saveSlot">
        <div class="modal-head">
          <h2>{{ editingSlot ? 'Sửa khung giá' : 'Thêm khung giá mới' }}</h2>
          <button class="btn-close" type="button" title="Đóng" @click="closeSlotModal">Đóng</button>
        </div>

        <label>
          <span>Loại sân</span>
          <select v-model.number="slotForm.court_type_id" required>
            <option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
          </select>
        </label>

        <div class="day-grid">
          <label v-for="day in days" :key="day.value" :class="{ selected: slotForm.apply_to_days.includes(day.value) }">
            <input v-model="slotForm.apply_to_days" type="checkbox" :value="day.value" />
            <span>{{ day.label }}</span>
          </label>
        </div>

        <div class="form-grid">
          <label>
            <span>Bắt đầu</span>
            <input v-model="slotForm.start_time" type="time" required />
          </label>
          <label>
            <span>Kết thúc</span>
            <input v-model="slotForm.end_time" type="time" required />
          </label>
        </div>

        <div class="form-grid">
          <label>
            <span>Loại đặt</span>
            <select v-model="slotForm.booking_type" required>
              <option value="all">Tất cả</option>
              <option value="single">Đặt lẻ</option>
              <option value="recurring">Đặt cố định</option>
            </select>
          </label>
          <label>
            <span>Giá / giờ</span>
            <input v-model.number="slotForm.price" type="number" min="0" step="1000" required />
          </label>
        </div>

        <label class="active-row">
          <input v-model="slotForm.is_active" type="checkbox" />
          <span>Đang áp dụng</span>
        </label>

        <div class="modal-actions">
          <button class="btn btn-secondary" type="button" @click="closeSlotModal">Hủy</button>
          <button class="btn btn-primary" type="submit" :disabled="isSavingSlot">
            {{ isSavingSlot ? 'Đang lưu...' : 'Lưu khung giá' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerPricing',
  components: { ActionIconButton, AppIcon, TableActionGroup },
  data() {
    return {
      clusters: [],
      courtTypesByCluster: {},
      priceSlots: [],
      selectedClusterId: '',
      activeTab: 'weekday',
      isLoading: true,
      isSavingDuration: false,
      isSavingSlot: false,
      error: null,
      notice: null,
      showSlotModal: false,
      editingSlot: null,
      durationForm: {
        min_duration_minutes: 30,
        max_duration_minutes: null,
      },
      slotForm: this.defaultSlotForm(),
      days: [
        { value: 1, label: 'T2' },
        { value: 2, label: 'T3' },
        { value: 3, label: 'T4' },
        { value: 4, label: 'T5' },
        { value: 5, label: 'T6' },
        { value: 6, label: 'T7' },
        { value: 7, label: 'CN' },
      ],
    };
  },
  computed: {
    selectedCluster() {
      return this.clusters.find((cluster) => String(cluster.id) === String(this.selectedClusterId)) || null;
    },
    courtTypes() {
      return this.courtTypesByCluster[this.selectedClusterId] || [];
    },
    filteredSlots() {
      return this.priceSlots.filter((slot) => String(slot.venue_cluster_id) === String(this.selectedClusterId));
    },
  },
  watch: {
    selectedClusterId() {
      this.syncDurationForm();
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.handleOwnerClusterChanged);
    await this.loadPricing();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleOwnerClusterChanged);
  },
  methods: {
    defaultSlotForm() {
      return {
        court_type_id: null,
        apply_to_days: [1, 2, 3, 4, 5],
        start_time: '06:00',
        end_time: '17:00',
        booking_type: 'all',
        price: 0,
        is_active: true,
      };
    },
    async loadPricing() {
      this.isLoading = true;
      this.error = null;

      try {
        const data = await api('/api/owner/pricing');
        this.clusters = data.clusters || [];
        this.courtTypesByCluster = data.court_types_by_cluster || {};
        this.priceSlots = data.price_slots || [];
        const savedClusterId = localStorage.getItem('selected_cluster');
        this.selectedClusterId = this.clusters.some((cluster) => String(cluster.id) === String(savedClusterId))
          ? savedClusterId
          : this.selectedClusterId || this.clusters[0]?.id || '';
        this.syncDurationForm();
      } catch (error) {
        this.error = error.message || 'Không thể tải cấu hình giá.';
      } finally {
        this.isLoading = false;
      }
    },
    syncDurationForm() {
      const config = this.selectedCluster?.booking_config || {};
      this.durationForm = {
        min_duration_minutes: config.min_duration_minutes ?? 30,
        max_duration_minutes: config.max_duration_minutes ?? null,
      };
    },
    handleOwnerClusterChanged(event) {
      const clusterId = event.detail?.id;
      if (!clusterId || String(clusterId) === String(this.selectedClusterId)) return;
      this.selectedClusterId = clusterId;
      this.syncDurationForm();
    },
    async saveDuration() {
      if (!this.selectedClusterId) return;
      this.clearMessages();
      this.isSavingDuration = true;

      try {
        const payload = {
          min_duration_minutes: Number(this.durationForm.min_duration_minutes || 30),
          max_duration_minutes: this.durationForm.max_duration_minutes ? Number(this.durationForm.max_duration_minutes) : null,
        };
        const config = await api(`/api/owner/booking-configs/${this.selectedClusterId}/duration`, {
          method: 'PATCH',
          body: JSON.stringify(payload),
        });

        const cluster = this.clusters.find((item) => String(item.id) === String(this.selectedClusterId));
        if (cluster) cluster.booking_config = config;
        this.notice = 'Đã lưu cấu hình thời lượng booking.';
      } catch (error) {
        this.error = error.message || 'Không thể lưu cấu hình thời lượng.';
      } finally {
        this.isSavingDuration = false;
      }
    },
    openCreateModal() {
      this.clearMessages();
      this.editingSlot = null;
      this.slotForm = {
        ...this.defaultSlotForm(),
        court_type_id: this.courtTypes[0]?.id || null,
      };
      this.showSlotModal = true;
    },
    openEditModal(slot) {
      this.clearMessages();
      this.editingSlot = slot;
      this.slotForm = {
        court_type_id: slot.court_type_id,
        apply_to_days: this.normalizeDays(slot.apply_to_days),
        start_time: this.formatTime(slot.start_time),
        end_time: this.formatTime(slot.end_time),
        booking_type: slot.booking_type,
        price: Number(slot.price),
        is_active: Boolean(slot.is_active),
      };
      this.showSlotModal = true;
    },
    closeSlotModal() {
      this.showSlotModal = false;
      this.editingSlot = null;
    },
    async saveSlot() {
      this.clearMessages();

      if (this.slotForm.apply_to_days.length === 0) {
        this.error = 'Vui lòng chọn ít nhất một ngày áp dụng.';
        return;
      }

      this.isSavingSlot = true;
      const payload = {
        ...this.slotForm,
        venue_cluster_id: this.selectedClusterId,
        apply_to_days: this.normalizeDays(this.slotForm.apply_to_days),
      };
      const path = this.editingSlot ? `/api/owner/price-slots/${this.editingSlot.id}` : '/api/owner/price-slots';
      const method = this.editingSlot ? 'PATCH' : 'POST';

      try {
        const saved = await api(path, {
          method,
          body: JSON.stringify(payload),
        });

        if (this.editingSlot) {
          this.priceSlots = this.priceSlots.map((slot) => (slot.id === saved.id ? saved : slot));
        } else {
          this.priceSlots = [...this.priceSlots, saved];
        }

        this.notice = 'Đã lưu khung giá.';
        this.closeSlotModal();
      } catch (error) {
        this.error = error.message || 'Không thể lưu khung giá.';
      } finally {
        this.isSavingSlot = false;
      }
    },
    async toggleSlot(slot) {
      this.clearMessages();
      try {
        const saved = await api(`/api/owner/price-slots/${slot.id}`, {
          method: 'PATCH',
          body: JSON.stringify({ is_active: !slot.is_active }),
        });
        this.priceSlots = this.priceSlots.map((item) => (item.id === saved.id ? saved : item));
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật trạng thái.';
      }
    },
    async deleteSlot(slot) {
      if (!confirm('Xóa khung giá này?')) return;
      this.clearMessages();

      try {
        await api(`/api/owner/price-slots/${slot.id}`, { method: 'DELETE' });
        this.priceSlots = this.priceSlots.filter((item) => item.id !== slot.id);
        this.notice = 'Đã xóa khung giá.';
      } catch (error) {
        this.error = error.message || 'Không thể xóa khung giá.';
      }
    },
    courtTypeName(id) {
      return this.courtTypes.find((type) => type.id === Number(id))?.name || 'Chưa rõ';
    },
    bookingTypeLabel(type) {
      return {
        all: 'Tất cả',
        single: 'Đặt lẻ',
        recurring: 'Đặt cố định',
      }[type] || type;
    },
    normalizeDays(days) {
      return [...new Set((days || []).map((day) => (Number(day) === 0 ? 7 : Number(day))))].sort((a, b) => a - b);
    },
    formatDays(days) {
      const values = this.normalizeDays(days);
      if (values.join(',') === '1,2,3,4,5') return 'Thứ 2 - Thứ 6';
      if (values.join(',') === '6,7') return 'Thứ 7, CN';
      if (values.join(',') === '1,2,3,4,5,6,7') return 'Tất cả các ngày';

      const labels = {
        1: 'Thứ 2',
        2: 'Thứ 3',
        3: 'Thứ 4',
        4: 'Thứ 5',
        5: 'Thứ 6',
        6: 'Thứ 7',
        7: 'CN',
      };
      return values.map((day) => labels[day]).join(', ');
    },
    formatTime(time) {
      return (time || '').slice(0, 5);
    },
    formatCurrency(amount) {
      return `${new Intl.NumberFormat('vi-VN').format(Number(amount || 0))}đ`;
    },
    clearMessages() {
      this.error = null;
      this.notice = null;
    },
  },
};
</script>

<style src="../../../css/owner/pricing.css" scoped></style>

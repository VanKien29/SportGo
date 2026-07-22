<template>
  <div class="courts-tab-surface">
    <!-- Header -->
    <div class="tab-section-header">
      <div class="header-copy">
        <h2>Danh sách sân con</h2>
        <p class="section-subtitle">Quản lý trạng thái, loại sân và khung giờ đặt cho từng sân con.</p>
      </div>
      <div class="header-actions">
        <button type="button" class="btn btn-outline btn-sm" @click="$emit('open-spatial-editor')">
          <AppIcon name="maximize" size="14" />
          <span>Sơ đồ mặt bằng</span>
        </button>
        <button type="button" class="btn btn-primary btn-sm" :disabled="isClusterLocked" @click="$emit('open-scale-request')">
          <AppIcon name="plus" size="14" />
          <span>Yêu cầu thêm sân</span>
        </button>
      </div>
    </div>

    <!-- Toolbar: Search + Filter Tabs + View Mode -->
    <div v-if="!loading && courts.length > 0" class="courts-toolbar">
      <div class="toolbar-search">
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="Tìm kiếm sân con..."
        />
        <button v-if="searchQuery" type="button" class="clear-search-btn" @click="searchQuery = ''">
          <AppIcon name="x" size="14" />
        </button>
      </div>

      <div class="filter-tabs">
        <button
          type="button"
          class="filter-tab-btn"
          :class="{ active: statusFilter === 'all' }"
          @click="statusFilter = 'all'"
        >
          Tất cả ({{ courts.length }})
        </button>
        <button
          type="button"
          class="filter-tab-btn"
          :class="{ active: statusFilter === 'active' }"
          @click="statusFilter = 'active'"
        >
          Hoạt động ({{ activeCount }})
        </button>
        <button
          type="button"
          class="filter-tab-btn"
          :class="{ active: statusFilter === 'inactive' }"
          @click="statusFilter = 'inactive'"
        >
          Tạm ẩn ({{ inactiveCount }})
        </button>
      </div>

      <div class="view-toggle">
        <button
          type="button"
          class="vtgl-btn"
          :class="{ active: currentView === 'list' }"
          @click="currentView = 'list'"
          title="Danh sách"
        >
          <AppIcon name="menu" size="15" />
        </button>
        <button
          type="button"
          class="vtgl-btn"
          :class="{ active: currentView === 'grid' }"
          @click="currentView = 'grid'"
          title="Dạng lưới"
        >
          <AppIcon name="dashboard" size="15" />
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 4" :key="i" class="skeleton-row">
        <div class="sk-index"></div>
        <div class="sk-body">
          <div class="sk-line sk-line--wide"></div>
          <div class="sk-line sk-line--narrow"></div>
        </div>
        <div class="sk-chip"></div>
        <div class="sk-btns">
          <div class="sk-btn"></div>
          <div class="sk-btn"></div>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="state-box state-box--error">
      <div class="state-icon">
        <AppIcon name="alert" size="20" />
      </div>
      <div>
        <p class="state-title">Không thể tải danh sách sân</p>
        <p class="state-desc">{{ error }}</p>
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="courts.length === 0" class="state-box state-box--empty">
      <div class="state-icon">
        <AppIcon name="layers" size="20" />
      </div>
      <div class="state-text">
        <p class="state-title">Chưa có sân con nào</p>
        <p class="state-desc">Gửi yêu cầu để thêm sân con và bắt đầu vận hành cụm sân.</p>
      </div>
      <button type="button" class="btn btn-primary btn-sm" @click="$emit('open-scale-request')">
        <AppIcon name="plus" size="14" />
        <span>Gửi yêu cầu thêm sân</span>
      </button>
    </div>

    <!-- Empty Search Results -->
    <div v-else-if="filteredCourts.length === 0" class="state-box state-box--empty">
      <p class="state-title">Không tìm thấy sân con phù hợp</p>
      <p class="state-desc">Thử tìm kiếm với từ khóa khác hoặc đặt lại bộ lọc.</p>
      <button type="button" class="btn btn-outline btn-sm" @click="searchQuery = ''; statusFilter = 'all'">
        Xóa bộ lọc
      </button>
    </div>

    <!-- Content -->
    <div v-else>

      <!-- LIST VIEW -->
      <div v-if="currentView === 'list'" class="courts-list">
        <div
          v-for="(court, index) in filteredCourts"
          :key="court.id"
          class="court-row"
          :class="{ 'court-row--inactive': court.status !== 'active' }"
        >
          <!-- Index -->
          <div class="court-index" aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</div>

          <!-- Name + Type -->
          <div class="court-main">
            <span class="court-name">{{ court.name }}</span>
            <span class="court-type-pill">{{ court.court_type ? court.court_type.name : '—' }}</span>
            <span v-if="court.slot_duration" class="court-duration-tag">{{ court.slot_duration }} phút</span>
          </div>

          <!-- Status -->
          <span class="status-pill" :class="`status-pill--${court.status}`">
            <AppIcon :name="courtStatusIcon(court.status)" size="11" />
            <span>{{ courtStatusLabel(court.status) }}</span>
          </span>

          <!-- Actions -->
          <div class="court-actions">
            <button
              type="button"
              class="action-btn action-btn--edit"
              :disabled="isClusterLocked"
              @click="$emit('edit-court', court)"
              title="Chỉnh sửa"
            >
              <AppIcon name="edit" size="14" />
              <span class="action-label">Chỉnh sửa</span>
            </button>
            <button
              type="button"
              class="action-btn"
              :class="court.status === 'active' ? 'action-btn--mute' : 'action-btn--activate'"
              :disabled="isClusterLocked"
              @click="$emit('toggle-court-status', court)"
              :title="court.status === 'active' ? 'Tạm ẩn' : 'Mở lại'"
            >
              <AppIcon :name="court.status === 'active' ? 'eyeOff' : 'eye'" size="14" />
              <span class="action-label">{{ court.status === 'active' ? 'Tạm ẩn' : 'Mở lại' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- GRID VIEW -->
      <div v-else-if="currentView === 'grid'" class="courts-grid">
        <div
          v-for="(court, index) in filteredCourts"
          :key="court.id"
          class="court-card"
          :class="{ 'court-card--inactive': court.status !== 'active' }"
        >
          <div class="court-card-head">
            <span class="court-card-num">{{ String(index + 1).padStart(2, '0') }}</span>
            <span class="status-pill" :class="`status-pill--${court.status}`">
              <AppIcon :name="courtStatusIcon(court.status)" size="12" />
              <span>{{ courtStatusLabel(court.status) }}</span>
            </span>
          </div>

          <div class="court-card-body">
            <h3 class="court-card-name">{{ court.name }}</h3>
            <dl class="court-card-meta">
              <div class="meta-row">
                <dt>Loại sân</dt>
                <dd>{{ court.court_type ? court.court_type.name : '—' }}</dd>
              </div>
              <div v-if="court.slot_duration" class="meta-row">
                <dt>Khung giờ</dt>
                <dd>{{ court.slot_duration }} phút</dd>
              </div>
            </dl>
          </div>

          <div class="court-card-footer">
            <button
              type="button"
              class="action-btn action-btn--edit"
              :disabled="isClusterLocked"
              @click="$emit('edit-court', court)"
            >
              <AppIcon name="edit" size="13" />
              <span>Chỉnh sửa</span>
            </button>
            <button
              type="button"
              class="action-btn"
              :class="court.status === 'active' ? 'action-btn--mute' : 'action-btn--activate'"
              :disabled="isClusterLocked"
              @click="$emit('toggle-court-status', court)"
            >
              <AppIcon :name="court.status === 'active' ? 'eyeOff' : 'eye'" size="13" />
              <span>{{ court.status === 'active' ? 'Tạm ẩn' : 'Mở lại' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../AppIcon.vue';

export default {
  name: 'ClusterCourtsTab',
  components: { AppIcon },
  props: {
    courts: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    isClusterLocked: { type: Boolean, default: false },
  },
  emits: ['edit-court', 'toggle-court-status', 'open-scale-request', 'open-spatial-editor'],
  data() {
    return {
      currentView: 'list',
      searchQuery: '',
      statusFilter: 'all',
    };
  },
  computed: {
    activeCount() {
      return this.courts.filter(c => c.status === 'active').length;
    },
    inactiveCount() {
      return this.courts.filter(c => c.status !== 'active').length;
    },
    filteredCourts() {
      return this.courts.filter(c => {
        const matchesStatus = this.statusFilter === 'all' || c.status === this.statusFilter;
        const q = this.searchQuery.trim().toLowerCase();
        const matchesSearch = !q ||
          (c.name && c.name.toLowerCase().includes(q)) ||
          (c.court_type && c.court_type.name && c.court_type.name.toLowerCase().includes(q));
        return matchesStatus && matchesSearch;
      });
    },
  },
  methods: {
    courtStatusLabel(status) {
      return { active: 'Đang hoạt động', inactive: 'Tạm đóng', maintenance: 'Bảo trì' }[status] || status;
    },
    courtStatusIcon(status) {
      return { active: 'circleCheck', inactive: 'clock', maintenance: 'alert' }[status] || 'clock';
    },
  },
};
</script>

<style scoped>
/* ── Surface ── */
.courts-tab-surface {
  background: var(--admin-surface);
  border-radius: 12px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── Courts Toolbar ── */
.courts-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 10px 14px;
  background: var(--admin-bg);
  border: 1px solid var(--admin-border-soft);
  border-radius: 10px;
}

.toolbar-search {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 180px;
  max-width: 300px;
}

.toolbar-search .search-icon {
  position: absolute;
  left: 10px;
  color: var(--admin-faint);
  pointer-events: none;
}

.toolbar-search .search-input {
  width: 100%;
  height: 32px;
  padding: 0 28px 0 12px;
  border-radius: 7px;
  border: 1px solid var(--admin-border-soft);
  background: var(--admin-surface);
  color: var(--admin-text);
  font-size: 12.5px;
}

.toolbar-search .search-input:focus {
  outline: none;
  border-color: var(--admin-primary);
}

.toolbar-search .clear-search-btn {
  position: absolute;
  right: 6px;
  background: transparent;
  border: none;
  color: var(--admin-faint);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.filter-tabs {
  display: flex;
  align-items: center;
  gap: 4px;
}

.filter-tab-btn {
  padding: 4px 10px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--admin-faint);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 140ms ease;
}

.filter-tab-btn:hover {
  color: var(--admin-text);
}

.filter-tab-btn.active {
  background: var(--admin-surface);
  color: var(--admin-text);
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
}

.court-type-pill {
  font-size: 11.5px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 4px;
  background: var(--admin-border-soft);
  color: var(--admin-faint);
  flex-shrink: 0;
}

.court-duration-tag {
  font-size: 11.5px;
  color: var(--admin-faint);
  flex-shrink: 0;
}

/* ── Header ── */
.tab-section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.header-copy { display: flex; flex-direction: column; gap: 3px; }

.tab-section-header h2 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--admin-text);
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 20px;
  min-width: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(34, 166, 83, 0.14);
  color: #22a653;
  font-size: 11px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

[data-theme="dark"] .count-badge {
  background: rgba(52, 211, 153, 0.15);
  color: #34d399;
}

.section-subtitle {
  margin: 0;
  font-size: 12.5px;
  color: var(--admin-faint);
}

.header-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

/* ── View Toggle ── */
.view-toggle {
  display: flex;
  background: var(--admin-bg);
  border-radius: 7px;
  padding: 3px;
  gap: 2px;
}

.vtgl-btn {
  width: 30px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 5px;
  background: transparent;
  color: var(--admin-faint);
  cursor: pointer;
  transition: background 140ms, color 140ms, box-shadow 140ms;
}

.vtgl-btn.active {
  background: var(--admin-surface);
  color: var(--admin-text);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.vtgl-btn:hover:not(.active) { color: var(--admin-text); }

/* ── Skeleton ── */
.skeleton-list { display: flex; flex-direction: column; }

.skeleton-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 0;
  border-bottom: 1px solid var(--admin-border-soft);
}

.skeleton-row:last-child { border-bottom: none; }

.sk-index {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: var(--admin-border-soft);
  flex-shrink: 0;
  animation: sk-pulse 1.4s ease-in-out infinite;
}

.sk-body { flex: 1; display: flex; flex-direction: column; gap: 6px; animation: sk-pulse 1.4s ease-in-out infinite; }
.sk-line { height: 10px; border-radius: 4px; background: var(--admin-border-soft); }
.sk-line--wide { width: 45%; }
.sk-line--narrow { width: 30%; }
.sk-chip { width: 90px; height: 22px; border-radius: 999px; background: var(--admin-border-soft); flex-shrink: 0; animation: sk-pulse 1.4s ease-in-out infinite; }
.sk-btns { display: flex; gap: 6px; flex-shrink: 0; animation: sk-pulse 1.4s ease-in-out infinite; }
.sk-btn { width: 72px; height: 28px; border-radius: 6px; background: var(--admin-border-soft); }

@keyframes sk-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* ── Feedback States ── */
.state-box {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px;
  border-radius: 10px;
  background: var(--admin-bg);
  flex-wrap: wrap;
}

.state-box--error { background: var(--admin-danger-soft); }

.state-icon {
  width: 38px;
  height: 38px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(34, 166, 83, 0.12);
  color: #22a653;
  flex-shrink: 0;
}

[data-theme="dark"] .state-icon { background: rgba(52, 211, 153, 0.12); color: #34d399; }
.state-box--error .state-icon { background: var(--admin-danger-soft); color: var(--admin-danger); }

.state-text { flex: 1; }

.state-title {
  margin: 0 0 3px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--admin-text);
}

.state-desc {
  margin: 0;
  font-size: 12.5px;
  color: var(--admin-faint);
}

/* Status Pill ── */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 500;
  white-space: nowrap;
  line-height: 1;
  /* default/unknown */
  background: var(--admin-hover);
  color: var(--admin-faint);
}

/* Active — green, works on both themes */
.status-pill--active {
  background: rgba(34, 166, 83, 0.14);
  color: #1a8244;
}
[data-theme="dark"] .status-pill--active {
  background: rgba(52, 211, 153, 0.15);
  color: #34d399;
}
.status-pill--active .status-dot { background: #22c55e; }

/* Inactive — neutral */
.status-pill--inactive {
  background: var(--admin-hover);
  color: var(--admin-faint);
}
.status-pill--inactive .status-dot { background: var(--admin-faint); }

/* Maintenance — amber */
.status-pill--maintenance {
  background: rgba(234, 179, 8, 0.12);
  color: #92400e;
}
[data-theme="dark"] .status-pill--maintenance {
  background: rgba(234, 179, 8, 0.14);
  color: #fbbf24;
}
.status-pill--maintenance .status-dot { background: #eab308; }

/* ── Action Buttons ── */
.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 30px;
  padding: 0 10px;
  border-radius: 6px;
  border: 1px solid var(--admin-border-soft);
  background-color: transparent;
  background: transparent;
  -webkit-appearance: none;
  appearance: none;
  color: var(--admin-text);
  font-size: 12px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: background 140ms, border-color 140ms, color 140ms;
  white-space: nowrap;
}

.action-btn:disabled { opacity: 0.38; cursor: not-allowed; }

.action-btn--edit:hover:not(:disabled) {
  background: rgba(34, 166, 83, 0.1);
  border-color: rgba(34, 166, 83, 0.4);
  color: #1a8244;
}
[data-theme="dark"] .action-btn--edit:hover:not(:disabled) {
  background: rgba(52, 211, 153, 0.1);
  border-color: rgba(52, 211, 153, 0.3);
  color: #34d399;
}

.action-btn--mute:hover:not(:disabled) {
  background: var(--admin-danger-soft);
  border-color: var(--admin-danger);
  color: var(--admin-danger);
}

.action-btn--activate:hover:not(:disabled) {
  background: rgba(34, 166, 83, 0.1);
  border-color: rgba(34, 166, 83, 0.4);
  color: #1a8244;
}
[data-theme="dark"] .action-btn--activate:hover:not(:disabled) {
  background: rgba(52, 211, 153, 0.1);
  border-color: rgba(52, 211, 153, 0.3);
  color: #34d399;
}

/* ── LIST VIEW ── */
.courts-list {
  display: flex;
  flex-direction: column;
  border-radius: 10px;
  border: 1px solid var(--admin-border-soft);
  overflow: hidden;
}

.court-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 16px;
  height: 46px;
  background: transparent;
  border-bottom: 1px solid var(--admin-border-soft);
  transition: background 140ms;
}

.court-row:last-child { border-bottom: none; }

.court-row:hover { background: var(--admin-hover); }

/* On hover, sync action buttons so they don't show a different background patch */
.court-row:hover .action-btn {
  background-color: transparent;
  background: transparent;
}

.court-row--inactive { opacity: 0.6; }

/* Index number */
.court-index {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  background: rgba(34, 166, 83, 0.12);
  color: #1a8244;
  font-size: 10.5px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
}

[data-theme="dark"] .court-index {
  background: rgba(52, 211, 153, 0.12);
  color: #34d399;
}

/* Court main — single inline line */
.court-main {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  /* NO overflow:hidden — would create a stacking context causing distinct background patches */
}

.court-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--admin-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex-shrink: 0;
  max-width: 50%;
}

.court-sep {
  color: var(--admin-border);
  font-size: 12px;
  flex-shrink: 0;
}

.court-type {
  font-size: 12px;
  color: var(--admin-faint);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex-shrink: 1;
  min-width: 0;
}

/* Actions */
.court-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.action-label { display: none; }

@media (min-width: 900px) {
  .action-label { display: inline; }
  .court-name { max-width: none; }
}

/* ── GRID VIEW ── */
.courts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
}

.court-card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  border-radius: 10px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  transition: border-color 160ms, box-shadow 160ms;
}

.court-card:hover {
  border-color: var(--admin-border);
  box-shadow: var(--admin-shadow-sm);
}

.court-card--inactive { opacity: 0.65; }

.court-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.court-card-num {
  font-size: 11px;
  font-weight: 700;
  color: var(--admin-faint);
  font-variant-numeric: tabular-nums;
  background: var(--admin-bg);
  padding: 3px 7px;
  border-radius: 5px;
}

.court-card-body { display: flex; flex-direction: column; gap: 10px; }

.court-card-name {
  margin: 0;
  font-size: 14.5px;
  font-weight: 600;
  color: var(--admin-text);
}

.court-card-meta {
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
}

.meta-row dt { color: var(--admin-faint); font-weight: 400; }
.meta-row dd { margin: 0; color: var(--admin-text); font-weight: 500; }

.court-card-footer {
  display: flex;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--admin-border-soft);
}

/* ── Spatial Notice ── */
.spatial-notice {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  padding: 40px 24px;
  border: 1px dashed var(--admin-border);
  border-radius: 10px;
  text-align: center;
}

.spatial-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: rgba(34, 166, 83, 0.12);
  color: #22a653;
  display: flex;
  align-items: center;
  justify-content: center;
}

[data-theme="dark"] .spatial-icon {
  background: rgba(52, 211, 153, 0.12);
  color: #34d399;
}

.spatial-notice h3 {
  margin: 0 0 6px;
  font-size: 15px;
  font-weight: 600;
  color: var(--admin-text);
}

.spatial-notice p {
  margin: 0;
  font-size: 13px;
  color: var(--admin-faint);
  line-height: 1.55;
  max-width: 340px;
}

/* ── Responsive ── */
@media (max-width: 480px) {
  .tab-section-header { flex-direction: column; }
  .action-btn { padding: 0 8px; }
}

@media (prefers-reduced-motion: reduce) {
  .court-row, .court-card, .skeleton-row { animation: none !important; }
}
</style>

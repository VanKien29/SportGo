<template>
  <div class="venue-combobox" :class="{ open, invalid, disabled }" @focusout="handleFocusOut">
    <div v-if="multiple && selectedOptions.length" class="selected-chips">
      <span v-for="option in selectedOptions" :key="option.id">
        {{ option.name }}
        <button type="button" :aria-label="`Bỏ chọn ${option.name}`" :disabled="disabled" @click="remove(option.id)">×</button>
      </span>
    </div>

    <div class="input-shell">
      <AppIcon name="search" size="17" />
      <input
        ref="input"
        v-model.trim="query"
        role="combobox"
        :aria-expanded="open ? 'true' : 'false'"
        aria-autocomplete="list"
        :aria-controls="listId"
        :placeholder="singleLabel || placeholder"
        :disabled="disabled"
        @focus="openDropdown"
        @input="scheduleSearch"
        @keydown.down.prevent="openDropdown"
        @keydown.esc="closeDropdown"
      />
      <button v-if="!multiple && modelValue" class="clear-button" type="button" aria-label="Bỏ cụm sân đã chọn" :disabled="disabled" @click="clearSingle">×</button>
      <AppIcon v-else name="chevronDown" size="16" />
    </div>

    <div v-if="open && !disabled" :id="listId" class="option-panel" role="listbox" :aria-multiselectable="multiple ? 'true' : undefined">
      <div v-if="loading && options.length === 0" class="option-state">Đang tìm cụm sân...</div>
      <button
        v-for="option in options"
        :key="option.id"
        class="option-row"
        :class="{ selected: isSelected(option.id) }"
        type="button"
        role="option"
        :aria-selected="isSelected(option.id) ? 'true' : 'false'"
        :disabled="isUnavailable(option)"
        @mousedown.prevent="choose(option)"
      >
        <span><strong>{{ option.name }}</strong><small>{{ ownerLabel(option) }}</small></span>
        <span class="option-meta">{{ Number(option.court_count || 0) }} sân<small v-if="isUnavailable(option)">Không đủ điều kiện</small></span>
      </button>
      <div v-if="!loading && options.length === 0" class="option-state">Không tìm thấy cụm sân phù hợp.</div>
      <button v-if="page < lastPage" class="load-more" type="button" :disabled="loading" @mousedown.prevent="load(page + 1, true)">
        {{ loading ? 'Đang tải...' : 'Xem thêm kết quả' }}
      </button>
      <div v-if="options.length" class="result-count">Đang hiển thị {{ options.length }}/{{ total }} kết quả</div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { adminVenueClusterService } from '../../services/adminVenueClusterService.js';

export default {
  name: 'VenueClusterCombobox',
  components: { AppIcon },
  props: {
    modelValue: { type: [String, Number, Array], default: '' },
    multiple: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Nhập tên cụm sân hoặc Chủ sân' },
    initialOptions: { type: Array, default: () => [] },
    requireCourts: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
  },
  emits: ['update:modelValue', 'select'],
  data() {
    return {
      open: false,
      query: '',
      options: [],
      selectedById: new Map(),
      page: 1,
      lastPage: 1,
      total: 0,
      loading: false,
      timer: null,
      requestToken: 0,
      listId: `venue-combobox-${Math.random().toString(36).slice(2)}`,
    };
  },
  computed: {
    selectedIds() {
      const values = this.multiple ? (Array.isArray(this.modelValue) ? this.modelValue : []) : (this.modelValue !== '' && this.modelValue != null ? [this.modelValue] : []);
      return values.map((value) => String(value));
    },
    selectedOptions() {
      return this.selectedIds.map((id) => this.selectedById.get(id)).filter(Boolean);
    },
    singleLabel() {
      return this.multiple ? '' : this.selectedOptions[0] ? this.optionLabel(this.selectedOptions[0]) : '';
    },
  },
  watch: {
    initialOptions: { immediate: true, deep: true, handler(options) { this.remember(options || []); } },
    modelValue() { this.remember(this.options); },
  },
  beforeUnmount() { window.clearTimeout(this.timer); this.requestToken += 1; },
  methods: {
    remember(options) {
      options.forEach((option) => { if (option?.id != null) this.selectedById.set(String(option.id), option); });
    },
    optionLabel(option) { return `${option.name}${option.owner?.full_name ? ` · ${option.owner.full_name}` : ''}`; },
    ownerLabel(option) {
      const name = String(option.owner?.full_name || option.owner?.username || '').trim();
      if (!name) return 'Chưa có Chủ sân';
      return /^chủ sân\b/i.test(name) ? name : `Chủ sân ${name}`;
    },
    isSelected(id) { return this.selectedIds.includes(String(id)); },
    isUnavailable(option) { return this.requireCourts && Number(option.court_count || 0) < 1; },
    async openDropdown() {
      if (this.disabled) return;
      this.open = true;
      if (this.options.length === 0) await this.load(1);
    },
    closeDropdown() { this.open = false; this.query = ''; },
    handleFocusOut(event) {
      if (!event.currentTarget.contains(event.relatedTarget)) window.setTimeout(() => this.closeDropdown(), 100);
    },
    scheduleSearch() {
      this.open = true;
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(() => this.load(1), 280);
    },
    async load(page = 1, append = false) {
      const token = ++this.requestToken;
      this.loading = true;
      try {
        const response = await adminVenueClusterService.list({ options: true, paginate: true, page, per_page: 20, search: this.query });
        if (token !== this.requestToken) return;
        const incoming = Array.isArray(response.data) ? response.data : [];
        this.remember(incoming);
        this.options = append ? [...this.options, ...incoming.filter((option) => !this.options.some((current) => String(current.id) === String(option.id)))] : incoming;
        this.page = Number(response.meta?.current_page || page);
        this.lastPage = Number(response.meta?.last_page || 1);
        this.total = Number(response.meta?.total || incoming.length);
      } catch {
        if (token === this.requestToken) { this.options = append ? this.options : []; this.page = 1; this.lastPage = 1; this.total = 0; }
      } finally {
        if (token === this.requestToken) this.loading = false;
      }
    },
    choose(option) {
      if (this.isUnavailable(option)) return;
      this.remember([option]);
      if (this.multiple) {
        const ids = [...this.selectedIds];
        const id = String(option.id);
        const next = ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
        this.$emit('update:modelValue', next);
      } else {
        this.$emit('update:modelValue', String(option.id));
        this.open = false;
        this.query = '';
      }
      this.$emit('select', option);
    },
    remove(id) { this.$emit('update:modelValue', this.selectedIds.filter((value) => value !== String(id))); },
    clearSingle() { this.$emit('update:modelValue', ''); this.query = ''; this.$refs.input?.focus(); },
  },
};
</script>

<style scoped>
.venue-combobox { position: relative; width: 100%; }.input-shell { display: flex; min-height: 42px; align-items: center; gap: 8px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 11px; background: #fff; color: #64748b; }.venue-combobox.open .input-shell { border-color: #16834b; box-shadow: 0 0 0 3px rgba(22, 131, 75, .1); }.venue-combobox.invalid .input-shell { border-color: #dc2626; }.venue-combobox.disabled .input-shell { background: #f1f5f9; }.input-shell input { min-width: 0; min-height: 40px; flex: 1; border: 0 !important; padding: 0 !important; outline: 0; box-shadow: none !important; }.input-shell input::placeholder { color: #64748b; }.clear-button { border: 0; background: transparent; color: #64748b; cursor: pointer; font-size: 20px; }.option-panel { position: absolute; z-index: 9200; top: calc(100% + 5px); right: 0; left: 0; max-height: 320px; overflow: auto; border: 1px solid #cbd5e1; border-radius: 9px; background: #fff; box-shadow: 0 14px 30px rgba(15, 23, 42, .16); }.option-row { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 12px; border: 0; border-bottom: 1px solid #eef2f7; padding: 10px 12px; background: #fff; color: #1e293b; text-align: left; cursor: pointer; }.option-row:hover, .option-row.selected { background: #f0fdf4; }.option-row:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }.option-row > span:first-child { display: grid; min-width: 0; gap: 3px; }.option-row strong, .option-row small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.option-row small { color: #64748b; font-size: 11px; font-weight: 400; }.option-meta { display: grid; flex: 0 0 auto; gap: 2px; color: #475569; font-size: 11px; text-align: right; }.option-state, .result-count { padding: 12px; color: #64748b; font-size: 12px; text-align: center; }.result-count { padding: 7px 12px; background: #f8fafc; font-size: 10px; }.load-more { width: 100%; border: 0; border-bottom: 1px solid #eef2f7; padding: 10px; background: #fff; color: #166534; cursor: pointer; font-weight: 600; }.selected-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }.selected-chips span { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 5px 7px 5px 10px; background: #e8f5ed; color: #166534; font-size: 11px; }.selected-chips button { display: grid; width: 19px; height: 19px; place-items: center; border: 0; border-radius: 50%; background: rgba(22, 101, 52, .1); color: inherit; cursor: pointer; }
</style>

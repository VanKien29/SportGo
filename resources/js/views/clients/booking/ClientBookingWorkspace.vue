<template>
  <div class="cbw-page">
    <PublicNavbar />

    <main class="cbw-main">


      <!-- PROGRESS STEPS -->
      <div class="cbw-steps">
        <div
          v-for="step in steps"
          :key="step.id"
          class="cbw-step"
          :class="{ 'is-active': activeStep === step.id, 'is-done': activeStep > step.id }"
        >
          <div class="cbw-step-num">
            <template v-if="activeStep > step.id">✓</template>
            <template v-else>{{ step.id }}</template>
          </div>
          <div class="cbw-step-info">
            <span class="cbw-step-label">{{ step.label }}</span>
            <span class="cbw-step-hint">{{ step.hint }}</span>
          </div>
        </div>
      </div>

      <!-- INITIAL LOADING STATE -->
      <div v-if="initialLoading" class="cbw-loading">
        <div class="cbw-spinner"></div>
        <span>Đang chuẩn bị bảng lịch sân...</span>
      </div>

      <!-- MAIN WORKSPACE LAYOUT -->
      <div v-else class="cbw-workspace" :class="{ 'is-fullwidth': !selectedSlotKeys.length }">
        <!-- LEFT COLUMN: TIME-ROW MATRIX -->
        <div class="cbw-schedule-panel">
          <!-- CLEAN COMPACT TOOLBAR -->
          <div class="cbw-toolbar">
            <!-- DATE SELECTOR (CUSTOM MINI CALENDAR PICKER) -->
            <div class="cbw-date-selector">
              <span class="cbw-date-lbl">Ngày chơi:</span>
              <AdminDatePicker
                :model-value="bookingDate"
                placeholder="Chọn ngày chơi"
                @update:model-value="onCustomDateSelect"
              />
              <div class="cbw-quick-dates">
                <button
                  type="button"
                  class="cbw-quick-btn"
                  :class="{ 'is-active': bookingDate === today }"
                  @click="setDateQuick(today)"
                >Hôm nay</button>
                <button
                  type="button"
                  class="cbw-quick-btn"
                  :class="{ 'is-active': bookingDate === tomorrow }"
                  @click="setDateQuick(tomorrow)"
                >Ngày mai</button>
                <button
                  type="button"
                  class="cbw-quick-btn"
                  :class="{ 'is-active': bookingDate === dayAfterTomorrow }"
                  @click="setDateQuick(dayAfterTomorrow)"
                >Ngày kia</button>
              </div>
            </div>

            <!-- CA CHƠI TABS (SÁNG / CHIỀU / TỐI) -->
            <div v-if="dynamicTimePeriods.length" class="cbw-period-tabs">
              <button
                v-for="period in dynamicTimePeriods"
                :key="period.key"
                type="button"
                class="cbw-period-btn"
                :class="{ 'is-active': activePeriodKey === period.key }"
                @click="activePeriod = period.key"
              >
                <span>{{ period.label }}</span>
                <span class="cbw-period-range">({{ period.range }})</span>
              </button>
            </div>
          </div>

          <!-- SCHEDULE MATRIX (TIME-ROW FORMAT: ROWS = TIME SLOTS, COLS = COURTS) -->
          <div v-if="scheduleLoading" class="cbw-skel">
            <span v-for="i in 20" :key="i"></span>
          </div>

          <div v-else-if="scheduleError" class="cbw-state-msg cbw-state-msg--error">
            {{ scheduleError }}
          </div>

          <div v-else-if="!courts.length" class="cbw-state-msg">
            Không có sân nào phù hợp với bộ lọc hiện tại.
          </div>

          <div v-else class="cbw-matrix-wrap">
            <table class="cbw-matrix">
              <thead>
                <tr>
                  <th class="cbw-th-corner">KHUNG GIỜ</th>
                  <th v-for="court in courts" :key="court.id" class="cbw-th-court">
                    <strong>{{ court.name }}</strong>
                    <span>{{ court.court_type?.name || 'Sân thể thao' }}</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!activePeriodSlots.length">
                  <td :colspan="courts.length + 1" class="cbw-td-empty">
                    Ca này nằm ngoài giờ hoạt động của sân hoặc không có khung giờ chơi.
                  </td>
                </tr>

                <tr v-else v-for="slotInfo in activePeriodSlots" :key="slotInfo.slot.start_time">
                  <!-- TIME COLUMN (STICKY LEFT) -->
                  <td class="cbw-td-time">
                    {{ shortTime(slotInfo.slot.start_time) }} – {{ shortTime(slotInfo.slot.end_time) }}
                  </td>

                  <!-- COURT COLUMNS -->
                  <td v-for="court in courts" :key="`${court.id}-${slotInfo.slot.start_time}`" class="cbw-td-slot">
                    <button
                      type="button"
                      class="cbw-slot-btn"
                      :class="slotClasses(court.id, slotInfo.slot)"
                      :disabled="slotDisabled(court.id, slotInfo.slot)"
                      :title="slotTitle(court, slotInfo.slot)"
                      @click="toggleSlot(court, slotInfo.index)"
                    >
                      <span v-if="isSelectedSlot(court.id, slotInfo.slot)" class="cbw-slot-label">Đã chọn</span>
                      <span v-else-if="!slotDisabled(court.id, slotInfo.slot)" class="cbw-slot-price">
                        {{ compactMoney(slotStatus(court.id, slotInfo.slot)?.price) }}
                      </span>
                      <span v-else class="cbw-slot-disabled-text">
                        {{ slotDisabledLabel(court.id, slotInfo.slot) }}
                      </span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- SELECTION FEEDBACK BAR -->
          <div v-if="selectedSlotKeys.length || validationError" class="cbw-sel-bar" :class="{ 'cbw-sel-bar--error': validationError }">
            <div class="cbw-sel-info">
              <strong>{{ validationError ? 'Lưu ý điều chỉnh:' : selectionTitle }}</strong>
              <span>{{ validationError || selectionHint }}</span>
            </div>
            <div v-if="selectedSlotKeys.length" class="cbw-sel-actions">
              <button type="button" class="cbw-clear-btn" @click="clearSelection">Bỏ chọn tất cả</button>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: BOOKING SUMMARY & PAYMENT PANEL (CHỈ HIỆN KHU DÃ CHỌN Ô GIỜ) -->
        <div v-if="selectedSlotKeys.length" class="cbw-summary-panel" ref="summaryPanel" tabindex="-1">
          <div class="cbw-summary-header">
            <span class="cbw-summary-label">THÔNG TIN ĐẶT SÂN</span>
            <h2 class="cbw-summary-title">{{ summaryTitle }}</h2>
            <p class="cbw-summary-sub">{{ summarySubtitle }}</p>
          </div>

          <div class="cbw-divider"></div>

          <!-- SUMMARY FACTS -->
          <div class="cbw-facts">
            <div class="cbw-fact-row">
              <span>Cụm sân:</span>
              <strong>{{ currentCluster?.name || '-' }}</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Ngày chơi:</span>
              <strong>{{ formatDate(bookingDate) }}</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Khung giờ:</span>
              <strong>{{ selectedTimeText }}</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Tổng thời lượng:</span>
              <strong>{{ durationText }}</strong>
            </div>
          </div>

          <!-- PRICE DETAILS LIST -->
          <template v-if="selectedPriceRows.length">
            <div class="cbw-divider"></div>
            <div class="cbw-price-rows">
              <div class="cbw-price-head">Chi tiết tiền sân:</div>
              <div v-for="row in selectedPriceRows" :key="`${row.courtId}-${row.startTime}`" class="cbw-price-row">
                <span>{{ row.courtName }} ({{ shortTime(row.startTime) }} - {{ shortTime(row.endTime) }})</span>
                <strong>{{ money(row.amount) }}</strong>
              </div>
            </div>
          </template>

          <!-- VOUCHER & DISCOUNT SECTION (FOR LOGGED IN / AUTHENTICATED USERS) -->
          <template v-if="selectedSlotKeys.length && !validationError">
            <div class="cbw-divider"></div>
            <div class="cbw-discount-section">
              <div v-if="membershipDiscount > 0" class="cbw-discount-row">
                <span>Ưu đãi thành viên:</span>
                <strong class="cbw-discount-val">-{{ money(membershipDiscount) }}</strong>
              </div>

              <template v-if="isLoggedIn">
                <button type="button" class="cbw-voucher-btn" @click="voucherOpen = !voucherOpen">
                  <span>{{ selectedVoucherCount ? `${selectedVoucherCount} voucher đã chọn` : 'Chọn Voucher giảm giá' }}</span>
                  <strong v-if="voucherDiscount" class="cbw-discount-val">-{{ money(voucherDiscount) }}</strong>
                  <span v-else class="cbw-voucher-count">{{ eligibleVouchers.length }} mã có sẵn</span>
                </button>
                <p v-if="voucherNotice" class="cbw-voucher-notice">{{ voucherNotice }}</p>

                <div v-if="voucherOpen && eligibleVouchers.length" class="cbw-voucher-list">
                  <button
                    v-for="v in eligibleVouchers"
                    :key="v.id"
                    type="button"
                    class="cbw-voucher-item"
                    :class="{ 'is-active': voucherSelected(v) }"
                    @click="toggleVoucher(v)"
                  >
                    <div>
                      <strong>{{ v.code }}</strong>
                      <span>{{ v.name }}</span>
                    </div>
                    <strong>-{{ money(voucherValue(v, voucherBaseAmount(v))) }}</strong>
                  </button>
                </div>
                <p v-if="voucherOpen && !eligibleVouchers.length" class="cbw-voucher-empty">Không có voucher nào phù hợp cho đơn này.</p>
              </template>
            </div>

            <!-- ADD-ON VENUE SERVICES -->
            <div v-if="clusterServices && clusterServices.length" class="cbw-services-section">
              <div class="cbw-divider"></div>
              <p class="cbw-payment-title">Dịch vụ đi kèm tại sân (Không bắt buộc)</p>
              <div class="cbw-services-list">
                <div v-for="srv in clusterServices" :key="srv.id" class="cbw-service-item">
                  <div class="cbw-srv-info">
                    <strong class="cbw-srv-name">{{ srv.name }}</strong>
                    <span class="cbw-srv-price">{{ money(srv.price) }} / {{ srv.unit || 'lượt' }}</span>
                  </div>
                  <div class="cbw-srv-qty">
                    <button
                      type="button"
                      class="cbw-qty-btn"
                      :disabled="!selectedServiceQty(srv.id)"
                      @click="updateServiceQty(srv, -1)"
                    >-</button>
                    <span class="cbw-qty-val">{{ selectedServiceQty(srv.id) }}</span>
                    <button
                      type="button"
                      class="cbw-qty-btn"
                      @click="updateServiceQty(srv, 1)"
                    >+</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- PAYMENT OPTIONS -->
            <div class="cbw-divider"></div>
            <div class="cbw-payment-section">
              <p class="cbw-payment-title">Hình thức thanh toán</p>

              <p v-if="isLoggedIn && walletBalance !== null" class="cbw-wallet-bal">
                Số dư Ví SportGo: <strong>{{ money(walletBalance) }}</strong>
              </p>

              <div class="cbw-payment-opts">
                <label
                  v-for="opt in paymentOptions"
                  :key="opt.value"
                  class="cbw-payment-opt"
                  :class="{ 'is-active': paymentOption === opt.value, 'is-disabled': opt.disabled }"
                >
                  <input type="radio" v-model="paymentOption" :value="opt.value" :disabled="opt.disabled" />
                  <div>
                    <strong>{{ opt.label }}</strong>
                    <span>{{ opt.hint }}</span>
                  </div>
                </label>
              </div>
            </div>
          </template>

          <!-- TOTAL & SUBMIT BUTTON -->
          <div class="cbw-divider"></div>

          <div class="cbw-total-row">
            <span>Tổng số tiền:</span>
            <strong class="cbw-total-val">{{ money(total) }}</strong>
          </div>

          <div v-if="selectedSlotKeys.length && paymentOption !== 'no_prepay'" class="cbw-required-row">
            <span>Cần thanh toán ngay:</span>
            <strong>{{ money(requiredAmount) }}</strong>
          </div>

          <p v-if="submitError" class="cbw-submit-error">{{ submitError }}</p>

          <button
            type="button"
            class="cbw-submit-btn"
            :disabled="!canSubmit || submitting"
            @click="submit"
          >
            <template v-if="submitting">Đang xử lý tạo đơn...</template>
            <template v-else-if="!isLoggedIn && selectedSlotKeys.length">Đăng nhập để xác nhận đặt sân</template>
            <template v-else>Xác nhận đặt sân ngay</template>
          </button>

          <p v-if="selectedSlotKeys.length && paymentOption !== 'no_prepay'" class="cbw-hold-note">
            * Sân sẽ được giữ tạm thời {{ config.slot_hold_minutes || 20 }} phút để bạn thực hiện thanh toán.
          </p>
        </div>
      </div>
    </main>
  </div>
</template>

<script>
import PublicNavbar from "../../../components/PublicNavbar.vue";
import AdminDatePicker from "../../../components/AdminDatePicker.vue";
import { bookingService } from "../../../services/bookingService.js";
import { getAuth } from "../../../stores/auth.js";

export default {
  name: "ClientBookingWorkspace",
  components: { PublicNavbar, AdminDatePicker },
  data() {
    return {
      steps: [
        { id: 1, label: "Chọn sân & lịch", hint: "Xem ô giờ trống trên ma trận" },
        { id: 2, label: "Kiểm tra giá", hint: "Tính giá & mã ưu đãi" },
        { id: 3, label: "Xác nhận đặt", hint: "Chọn cách thức thanh toán" },
      ],
      clusters: [],
      clusterId: "",
      clusterLocked: false,
      courtTypeId: "",
      bookingDate: new Date().toLocaleDateString("en-CA"),
      initialLoading: true,
      scheduleLoading: false,
      scheduleRequestId: 0,
      scheduleError: "",
      slots: [],
      courts: [],
      statuses: [],
      operatingHours: null,
      activePeriod: "",
      selectedSlotKeys: [],
      selectionError: "",
      availabilityRequestId: 0,
      checking: false,
      available: true,
      preview: null,
      paymentOption: "no_prepay",
      walletBalance: null,
      _venueVouchers: [],
      _vipVouchers: [],
      selectedVenueVoucherId: "",
      selectedVipVoucherId: "",
      voucherOpen: false,
      voucherNotice: "",
      submitting: false,
      submitError: "",
      routeSelection: null,
      selectedServicesMap: {},
    };
  },
  computed: {
    today() {
      return new Date().toLocaleDateString("en-CA");
    },
    tomorrow() {
      const d = new Date();
      d.setDate(d.getDate() + 1);
      return d.toLocaleDateString("en-CA");
    },
    dayAfterTomorrow() {
      const d = new Date();
      d.setDate(d.getDate() + 2);
      return d.toLocaleDateString("en-CA");
    },
    isLoggedIn() {
      return Boolean(getAuth());
    },
    currentCluster() {
      return this.clusters.find(c => String(c.id) === String(this.clusterId)) || null;
    },
    clusterServices() {
      return this.currentCluster?.services || [];
    },
    servicesTotal() {
      return Object.values(this.selectedServicesMap).reduce((sum, item) => sum + (item.quantity * item.price), 0);
    },
    backTarget() {
      const r = String(this.$route.query.return_to || "");
      if (r.startsWith("/venues") && !r.startsWith("//")) return r;
      if (this.clusterId) return `/venues/${this.clusterId}`;
      return "/venues";
    },
    config() {
      return {
        min_duration_minutes: 30,
        max_duration_minutes: null,
        min_advance_booking_minutes: 30,
        slot_hold_minutes: 20,
        allow_full_payment: true,
        allow_deposit: true,
        allow_no_prepay: true,
        deposit_percent: 30,
        ...(this.currentCluster?.booking_config || {}),
      };
    },
    courtTypes() {
      const m = new Map();
      (this.currentCluster?.venue_courts || []).forEach(c => {
        if (c.court_type?.id) m.set(String(c.court_type.id), c.court_type);
      });
      return [...m.values()];
    },
    minDuration() {
      return Number(this.config.min_duration_minutes || 30);
    },
    maxDuration() {
      const v = Number(this.config.max_duration_minutes || 0);
      return v > 0 ? v : null;
    },
    minAdvance() {
      return Number(this.config.min_advance_booking_minutes || 0);
    },
    configText() {
      const parts = [];
      if (this.operatingHours?.is_open) {
        parts.push(`Giờ mở cửa: ${this.shortTime(this.operatingHours.open_time)} – ${this.shortTime(this.operatingHours.close_time)}`);
      } else if (this.operatingHours) {
        parts.push("Sân đóng cửa ngày này");
      }
      parts.push(`Đặt tối thiểu ${this.minDuration} phút`);
      if (this.maxDuration) parts.push(`Tối đa ${this.maxDuration} phút`);
      if (this.minAdvance) parts.push(`Cần đặt trước ${this.minAdvance} phút`);
      if (this.config.slot_hold_minutes) parts.push(`Giữ chỗ ${this.config.slot_hold_minutes} phút`);
      return parts.join(" · ");
    },
    dynamicTimePeriods() {
      const defs = [
        { key: "morning", label: "Ca Sáng", from: 0, to: 12 * 60 },
        { key: "afternoon", label: "Ca Chiều", from: 12 * 60, to: 18 * 60 },
        { key: "evening", label: "Ca Tối", from: 18 * 60, to: 24 * 60 },
      ];
      return defs
        .map(p => {
          const infos = this.slots
            .map((slot, index) => ({ slot, index }))
            .filter(({ slot }) => {
              const s = this.minutes(slot.start_time);
              return s >= p.from && s < p.to;
            });
          if (!infos.length) return null;
          return {
            ...p,
            slotInfos: infos,
            range: `${this.shortTime(infos[0].slot.start_time)} – ${this.shortTime(infos[infos.length - 1].slot.end_time)}`,
          };
        })
        .filter(Boolean);
    },
    activePeriodKey() {
      const ps = this.dynamicTimePeriods;
      if (!ps.length) return "";
      return ps.some(p => p.key === this.activePeriod) ? this.activePeriod : ps[0].key;
    },
    activePeriodSlots() {
      return this.dynamicTimePeriods.find(p => p.key === this.activePeriodKey)?.slotInfos || [];
    },
    selectedSlotEntries() {
      return this.slotEntriesFromKeys(this.selectedSlotKeys);
    },
    selectedSlotRanges() {
      return this.slotRangesFromKeys(this.selectedSlotKeys);
    },
    selectedCourts() {
      const m = new Map();
      this.selectedSlotEntries.forEach(e => m.set(String(e.court.id), e.court));
      return [...m.values()];
    },
    selectedCourt() {
      return this.selectedCourts[0] || null;
    },
    selectedDetails() {
      return this.selectedSlotEntries
        .map(e => ({ ...e, status: this.slotStatus(e.courtId, e.slot) }))
        .filter(e => e.status);
    },
    selectedPriceRows() {
      return this.selectedSlotRanges.map(range => {
        const amount = this.selectedDetails
          .filter(
            e =>
              String(e.courtId) === String(range.venue_court_id) &&
              this.minutes(e.slot.start_time) >= this.minutes(range.start_time) &&
              this.minutes(e.slot.end_time) <= this.minutes(range.end_time)
          )
          .reduce((s, e) => s + Number(e.status?.price || 0), 0);
        return {
          courtId: range.venue_court_id,
          courtName: range.court?.name || "Sân",
          startTime: range.start_time,
          endTime: range.end_time,
          amount,
        };
      });
    },
    duration() {
      return this.selectedSlotEntries.reduce(
        (s, e) => s + Math.max(this.minutes(e.slot.end_time) - this.minutes(e.slot.start_time), 0),
        0
      );
    },
    durationText() {
      if (!this.duration) return "0 phút";
      const h = Math.floor(this.duration / 60);
      const m = this.duration % 60;
      if (!h) return `${m} phút`;
      if (!m) return `${h} giờ`;
      return `${h} giờ ${m} phút`;
    },
    selectedTimeText() {
      return this.rangeListText(this.selectedSlotRanges);
    },
    summaryTitle() {
      if (!this.selectedSlotRanges.length) return "Chưa chọn khung giờ nào";
      if (this.selectedCourts.length === 1) return this.selectedCourts[0].name;
      return `${this.selectedCourts.length} sân đã chọn`;
    },
    summarySubtitle() {
      if (!this.selectedSlotRanges.length) return "Vui lòng bấm chọn ô giờ trên bảng lịch để đặt.";
      if (this.selectedSlotRanges.length === 1) return `${this.selectedCourt?.court_type?.name || "Sân thể thao"} · ${this.durationText}`;
      return `${this.selectedSlotRanges.length} khung giờ · ${this.durationText}`;
    },
    selectionTitle() {
      if (this.checking) return "Đang đối chiếu khung giờ...";
      if (!this.selectedSlotRanges.length) return "Hãy chọn khung giờ trống trên ma trận";
      if (this.selectedSlotRanges.length === 1) return `${this.selectedCourt?.name} (${this.durationText})`;
      return `${this.selectedSlotRanges.length} khung giờ (${this.selectedCourts.length} sân)`;
    },
    selectionHint() {
      if (this.checking) return "Đang kiểm tra tính khả dụng và áp dụng giá tốt nhất...";
      return this.selectedSlotRanges.length
        ? `${this.selectedTimeText} · Tạm tính: ${this.money(this.baseAmount)}`
        : `Mỗi sân cần chọn liền nhau đủ tối thiểu ${this.minDuration} phút.`;
    },
    validationError() {
      if (this.selectionError) return this.selectionError;
      if (!this.selectedSlotKeys.length) return "";
      const bad = this.selectedCourts.find(c => {
        const mins = this.selectedSlotEntries
          .filter(e => String(e.courtId) === String(c.id))
          .reduce((s, e) => s + Math.max(this.minutes(e.slot.end_time) - this.minutes(e.slot.start_time), 0), 0);
        return mins < this.minDuration || (this.maxDuration && mins > this.maxDuration);
      });
      if (bad) {
        const mins = this.selectedSlotEntries
          .filter(e => String(e.courtId) === String(bad.id))
          .reduce((s, e) => s + Math.max(this.minutes(e.slot.end_time) - this.minutes(e.slot.start_time), 0), 0);
        if (mins < this.minDuration) return `${bad.name} yêu cầu thời lượng đặt tối thiểu ${this.minDuration} phút (hiện có ${mins} phút).`;
        return `${bad.name} chỉ được đặt tối đa ${this.maxDuration} phút.`;
      }
      if (this.paymentOption === "wallet" && this.walletBalance !== null && this.walletBalance < this.total) {
        return "Số dư ví SportGo không đủ cho booking này.";
      }
      if (!this.available && !this.checking) return "Khung giờ bạn chọn đã bị người khác đặt hoặc tạm khóa.";
      return "";
    },
    baseAmount() {
      return this.selectedDetails.reduce((s, e) => s + Number(e.status?.price || 0), 0);
    },
    membershipDiscount() {
      return Number(this.preview?.membership_discount_amount || 0);
    },
    afterMembership() {
      return Number(this.preview?.final_amount ?? Math.max(this.baseAmount - this.membershipDiscount, 0));
    },
    eligibleVouchers() {
      return [...(this._venueVouchers || []), ...this.vipVouchers];
    },
    venueVouchers() {
      return this._venueVouchers || [];
    },
    vipVouchers() {
      const rem = Math.max(this.afterMembership - this.venueVoucherDiscount, 0);
      if (rem <= 0) return [];
      return (this._vipVouchers || []).filter(v => Number(v.min_order_amount || 0) <= rem);
    },
    venueVoucher() {
      return this.venueVouchers.find(v => String(v.id) === String(this.selectedVenueVoucherId));
    },
    vipVoucher() {
      return this.vipVouchers.find(v => String(v.id) === String(this.selectedVipVoucherId));
    },
    selectedVoucherCount() {
      return [this.venueVoucher, this.vipVoucher].filter(Boolean).length;
    },
    venueVoucherDiscount() {
      return this.voucherValue(this.venueVoucher, this.afterMembership);
    },
    vipVoucherDiscount() {
      return this.voucherValue(this.vipVoucher, Math.max(this.afterMembership - this.venueVoucherDiscount, 0));
    },
    voucherDiscount() {
      return this.venueVoucherDiscount + this.vipVoucherDiscount;
    },
    total() {
      return Math.max(this.afterMembership - this.voucherDiscount, 0) + this.servicesTotal;
    },
    requiredAmount() {
      if (this.paymentOption === "full_payment" || this.paymentOption === "wallet") return this.total;
      if (this.paymentOption === "deposit") return this.total * (Number(this.config.deposit_percent || 30) / 100);
      return 0;
    },
    paymentOptions() {
      return [
        this.config.allow_no_prepay !== false && { value: "no_prepay", label: "Thanh toán tại sân", hint: "Trả trực tiếp khi đến sân chơi" },
        this.config.allow_deposit !== false && { value: "deposit", label: `Đặt cọc trước ${this.config.deposit_percent || 30}%`, hint: "Giữ sân qua thanh toán Online" },
        this.config.allow_full_payment !== false && { value: "full_payment", label: "Thanh toán 100% online", hint: "Chuyển khoản qua cổng SePay/QR" },
        this.config.allow_full_payment !== false && this.walletBalance !== null && {
          value: "wallet",
          label: "Thanh toán bằng Ví SportGo",
          hint: this.walletBalance >= this.total ? `Số dư: ${this.money(this.walletBalance)}` : "Số dư ví không đủ",
          disabled: this.walletBalance < this.total,
        },
      ].filter(Boolean);
    },
    canSubmit() {
      return this.selectedSlotKeys.length > 0 && !this.validationError && !this.submitting;
    },
    activeStep() {
      if (this.selectedSlotKeys.length && !this.validationError) return 3;
      if (this.selectedSlotKeys.length) return 2;
      return 1;
    },
  },
  async mounted() {
    window.addEventListener("pageshow", this.onPageShow);
    window.addEventListener("focus", this.onWindowFocus);
    await this.initialize();
  },
  beforeUnmount() {
    window.removeEventListener("pageshow", this.onPageShow);
    window.removeEventListener("focus", this.onWindowFocus);
  },
  activated() {
    this.loadSchedule();
  },
  methods: {
    onPageShow(e) {
      if (e.persisted) this.loadSchedule();
    },
    onWindowFocus() {
      if (!this.initialLoading) this.loadSchedule();
    },
    async initialize() {
      try {
        const q = this.$route.query || {};
        const res = await bookingService.getInitData();

        if (getAuth()) {
          try {
            const wr = await bookingService.getWallet();
            this.walletBalance = Number(wr?.wallet?.balance || 0);
          } catch {
            this.walletBalance = null;
          }
        } else {
          this.walletBalance = null;
        }

        this.clusters = res.clusters || [];
        const reqId = q.venue_cluster_id || q.cluster;
        this.clusterId = this.clusters.find(c => String(c.id) === String(reqId))?.id || this.clusters[0]?.id || "";
        this.clusterLocked = Boolean(reqId);

        const reqDate = String(q.booking_date || q.date || this.today);
        this.bookingDate = reqDate >= this.today ? reqDate : this.today;
        this.courtTypeId = String(q.court_type_id || q.court_type || "");

        this.routeSelection = (q.venue_court_id || q.court)
          ? {
              courtId: q.venue_court_id || q.court,
              start: this.normalizeTime(q.start_time),
              end: this.normalizeTime(q.end_time),
            }
          : null;

        this.ensurePaymentOption();
        await this.loadSchedule();
      } catch (err) {
        this.scheduleError = (err?.message && !err.message.includes("<") && !err.message.includes("Phiên đăng nhập"))
          ? err.message
          : "Không thể khởi tạo dữ liệu sân. Vui lòng thử lại.";
      } finally {
        this.initialLoading = false;
      }
    },
    async loadSchedule() {
      if (!this.clusterId || !this.bookingDate) return;
      const rid = ++this.scheduleRequestId;
      const prevKeys = [...this.selectedSlotKeys];
      this.availabilityRequestId += 1;
      this.checking = false;
      this.available = true;
      this.preview = null;
      this._venueVouchers = [];
      this._vipVouchers = [];
      this.selectedVenueVoucherId = "";
      this.selectedVipVoucherId = "";
      this.voucherNotice = "";
      this.scheduleLoading = true;
      this.scheduleError = "";
      try {
        const params = { venue_cluster_id: this.clusterId, booking_date: this.bookingDate };
        if (this.courtTypeId) params.court_type_id = this.courtTypeId;
        const res = await bookingService.getSchedule(params);
        if (rid !== this.scheduleRequestId) return;
        this.slots = res.time_slots || [];
        this.courts = res.courts || [];
        this.statuses = res.slot_statuses || [];
        this.operatingHours = res.operating_hours || null;
        this.ensureActivePeriod();

        if (prevKeys.length) {
          this.selectedSlotKeys = prevKeys;
          const entries = this.slotEntriesFromKeys(prevKeys);
          const changed = entries.length !== prevKeys.length || entries.some(e => this.slotDisabled(e.courtId, e.slot));
          if (changed) {
            this.clearSelection();
            this.selectionError = "Lịch sân vừa cập nhật. Vui lòng chọn lại các khung giờ trống.";
          } else if (this.isLoggedIn) {
            await this.checkAvailability();
          }
        } else {
          await this.applyRouteSelection();
        }
      } catch (err) {
        if (rid !== this.scheduleRequestId) return;
        this.scheduleError = (err?.message && !err.message.includes("<") && !err.message.includes("Phiên đăng nhập"))
          ? err.message
          : "Không thể tải lịch sân. Vui lòng thử lại.";
      } finally {
        if (rid === this.scheduleRequestId) this.scheduleLoading = false;
      }
    },
    async changeCluster() {
      this.courtTypeId = "";
      this.ensurePaymentOption();
      this.clearSelection();
      await this.loadSchedule();
    },
    async changeDate() {
      this.clearSelection();
      await this.loadSchedule();
    },
    async changeCourtType() {
      this.clearSelection();
      await this.loadSchedule();
    },
    ensureActivePeriod() {
      const ps = this.dynamicTimePeriods;
      if (!ps.length) {
        this.activePeriod = "";
        return;
      }
      if (ps.some(p => p.key === this.activePeriod)) return;
      const now = new Date();
      const nowM = now.getHours() * 60 + now.getMinutes();
      const cur =
        this.bookingDate === this.today
          ? ps.find(p => p.slotInfos.some(({ slot }) => this.minutes(slot.start_time) <= nowM && this.minutes(slot.end_time) > nowM))
          : null;
      this.activePeriod = (cur || ps[0]).key;
    },
    setActivePeriodByIndex(index) {
      const p = this.dynamicTimePeriods.find(p => p.slotInfos.some(s => s.index === index));
      if (p) this.activePeriod = p.key;
    },
    shiftDate(days) {
      const d = new Date(`${this.bookingDate}T00:00:00`);
      d.setDate(d.getDate() + days);
      const next = d.toLocaleDateString("en-CA");
      if (next < this.today) return;
      if (next !== this.bookingDate) {
        this.bookingDate = next;
        this.changeDate();
      }
    },
    slotStatus(courtId, slot) {
      const s = String(slot?.start_time || "").slice(0, 5);
      return this.statuses.find(x => String(x.venue_court_id) === String(courtId) && String(x.start_time).slice(0, 5) === s);
    },
    slotPast(slot) {
      if (this.bookingDate !== this.today) return false;
      const now = new Date();
      return this.minutes(slot.start_time) <= now.getHours() * 60 + now.getMinutes();
    },
    slotTooSoon(slot) {
      if (this.bookingDate !== this.today || !this.minAdvance) return false;
      const now = new Date();
      return this.minutes(slot.start_time) < now.getHours() * 60 + now.getMinutes() + this.minAdvance;
    },
    slotDisabled(courtId, slot) {
      return this.slotPast(slot) || this.slotTooSoon(slot) || this.slotStatus(courtId, slot)?.is_available === false;
    },
    slotDisabledLabel(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      if (this.slotPast(slot)) return "Đã qua";
      if (this.slotTooSoon(slot)) return "Quá sát giờ";
      if (status?.busy_source === "slot_lock") return "Đang khóa";
      if (status?.is_available === false) return "Đã đặt";
      return "-";
    },
    isSelectedSlot(courtId, slot) {
      return this.selectedSlotKeys.includes(this.slotKey(courtId, slot));
    },
    slotClasses(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      return {
        "is-selected": this.isSelectedSlot(courtId, slot),
        "is-booked": status?.is_available === false && status?.busy_source !== "slot_lock",
        "is-locked": status?.busy_source === "slot_lock",
        "is-past": this.slotPast(slot) || this.slotTooSoon(slot),
      };
    },
    slotTitle(court, slot) {
      const status = this.slotStatus(court.id, slot);
      if (this.slotPast(slot)) return "Khung giờ đã trôi qua.";
      if (this.slotTooSoon(slot)) return `Cần đặt trước ít nhất ${this.minAdvance} phút.`;
      if (status?.busy_source === "slot_lock") return status.lock_reason || "Sân đang được chủ sân giữ lịch.";
      if (status?.is_available === false) return "Sân đã có người khác đặt.";
      return `${court.name} · ${this.shortTime(slot.start_time)}–${this.shortTime(slot.end_time)} · ${this.money(status?.price)}`;
    },
    async toggleSlot(court, index) {
      const slot = this.slots[index];
      if (this.slotDisabled(court.id, slot)) return;
      this.selectionError = "";
      const key = this.slotKey(court.id, slot);
      const nextKeys = this.selectedSlotKeys.includes(key)
        ? this.selectedSlotKeys.filter(k => k !== key)
        : [...this.selectedSlotKeys, key];
      const nextEntries = this.slotEntriesFromKeys(nextKeys);
      const courtMins = nextEntries
        .filter(e => String(e.courtId) === String(court.id))
        .reduce((s, e) => s + Math.max(this.minutes(e.slot.end_time) - this.minutes(e.slot.start_time), 0), 0);

      if (this.maxDuration && courtMins > this.maxDuration) {
        this.selectionError = `${court.name} chỉ cho phép đặt tối đa ${this.maxDuration} phút mỗi lần.`;
        return;
      }
      this.selectedSlotKeys = nextKeys;
      if (this.isLoggedIn) {
        await this.checkAvailability();
      }
    },
    clearSelection() {
      this.availabilityRequestId += 1;
      this.checking = false;
      this.selectedSlotKeys = [];
      this.selectionError = "";
      this.available = true;
      this.preview = null;
      this._venueVouchers = [];
      this._vipVouchers = [];
      this.selectedVenueVoucherId = "";
      this.selectedVipVoucherId = "";
      this.voucherNotice = "";
    },
    async checkAvailability() {
      if (!this.isLoggedIn) return;
      const rid = ++this.availabilityRequestId;
      const ranges = this.selectedSlotRanges.map(r => ({ ...r }));
      if (!ranges.length) {
        this.available = true;
        this.preview = null;
        this._venueVouchers = [];
        this._vipVouchers = [];
        this.checking = false;
        return;
      }
      this.checking = true;
      try {
        const responses = await Promise.all(
          ranges.map(r =>
            bookingService.checkAvailability({
              venue_court_id: r.venue_court_id,
              booking_date: this.bookingDate,
              start_time: r.start_time,
              end_time: r.end_time,
            })
          )
        );
        if (rid !== this.availabilityRequestId) return;
        this.available = responses.every(r => Boolean(r.available));
        const orig = responses.reduce((s, r) => s + Number(r.price_preview?.original_amount || r.total_price || 0), 0);
        const disc = responses.reduce((s, r) => s + Number(r.membership_discount?.discount_amount || r.price_preview?.membership_discount_amount || 0), 0);
        this.preview = { original_amount: orig, membership_discount_amount: disc, final_amount: Math.max(orig - disc, 0) };
        if (this.available) await this.loadVouchers(rid, ranges[0]);
      } catch (err) {
        if (rid !== this.availabilityRequestId) return;
        // Bắt lỗi không làm vỡ trải nghiệm
        this.available = true;
      } finally {
        if (rid === this.availabilityRequestId) this.checking = false;
      }
    },
    async loadVouchers(rid, firstRange) {
      if (!firstRange || !this.isLoggedIn) return;
      try {
        const res = await bookingService.eligibleVouchers({
          venue_court_id: firstRange.venue_court_id,
          booking_date: this.bookingDate,
          start_time: firstRange.start_time,
          end_time: firstRange.end_time,
          amount: this.baseAmount,
        });
        if (rid !== this.availabilityRequestId) return;
        this._venueVouchers = res.venue_vouchers || [];
        this._vipVouchers = res.vip_vouchers || [];
      } catch {
        if (rid !== this.availabilityRequestId) return;
        this._venueVouchers = [];
        this._vipVouchers = [];
      }
    },
    voucherSelected(v) {
      return v.owner_type === "venue"
        ? String(this.selectedVenueVoucherId) === String(v.id)
        : String(this.selectedVipVoucherId) === String(v.id);
    },
    toggleVoucher(v) {
      const already = this.voucherSelected(v);
      this.voucherNotice = "";
      if (v.owner_type === "venue") {
        this.selectedVenueVoucherId = already ? "" : v.id;
        if (this.selectedVipVoucherId && !this.vipVouchers.some(x => String(x.id) === String(this.selectedVipVoucherId))) {
          this.selectedVipVoucherId = "";
          this.voucherNotice = "Voucher VIP đã được gỡ do giá trị đơn hàng sau giảm giá voucher sân không đủ điều kiện.";
        }
        return;
      }
      this.selectedVipVoucherId = already ? "" : v.id;
    },
    voucherBaseAmount(v) {
      return v?.owner_type === "venue" ? this.afterMembership : Math.max(this.afterMembership - this.venueVoucherDiscount, 0);
    },
    voucherValue(v, amount = this.afterMembership) {
      if (!v) return 0;
      let val = v.discount_type === "percent" ? (Number(amount) * Number(v.discount_value || 0)) / 100 : Number(v.discount_value || 0);
      if (v.max_discount_amount != null) val = Math.min(val, Number(v.max_discount_amount));
      return Math.min(Math.max(val, 0), Number(amount));
    },
    ensurePaymentOption() {
      const allowed = this.paymentOptions.map(o => o.value);
      if (!allowed.includes(this.paymentOption)) this.paymentOption = allowed[0] || "no_prepay";
    },
    async applyRouteSelection() {
      if (!this.routeSelection) return;
      const court = this.courts.find(c => String(c.id) === String(this.routeSelection.courtId));
      const s = this.minutes(this.routeSelection.start);
      const e = this.minutes(this.routeSelection.end);
      const sel = this.slots
        .map((slot, index) => ({ slot, index }))
        .filter(({ slot }) => this.minutes(slot.start_time) >= s && this.minutes(slot.end_time) <= e)
        .map(({ index }) => index);
      this.routeSelection = null;
      if (!court || !sel.length || sel.some(i => this.slotDisabled(court.id, this.slots[i]))) return;
      this.selectedSlotKeys = sel.map(i => this.slotKey(court.id, this.slots[i]));
      this.setActivePeriodByIndex(sel[0]);
      if (this.isLoggedIn) {
        await this.checkAvailability();
      }
    },
    setDateQuick(d) {
      this.bookingDate = d;
      this.onDateChange();
    },
    async onCustomDateSelect(newDate) {
      if (!newDate) return;
      this.bookingDate = newDate;
      await this.onDateChange();
    },
    async onDateChange() {
      this.clearSelection();
      await this.loadSchedule();
    },
    selectedServiceQty(serviceId) {
      return this.selectedServicesMap[serviceId]?.quantity || 0;
    },
    updateServiceQty(srv, delta) {
      const current = this.selectedServicesMap[srv.id]?.quantity || 0;
      const next = Math.max(0, current + delta);
      if (next === 0) {
        const copy = { ...this.selectedServicesMap };
        delete copy[srv.id];
        this.selectedServicesMap = copy;
      } else {
        this.selectedServicesMap = {
          ...this.selectedServicesMap,
          [srv.id]: {
            service_id: srv.id,
            name: srv.name,
            price: Number(srv.price || 0),
            unit: srv.unit || "lượt",
            quantity: next,
          },
        };
      }
    },
    async submit() {
      if (!this.canSubmit) return;
      if (!getAuth()) {
        this.submitError = "";
        this.$router.push({ name: "login", query: { redirect: this.$route.fullPath } });
        return;
      }
      this.submitting = true;
      this.submitError = "";
      try {
        const ranges = this.selectedSlotRanges.map(r => ({
          venue_court_id: r.venue_court_id,
          start_time: r.start_time,
          end_time: r.end_time,
        }));
        const first = ranges[0];
        const selectedServices = Object.values(this.selectedServicesMap).map(item => ({
          service_id: item.service_id,
          quantity: item.quantity
        }));

        const booking = await bookingService.createBooking({
          venue_court_id: first.venue_court_id,
          booking_date: this.bookingDate,
          start_time: first.start_time,
          end_time: first.end_time,
          ...(ranges.length > 1 ? { time_ranges: ranges } : {}),
          ...(selectedServices.length > 0 ? { selected_services: selectedServices } : {}),
          payment_option: this.paymentOption,
          venue_voucher_id: this.venueVoucher?.id || null,
          vip_voucher_id: this.vipVoucher?.id || null,
        });
        this.$router.push({ name: "booking-detail", params: { id: booking.id } });
      } catch (err) {
        this.submitError = err.message || "Không thể tạo đơn đặt sân. Vui lòng thử lại.";
        await this.loadSchedule();
      } finally {
        this.submitting = false;
      }
    },
    slotKey(courtId, slot) {
      return `${courtId}|${slot?.start_time || ""}`;
    },
    slotEntriesFromKeys(keys = []) {
      return keys
        .map(key => {
          const [courtId, startTime] = key.split("|");
          const court = this.courts.find(c => String(c.id) === String(courtId));
          const slot = this.slots.find(s => s.start_time === startTime);
          return court && slot ? { courtId, court, slot } : null;
        })
        .filter(Boolean)
        .sort((a, b) => {
          const cs = String(a.court.name || "").localeCompare(String(b.court.name || ""));
          return cs !== 0 ? cs : this.minutes(a.slot.start_time) - this.minutes(b.slot.start_time);
        });
    },
    slotRangesFromKeys(keys = []) {
      const ranges = [];
      this.slotEntriesFromKeys(keys).forEach(({ courtId, court, slot }) => {
        const cur = ranges[ranges.length - 1];
        if (!cur || String(cur.venue_court_id) !== String(courtId) || cur.end_time !== slot.start_time) {
          ranges.push({ venue_court_id: courtId, court, start_time: slot.start_time, end_time: slot.end_time });
          return;
        }
        cur.end_time = slot.end_time;
      });
      return ranges;
    },
    rangeListText(ranges = []) {
      if (!ranges.length) return "Chưa chọn";
      if (ranges.length <= 2) {
        return ranges.map(r => `${r.court?.name || "Sân"}: ${this.shortTime(r.start_time)}–${this.shortTime(r.end_time)}`).join("; ");
      }
      return `${ranges.length} khung giờ trên ${this.selectedCourts.length} sân`;
    },
    minutes(time) {
      const [h, m] = String(time || "00:00").slice(0, 5).split(":").map(Number);
      return h * 60 + m;
    },
    normalizeTime(value) {
      const raw = String(value || "");
      return /^\d{2}:\d{2}$/.test(raw) ? `${raw}:00` : raw;
    },
    shortTime(value) {
      return String(value || "").slice(0, 5);
    },
    formatDate(value) {
      if (!value) return "-";
      const [y, m, d] = value.split("-");
      return `${d}/${m}/${y}`;
    },
    money(value) {
      return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    compactMoney(value) {
      const n = Number(value || 0);
      return n >= 1000 ? `${Math.round(n / 1000)}k` : n || "";
    },
  },
};
</script>

<style scoped>
<style scoped>
/* ===== BASE LAYOUT ===== */
.cbw-page {
  min-height: 100vh;
  background: #ffffff;
  color: #0f172a;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  font-size: 14px;
}

.cbw-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
}

/* ===== STEP PROGRESS INDICATOR ===== */
.cbw-steps {
  display: flex;
  gap: 0;
  align-items: stretch;
  padding: 0 0 16px 0;
  margin-bottom: 24px;
  background: transparent;
  border: none;
  border-bottom: 1px solid #f1f5f9;
  border-radius: 0;
  box-shadow: none;
}

.cbw-step {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  padding: 10px 16px;
  opacity: 0.45;
  transition: opacity 0.2s ease;
  position: relative;
}

.cbw-step.is-active,
.cbw-step.is-done {
  opacity: 1;
}

.cbw-step-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #475569;
  font-size: 13px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cbw-step.is-active .cbw-step-num {
  background: #15803d;
  color: #ffffff;
}

.cbw-step.is-done .cbw-step-num {
  background: #dcfce7;
  color: #15803d;
}

.cbw-step-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cbw-step-label {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  line-height: 1.2;
}

.cbw-step-hint {
  font-size: 12px;
  color: #64748b;
  line-height: 1.3;
}

/* ===== LOADING SPINNER ===== */
.cbw-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 100px 0;
  color: #475569;
  font-size: 14px;
}

.cbw-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #15803d;
  border-radius: 50%;
  animation: cbw-spin 0.75s linear infinite;
}

@keyframes cbw-spin {
  to { transform: rotate(360deg); }
}

/* ===== WORKSPACE GRID LAYOUT ===== */
.cbw-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 32px;
  align-items: start;
  min-width: 0;
}

.cbw-workspace.is-fullwidth {
  grid-template-columns: minmax(0, 1fr);
}

/* ===== CLEAN COMPACT TOOLBAR ===== */
.cbw-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.cbw-date-selector {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.cbw-date-lbl {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

.cbw-date-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 13.5px;
  color: #0f172a;
  background: #ffffff;
  outline: none;
  font-weight: 400;
}

.cbw-date-input:focus {
  border-color: #15803d;
}

.cbw-quick-dates {
  display: flex;
  gap: 6px;
}

.cbw-quick-btn {
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cbw-quick-btn:hover {
  border-color: #15803d;
  color: #15803d;
}

.cbw-quick-btn.is-active {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.cbw-period-tabs {
  display: flex;
  gap: 6px;
}

.cbw-period-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  font-size: 13px;
  color: #475569;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.15s ease;
}

.cbw-period-btn:hover:not(.is-active) {
  border-color: #15803d;
  color: #15803d;
}

.cbw-period-btn.is-active {
  color: #ffffff;
  background: #15803d;
  border-color: #15803d;
}

.cbw-period-range {
  font-size: 11.5px;
  opacity: 0.85;
  font-weight: 400;
}

/* ===== STATE MESSAGES ===== */
.cbw-state-msg {
  text-align: center;
  padding: 50px 16px;
  color: #475569;
  font-size: 14px;
  font-weight: 400;
}

.cbw-state-msg--error {
  color: #dc2626;
}

/* ===== MATRIX TABLE ===== */
.cbw-schedule-panel {
  background: transparent;
  border: none;
  border-radius: 0;
  padding: 0;
  box-shadow: none;
  min-width: 0;
  overflow: hidden;
}

.cbw-matrix-wrap {
  overflow-x: auto;
  overflow-y: auto;
  border: 1px solid #f1f5f9;
  border-radius: 6px;
  margin-bottom: 14px;
  max-height: 560px;
  width: 100%;
  max-width: 100%;
}

.cbw-matrix {
  width: 100%;
  border-collapse: collapse;
  min-width: 550px;
}

.cbw-th-corner {
  padding: 10px 14px;
  text-align: left;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  background: #ffffff;
  white-space: nowrap;
  position: sticky;
  top: 0;
  left: 0;
  z-index: 3;
  border-bottom: 2px solid #15803d;
  border-right: 1px solid #f1f5f9;
}

.cbw-th-court {
  padding: 10px 14px;
  text-align: center;
  background: #ffffff;
  min-width: 140px;
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 2;
  border-bottom: 2px solid #15803d;
  border-right: 1px solid #f1f5f9;
}

.cbw-th-court strong {
  display: block;
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

.cbw-th-court span {
  display: block;
  font-size: 11.5px;
  color: #64748b;
  font-weight: 400;
  margin-top: 2px;
}

.cbw-td-time {
  padding: 0 14px;
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
  background: #ffffff;
  white-space: nowrap;
  position: sticky;
  left: 0;
  z-index: 1;
  border-right: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
  height: 44px;
  vertical-align: middle;
}

.cbw-td-slot {
  padding: 4px;
  vertical-align: stretch;
  border-bottom: 1px solid #f1f5f9;
  border-right: 1px solid #f1f5f9;
}

.cbw-td-empty {
  padding: 40px 16px;
  text-align: center;
  color: #64748b;
  font-size: 13.5px;
  font-weight: 400;
}

/* ===== SLOT BUTTONS ===== */
.cbw-slot-btn {
  width: 100%;
  height: 100%;
  min-height: 38px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  font-size: 12.5px;
  font-weight: 500;
  color: #15803d;
  transition: all 0.15s ease;
}

.cbw-slot-btn:hover:not(:disabled):not(.is-selected) {
  border-color: #15803d;
  background: #ffffff;
}

.cbw-slot-btn.is-selected {
  background: #15803d;
  border-color: #15803d;
  color: #ffffff;
  font-weight: 500;
}

.cbw-slot-btn.is-booked,
.cbw-slot-btn.is-past {
  background: #ffffff;
  border-color: #f1f5f9;
  color: #cbd5e1;
  cursor: not-allowed;
}

.cbw-slot-btn.is-locked {
  background: #ffffff;
  border-color: #fecaca;
  color: #ef4444;
  cursor: not-allowed;
}

.cbw-slot-label {
  font-size: 12px;
  font-weight: 500;
  color: #ffffff;
}

.cbw-slot-price {
  font-size: 12px;
  font-weight: 500;
  color: #15803d;
}

.cbw-slot-disabled-text {
  font-size: 11.5px;
  color: #cbd5e1;
  font-weight: 400;
}

.cbw-slot-btn.is-locked .cbw-slot-disabled-text {
  color: #f87171;
  font-size: 11px;
}

/* ===== SELECTION FEEDBACK BAR ===== */
.cbw-sel-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #f0fdf4;
  border: 1.5px solid #bbf7d0;
  border-radius: 10px;
  gap: 12px;
  flex-wrap: wrap;
 /* ===== RIGHT SUMMARY PANEL ===== */
.cbw-summary-panel {
  border: none;
  border-radius: 8px;
  padding: 20px;
  background: #f8fafc;
  position: sticky;
  top: 24px;
  display: flex;
  flex-direction: column;
  box-shadow: none;
}

.cbw-summary-header {
  margin-bottom: 6px;
}

.cbw-summary-label {
  display: inline-block;
  font-size: 11px;
  font-weight: 500;
  color: #15803d;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  background: transparent;
  padding: 0;
  margin-bottom: 4px;
}

.cbw-summary-title {
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 4px;
}

.cbw-summary-sub {
  font-size: 12.5px;
  color: #475569;
  margin: 0;
  line-height: 1.5;
}

.cbw-divider {
  height: 1px;
  background: #e2e8f0;
  margin: 16px 0;
}

/* ===== FACTS ===== */
.cbw-facts {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.cbw-fact-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  gap: 8px;
}

.cbw-fact-row span {
  color: #475569;
  flex-shrink: 0;
}

.cbw-fact-row strong {
  color: #0f172a;
  font-weight: 500;
  text-align: right;
}

/* ===== PRICE ROWS ===== */
.cbw-price-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cbw-price-head {
  font-size: 11.5px;
  font-weight: 500;
  color: #475569;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.cbw-price-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
  gap: 8px;
  padding: 6px 10px;
  background: #ffffff;
  border-radius: 4px;
}

.cbw-price-row span {
  color: #475569;
  flex: 1;
  min-width: 0;
  line-height: 1.3;
}

.cbw-price-row strong {
  color: #0f172a;
  font-weight: 500;
  flex-shrink: 0;
}

/* ===== DISCOUNT & VOUCHER ===== */
.cbw-discount-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cbw-discount-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
}

.cbw-discount-val {
  color: #15803d;
  font-weight: 500;
}

.cbw-voucher-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 10px 13px;
  font-size: 13px;
  color: #0f172a;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
  text-align: left;
  font-weight: 500;
  transition: border-color 0.15s, background 0.15s;
}

.cbw-voucher-btn:hover {
  border-color: #15803d;
}

.cbw-voucher-count {
  font-size: 11.5px;
  color: #64748b;
  font-weight: 400;
}

.cbw-voucher-notice {
  font-size: 12px;
  color: #b45309;
  margin: 0;
}

.cbw-voucher-list {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.cbw-voucher-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 13px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  cursor: pointer;
  font-size: 13px;
  color: #0f172a;
  text-align: left;
  transition: border-color 0.15s, background 0.15s;
}

.cbw-voucher-item:hover:not(.is-active) {
  border-color: #15803d;
}

.cbw-voucher-item.is-active {
  border-color: #15803d;
  background: #ffffff;
}

.cbw-voucher-item > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cbw-voucher-item > div strong {
  font-weight: 500;
  color: #0f172a;
  font-size: 13px;
}

.cbw-voucher-item > div span {
  font-size: 11.5px;
  color: #64748b;
}

.cbw-voucher-item > strong {
  color: #15803d;
  font-weight: 500;
  flex-shrink: 0;
}

.cbw-voucher-empty {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
  text-align: center;
  padding: 8px;
}

/* ===== PAYMENT SECTION ===== */
.cbw-payment-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cbw-payment-title {
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.cbw-wallet-bal {
  font-size: 12.5px;
  color: #475569;
  margin: 0;
  padding: 8px 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
}

.cbw-wallet-bal strong {
  color: #15803d;
  font-weight: 500;
}

.cbw-payment-opts {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.cbw-payment-opt {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  cursor: pointer;
  transition: border-color 0.15s;
}

.cbw-payment-opt:hover:not(.is-active):not(.is-disabled) {
  border-color: #15803d;
}

.cbw-payment-opt input[type="radio"] {
  width: 16px;
  height: 16px;
  margin-top: 3px;
  accent-color: #15803d;
  flex-shrink: 0;
  cursor: pointer;
}

.cbw-payment-opt > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cbw-payment-opt > div strong {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
  line-height: 1.3;
}

.cbw-payment-opt > div span {
  font-size: 11.5px;
  color: #64748b;
  line-height: 1.4;
}

.cbw-payment-opt.is-active {
  border-color: #15803d;
}

.cbw-payment-opt.is-active > div strong {
  color: #15803d;
}

.cbw-payment-opt.is-disabled {
  opacity: 0.42;
  cursor: not-allowed;
}

/* ===== TOTAL & SUBMIT ===== */
.cbw-total-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 14px;
  color: #0f172a;
  margin-bottom: 4px;
  font-weight: 500;
}

.cbw-total-val {
  font-size: 20px;
  font-weight: 600;
  color: #15803d;
}

.cbw-required-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
  color: #475569;
  margin-bottom: 14px;
  background: #ffffff;
  border-radius: 4px;
  padding: 8px 12px;
}

.cbw-required-row strong {
  color: #0f172a;
  font-weight: 500;
}

.cbw-submit-error {
  font-size: 12.5px;
  color: #dc2626;
  margin: 0 0 10px;
  padding: 8px 12px;
  background: #ffffff;
  border-radius: 4px;
  border: 1px solid #fecaca;
}

.cbw-submit-btn {
  width: 100%;
  padding: 12px;
  background: #15803d;
  color: #ffffff;
  font-size: 14px;
  font-weight: 500;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: opacity 0.2s;
}

.cbw-submit-btn:hover:not(:disabled) {
  background: #166534;
}

.cbw-submit-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}ght: 700;
}

.cbw-submit-error {
  font-size: 12.5px;
  color: #dc2626;
  margin: 0 0 10px;
  padding: 8px 12px;
  background: #fef2f2;
  border-radius: 8px;
  border: 1px solid #fecaca;
}

.cbw-submit-btn {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, #16a34a, #15803d);
  color: #ffffff;
  font-size: 14.5px;
  font-weight: 700;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  letter-spacing: 0.01em;
  box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
  transition: opacity 0.2s, transform 0.1s;
}

.cbw-submit-btn:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
}

.cbw-submit-btn:disabled {
  opacity: 0.38;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.cbw-hold-note {
  font-size: 11.5px;
  color: #9ca3af;
  margin: 10px 0 0;
  text-align: center;
  line-height: 1.5;
}

/* ===== SKELETON LOADING ===== */
.cbw-skel {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
  padding: 16px;
}

.cbw-skel span {
  height: 44px;
  border-radius: 8px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: cbw-shimmer 1.4s infinite;
}

@keyframes cbw-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ===== SERVICES ADD-ON SECTION ===== */
.cbw-services-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cbw-services-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cbw-service-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 8px;
  gap: 12px;
}

.cbw-srv-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cbw-srv-name {
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 500;
}

.cbw-srv-price {
  font-size: 12px;
  color: #15803d;
  font-weight: 500;
}

.cbw-srv-qty {
  display: flex;
  align-items: center;
  gap: 8px;
}

.cbw-qty-btn {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
  font-weight: 600;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cbw-qty-btn:hover:not(:disabled) {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.cbw-qty-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.cbw-qty-val {
  font-size: 13.5px;
  font-weight: 600;
  color: #0f172a;
  min-width: 16px;
  text-align: center;
}

/* ===== RESPONSIVE STYLES ===== */
@media (max-width: 1024px) {
  .cbw-workspace {
    grid-template-columns: 1fr;
  }
  .cbw-summary-panel {
    position: static;
  }
}

@media (max-width: 640px) {
  .cbw-main {
    padding: 16px 14px 60px;
  }
  .cbw-steps {
    border-radius: 10px;
    flex-direction: column;
  }
  .cbw-step {
    border-right: none;
    border-bottom: 1px solid #e2e8f0;
  }
  .cbw-step:last-child {
    border-bottom: none;
  }
  .cbw-filters {
    grid-template-columns: 1fr;
  }
  .cbw-period-tabs {
    width: 100%;
  }
  .cbw-period-btn {
    flex: 1;
    justify-content: center;
  }
}
</style>

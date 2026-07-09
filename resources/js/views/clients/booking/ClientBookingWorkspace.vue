<template>
    <div class="client-booking">
        <PublicNavbar />

        <main class="booking-shell">
            <header class="page-head">
                <div>
                    <router-link to="/venues">Tìm sân</router-link>
                    <span>/</span>
                    <strong>Đặt sân</strong>
                </div>
                <h1>{{ currentCluster?.name || "Đặt sân trực tuyến" }}</h1>
                <p>Chọn sân và thời gian trực tiếp trên lịch. Giá và số tiền cần trả được cập nhật ngay.</p>
            </header>

            <nav class="flow-steps" aria-label="Tiến trình đặt sân">
                <div v-for="step in steps" :key="step.id" :class="{ active: activeStep === step.id, done: activeStep > step.id }">
                    <span>{{ activeStep > step.id ? "✓" : step.id }}</span>
                    <section>
                        <strong>{{ step.label }}</strong>
                        <small>{{ step.hint }}</small>
                    </section>
                </div>
            </nav>

            <div v-if="initialLoading" class="loading-panel">
                <i></i>
                <span>Đang chuẩn bị lịch sân...</span>
            </div>

            <div v-else class="workspace">
                <section class="schedule-workspace">
                    <header class="workspace-head">
                        <div>
                            <span class="eyebrow">LỊCH SÂN</span>
                            <h2>Chọn ngày và khung giờ</h2>
                        </div>
                        <div class="config-chips">
                            <span>{{ operatingHoursText }}</span>
                            <span>Tối thiểu {{ minDuration }} phút</span>
                        </div>
                    </header>

                    <div class="booking-filters">
                        <label>
                            <span>Cụm sân</span>
                            <select v-model="clusterId" :disabled="clusterLocked" @change="changeCluster">
                                <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
                                    {{ cluster.name }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span>Ngày chơi</span>
                            <div class="date-control">
                                <button type="button" aria-label="Ngày trước" @click="shiftDate(-1)">‹</button>
                                <input v-model="bookingDate" type="date" :min="today" @change="changeDate" />
                                <button type="button" aria-label="Ngày sau" @click="shiftDate(1)">›</button>
                            </div>
                        </label>
                        <label>
                            <span>Loại sân</span>
                            <select v-model="courtTypeId" @change="changeCourtType">
                                <option value="">Tất cả loại sân</option>
                                <option v-for="type in courtTypes" :key="type.id" :value="String(type.id)">
                                    {{ type.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="legend">
                        <span><i class="free"></i>Trống</span>
                        <span><i class="selected"></i>Đang chọn</span>
                        <span><i class="booked"></i>Đã đặt</span>
                        <span><i class="locked"></i>Khóa sân</span>
                        <span><i class="past"></i>Không thể đặt</span>
                    </div>

                    <div v-if="scheduleLoading" class="schedule-skeleton">
                        <i v-for="item in 12" :key="item"></i>
                    </div>
                    <div v-else-if="scheduleError" class="state-panel error">{{ scheduleError }}</div>
                    <div v-else-if="!courts.length" class="state-panel">Không có sân phù hợp với bộ lọc.</div>
                    <div v-else class="schedule-board">
                        <div class="timeline" :style="gridStyle">
                            <div class="court-heading sticky">Sân / giờ</div>
                            <div v-for="slot in slots" :key="slot.start_time" class="time-heading">
                                {{ shortTime(slot.start_time) }}
                            </div>

                            <template v-for="court in courts" :key="court.id">
                                <button type="button" class="court-name sticky" @click="focusCourt(court)">
                                    <strong>{{ court.name }}</strong>
                                    <span>{{ court.court_type?.name || "Sân thể thao" }}</span>
                                </button>
                                <button
                                    v-for="(slot, index) in slots"
                                    :key="`${court.id}-${slot.start_time}`"
                                    type="button"
                                    class="slot"
                                    :class="slotClasses(court.id, slot, index)"
                                    :disabled="slotDisabled(court.id, slot)"
                                    :title="slotTitle(court, slot)"
                                    @click="toggleSlot(court, index)"
                                >
                                    <small v-if="!slotDisabled(court.id, slot)">
                                        {{ compactMoney(slotStatus(court.id, slot)?.price) }}
                                    </small>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="selection-feedback" :class="{ error: validationError }">
                        <div>
                            <strong>{{ validationError ? "Cần điều chỉnh" : selectionTitle }}</strong>
                            <span>{{ validationError || selectionHint }}</span>
                        </div>
                        <button v-if="selectedIndexes.length" type="button" @click="clearSelection">Bỏ chọn</button>
                    </div>
                </section>

                <aside class="booking-summary">
                    <header>
                        <span class="eyebrow">TÓM TẮT BOOKING</span>
                        <h2>{{ selectedCourt?.name || "Chưa chọn sân" }}</h2>
                        <p>{{ selectedCourt?.court_type?.name || "Chọn một khung giờ trống để bắt đầu." }}</p>
                    </header>

                    <dl class="booking-facts">
                        <div><dt>Ngày chơi</dt><dd>{{ formatDate(bookingDate) }}</dd></div>
                        <div><dt>Khung giờ</dt><dd>{{ selectedTimeText }}</dd></div>
                        <div><dt>Thời lượng</dt><dd>{{ duration ? `${duration} phút` : "-" }}</dd></div>
                    </dl>

                    <section v-if="selectedDetails.length" class="price-breakdown">
                        <header><strong>Chi tiết giá</strong><span>{{ selectedDetails.length }} ô</span></header>
                        <div v-for="slot in selectedDetails" :key="slot.start_time">
                            <span>{{ shortTime(slot.start_time) }} - {{ shortTime(slot.end_time) }}</span>
                            <strong>{{ money(slot.price) }}</strong>
                        </div>
                    </section>

                    <section v-if="available" class="discount-section">
                        <div v-if="membershipDiscount > 0">
                            <span>Ưu đãi thành viên</span>
                            <strong>-{{ money(membershipDiscount) }}</strong>
                        </div>
                        <button type="button" @click="voucherOpen = !voucherOpen">
                            <span>{{ selectedVoucherCount ? `${selectedVoucherCount} voucher đã chọn` : "Chọn voucher" }}</span>
                            <strong v-if="voucherDiscount">-{{ money(voucherDiscount) }}</strong>
                            <em v-else>{{ eligibleVouchers.length }} mã</em>
                        </button>
                        <div v-if="voucherOpen" class="voucher-list">
                            <button
                                v-for="voucher in eligibleVouchers"
                                :key="voucher.id"
                                type="button"
                                :class="{ active: voucherSelected(voucher) }"
                                @click="toggleVoucher(voucher)"
                            >
                                <span><strong>{{ voucher.code }}</strong><small>{{ voucher.name }}</small></span>
                                <em>-{{ money(voucherValue(voucher)) }}</em>
                            </button>
                            <p v-if="!eligibleVouchers.length">Không có voucher phù hợp.</p>
                        </div>
                    </section>

                    <section v-if="available" class="payment-section">
                        <h3>Hình thức thanh toán</h3>
                        <label v-for="option in paymentOptions" :key="option.value" :class="{ active: paymentOption === option.value }">
                            <input v-model="paymentOption" type="radio" :value="option.value" />
                            <span><strong>{{ option.label }}</strong><small>{{ option.hint }}</small></span>
                        </label>
                    </section>

                    <footer>
                        <div><span>Tổng tiền</span><strong>{{ money(total) }}</strong></div>
                        <div v-if="paymentOption !== 'no_prepay'" class="required">
                            <span>Cần thanh toán ngay</span><strong>{{ money(requiredAmount) }}</strong>
                        </div>
                        <p v-if="submitError">{{ submitError }}</p>
                        <button type="button" :disabled="!canSubmit || submitting" @click="submit">
                            {{ submitting ? "Đang tạo booking..." : "Xác nhận đặt sân" }}
                        </button>
                        <small v-if="paymentOption !== 'no_prepay'">
                            Sân được giữ {{ config.slot_hold_minutes || 20 }} phút để thanh toán.
                        </small>
                    </footer>
                </aside>
            </div>
        </main>
    </div>
</template>

<script>
import PublicNavbar from "../../../components/PublicNavbar.vue";
import { bookingService } from "../../../services/bookingService.js";
import { getAuth } from "../../../stores/auth.js";

export default {
    name: "ClientBookingWorkspace",
    components: { PublicNavbar },
    data() {
        return {
            steps: [
                { id: 1, label: "Chọn lịch", hint: "Ngày, sân và khung giờ" },
                { id: 2, label: "Kiểm tra giá", hint: "Ưu đãi và voucher" },
                { id: 3, label: "Xác nhận", hint: "Hình thức thanh toán" },
            ],
            clusters: [],
            clusterId: "",
            clusterLocked: false,
            courtTypeId: "",
            bookingDate: new Date().toLocaleDateString("en-CA"),
            initialLoading: true,
            scheduleLoading: false,
            scheduleError: "",
            slots: [],
            courts: [],
            statuses: [],
            operatingHours: null,
            selectedCourtId: "",
            selectedIndexes: [],
            selectionError: "",
            checking: false,
            available: false,
            preview: null,
            paymentOption: "no_prepay",
            _venueVouchers: [],
            _vipVouchers: [],
            selectedVenueVoucherId: "",
            selectedVipVoucherId: "",
            voucherOpen: false,
            submitting: false,
            submitError: "",
            routeSelection: null,
        };
    },
    computed: {
        today() {
            return new Date().toLocaleDateString("en-CA");
        },
        currentCluster() {
            return this.clusters.find(item => String(item.id) === String(this.clusterId)) || null;
        },
        config() {
            return this.currentCluster?.booking_config || {};
        },
        courtTypes() {
            const types = new Map();
            (this.currentCluster?.venue_courts || []).forEach(court => {
                if (court.court_type?.id) types.set(String(court.court_type.id), court.court_type);
            });
            return [...types.values()];
        },
        selectedCourt() {
            return this.courts.find(item => String(item.id) === String(this.selectedCourtId)) || null;
        },
        selectedDetails() {
            return this.selectedIndexes.map(index => this.slotStatus(this.selectedCourtId, this.slots[index])).filter(Boolean);
        },
        duration() {
            return this.selectedIndexes.length * 30;
        },
        minDuration() {
            return Number(this.config.min_duration_minutes || 30);
        },
        maxDuration() {
            const value = Number(this.config.max_duration_minutes || 0);
            return value > 0 ? value : null;
        },
        minAdvance() {
            return Number(this.config.min_advance_booking_minutes || 0);
        },
        startTime() {
            if (!this.selectedIndexes.length) return "";
            return this.slots[Math.min(...this.selectedIndexes)]?.start_time || "";
        },
        endTime() {
            if (!this.selectedIndexes.length) return "";
            return this.slots[Math.max(...this.selectedIndexes)]?.end_time || "";
        },
        selectedTimeText() {
            return this.startTime ? `${this.shortTime(this.startTime)} - ${this.shortTime(this.endTime)}` : "-";
        },
        selectionTitle() {
            return this.selectedIndexes.length ? `${this.selectedCourt?.name} · ${this.duration} phút` : "Chọn khung giờ trống";
        },
        selectionHint() {
            return this.selectedIndexes.length
                ? `${this.selectedTimeText} · ${this.money(this.baseAmount)}`
                : `Chọn tối thiểu ${this.minDuration} phút liên tiếp trên cùng một sân.`;
        },
        validationError() {
            if (this.selectionError) return this.selectionError;
            if (!this.selectedIndexes.length) return "";
            if (this.duration < this.minDuration) return `Thời lượng tối thiểu là ${this.minDuration} phút.`;
            if (this.maxDuration && this.duration > this.maxDuration) return `Thời lượng tối đa là ${this.maxDuration} phút.`;
            if (!this.available && !this.checking) return "Khung giờ không còn khả dụng.";
            return "";
        },
        baseAmount() {
            return this.selectedDetails.reduce((sum, item) => sum + Number(item.price || 0), 0);
        },
        membershipDiscount() {
            return Number(this.preview?.membership_discount_amount || 0);
        },
        afterMembership() {
            return Number(this.preview?.final_amount ?? Math.max(this.baseAmount - this.membershipDiscount, 0));
        },
        eligibleVouchers() {
            return [...this.venueVouchers, ...this.vipVouchers];
        },
        venueVouchers() {
            return this._venueVouchers || [];
        },
        vipVouchers() {
            return this._vipVouchers || [];
        },
        selectedVoucherCount() {
            return Number(Boolean(this.selectedVenueVoucherId)) + Number(Boolean(this.selectedVipVoucherId));
        },
        venueVoucher() {
            return this.venueVouchers.find(item => item.id === this.selectedVenueVoucherId);
        },
        vipVoucher() {
            return this.vipVouchers.find(item => item.id === this.selectedVipVoucherId);
        },
        voucherDiscount() {
            const venue = this.voucherValue(this.venueVoucher, this.afterMembership);
            return venue + this.voucherValue(this.vipVoucher, Math.max(this.afterMembership - venue, 0));
        },
        total() {
            return Math.max(this.afterMembership - this.voucherDiscount, 0);
        },
        requiredAmount() {
            if (this.paymentOption === "full_payment") return this.total;
            if (this.paymentOption === "deposit") return this.total * (Number(this.config.deposit_percent || 30) / 100);
            return 0;
        },
        paymentOptions() {
            return [
                this.config.allow_no_prepay !== false && { value: "no_prepay", label: "Thanh toán tại sân", hint: "Không cần trả trước" },
                this.config.allow_deposit && { value: "deposit", label: `Đặt cọc ${this.config.deposit_percent || 30}%`, hint: "Giữ sân bằng tiền cọc" },
                this.config.allow_full_payment !== false && { value: "full_payment", label: "Thanh toán toàn bộ", hint: "Thanh toán online 100%" },
            ].filter(Boolean);
        },
        canSubmit() {
            return this.available && !this.validationError && this.paymentOption && !this.submitting;
        },
        activeStep() {
            if (this.available) return 3;
            if (this.selectedIndexes.length) return 2;
            return 1;
        },
        gridStyle() {
            return { gridTemplateColumns: `168px repeat(${this.slots.length}, 58px)` };
        },
        operatingHoursText() {
            if (!this.operatingHours?.is_open) return "Đóng cửa";
            return `${this.shortTime(this.operatingHours.open_time)} - ${this.shortTime(this.operatingHours.close_time)}`;
        },
    },
    async mounted() {
        if (!getAuth()) {
            this.$router.push("/login");
            return;
        }
        await this.initialize();
    },
    methods: {
        async initialize() {
            try {
                const query = this.$route.query || {};
                const response = await bookingService.getInitData();
                this.clusters = response.clusters || [];
                const requested = query.venue_cluster_id || query.cluster;
                this.clusterId = this.clusters.find(item => String(item.id) === String(requested))?.id || this.clusters[0]?.id || "";
                this.clusterLocked = Boolean(query.venue_cluster_id);
                this.bookingDate = String(query.booking_date || query.date || this.today);
                this.courtTypeId = String(query.court_type || "");
                this.routeSelection = query.venue_court_id
                    ? { courtId: query.venue_court_id, start: this.time(query.start_time), end: this.time(query.end_time) }
                    : null;
                this.ensurePaymentOption();
                await this.loadSchedule();
            } catch (error) {
                this.scheduleError = error.message || "Không thể tải dữ liệu đặt sân.";
            } finally {
                this.initialLoading = false;
            }
        },
        async loadSchedule() {
            if (!this.clusterId || !this.bookingDate) return;
            this.scheduleLoading = true;
            this.scheduleError = "";
            try {
                const params = { venue_cluster_id: this.clusterId, booking_date: this.bookingDate };
                if (this.courtTypeId) params.court_type_id = this.courtTypeId;
                const response = await bookingService.getSchedule(params);
                this.slots = response.time_slots || [];
                this.courts = response.courts || [];
                this.statuses = response.slot_statuses || [];
                this.operatingHours = response.operating_hours || null;
                await this.applyRouteSelection();
            } catch (error) {
                this.scheduleError = error.message || "Không thể tải lịch sân.";
            } finally {
                this.scheduleLoading = false;
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
        shiftDate(days) {
            const date = new Date(`${this.bookingDate}T00:00:00`);
            date.setDate(date.getDate() + days);
            const next = date.toLocaleDateString("en-CA");
            if (next < this.today) return;
            this.bookingDate = next;
            this.changeDate();
        },
        slotStatus(courtId, slot) {
            return this.statuses.find(item => String(item.venue_court_id) === String(courtId) && item.start_time === slot?.start_time);
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
        slotClasses(courtId, slot, index) {
            const status = this.slotStatus(courtId, slot);
            return {
                selected: String(this.selectedCourtId) === String(courtId) && this.selectedIndexes.includes(index),
                booked: status?.is_available === false && status?.busy_source !== "slot_lock",
                locked: status?.busy_source === "slot_lock",
                past: this.slotPast(slot) || this.slotTooSoon(slot),
            };
        },
        slotTitle(court, slot) {
            const status = this.slotStatus(court.id, slot);
            if (this.slotPast(slot)) return "Khung giờ đã qua.";
            if (this.slotTooSoon(slot)) return `Cần đặt trước ít nhất ${this.minAdvance} phút.`;
            if (status?.busy_source === "slot_lock") return status.lock_reason || "Sân đang tạm khóa.";
            if (status?.is_available === false) return "Khung giờ đã được đặt.";
            return `${court.name} · ${this.shortTime(slot.start_time)} · ${this.money(status?.price)}`;
        },
        async toggleSlot(court, index) {
            if (this.slotDisabled(court.id, this.slots[index])) return;
            this.selectionError = "";
            let indexes = [index];
            if (String(this.selectedCourtId) === String(court.id) && this.selectedIndexes.length) {
                const min = Math.min(...this.selectedIndexes);
                const max = Math.max(...this.selectedIndexes);
                if (this.selectedIndexes.includes(index)) indexes = [index];
                else if (index === min - 1) indexes = this.range(index, max);
                else if (index === max + 1) indexes = this.range(min, index);
                else {
                    this.selectionError = "Chỉ được chọn các khung giờ liên tiếp trên cùng một sân.";
                    return;
                }
            }
            if (this.maxDuration && indexes.length * 30 > this.maxDuration) {
                this.selectionError = `Thời lượng tối đa là ${this.maxDuration} phút.`;
                return;
            }
            if (indexes.some(item => this.slotDisabled(court.id, this.slots[item]))) {
                this.selectionError = "Khoảng vừa chọn có khung giờ không khả dụng.";
                return;
            }
            this.selectedCourtId = court.id;
            this.selectedIndexes = indexes;
            await this.checkAvailability();
        },
        focusCourt(court) {
            if (String(this.selectedCourtId) !== String(court.id)) this.clearSelection();
        },
        clearSelection() {
            this.selectedCourtId = "";
            this.selectedIndexes = [];
            this.selectionError = "";
            this.available = false;
            this.preview = null;
            this._venueVouchers = [];
            this._vipVouchers = [];
            this.selectedVenueVoucherId = "";
            this.selectedVipVoucherId = "";
        },
        async checkAvailability() {
            if (!this.selectedCourtId || !this.startTime || !this.endTime) return;
            this.checking = true;
            this.available = false;
            try {
                const response = await bookingService.checkAvailability({
                    venue_court_id: this.selectedCourtId,
                    booking_date: this.bookingDate,
                    start_time: this.startTime,
                    end_time: this.endTime,
                });
                this.available = Boolean(response.available);
                this.preview = {
                    ...(response.price_preview || {}),
                    membership_discount_amount: response.membership_discount?.discount_amount || response.price_preview?.membership_discount_amount || 0,
                };
                if (this.available) await this.loadVouchers();
            } catch (error) {
                this.selectionError = error.message || "Không thể kiểm tra khung giờ.";
            } finally {
                this.checking = false;
            }
        },
        async loadVouchers() {
            try {
                const response = await bookingService.eligibleVouchers({
                    venue_court_id: this.selectedCourtId,
                    booking_date: this.bookingDate,
                    start_time: this.startTime,
                    end_time: this.endTime,
                });
                this._venueVouchers = response.venue_vouchers || [];
                this._vipVouchers = response.vip_vouchers || [];
            } catch {
                this._venueVouchers = [];
                this._vipVouchers = [];
            }
        },
        voucherSelected(voucher) {
            return voucher.owner_type === "venue"
                ? this.selectedVenueVoucherId === voucher.id
                : this.selectedVipVoucherId === voucher.id;
        },
        toggleVoucher(voucher) {
            if (voucher.owner_type === "venue") {
                this.selectedVenueVoucherId = this.selectedVenueVoucherId === voucher.id ? "" : voucher.id;
            } else {
                this.selectedVipVoucherId = this.selectedVipVoucherId === voucher.id ? "" : voucher.id;
            }
        },
        voucherValue(voucher, amount = this.afterMembership) {
            if (!voucher) return 0;
            let value = voucher.discount_type === "percent"
                ? Number(amount) * Number(voucher.discount_value || 0) / 100
                : Number(voucher.discount_value || 0);
            if (voucher.max_discount_amount != null) value = Math.min(value, Number(voucher.max_discount_amount));
            return Math.min(Math.max(value, 0), Number(amount));
        },
        ensurePaymentOption() {
            const allowed = this.paymentOptions.map(item => item.value);
            if (!allowed.includes(this.paymentOption)) this.paymentOption = allowed[0] || "full_payment";
        },
        async applyRouteSelection() {
            if (!this.routeSelection) return;
            const court = this.courts.find(item => String(item.id) === String(this.routeSelection.courtId));
            const first = this.slots.findIndex(item => item.start_time === this.routeSelection.start);
            const last = this.slots.findIndex(item => item.end_time === this.routeSelection.end);
            const selection = first >= 0 && last >= first ? this.range(first, last) : [];
            this.routeSelection = null;
            if (!court || !selection.length || selection.some(index => this.slotDisabled(court.id, this.slots[index]))) return;
            this.selectedCourtId = court.id;
            this.selectedIndexes = selection;
            await this.checkAvailability();
        },
        async submit() {
            if (!this.canSubmit) return;
            this.submitting = true;
            this.submitError = "";
            try {
                const booking = await bookingService.createBooking({
                    venue_court_id: this.selectedCourtId,
                    booking_date: this.bookingDate,
                    start_time: this.startTime,
                    end_time: this.endTime,
                    payment_option: this.paymentOption,
                    venue_voucher_id: this.selectedVenueVoucherId || null,
                    vip_voucher_id: this.selectedVipVoucherId || null,
                });
                this.$router.push({ name: "booking-detail", params: { id: booking.id } });
            } catch (error) {
                this.submitError = error.message || "Không thể tạo booking.";
                await this.loadSchedule();
            } finally {
                this.submitting = false;
            }
        },
        range(start, end) {
            return Array.from({ length: end - start + 1 }, (_, offset) => start + offset);
        },
        minutes(time) {
            const [hour, minute] = String(time || "00:00").slice(0, 5).split(":").map(Number);
            return hour * 60 + minute;
        },
        time(value) {
            const raw = String(value || "");
            return /^\d{2}:\d{2}$/.test(raw) ? `${raw}:00` : raw;
        },
        shortTime(value) {
            return String(value || "").slice(0, 5);
        },
        formatDate(value) {
            if (!value) return "-";
            const [year, month, day] = value.split("-");
            return `${day}/${month}/${year}`;
        },
        money(value) {
            return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(value || 0));
        },
        compactMoney(value) {
            const amount = Number(value || 0);
            return amount >= 1000 ? `${Math.round(amount / 1000)}k` : amount || "";
        },
    },
};
</script>

<style scoped>
.client-booking{min-height:100vh;background:#f3f7f4;color:#17251c}.booking-shell{width:min(1540px,100%);margin:auto;padding:94px 24px 48px}.page-head{margin-bottom:18px}.page-head>div{display:flex;gap:8px;color:#65756b;font-size:12px}.page-head a{color:#168447;text-decoration:none;font-weight:800}.page-head h1{margin:10px 0 5px;font-size:32px;letter-spacing:0}.page-head p{margin:0;color:#65756b}.flow-steps{display:grid;grid-template-columns:repeat(3,1fr);margin-bottom:18px;border:1px solid #d6e3d8;border-radius:8px;overflow:hidden;background:#d6e3d8;gap:1px}.flow-steps>div{display:flex;align-items:center;gap:10px;padding:12px 14px;background:#fff}.flow-steps>div>span{display:grid;place-items:center;width:30px;height:30px;border:1px solid #c9d7cb;border-radius:50%;font-weight:900}.flow-steps section{display:grid;gap:2px}.flow-steps small{color:#718077}.flow-steps .active{background:#f0faf3}.flow-steps .active>span,.flow-steps .done>span{border-color:#22a653;background:#22a653;color:#fff}.workspace{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:18px;align-items:start}.schedule-workspace,.booking-summary{border:1px solid #d6e3d8;border-radius:8px;background:#fff;box-shadow:0 8px 24px rgba(35,65,43,.05)}.schedule-workspace{min-width:0;padding:18px}.workspace-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.workspace-head h2,.booking-summary h2{margin:3px 0 0;font-size:19px}.eyebrow{color:#168447;font-size:10px;font-weight:900;letter-spacing:.06em}.config-chips{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px}.config-chips span{padding:5px 8px;border-radius:999px;background:#e7f7eb;color:#15733a;font-size:11px;font-weight:800}.booking-filters{display:grid;grid-template-columns:1fr 1fr .8fr;gap:10px;margin:16px 0;padding:12px;border:1px solid #e0e9e1;border-radius:8px;background:#f7faf7}.booking-filters label{display:grid;gap:6px;min-width:0}.booking-filters label>span{color:#526158;font-size:11px;font-weight:800}.booking-filters select,.booking-filters input{box-sizing:border-box;width:100%;height:40px;border:1px solid #cedbd0;border-radius:7px;background:#fff;color:#17251c;padding:0 10px;font:inherit}.date-control{display:grid;grid-template-columns:36px 1fr 36px}.date-control button{border:1px solid #cedbd0;background:#fff;color:#15733a;font-size:19px}.date-control button:first-child{border-radius:7px 0 0 7px}.date-control button:last-child{border-radius:0 7px 7px 0}.date-control input{border-radius:0;border-inline:0}.legend{display:flex;flex-wrap:wrap;gap:10px 16px;margin-bottom:12px;color:#607066;font-size:11px;font-weight:700}.legend span{display:flex;align-items:center;gap:5px}.legend i{width:12px;height:12px;border:1px solid #bfd0c2;border-radius:3px}.legend .free{background:#eefaf1}.legend .selected{background:#22a653}.legend .booked{background:#fee2e2}.legend .locked{background:repeating-linear-gradient(-45deg,#9ca3af,#9ca3af 3px,#e5e7eb 3px,#e5e7eb 6px)}.legend .past{background:#edf1ed}.schedule-board{overflow:auto;border:1px solid #d6e3d8;border-radius:8px}.timeline{display:grid;min-width:max-content}.timeline>*{min-height:54px;border-right:1px solid #e0e8e1;border-bottom:1px solid #e0e8e1}.sticky{position:sticky;left:0;z-index:2}.court-heading,.time-heading{display:grid;place-items:center;background:#edf5ee;color:#4d5e53;font-size:11px;font-weight:900}.court-name{display:flex;flex-direction:column;justify-content:center;gap:3px;padding:8px 10px;border:0;background:#fff;text-align:left}.court-name strong{color:#17251c;font-size:12px}.court-name span{color:#6d7c72;font-size:10px}.slot{position:relative;border:0;border-right:1px solid #e0e8e1;border-bottom:1px solid #e0e8e1;background:#f4fbf5;cursor:pointer}.slot:hover:not(:disabled){background:#dcf5e2}.slot small{color:#267f43;font-size:9px;font-weight:850}.slot.selected{background:#22a653;box-shadow:inset 0 0 0 2px #168447}.slot.selected small{color:#fff}.slot.booked{background:#fee7e7}.slot.locked{background:repeating-linear-gradient(-45deg,#d1d5db,#d1d5db 6px,#eef0f2 6px,#eef0f2 12px)}.slot.past{background:#edf1ed}.slot:disabled{cursor:not-allowed}.selection-feedback{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;padding:11px 12px;border:1px solid #bfe6c8;border-radius:8px;background:#effbf2}.selection-feedback>div{display:grid;gap:2px}.selection-feedback strong{color:#176c36;font-size:12px}.selection-feedback span{color:#597063;font-size:11px}.selection-feedback button{color:#c62828;font-size:11px;font-weight:850}.selection-feedback.error{border-color:#fecaca;background:#fff3f3}.selection-feedback.error strong{color:#b91c1c}.booking-summary{position:sticky;top:86px;padding:18px}.booking-summary header p{margin:4px 0 0;color:#68786e;font-size:12px}.booking-facts{display:grid;gap:0;margin:16px 0}.booking-facts div{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px solid #e5ece6}.booking-facts dt{color:#68786e;font-size:12px}.booking-facts dd{margin:0;font-size:12px;font-weight:850;text-align:right}.price-breakdown,.discount-section,.payment-section{display:grid;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid #dfe8e0}.price-breakdown header,.price-breakdown>div,.discount-section>div{display:flex;justify-content:space-between;gap:10px;font-size:11px}.price-breakdown header span,.price-breakdown>div span{color:#68786e}.discount-section>div strong{color:#168447}.discount-section>button{display:flex;justify-content:space-between;gap:8px;padding:10px;border:1px solid #bfe2c6;border-radius:7px;background:#eef9f0;color:#184f2b;font-weight:800}.discount-section button em{font-style:normal;color:#168447}.voucher-list{display:grid;gap:6px}.voucher-list button{display:flex;justify-content:space-between;gap:8px;padding:9px;border:1px solid #d7e3d8;border-radius:7px;background:#fff;text-align:left}.voucher-list button.active{border-color:#22a653;background:#effbf2}.voucher-list button span{display:grid}.voucher-list small{color:#6c7b71}.voucher-list em{color:#168447;font-style:normal;font-weight:900}.payment-section h3{margin:0 0 2px;font-size:13px}.payment-section label{display:flex;gap:9px;padding:10px;border:1px solid #d7e3d8;border-radius:7px}.payment-section label.active{border-color:#22a653;background:#effbf2}.payment-section label span{display:grid}.payment-section label small{color:#6b7a70}.booking-summary footer{display:grid;gap:9px;margin-top:16px;padding-top:15px;border-top:1px solid #dfe8e0}.booking-summary footer>div{display:flex;justify-content:space-between}.booking-summary footer>div strong{font-size:18px}.booking-summary footer .required strong{color:#168447}.booking-summary footer>p{margin:0;padding:8px;border-radius:6px;background:#fff0f0;color:#b91c1c;font-size:11px}.booking-summary footer>button{min-height:44px;border:1px solid #22a653;border-radius:7px;background:#22a653;color:#fff;font-weight:900}.booking-summary footer>button:disabled{border-color:#d4ddd5;background:#e7ece8;color:#99a49c}.booking-summary footer>small{color:#6c7b71;text-align:center}.state-panel,.loading-panel{display:grid;place-items:center;min-height:180px;color:#68786e}.state-panel.error{color:#b91c1c}.schedule-skeleton{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.schedule-skeleton i{height:54px;border-radius:7px;background:linear-gradient(90deg,#edf2ee,#f8faf8,#edf2ee);background-size:200% 100%;animation:shimmer 1.2s infinite}@keyframes shimmer{to{background-position:-200% 0}}.loading-panel{gap:10px}.loading-panel i{width:30px;height:30px;border:3px solid #d9e5db;border-top-color:#22a653;border-radius:50%;animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:1050px){.workspace{grid-template-columns:1fr}.booking-summary{position:static}.booking-filters{grid-template-columns:1fr 1fr}.booking-filters label:last-child{grid-column:1/-1}}@media(max-width:680px){.booking-shell{padding:78px 12px 30px}.page-head h1{font-size:25px}.flow-steps{grid-template-columns:1fr}.flow-steps>div{padding:9px 11px}.workspace-head{flex-direction:column}.config-chips{justify-content:flex-start}.booking-filters{grid-template-columns:1fr}.booking-filters label:last-child{grid-column:auto}.schedule-workspace,.booking-summary{padding:13px}.selection-feedback{align-items:flex-start;flex-direction:column}.schedule-skeleton{grid-template-columns:repeat(2,1fr)}} 
</style>

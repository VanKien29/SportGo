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
                            <span v-for="item in configCards" :key="item">{{ item }}</span>
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
                            <div class="date-picker-panel">
                                <div class="date-control">
                                    <button type="button" aria-label="Ngày trước" @click="shiftDate(-1)">‹</button>
                                    <input v-model="bookingDate" type="date" :min="today" @change="changeDate" />
                                    <button type="button" aria-label="Ngày sau" @click="shiftDate(1)">›</button>
                                </div>
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

                    <div class="booking-insights">
                        <section>
                            <span class="eyebrow">CÁCH CHỌN</span>
                            <strong>Chọn một hoặc nhiều sân trong cùng ngày</strong>
                            <p>
                                Ô đã đặt, sân khóa hoặc quá sát giờ sẽ bị khóa chọn. Mỗi sân cần đủ thời lượng tối
                                thiểu theo cấu hình, giá trong từng ô được tính theo khung giờ.
                            </p>
                        </section>
                        <section class="price-guide">
                            <header>
                                <span class="eyebrow">BẢNG GIÁ NHANH</span>
                                <strong>{{ formatDate(bookingDate) }}</strong>
                            </header>
                            <div v-if="priceRows.length">
                                <article v-for="row in priceRows" :key="row.id">
                                    <span>
                                        <strong>{{ row.name }}</strong>
                                        <small>{{ row.type }}</small>
                                    </span>
                                    <em>{{ row.priceText }}</em>
                                </article>
                            </div>
                            <p v-else>Chưa có dữ liệu giá trong ngày này.</p>
                        </section>
                    </div>

                    <div class="period-row">
                        <div class="period-tabs">
                            <button
                                v-for="period in dynamicTimePeriods"
                                :key="period.key"
                                type="button"
                                :class="{ active: activePeriodKey === period.key }"
                                @click="activePeriod = period.key"
                            >
                                <span class="period-label">{{ period.label }}</span>
                                <span class="period-time">{{ period.range }}</span>
                            </button>
                        </div>
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
                            <div v-for="slotInfo in activePeriodSlots" :key="slotInfo.slot.start_time" class="time-heading">
                                {{ shortTime(slotInfo.slot.start_time) }}
                            </div>

                            <template v-for="court in courts" :key="court.id">
                                <button type="button" class="court-name sticky" @click="focusCourt(court)">
                                    <strong>{{ court.name }}</strong>
                                    <span>{{ court.court_type?.name || "Sân thể thao" }}</span>
                                </button>
                                <button
                                    v-for="slotInfo in activePeriodSlots"
                                    :key="`${court.id}-${slotInfo.slot.start_time}`"
                                    type="button"
                                    class="slot"
                                    :class="slotClasses(court.id, slotInfo.slot, slotInfo.index)"
                                    :disabled="slotDisabled(court.id, slotInfo.slot)"
                                    :title="slotTitle(court, slotInfo.slot)"
                                    @click="toggleSlot(court, slotInfo.index)"
                                >
                                    <small v-if="!slotDisabled(court.id, slotInfo.slot)">
                                        {{ compactMoney(slotStatus(court.id, slotInfo.slot)?.price) }}
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
                        <button v-if="selectedSlotKeys.length" type="button" @click="clearSelection">Bỏ chọn</button>
                    </div>
                </section>

                <aside class="booking-summary">
                    <header>
                        <span class="eyebrow">TÓM TẮT BOOKING</span>
                        <h2>{{ summaryTitle }}</h2>
                        <p>{{ summarySubtitle }}</p>
                    </header>

                    <dl class="booking-facts">
                        <div><dt>Ngày chơi</dt><dd>{{ formatDate(bookingDate) }}</dd></div>
                        <div><dt>Khung giờ</dt><dd>{{ selectedTimeText }}</dd></div>
                        <div><dt>Thời lượng</dt><dd>{{ durationText }}</dd></div>
                    </dl>

                    <section v-if="selectedPriceRows.length" class="price-breakdown">
                        <header><strong>Chi tiết giá</strong><span>{{ selectedPriceRows.length }} khung</span></header>
                        <div v-for="row in selectedPriceRows" :key="`${row.courtId}-${row.startTime}`">
                            <span>{{ row.courtName }} · {{ shortTime(row.startTime) }} - {{ shortTime(row.endTime) }}</span>
                            <strong>{{ money(row.amount) }}</strong>
                        </div>
                    </section>

                    <section v-if="available" class="discount-section">
                        <div v-if="membershipDiscount > 0">
                            <span>Ưu đãi thành viên</span>
                            <strong>-{{ money(membershipDiscount) }}</strong>
                        </div>
                        <button type="button" @click="voucherOpen = !voucherOpen">
                            <span>{{ selectedVoucher ? `${selectedVoucher.code} đã chọn` : "Chọn voucher" }}</span>
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
                            <span class="payment-icon">
                                <AppIcon :name="option.icon" size="17" />
                            </span>
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
import AppIcon from "../../../components/AppIcon.vue";
import PublicNavbar from "../../../components/PublicNavbar.vue";
import { bookingService } from "../../../services/bookingService.js";
import { getAuth } from "../../../stores/auth.js";

export default {
    name: "ClientBookingWorkspace",
    components: { AppIcon, PublicNavbar },
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
            activePeriod: "",
            selectedSlotKeys: [],
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
        selectedSlotEntries() {
            return this.slotEntriesFromKeys(this.selectedSlotKeys);
        },
        selectedSlotRanges() {
            return this.slotRangesFromKeys(this.selectedSlotKeys);
        },
        selectedCourts() {
            const map = new Map();
            this.selectedSlotEntries.forEach(entry => map.set(String(entry.court.id), entry.court));
            return [...map.values()];
        },
        selectedCourt() {
            return this.selectedCourts[0] || null;
        },
        selectedDetails() {
            return this.selectedSlotEntries
                .map(entry => ({
                    ...entry,
                    status: this.slotStatus(entry.courtId, entry.slot),
                }))
                .filter(entry => entry.status);
        },
        selectedPriceRows() {
            return this.selectedSlotRanges.map(range => {
                const amount = this.selectedDetails
                    .filter(entry => {
                        return String(entry.courtId) === String(range.venue_court_id)
                            && this.minutes(entry.slot.start_time) >= this.minutes(range.start_time)
                            && this.minutes(entry.slot.end_time) <= this.minutes(range.end_time);
                    })
                    .reduce((sum, entry) => sum + Number(entry.status?.price || 0), 0);

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
            return this.selectedSlotEntries.reduce((sum, entry) => {
                return sum + Math.max(this.minutes(entry.slot.end_time) - this.minutes(entry.slot.start_time), 0);
            }, 0);
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
            return this.selectedSlotRanges[0]?.start_time || "";
        },
        endTime() {
            return this.selectedSlotRanges[this.selectedSlotRanges.length - 1]?.end_time || "";
        },
        selectedTimeText() {
            return this.rangeListText(this.selectedSlotRanges);
        },
        summaryTitle() {
            if (!this.selectedSlotRanges.length) return "Chưa chọn sân";
            if (this.selectedCourts.length === 1) return this.selectedCourts[0].name;
            return `${this.selectedCourts.length} sân đã chọn`;
        },
        summarySubtitle() {
            if (!this.selectedSlotRanges.length) return "Chọn một hoặc nhiều khung giờ trống để bắt đầu.";
            if (this.selectedSlotRanges.length === 1) return this.selectedCourt?.court_type?.name || "Sân thể thao";
            return `${this.selectedSlotRanges.length} khung giờ · ${this.durationText}`;
        },
        durationText() {
            if (!this.duration) return "-";
            const hours = Math.floor(this.duration / 60);
            const minutes = this.duration % 60;
            if (!hours) return `${minutes} phút`;
            if (!minutes) return `${hours} giờ`;
            return `${hours} giờ ${minutes} phút`;
        },
        selectionTitle() {
            if (this.checking) return "Đang kiểm tra khung giờ";
            if (!this.selectedSlotRanges.length) return "Chọn khung giờ trống";
            if (this.selectedSlotRanges.length === 1) return `${this.selectedCourt?.name} · ${this.durationText}`;
            return `${this.selectedSlotRanges.length} khung giờ · ${this.selectedCourts.length} sân`;
        },
        selectionHint() {
            if (this.checking) return "Hệ thống đang đối chiếu trạng thái sân, giá và ưu đãi mới nhất.";
            return this.selectedSlotRanges.length
                ? `${this.selectedTimeText} · ${this.money(this.baseAmount)}`
                : `Có thể chọn nhiều sân; mỗi sân cần đủ tối thiểu ${this.minDuration} phút.`;
        },
        validationError() {
            if (this.selectionError) return this.selectionError;
            if (!this.selectedSlotKeys.length) return "";
            const invalidCourt = this.selectedCourts.find(court => {
                const minutes = this.selectedSlotEntries
                    .filter(entry => String(entry.courtId) === String(court.id))
                    .reduce((sum, entry) => sum + Math.max(this.minutes(entry.slot.end_time) - this.minutes(entry.slot.start_time), 0), 0);
                return minutes < this.minDuration || (this.maxDuration && minutes > this.maxDuration);
            });
            if (invalidCourt) {
                const minutes = this.selectedSlotEntries
                    .filter(entry => String(entry.courtId) === String(invalidCourt.id))
                    .reduce((sum, entry) => sum + Math.max(this.minutes(entry.slot.end_time) - this.minutes(entry.slot.start_time), 0), 0);
                if (minutes < this.minDuration) return `${invalidCourt.name} cần đặt tối thiểu ${this.minDuration} phút.`;
                return `${invalidCourt.name} chỉ được đặt tối đa ${this.maxDuration} phút.`;
            }
            if (!this.available && !this.checking) return "Khung giờ không còn khả dụng.";
            return "";
        },
        baseAmount() {
            return this.selectedDetails.reduce((sum, item) => sum + Number(item.status?.price || 0), 0);
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
        selectedVoucher() {
            return this.venueVoucher || this.vipVoucher || null;
        },
        selectedVoucherCount() {
            return this.selectedVoucher ? 1 : 0;
        },
        venueVoucher() {
            return this.venueVouchers.find(item => item.id === this.selectedVenueVoucherId);
        },
        vipVoucher() {
            return this.vipVouchers.find(item => item.id === this.selectedVipVoucherId);
        },
        voucherDiscount() {
            return this.voucherValue(this.selectedVoucher, this.afterMembership);
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
                this.config.allow_no_prepay !== false && { value: "no_prepay", label: "Thanh toán tại sân", hint: "Không cần trả trước", icon: "banknote" },
                this.config.allow_deposit && { value: "deposit", label: `Đặt cọc ${this.config.deposit_percent || 30}%`, hint: "Giữ sân bằng tiền cọc", icon: "creditCard" },
                this.config.allow_full_payment !== false && { value: "full_payment", label: "Thanh toán toàn bộ", hint: "Thanh toán online 100%", icon: "qrCode" },
            ].filter(Boolean);
        },
        canSubmit() {
            return this.available && !this.validationError && this.paymentOption && !this.submitting;
        },
        activeStep() {
            if (this.available && !this.validationError) return 3;
            if (this.selectedSlotKeys.length) return 2;
            return 1;
        },
        configCards() {
            const cards = [this.operatingHoursText];
            cards.push(`Tối thiểu ${this.minDuration} phút`);
            if (this.maxDuration) cards.push(`Tối đa ${this.maxDuration} phút`);
            if (this.minAdvance) cards.push(`Đặt trước ${this.minAdvance} phút`);
            if (this.config.slot_hold_minutes) cards.push(`Giữ chỗ ${this.config.slot_hold_minutes} phút`);
            if (this.config.allow_deposit) cards.push(`Cọc ${this.config.deposit_percent || 30}%`);
            return cards;
        },
        dynamicTimePeriods() {
            const definitions = [
                { key: "morning", label: "Sáng", from: 0, to: 12 * 60 },
                { key: "afternoon", label: "Chiều", from: 12 * 60, to: 18 * 60 },
                { key: "evening", label: "Tối", from: 18 * 60, to: 24 * 60 },
            ];

            return definitions
                .map(period => {
                    const slotInfos = this.slots
                        .map((slot, index) => ({ slot, index }))
                        .filter(({ slot }) => {
                            const start = this.minutes(slot.start_time);
                            return start >= period.from && start < period.to;
                        });

                    if (!slotInfos.length) return null;
                    const first = slotInfos[0].slot;
                    const last = slotInfos[slotInfos.length - 1].slot;
                    return {
                        ...period,
                        slotInfos,
                        range: `${this.shortTime(first.start_time)} - ${this.shortTime(last.end_time)}`,
                    };
                })
                .filter(Boolean);
        },
        activePeriodKey() {
            const periods = this.dynamicTimePeriods;
            if (!periods.length) return "";
            return periods.some(period => period.key === this.activePeriod) ? this.activePeriod : periods[0].key;
        },
        activePeriodSlots() {
            return this.dynamicTimePeriods.find(period => period.key === this.activePeriodKey)?.slotInfos || [];
        },
        gridStyle() {
            const slotCount = Math.max(this.activePeriodSlots.length, 1);
            const courtColumn = 160;
            const slotColumn = 60;
            return {
                gridTemplateColumns: `${courtColumn}px repeat(${slotCount}, minmax(${slotColumn}px, 1fr))`,
                "--timeline-min-width": `${courtColumn + slotCount * slotColumn}px`,
            };
        },
        operatingHoursText() {
            if (!this.operatingHours?.is_open) return "Đóng cửa";
            return `${this.shortTime(this.operatingHours.open_time)} - ${this.shortTime(this.operatingHours.close_time)}`;
        },
        priceRows() {
            return this.courts
                .map(court => {
                    const prices = this.slots
                        .map(slot => this.slotStatus(court.id, slot))
                        .filter(status => status && status.is_available !== false && Number(status.price || 0) > 0)
                        .map(status => Number(status.price));
                    if (!prices.length) return null;
                    const min = Math.min(...prices);
                    const max = Math.max(...prices);
                    return {
                        id: court.id,
                        name: court.name,
                        type: court.court_type?.name || "Sân thể thao",
                        priceText: min === max ? `${this.compactMoney(min)}/30p` : `${this.compactMoney(min)} - ${this.compactMoney(max)}/30p`,
                    };
                })
                .filter(Boolean)
                .slice(0, 6);
        },
    },
    async mounted() {
        if (!getAuth()) {
            this.$router.push("/login");
            return;
        }
        window.addEventListener("pageshow", this.handlePageShow);
        window.addEventListener("focus", this.handleWindowFocus);
        await this.initialize();
    },
    beforeUnmount() {
        window.removeEventListener("pageshow", this.handlePageShow);
        window.removeEventListener("focus", this.handleWindowFocus);
    },
    activated() {
        this.loadSchedule();
    },
    methods: {
        handlePageShow(event) {
            if (event.persisted) this.loadSchedule();
        },
        handleWindowFocus() {
            if (!this.initialLoading) this.loadSchedule();
        },
        async initialize() {
            try {
                const query = this.$route.query || {};
                const response = await bookingService.getInitData();
                this.clusters = response.clusters || [];
                const requested = query.venue_cluster_id || query.cluster;
                this.clusterId = this.clusters.find(item => String(item.id) === String(requested))?.id || this.clusters[0]?.id || "";
                this.clusterLocked = Boolean(requested);
                this.bookingDate = String(query.booking_date || query.date || this.today);
                this.courtTypeId = String(query.court_type_id || query.court_type || "");
                this.routeSelection = query.venue_court_id || query.court
                    ? {
                        courtId: query.venue_court_id || query.court,
                        start: this.time(query.start_time),
                        end: this.time(query.end_time),
                    }
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
                this.ensureActivePeriod();
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
        ensureActivePeriod() {
            const periods = this.dynamicTimePeriods;
            if (!periods.length) {
                this.activePeriod = "";
                return;
            }
            if (periods.some(period => period.key === this.activePeriod)) return;

            const now = new Date();
            const nowMinutes = now.getHours() * 60 + now.getMinutes();
            const currentPeriod = this.bookingDate === this.today
                ? periods.find(period => period.slotInfos.some(({ slot }) => this.minutes(slot.start_time) <= nowMinutes && this.minutes(slot.end_time) > nowMinutes))
                : null;
            this.activePeriod = (currentPeriod || periods[0]).key;
        },
        setActivePeriodByIndex(index) {
            const period = this.dynamicTimePeriods.find(item => item.slotInfos.some(slotInfo => slotInfo.index === index));
            if (period) this.activePeriod = period.key;
        },
        shiftDate(days) {
            const date = new Date(`${this.bookingDate}T00:00:00`);
            date.setDate(date.getDate() + days);
            const next = date.toLocaleDateString("en-CA");
            if (next < this.today) return;
            this.selectDate(next);
        },
        selectDate(value) {
            if (!value || value < this.today) return;
            if (value === this.bookingDate) return;
            this.bookingDate = value;
            this.changeDate();
        },
        timeKey(value) {
            return String(value || "").slice(0, 5);
        },
        slotStatus(courtId, slot) {
            const start = this.timeKey(slot?.start_time);
            return this.statuses.find(item => String(item.venue_court_id) === String(courtId) && this.timeKey(item.start_time) === start);
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
                selected: this.selectedSlotKeys.includes(this.slotKey(courtId, slot)),
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
            const slot = this.slots[index];
            if (this.slotDisabled(court.id, slot)) return;
            this.selectionError = "";
            const key = this.slotKey(court.id, slot);
            const nextKeys = this.selectedSlotKeys.includes(key)
                ? this.selectedSlotKeys.filter(item => item !== key)
                : [...this.selectedSlotKeys, key];
            const nextEntries = this.slotEntriesFromKeys(nextKeys);
            const courtMinutes = nextEntries
                .filter(entry => String(entry.courtId) === String(court.id))
                .reduce((sum, entry) => sum + Math.max(this.minutes(entry.slot.end_time) - this.minutes(entry.slot.start_time), 0), 0);

            if (this.maxDuration && courtMinutes > this.maxDuration) {
                this.selectionError = `${court.name} chỉ được đặt tối đa ${this.maxDuration} phút.`;
                return;
            }

            this.selectedSlotKeys = nextKeys;
            await this.checkAvailability();
        },
        focusCourt(court) {
            const courtKeys = this.selectedSlotKeys.filter(key => key.startsWith(`${court.id}|`));
            if (courtKeys.length) this.selectedSlotKeys = courtKeys;
        },
        clearSelection() {
            this.selectedSlotKeys = [];
            this.selectionError = "";
            this.available = false;
            this.preview = null;
            this._venueVouchers = [];
            this._vipVouchers = [];
            this.selectedVenueVoucherId = "";
            this.selectedVipVoucherId = "";
        },
        async checkAvailability() {
            if (!this.selectedSlotRanges.length) {
                this.available = false;
                this.preview = null;
                this._venueVouchers = [];
                this._vipVouchers = [];
                return;
            }
            this.checking = true;
            this.available = false;
            try {
                const responses = await Promise.all(this.selectedSlotRanges.map(range => bookingService.checkAvailability({
                    venue_court_id: range.venue_court_id,
                    booking_date: this.bookingDate,
                    start_time: range.start_time,
                    end_time: range.end_time,
                })));
                this.available = responses.every(response => Boolean(response.available));
                const original = responses.reduce((sum, response) => sum + Number(response.price_preview?.original_amount || response.total_price || 0), 0);
                const membershipDiscount = responses.reduce((sum, response) => {
                    return sum + Number(response.membership_discount?.discount_amount || response.price_preview?.membership_discount_amount || 0);
                }, 0);
                this.preview = {
                    original_amount: original,
                    membership_discount_amount: membershipDiscount,
                    final_amount: Math.max(original - membershipDiscount, 0),
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
                const firstRange = this.selectedSlotRanges[0];
                if (!firstRange) return;
                const response = await bookingService.eligibleVouchers({
                    venue_court_id: firstRange.venue_court_id,
                    booking_date: this.bookingDate,
                    start_time: firstRange.start_time,
                    end_time: firstRange.end_time,
                    amount: this.baseAmount,
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
            const alreadySelected = this.voucherSelected(voucher);
            this.selectedVenueVoucherId = "";
            this.selectedVipVoucherId = "";
            if (alreadySelected) return;
            if (voucher.owner_type === "venue") this.selectedVenueVoucherId = voucher.id;
            else this.selectedVipVoucherId = voucher.id;
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
            const start = this.minutes(this.routeSelection.start);
            const end = this.minutes(this.routeSelection.end);
            const selection = this.slots
                .map((slot, index) => ({ slot, index }))
                .filter(({ slot }) => this.minutes(slot.start_time) >= start && this.minutes(slot.end_time) <= end)
                .map(({ index }) => index);
            this.routeSelection = null;
            if (!court || !selection.length || selection.some(index => this.slotDisabled(court.id, this.slots[index]))) return;
            this.selectedSlotKeys = selection.map(index => this.slotKey(court.id, this.slots[index]));
            this.setActivePeriodByIndex(selection[0]);
            await this.checkAvailability();
        },
        async submit() {
            if (!this.canSubmit) return;
            this.submitting = true;
            this.submitError = "";
            try {
                const ranges = this.selectedSlotRanges.map(range => ({
                    venue_court_id: range.venue_court_id,
                    start_time: range.start_time,
                    end_time: range.end_time,
                }));
                const firstRange = ranges[0];
                const booking = await bookingService.createBooking({
                    venue_court_id: firstRange.venue_court_id,
                    booking_date: this.bookingDate,
                    start_time: firstRange.start_time,
                    end_time: firstRange.end_time,
                    ...(ranges.length > 1 ? { time_ranges: ranges } : {}),
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
        slotKey(courtId, slot) {
            return `${courtId}|${slot?.start_time || ""}`;
        },
        slotEntriesFromKeys(keys = []) {
            return keys
                .map(key => {
                    const [courtId, startTime] = key.split("|");
                    const court = this.courts.find(item => String(item.id) === String(courtId));
                    const slot = this.slots.find(item => item.start_time === startTime);
                    return court && slot ? { courtId, court, slot } : null;
                })
                .filter(Boolean)
                .sort((a, b) => {
                    const courtSort = String(a.court.name || "").localeCompare(String(b.court.name || ""));
                    if (courtSort !== 0) return courtSort;
                    return this.minutes(a.slot.start_time) - this.minutes(b.slot.start_time);
                });
        },
        slotRangesFromKeys(keys = []) {
            const ranges = [];
            this.slotEntriesFromKeys(keys).forEach(({ courtId, court, slot }) => {
                const current = ranges[ranges.length - 1];
                if (!current || String(current.venue_court_id) !== String(courtId) || current.end_time !== slot.start_time) {
                    ranges.push({
                        venue_court_id: courtId,
                        court,
                        start_time: slot.start_time,
                        end_time: slot.end_time,
                    });
                    return;
                }
                current.end_time = slot.end_time;
            });
            return ranges;
        },
        rangeListText(ranges = []) {
            if (!ranges.length) return "-";
            if (ranges.length <= 2) {
                return ranges.map(range => `${range.court?.name || "Sân"}: ${this.shortTime(range.start_time)} - ${this.shortTime(range.end_time)}`).join("; ");
            }
            return `${ranges.length} khung giờ trên ${this.selectedCourts.length} sân`;
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
.client-booking {
    min-height: 100vh;
    background: #f4f8f5;
    color: #15241b;
}

.booking-shell {
    width: min(1680px, 100%);
    margin: 0 auto;
    padding: 92px 20px 48px;
}

.page-head {
    margin-bottom: 18px;
}

.page-head > div {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #66756c;
    font-size: 13px;
}

.page-head a {
    color: #148348;
    font-weight: 800;
    text-decoration: none;
}

.page-head h1 {
    margin: 10px 0 6px;
    font-size: 30px;
    line-height: 1.15;
}

.page-head p {
    margin: 0;
    color: #627267;
    font-size: 14px;
}

.flow-steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    margin-bottom: 18px;
    overflow: hidden;
    border: 1px solid #d7e4d9;
    border-radius: 8px;
    background: #d7e4d9;
}

.flow-steps > div {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 62px;
    padding: 12px 16px;
    background: #fff;
}

.flow-steps > div > span {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 30px;
    height: 30px;
    border: 1px solid #cbd8ce;
    border-radius: 999px;
    font-weight: 900;
}

.flow-steps section {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.flow-steps strong {
    font-size: 14px;
}

.flow-steps small {
    color: #748178;
    font-size: 12px;
}

.flow-steps .active {
    background: #f1faf4;
}

.flow-steps .active > span,
.flow-steps .done > span {
    border-color: #22a653;
    background: #22a653;
    color: #fff;
}

.workspace {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 16px;
    align-items: start;
}

.schedule-workspace,
.booking-summary {
    border: 1px solid #d5e2d8;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 12px 28px rgba(37, 68, 45, 0.06);
}

.schedule-workspace {
    min-width: 0;
    padding: 18px;
}

.workspace-head {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) auto;
    gap: 18px;
    align-items: start;
}

.workspace-head h2,
.booking-summary h2 {
    margin: 4px 0 0;
    font-size: 22px;
    line-height: 1.25;
}

.eyebrow {
    color: #148348;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.02em;
}

.config-chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 7px;
    max-width: 620px;
}

.config-chips span {
    padding: 7px 10px;
    border-radius: 999px;
    background: #e8f7ec;
    color: #13753d;
    font-size: 12px;
    font-weight: 800;
}

.config-chips span:first-child {
    background: #eef6ff;
    color: #2563a7;
}

.booking-filters {
    display: grid;
    grid-template-columns: minmax(190px, 0.9fr) minmax(270px, 1.1fr) minmax(190px, 0.9fr);
    gap: 12px;
    margin: 18px 0 12px;
    padding: 12px;
    border: 1px solid #dce8de;
    border-radius: 8px;
    background: #f8fbf8;
}

.booking-filters label {
    display: grid;
    align-content: start;
    gap: 6px;
    min-width: 0;
}

.booking-filters label > span {
    color: #53655a;
    font-size: 12px;
    font-weight: 800;
}

.booking-filters select,
.booking-filters input {
    box-sizing: border-box;
    width: 100%;
    height: 42px;
    border: 1px solid #cbd9cf;
    border-radius: 7px;
    background: #fff;
    color: #17251c;
    padding: 0 12px;
    font: inherit;
    font-size: 14px;
}

.booking-filters input[type="date"] {
    color-scheme: light;
    color: #17251c;
}

.date-control {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) 38px;
    margin-bottom: 8px;
}

.date-control button {
    display: grid;
    place-items: center;
    border: 1px solid #cbd9cf;
    background: #fff;
    color: #13753d;
    font-size: 20px;
    font-weight: 800;
}

.date-control button:first-child {
    border-radius: 7px 0 0 7px;
}

.date-control button:last-child {
    border-radius: 0 7px 7px 0;
}

.date-control input {
    border-right: 0;
    border-left: 0;
    border-radius: 0;
}

.date-picker-panel {
    display: grid;
    gap: 8px;
}

.booking-insights {
    display: grid;
    grid-template-columns: minmax(260px, 0.82fr) minmax(420px, 1.18fr);
    gap: 12px;
    margin-bottom: 14px;
}

.booking-insights section {
    border: 1px solid #dce8de;
    border-radius: 8px;
    background: #fbfdfb;
    padding: 12px;
}

.booking-insights section > strong {
    display: block;
    margin-top: 5px;
    color: #173921;
    font-size: 15px;
}

.booking-insights p {
    margin: 7px 0 0;
    color: #64766b;
    font-size: 12px;
    line-height: 1.55;
}

.price-guide header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.price-guide header strong {
    color: #173921;
    font-size: 13px;
}

.price-guide > div {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.price-guide article {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 46px;
    padding: 8px 10px;
    border: 1px solid #e0e9e1;
    border-radius: 7px;
    background: #fff;
}

.price-guide article span {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.price-guide article strong,
.price-guide article small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.price-guide article strong {
    font-size: 13px;
}

.price-guide article small {
    color: #718077;
    font-size: 11px;
}

.price-guide article em {
    flex: 0 0 auto;
    color: #148348;
    font-size: 13px;
    font-style: normal;
    font-weight: 900;
}

.period-row {
    margin: 14px 0 10px;
}

.period-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.period-tabs button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    min-width: 150px;
    padding: 0 16px;
    border: 1px solid #d5e2d8;
    border-radius: 7px;
    background: #fff;
    color: #2d4035;
    font-size: 15px;
    font-weight: 900;
    cursor: pointer;
    line-height: 1;
}

.period-tabs button.active {
    border-color: #22a653;
    background: #22a653;
    color: #fff;
    box-shadow: 0 8px 18px rgba(34, 166, 83, 0.18);
}

.period-label {
    font-size: 16px;
    font-weight: 900;
}

.period-time {
    color: inherit;
    font-size: 12px;
    font-weight: 800;
    opacity: 0.74;
    transform: translateY(1px);
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 9px 16px;
    margin-bottom: 12px;
    color: #5d6d64;
    font-size: 12px;
    font-weight: 750;
}

.legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend i {
    width: 12px;
    height: 12px;
    border: 1px solid #bdd0c1;
    border-radius: 3px;
}

.legend .free { background: #eefaf1; }
.legend .selected { background: #22a653; }
.legend .booked { background: #fee2e2; }
.legend .locked { background: repeating-linear-gradient(-45deg, #9ca3af, #9ca3af 3px, #e5e7eb 3px, #e5e7eb 6px); }
.legend .past { background: #edf1ed; }

.schedule-board {
    overflow-x: auto;
    overflow-y: hidden;
    border: 1px solid #d5e2d8;
    border-radius: 8px;
}

.timeline {
    display: grid;
    width: 100%;
    min-width: var(--timeline-min-width, max-content);
}

.timeline > * {
    min-height: 54px;
    border-right: 1px solid #e1e9e2;
    border-bottom: 1px solid #e1e9e2;
}

.sticky {
    position: sticky;
    left: 0;
    z-index: 2;
}

.court-heading,
.time-heading {
    display: grid;
    place-items: center;
    background: #edf5ee;
    color: #43544a;
    font-size: 12px;
    font-weight: 900;
}

.court-name {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 3px;
    padding: 9px 10px;
    border: 0;
    background: #fff;
    text-align: left;
}

.court-name strong {
    color: #16251c;
    font-size: 11.5px;
}

.court-name span {
    color: #6e7e74;
    font-size: 10.5px;
    line-height: 1.35;
}

.slot {
    position: relative;
    display: grid;
    place-items: center;
    border: 0;
    border-right: 1px solid #e1e9e2;
    border-bottom: 1px solid #e1e9e2;
    background: #f5fbf6;
    cursor: pointer;
}

.slot:hover:not(:disabled) {
    background: #ddf4e3;
}

.slot small {
    color: #14733b;
    font-size: 10px;
    font-weight: 900;
    white-space: nowrap;
}

.slot.selected {
    background: #22a653;
    box-shadow: inset 0 0 0 2px #148348;
}

.slot.selected small {
    color: #fff;
}

.slot.booked { background: #fee7e7; }
.slot.locked { background: repeating-linear-gradient(-45deg, #d1d5db, #d1d5db 6px, #eef0f2 6px, #eef0f2 12px); }
.slot.past { background: #edf1ed; }
.slot:disabled { cursor: not-allowed; }

.selection-feedback {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 12px;
    padding: 12px 14px;
    border: 1px solid #bfe6c8;
    border-radius: 8px;
    background: #effbf2;
}

.selection-feedback > div {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.selection-feedback strong {
    color: #176c36;
    font-size: 14px;
}

.selection-feedback span {
    overflow: hidden;
    color: #5c6f63;
    font-size: 13px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selection-feedback button {
    flex: 0 0 auto;
    color: #c62828;
    font-size: 13px;
    font-weight: 850;
}

.selection-feedback.error {
    border-color: #fecaca;
    background: #fff3f3;
}

.selection-feedback.error strong {
    color: #b91c1c;
}

.booking-summary {
    position: sticky;
    top: 86px;
    padding: 18px;
}

.booking-summary header p {
    margin: 6px 0 0;
    color: #68786e;
    font-size: 13px;
    line-height: 1.45;
}

.booking-facts {
    display: grid;
    margin: 16px 0;
}

.booking-facts div {
    display: grid;
    grid-template-columns: 110px minmax(0, 1fr);
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid #e5ece6;
}

.booking-facts dt {
    color: #68786e;
    font-size: 13px;
}

.booking-facts dd {
    margin: 0;
    color: #14221a;
    font-size: 13px;
    font-weight: 850;
    text-align: right;
}

.price-breakdown,
.discount-section,
.payment-section {
    display: grid;
    gap: 9px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #dfe8e0;
}

.price-breakdown header,
.price-breakdown > div,
.discount-section > div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
}

.price-breakdown > div span {
    color: #68786e;
    line-height: 1.35;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.discount-section > div strong,
.discount-section button em {
    color: #148348;
}

.discount-section > button {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 12px;
    border: 1px solid #bfe2c6;
    border-radius: 7px;
    background: #eef9f0;
    color: #184f2b;
    font-weight: 850;
}

.discount-section button em {
    font-style: normal;
    font-weight: 900;
}

.voucher-list {
    display: grid;
    gap: 7px;
}

.voucher-list button {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 10px;
    border: 1px solid #d7e3d8;
    border-radius: 7px;
    background: #fff;
    text-align: left;
}

.voucher-list button.active {
    border-color: #22a653;
    background: #effbf2;
}

.voucher-list button span {
    display: grid;
}

.voucher-list small {
    color: #6c7b71;
}

.payment-section h3 {
    margin: 0 0 2px;
    font-size: 15px;
}

.payment-section label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px;
    border: 1px solid #d7e3d8;
    border-radius: 7px;
}

.payment-section input {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: 0;
    opacity: 0;
    pointer-events: none;
}

.payment-section label.active {
    border-color: #22a653;
    background: #effbf2;
}

.payment-icon {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 34px;
    height: 34px;
    border: 1px solid #d4e2d7;
    border-radius: 8px;
    background: #fff;
    color: #13753d;
}

.payment-section label.active .payment-icon {
    border-color: #22a653;
    background: #22a653;
    color: #fff;
}

.payment-section label > span:last-child {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.payment-section label strong {
    font-size: 14px;
    line-height: 1.25;
}

.payment-section label small {
    color: #6b7a70;
    font-size: 12px;
    line-height: 1.25;
}

.booking-summary footer {
    display: grid;
    gap: 10px;
    margin-top: 16px;
    padding-top: 15px;
    border-top: 1px solid #dfe8e0;
}

.booking-summary footer > div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.booking-summary footer > div strong {
    font-size: 20px;
}

.booking-summary footer .required strong {
    color: #148348;
}

.booking-summary footer > p {
    margin: 0;
    padding: 9px 10px;
    border-radius: 7px;
    background: #fff0f0;
    color: #b91c1c;
    font-size: 12px;
}

.booking-summary footer > button {
    min-height: 46px;
    border: 1px solid #22a653;
    border-radius: 7px;
    background: #22a653;
    color: #fff;
    font-weight: 900;
    cursor: pointer;
}

.booking-summary footer > button:disabled {
    border-color: #d4ddd5;
    background: #e7ece8;
    color: #99a49c;
}

.booking-summary footer > small {
    color: #6c7b71;
    text-align: center;
}

.state-panel,
.loading-panel {
    display: grid;
    place-items: center;
    min-height: 180px;
    color: #68786e;
}

.state-panel.error {
    color: #b91c1c;
}

.schedule-skeleton {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.schedule-skeleton i {
    height: 54px;
    border-radius: 7px;
    background: linear-gradient(90deg, #edf2ee, #f8faf8, #edf2ee);
    background-size: 200% 100%;
    animation: shimmer 1.2s infinite;
}

.loading-panel {
    gap: 10px;
}

.loading-panel i {
    width: 30px;
    height: 30px;
    border: 3px solid #d9e5db;
    border-top-color: #22a653;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes shimmer {
    to { background-position: -200% 0; }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 1100px) {
    .workspace {
        grid-template-columns: 1fr;
    }

    .booking-summary {
        position: static;
    }

    .booking-filters,
    .booking-insights {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 680px) {
    .booking-shell {
        padding: 78px 12px 30px;
    }

    .page-head h1 {
        font-size: 25px;
    }

    .flow-steps {
        grid-template-columns: 1fr;
    }

    .workspace-head,
    .booking-filters {
        grid-template-columns: 1fr;
    }

    .config-chips {
        justify-content: flex-start;
    }

    .schedule-workspace,
    .booking-summary {
        padding: 14px;
    }

    .price-guide > div {
        grid-template-columns: 1fr;
    }

    .selection-feedback {
        align-items: flex-start;
        flex-direction: column;
    }

    .selection-feedback span {
        white-space: normal;
    }

    .period-tabs {
        display: grid;
        grid-template-columns: 1fr;
        width: 100%;
    }

    .booking-summary {
        position: sticky;
        z-index: 10;
        bottom: 0;
        max-height: 82vh;
        overflow: auto;
        border-radius: 10px 10px 0 0;
        box-shadow: 0 -12px 30px rgba(15, 49, 26, 0.14);
    }
}
</style>

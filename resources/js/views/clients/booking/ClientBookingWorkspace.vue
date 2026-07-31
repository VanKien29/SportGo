<template>
    <div class="client-booking sg-client-page">
        <PublicNavbar />

        <main class="booking-shell sg-client-shell">
            <header class="page-head">
                <div class="page-head__topline">
                    <nav class="breadcrumbs" aria-label="Điều hướng booking">
                        <router-link :to="bookingBackTarget">Quay lại tìm sân</router-link>
                        <AppIcon name="chevronRight" aria-hidden="true" />
                        <strong>Đặt sân</strong>
                    </nav>
                    <router-link :to="{ name: 'booking-history' }" class="history-link sg-client-button">
                        <AppIcon name="history" aria-hidden="true" />
                        Lịch sử đặt sân
                    </router-link>
                </div>
                <h1>{{ currentCluster?.name || "Đặt sân trực tuyến" }}</h1>
                <p>Chọn sân và thời gian trực tiếp trên lịch. Giá và số tiền cần trả được cập nhật ngay.</p>
            </header>

            <nav class="flow-steps" aria-label="Tiến trình đặt sân">
                <div v-for="step in steps" :key="step.id" :class="{ active: activeStep === step.id, done: activeStep > step.id }">
                    <span>
                        <AppIcon v-if="activeStep > step.id" name="check" :size="14" aria-hidden="true" />
                        <template v-else>{{ step.id }}</template>
                    </span>
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
                <section class="schedule-workspace sg-client-card">
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
                            <select v-model="clusterId" class="sg-client-input" :disabled="clusterLocked" @change="changeCluster">
                                <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
                                    {{ cluster.name }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span>Ngày chơi</span>
                            <div class="date-picker-panel">
                                <div class="date-control">
                                    <button type="button" aria-label="Ngày trước" @click="shiftDate(-1)">
                                        <AppIcon name="chevronLeft" aria-hidden="true" />
                                    </button>
                                    <input v-model="bookingDate" class="sg-client-input" type="date" :min="today" @change="changeDate" />
                                    <button type="button" aria-label="Ngày sau" @click="shiftDate(1)">
                                        <AppIcon name="chevronRight" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        </label>
                        <label>
                            <span>Loại sân</span>
                            <select v-model="courtTypeId" class="sg-client-input" @change="changeCourtType">
                                <option value="">Tất cả loại sân</option>
                                <option v-for="type in courtTypes" :key="type.id" :value="String(type.id)">
                                    {{ type.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <details class="booking-guide">
                        <summary>
                            <span>
                                <AppIcon name="calendar" aria-hidden="true" />
                                Hướng dẫn chọn lịch và bảng giá nhanh
                            </span>
                            <AppIcon name="chevronDown" class="guide-chevron" aria-hidden="true" />
                        </summary>
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
                    </details>

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
                    <div v-else class="schedule-board" role="table" aria-label="Lịch trống theo sân và khung giờ">
                        <div class="timeline">
                            <div class="timeline-row timeline-head" role="row">
                                <div class="court-heading sticky" role="columnheader">Sân / giờ</div>
                                <div
                                    v-for="slotInfo in activePeriodSlots"
                                    :key="slotInfo.slot.start_time"
                                    class="time-heading"
                                    role="columnheader"
                                >
                                    {{ shortTime(slotInfo.slot.start_time) }}
                                </div>
                            </div>

                            <div v-for="court in courts" :key="court.id" class="timeline-row" role="row">
                                <button type="button" class="court-name sticky" role="rowheader" @click="focusCourt(court)">
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
                            </div>
                        </div>
                    </div>

                    <div class="selection-feedback" :class="{ error: validationError }">
                        <div>
                            <strong>{{ validationError ? "Cần điều chỉnh" : selectionTitle }}</strong>
                            <span>{{ validationError || selectionHint }}</span>
                        </div>
                        <div v-if="selectedSlotKeys.length" class="selection-actions">
                            <button type="button" class="clear-selection" @click="clearSelection">Bỏ chọn</button>
                            <button type="button" class="summary-jump" @click="scrollToSummary">
                                Xem tóm tắt
                                <AppIcon name="chevronRight" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </section>

                <aside id="booking-summary" ref="bookingSummary" class="booking-summary sg-client-card" tabindex="-1">
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
                            <span>{{ selectedVoucherCount ? `${selectedVoucherCount} voucher đã chọn` : "Chọn voucher" }}</span>
                            <strong v-if="voucherDiscount">-{{ money(voucherDiscount) }}</strong>
                            <em v-else>{{ eligibleVouchers.length }} mã</em>
                        </button>
                        <p v-if="voucherNotice" class="voucher-notice" role="status">{{ voucherNotice }}</p>
                        <div v-if="voucherOpen" class="voucher-list">
                            <button
                                v-for="voucher in eligibleVouchers"
                                :key="voucher.id"
                                type="button"
                                :class="{ active: voucherSelected(voucher) }"
                                @click="toggleVoucher(voucher)"
                            >
                                <span><strong>{{ voucher.code }}</strong><small>{{ voucher.name }}</small></span>
                                <em>-{{ money(voucherValue(voucher, voucherBaseAmount(voucher))) }}</em>
                            </button>
                            <p v-if="!eligibleVouchers.length">Không có voucher phù hợp.</p>
                        </div>
                    </section>

                    <section v-if="available" class="payment-section">
                        <h3>Hình thức thanh toán</h3>
                        <p v-if="walletBalance !== null" class="wallet-payment-balance">
                            Số dư ví: <strong>{{ money(walletBalance) }}</strong>
                        </p>
                        <label v-for="option in paymentOptions" :key="option.value" :class="{ active: paymentOption === option.value, disabled: option.disabled }">
                            <input v-model="paymentOption" type="radio" :value="option.value" :disabled="option.disabled" />
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
            available: false,
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
        };
    },
    computed: {
        today() {
            return new Date().toLocaleDateString("en-CA");
        },
        currentCluster() {
            return this.clusters.find(item => String(item.id) === String(this.clusterId)) || null;
        },
        bookingBackTarget() {
            const candidate = String(this.$route.query.return_to || "");
            return candidate.startsWith("/venues") && !candidate.startsWith("//") ? candidate : "/venues";
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
            if (this.paymentOption === "wallet" && this.walletBalance !== null && this.walletBalance < this.total) {
                return "Số dư ví không đủ cho booking này.";
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
            const amountAfterVenueVoucher = Math.max(this.afterMembership - this.venueVoucherDiscount, 0);
            if (amountAfterVenueVoucher <= 0) return [];
            return (this._vipVouchers || []).filter(voucher => {
                return Number(voucher.min_order_amount || 0) <= amountAfterVenueVoucher;
            });
        },
        selectedVoucherCount() {
            return [this.venueVoucher, this.vipVoucher].filter(Boolean).length;
        },
        venueVoucher() {
            return this.venueVouchers.find(item => String(item.id) === String(this.selectedVenueVoucherId));
        },
        vipVoucher() {
            return this.vipVouchers.find(item => String(item.id) === String(this.selectedVipVoucherId));
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
            return Math.max(this.afterMembership - this.voucherDiscount, 0);
        },
        requiredAmount() {
            if (this.paymentOption === "full_payment" || this.paymentOption === "wallet") return this.total;
            if (this.paymentOption === "deposit") return this.total * (Number(this.config.deposit_percent || 30) / 100);
            return 0;
        },
        paymentOptions() {
            return [
                this.config.allow_no_prepay !== false && { value: "no_prepay", label: "Thanh toán tại sân", hint: "Không cần trả trước", icon: "banknote" },
                this.config.allow_deposit !== false && { value: "deposit", label: `Đặt cọc ${this.config.deposit_percent || 30}%`, hint: "Giữ sân bằng tiền cọc", icon: "creditCard" },
                this.config.allow_full_payment !== false && { value: "full_payment", label: "Thanh toán toàn bộ", hint: "Thanh toán online 100%", icon: "qrCode" },
                this.config.allow_full_payment !== false && this.walletBalance !== null && {
                    value: "wallet",
                    label: "Thanh toán bằng ví",
                    hint: this.walletBalance >= this.total ? `Đủ số dư · ${this.money(this.walletBalance)}` : "Số dư ví không đủ",
                    icon: "wallet",
                    disabled: this.walletBalance < this.total,
                },
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
                try {
                    const walletResponse = await bookingService.getWallet();
                    this.walletBalance = Number(walletResponse?.wallet?.balance || 0);
                } catch {
                    this.walletBalance = null;
                }
                this.clusters = response.clusters || [];
                const requested = query.venue_cluster_id || query.cluster;
                this.clusterId = this.clusters.find(item => String(item.id) === String(requested))?.id || this.clusters[0]?.id || "";
                this.clusterLocked = Boolean(requested);
                const requestedDate = String(query.booking_date || query.date || this.today);
                this.bookingDate = requestedDate >= this.today ? requestedDate : this.today;
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
            const requestId = ++this.scheduleRequestId;
            const selectedKeysBeforeReload = [...this.selectedSlotKeys];
            this.availabilityRequestId += 1;
            this.checking = false;
            this.available = false;
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
                const response = await bookingService.getSchedule(params);
                if (requestId !== this.scheduleRequestId) return;
                this.slots = response.time_slots || [];
                this.courts = response.courts || [];
                this.statuses = response.slot_statuses || [];
                this.operatingHours = response.operating_hours || null;
                this.ensureActivePeriod();
                if (selectedKeysBeforeReload.length) {
                    this.selectedSlotKeys = selectedKeysBeforeReload;
                    const entries = this.slotEntriesFromKeys(selectedKeysBeforeReload);
                    const selectionChanged = entries.length !== selectedKeysBeforeReload.length
                        || entries.some(entry => this.slotDisabled(entry.courtId, entry.slot));
                    if (selectionChanged) {
                        this.clearSelection();
                        this.selectionError = "Lịch sân vừa thay đổi. Vui lòng chọn lại khung giờ còn trống.";
                    } else {
                        await this.checkAvailability();
                    }
                } else {
                    await this.applyRouteSelection();
                }
            } catch (error) {
                if (requestId !== this.scheduleRequestId) return;
                this.scheduleError = error.message || "Không thể tải lịch sân.";
            } finally {
                if (requestId === this.scheduleRequestId) this.scheduleLoading = false;
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
            this.availabilityRequestId += 1;
            this.checking = false;
            this.selectedSlotKeys = [];
            this.selectionError = "";
            this.available = false;
            this.preview = null;
            this._venueVouchers = [];
            this._vipVouchers = [];
            this.selectedVenueVoucherId = "";
            this.selectedVipVoucherId = "";
            this.voucherNotice = "";
        },
        scrollToSummary() {
            this.$nextTick(() => {
                const summary = this.$refs.bookingSummary;
                if (!summary) return;
                summary.scrollIntoView({ behavior: "smooth", block: "start" });
                summary.focus({ preventScroll: true });
            });
        },
        async checkAvailability() {
            const requestId = ++this.availabilityRequestId;
            const ranges = this.selectedSlotRanges.map(range => ({ ...range }));
            if (!ranges.length) {
                this.available = false;
                this.preview = null;
                this._venueVouchers = [];
                this._vipVouchers = [];
                this.checking = false;
                return;
            }
            this.checking = true;
            this.available = false;
            try {
                const responses = await Promise.all(ranges.map(range => bookingService.checkAvailability({
                    venue_court_id: range.venue_court_id,
                    booking_date: this.bookingDate,
                    start_time: range.start_time,
                    end_time: range.end_time,
                })));
                if (requestId !== this.availabilityRequestId) return;
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
                if (this.available) await this.loadVouchers(requestId, ranges[0]);
            } catch (error) {
                if (requestId !== this.availabilityRequestId) return;
                this.selectionError = error.message || "Không thể kiểm tra khung giờ.";
            } finally {
                if (requestId === this.availabilityRequestId) this.checking = false;
            }
        },
        async loadVouchers(requestId = this.availabilityRequestId, firstRange = this.selectedSlotRanges[0]) {
            try {
                if (!firstRange) return;
                const response = await bookingService.eligibleVouchers({
                    venue_court_id: firstRange.venue_court_id,
                    booking_date: this.bookingDate,
                    start_time: firstRange.start_time,
                    end_time: firstRange.end_time,
                    amount: this.baseAmount,
                });
                if (requestId !== this.availabilityRequestId) return;
                this._venueVouchers = response.venue_vouchers || [];
                this._vipVouchers = response.vip_vouchers || [];
            } catch {
                if (requestId !== this.availabilityRequestId) return;
                this._venueVouchers = [];
                this._vipVouchers = [];
            }
        },
        voucherSelected(voucher) {
            return voucher.owner_type === "venue"
                ? String(this.selectedVenueVoucherId) === String(voucher.id)
                : String(this.selectedVipVoucherId) === String(voucher.id);
        },
        toggleVoucher(voucher) {
            const alreadySelected = this.voucherSelected(voucher);
            this.voucherNotice = "";
            if (voucher.owner_type === "venue") {
                this.selectedVenueVoucherId = alreadySelected ? "" : voucher.id;
                if (this.selectedVipVoucherId
                    && !this.vipVouchers.some(item => String(item.id) === String(this.selectedVipVoucherId))) {
                    this.selectedVipVoucherId = "";
                    this.voucherNotice = "Voucher VIP đã được bỏ vì giá trị còn lại sau voucher sân không đủ điều kiện.";
                }
                return;
            }
            this.selectedVipVoucherId = alreadySelected ? "" : voucher.id;
        },
        voucherBaseAmount(voucher) {
            return voucher?.owner_type === "venue"
                ? this.afterMembership
                : Math.max(this.afterMembership - this.venueVoucherDiscount, 0);
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
                    venue_voucher_id: this.venueVoucher?.id || null,
                    vip_voucher_id: this.vipVoucher?.id || null,
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

<style scoped src="../../../../css/client-booking-workspace.css"></style>

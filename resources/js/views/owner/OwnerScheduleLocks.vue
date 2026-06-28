<template>
    <section class="schedule-lock-page">
        <!-- Floating Lock Button -->
        <div
            v-if="selectedSlots.length"
            class="floating-add-container"
            style="z-index: 100"
        >
            <button
                class="btn-float-add"
                type="button"
                :disabled="saving || previewing || !canSubmit"
                @click="createLock"
                title="Khóa lịch"
            >
                <AppIcon name="lock" size="20" />
                <span class="btn-float-text"
                    >Khóa {{ selectedSlots.length }} ô</span
                >
            </button>
        </div>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

        <!-- Conflict Modal -->
        <div
            v-if="lockConflictPreview"
            class="modal-backdrop"
            @click.self="closeConflictPreview"
        >
            <section class="conflict-modal">
                <header>
                    <div>
                        <p class="eyebrow">KHÓA SÂN ĐỘT XUẤT</p>
                        <h3>
                            {{ lockConflictPreview.affected_count }} booking bị
                            ảnh hưởng
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="icon-close"
                        @click="closeConflictPreview"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                          <line x1="18" y1="6" x2="6" y2="18"></line>
                          <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </header>

                <p class="conflict-help">
                    Ưu tiên đổi sang sân cùng loại còn trống. Nếu không đổi
                    được, chọn hoàn ví hoặc ghi nhận đã hoàn tiền mặt tại sân.
                </p>

                <div class="conflict-list">
                    <article
                        v-for="item in lockConflictPreview.items"
                        :key="item.booking_item_id"
                        class="conflict-row"
                    >
                        <div class="conflict-main">
                            <strong>{{
                                item.booking_code || "Booking"
                            }}</strong>
                            <span
                                >{{ item.customer?.name || "Khách hàng" }} ·
                                {{ item.customer?.phone || "-" }}</span
                            >
                            <small>
                                {{ item.court?.name || "Sân" }} ·
                                {{ time(item.start_time) }} -
                                {{ time(item.end_time) }} ·
                                {{
                                    item.payment_status === "paid"
                                        ? "Đã thanh toán"
                                        : "Chưa thanh toán"
                                }}
                            </small>
                            <small
                                v-if="item.is_playing"
                                class="incident-summary"
                            >
                                Đang chơi · đã dùng
                                {{ item.incident?.played_minutes || 0 }} phút ·
                                còn {{ item.incident?.remaining_minutes || 0 }}
                                phút · dự kiến hoàn
                                {{
                                    currency(
                                        item.incident
                                            ?.estimated_refund_amount,
                                    )
                                }}
                            </small>
                        </div>

                        <div class="conflict-actions">
                            <label
                                v-if="item.alternatives?.length"
                                class="radio-line"
                            >
                                <input
                                    v-model="
                                        lockResolutions[item.booking_item_id]
                                            .action
                                    "
                                    type="radio"
                                    value="switch"
                                />
                                Đổi sân
                            </label>
                            <select
                                v-if="item.alternatives?.length"
                                v-model="
                                    lockResolutions[item.booking_item_id]
                                        .venue_court_id
                                "
                                :disabled="
                                    lockResolutions[item.booking_item_id]
                                        .action !== 'switch'
                                "
                            >
                                <option
                                    v-for="court in item.alternatives"
                                    :key="court.id"
                                    :value="court.id"
                                >
                                    {{ court.name }}
                                </option>
                            </select>
                            <label class="radio-line danger">
                                <input
                                    v-model="
                                        lockResolutions[item.booking_item_id]
                                            .action
                                    "
                                    type="radio"
                                    value="cancel"
                                />
                                Hủy/hoàn ví
                            </label>
                            <label
                                v-if="item.payment_status === 'paid'"
                                class="radio-line cash"
                            >
                                <input
                                    v-model="
                                        lockResolutions[item.booking_item_id]
                                            .action
                                    "
                                    type="radio"
                                    value="cash_refund"
                                />
                                Đã hoàn tiền mặt
                            </label>
                        </div>
                    </article>
                </div>

                <footer>
                    <button
                        type="button"
                        class="secondary-btn"
                        @click="closeConflictPreview"
                    >
                        Đóng
                    </button>
                    <button
                        type="button"
                        class="primary-btn"
                        :disabled="saving"
                        @click="createLockWithResolutions"
                    >
                        {{ saving ? "Đang xử lý..." : "Khóa và xử lý booking" }}
                    </button>
                </footer>
            </section>
        </div>

        <div class="content-grid">
            <aside class="side-panel">
                <!-- MiniCalendar Date Range selector -->
                <div class="calendar-card">
                    <MiniCalendar
                        mode="range"
                        :model-value="{ start: form.start_date, end: form.end_date }"
                        :min-date="today"
                        @update:model-value="onCalendarRangePick"
                    />
                </div>

                <article class="form-card">
                    <div class="card-head compact-head">
                        <div>
                            <p class="eyebrow">TẠO KHÓA MỚI</p>
                            <h3>Chọn khoảng không nhận khách</h3>
                        </div>
                    </div>

                    <form @submit.prevent="createLock">
                        <div class="mode-switch">
                            <button
                                type="button"
                                :class="{ active: form.lock_mode === 'slots' }"
                                @click="setLockMode('slots')"
                            >
                                Theo khung giờ
                            </button>
                            <button
                                type="button"
                                :class="{
                                    active: form.lock_mode === 'whole_day',
                                }"
                                @click="setLockMode('whole_day')"
                            >
                                Theo ngày
                            </button>
                        </div>

                        <div class="lock-type-switch">
                            <button
                                type="button"
                                :class="{ active: form.lock_type === 'manual' }"
                                @click="form.lock_type = 'manual'"
                            >
                                Khóa thường
                            </button>
                            <button
                                type="button"
                                :class="{
                                    active: form.lock_type === 'emergency',
                                }"
                                @click="form.lock_type = 'emergency'"
                            >
                                Khóa đột xuất
                            </button>
                        </div>
                        <p
                            v-if="form.lock_type === 'emergency'"
                            class="emergency-hint"
                        >
                            Có thể chọn ô đang có booking để đổi sân hoặc xử lý
                            phần thời gian còn lại.
                        </p>

                        <!-- Selection Summary or Court Picker -->
                        <div
                            v-if="form.lock_mode === 'slots'"
                            class="selection-box"
                            :class="{ empty: !selectedSlots.length }"
                        >
                            <strong>{{ selectionSummary }}</strong>
                            <span>{{ dateRangeLabel }}</span>
                            <button
                                v-if="selectedSlots.length"
                                type="button"
                                @click="clearSelection"
                            >
                                Bỏ chọn tất cả
                            </button>
                        </div>

                        <div v-else class="court-picker">
                            <div class="picker-head">
                                <strong>Chọn sân áp dụng</strong>
                                <span>{{ selectedCourtIds.length }} sân</span>
                                <button type="button" @click="toggleAllCourts">
                                    {{
                                        selectedCourtIds.length ===
                                        scheduleCourts.length
                                            ? "Bỏ chọn tất cả"
                                            : "Chọn tất cả"
                                    }}
                                </button>
                            </div>
                            <div class="court-chip-grid">
                                <label
                                    v-for="court in scheduleCourts"
                                    :key="court.id"
                                    :class="{
                                        active: selectedCourtIds.includes(
                                            court.id,
                                        ),
                                    }"
                                >
                                    <input
                                        v-model="selectedCourtIds"
                                        type="checkbox"
                                        :value="court.id"
                                    />
                                    <span>
                                        <strong>{{ court.name }}</strong>
                                        <small>{{
                                            court.court_type?.name || "-"
                                        }}</small>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <section
                            v-if="lockPreviewRows.length"
                            class="lock-preview-panel"
                        >
                            <div class="preview-headline">
                                <div>
                                    <strong>Preview áp dụng</strong>
                                    <span
                                        >{{ lockPreviewStats.total }} lượt
                                        khóa</span
                                    >
                                </div>
                                <em v-if="lockPreviewStats.knownBusy">
                                    {{ lockPreviewStats.knownBusy }} lượt đang
                                    bận ở ngày đang xem
                                </em>
                            </div>

                            <div class="lock-preview-list">
                                <article
                                    v-for="row in lockPreviewRows.slice(0, 12)"
                                    :key="row.key"
                                    :class="{ busy: row.isBusy }"
                                >
                                    <div>
                                        <strong>{{ row.dateLabel }}</strong>
                                        <small
                                            >{{ row.courtName }} ·
                                            {{ row.timeText }}</small
                                        >
                                    </div>
                                    <span>{{ row.statusLabel }}</span>
                                </article>
                            </div>

                            <small
                                v-if="lockPreviewRows.length > 12"
                                class="preview-more"
                            >
                                Còn {{ lockPreviewRows.length - 12 }} lượt khóa
                                khác.
                            </small>
                        </section>

                        <label>
                            Lý do khóa
                            <textarea
                                v-model.trim="form.reason"
                                rows="4"
                                maxlength="500"
                                placeholder="Ví dụ: Bảo trì mặt sân, nghỉ lễ, sự kiện nội bộ..."
                                required
                            />
                        </label>

                        <button
                            class="primary-btn"
                            type="submit"
                            :disabled="saving || previewing || !canSubmit"
                        >
                            {{
                                saving
                                    ? "Đang khóa..."
                                    : previewing
                                      ? "Đang kiểm tra..."
                                      : lockButtonLabel
                            }}
                        </button>
                    </form>
                </article>

                <article class="locks-card">
                    <div class="card-head compact-head">
                        <div>
                            <p class="eyebrow">MỞ KHÓA</p>
                            <h3>Khoảng đã khóa</h3>
                            <span>{{ date(form.start_date) }}</span>
                        </div>
                        <button
                            v-if="locks.length"
                            class="text-danger-btn"
                            type="button"
                            :disabled="Boolean(deletingId)"
                            @click="
                                removeLocks(
                                    locks,
                                    'Mở tất cả khoảng đã khóa trong ngày này?',
                                )
                            "
                        >
                            Mở tất cả
                        </button>
                    </div>

                    <div v-if="!locks.length" class="state compact-state">
                        Ngày này chưa có khoảng khóa thủ công.
                    </div>
                    <div v-else class="lock-list">
                        <div
                            v-for="group in lockGroups"
                            :key="group.courtId"
                            class="lock-group"
                        >
                            <div class="lock-group-head">
                                <div>
                                    <strong>{{ group.courtName }}</strong>
                                    <span>{{ group.items.length }} khoảng</span>
                                </div>
                                <button
                                    type="button"
                                    :disabled="Boolean(deletingId)"
                                    @click="
                                        removeLocks(
                                            group.items,
                                            `Mở tất cả khoảng khóa của ${group.courtName}?`,
                                        )
                                    "
                                >
                                    Mở sân này
                                </button>
                            </div>
                            <div class="lock-chip-list">
                                <button
                                    v-for="lock in group.items"
                                    :key="lock.id"
                                    type="button"
                                    :disabled="Boolean(deletingId)"
                                    :title="lock.reason || 'Mở khoảng khóa'"
                                    @click="removeLock(lock)"
                                >
                                    <strong>
                                        {{ time(lock.start_time) }} -
                                        {{ time(lock.end_time) }}
                                    </strong>
                                    <span>Mở</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </aside>

            <article class="schedule-card">
                <div class="card-head schedule-headline">
                    <div>
                        <p class="eyebrow">TRẠNG THÁI TRONG NGÀY</p>
                        <h3>{{ date(form.start_date) }}</h3>
                    </div>
                    <div class="header-actions">
                        <div class="legend">
                            <span><i class="available"></i>Trống</span>
                            <span><i class="booking"></i>Đã đặt</span>
                            <span><i class="holding"></i>Đang giữ</span>
                            <span><i class="manual"></i>Đã khóa</span>
                        </div>
                        <button
                            class="secondary-btn btn-refresh"
                            type="button"
                            :disabled="loading"
                            @click="loadData"
                        >
                            Làm mới
                        </button>
                    </div>
                </div>

                <div class="quick-ranges">
                    <button
                        v-for="range in quickRanges"
                        :key="range.key"
                        type="button"
                        :class="{ active: activeTimePeriod === range.key }"
                        :disabled="loading"
                        @click="activeTimePeriod = range.key"
                    >
                        <strong>{{ range.label }}</strong>
                        <small>{{ range.start }} - {{ range.end }}</small>
                    </button>
                </div>

                <div v-if="loading" class="state">Đang tải lịch sân...</div>
                <div v-else-if="!selectedClusterId" class="state">
                    Vui lòng chọn cụm sân ở thanh bên.
                </div>
                <div v-else-if="!scheduleCourts.length" class="state">
                    Cụm sân chưa có sân đang hoạt động.
                </div>
                <div v-else class="schedule-wrap">
                    <div class="schedule-grid" :style="scheduleGridStyle">
                        <div class="grid-head sticky-col">Sân \ Giờ</div>
                        <div
                            v-for="slot in activePeriodSlots"
                            :key="slot.start_time"
                            class="grid-head time-head"
                        >
                            {{ time(slot.start_time) }}
                        </div>

                        <template
                            v-for="court in scheduleCourts"
                            :key="court.id"
                        >
                            <div class="court-cell sticky-col">
                                <strong>{{ court.name }}</strong>
                                <span>{{ court.court_type?.name }}</span>
                            </div>
                            <button
                                v-for="slot in activePeriodSlots"
                                :key="`${court.id}-${slot.start_time}`"
                                class="slot-cell"
                                :class="slotClass(court.id, slot)"
                                :title="slotTitle(court.id, slot)"
                                type="button"
                                :disabled="!canSelectSlot(court.id, slot)"
                                :aria-pressed="isSelected(court.id, slot)"
                                @click="pickSlot(court, slot)"
                            />
                        </template>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>

<script>
import { bookingService } from "../../services/bookingService.js";
import { ownerScheduleLockService } from "../../services/ownerScheduleLocks.js";
import MiniCalendar from "../../components/MiniCalendar.vue";
import AppIcon from "../../components/AppIcon.vue";

export default {
    name: "OwnerScheduleLocks",
    components: { MiniCalendar, AppIcon },
    data() {
        const today = new Date().toISOString().split("T")[0];

        return {
            today,
            selectedClusterId: localStorage.getItem("selected_cluster") || "",
            locks: [],
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleSlotStatuses: [],
            loading: true,
            saving: false,
            deletingId: "",
            error: "",
            notice: "",
            form: {
                start_date: today,
                end_date: today,
                lock_mode: "slots",
                lock_type: "manual",
                reason: "",
            },
            selectedSlots: [],
            selectedCourtIds: [],
            quickRanges: [
                { key: "morning", label: "Sáng", start: "06:00", end: "12:00" },
                {
                    key: "afternoon",
                    label: "Chiều",
                    start: "12:00",
                    end: "18:00",
                },
                { key: "evening", label: "Tối", start: "18:00", end: "22:00" },
            ],
            activeTimePeriod: "morning",
            previewing: false,
            pendingLockPayload: null,
            lockConflictPreview: null,
            lockResolutions: {},
        };
    },
    computed: {
        canSubmit() {
            const hasTarget =
                this.form.lock_mode === "whole_day"
                    ? this.selectedCourtIds.length > 0
                    : this.selectedSlots.length > 0;

            return (
                hasTarget &&
                this.form.start_date &&
                this.form.end_date &&
                this.form.reason
            );
        },
        dateRangeLabel() {
            if (this.form.start_date === this.form.end_date) {
                return this.date(this.form.start_date);
            }

            return `${this.date(this.form.start_date)} - ${this.date(this.form.end_date)}`;
        },
        dateCount() {
            const start = new Date(`${this.form.start_date}T00:00:00`);
            const end = new Date(`${this.form.end_date}T00:00:00`);
            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
                return 0;
            }

            return Math.max(Math.floor((end - start) / 86400000) + 1, 0);
        },
        selectedCourtCount() {
            return new Set(
                this.selectedSlots.map((slot) => slot.venue_court_id),
            ).size;
        },
        selectionSummary() {
            if (!this.selectedSlots.length) {
                return "Chọn thời gian trên bảng bên phải";
            }

            return `Thời gian: ${this.selectedTimeText} · ${this.selectedCourtCount} sân`;
        },
        lockButtonLabel() {
            if (this.form.lock_mode === "whole_day") {
                return "Khóa cả ngày";
            }

            return "Khóa thời gian đã chọn";
        },
        selectedTimeText() {
            const ranges = [
                ...new Set(
                    this.selectedSlots
                        .map(
                            (slot) =>
                                `${this.time(slot.start_time)} - ${this.time(slot.end_time)}`,
                        )
                        .sort(),
                ),
            ];

            if (!ranges.length) return "-";
            if (ranges.length <= 2) return ranges.join(", ");

            return `${ranges.slice(0, 2).join(", ")} +${ranges.length - 2} khung`;
        },
        lockGroups() {
            const grouped = this.locks.reduce((result, lock) => {
                const courtId =
                    lock.venue_court_id || lock.venue_court?.id || "unknown";
                if (!result[courtId]) {
                    result[courtId] = {
                        courtId,
                        courtName: lock.venue_court?.name || "Sân chưa rõ",
                        items: [],
                    };
                }
                result[courtId].items.push(lock);
                return result;
            }, {});

            return Object.values(grouped)
                .map((group) => ({
                    ...group,
                    items: group.items.sort((a, b) =>
                        a.start_time.localeCompare(b.start_time),
                    ),
                }))
                .sort((a, b) => a.courtName.localeCompare(b.courtName));
        },
        dateRangeDates() {
            const start = new Date(`${this.form.start_date}T00:00:00`);
            const end = new Date(`${this.form.end_date}T00:00:00`);
            if (
                Number.isNaN(start.getTime()) ||
                Number.isNaN(end.getTime()) ||
                end < start
            ) {
                return [];
            }

            const dates = [];
            for (
                let date = new Date(start);
                date <= end && dates.length <= 45;
                date.setDate(date.getDate() + 1)
            ) {
                dates.push(this.isoDate(date));
            }

            return dates;
        },
        lockTargetRanges() {
            return this.form.lock_mode === "whole_day"
                ? this.buildWholeDayRanges().map((range) => ({
                      ...range,
                      court_name:
                          this.scheduleCourts.find(
                              (court) => court.id === range.venue_court_id,
                          )?.name || "Sân",
                  }))
                : this.buildSelectedRanges().map((range) => ({
                      ...range,
                      court_name:
                          this.selectedSlots.find(
                              (slot) =>
                                  slot.venue_court_id === range.venue_court_id,
                          )?.court_name ||
                          this.scheduleCourts.find(
                              (court) => court.id === range.venue_court_id,
                          )?.name ||
                          "Sân",
                  }));
        },
        lockPreviewRows() {
            return this.dateRangeDates.flatMap((date) =>
                this.lockTargetRanges.map((range) => {
                    const isStartDate = date === this.form.start_date;
                    const representativeSlot = {
                        start_time: range.start_time,
                        end_time: range.end_time,
                    };
                    const isBusy = isStartDate
                        ? this.isRangeBusy(
                              range.venue_court_id,
                              representativeSlot,
                          )
                        : false;

                    return {
                        key: `${date}-${range.venue_court_id}-${range.start_time}-${range.end_time}`,
                        date,
                        dateLabel: this.date(date),
                        courtName: range.court_name,
                        timeText: `${this.time(range.start_time)} - ${this.time(range.end_time)}`,
                        isBusy,
                        statusLabel: isBusy
                            ? "Đang có lịch"
                            : isStartDate
                              ? "Trống"
                              : "Sẽ kiểm tra khi khóa",
                    };
                }),
            );
        },
        lockPreviewStats() {
            return {
                total: this.lockPreviewRows.length,
                knownBusy: this.lockPreviewRows.filter((row) => row.isBusy)
                    .length,
            };
        },
        activePeriod() {
            return (
                this.quickRanges.find(
                    (range) => range.key === this.activeTimePeriod,
                ) || this.quickRanges[0]
            );
        },
        activePeriodSlots() {
            return this.scheduleSlots.filter((slot) => {
                const start = this.time(slot.start_time);
                return (
                    start >= this.activePeriod.start &&
                    start < this.activePeriod.end
                );
            });
        },
        scheduleGridStyle() {
            return {
                gridTemplateColumns: `minmax(150px, .9fr) repeat(${this.activePeriodSlots.length}, minmax(54px, 1fr))`,
            };
        },
    },
    async mounted() {
        window.addEventListener(
            "owner-cluster-changed",
            this.handleClusterChanged,
        );
        await this.loadData();
    },
    beforeUnmount() {
        window.removeEventListener(
            "owner-cluster-changed",
            this.handleClusterChanged,
        );
    },
    methods: {
        async handleClusterChanged(event) {
            this.selectedClusterId =
                event.detail?.id ||
                localStorage.getItem("selected_cluster") ||
                "";
            this.clearSelection();
            await this.loadData();
        },
        async onCalendarRangePick(val) {
            this.form.start_date = val?.start || "";
            this.form.end_date = val?.end || "";
            this.clearSelection();
            await this.loadData();
        },
        async handleStartDateChange() {
            if (
                this.form.end_date &&
                this.form.end_date < this.form.start_date
            ) {
                this.form.end_date = this.form.start_date;
            }

            this.clearSelection();
            await Promise.all([this.loadSchedule(), this.loadLocks()]);
        },
        async handleEndDateChange() {
            if (
                this.form.start_date &&
                this.form.end_date < this.form.start_date
            ) {
                this.form.end_date = this.form.start_date;
            }
        },
        async loadData() {
            this.loading = true;
            this.error = "";

            if (!this.selectedClusterId) {
                this.loading = false;
                return;
            }

            try {
                await Promise.all([this.loadSchedule(), this.loadLocks()]);
            } catch (error) {
                this.error = error.message || "Không thể tải lịch sân.";
            } finally {
                this.loading = false;
            }
        },
        async loadSchedule() {
            if (!this.selectedClusterId || !this.form.start_date) return;

            const response = await bookingService.getSchedule({
                venue_cluster_id: this.selectedClusterId,
                booking_date: this.form.start_date,
                booking_type: "single",
            });
            this.scheduleSlots = response.time_slots || [];
            this.scheduleCourts = response.courts || [];
            this.scheduleSlotStatuses = response.slot_statuses || [];
        },
        async loadLocks() {
            if (!this.selectedClusterId || !this.form.start_date) return;

            const response = await ownerScheduleLockService.list({
                venue_cluster_id: this.selectedClusterId,
                booking_date: this.form.start_date,
            });
            this.locks = response.data || [];
        },
        async createLock() {
            if (!this.canSubmit) return;

            this.error = "";
            this.notice = "";
            try {
                const payload = this.buildLockPayload();
                this.previewing = true;
                const preview = await ownerScheduleLockService.preview(payload);
                const data = preview.data || {};

                if ((data.affected_count || 0) > 0) {
                    this.pendingLockPayload = payload;
                    this.lockConflictPreview = data;
                    this.lockResolutions = this.defaultLockResolutions(
                        data.items || [],
                    );
                    return;
                }

                await this.finalizeLock(payload);
            } catch (error) {
                this.error = error.message || "Không thể khóa khung giờ.";
            } finally {
                this.previewing = false;
            }
        },
        buildLockPayload() {
            return {
                start_date: this.form.start_date,
                end_date: this.form.end_date,
                lock_type: this.form.lock_type,
                reason: this.form.reason,
                slots:
                    this.form.lock_mode === "whole_day"
                        ? this.buildWholeDayRanges()
                        : this.buildSelectedRanges(),
            };
        },
        defaultLockResolutions(items = []) {
            return items.reduce((result, item) => {
                const firstAlternative = item.alternatives?.[0]?.id || "";
                result[item.booking_item_id] = {
                    booking_item_id: item.booking_item_id,
                    action: firstAlternative ? "switch" : "cancel",
                    venue_court_id: firstAlternative,
                };
                return result;
            }, {});
        },
        async createLockWithResolutions() {
            if (!this.pendingLockPayload) return;

            const resolutions = Object.values(this.lockResolutions).map(
                (item) => ({
                    booking_item_id: item.booking_item_id,
                    action: item.action,
                    venue_court_id:
                        item.action === "switch" ? item.venue_court_id : null,
                }),
            );

            await this.finalizeLock({
                ...this.pendingLockPayload,
                resolutions,
            });
        },
        async finalizeLock(payload) {
            this.saving = true;
            this.error = "";
            this.notice = "";
            try {
                const response = await ownerScheduleLockService.create(payload);
                this.notice = response.message;
                this.form.reason = "";
                this.closeConflictPreview();
                this.clearSelection();
                await Promise.all([this.loadSchedule(), this.loadLocks()]);
            } catch (error) {
                this.error = error.message || "Không thể khóa khung giờ.";
            } finally {
                this.saving = false;
            }
        },
        closeConflictPreview() {
            this.pendingLockPayload = null;
            this.lockConflictPreview = null;
            this.lockResolutions = {};
        },
        async removeLock(lock) {
            await this.removeLocks(
                [lock],
                `Mở lại ${this.time(lock.start_time)} - ${this.time(lock.end_time)} tại ${lock.venue_court?.name}?`,
            );
        },
        async removeLocks(locks, confirmMessage) {
            const targets = (locks || []).filter(Boolean);
            if (!targets.length || !window.confirm(confirmMessage)) return;

            this.deletingId =
                targets.length === 1 ? targets[0].id : `bulk-${Date.now()}`;
            this.error = "";
            this.notice = "";
            try {
                await Promise.all(
                    targets.map((lock) =>
                        ownerScheduleLockService.remove(lock.id),
                    ),
                );
                this.notice =
                    targets.length === 1
                        ? "Đã mở lại khung giờ."
                        : `Đã mở lại ${targets.length} khoảng khóa.`;
                await Promise.all([this.loadSchedule(), this.loadLocks()]);
            } catch (error) {
                this.error = error.message || "Không thể mở lại khung giờ.";
            } finally {
                this.deletingId = "";
            }
        },
        statusFor(courtId, slot) {
            return (
                this.scheduleSlotStatuses.find(
                    (status) =>
                        status.venue_court_id === courtId &&
                        status.start_time === slot.start_time,
                ) || null
            );
        },
        isBusy(courtId, slot) {
            return !this.statusFor(courtId, slot)?.is_available;
        },
        canSelectSlot(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (!status || status.is_available) return true;

            return (
                this.form.lock_type === "emergency" &&
                status.busy_source === "booking"
            );
        },
        isRangeBusy(courtId, range) {
            const start = this.minutes(range.start_time);
            const end = this.minutes(range.end_time);

            return this.scheduleSlotStatuses.some((status) => {
                if (status.venue_court_id !== courtId || status.is_available) {
                    return false;
                }

                const statusStart = this.minutes(status.start_time);
                const statusEnd = this.minutes(status.end_time);

                return statusStart < end && statusEnd > start;
            });
        },
        slotClass(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.isSelected(courtId, slot)) return "selected";
            if (!status || status.is_available) return "available";
            if (status.busy_source === "booking") return "booking";
            if (["manual", "emergency"].includes(status.busy_status))
                return "manual";
            return "holding";
        },
        slotTitle(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.isSelected(courtId, slot))
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Đã chọn`;
            if (!status || status.is_available)
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Trống`;
            if (status.busy_source === "booking") {
                return this.form.lock_type === "emergency"
                    ? "Đã có booking · có thể chọn để xử lý đột xuất"
                    : "Đã có booking";
            }
            if (["manual", "emergency"].includes(status.busy_status))
                return `Đã khóa: ${status.lock_reason || "Không có lý do"}`;
            return "Đang được giữ chỗ";
        },
        pickSlot(court, slot) {
            if (this.form.lock_mode !== "slots") return;

            const key = this.slotKey(court.id, slot.start_time);
            const existingIndex = this.selectedSlots.findIndex(
                (item) => item.key === key,
            );

            if (existingIndex >= 0) {
                this.selectedSlots.splice(existingIndex, 1);
                return;
            }

            this.selectedSlots.push({
                key,
                venue_court_id: court.id,
                court_name: court.name,
                start_time: this.withSeconds(slot.start_time),
                end_time: this.withSeconds(slot.end_time),
            });
        },
        slotKey(courtId, startTime) {
            return `${courtId}-${this.withSeconds(startTime)}`;
        },
        isSelected(courtId, slot) {
            const key = this.slotKey(courtId, slot.start_time);
            return this.selectedSlots.some((item) => item.key === key);
        },
        clearSelection() {
            this.selectedSlots = [];
            this.selectedCourtIds = [];
        },
        setLockMode(mode) {
            this.form.lock_mode = mode;
            this.clearSelection();
        },
        toggleAllCourts() {
            if (this.selectedCourtIds.length === this.scheduleCourts.length) {
                this.selectedCourtIds = [];
                return;
            }

            this.selectedCourtIds = this.scheduleCourts.map(
                (court) => court.id,
            );
        },
        buildWholeDayRanges() {
            return this.selectedCourtIds.map((courtId) => ({
                venue_court_id: courtId,
                start_time: "00:00:00",
                end_time: "24:00:00",
            }));
        },
        buildSelectedRanges() {
            const grouped = [...this.selectedSlots]
                .sort((a, b) => a.start_time.localeCompare(b.start_time))
                .reduce((result, slot) => {
                    if (!result[slot.venue_court_id])
                        result[slot.venue_court_id] = [];
                    result[slot.venue_court_id].push(slot);
                    return result;
                }, {});

            return Object.entries(grouped).flatMap(([courtId, slots]) => {
                const ranges = [];

                slots.forEach((slot) => {
                    const previous = ranges[ranges.length - 1];
                    if (previous && previous.end_time === slot.start_time) {
                        previous.end_time = slot.end_time;
                    } else {
                        ranges.push({
                            venue_court_id: courtId,
                            start_time: slot.start_time,
                            end_time: slot.end_time,
                        });
                    }
                });

                return ranges;
            });
        },
        withSeconds(value) {
            return value.length === 5 ? `${value}:00` : value;
        },
        time(value) {
            return (value || "").slice(0, 5);
        },
        minutes(value) {
            const normalized = this.withSeconds(value || "00:00:00");
            if (normalized.startsWith("24:00")) return 24 * 60;
            const [hour, minute] = normalized
                .slice(0, 5)
                .split(":")
                .map(Number);
            return hour * 60 + minute;
        },
        isoDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
           <style scoped>
/* ═══════════════════════════════════════════════════════════
   Base Page Layout
═══════════════════════════════════════════════════════════ */
.schedule-lock-page {
  display: grid;
  gap: 14px;
  width: 100%;
}

.content-grid {
  display: grid;
  grid-template-columns: 320px minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}

/* ── Sidebar Panels ── */
.side-panel {
  display: grid;
  gap: 12px;
  position: sticky;
  top: 14px;
  align-self: start;
}

.calendar-card {
  border: 1px solid #d8ecdb;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 4px 16px rgba(22, 163, 74, 0.06);
  overflow: hidden;
}

.form-card,
.locks-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.card-head h3 {
  margin: 0;
  color: #0f172a;
  font-size: 15px;
  font-weight: 900;
  line-height: 1.3;
}

.eyebrow {
  margin: 0 0 3px;
  color: #16a34a;
  font-size: 10px;
  font-weight: 950;
  letter-spacing: .08em;
}

/* Forms */
.form-card form {
  display: grid;
  gap: 12px;
  margin-top: 12px;
}

.mode-switch,
.lock-type-switch {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px;
  padding: 3px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.mode-switch button,
.lock-type-switch button {
  min-height: 34px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #475b4d;
  font: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
  transition: background .12s, color .12s;
}

.mode-switch button.active,
.lock-type-switch button.active {
  background: #16a34a;
  color: #fff;
  box-shadow: 0 2px 6px rgba(22, 163, 74, 0.15);
}

.emergency-hint {
  margin: 0;
  padding: 8px 10px;
  border-left: 3px solid #f59e0b;
  background: #fffbeb;
  color: #92400e;
  font-size: 11px;
  font-weight: 750;
  line-height: 1.4;
  border-radius: 0 6px 6px 0;
}

.form-card label {
  display: grid;
  gap: 5px;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}

.form-card textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 8px 10px;
  background: #fff;
  color: #0f172a;
  font: inherit;
  font-size: 13px;
  min-height: 80px;
  resize: vertical;
}

.form-card textarea:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.primary-btn {
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #15803d;
  border-radius: 8px;
  background: #16a34a;
  color: #fff;
  font: inherit;
  font-weight: 900;
  font-size: 13px;
  cursor: pointer;
  transition: background .13s;
}

.primary-btn:hover { background: #15803d; }
.primary-btn:disabled { opacity: .55; cursor: not-allowed; }

.secondary-btn {
  min-height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  background: #fff;
  color: #334155;
  font: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
  padding: 0 12px;
}

.secondary-btn:hover { background: #f1f5f9; }

/* Selection Box */
.selection-box {
  display: grid;
  gap: 3px;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
}

.selection-box strong { font-size: 13px; color: #0f172a; font-weight: 950; }
.selection-box span { font-size: 11px; color: #64748b; font-weight: 800; }
.selection-box button {
  border: 0;
  background: transparent;
  color: #b42318;
  font: inherit;
  font-size: 11px;
  font-weight: 900;
  text-align: left;
  cursor: pointer;
  padding: 2px 0 0;
  text-decoration: underline;
}

/* Court picker (whole day mode) */
.court-picker {
  display: grid;
  gap: 10px;
  padding: 10px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.picker-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
}

.picker-head strong { color: #14532d; font-weight: 900; }
.picker-head span {
  padding: 2px 6px;
  border-radius: 999px;
  background: #e8f5e9;
  color: #1b5e20;
  font-size: 10px;
  font-weight: 900;
}

.picker-head button {
  border: 0;
  background: transparent;
  color: #16a34a;
  font: inherit;
  font-size: 11px;
  font-weight: 950;
  cursor: pointer;
  text-decoration: underline;
}

.court-chip-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 6px;
  max-height: 160px;
  overflow-y: auto;
}

.court-chip-grid label {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border: 1px solid #d9e8d9;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  font-size: 12px;
}

.court-chip-grid label.active {
  border-color: #16a34a;
  background: #eef8f0;
}

.court-chip-grid label strong { color: #0f172a; font-weight: 900; }
.court-chip-grid label small { color: #64748b; font-weight: 750; margin-left: auto; }

/* Applied lock preview */
.lock-preview-panel {
  display: grid;
  gap: 6px;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.preview-headline {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #64748b;
  font-weight: 800;
}

.preview-headline strong { color: #0f172a; font-weight: 900; }

.lock-preview-list {
  display: grid;
  gap: 4px;
  max-height: 120px;
  overflow-y: auto;
}

.lock-preview-list article {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 8px;
  border-radius: 6px;
  background: #f8fafc;
  font-size: 11px;
}

.lock-preview-list article.busy {
  background: #fffbeb;
  color: #b45309;
}

.lock-preview-list article strong { font-weight: 900; }
.lock-preview-list article small { color: #64748b; font-weight: 800; }

/* Unlocking section (khoảng đã khóa) */
.lock-list {
  display: grid;
  gap: 10px;
  margin-top: 10px;
  max-height: 220px;
  overflow-y: auto;
}

.lock-group {
  display: grid;
  gap: 6px;
  padding: 8px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.lock-group-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.lock-group-head strong { font-size: 12px; color: #0f172a; font-weight: 900; }
.lock-group-head span { font-size: 10px; color: #64748b; font-weight: 800; margin-left: 6px; }
.lock-group-head button {
  border: 0;
  background: transparent;
  color: #b42318;
  font: inherit;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
  text-decoration: underline;
}

.lock-chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.lock-chip-list button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border: 1px solid #fca5a5;
  border-radius: 6px;
  background: #fee2e2;
  color: #7f1d1d;
  font: inherit;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
  transition: background .12s;
}

.lock-chip-list button:hover {
  background: #fca5a5;
}

.lock-chip-list button span { opacity: .75; text-decoration: underline; }

.state {
  padding: 24px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
  font-weight: 800;
}

.compact-state {
  padding: 12px 6px;
  font-size: 12px;
}

.text-danger-btn {
  border: 0;
  background: transparent;
  color: #dc2626;
  font: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
  text-decoration: underline;
}

/* ═══════════════════════════════════════════════════════════
   CENTER: Schedule Grid Area
═══════════════════════════════════════════════════════════ */
.schedule-card {
  padding: 16px;
}

.schedule-headline {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.schedule-headline h3 {
  margin: 4px 0 0;
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.legend {
  display: flex;
  gap: 10px;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.legend i {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.legend .available { background: #fff; border: 1px solid #cbd5e1; }
.legend .booking   { background: #dbeafe; border: 1px solid #93c5fd; }
.legend .holding   { background: #fef3c7; border: 1px solid #facc15; }
.legend .manual    { background: #fee2e2; border: 1px solid #fca5a5; }

.quick-ranges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 14px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 12px;
}

.quick-ranges button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 38px;
  padding: 6px 12px;
  border: 1px solid #d7e4d7;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font: inherit;
  cursor: pointer;
  transition: background .13s, border-color .13s, color .13s;
}

.quick-ranges button strong { font-size: 13px; }
.quick-ranges button small { opacity: .75; font-size: 11px; font-weight: 800; }

.quick-ranges button.active {
  border-color: #16a34a;
  background: #16a34a;
  color: #fff;
}

/* Timeline scroller grid */
.schedule-wrap {
  max-width: 100%;
  overflow-x: auto;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
}

.schedule-grid {
  display: grid;
  min-width: 680px;
  background: #e2e8f0;
  gap: 1px;
}

.grid-head,
.court-cell,
.slot-cell {
  background: #fff;
  padding: 8px;
  min-height: 36px;
  display: flex;
  align-items: center;
}

.grid-head {
  justify-content: center;
  background: #f3f8f1;
  color: #334238;
  font-size: 11px;
  font-weight: 900;
  text-align: center;
  border-bottom: 1px solid #dfe8df;
}

.court-cell {
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 2px;
  position: sticky;
  left: 0;
  z-index: 2;
  box-shadow: 2px 0 5px rgba(0,0,0,0.03);
}

.court-cell strong { font-size: 12px; color: #0f172a; font-weight: 900; }
.court-cell span { font-size: 10px; color: #64748b; font-weight: 750; }

.sticky-col {
  position: sticky;
  left: 0;
  z-index: 2;
}

.slot-cell {
  border: 0;
  cursor: pointer;
  transition: transform .1s, outline .1s;
}

.slot-cell:hover:not(:disabled) {
  outline: 2px solid rgba(22, 163, 74, 0.35);
  outline-offset: -2px;
  transform: scale(1.02);
  z-index: 1;
}

.slot-cell:disabled {
  cursor: not-allowed;
}

/* Colors for cells */
.slot-cell.available { background: #fff; }
.slot-cell.booking   { background: #dbeafe; border: 1px solid #93c5fd; }
.slot-cell.holding   { background: #fef3c7; border: 1px solid #facc15; }
.slot-cell.manual    { background: #fee2e2; border: 1px solid #fca5a5; }
.slot-cell.selected  { background: #16a34a !important; border: 1px solid #15803d !important; }

/* ═══════════════════════════════════════════════════════════
   Conflict Modal
═══════════════════════════════════════════════════════════ */
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.48);
  backdrop-filter: blur(2px);
}

.conflict-modal {
  width: min(640px, 100%);
  display: grid;
  gap: 0;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 24px 64px rgba(15, 23, 42, 0.2);
  overflow: hidden;
}

.conflict-modal header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  background: #fef2f2;
}

.conflict-modal header h3 { margin: 0; font-size: 16px; font-weight: 900; color: #991b1b; }

.icon-close {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border: 0;
  background: transparent;
  color: #991b1b;
  cursor: pointer;
}

.conflict-help {
  margin: 14px 20px 0;
  color: #64748b;
  font-size: 12px;
  line-height: 1.45;
}

.conflict-list {
  margin: 12px 20px 0;
  max-height: 280px;
  overflow-y: auto;
  display: grid;
  gap: 10px;
}

.conflict-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 14px;
  padding: 12px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fff5f5;
  align-items: start;
}

.conflict-main {
  display: grid;
  gap: 3px;
}

.conflict-main strong { font-size: 13px; color: #7f1d1d; font-weight: 900; }
.conflict-main span { font-size: 11px; color: #991b1b; font-weight: 800; }
.conflict-main small { font-size: 11px; color: #64748b; }

.incident-summary {
  color: #b45309 !important;
  font-weight: 800;
}

.conflict-actions {
  display: grid;
  gap: 6px;
  justify-items: end;
}

.radio-line {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 800;
  color: #334155;
  cursor: pointer;
}

.radio-line input { cursor: pointer; }
.radio-line.danger { color: #dc2626; }
.radio-line.cash   { color: #16a34a; }

.conflict-actions select {
  padding: 4px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font: inherit;
  font-size: 11px;
}

.conflict-modal footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid #e2e8f0;
  background: #f9fafb;
  margin-top: 14px;
}

/* Alerts */
.alert {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 800;
}

.alert.error {
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.alert.success {
  border: 1px solid #bbf7d0;
  background: #dcfce7;
  color: #166534;
}

/* ═══════════════════════════════════════════════════════════
   Responsive
═══════════════════════════════════════════════════════════ */
@media (max-width: 900px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
  .side-panel {
    position: static;
  }
}
</style>

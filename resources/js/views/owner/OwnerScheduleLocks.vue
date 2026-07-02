<template>
    <section class="schedule-lock-page">
        <!-- Floating Lock Button (sticky bottom bar) -->
        <Teleport to="body">
            <div
                v-if="
                    selectedSlots.length ||
                    (form.lock_mode === 'whole_day' && selectedCourtIds.length)
                "
                class="sticky-bottom-bar"
            >
                <div class="sticky-bottom-inner">
                    <div class="sticky-bottom-info">
                        <strong v-if="form.lock_mode === 'slots'"
                            >{{ selectedSlots.length }} ô đã chọn</strong
                        >
                        <strong v-else
                            >{{ selectedCourtIds.length }} sân · cả ngày</strong
                        >
                        <span>{{ dateRangeLabel }}</span>
                    </div>
                    <div class="sticky-bottom-actions">
                        <button
                            type="button"
                            class="sticky-btn-clear"
                            @click="clearSelection"
                        >
                            Bỏ chọn
                        </button>
                        <button
                            type="button"
                            class="sticky-btn-submit"
                            :disabled="saving || previewing || !canSubmit"
                            @click="createLock"
                        >
                            {{
                                saving
                                    ? "Đang khóa..."
                                    : previewing
                                      ? "Đang kiểm tra..."
                                      : lockButtonLabel
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

        <!-- Conflict Preview Modal (unchanged logic) -->
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
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="14"
                            height="14"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
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
                        class="conflict-card"
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
                                        item.incident?.estimated_refund_amount,
                                    )
                                }}
                            </small>
                        </div>

                        <div class="conflict-actions">
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
                                class="conflict-select"
                            >
                                <option
                                    v-for="court in item.alternatives"
                                    :key="court.id"
                                    :value="court.id"
                                >
                                    {{ court.name }}
                                </option>
                            </select>
                            <div class="conflict-radios">
                                <label
                                    v-if="item.alternatives?.length"
                                    class="radio-line"
                                >
                                    <input
                                        v-model="
                                            lockResolutions[
                                                item.booking_item_id
                                            ].action
                                        "
                                        type="radio"
                                        value="switch"
                                    />
                                    Đổi sân
                                </label>
                                <label class="radio-line danger">
                                    <input
                                        v-model="
                                            lockResolutions[
                                                item.booking_item_id
                                            ].action
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
                                            lockResolutions[
                                                item.booking_item_id
                                            ].action
                                        "
                                        type="radio"
                                        value="cash_refund"
                                    />
                                    Đã hoàn tiền mặt
                                </label>
                            </div>
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

        <!-- ===== TOP: Config panel ===== -->
        <div class="config-strip">
            <div class="config-left">
                <MiniCalendar
                    mode="range"
                    :start-date="form.start_date"
                    :end-date="form.end_date"
                    :min-date="today"
                    @update:start-date="
                        (val) => {
                            form.start_date = val;
                            handleStartDateChange();
                        }
                    "
                    @update:end-date="
                        (val) => {
                            form.end_date = val;
                            handleEndDateChange();
                        }
                    "
                />
            </div>
            <div class="config-right">
                <div class="config-section">
                    <p class="config-label">Chế độ khóa</p>
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
                            :class="{ active: form.lock_mode === 'whole_day' }"
                            @click="setLockMode('whole_day')"
                        >
                            Theo ngày
                        </button>
                    </div>
                </div>

                <div class="config-section">
                    <label class="reason-label">
                        <span>Lý do khóa</span>
                        <textarea
                            v-model.trim="form.reason"
                            rows="3"
                            maxlength="500"
                            placeholder="Ví dụ: Bảo trì mặt sân, nghỉ lễ, sự kiện nội bộ..."
                            required
                        />
                    </label>
                </div>

                <!-- Whole-day: court picker inline -->
                <div
                    v-if="form.lock_mode === 'whole_day'"
                    class="config-section"
                >
                    <div class="court-picker">
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
                                    active: selectedCourtIds.includes(court.id),
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
                </div>

                <div class="lock-preview-panel">
                    <details
                        v-if="lockPreviewIssues.length"
                        class="preview-details"
                    >
                        <summary>
                            <strong>Cần chú ý</strong>
                            <span
                                >{{ lockPreviewIssues.length }} lịch bị ảnh
                                hưởng</span
                            >
                        </summary>
                        <div class="lock-preview-list">
                            <article
                                v-for="row in lockPreviewIssues.slice(0, 12)"
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
                            v-if="lockPreviewIssues.length > 12"
                            class="preview-more"
                        >
                            Còn {{ lockPreviewIssues.length - 12 }} lịch cần chú
                            ý khác.
                        </small>
                    </details>
                    <div v-else class="lock-empty-preview">
                        <strong>Chưa có lịch cần chú ý.</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MIDDLE: Schedule Grid (full width) ===== -->
        <article class="schedule-card">
            <div class="schedule-headline">
                <div class="schedule-headline-left">
                    <p class="eyebrow">TRẠNG THÁI TRONG NGÀY</p>
                    <h3>{{ date(form.start_date) }}</h3>
                </div>
                <div class="schedule-headline-right">
                    <div class="legend">
                        <span><i class="dot-available"></i>Trống</span>
                        <span><i class="dot-booking"></i>Đã đặt</span>
                        <span><i class="dot-holding"></i>Đang giữ</span>
                        <span><i class="dot-manual"></i>Đã khóa</span>
                        <span v-if="form.lock_mode === 'slots'"
                            ><i class="dot-selected"></i>Đang chọn</span
                        >
                    </div>
                    <button
                        class="secondary-btn btn-compact"
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
                    v-for="range in dynamicQuickRanges"
                    :key="range.key"
                    type="button"
                    :class="{ active: activeTimePeriod === range.key }"
                    :disabled="loading"
                    @click="activeTimePeriod = range.key"
                >
                    <strong>{{ range.label }}</strong>
                    <small>{{ range.range }}</small>
                </button>
            </div>

            <div v-if="loading" class="state">Đang tải lịch sân...</div>
            <div v-else-if="!selectedClusterId" class="state">
                Vui lòng chọn cụm sân.
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

                    <template v-for="court in scheduleCourts" :key="court.id">
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

        <!-- ===== BOTTOM: Existing locks (collapsible) ===== -->
        <details v-if="locks.length" class="locks-section" open>
            <summary class="locks-summary">
                <div>
                    <strong>Khoảng đã khóa trong ngày</strong>
<<<<<<< HEAD
                    <span>{{ locks.length }} khoảng · {{ date(form.start_date) }}</span>
=======
                    <span
                        >{{ locks.length }} khoảng ·
                        {{ date(form.start_date) }}</span
                    >
>>>>>>> origin/owner-refund-withdrawal-requests
                </div>
                <button
                    v-if="locks.length"
                    class="text-danger-btn"
                    type="button"
                    :disabled="Boolean(deletingId)"
                    @click.stop="
                        removeLocks(
                            locks,
                            'Mở tất cả khoảng đã khóa trong ngày này?',
                        )
                    "
                >
                    Mở tất cả
                </button>
            </summary>
            <div class="lock-list">
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
        </details>
    </section>
</template>

<script>
import { bookingService } from "../../services/bookingService.js";
import { ownerScheduleLockService } from "../../services/ownerScheduleLocks.js";
import MiniCalendar from "../../components/MiniCalendar.vue";

export default {
    name: "OwnerScheduleLocks",
    components: { MiniCalendar },
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
                reason: "",
            },
            selectedSlots: [],
            selectedCourtIds: [],
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
        lockPreviewIssues() {
            return this.lockPreviewRows.filter((row) => row.isBusy);
        },
        lockPreviewStats() {
            return {
                total: this.lockPreviewRows.length,
                knownBusy: this.lockPreviewRows.filter((row) => row.isBusy)
                    .length,
            };
        },
        dynamicQuickRanges() {
            const slotStarts = this.scheduleSlots.map((slot) =>
                this.minutes(slot.start_time),
            );
            const slotEnds = this.scheduleSlots.map((slot) =>
                this.minutes(slot.end_time),
            );

            const open = slotStarts.length ? Math.min(...slotStarts) : 6 * 60;
            const close = Math.max(
                slotEnds.length ? Math.max(...slotEnds) : 22 * 60,
                open + 30,
            );
            const ranges = [
                {
                    key: "morning",
                    label: "Sáng",
                    startMinutes: open,
                    endMinutes: Math.min(close, 12 * 60),
                },
                {
                    key: "afternoon",
                    label: "Chiều",
                    startMinutes: Math.max(open, 12 * 60),
                    endMinutes: Math.min(close, 18 * 60),
                },
                {
                    key: "evening",
                    label: "Tối",
                    startMinutes: Math.max(open, 18 * 60),
                    endMinutes: close,
                },
            ]
                .filter((range) => range.endMinutes > range.startMinutes)
                .map((range) => ({
                    ...range,
                    start: this.minutesToTime(range.startMinutes),
                    end: this.minutesToTime(range.endMinutes),
                    range: `${this.minutesToTime(range.startMinutes)} - ${this.minutesToTime(range.endMinutes)}`,
                }));

            return ranges.length
                ? ranges
                : [
                      {
                          key: "all",
                          label: "Cả ngày",
                          startMinutes: open,
                          endMinutes: close,
                          start: this.minutesToTime(open),
                          end: this.minutesToTime(close),
                          range: `${this.minutesToTime(open)} - ${this.minutesToTime(close)}`,
                      },
                  ];
        },
        activePeriod() {
            return (
                this.dynamicQuickRanges.find(
                    (range) => range.key === this.activeTimePeriod,
                ) || this.dynamicQuickRanges[0]
            );
        },
        activePeriodSlots() {
            return this.scheduleSlots.filter((slot) => {
                const start = this.minutes(slot.start_time);
                return (
                    start >= this.activePeriod.startMinutes &&
                    start < this.activePeriod.endMinutes
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
            this.ensureActiveTimePeriod();
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
                    this.pendingLockPayload = {
                        ...payload,
                        lock_type: "emergency",
                    };
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
                lock_type: "manual",
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

            return status.busy_source === "booking";
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
                return "Đã có booking · có thể chọn để khóa và xử lý";
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
        minutesToTime(minutes) {
            const normalized = Math.max(0, Math.min(24 * 60, minutes));
            const hour = Math.floor(normalized / 60);
            const minute = normalized % 60;
            return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
        },
        ensureActiveTimePeriod() {
            if (
                !this.dynamicQuickRanges.some(
                    (range) => range.key === this.activeTimePeriod,
                )
            ) {
                this.activeTimePeriod =
                    this.dynamicQuickRanges[0]?.key || "morning";
            }
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
            return `${year}-${month}-${day}`;
        },
        date(value) {
            if (!value) return "-";
            return new Intl.DateTimeFormat("vi-VN").format(
                new Date(`${value}T00:00:00`),
            );
        },
        currency(value) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(Number(value || 0));
        },
    },
};
</script>

<style scoped>
/* ===== Page layout ===== */
.schedule-lock-page {
    display: grid;
    gap: 14px;
    max-width: 1400px;
    padding-bottom: 80px; /* space for sticky bar */
}

/* ===== Alerts ===== */
.alert {
    padding: 13px 15px;
    border-radius: 10px;
    font-weight: 800;
}
.alert.error {
    background: #fee2e2;
    color: #991b1b;
}
.alert.success {
    background: #dcfce7;
    color: #166534;
}

/* ===== Config Strip (calendar + settings) ===== */
.config-strip {
    display: grid;
    grid-template-columns: 236px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
    width: 100%;
    padding: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.035);
}

.config-left {
    flex: 0 0 auto;
    width: 236px;
}

.config-left :deep(.mini-cal) {
    border: 0;
    padding: 0;
    max-width: 236px;
}

.config-right {
    display: grid;
    grid-template-columns: minmax(220px, 280px) minmax(420px, 1fr);
    grid-template-rows: auto minmax(0, 1fr);
    gap: 12px;
    align-self: stretch;
    align-items: start;
}

.config-section {
    display: grid;
    gap: 8px;
}

.config-section:nth-child(3) {
    grid-column: 1 / -1;
    max-width: none;
}

.lock-preview-panel {
    grid-column: 1 / -1;
    align-self: stretch;
}

.config-label {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

/* ===== Mode Switch ===== */
.mode-switch {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px;
    max-width: 280px;
    padding: 4px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
}
.mode-switch button {
    min-height: 36px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: #64748b;
    font: inherit;
    font-weight: 850;
    cursor: pointer;
    transition: all 0.15s ease;
}
.mode-switch button.active {
    background: var(--admin-primary, #16a34a);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.lock-flow-note {
    min-height: 64px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    margin: 0;
    padding: 12px 14px;
    border: 1px solid #dbe8d8;
    border-radius: 10px;
    background:
        linear-gradient(
            90deg,
            rgba(34, 197, 94, 0.08),
            rgba(255, 255, 255, 0.95)
        ),
        #fff;
}

.lock-flow-note strong {
    color: #14532d;
    font-size: 13px;
    font-weight: 900;
}

.lock-flow-note span {
    color: #64756b;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
}

.incident-summary {
    color: #b45309 !important;
    font-weight: 800;
}

/* ===== Reason ===== */
.reason-label {
    display: grid;
    gap: 7px;
}
.reason-label span {
    color: #334155;
    font-size: 13px;
    font-weight: 850;
}
.reason-label textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 10px 11px;
    background: #fff;
    color: #0f172a;
    font: inherit;
    min-height: 76px;
    resize: vertical;
}

/* ===== Court Picker ===== */
.court-picker {
    display: grid;
    gap: 12px;
    padding: 14px;
    border: 1px solid #d9e8d9;
    border-radius: 10px;
    background: linear-gradient(180deg, #fbfefb, #f4fbf5);
}
.picker-head {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #14532d;
}
.picker-head strong {
    flex: 1;
    font-size: 14px;
}
.picker-head span {
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--admin-primary-soft, #dcfce7);
    color: var(--admin-primary-dark, #166534);
    font-size: 12px;
    font-weight: 900;
}
.picker-head button {
    border: 0;
    background: transparent;
    color: var(--admin-primary, #16a34a);
    font: inherit;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}
.court-chip-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 8px;
}
.court-chip-grid label {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 56px;
    padding: 10px 32px 10px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    transition: all 0.15s ease;
}
.court-chip-grid label::after {
    content: "";
    position: absolute;
    top: 10px;
    right: 10px;
    width: 16px;
    height: 16px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #fff;
    box-shadow: inset 0 0 0 3px #fff;
}
.court-chip-grid label:hover {
    border-color: var(--admin-border);
    background: var(--admin-hover);
}
.court-chip-grid label.active {
    border-color: var(--admin-primary);
    background: var(--admin-primary-soft);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.court-chip-grid label.active::after {
    border-color: var(--admin-primary);
    background: var(--admin-primary);
}
.court-chip-grid input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}
.court-chip-grid span {
    display: grid;
    gap: 2px;
}
.court-chip-grid strong {
    color: #1f2f25;
    font-size: 13px;
}
.court-chip-grid small {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

/* ===== Preview (collapsible) ===== */
.preview-details {
    border: 1px solid #d9e8d9;
    border-radius: 12px;
    background: #fbfefc;
    overflow: hidden;
    width: 100%;
    height: 100%;
    min-width: 0;
    max-width: 100%;
}

.preview-details[open] {
    width: 100%;
    border-radius: 12px;
}

.preview-details summary {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 46px;
    padding: 10px 13px;
    cursor: pointer;
    user-select: none;
}

.preview-details summary::-webkit-details-marker {
    display: none;
}

.preview-details summary::after {
    content: "Xem";
    margin-left: auto;
    color: #15803d;
    font-size: 12px;
    font-weight: 900;
}

.preview-details[open] summary::after {
    content: "Thu gọn";
}

.preview-details summary strong {
    color: #16231a;
    font-size: 13px;
}
.preview-details summary span {
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
}
.preview-details summary em {
    color: #b45309;
    font-size: 12px;
    font-style: normal;
    font-weight: 750;
}

.lock-empty-preview {
    width: 100%;
    min-width: 0;
    height: 100%;
    min-height: 74px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border: 1px solid #d9e8d9;
    border-radius: 10px;
    background:
        linear-gradient(
            180deg,
            rgba(240, 253, 244, 0.72),
            rgba(255, 255, 255, 0.95)
        ),
        #fff;
}

.lock-empty-preview::before {
    content: "";
    flex: 0 0 auto;
    width: 8px;
    height: 34px;
    border-radius: 999px;
    background: #22c55e;
    opacity: 0.75;
}

.lock-empty-preview strong {
    flex: 0 0 auto;
    color: #14532d;
    font-size: 14px;
    font-weight: 900;
}

.lock-empty-preview span {
    color: #607267;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
    max-width: 760px;
}

.lock-preview-list {
    display: grid;
    gap: 6px;
    max-height: 200px;
    overflow-y: auto;
    padding: 0 14px 14px;
}
.lock-preview-list article {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}
.lock-preview-list article.busy {
    border-color: #fed7aa;
    background: #fff7ed;
}
.lock-preview-list article > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}
.lock-preview-list strong {
    color: #1f2937;
    font-size: 12px;
}
.lock-preview-list small {
    color: #64748b;
    font-size: 11px;
}
.lock-preview-list article > span {
    flex: 0 0 auto;
    padding: 3px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #475569;
    font-size: 11px;
    font-weight: 900;
}
.lock-preview-list article.busy > span {
    background: #ffedd5;
    color: #c2410c;
}
.preview-more {
    display: block;
    padding: 4px 14px 12px;
    color: #607267;
    font-size: 12px;
}

/* ===== Schedule Card ===== */
.schedule-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.schedule-headline {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
}
.schedule-headline-left h3 {
    margin: 0;
    color: #0f172a;
}
.schedule-headline-right {
    display: flex;
    align-items: center;
    gap: 16px;
}
.eyebrow {
    margin: 0 0 4px;
    color: var(--admin-muted, #64748b);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.1em;
}

/* ===== Legend ===== */
.legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
}
.legend span {
    display: flex;
    align-items: center;
    gap: 5px;
}
.legend i {
    width: 11px;
    height: 11px;
    border-radius: 3px;
    border: 1px solid #cbd5e1;
}
.dot-available {
    background: #fff;
}
.dot-booking {
    background: #cbd5e1;
}
.dot-holding {
    background: #fde68a;
}
.dot-manual {
    background: #fca5a5;
}
.dot-selected {
    background: var(--admin-primary, #16a34a);
    border-color: var(--admin-primary, #16a34a);
}

/* ===== Quick Ranges ===== */
.quick-ranges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 14px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}
.quick-ranges button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 38px;
    padding: 8px 14px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
    color: #344238;
    font: inherit;
    font-weight: 850;
    cursor: pointer;
    transition: all 0.15s ease;
}
.quick-ranges button strong {
    font-size: 14px;
    font-weight: 850;
}
.quick-ranges button small {
    font-size: 12px;
    font-weight: 700;
    opacity: 0.78;
}
.quick-ranges button:hover:not(:disabled):not(.active) {
    border-color: var(--admin-border);
    background: var(--admin-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 9px rgba(0, 0, 0, 0.04);
}
.quick-ranges button.active {
    border-color: var(--admin-primary, #16a34a);
    background: var(--admin-primary, #16a34a);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.quick-ranges button.active strong,
.quick-ranges button.active small {
    color: #fff;
}
.quick-ranges button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

/* ===== States ===== */
.state {
    padding: 40px;
    text-align: center;
    color: #64748b;
    font-weight: 700;
}

/* ===== Schedule Grid ===== */
.schedule-wrap {
    max-width: 100%;
    overflow-x: auto;
}
.schedule-grid {
    display: grid;
    width: 100%;
    min-width: 760px;
}
.grid-head,
.court-cell,
.slot-cell {
    min-height: 48px;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}
.grid-head {
    display: grid;
    place-items: center;
    background: #f2f7ef;
    color: #334238;
    font-size: 11px;
    font-weight: 900;
}
.time-head {
    padding: 8px 4px;
    white-space: nowrap;
}
.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
}
.grid-head.sticky-col {
    z-index: 3;
}
.court-cell {
    display: grid;
    align-content: center;
    gap: 3px;
    padding: 8px 10px;
    background: #fff;
}
.court-cell strong {
    color: #0f172a;
    font-size: 12px;
}
.court-cell span {
    color: #64748b;
    font-size: 11px;
}
.slot-cell {
    min-width: 0;
    padding: 0;
    border-top: 0;
    border-left: 0;
    background: #fff;
    cursor: pointer;
    transition:
        background 0.12s ease,
        box-shadow 0.12s ease;
}
.slot-cell.available:hover {
    background: #d1fae5;
    box-shadow: inset 0 0 0 1px rgba(5, 150, 105, 0.35);
}
.slot-cell.booking {
    background: #cbd5e1;
}
.slot-cell.holding {
    background: #fde68a;
}
.slot-cell.manual {
    background: repeating-linear-gradient(
        -45deg,
        #fca5a5,
        #fca5a5 4px,
        #fecaca 4px,
        #fecaca 8px
    );
}
.slot-cell.selected {
    background: var(--admin-primary, #16a34a);
    box-shadow: inset 0 0 0 2px var(--admin-primary-light, #22c55e);
}
.slot-cell.selected:hover {
    background: var(--admin-primary-dark, #15803d);
}
.slot-cell:disabled {
    cursor: not-allowed;
}

/* ===== Existing Locks Section ===== */
.locks-section {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.locks-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    cursor: pointer;
    user-select: none;
}
.locks-summary > div {
    display: grid;
    gap: 3px;
}
.locks-summary strong {
    color: #0f172a;
    font-size: 15px;
}
.locks-summary span {
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
}
.lock-list {
    display: grid;
    gap: 10px;
    padding: 0 20px 20px;
}
.lock-group {
    display: grid;
    gap: 10px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}
.lock-group-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.lock-group-head > div {
    display: grid;
    gap: 2px;
}
.lock-group-head strong {
    color: #0f172a;
    font-size: 14px;
}
.lock-group-head span {
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
}
.lock-group-head button,
.text-danger-btn {
    border: 0;
    background: transparent;
    color: #dc2626;
    font: inherit;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}
.lock-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.lock-chip-list button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: 1px solid #fecaca;
    border-radius: 999px;
    padding: 7px 10px;
    background: #fff;
    color: #991b1b;
    font: inherit;
    cursor: pointer;
    transition: all 0.12s ease;
}
.lock-chip-list button strong {
    font-size: 12px;
    font-weight: 900;
}
.lock-chip-list button span {
    color: #dc2626;
    font-size: 11px;
    font-weight: 900;
}
.lock-chip-list button:hover:not(:disabled) {
    border-color: #fca5a5;
    background: #fff5f5;
}
.lock-chip-list button:disabled,
.lock-group-head button:disabled,
.text-danger-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* ===== Sticky Bottom Bar ===== */
.sticky-bottom-bar {
    position: fixed;
    bottom: 16px;
    left: var(--owner-sidebar-width, 280px);
    right: 24px;
    z-index: 100;
    display: flex;
    justify-content: center;
    padding: 0;
    pointer-events: none;
}
.sticky-bottom-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    width: min(1400px, 100%);
    margin: 0;
    padding: 14px 20px;
    border: 1px solid #d9e8d9;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    box-shadow: 0 -4px 24px rgba(15, 23, 42, 0.12);
    pointer-events: auto;
}
.sticky-bottom-info {
    display: grid;
    gap: 3px;
}
.sticky-bottom-info strong {
    color: #0f172a;
    font-size: 15px;
}
.sticky-bottom-info span {
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
}
.sticky-bottom-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.sticky-btn-clear {
    border: 0;
    background: transparent;
    color: #dc2626;
    font: inherit;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
}
.sticky-btn-submit {
    min-height: 42px;
    padding: 0 20px;
    border: 0;
    border-radius: 10px;
    background: var(--admin-primary, #16a34a);
    color: #fff;
    font: inherit;
    font-weight: 900;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: all 0.15s ease;
}
.sticky-btn-submit:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
}
.sticky-btn-submit:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* ===== Buttons ===== */
.primary-btn,
.secondary-btn {
    border: 0;
    border-radius: 9px;
    padding: 10px 16px;
    font: inherit;
    font-weight: 850;
    cursor: pointer;
}
.primary-btn {
    background: var(--admin-primary, #16a34a);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.secondary-btn {
    border: 1px solid var(--admin-border, #e5e7eb);
    background: #fff;
    color: var(--admin-text, #0f172a);
}
.btn-compact {
    padding: 8px 14px;
    font-size: 13px;
}
.primary-btn:disabled,
.secondary-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* ===== Modal ===== */
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 120;
    display: grid;
    place-items: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.55);
}
.conflict-modal {
    width: min(900px, 100%);
    max-height: min(760px, calc(100vh - 48px));
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr) auto;
    gap: 14px;
    overflow: hidden;
    border-radius: 14px;
    border: 1px solid #d7ead7;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
}
.conflict-modal header,
.conflict-modal footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 0;
}
.conflict-modal footer {
    padding: 0 20px 18px;
}
.conflict-modal h3 {
    margin: 0;
    color: #163222;
    font-size: 20px;
}
.icon-close {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border: 1px solid #d8e8d8;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    cursor: pointer;
}
.conflict-help {
    margin: 0 20px;
    padding: 12px 14px;
    border-radius: 8px;
    background: #f0fdf4;
    color: #496355;
    font-size: 13px;
    line-height: 1.45;
}
.conflict-list {
    display: grid;
    gap: 10px;
    min-height: 0;
    overflow-y: auto;
    padding: 0 20px;
}
.conflict-card {
    display: grid;
    gap: 12px;
    padding: 14px;
    border: 1px solid #e2eadf;
    border-radius: 10px;
    background: #fbfffb;
}
.conflict-main {
    display: grid;
    gap: 4px;
}
.conflict-main strong {
    color: #12301f;
    font-size: 14px;
}
.conflict-main span,
.conflict-main small {
    color: #5d7165;
    font-size: 12px;
    line-height: 1.35;
}
.conflict-actions {
    display: grid;
    gap: 8px;
}
.conflict-select {
    width: 100%;
    height: 38px;
    border: 1px solid #d8e8d8;
    border-radius: 8px;
    padding: 0 10px;
    background: #fff;
    color: #1f2937;
    font: inherit;
    font-weight: 750;
}
.conflict-radios {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.radio-line {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #2f5a3a;
    font-size: 13px;
    font-weight: 850;
    white-space: nowrap;
    cursor: pointer;
}
.radio-line.danger {
    color: #b91c1c;
}
.radio-line.cash {
    color: #b45309;
}

/* ===== Responsive ===== */
@media (max-width: 860px) {
    .sticky-bottom-bar {
        left: 12px;
        right: 12px;
        bottom: 12px;
    }

    .config-strip {
        grid-template-columns: 1fr;
    }
    .config-right {
        grid-template-columns: 1fr;
    }
    .config-section:nth-child(3),
    .config-section:nth-child(n + 4),
    .preview-details,
    .lock-empty-preview {
        grid-column: auto;
        grid-row: auto;
    }

    .lock-empty-preview {
        width: 100%;
        min-width: 0;
        align-items: flex-start;
    }
    .preview-details {
        width: 100%;
        min-width: 0;
    }
    .config-left {
        display: flex;
        justify-content: center;
        width: 100%;
    }
    .schedule-headline {
        flex-direction: column;
        gap: 12px;
    }
    .schedule-headline-right {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .legend {
        justify-content: flex-start;
    }
    .quick-ranges {
        padding: 14px;
    }
    .quick-ranges button {
        flex: 1 1 120px;
    }
    .sticky-bottom-inner {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }
}
</style>

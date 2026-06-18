<template>
    <section class="schedule-lock-page">
        <header class="page-head">
            <div>
                <p class="eyebrow">LỊCH SÂN</p>
                <h2>Khóa lịch theo khung giờ</h2>
                <p>
                    Ngừng nhận khách trong thời gian bảo trì, nghỉ hoặc tổ chức
                    sự kiện riêng.
                </p>
            </div>
            <button
                class="secondary-btn"
                type="button"
                :disabled="loading"
                @click="loadData"
            >
                Làm mới
            </button>
        </header>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

        <div class="content-grid">
            <article class="form-card">
                <div class="card-head">
                    <div>
                        <p class="eyebrow">TẠO KHÓA MỚI</p>
                        <h3>Chọn khoảng không nhận khách</h3>
                    </div>
                </div>

                <form @submit.prevent="createLock">
                    <label>
                        Ngày khóa
                        <input
                            v-model="form.booking_date"
                            type="date"
                            :min="today"
                            required
                            @change="handleDateChange"
                        />
                    </label>

                    <div
                        class="selection-box"
                        :class="{ empty: !selectedSlots.length }"
                    >
                        <strong
                            >{{ selectedSlots.length }} ô giờ trên
                            {{ selectedCourtCount }} sân</strong
                        >
                        <button
                            v-if="selectedSlots.length"
                            type="button"
                            @click="clearSelection"
                        >
                            Bỏ chọn tất cả
                        </button>
                    </div>

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
                        :disabled="saving || !canSubmit"
                    >
                        {{
                            saving
                                ? "Đang khóa..."
                                : `Khóa ${selectedSlots.length} ô đã chọn`
                        }}
                    </button>
                </form>
            </article>

            <article class="schedule-card">
                <div class="card-head schedule-headline">
                    <div>
                        <p class="eyebrow">TRẠNG THÁI TRONG NGÀY</p>
                        <h3>{{ date(form.booking_date) }}</h3>
                    </div>
                    <div class="legend">
                        <span><i class="available"></i>Trống</span>
                        <span><i class="booking"></i>Đã đặt</span>
                        <span><i class="holding"></i>Đang giữ</span>
                        <span><i class="manual"></i>Đã khóa</span>
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
                                :disabled="isBusy(court.id, slot)"
                                :aria-pressed="isSelected(court.id, slot)"
                                @click="pickSlot(court, slot)"
                            />
                        </template>
                    </div>
                </div>
            </article>
        </div>

        <article class="locks-card">
            <div class="card-head">
                <div>
                    <p class="eyebrow">KHÓA THỦ CÔNG</p>
                    <h3>Các khoảng đã khóa trong ngày</h3>
                </div>
                <span class="count-badge">{{ locks.length }} khoảng</span>
            </div>

            <div v-if="!locks.length" class="state">
                Ngày này chưa có khoảng khóa thủ công.
            </div>
            <div v-else class="lock-list">
                <div v-for="lock in locks" :key="lock.id" class="lock-row">
                    <div class="lock-time">
                        <strong
                            >{{ time(lock.start_time) }} -
                            {{ time(lock.end_time) }}</strong
                        >
                        <span>{{ lock.venue_court?.name }}</span>
                    </div>
                    <p>{{ lock.reason }}</p>
                    <button
                        class="danger-btn"
                        type="button"
                        :disabled="deletingId === lock.id"
                        @click="removeLock(lock)"
                    >
                        {{ deletingId === lock.id ? "Đang mở..." : "Mở lịch" }}
                    </button>
                </div>
            </div>
        </article>
    </section>
</template>

<script>
import { bookingService } from "../../services/bookingService.js";
import { ownerScheduleLockService } from "../../services/ownerScheduleLocks.js";

export default {
    name: "OwnerScheduleLocks",
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
                booking_date: today,
                reason: "",
            },
            selectedSlots: [],
            quickRanges: [
                { key: "early", label: "Đêm", start: "00:00", end: "06:00" },
                { key: "morning", label: "Sáng", start: "06:00", end: "12:00" },
                {
                    key: "afternoon",
                    label: "Chiều",
                    start: "12:00",
                    end: "18:00",
                },
                { key: "evening", label: "Tối", start: "18:00", end: "22:00" },
                { key: "late", label: "Khuya", start: "22:00", end: "24:00" },
            ],
            activeTimePeriod: "morning",
        };
    },
    computed: {
        canSubmit() {
            return (
                this.selectedSlots.length &&
                this.form.booking_date &&
                this.form.reason
            );
        },
        selectedCourtCount() {
            return new Set(
                this.selectedSlots.map((slot) => slot.venue_court_id),
            ).size;
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
        async handleDateChange() {
            this.clearSelection();
            await Promise.all([this.loadSchedule(), this.loadLocks()]);
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
            if (!this.selectedClusterId || !this.form.booking_date) return;

            const response = await bookingService.getSchedule({
                venue_cluster_id: this.selectedClusterId,
                booking_date: this.form.booking_date,
                booking_type: "single",
            });
            this.scheduleSlots = response.time_slots || [];
            this.scheduleCourts = response.courts || [];
            this.scheduleSlotStatuses = response.slot_statuses || [];
        },
        async loadLocks() {
            if (!this.selectedClusterId || !this.form.booking_date) return;

            const response = await ownerScheduleLockService.list({
                venue_cluster_id: this.selectedClusterId,
                booking_date: this.form.booking_date,
            });
            this.locks = response.data || [];
        },
        async createLock() {
            if (!this.canSubmit) return;

            this.saving = true;
            this.error = "";
            this.notice = "";
            try {
                const response = await ownerScheduleLockService.create({
                    booking_date: this.form.booking_date,
                    reason: this.form.reason,
                    slots: this.buildSelectedRanges(),
                });
                this.notice = response.message;
                this.form.reason = "";
                this.clearSelection();
                await Promise.all([this.loadSchedule(), this.loadLocks()]);
            } catch (error) {
                this.error = error.message || "Không thể khóa khung giờ.";
            } finally {
                this.saving = false;
            }
        },
        async removeLock(lock) {
            if (
                !window.confirm(
                    `Mở lại ${this.time(lock.start_time)} - ${this.time(lock.end_time)} tại ${lock.venue_court?.name}?`,
                )
            )
                return;

            this.deletingId = lock.id;
            this.error = "";
            this.notice = "";
            try {
                const response = await ownerScheduleLockService.remove(lock.id);
                this.notice = response.message;
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
        slotClass(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.isSelected(courtId, slot)) return "selected";
            if (!status || status.is_available) return "available";
            if (status.busy_source === "booking") return "booking";
            if (status.busy_status === "manual") return "manual";
            return "holding";
        },
        slotTitle(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.isSelected(courtId, slot))
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Đã chọn`;
            if (!status || status.is_available)
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Trống`;
            if (status.busy_source === "booking") return "Đã có booking";
            if (status.busy_status === "manual")
                return `Đã khóa: ${status.lock_reason || "Không có lý do"}`;
            return "Đang được giữ chỗ";
        },
        pickSlot(court, slot) {
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
        date(value) {
            if (!value) return "-";
            return new Intl.DateTimeFormat("vi-VN").format(
                new Date(`${value}T00:00:00`),
            );
        },
    },
};
</script>

<style scoped>
.schedule-lock-page {
    display: grid;
    gap: 18px;
    max-width: 1320px;
}
.page-head,
.card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}
.page-head h2,
.card-head h3 {
    margin: 0;
    color: #0f172a;
}
.page-head > div > p:last-child {
    margin: 7px 0 0;
    color: #64748b;
}
.eyebrow {
    margin: 0 0 6px;
    color: #059669;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.1em;
}
.content-grid {
    display: grid;
    grid-template-columns: 330px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}
.form-card,
.schedule-card,
.locks-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.04);
}
.form-card,
.locks-card {
    padding: 20px;
}
.form-card form {
    display: grid;
    gap: 15px;
    margin-top: 18px;
}
.form-card label {
    display: grid;
    gap: 7px;
    color: #334155;
    font-size: 13px;
    font-weight: 850;
}
.form-card input,
.form-card select,
.form-card textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 10px 11px;
    background: #fff;
    color: #0f172a;
    font: inherit;
}
.time-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.form-note {
    padding: 11px;
    border-radius: 9px;
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}
.primary-btn,
.secondary-btn,
.danger-btn {
    border: 0;
    border-radius: 9px;
    padding: 10px 14px;
    font: inherit;
    font-weight: 850;
    cursor: pointer;
}
.primary-btn {
    background: #059669;
    color: #fff;
}
.secondary-btn {
    background: #f1f5f9;
    color: #334155;
}
.danger-btn {
    background: #fee2e2;
    color: #991b1b;
}
.primary-btn:disabled,
.secondary-btn:disabled,
.danger-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
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
.schedule-card {
    overflow: hidden;
}
.schedule-headline {
    padding: 18px 20px;
    border-bottom: 1px solid #e2e8f0;
}
.legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 12px;
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
.legend .available {
    background: #fff;
}
.legend .booking {
    background: #cbd5e1;
}
.legend .holding {
    background: #fde68a;
}
.legend .manual {
    background: #fca5a5;
}
.state {
    padding: 36px;
    text-align: center;
    color: #64748b;
}
.schedule-wrap {
    max-width: 100%;
    overflow-x: auto;
    border-top: 1px solid #e2e8f0;
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
    background: #fca5a5;
}
.slot-cell:disabled {
    cursor: not-allowed;
}
.locks-card .card-head {
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}
.count-badge {
    padding: 5px 9px;
    border-radius: 999px;
    background: #ecfdf5;
    color: #047857;
    font-size: 12px;
    font-weight: 850;
}
.lock-list {
    display: grid;
}
.lock-row {
    display: grid;
    grid-template-columns: 210px 1fr auto;
    gap: 16px;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid #e2e8f0;
}
.lock-row:last-child {
    border-bottom: 0;
}
.lock-time {
    display: grid;
    gap: 4px;
}
.lock-time strong {
    color: #0f172a;
}
.lock-time span,
.lock-row p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}
@media (max-width: 1050px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    .form-card form {
        grid-template-columns: repeat(2, 1fr);
    }
    .form-note,
    .primary-btn,
    .form-card label:last-of-type {
        grid-column: 1/-1;
    }
}
@media (max-width: 720px) {
    .page-head,
    .schedule-headline {
        display: grid;
    }
    .form-card form {
        grid-template-columns: 1fr;
    }
    .time-grid {
        grid-template-columns: 1fr;
    }
    .form-note,
    .primary-btn,
    .form-card label:last-of-type {
        grid-column: auto;
    }
    .lock-row {
        grid-template-columns: 1fr;
    }
    .legend {
        justify-content: flex-start;
    }
}
</style>
<style scoped>
.selection-box {
    display: grid;
    gap: 5px;
    padding: 12px;
    border: 1px solid #6ee7b7;
    border-radius: 9px;
    background: #ecfdf5;
    color: #047857;
}
.selection-box.empty {
    border-color: #cbd5e1;
    background: #f8fafc;
    color: #64748b;
}
.selection-box span {
    font-size: 12px;
    line-height: 1.45;
}
.selection-box button {
    justify-self: start;
    border: 0;
    padding: 0;
    background: transparent;
    color: #dc2626;
    font: inherit;
    font-size: 12px;
    font-weight: 850;
    cursor: pointer;
}
.slot-cell.selected {
    background: #10b981;
    box-shadow: inset 0 0 0 2px #047857;
}
.slot-cell.selected:hover {
    background: #059669;
}
.quick-ranges {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    padding: 14px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}
.quick-ranges button {
    appearance: none;
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 38px;
    margin: 0;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    padding: 8px 12px;
    background: #fff;
    color: #344238;
    font-family: inherit;
    font-weight: 850;
    line-height: 1;
    white-space: nowrap;
    cursor: pointer;
    box-shadow: none;
    transition:
        background 0.16s ease,
        border-color 0.16s ease,
        color 0.16s ease,
        transform 0.16s ease,
        box-shadow 0.16s ease;
}
.quick-ranges button strong {
    color: inherit;
    font-size: 15px;
    font-weight: 850;
    line-height: 1;
}
.quick-ranges button small {
    color: inherit;
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.15;
    opacity: 0.78;
    transform: translateY(1px);
}
.quick-ranges button:hover:not(:disabled):not(.active) {
    border-color: #86efac;
    background: #f0fdf4;
    color: #2f5a3a;
    transform: translateY(-1px);
    box-shadow: 0 4px 9px rgba(22, 163, 74, 0.08);
}
.quick-ranges button:hover:not(:disabled):not(.active) small {
    opacity: 0.84;
}
.quick-ranges button.active {
    border-color: #2f9e44;
    background: #2f9e44;
    color: #fff;
    box-shadow: 0 5px 12px rgba(22, 163, 74, 0.18);
}
.quick-ranges button.active strong,
.quick-ranges button.active small {
    color: #fff !important;
}
.quick-ranges button.active small {
    opacity: 0.88;
}
.quick-ranges button:focus-visible {
    outline: 3px solid rgba(47, 158, 68, 0.22);
    outline-offset: 2px;
}
.quick-ranges button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
@media (max-width: 720px) {
    .quick-ranges {
        gap: 8px;
        padding: 14px;
    }
    .quick-ranges button {
        flex: 1 1 132px;
        padding: 0 12px;
    }
    .quick-ranges button strong {
        font-size: 15px;
    }
    .quick-ranges button small {
        font-size: 11px;
    }
}
</style>

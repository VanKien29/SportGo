<template>
    <Teleport to="body">
        <div v-if="booking" class="court-change-backdrop" @click.self="close">
            <form class="court-change-modal" @submit.prevent="submit">
                <header>
                    <div>
                        <span>ĐỔI SÂN BOOKING</span>
                        <h2>Chọn sân thay thế</h2>
                        <p>#{{ booking.booking_code }}</p>
                    </div>
                    <button type="button" class="court-change-close" @click="close">×</button>
                </header>

                <div class="court-change-body">
                    <label>
                        <span>Sân mới</span>
                        <select v-model="form.venue_court_id" :disabled="loading" required>
                            <option value="" disabled>{{ loading ? 'Đang tải sân còn trống...' : 'Chọn sân cùng loại' }}</option>
                            <option v-for="court in options" :key="court.id" :value="court.id">
                                {{ court.name }} · {{ court.court_type?.name || 'Cùng loại sân' }}
                            </option>
                        </select>
                    </label>
                    <p v-if="!loading && !options.length" class="court-change-empty">Không còn sân cùng loại trống trong khung giờ này.</p>
                    <label>
                        <span>Lý do đổi sân</span>
                        <textarea v-model.trim="form.court_changed_reason" rows="3" maxlength="1000" required placeholder="Ví dụ: sân cần bảo trì hoặc khách yêu cầu chuyển sân."></textarea>
                    </label>
                    <p v-if="error" class="court-change-error">{{ error }}</p>
                </div>

                <footer>
                    <button type="button" class="court-change-secondary" :disabled="saving" @click="close">Hủy</button>
                    <button type="submit" class="court-change-primary" :disabled="loading || saving || !form.venue_court_id || !form.court_changed_reason">
                        {{ saving ? 'Đang lưu...' : 'Xác nhận đổi sân' }}
                    </button>
                </footer>
            </form>
        </div>
    </Teleport>
</template>

<script>
import { ownerBookingService } from '../services/ownerBookings.js';

export default {
    name: 'BookingCourtChangeModal',
    props: {
        booking: { type: Object, default: null },
    },
    emits: ['close', 'saved'],
    data() {
        return {
            options: [],
            form: { venue_court_id: '', court_changed_reason: '' },
            loading: false,
            saving: false,
            error: '',
        };
    },
    watch: {
        booking: {
            immediate: true,
            async handler(value) {
                if (!value?.id) {
                    this.options = [];
                    return;
                }
                this.form = { venue_court_id: '', court_changed_reason: '' };
                this.error = '';
                this.loading = true;
                try {
                    const response = await ownerBookingService.courtOptions(value.id);
                    this.options = response.data || [];
                } catch (error) {
                    this.error = error.message || 'Không thể tải sân thay thế.';
                    this.options = [];
                } finally {
                    this.loading = false;
                }
            },
        },
    },
    methods: {
        close() {
            if (!this.saving) this.$emit('close');
        },
        async submit() {
            if (!this.booking?.id || this.saving || !this.form.venue_court_id || !this.form.court_changed_reason) return;
            this.saving = true;
            this.error = '';
            try {
                const response = await ownerBookingService.changeCourt(this.booking.id, this.form);
                this.$emit('saved', response.data || response);
                this.$emit('close');
            } catch (error) {
                this.error = error.message || 'Không thể đổi sân.';
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>

<style scoped>
.court-change-backdrop { position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center; padding: 20px; background: rgb(15 23 42 / 58%); }
.court-change-modal { width: min(500px, calc(100vw - 32px)); overflow: hidden; border: 1px solid #cbd5e1; border-radius: 14px; background: #fff; box-shadow: 0 24px 70px rgb(15 23 42 / 24%); }
.court-change-modal header, .court-change-modal footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 20px; }
.court-change-modal header { border-bottom: 1px solid #e2e8f0; }
.court-change-modal header span { color: #5c7e6e; font-size: 10px; font-weight: 800; letter-spacing: .08em; }
.court-change-modal h2, .court-change-modal p { margin: 4px 0 0; }
.court-change-modal h2 { color: #0f172a; font-size: 19px; }
.court-change-modal header p { color: #64748b; font-size: 12px; }
.court-change-close { border: 0; background: transparent; color: #64748b; cursor: pointer; font-size: 26px; line-height: 1; }
.court-change-body { display: grid; gap: 14px; padding: 20px; }
.court-change-body label { display: grid; gap: 7px; color: #334155; font-size: 13px; font-weight: 600; }
.court-change-body select, .court-change-body textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; color: #0f172a; font: inherit; }
.court-change-body textarea { resize: vertical; }
.court-change-body select:focus, .court-change-body textarea:focus { border-color: #5c7e6e; outline: 2px solid rgb(92 126 110 / 18%); }
.court-change-empty, .court-change-error { font-size: 12px; }
.court-change-empty { color: #b45309; }
.court-change-error { color: #dc2626; }
.court-change-modal footer { justify-content: flex-end; border-top: 1px solid #e2e8f0; }
.court-change-secondary, .court-change-primary { border-radius: 8px; cursor: pointer; padding: 10px 14px; font: inherit; font-weight: 600; }
.court-change-secondary { border: 1px solid #cbd5e1; background: #fff; color: #334155; }
.court-change-primary { border: 1px solid #5c7e6e; background: #5c7e6e; color: #fff; }
.court-change-secondary:disabled, .court-change-primary:disabled { cursor: not-allowed; opacity: .55; }
</style>

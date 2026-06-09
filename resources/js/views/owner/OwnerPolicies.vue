<template>
  <section class="page">
    <header class="hero-card">
      <div>
        <p class="eyebrow">Chính sách sân</p>
        <h2>Cấu hình chính sách cho cụm sân</h2>
        <p>Chỉnh các quy tắc được hệ thống cho phép và tạo nội quy ngắn để khách đọc trước khi đặt sân.</p>
      </div>
      <div class="hero-cluster" :class="clusterTone">
        <AppIcon name="building" size="18" />
        <div>
          <span>Cụm sân đang chọn</span>
          <strong>{{ venueCluster?.name || 'Chưa chọn cụm sân' }}</strong>
        </div>
      </div>
    </header>

    <section class="stat-grid">
      <article class="stat-card">
        <AppIcon name="fileText" size="20" />
        <strong>{{ systemPolicies.length }}</strong>
        <span>Chính sách cho phép cấu hình</span>
      </article>
      <article class="stat-card success">
        <AppIcon name="circleCheck" size="20" />
        <strong>{{ configuredRuleCount }}</strong>
        <span>Quy tắc sân đã đặt</span>
      </article>
      <article class="stat-card warning">
        <AppIcon name="alert" size="20" />
        <strong>{{ rulesNeedConfigCount }}</strong>
        <span>Cần rà soát</span>
      </article>
      <article class="stat-card dark">
        <AppIcon name="eye" size="20" />
        <strong>{{ activeNoticeCount }}</strong>
        <span>Nội quy đang hiển thị</span>
      </article>
    </section>

    <div v-if="venueCluster && venueCluster.status !== 'active'" class="cluster-alert">
      <AppIcon name="alert" size="18" />
      <span>{{ venueCluster.status_reason || 'Cụm sân không ở trạng thái hoạt động. Một số thao tác có thể bị hạn chế.' }}</span>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <nav class="tabs" aria-label="Nhóm chính sách sân">
      <button :class="{ active: tab === 'rules' }" type="button" @click="tab = 'rules'">
        <AppIcon name="sliders" size="17" />
        Quy tắc áp dụng hệ thống
      </button>
      <button :class="{ active: tab === 'notices' }" type="button" @click="tab = 'notices'">
        <AppIcon name="fileText" size="17" />
        Quy định hiển thị cho khách
      </button>
    </nav>

    <section v-if="tab === 'rules'" class="rules-section">
      <div class="section-title">
        <div>
          <h3>Quy tắc có thể cấu hình riêng</h3>
          <p>Mỗi card cho biết khung hệ thống, giá trị sân đang đặt và giới hạn được phép.</p>
        </div>
        <button class="btn secondary" type="button" :disabled="loading" @click="load">
          <AppIcon name="refresh" size="16" />
          Tải lại
        </button>
      </div>

      <div v-if="loading" class="state">
        <span class="spinner"></span>
        Đang tải chính sách sân...
      </div>

      <div v-else-if="systemPolicies.length === 0" class="empty-state">
        <AppIcon name="fileText" size="28" />
        <strong>Chưa có chính sách nào cho phép sân cấu hình riêng</strong>
        <span>Admin cần bật quyền cấu hình riêng cho chính sách hệ thống trước.</span>
      </div>

      <article v-for="policy in systemPolicies" v-else :key="policy.id" class="policy-card">
        <header class="policy-head">
          <div>
            <span class="policy-type">{{ policy.policy_type_label }}</span>
            <h3>{{ policy.title }}</h3>
            <p>{{ policy.business_summary }}</p>
          </div>
          <span class="rule-count">{{ policy.rules?.length || 0 }} quy tắc</span>
        </header>

        <div class="rule-grid">
          <article v-for="rule in policy.rules" :key="rule.id" class="rule-card" :class="ruleTone(rule)">
            <div class="rule-top">
              <span class="rule-icon">
                <AppIcon :name="ruleIcon(rule)" size="18" />
              </span>
              <div>
                <strong>{{ rule.rule_label }}</strong>
                <span>{{ rule.action_label }}</span>
              </div>
              <span class="status-pill" :class="ruleTone(rule)">{{ ruleStatusLabel(rule) }}</span>
            </div>

            <p class="rule-summary">{{ rule.business_summary }}</p>

            <div class="metric-grid">
              <div>
                <small>Hệ thống</small>
                <strong>{{ systemValueText(rule) }}</strong>
              </div>
              <div>
                <small>Sân đang đặt</small>
                <strong>{{ venueValueText(rule) }}</strong>
              </div>
              <div>
                <small>Giới hạn</small>
                <strong>{{ rule.limit_summary || 'Theo khung hệ thống' }}</strong>
              </div>
            </div>

            <div class="preview-box">
              <AppIcon name="eye" size="16" />
              <span>{{ rule.preview_summary || previewText(rule) }}</span>
            </div>

            <footer class="rule-actions">
              <button class="btn primary" type="button" :disabled="!rule.can_override" @click="openRule(rule)">
                <AppIcon name="settings" size="16" />
                Cấu hình
              </button>
              <details>
                <summary>Xem dữ liệu kỹ thuật</summary>
                <pre>{{ technicalText(rule) }}</pre>
              </details>
            </footer>
          </article>
        </div>
      </article>
    </section>

    <section v-else class="notice-section">
      <div class="section-title">
        <div>
          <h3>Quy định hiển thị cho khách</h3>
          <p>Đây là nội quy dạng văn bản. Không sinh rule tự động, không ảnh hưởng hoàn tiền hay booking.</p>
        </div>
        <button class="btn primary" type="button" @click="openNotice()">
          <AppIcon name="plus" size="16" />
          Thêm quy định
        </button>
      </div>

      <div class="notice-note">
        <AppIcon name="messageWarning" size="18" />
        <span>Khách sẽ đọc các quy định này khi xem sân. Hãy viết ngắn, rõ, đúng thực tế vận hành.</span>
      </div>

      <div v-if="customerNotices.length === 0" class="empty-state">
        <AppIcon name="fileText" size="28" />
        <strong>Chưa có nội quy hiển thị cho khách</strong>
        <span>Thêm các quy định như gửi xe, giày dép, vệ sinh, phòng thay đồ.</span>
      </div>

      <div v-else class="notice-grid">
        <article v-for="notice in customerNotices" :key="notice.id" class="notice-card">
          <header>
            <span class="notice-icon"><AppIcon name="fileText" size="18" /></span>
            <span class="badge" :class="noticeTone(notice.status)">{{ notice.status_label }}</span>
          </header>
          <strong>{{ notice.title }}</strong>
          <p>{{ notice.content }}</p>
          <footer>
            <span>{{ notice.business_summary }}</span>
            <button class="icon-btn" type="button" title="Sửa quy định" @click="openNotice(notice)">
              <AppIcon name="pencil" size="16" />
            </button>
          </footer>
        </article>
      </div>
    </section>

    <div v-if="ruleModal" class="modal-backdrop" @click.self="closeRule">
      <form class="modal wide" @submit.prevent="saveRule">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Cấu hình quy tắc sân</p>
            <h3>{{ ruleModal.rule_label }}</h3>
            <span>{{ ruleModal.action_label }}</span>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeRule">
            <AppIcon name="x" size="18" />
          </button>
        </header>

        <section class="modal-summary">
          <div>
            <small>Quy định hệ thống</small>
            <strong>{{ systemValueText(ruleModal) }}</strong>
          </div>
          <div>
            <small>Giới hạn được phép</small>
            <strong>{{ ruleModal.limit_summary || 'Theo khung hệ thống' }}</strong>
          </div>
          <div>
            <small>Giá trị sân hiện tại</small>
            <strong>{{ venueValueText(ruleModal) }}</strong>
          </div>
        </section>

        <template v-if="isRefundRule(ruleModal)">
          <div class="form-grid">
            <label>
              Hủy trước giờ chơi tối thiểu
              <div class="input-unit">
                <input v-model.number="ruleForm.hours_before_start" type="number" min="1" required />
                <span>giờ</span>
              </div>
            </label>
            <label>
              Phần trăm hoàn tiền của sân
              <div class="input-unit">
                <input v-model.number="ruleForm.refund_percent" type="number" min="0" max="100" required />
                <span>%</span>
              </div>
            </label>
          </div>

          <div class="live-preview" :class="{ invalid: ruleValidationMessage }">
            <AppIcon :name="ruleValidationMessage ? 'alert' : 'circleCheck'" size="18" />
            <span>{{ ruleValidationMessage || refundPreview }}</span>
          </div>
        </template>

        <div v-else class="empty-state compact">
          <AppIcon name="lock" size="24" />
          <strong>Quy tắc này chưa mở form cấu hình riêng cho chủ sân</strong>
          <span>Chủ sân chỉ xem được khung hệ thống. Khi nghiệp vụ cần, form cấu hình sẽ được bổ sung riêng.</span>
        </div>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeRule">Hủy</button>
          <button v-if="isRefundRule(ruleModal)" class="btn primary" type="submit" :disabled="saving || !!ruleValidationMessage">
            <AppIcon name="check" size="16" />
            Lưu chính sách sân
          </button>
        </footer>
      </form>
    </div>

    <div v-if="noticeModal" class="modal-backdrop" @click.self="closeNotice">
      <form class="modal" @submit.prevent="saveNotice">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Quy định khách đọc</p>
            <h3>{{ noticeForm.id ? 'Sửa quy định' : 'Thêm quy định hiển thị cho khách' }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeNotice">
            <AppIcon name="x" size="18" />
          </button>
        </header>

        <label>Tiêu đề
          <input v-model.trim="noticeForm.title" required placeholder="Ví dụ: Quy định gửi xe" />
        </label>
        <label>Nội dung
          <textarea v-model.trim="noticeForm.content" rows="6" required placeholder="Viết ngắn gọn để khách dễ đọc." />
        </label>
        <label>Trạng thái
          <select v-model="noticeForm.status">
            <option value="draft">Bản nháp</option>
            <option value="active">Hiển thị</option>
            <option value="inactive">Ẩn</option>
          </select>
        </label>

        <div class="notice-note">
          <AppIcon name="shield" size="17" />
          <span>Quy định này chỉ hiển thị cho khách đọc, không tạo rule tự động.</span>
        </div>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeNotice">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            <AppIcon name="check" size="16" />
            Lưu quy định
          </button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerPolicyService } from '../../services/ownerPolicyService.js';

export default {
  name: 'OwnerPolicies',
  components: { AppIcon },
  data() {
    return {
      tab: 'rules',
      loading: false,
      saving: false,
      error: '',
      success: '',
      venueCluster: null,
      systemPolicies: [],
      venueRules: [],
      customerNotices: [],
      ruleModal: null,
      noticeModal: false,
      ruleForm: { refund_percent: 80, hours_before_start: 24 },
      noticeForm: this.emptyNotice(),
    };
  },
  computed: {
    clusterTone() {
      return this.venueCluster?.status === 'active' ? 'active' : 'locked';
    },
    allRules() {
      return this.systemPolicies.flatMap((policy) => policy.rules || []);
    },
    configuredRuleCount() {
      return this.allRules.filter((rule) => !!rule.venue_rule).length;
    },
    rulesNeedConfigCount() {
      return this.allRules.filter((rule) => rule.can_override && !rule.venue_rule).length;
    },
    activeNoticeCount() {
      return this.customerNotices.filter((notice) => notice.status === 'active').length;
    },
    refundPreview() {
      return `Nếu khách hủy trước ${this.ruleForm.hours_before_start || 0} giờ, sân đề xuất hoàn ${this.ruleForm.refund_percent || 0}% số tiền đã thanh toán.`;
    },
    ruleValidationMessage() {
      if (!this.ruleModal || !this.isRefundRule(this.ruleModal)) return '';

      const minPercent = this.minimumRefundPercent(this.ruleModal);
      if (Number(this.ruleForm.hours_before_start) <= 0) {
        return 'Thời gian hủy trước giờ chơi phải lớn hơn 0.';
      }
      if (Number(this.ruleForm.refund_percent) < 0 || Number(this.ruleForm.refund_percent) > 100) {
        return 'Phần trăm hoàn tiền phải nằm trong khoảng 0 đến 100%.';
      }
      if (minPercent !== null && Number(this.ruleForm.refund_percent) < minPercent) {
        return `Không được cấu hình mức hoàn thấp hơn ${minPercent}% theo chính sách hệ thống.`;
      }

      return '';
    },
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.load);
    this.load();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.load);
  },
  methods: {
    emptyNotice() {
      return { id: null, title: '', content: '', status: 'active' };
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await ownerPolicyService.list();
        const data = response.data || {};
        this.venueCluster = data.venue_cluster || null;
        this.systemPolicies = data.system_policies || [];
        this.venueRules = data.venue_rules || [];
        this.customerNotices = data.customer_notices || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải chính sách sân.';
      } finally {
        this.loading = false;
      }
    },
    openRule(rule) {
      this.ruleModal = rule;
      this.ruleForm = {
        refund_percent: rule.venue_value?.refund_percent ?? rule.system_value?.refund_percent ?? 80,
        hours_before_start: rule.venue_value?.hours_before_start ?? rule.system_value?.hours_before_start ?? 24,
      };
    },
    closeRule() {
      this.ruleModal = null;
    },
    async saveRule() {
      if (!this.ruleModal || this.ruleValidationMessage) return;

      this.saving = true;
      this.error = '';
      try {
        const response = await ownerPolicyService.saveRule({
          base_policy_rule_id: this.ruleModal.id,
          refund_percent: this.ruleForm.refund_percent,
          hours_before_start: this.ruleForm.hours_before_start,
          status: 'active',
        });
        this.success = response.message;
        this.closeRule();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu chính sách sân.';
      } finally {
        this.saving = false;
      }
    },
    openNotice(notice = null) {
      this.noticeForm = notice ? {
        id: notice.id,
        title: notice.title,
        content: notice.content,
        status: notice.status,
      } : this.emptyNotice();
      this.noticeModal = true;
    },
    closeNotice() {
      this.noticeModal = false;
    },
    async saveNotice() {
      this.saving = true;
      this.error = '';
      try {
        const response = this.noticeForm.id
          ? await ownerPolicyService.updateNotice(this.noticeForm.id, this.noticeForm)
          : await ownerPolicyService.createNotice(this.noticeForm);
        this.success = response.message;
        this.closeNotice();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu quy định.';
      } finally {
        this.saving = false;
      }
    },
    isRefundRule(rule) {
      return rule?.rule_type === 'refund_percent_by_cancel_time';
    },
    systemValueText(rule) {
      const percent = rule.system_value?.refund_percent;
      const hours = rule.system_value?.hours_before_start;
      if (percent !== null && percent !== undefined) {
        return `Hoàn tối thiểu ${percent}% khi hủy trước ${hours || '?'} giờ.`;
      }
      return rule.business_summary || 'Theo khung hệ thống.';
    },
    venueValueText(rule) {
      const percent = rule.venue_value?.refund_percent;
      const hours = rule.venue_value?.hours_before_start;
      if (percent !== null && percent !== undefined) {
        return `Sân đặt ${percent}% khi hủy trước ${hours || '?'} giờ.`;
      }
      return 'Chưa cấu hình riêng.';
    },
    previewText(rule) {
      if (rule.venue_rule) {
        return 'Quy tắc sân đã có cấu hình riêng và vẫn nằm trong khung hệ thống.';
      }
      if (rule.can_override) {
        return 'Chưa cấu hình riêng. Sân đang dùng quy định mặc định của hệ thống.';
      }
      return 'Quy tắc này chỉ xem, chưa cho sân cấu hình riêng.';
    },
    ruleStatusLabel(rule) {
      if (!rule.can_override) return 'Chỉ xem';
      if (rule.venue_rule?.status_label) return rule.venue_rule.status_label;
      if (rule.venue_rule) return 'Đã cấu hình';
      return 'Cần cấu hình';
    },
    ruleTone(rule) {
      if (!rule.can_override) return 'muted';
      if (rule.venue_rule?.status === 'rejected') return 'danger';
      if (rule.venue_rule) return 'success';
      return 'warning';
    },
    ruleIcon(rule) {
      if (this.isRefundRule(rule)) return 'banknote';
      if (rule.action_code?.includes('booking')) return 'calendar';
      if (rule.action_code?.includes('venue')) return 'building';
      return 'sliders';
    },
    noticeTone(status) {
      return {
        active: 'success',
        inactive: 'danger',
        draft: 'muted',
      }[status] || 'muted';
    },
    minimumRefundPercent(rule) {
      const direct = rule.constraints?.find?.((constraint) => constraint.constraint_type === 'min_value' || constraint.min_value !== undefined);
      const value = direct?.min_value ?? direct?.constraint_value?.min_value ?? rule.system_value?.refund_percent;
      return value === null || value === undefined ? null : Number(value);
    },
    technicalText(rule) {
      return JSON.stringify({
        rule_code: rule.rule_code,
        rule_type: rule.rule_type,
        action_code: rule.action_code,
        system_value: rule.system_value,
        venue_value: rule.venue_value,
        constraints: rule.constraints,
      }, null, 2);
    },
  },
};
</script>

<style scoped>
.page { display: grid; gap: 16px; }
.hero-card {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: stretch;
  padding: 22px;
  border-radius: 18px;
  background:
    radial-gradient(circle at top right, rgba(34, 197, 94, .24), transparent 30%),
    linear-gradient(135deg, #07140d 0%, #0f172a 100%);
  color: #fff;
  border: 1px solid rgba(34, 197, 94, .18);
}
.hero-card h2 { margin: 0 0 8px; font-size: 28px; }
.hero-card p { margin: 0; max-width: 680px; color: #cbd5e1; }
.eyebrow { margin: 0 0 6px; color: #86efac; font-size: 12px; font-weight: 900; letter-spacing: .05em; text-transform: uppercase; }
.hero-cluster { min-width: 240px; display: flex; align-items: center; gap: 12px; align-self: center; padding: 14px; border-radius: 14px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); }
.hero-cluster.active { box-shadow: inset 4px 0 0 #22c55e; }
.hero-cluster.locked { box-shadow: inset 4px 0 0 #f59e0b; }
.hero-cluster span { display: block; color: #94a3b8; font-size: 12px; font-weight: 800; }
.hero-cluster strong { display: block; margin-top: 3px; }
.stat-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 12px; }
.stat-card { display: grid; gap: 8px; min-height: 112px; padding: 16px; border-radius: 16px; border: 1px solid #e2e8f0; background: #fff; align-content: center; }
.stat-card svg { color: #16a34a; }
.stat-card strong { font-size: 28px; color: #0f172a; line-height: 1; }
.stat-card span { color: #64748b; font-weight: 800; }
.stat-card.success { background: #f0fdf4; border-color: #bbf7d0; }
.stat-card.warning { background: #fffbeb; border-color: #fde68a; }
.stat-card.dark { background: #0f172a; border-color: #0f172a; }
.stat-card.dark strong, .stat-card.dark span, .stat-card.dark svg { color: #fff; }
.cluster-alert, .notice-note, .preview-box, .live-preview, .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 14px; font-weight: 800; }
.cluster-alert { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.notice-note { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
.alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.tabs { display: inline-flex; width: fit-content; gap: 6px; padding: 6px; border-radius: 14px; background: #f1f5f9; border: 1px solid #e2e8f0; }
.tabs button { border: 0; background: transparent; color: #475569; border-radius: 10px; padding: 10px 14px; display: inline-flex; align-items: center; gap: 8px; font-weight: 900; cursor: pointer; }
.tabs button.active { background: #16a34a; color: #fff; box-shadow: 0 10px 24px rgba(22, 163, 74, .22); }
.rules-section, .notice-section { display: grid; gap: 14px; }
.section-title { display: flex; justify-content: space-between; gap: 14px; align-items: center; }
.section-title h3 { margin: 0 0 4px; font-size: 20px; }
.section-title p { margin: 0; color: #64748b; }
.policy-card { border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; padding: 18px; display: grid; gap: 16px; }
.policy-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.policy-head h3 { margin: 3px 0 6px; }
.policy-head p { margin: 0; color: #64748b; }
.policy-type, .rule-count { color: #15803d; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.rule-count { border-radius: 999px; background: #dcfce7; padding: 7px 10px; white-space: nowrap; }
.rule-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; }
.rule-card { display: grid; gap: 14px; padding: 16px; border-radius: 16px; border: 1px solid #e2e8f0; background: #f8fafc; }
.rule-card.success { background: #f0fdf4; border-color: #bbf7d0; }
.rule-card.warning { background: #fffbeb; border-color: #fde68a; }
.rule-card.danger { background: #fef2f2; border-color: #fecaca; }
.rule-card.muted { opacity: .82; }
.rule-top { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: start; }
.rule-icon, .notice-icon { width: 36px; height: 36px; border-radius: 12px; display: grid; place-items: center; background: #052e16; color: #bbf7d0; }
.rule-top strong, .notice-card strong { color: #0f172a; }
.rule-top span:not(.status-pill) { display: block; margin-top: 3px; color: #64748b; font-size: 13px; }
.status-pill, .badge { display: inline-flex; width: fit-content; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.status-pill.success, .badge.success { background: #dcfce7; color: #166534; }
.status-pill.warning { background: #fef3c7; color: #92400e; }
.status-pill.danger, .badge.danger { background: #fee2e2; color: #991b1b; }
.status-pill.muted, .badge.muted { background: #e2e8f0; color: #475569; }
.rule-summary { margin: 0; color: #334155; line-height: 1.5; }
.metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.metric-grid div, .modal-summary div { min-height: 92px; border-radius: 14px; background: #fff; border: 1px solid #e2e8f0; padding: 12px; display: grid; align-content: start; gap: 6px; }
.metric-grid small, .modal-summary small { color: #64748b; font-weight: 900; text-transform: uppercase; font-size: 11px; }
.metric-grid strong, .modal-summary strong { color: #0f172a; font-size: 14px; line-height: 1.45; }
.preview-box { background: rgba(255,255,255,.72); border: 1px dashed #bbf7d0; color: #166534; }
.rule-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
details { color: #64748b; font-size: 13px; }
details summary { cursor: pointer; font-weight: 800; }
pre { max-height: 180px; overflow: auto; padding: 10px; border-radius: 10px; background: #0f172a; color: #e2e8f0; white-space: pre-wrap; }
.notice-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
.notice-card { display: grid; gap: 12px; padding: 16px; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; }
.notice-card header, .notice-card footer { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
.notice-card p { margin: 0; color: #334155; line-height: 1.5; }
.notice-card footer span { color: #64748b; font-size: 13px; }
.btn, .icon-btn { border: 0; border-radius: 12px; font-weight: 900; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
.btn { min-height: 40px; padding: 10px 14px; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #f1f5f9; color: #0f172a; }
.icon-btn { width: 38px; height: 38px; background: #f1f5f9; color: #0f172a; }
.btn:disabled { opacity: .55; cursor: not-allowed; }
.state, .empty-state { display: grid; place-items: center; text-align: center; gap: 8px; padding: 32px 18px; border: 1px dashed #cbd5e1; border-radius: 16px; background: #f8fafc; color: #64748b; }
.empty-state strong { color: #0f172a; }
.empty-state.compact { padding: 18px; }
.spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #16a34a; border-radius: 50%; animation: spin .8s linear infinite; }
.modal-backdrop { position: fixed; inset: 0; z-index: 600; display: grid; place-items: center; padding: 20px; background: rgba(15, 23, 42, .58); }
.modal { width: min(620px, calc(100vw - 32px)); max-height: calc(100vh - 40px); overflow: auto; display: grid; gap: 16px; padding: 20px; border-radius: 18px; background: #fff; box-shadow: 0 24px 80px rgba(15, 23, 42, .24); }
.modal.wide { width: min(820px, calc(100vw - 32px)); }
.modal-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.modal-head h3 { margin: 0 0 4px; }
.modal-head span { color: #64748b; font-weight: 700; }
.modal-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label { display: grid; gap: 7px; color: #334155; font-weight: 900; }
input, select, textarea { width: 100%; border: 1px solid #dbe3ef; border-radius: 12px; padding: 11px 12px; font: inherit; color: #0f172a; background: #fff; }
.input-unit { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 8px; }
.input-unit span { color: #64748b; font-weight: 900; }
.live-preview { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.live-preview.invalid { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 900px) {
  .hero-card, .section-title, .policy-head, .rule-actions { flex-direction: column; align-items: stretch; }
  .stat-grid, .metric-grid, .modal-summary, .form-grid { grid-template-columns: 1fr; }
  .hero-cluster { min-width: 0; }
  .tabs { width: 100%; display: grid; }
}
</style>

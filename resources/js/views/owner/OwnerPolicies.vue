<template>
  <section class="page">
    <header class="page-head">
      <div>
        <h2>Cấu hình chính sách sân</h2>
        <p>Thiết lập quy tắc trong khung hệ thống và nội quy hiển thị cho khách.</p>
      </div>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <nav class="tabs">
      <button :class="{ active: tab === 'rules' }" type="button" @click="tab = 'rules'">Quy tắc áp dụng hệ thống</button>
      <button :class="{ active: tab === 'notices' }" type="button" @click="tab = 'notices'">Quy định hiển thị cho khách</button>
    </nav>

    <section v-if="tab === 'rules'" class="grid">
      <article v-for="policy in systemPolicies" :key="policy.id" class="card">
        <h3>{{ policy.title }}</h3>
        <p>{{ policy.business_summary }}</p>
        <div v-for="rule in policy.rules" :key="rule.id" class="rule-row">
          <div>
            <strong>{{ rule.rule_label }}</strong>
            <span>{{ rule.business_summary }}</span>
          </div>
          <ActionIconButton icon="settings" label="Cấu hình quy tắc" @click="openRule(rule)" />
        </div>
      </article>
      <div v-if="!loading && systemPolicies.length === 0" class="state">
        Chưa có chính sách hệ thống nào cho phép sân cấu hình riêng.
      </div>
    </section>

    <section v-if="tab === 'notices'" class="panel">
      <div class="section-head">
        <div>
          <h3>Quy định hiển thị cho khách</h3>
          <p>Nội dung này chỉ để khách đọc, không tác động tự động đến booking/refund/payment.</p>
        </div>
        <button class="btn primary" type="button" @click="openNotice()">
          <AppIcon name="plus" size="16" />
          <span>Thêm quy định</span>
        </button>
      </div>
      <div v-if="customerNotices.length === 0" class="state">Chưa có nội quy hiển thị cho khách.</div>
      <article v-for="notice in customerNotices" :key="notice.id" class="notice-card">
        <div>
          <strong>{{ notice.title }}</strong>
          <p>{{ notice.content }}</p>
        </div>
        <span class="badge" :class="notice.status">{{ notice.status_label }}</span>
        <ActionIconButton icon="pencil" label="Sửa quy định" @click="openNotice(notice)" />
      </article>
    </section>

    <div v-if="ruleModal" class="modal-backdrop" @click.self="ruleModal = null">
      <form class="modal" @submit.prevent="saveRule">
        <h3>{{ ruleModal.rule_label }}</h3>
        <p class="muted">{{ ruleModal.business_summary }}</p>
        <template v-if="ruleModal.rule_type === 'refund_percent_by_cancel_time'">
          <label>Hủy trước giờ chơi tối thiểu (giờ)
            <input v-model.number="ruleForm.hours_before_start" type="number" min="1" required />
          </label>
          <label>Phần trăm hoàn tiền của sân (%)
            <input v-model.number="ruleForm.refund_percent" type="number" min="0" max="100" required />
          </label>
          <p class="preview">Nếu khách hủy trước {{ ruleForm.hours_before_start }} giờ, sân đề xuất hoàn {{ ruleForm.refund_percent }}%.</p>
        </template>
        <p v-else class="state">Quy tắc này hiện chỉ xem được. Các trường cấu hình chi tiết sẽ bổ sung khi nghiệp vụ cần.</p>
        <footer>
          <button class="btn secondary" type="button" @click="ruleModal = null">Hủy</button>
          <button class="btn primary" type="submit">Lưu chính sách sân</button>
        </footer>
      </form>
    </div>

    <div v-if="noticeModal" class="modal-backdrop" @click.self="noticeModal = false">
      <form class="modal" @submit.prevent="saveNotice">
        <h3>{{ noticeForm.id ? 'Sửa quy định' : 'Thêm quy định hiển thị cho khách' }}</h3>
        <label>Tiêu đề<input v-model.trim="noticeForm.title" required /></label>
        <label>Nội dung<textarea v-model.trim="noticeForm.content" rows="6" required></textarea></label>
        <label>Trạng thái
          <select v-model="noticeForm.status">
            <option value="draft">Bản nháp</option>
            <option value="active">Hiển thị</option>
            <option value="inactive">Ẩn</option>
          </select>
        </label>
        <footer>
          <button class="btn secondary" type="button" @click="noticeModal = false">Hủy</button>
          <button class="btn primary" type="submit">Lưu</button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import { ownerPolicyService } from '../../services/ownerPolicyService.js';

export default {
  name: 'OwnerPolicies',
  components: { ActionIconButton, AppIcon },
  data() {
    return {
      tab: 'rules',
      loading: false,
      error: '',
      success: '',
      systemPolicies: [],
      venueRules: [],
      customerNotices: [],
      ruleModal: null,
      noticeModal: false,
      ruleForm: { refund_percent: 80, hours_before_start: 24 },
      noticeForm: this.emptyNotice(),
    };
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
      try {
        const response = await ownerPolicyService.list();
        const data = response.data || {};
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
      const existing = this.venueRules.find((item) => item.base_policy_rule_id === rule.id);
      this.ruleModal = rule;
      this.ruleForm = {
        refund_percent: existing?.result_json?.refund_percent || rule.system_value?.refund_percent || 80,
        hours_before_start: existing?.condition_json?.hours_before_start?.gte || 24,
      };
    },
    async saveRule() {
      try {
        const response = await ownerPolicyService.saveRule({
          base_policy_rule_id: this.ruleModal.id,
          refund_percent: this.ruleForm.refund_percent,
          hours_before_start: this.ruleForm.hours_before_start,
          status: 'active',
        });
        this.success = response.message;
        this.ruleModal = null;
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu chính sách sân.';
      }
    },
    openNotice(notice = null) {
      this.noticeForm = notice ? { ...notice } : this.emptyNotice();
      this.noticeModal = true;
    },
    async saveNotice() {
      try {
        const response = this.noticeForm.id
          ? await ownerPolicyService.updateNotice(this.noticeForm.id, this.noticeForm)
          : await ownerPolicyService.createNotice(this.noticeForm);
        this.success = response.message;
        this.noticeModal = false;
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu quy định.';
      }
    },
  },
};
</script>

<style scoped>
.page{display:grid;gap:16px}.page-head,.section-head{display:flex;justify-content:space-between;gap:16px}.page-head h2,.section-head h3{margin:0 0 6px}.page-head p,.section-head p,.muted{margin:0;color:#64748b}.tabs{display:flex;gap:8px}.tabs button{border:1px solid #dbe3ef;background:#fff;border-radius:8px;padding:10px 14px;font-weight:800}.tabs .active{background:#dcfce7;border-color:#22c55e;color:#166534}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.card,.panel,.modal,.notice-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px}.card,.panel,.modal{padding:18px}.card h3{margin:0 0 8px}.card p{color:#64748b}.rule-row,.notice-card{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;margin-top:10px}.rule-row{background:#f8fafc;border-radius:10px}.rule-row span,.notice-card p{display:block;color:#64748b;margin-top:4px}.btn,.mini-btn{border:0;border-radius:8px;font-weight:800;cursor:pointer}.btn{padding:10px 14px}.mini-btn{padding:8px 10px;background:#f1f5f9}.primary{background:#16a34a;color:#fff}.secondary{background:#f1f5f9;color:#0f172a}.state{padding:18px;color:#64748b;background:#f8fafc;border-radius:10px}.preview{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px;border-radius:10px}.badge{border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#f1f5f9}.badge.active{background:#dcfce7;color:#166534}.badge.inactive{background:#fee2e2;color:#b91c1c}.alert{padding:12px;border-radius:10px;font-weight:700}.error{background:#fee2e2;color:#b91c1c}.success{background:#dcfce7;color:#166534}.modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.56);display:grid;place-items:center;z-index:500;padding:20px}.modal{width:min(620px,calc(100vw - 32px));display:grid;gap:14px}label{display:grid;gap:6px;font-weight:800}input,select,textarea{border:1px solid #dbe3ef;border-radius:8px;padding:10px;font:inherit}footer{display:flex;justify-content:flex-end;gap:10px}@media(max-width:900px){.grid{grid-template-columns:1fr}.page-head,.section-head,.notice-card{flex-direction:column}}
</style>

import { api } from './api';

export default {
    getProfiles(params) {
        const query = new URLSearchParams(params).toString();
        return api(`/api/admin/partner-profiles?${query}`);
    },
    getProfile(id) {
        return api(`/api/admin/partner-profiles/${id}`);
    },
    approveProfile(id) {
        return api(`/api/admin/partner-profiles/${id}/approve`, { method: 'POST' });
    },
    rejectProfile(id, reason) {
        return api(`/api/admin/partner-profiles/${id}/reject`, {
            method: 'POST',
            body: JSON.stringify({ reason })
        });
    },
    terminateProfile(id, type, reason) {
        return api(`/api/admin/partner-profiles/${id}/terminate`, {
            method: 'POST',
            body: JSON.stringify({ type, reason })
        });
    },
    refundWallet(id) {
        return api(`/api/admin/partner-profiles/${id}/refund`, { method: 'POST' });
    },
    generateContract(profileId, templateId) {
        return api(`/api/admin/partner-profiles/${profileId}/contracts`, {
            method: 'POST',
            body: JSON.stringify({ template_id: templateId })
        });
    },
    sendContractEmail(contractId) {
        return api(`/api/admin/contracts/${contractId}/send-email`, { method: 'POST' });
    },
    approveSignature(contractId) {
        return api(`/api/admin/contracts/${contractId}/approve-signature`, { method: 'POST' });
    },
};

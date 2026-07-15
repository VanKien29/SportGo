import { api, apiFormData } from "./api.js";

const BASE_URL = "/api/admin/system-profile";

export async function fetchSystemProfile() {
    const response = await api(BASE_URL);
    return response.data || response;
}

export async function saveSystemProfile(formData) {
    const response = await apiFormData(BASE_URL, formData);
    return response;
}

import { reactive } from "vue";

const DEFAULT_PROFILE = {
    system_name: "SportGo",
    company_name: "Công ty SportGo",
    company_short_name: "SportGo",
    representative_name: "",
    representative_title: "",
    company_address: "",
    tax_code: "",
    business_code: "",
    business_license_number: "",
    support_email: "",
    support_phone: "",
    website_url: "",
    logo_url: "",
    favicon_url: "",
};

export const systemProfileState = reactive({
    loaded: false,
    loading: false,
    profile: { ...DEFAULT_PROFILE },
});

export function resolveSystemAsset(url) {
    if (!url) return "";
    if (/^(https?:)?\/\//i.test(url) || url.startsWith("data:") || url.startsWith("blob:")) {
        return url;
    }
    return url.startsWith("/") ? url : `/${url}`;
}

export function systemName() {
    return systemProfileState.profile.system_name || DEFAULT_PROFILE.system_name;
}

export function systemShortName() {
    return systemProfileState.profile.company_short_name || systemName();
}

export function systemInitials() {
    return systemShortName()
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part[0])
        .join("")
        .slice(0, 2)
        .toUpperCase() || "SG";
}

function upsertIconLink(rel, href) {
    if (!href || typeof document === "undefined") return;

    let link = document.querySelector(`link[rel="${rel}"]`);
    if (!link) {
        link = document.createElement("link");
        link.rel = rel;
        document.head.appendChild(link);
    }

    link.href = href;
}

export function applySystemProfile(profile = {}) {
    Object.assign(systemProfileState.profile, DEFAULT_PROFILE, profile);
    systemProfileState.loaded = true;

    if (typeof document === "undefined") return;

    const name = systemName();
    const favicon = resolveSystemAsset(systemProfileState.profile.favicon_url || systemProfileState.profile.logo_url);

    document.title = `${name} - Đặt sân thể thao online`;
    upsertIconLink("icon", favicon);
    upsertIconLink("shortcut icon", favicon);
    upsertIconLink("apple-touch-icon", favicon);

    const description = document.querySelector('meta[name="description"]');
    if (description) {
        description.setAttribute("content", `${name} - Nền tảng đặt sân thể thao online`);
    }
}

export async function loadSystemProfile({ force = false } = {}) {
    if (systemProfileState.loading || (systemProfileState.loaded && !force)) {
        return systemProfileState.profile;
    }

    systemProfileState.loading = true;

    try {
        const response = await fetch("/api/system-profile", {
            headers: { Accept: "application/json" },
        });
        const payload = await response.json().catch(() => ({}));

        if (response.ok) {
            applySystemProfile(payload.data || {});
        } else {
            applySystemProfile();
        }
    } catch {
        applySystemProfile();
    } finally {
        systemProfileState.loading = false;
    }

    return systemProfileState.profile;
}

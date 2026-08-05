const ADMIN_ROUTE_REQUIREMENTS = {
  'admin-dashboard': { all: ['dashboard.view'] },
  'admin-profile': { all: ['profile.view'] },
  'admin-users': { all: ['user.view'] },
  'admin-user-detail': { all: ['user.view'] },
  'admin-staffs': { all: ['staff.view'] },
  'admin-staff-detail': { all: ['staff.view'] },
  'admin-vouchers': { all: ['voucher.view'] },
  'admin-voucher-detail': { all: ['voucher.view'] },
  'admin-membership-packages': { all: ['membership.view'] },
  'admin-payments': { all: ['payment.view'] },
  'admin-finance-operations': { all: ['refund.view', 'withdrawal.view', 'wallet.view'] },
  'admin-partner-applications': { all: ['partner.view'] },
  'admin-partner-application-detail': { all: ['partner.view'] },
  'admin-partner-application-document': { all: ['partner.view'] },
  'admin-partner-detail': { all: ['partner.view'] },
  'admin-banners': { all: ['banner.view'] },
  'admin-moderation': { all: ['moderation.view', 'content.view'] },
  'admin-system-posts': { all: ['system_post.view'] },
  'admin-policies': { all: ['policy.view'] },
  'admin-platform-fee-policies': { all: ['policy.view'] },
  'admin-policy-detail': { all: ['policy.view'] },
  'admin-reports-complaints': { all: ['report.view', 'complaint.view'] },
  'admin-roles': { all: ['role.view'] },
  'admin-role-detail': { all: ['role.view'] },
  'admin-court-types': { all: ['court_type.view'] },
  'admin-amenities': { all: ['amenity.view'] },
  'admin-service-categories': { all: ['service_category.view'] },
  'admin-venue-clusters': { all: ['venue.view'] },
  'admin-venue-cluster-detail': { all: ['venue.view'] },
  'admin-platform-fee-tiers': { all: ['platform_fee.view'] },
  'admin-platform-fee-ledgers': { all: ['platform_fee.view'] },
  'admin-platform-fee-ledger-detail': { all: ['platform_fee.view'] },
  'admin-venue-platform-fees': { all: ['platform_fee.view'] },
  'admin-platform-fee-settings': { all: ['platform_fee.view'] },
  'admin-system-profile': { all: ['system_profile.view'] },
  'admin-settings': { all: ['ui_settings.view'] },
  'admin-post-detail': { any: ['moderation.view', 'content.view', 'user.view'] },
  'admin-chat': { all: ['dashboard.view'] },
};

const STAFF_ROUTE_MENU_KEYS = {
  'staff-dashboard': 'dashboard',
  'staff-schedules': 'schedules',
  'staff-bookings': 'bookings',
  'staff-counter-booking': 'counter_booking',
  'staff-vouchers': 'vouchers',
  'staff-chat': 'chat',
  'staff-settings': 'settings',
};

export function isSuperAdmin(auth) {
  if (!auth) return false;
  if (auth.role_group === 'admin') return true;
  const roles = auth.roles || [];
  return roles.includes('super_admin') || roles.includes('admin');
}

export function hasAllAdminPermissions(auth, permissionCodes = []) {
  if (isSuperAdmin(auth)) return true;
  const granted = new Set(auth?.permissions || []);
  return permissionCodes.every((code) => granted.has(code));
}

export function hasAnyAdminPermission(auth, permissionCodes = []) {
  if (isSuperAdmin(auth)) return true;
  const granted = new Set(auth?.permissions || []);
  return permissionCodes.some((code) => granted.has(code));
}

export function canAccessAdminRoute(routeName, auth) {
  const requirement = ADMIN_ROUTE_REQUIREMENTS[String(routeName || '')];
  if (!requirement) return false;
  if (requirement.any) return hasAnyAdminPermission(auth, requirement.any);
  return hasAllAdminPermissions(auth, requirement.all || []);
}

export function firstAccessibleAdminRoute(auth, navigationSections = []) {
  const item = navigationSections
    .flatMap((section) => section.items || [])
    .find((candidate) => canAccessAdminRoute(candidate.activeNames?.[0], auth));

  if (item?.to) return item.to;
  return hasAllAdminPermissions(auth, ['profile.view']) ? '/admin/profile' : '/admin/login';
}

export function staffMenuKeys(auth, clusterId) {
  if (!clusterId) return [];
  return auth?.venue_staff_permissions?.[String(clusterId)] || [];
}

export function canAccessStaffMenu(auth, clusterId, menuKey) {
  if (!menuKey) return true;
  return staffMenuKeys(auth, clusterId).includes(menuKey);
}

export function canAccessStaffRoute(routeName, auth, clusterId) {
  const menuKey = STAFF_ROUTE_MENU_KEYS[String(routeName || '')];
  return menuKey ? canAccessStaffMenu(auth, clusterId, menuKey) : routeName === 'staff-profile';
}

export function firstAccessibleStaffRoute(auth, clusterId, navigationSections = []) {
  const item = navigationSections
    .flatMap((section) => section.items || [])
    .find((candidate) => canAccessStaffMenu(auth, clusterId, candidate.menuKey));

  return item?.to || '/staff/profile';
}

export function adminRouteRequirement(routeName) {
  return ADMIN_ROUTE_REQUIREMENTS[String(routeName || '')] || null;
}

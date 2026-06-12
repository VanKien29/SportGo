export const ownerNavigationSections = [
  {
    label: 'Tổng quan',
    items: [
      {
        label: 'Bảng điều hành',
        icon: 'dashboard',
        to: '/owner/dashboard',
        activeNames: ['owner-dashboard'],
      },
    ],
  },
  {
    label: 'Vận hành sân',
    items: [
      {
        label: 'Cụm sân & Sân con',
        icon: 'building',
        to: '/owner/venue-clusters',
        activeNames: ['owner-venue-clusters', 'owner-venue-courts'],
      },
      {
        label: 'Cấu hình giá',
        icon: 'settings',
        to: '/owner/pricing',
        activeNames: ['owner-pricing'],
      },
    ],
  },
  {
    label: 'Kinh doanh',
    items: [
      {
        label: 'Voucher sân',
        icon: 'tag',
        to: '/owner/vouchers',
        activeNames: ['owner-vouchers'],
      },
      {
        label: 'Chính sách sân',
        icon: 'fileText',
        to: '/owner/policies',
        activeNames: ['owner-policies'],
      },
    ],
  },
  {
    label: 'Nhân sự',
    items: [
      {
        label: 'Nhân viên sân',
        icon: 'users',
        to: '/owner/staff',
        activeNames: ['owner-staff'],
      },
    ],
  },
];


export const ownerRouteTitles = {
  'owner-dashboard': 'Bảng điều hành',
  'owner-profile': 'Thông tin cá nhân',
  'owner-venue-clusters': 'Quản lý cụm sân',
  'owner-venue-courts': 'Quản lý sân con',
  'owner-pricing': 'Cấu hình giá',
  'owner-staff': 'Nhân viên sân',
  'owner-vouchers': 'Voucher của sân',
  'owner-policies': 'Chính sách sân',
};

export const ownerRouteSectionLabels = {
  'owner-profile': 'Tài khoản',
};

export function findOwnerNavigationSection(routeName) {
  return ownerNavigationSections.find((section) =>
    section.items.some((item) => item.activeNames.includes(routeName)),
  );
}

export function getOwnerRouteSectionLabel(routeName) {
  return findOwnerNavigationSection(routeName)?.label || ownerRouteSectionLabels[routeName] || 'Tổng quan';
}

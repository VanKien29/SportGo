export const adminNavigationSections = [
  {
    label: 'Vận hành sân',
    items: [
      {
        label: 'Cụm sân',
        icon: 'building2',
        to: '/admin/venue-clusters',
        activeNames: [
          'admin-venue-clusters',
          'admin-venue-cluster-detail',
          'admin-venue-platform-fees',
        ],
        permissionCodes: ['venue.view'],
      },
      {
        label: 'Phí nền tảng',
        icon: 'layers3',
        to: '/admin/platform-fee-tiers',
        activeNames: [
          'admin-platform-fee-tiers',
          'admin-platform-fee-ledgers',
          'admin-platform-fee-ledger-detail',
          'admin-platform-fee-policies',
        ],
        permissionCodes: ['platform_fee.view'],
      },
      {
        label: 'Hồ sơ đối tác',
        icon: 'fileCheck',
        to: '/admin/partner-applications',
        activeNames: [
          'admin-partner-applications',
          'admin-partner-application-detail',
          'admin-partner-application-document',
          'admin-partner-detail',
        ],
        permissionCodes: ['partner.view'],
      },
      {
        label: 'Loại sân',
        icon: 'grid2x2',
        to: '/admin/court-types',
        activeNames: ['admin-court-types'],
        permissionCodes: ['court_type.view'],
      },
      {
        label: 'Tiện ích',
        icon: 'accessibility',
        to: '/admin/amenities',
        activeNames: ['admin-amenities'],
        permissionCodes: ['amenity.view'],
      },
    ],
  },
  {
    label: 'Người dùng & quyền',
    items: [
      {
        label: 'Nhân sự',
        icon: 'userRoundCog',
        to: '/admin/staffs',
        activeNames: ['admin-staffs', 'admin-staff-detail'],
        permissionCodes: ['staff.view'],
      },
      {
        label: 'Tài khoản',
        icon: 'userRound',
        to: '/admin/users',
        activeNames: ['admin-users', 'admin-user-detail'],
        permissionCodes: ['user.view'],
      },
      {
        label: 'Nhóm quyền',
        icon: 'shieldCheck',
        to: '/admin/roles',
        activeNames: ['admin-roles', 'admin-role-detail'],
        permissionCodes: ['role.view'],
      },
    ],
  },
  {
    label: 'Tài chính',
    items: [
      {
        label: 'Thanh toán booking',
        icon: 'receiptText',
        to: '/admin/payments',
        activeNames: ['admin-payments'],
        permissionCodes: ['payment.view'],
      },
      {
        label: 'Hoàn tiền & rút tiền',
        icon: 'walletCards',
        to: '/admin/finance-operations',
        activeNames: ['admin-finance-operations'],
        permissionCodes: ['refund.view', 'withdrawal.view', 'wallet.view'],
      },
      {
        label: 'Voucher hệ thống',
        icon: 'ticket',
        to: '/admin/vouchers',
        activeNames: ['admin-vouchers', 'admin-voucher-detail'],
        permissionCodes: ['voucher.view'],
      },
      {
        label: 'Gói VIP hệ thống',
        icon: 'crown',
        to: '/admin/membership-packages',
        activeNames: ['admin-membership-packages'],
        permissionCodes: ['membership.view'],
      },
    ],
  },
  {
    label: 'Nội dung & cấu hình',
    items: [
      {
        label: 'Tin tức hệ thống',
        icon: 'newspaper',
        to: '/admin/system-posts',
        activeNames: ['admin-system-posts'],
        permissionCodes: ['system_post.view'],
      },
      {
        label: 'Chính sách',
        icon: 'fileSearch',
        to: '/admin/policies',
        activeNames: ['admin-policies', 'admin-policy-detail'],
        permissionCodes: ['policy.view'],
      },
      {
        label: 'Danh mục dịch vụ',
        icon: 'shopping-bag',
        to: '/admin/service-categories',
        activeNames: ['admin-service-categories'],
        permissionCodes: ['service_category.view'],
      },
    ],
  },
  {
    label: 'Kiểm duyệt & hỗ trợ',
    items: [
      {
        label: 'Kiểm duyệt bài viết',
        icon: 'shieldAlert',
        to: '/admin/moderation',
        activeNames: ['admin-moderation'],
        permissionCodes: ['moderation.view', 'content.view'],
      },
      {
        label: 'Báo cáo & Khiếu nại',
        icon: 'messageWarning',
        to: '/admin/reports-complaints',
        activeNames: ['admin-reports-complaints', 'admin-reports', 'admin-complaints'],
        permissionCodes: ['report.view', 'complaint.view'],
      },
    ],
  },
];

export const adminRouteTitles = {
  'admin-dashboard': 'Bảng điều hành',
  'admin-profile': 'Thông tin cá nhân',
  'admin-staffs': 'Quản lý nhân sự',
  'admin-users': 'Quản lý tài khoản',
  'admin-user-detail': 'Chi tiết tài khoản',
  'admin-payments': 'Theo dõi thanh toán booking',
  'admin-finance-operations': 'Hoàn tiền và rút tiền',
  'admin-vouchers': 'Voucher hệ thống',
  'admin-membership-packages': 'Gói VIP hệ thống',
  'admin-partner-applications': 'Quản lý hồ sơ đối tác',
  'admin-banners': 'Quản lý banner',
  'admin-moderation': 'Kiểm duyệt bài viết',
  'admin-venue-posts': 'Kiểm duyệt bài viết',
  'admin-roles': 'Quản lý nhóm quyền',
  'admin-role-detail': 'Chi tiết nhóm quyền',
  'admin-system-posts': 'Tin tức hệ thống',
  'admin-policies': 'Quản lý chính sách',
  'admin-platform-fee-policies': 'Chính sách phí nền tảng',
  'admin-policy-detail': 'Chi tiết chính sách',
  'admin-reports-complaints': 'Báo cáo & Khiếu nại',
  'admin-court-types': 'Quản lý loại sân',
  'admin-amenities': 'Quản lý tiện ích',
  'admin-service-categories': 'Quản lý danh mục dịch vụ',
  'admin-venue-clusters': 'Quản lý cụm sân',
  'admin-venue-cluster-detail': 'Chi tiết cụm sân',
  'admin-platform-fee-tiers': 'Cấu hình bậc phí nền tảng',
  'admin-platform-fee-ledgers': 'Quản lý phí duy trì hệ thống',
  'admin-platform-fee-ledger-detail': 'Chi tiết kỳ phí duy trì',
  'admin-venue-platform-fees': 'Phí duy trì cụm sân',
  'admin-platform-fee-settings': 'Cài đặt nhắc phí',
  'admin-system-profile': 'Thông tin hệ thống',
  'admin-settings': 'Cài đặt hệ thống',
};

export function findAdminNavigationSection(routeName) {
  return adminNavigationSections.find((section) =>
    section.items.some((item) => item.activeNames.includes(routeName)),
  );
}

import { paths } from 'src/routes/paths';

import { CONFIG } from 'src/config-global';

import { SvgColor } from 'src/components/svg-color';

// ----------------------------------------------------------------------

const icon = (name) => <SvgColor src={`${CONFIG.site.basePath}/assets/icons/navbar/${name}.svg`} />;

const ICONS = {
  job: icon('ic-job'),
  blog: icon('ic-blog'),
  chat: icon('ic-chat'),
  mail: icon('ic-mail'),
  user: icon('ic-user'),
  file: icon('ic-file'),
  lock: icon('ic-lock'),
  tour: icon('ic-tour'),
  order: icon('ic-order'),
  label: icon('ic-label'),
  blank: icon('ic-blank'),
  kanban: icon('ic-kanban'),
  folder: icon('ic-folder'),
  course: icon('ic-course'),
  banking: icon('ic-banking'),
  booking: icon('ic-booking'),
  invoice: icon('ic-invoice'),
  product: icon('ic-product'),
  calendar: icon('ic-calendar'),
  disabled: icon('ic-disabled'),
  external: icon('ic-external'),
  menuItem: icon('ic-menu-item'),
  ecommerce: icon('ic-ecommerce'),
  analytics: icon('ic-analytics'),
  dashboard: icon('ic-dashboard'),
  parameter: icon('ic-parameter'),
};

// ----------------------------------------------------------------------

export const navData = [
  {
    subheader: 'OTT platform',
    items: [
      { title: 'Overview', path: paths.dashboard.root, icon: ICONS.dashboard },
      {
        title: 'Content approval',
        path: '/dashboard/content/review',
        icon: ICONS.blog,
        permissions: ['content.submit', 'content.review', 'content.approve', 'content.publish'],
      },
      {
        title: 'Content licensing',
        path: '/dashboard/content/licensing',
        icon: ICONS.lock,
        permission: 'content.view',
      },
      {
        title: 'Media library',
        path: '/dashboard/media',
        icon: ICONS.blog,
        permission: 'media.view',
      },
      {
        title: 'Subscriptions',
        path: '/dashboard/subscriptions',
        icon: ICONS.invoice,
        permission: 'subscriptions.view',
      },
      {
        title: 'Finance',
        path: '/dashboard/finance',
        icon: ICONS.invoice,
        permission: 'finance.payments.view',
      },
      {
        title: 'Marketing',
        path: '/dashboard/marketing',
        icon: ICONS.blog,
        permission: 'marketing.campaigns.manage',
      },
      {
        title: 'Customer support',
        path: '/dashboard/support',
        icon: ICONS.user,
        permissions: ['support.accounts.view', 'support.tickets.manage'],
      },
      {
        title: 'Analytics',
        path: '/dashboard/analytics',
        icon: ICONS.analytics,
        permissions: ['analytics.audience.view', 'analytics.subscriptions.view', 'analytics.content.view', 'analytics.revenue.view', 'analytics.playback.view'],
      },
      {
        title: 'Operations & reports',
        path: '/dashboard/operations',
        icon: ICONS.analytics,
        permissions: ['operations.health.view', 'operations.logs.view', 'operations.jobs.view', 'operations.jobs.retry', 'operations.incidents.manage', 'analytics.playback.view'],
      },
      {
        title: 'Roles',
        path: '/dashboard/access/roles',
        icon: ICONS.lock,
        permission: 'admin.roles.view',
      },
      {
        title: 'Staff',
        path: '/dashboard/access/staff',
        icon: ICONS.user,
        permission: 'admin.staff.view',
      },
      {
        title: 'Permissions',
        path: '/dashboard/access/permissions',
        icon: ICONS.parameter,
        permission: 'admin.permissions.view',
      },
      {
        title: 'Audit log',
        path: '/dashboard/access/audit',
        icon: ICONS.file,
        permission: 'audit.view',
      },
    ],
  },
];

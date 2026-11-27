import { Iconify } from 'src/components/iconify';

export const _account = [
  {
    label: 'Dashboard',
    href: '/dashboard',
    icon: <Iconify icon="solar:home-angle-bold-duotone" />,
  },
  {
    label: 'Subscriptions',
    href: '/dashboard/subscriptions',
    permission: 'subscriptions.view',
    icon: <Iconify icon="solar:bill-list-bold-duotone" />,
  },
  {
    label: 'Staff',
    href: '/dashboard/access/staff',
    permission: 'admin.staff.view',
    icon: <Iconify icon="solar:users-group-rounded-bold-duotone" />,
  },
  {
    label: 'Roles',
    href: '/dashboard/access/roles',
    permission: 'admin.roles.view',
    icon: <Iconify icon="solar:shield-keyhole-bold-duotone" />,
  },
  {
    label: 'Audit log',
    href: '/dashboard/access/audit',
    permission: 'audit.view',
    icon: <Iconify icon="solar:document-text-bold-duotone" />,
  },
];

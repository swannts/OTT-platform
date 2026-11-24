export function hasPermission(user, permission) {
  return Array.isArray(user?.permissions) && user.permissions.includes(permission);
}

export function hasAnyPermission(user, permissions = []) {
  return permissions.some((permission) => hasPermission(user, permission));
}

export function filterNavigationByPermissions(data, user) {
  return data
    .map((section) => {
      const items = (section.items || []).filter(
        (item) =>
          (!item.permission && !item.permissions) ||
          (item.permission && hasPermission(user, item.permission)) ||
          (item.permissions && hasAnyPermission(user, item.permissions))
      );
      return { ...section, items };
    })
    .filter((section) => section.items.length > 0);
}

export function requiredPermissionForPath(pathname) {
  const routePermissions = {
    '/dashboard/access/roles': 'admin.roles.view',
    '/dashboard/access/permissions': 'admin.permissions.view',
    '/dashboard/access/staff': 'admin.staff.view',
    '/dashboard/access/audit': 'audit.view',
    '/dashboard/media': 'media.view',
    '/dashboard/subscriptions': 'subscriptions.view',
    '/dashboard/finance': 'finance.payments.view',
    '/dashboard/marketing': 'marketing.campaigns.manage',
    '/dashboard/support': 'support.tickets.manage',
  };
  return routePermissions[pathname] || null;
}

export function requiredPermissionsForPath(pathname) {
  if (pathname === '/dashboard/content/review') {
    return ['content.submit', 'content.review', 'content.approve', 'content.publish'];
  }
  if (pathname === '/dashboard/operations') {
    return ['media.view', 'subscriptions.view', 'finance.payments.view', 'marketing.campaigns.manage', 'analytics.audience.view', 'operations.health.view'];
  }
  if (pathname === '/dashboard/support') {
    return ['support.accounts.view', 'support.tickets.manage'];
  }
  const required = requiredPermissionForPath(pathname);
  return required ? [required] : [];
}

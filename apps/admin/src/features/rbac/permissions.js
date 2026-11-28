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
  const normalizedPath = pathname.replace(/\/+$/, '') || '/';
  const routePermissions = {
    '/dashboard/access/roles': 'admin.roles.view',
    '/dashboard/access/permissions': 'admin.permissions.view',
    '/dashboard/access/staff': 'admin.staff.view',
    '/dashboard/access/audit': 'audit.view',
    '/dashboard/content/licensing': 'content.view',
    '/dashboard/videos': 'content.view',
    '/dashboard/media': 'media.view',
    '/dashboard/subscriptions': 'subscriptions.view',
    '/dashboard/finance': 'finance.payments.view',
    '/dashboard/marketing': 'marketing.campaigns.manage',
    '/dashboard/support': 'support.tickets.manage',
  };
  return routePermissions[normalizedPath] || null;
}

export function requiredPermissionsForPath(pathname) {
  const normalizedPath = pathname.replace(/\/+$/, '') || '/';
  if (/^\/dashboard\/media\/assets\/\d+$/.test(normalizedPath)) {
    return ['media.view', 'media.preview'];
  }
  if (normalizedPath === '/dashboard/content/review') {
    return ['content.submit', 'content.review', 'content.approve', 'content.publish'];
  }
  if (normalizedPath === '/dashboard/operations') {
    return ['operations.health.view', 'operations.logs.view', 'operations.jobs.view', 'operations.jobs.retry', 'operations.incidents.manage', 'analytics.playback.view'];
  }
  if (normalizedPath === '/dashboard/support') {
    return ['support.accounts.view', 'support.tickets.manage'];
  }
  if (normalizedPath === '/dashboard/analytics') {
    return ['analytics.audience.view', 'analytics.subscriptions.view', 'analytics.content.view', 'analytics.revenue.view', 'analytics.playback.view'];
  }
  const required = requiredPermissionForPath(normalizedPath);
  return required ? [required] : [];
}

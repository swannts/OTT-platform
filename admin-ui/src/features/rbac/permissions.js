export function hasPermission(user, permission) {
  return Array.isArray(user?.permissions) && user.permissions.includes(permission);
}

export function filterNavigationByPermissions(data, user) {
  return data
    .map((section) => {
      const items = (section.items || []).filter(
        (item) => !item.permission || hasPermission(user, item.permission)
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
  };
  return routePermissions[pathname] || null;
}

const ADMIN_PERMISSIONS = [
  'admin.access',
  'admin.permissions.manage',
  'admin.permissions.view',
  'admin.roles.create',
  'admin.roles.delete',
  'admin.roles.update',
  'admin.roles.view',
  'admin.staff.invite',
  'admin.staff.roles.update',
  'admin.staff.sessions.revoke',
  'admin.staff.status.update',
  'admin.staff.view',
  'analytics.audience.view',
  'analytics.content.view',
  'analytics.export',
  'analytics.playback.view',
  'analytics.revenue.view',
  'analytics.subscriptions.view',
  'audit.export',
  'audit.view',
  'content.approve',
  'content.publish',
  'content.reject',
  'content.review',
  'content.schedule',
  'content.submit',
  'content.view',
  'finance.invoices.view',
  'finance.payments.view',
  'finance.reconciliation.view',
  'finance.refunds.approve',
  'finance.refunds.request',
  'finance.reports.export',
  'marketing.banners.manage',
  'marketing.campaigns.approve',
  'marketing.campaigns.manage',
  'media.view',
  'operations.health.view',
  'operations.incidents.manage',
  'operations.jobs.retry',
  'operations.jobs.view',
  'operations.logs.view',
  'subscriptions.coupons.manage',
  'subscriptions.plans.archive',
  'subscriptions.plans.create',
  'subscriptions.plans.update',
  'subscriptions.subscribers.view',
  'subscriptions.trials.manage',
  'subscriptions.view',
  'support.accounts.view',
  'support.devices.view',
  'support.payments.view',
  'support.playback.view',
  'support.sessions.revoke',
  'support.tickets.manage',
];

const defaultUser = (permissions) => ({
  id: 1,
  name: 'OTT Test Administrator',
  email: 'admin@example.test',
  permissions: permissions ?? ADMIN_PERMISSIONS,
  roles: [{ id: 1, name: 'platform_admin', title: 'Platform Admin' }],
});

const defaultResponse = (method, pathname, user) => {
  if (pathname === '/api/admin/me') return { user };
  if (method === 'GET' && pathname.endsWith('/permissions')) return { data: [], groups: {} };
  if (method === 'GET') return { data: [], meta: { total: 0, current_page: 1, last_page: 1 } };
  return { data: {} };
};

Cypress.Commands.add('mockAdminApi', ({ permissions, routes = {} } = {}) => {
  const user = defaultUser(permissions);

  cy.intercept('**/api/admin/**', (request) => {
    const pathname = new URL(request.url).pathname.replace(/\/+$/, '') || '/';
    const method = request.method.toUpperCase();
    if (method === 'GET' && pathname === '/api/admin/me') request.alias = 'adminSession';
    const handler = routes[`${method} ${pathname}`] ?? routes[pathname];

    if (handler === undefined) {
      request.reply({ statusCode: 200, body: defaultResponse(method, pathname, user) });
      return;
    }

    if (typeof handler === 'function') {
      const result = handler(request, user);
      if (result !== undefined) {
        if (result && typeof result === 'object' && ('statusCode' in result || 'body' in result)) {
          request.reply(result);
        } else {
          request.reply({ statusCode: 200, body: result });
        }
      }
      return;
    }

    if (handler && typeof handler === 'object' && ('statusCode' in handler || 'body' in handler)) {
      request.reply(handler);
      return;
    }

    request.reply({ statusCode: 200, body: handler });
  }).as('adminApi');
});

Cypress.Commands.add('visitAdmin', (path, options = {}) => {
  cy.mockAdminApi(options);
  cy.visit(path);
});

Cypress.Commands.add('fillMuiField', (label, value) => {
  cy.contains('label', label).parent().find('input, textarea').first().clear().type(value);
});

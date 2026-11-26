const ADMIN_PERMISSIONS = ['admin.access', 'admin.roles.view'];

describe('administrator authentication', () => {
  it('validates credentials and reports a rejected sign-in without exposing backend details', () => {
    cy.mockAdminApi({
      permissions: ADMIN_PERMISSIONS,
      routes: {
        'GET /api/admin/me': (request) =>
          request.reply({ statusCode: 401, body: { message: 'Unauthenticated' } }),
        'POST /api/admin/login': (request) => {
          expect(request.body).to.deep.equal({ email: 'admin@example.test', password: 'wrong-password' });
          request.reply({ statusCode: 422, body: { message: 'Private backend authentication detail' } });
        },
      },
    });

    cy.visit('/login/');
    cy.wait('@adminSession');
    cy.get('input[name="email"]').type('admin@example.test');
    cy.get('input[name="password"]').type('wrong-password');
    cy.contains('button', 'Sign in').click();

    cy.contains('Email or password is incorrect, or this account does not have administrator access.').should('be.visible');
    cy.contains('Private backend authentication detail').should('not.exist');
    cy.location('pathname').should('match', /^\/login\/?$/);
  });

  it('signs in an authorized administrator and opens the dashboard', () => {
    let authenticated = false;
    const user = {
      id: 14,
      name: 'Platform Administrator',
      email: 'admin@example.test',
      permissions: ADMIN_PERMISSIONS,
      roles: [{ id: 1, name: 'platform_admin', title: 'Platform Admin' }],
    };

    cy.mockAdminApi({
      permissions: ADMIN_PERMISSIONS,
      routes: {
        'GET /api/admin/me': (request) =>
          request.reply(authenticated ? { statusCode: 200, body: { user } } : { statusCode: 401, body: { message: 'Unauthenticated' } }),
        'POST /api/admin/login': (request) => {
          expect(request.body).to.deep.equal({ email: user.email, password: 'correct-password' });
          authenticated = true;
          request.reply({
            statusCode: 200,
            headers: { 'set-cookie': 'ott_admin_token=cypress-session; Path=/; HttpOnly; SameSite=Lax' },
            body: { user },
          });
        },
      },
    });

    cy.visit('/login/');
    cy.wait('@adminSession');
    cy.get('input[name="email"]').type(user.email);
    cy.get('input[name="password"]').type('correct-password');
    cy.contains('button', 'Sign in').click();

    cy.wait('@adminSession');
    cy.location('pathname').should('match', /^\/dashboard\/?$/);
    cy.get('h4').filter(':visible').should('contain.text', 'OTT overview');
  });

  it('redirects unauthenticated users away from protected administration routes', () => {
    cy.mockAdminApi({
      permissions: [],
      routes: {
        'GET /api/admin/me': (request) =>
          request.reply({ statusCode: 401, body: { message: 'Unauthenticated' } }),
      },
    });

    cy.visit('/dashboard/access/roles/');
    cy.wait('@adminSession');
    cy.location('pathname').should('match', /^\/login\/?$/);
    cy.location('search').should('contain', 'returnTo=%2Fdashboard%2Faccess%2Froles');
  });
});

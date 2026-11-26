describe('administrative access workflows', () => {
  it('creates a custom role and sends only the entered role data to the API', () => {
    const roles = [];
    cy.visitAdmin('/dashboard/access/roles', {
      permissions: ['admin.access', 'admin.roles.view', 'admin.roles.create', 'admin.permissions.view', 'admin.permissions.manage'],
      routes: {
        'GET /api/admin/roles': () => ({ data: roles, meta: { total: roles.length } }),
        'GET /api/admin/permissions': { groups: { content: [{ name: 'content.review', title: 'Review content' }] } },
        'POST /api/admin/roles': (request) => {
          expect(request.body).to.include({ title: 'Content Reviewer', description: 'Reviews submitted catalog changes.', is_active: true });
          expect(request.body.permissions).to.deep.equal([]);
          roles.push({ id: 21, name: 'content_reviewer_custom', title: request.body.title, description: request.body.description, is_active: true, permissions: [], staff_count: 0, is_system: false });
          request.reply({ statusCode: 201, body: { data: roles[0] } });
        },
      },
    });

    cy.contains('Administrative roles').should('be.visible');
    cy.contains('button', 'Create role').click();
    cy.get('[role="dialog"]').should('be.visible');
    cy.fillMuiField('Role name', 'Content Reviewer');
    cy.fillMuiField('Description', 'Reviews submitted catalog changes.');
    cy.get('[role="dialog"]').contains('button', 'Save role').click();

    cy.contains('Role created.').should('be.visible');
    cy.contains('Content Reviewer').should('be.visible');
  });

  it('invites a staff member with a selected role', () => {
    let invitation;
    cy.visitAdmin('/dashboard/access/staff', {
      permissions: ['admin.access', 'admin.staff.view', 'admin.staff.invite', 'admin.roles.view'],
      routes: {
        'GET /api/admin/staff': { data: [], meta: { total: 0 } },
        'GET /api/admin/roles': { data: [{ id: 3, name: 'content_manager', title: 'Content Manager', is_active: true }] },
        'POST /api/admin/staff/invitations': (request) => {
          invitation = request.body;
          request.reply({ statusCode: 201, body: { data: { id: 44 } } });
        },
      },
    });

    cy.contains('Staff access').should('be.visible');
    cy.contains('button', 'Invite staff').click();
    cy.fillMuiField('Work email', 'editor@example.test');
    cy.contains('label', 'Content Manager').click();
    cy.get('[role="dialog"]').contains('button', 'Send invitation').click();

    cy.contains('Invitation sent. It expires after 48 hours.').should('be.visible');
    cy.then(() => {
      expect(invitation).to.deep.include({ email: 'editor@example.test', role_names: ['content_manager'] });
    });
  });

  it('shows an access-denied state when a signed-in user lacks the page permission', () => {
    cy.visitAdmin('/dashboard/access/roles', { permissions: ['admin.access'] });

    cy.contains('Access denied').should('be.visible');
    cy.contains('You do not have permission to open this administrative page.').should('be.visible');
    cy.contains('button', 'Create role').should('not.exist');
  });
});

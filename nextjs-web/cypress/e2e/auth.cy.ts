describe('Customer authentication', () => {
  it('renders sign-in fields and toggles password visibility', () => {
    cy.visit('/login');
    cy.contains('h2', 'Welcome Back').should('be.visible');
    cy.get('input[type="email"]').should('be.visible').and('have.attr', 'required');
    cy.get('input[type="password"]').should('be.visible').and('have.attr', 'required');
    cy.get('button[aria-label="Show password"]').click();
    cy.get('input[type="text"]').should('be.visible');
    cy.get('button[aria-label="Hide password"]').click();
    cy.get('input[type="password"]').should('be.visible');
  });

  it('uses browser validation before sending empty credentials', () => {
    cy.intercept('POST', '**/api/backend/login', { statusCode: 500 }).as('unexpectedLogin');
    cy.visit('/login');
    cy.get('button[type="submit"]').click();
    cy.get('input[type="email"]').then(($email) => {
      expect(($email[0] as HTMLInputElement).validity.valueMissing).to.equal(true);
    });
    cy.get('@unexpectedLogin.all').should('have.length', 0);
  });

  it('shows a safe message when the backend rejects sign-in', () => {
    cy.intercept('POST', '**/api/backend/login', {
      statusCode: 422,
      body: { message: 'The provided credentials are invalid.' },
    }).as('login');

    cy.visit('/login');
    cy.get('input[type="email"]').type('viewer@example.test');
    cy.get('input[type="password"]').type('wrong-password');
    cy.get('button[type="submit"]').click();
    cy.wait('@login').its('request.body').should('include', { email: 'viewer@example.test' });
    cy.contains('The provided credentials are invalid.').should('be.visible');
    cy.get('input[type="password"]').should('have.value', 'wrong-password');
  });

  it('navigates between login and registration pages', () => {
    cy.visit('/login');
    cy.get('a[href="/register"]').filter(':visible').last().click();
    cy.location('pathname').should('eq', '/register');
    cy.contains('h2', 'Create an Account').should('be.visible');
    cy.get('a[href="/login"]').filter(':visible').last().click();
    cy.location('pathname').should('eq', '/login');
  });

  it('validates registration input and reports a duplicate account safely', () => {
    cy.intercept('POST', '**/api/backend/register', {
      statusCode: 422,
      body: { message: 'The email has already been taken.' },
    }).as('register');

    cy.visit('/register');
    cy.get('button[type="submit"]').click();
    cy.get('input[type="email"]').then(($email) => {
      expect(($email[0] as HTMLInputElement).validity.valueMissing).to.equal(true);
    });

    cy.get('input[placeholder="Jane Doe"]').type('Test Viewer');
    cy.get('input[type="email"]').type('viewer@example.test');
    cy.get('input[type="password"]').first().type('SafePassword!23');
    cy.get('button[type="submit"]').click();
    cy.wait('@register');
    cy.contains('The email has already been taken.').should('be.visible');
  });

  it('redirects guests from every protected customer route to sign-in', () => {
    ['/account', '/account/security', '/account/billing', '/profiles'].forEach((route) => {
      cy.visit(route);
      cy.location('pathname', { timeout: 15000 }).should('eq', '/login');
      cy.location('search').should('include', 'redirect=');
    });
  });
});

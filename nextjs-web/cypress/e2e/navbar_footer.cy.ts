describe('Global navigation and footer', () => {
  it('returns to the home route through the NOOR brand link', () => {
    cy.visit('/movies');
    cy.get('nav a[href="/"]').first().click();
    cy.location('pathname').should('eq', '/');
  });

  it('searches from the desktop navigation', () => {
    cy.intercept('GET', '**/v3/get-search-data?search=Prophet', {
      statusCode: 200,
      body: { status: true, data: { data: [] } },
    });
    cy.visit('/');
    cy.get('nav input[placeholder*="Search"]').filter(':visible').first().type('Prophet{enter}');
    cy.location('pathname').should('eq', '/search');
    cy.location('search').should('eq', '?q=Prophet');
  });

  it('opens the notification dropdown and exposes its all-notifications action', () => {
    cy.visit('/');
    cy.get('button[aria-label^="Notifications"]').click();
    cy.get('button[aria-label^="Notifications"]').should('have.attr', 'aria-expanded', 'true');
    cy.contains('Sign in').should('be.visible');
  });

  it('offers sign-in and registration for guest users', () => {
    cy.viewport(390, 844);
    cy.visit('/');
    cy.get('button[aria-label="Toggle Navigation Menu"]').click();
    cy.get('nav a[href="/login"]').filter(':visible').should('be.visible');
    cy.get('a[href="/register"]').filter(':visible').should('be.visible');
  });

  it('shows account destinations in the authenticated user menu', () => {
    cy.loginAsUser({}, '/');
    cy.get('button[aria-label="User Profile"]').click();
    cy.contains('Test Viewer').should('be.visible');
    cy.get('a[href="/account"]').should('be.visible');
    cy.get('a[href="/watchlist"]').should('be.visible');
    cy.contains('Sign Out').should('be.visible');
  });

  it('renders working footer routes and legal links', () => {
    cy.visit('/');
    cy.scrollTo('bottom');
    cy.get('footer a[href="/live-tv"]').should('be.visible');
    cy.get('footer a[href="/pricing"]').should('be.visible');
    cy.get('footer a[href="/pages/privacy-policy"]').should('be.visible');
    cy.get('footer a[href="/pages/terms-and-conditions"]').should('be.visible');
    cy.get('footer a[href="/blog"]').should('be.visible');
    cy.get('footer a[href="/pages/faq"]').click();
    cy.location('pathname').should('eq', '/pages/faq');
  });
});

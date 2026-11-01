describe('Navbar and Footer E2E', () => {
  beforeEach(() => {
    cy.visit('/');
  });

  describe('Navbar Functionality', () => {
    it('navigates to home when clicking the brand logo', () => {
      cy.get('a[href="/"]').first().click();
      cy.url().should('eq', `${Cypress.config().baseUrl}/`);
    });

    it('submits search query from the navbar search input', () => {
      cy.get('nav input[placeholder*="Search"]').type('Prophet{enter}');
      cy.url().should('include', '/search?q=Prophet');
      cy.contains('Search results for "Prophet"').should('be.visible');
    });

    it('navigates to notifications page from the bell icon', () => {
      cy.get('nav a[href="/notifications"]').click();
      cy.url().should('include', '/notifications');
    });

    it('renders user menu options when logged out', () => {
      cy.get('button[aria-label="User Profile"]').click();
      cy.contains('Welcome to NOOR').should('be.visible');
      cy.contains('a', 'Sign In / Register').should('be.visible');
    });

    it('shows user dropdown menu when logged in', () => {
      cy.loginAsUser();
      cy.visit('/');
      cy.get('button[aria-label="User Profile"]').click();
      cy.contains('Test Viewer').should('be.visible');
      cy.contains('Account Settings').should('be.visible');
      cy.contains('My Watchlist').should('be.visible');
      cy.contains('Sign Out').should('be.visible');
    });
  });

  describe('Footer Links', () => {
    it('renders footer with brand description and category links', () => {
      cy.scrollTo('bottom');
      cy.contains('NOOR Media Network').should('be.visible');

      // Check footer links
      cy.get('footer a[href="/live-tv"]').should('be.visible');
      cy.get('footer a[href="/pricing"]').should('be.visible');
      cy.get('footer a[href="/pages/privacy-policy"]').should('be.visible');
      cy.get('footer a[href="/pages/terms-and-conditions"]').should('be.visible');
    });

    it('navigates to privacy policy page from footer link', () => {
      cy.scrollTo('bottom');
      cy.get('footer a[href="/pages/privacy-policy"]').click();
      cy.url().should('include', '/pages/privacy-policy');
      cy.contains(/Privacy Policy/i).should('be.visible');
    });
  });
});

describe('Authentication Flow E2E', () => {
  describe('Login Page', () => {
    it('visits the login page and renders form fields', () => {
      cy.visit('/login');
      cy.contains('Sign In').should('be.visible');
      cy.get('input[type="email"]').should('be.visible');
      cy.get('input[type="password"]').should('be.visible');
      cy.get('button[type="submit"]').should('be.visible');
    });

    it('validates empty credentials', () => {
      cy.visit('/login');
      cy.get('button[type="submit"]').click();
      cy.get('input[type="email"]:invalid').should('exist');
    });

    it('navigates to register page from login', () => {
      cy.visit('/login');
      cy.contains('Sign up now').click();
      cy.url().should('include', '/register');
    });
  });

  describe('Register Page', () => {
    it('renders registration form fields', () => {
      cy.visit('/register');
      cy.contains(/Create Account|Sign Up|Register/i).should('be.visible');
      cy.get('input[type="text"]').should('be.visible');
      cy.get('input[type="email"]').should('be.visible');
      cy.get('input[type="password"]').should('exist');
    });

    it('navigates back to login from registration page', () => {
      cy.visit('/register');
      cy.get('a[href="/login"]').click();
      cy.url().should('include', '/login');
    });
  });

  describe('Protected Route Guards', () => {
    it('redirects unauthenticated users from /account to /login', () => {
      cy.logoutUser();
      cy.visit('/account');
      cy.url().should('include', '/login');
    });

    it('redirects unauthenticated users from /profiles to /login', () => {
      cy.logoutUser();
      cy.visit('/profiles');
      cy.url().should('include', '/login');
    });

    it('redirects unauthenticated users from /account/security to /login', () => {
      cy.logoutUser();
      cy.visit('/account/security');
      cy.url().should('include', '/login');
    });

    it('redirects unauthenticated users from /account/billing to /login', () => {
      cy.logoutUser();
      cy.visit('/account/billing');
      cy.url().should('include', '/login');
    });
  });
});

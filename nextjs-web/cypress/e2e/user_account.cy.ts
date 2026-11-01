describe('User Account, Profiles & Preferences E2E', () => {
  describe('Watchlist Functionality', () => {
    it('prompts unauthenticated users to sign in to access watchlist', () => {
      cy.logoutUser();
      cy.visit('/watchlist');
      cy.contains('Sign in to view your watchlist').should('be.visible');
      cy.contains('a', 'Sign In').should('be.visible');
    });

    it('displays user watchlist when logged in', () => {
      cy.loginAsUser();
      cy.visit('/watchlist');
      cy.contains('My Watchlist').should('be.visible');
    });
  });

  describe('Account & Settings Management', () => {
    beforeEach(() => {
      cy.loginAsUser();
      cy.visit('/account');
    });

    it('renders account overview and profile details', () => {
      cy.contains('Account & Settings').should('be.visible');
      cy.get('input[value="Test Viewer"]').should('exist');
      cy.get('input[value="viewer@noor.tv"]').should('exist');
    });

    it('displays password change inputs', () => {
      cy.contains(/Change Password|Security/i).should('exist');
      cy.get('input[type="password"]').should('have.length.at.least', 2);
    });
  });

  describe('Profiles ("Who\'s Watching")', () => {
    beforeEach(() => {
      cy.loginAsUser();
      cy.visit('/profiles');
    });

    it('renders profile selection screen for logged in user', () => {
      cy.contains("Who's Watching?").should('be.visible');
      cy.contains('button', 'Manage Profiles').should('be.visible');
    });

    it('toggles manage profiles mode', () => {
      cy.contains('button', 'Manage Profiles').should('be.visible').click();
      cy.contains('button', 'Done').should('be.visible');
    });
  });

  describe('Notifications Center', () => {
    it('renders notification screen for logged in user', () => {
      cy.loginAsUser();
      cy.visit('/notifications');
      cy.contains(/Notifications|No notifications/i).should('be.visible');
    });
  });
});


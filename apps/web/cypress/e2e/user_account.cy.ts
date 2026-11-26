describe('Account, profiles, watchlist and notifications', () => {
  it('requires authentication for watchlist and redirects guests from account pages', () => {
    cy.logoutUser();
    cy.visit('/watchlist');
    cy.contains('Sign in to view your watchlist').should('be.visible');
    cy.visit('/account');
    cy.location('pathname').should('eq', '/login');
    cy.visit('/profiles');
    cy.location('pathname').should('eq', '/login');
  });

  it('loads account profile and supports URL-addressable settings tabs', () => {
    cy.loginAsUser({}, '/account');
    cy.contains('Account & Settings').should('be.visible');
    cy.contains('button', 'General Info').should('be.visible');
    cy.contains('button', 'Preferences').click();
    cy.location('search').should('contain', 'tab=preferences');
    cy.contains('Playback & Streaming Controls').should('be.visible');
    cy.get('select').first().select('bn');
    cy.contains('button', '720p HD').click();
    cy.contains('button', 'Save Preferences').click();
    cy.contains('Preferences saved successfully').should('be.visible');
    cy.window().its('localStorage.noor_playback_preferences').should('contain', '720p');
  });

  it('opens security and device settings with useful empty-device state', () => {
    cy.loginAsUser({}, '/account?tab=security');
    cy.contains('Security & PIN').should('be.visible');
    cy.get('input[type="password"]').should('have.length.at.least', 2);
    cy.loginAsUser({}, '/account?tab=devices');
    cy.contains('Manage Access & Devices').should('be.visible');
    cy.contains('Chrome on Mac').should('be.visible');
    cy.contains('No other devices are currently signed in.').should('be.visible');
  });

  it('loads watchlist data from its API and handles an empty list', () => {
    cy.intercept('GET', '**/v3/watch-list*', { statusCode: 200, body: { status: true, data: [] } }).as('watchlist');
    cy.loginAsUser({}, '/watchlist');
    cy.wait('@watchlist');
    cy.contains('My Watchlist').should('be.visible');
    cy.contains(/no titles|empty|nothing here/i).should('be.visible');
  });

  it('marks unread notifications through the API before opening their destination', () => {
    cy.intercept('GET', '**/api/backend/notification-list*', {
      statusCode: 200,
      body: { status: true, data: { notification_data: [{ notification_id: 'notice-1', title: 'New Episode', message: 'A new episode is available.', notification_type: 'episode_add', tv_show_id: 42, is_already_read: 0, date_time: '2026-11-25T12:00:00Z' }] } },
    }).as('notifications');
    cy.intercept('POST', '**/api/backend/notification/notice-1/mark-read*', { statusCode: 200, body: { status: true, data: { message: 'Read' } } }).as('markOne');
    cy.loginAsUser({}, '/notifications');
    cy.wait('@notifications');
    cy.contains('New Episode').should('be.visible');
    cy.contains('button', 'Open').click();
    cy.wait('@markOne');
    cy.location('pathname').should('eq', '/tv-shows/42');
  });

  it('marks all notifications read and dismisses an item through the API', () => {
    cy.intercept('GET', '**/api/backend/notification-list*', {
      statusCode: 200,
      body: { status: true, data: { notification_data: [{ notification_id: 'notice-1', title: 'New Episode', message: 'A new episode is available.', notification_type: 'episode_add', tv_show_id: 42, is_already_read: 0 }] } },
    });
    cy.intercept('POST', '**/api/backend/notification-mark-all-read*', { statusCode: 200, body: { status: true, data: { message: 'Read' } } }).as('markAll');
    cy.intercept('POST', '**/api/backend/v3/delete-notification*', { statusCode: 200, body: { status: true, data: { message: 'Dismissed' } } }).as('deleteNotification');
    cy.loginAsUser({}, '/notifications');
    cy.contains('New Episode').should('be.visible');
    cy.contains('button', 'Mark All as Read').click();
    cy.wait('@markAll');
    cy.get('button[title="Dismiss notification"]').click();
    cy.wait('@deleteNotification');
    cy.contains('No notifications yet').should('be.visible');
  });

  it('renders profile selection and toggles profile management', () => {
    cy.loginAsUser({}, '/profiles');
    cy.contains("Who's Watching?").should('be.visible');
    cy.contains('button', 'Manage Profiles').click();
    cy.contains('button', 'Done').should('be.visible');
  });
});

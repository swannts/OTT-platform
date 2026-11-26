// Cypress custom commands
/// <reference types="cypress" />

/* Cypress custom commands extend its global namespace by design. */
/* eslint-disable @typescript-eslint/no-namespace */
declare namespace Cypress {
  interface Chainable {
    getBySel(selector: string, options?: Partial<Cypress.Loggable & Cypress.Timeoutable & Cypress.Withinable & Cypress.Shadow>): Chainable<JQuery<HTMLElement>>;
    loginAsUser(customUser?: Partial<{ id: number; name: string; email: string; avatar: string; first_name?: string; last_name?: string }>, path?: string): Chainable<void>;
    logoutUser(): Chainable<void>;
  }
}

Cypress.Commands.add('getBySel', (selector, options) => {
  return cy.get(`[data-testid="${selector}"]`, options);
});

Cypress.Commands.add('loginAsUser', (customUser = {}, path = '/') => {
  const user = {
    id: 1,
    name: 'Test Viewer',
    email: 'viewer@noor.tv',
    first_name: 'Test',
    last_name: 'Viewer',
    avatar: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80',
    is_subscribe: 1,
    ...customUser,
  };

  cy.intercept('GET', '**/api/backend/v3/profile-details*', {
    statusCode: 200,
    body: {
      status: true,
      data: user,
    },
  });

  cy.intercept('GET', '**/api/backend/v3/user/subscription*', {
    statusCode: 200,
    body: {
      status: true,
      data: { has_active_subscription: true, is_subscribe: 1, plan_name: 'Test Premium' },
    },
  });

  cy.intercept('GET', '**/api/backend/account-setting*', {
    statusCode: 200,
    body: {
      status: true,
      data: {
        user,
        your_device: { id: 1, device_id: '127.0.0.1', device_name: 'Chrome on Mac', platform: 'Mac' },
        other_device: [],
      },
    },
  });

  cy.intercept('GET', '**/api/backend/user-profile-list*', {
    statusCode: 200,
    body: {
      status: true,
      data: [
        { id: 1, name: 'Test Viewer', is_child_profile: 0, is_active: 1 },
      ],
    },
  });

  cy.intercept('GET', '**/api/backend/v3/user/billing-history*', {
    statusCode: 200,
    body: { status: true, data: { data: [], current_page: 1, last_page: 1 } },
  });

  cy.setCookie('noor_session', 'cypress-session-token', { httpOnly: true, sameSite: 'lax' });
  cy.visit(path, {
    onBeforeLoad(win) {
      win.localStorage.setItem('token', 'mock-valid-jwt-token');
      win.localStorage.setItem('user', JSON.stringify(user));
    },
  });
});

Cypress.Commands.add('logoutUser', () => {
  cy.clearCookies();
  cy.visit('/', {
    onBeforeLoad(win) {
    win.localStorage.removeItem('token');
    win.localStorage.removeItem('user');
    },
  });
});

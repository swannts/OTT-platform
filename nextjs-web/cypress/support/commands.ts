// Cypress custom commands
/// <reference types="cypress" />

declare namespace Cypress {
  interface Chainable {
    getBySel(selector: string, options?: Partial<Cypress.Loggable & Cypress.Timeoutable & Cypress.Withinable & Cypress.Shadow>): Chainable<JQuery<HTMLElement>>;
    loginAsUser(customUser?: Partial<{ id: number; name: string; email: string; avatar: string }>): Chainable<void>;
    logoutUser(): Chainable<void>;
  }
}

Cypress.Commands.add('getBySel', (selector, options) => {
  return cy.get(`[data-testid="${selector}"]`, options);
});

Cypress.Commands.add('loginAsUser', (customUser = {}) => {
  const user = {
    id: 1,
    name: 'Test Viewer',
    email: 'viewer@noor.tv',
    avatar: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80',
    ...customUser,
  };
  window.localStorage.setItem('token', 'mock-valid-jwt-token');
  window.localStorage.setItem('user', JSON.stringify(user));
});

Cypress.Commands.add('logoutUser', () => {
  window.localStorage.removeItem('token');
  window.localStorage.removeItem('user');
});


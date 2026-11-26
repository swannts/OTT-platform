// Import commands.js using ES2015 syntax:
import './commands';

// Keep UI copy deterministic across the default Bengali locale and English tests.
Cypress.on('window:before:load', (win) => {
  win.localStorage.setItem('noor_locale', 'en');
});

beforeEach(() => {
  cy.setCookie('noor_locale', 'en');
});

// Import commands.js using ES2015 syntax:
import './commands';

// Prevent uncaught exceptions from failing Cypress tests
Cypress.on('uncaught:exception', () => {
  // returning false here prevents Cypress from failing the test
  return false;
});

import './commands';

Cypress.on('uncaught:exception', (error) => {
  // Keep real application exceptions visible; only ignore known browser-extension noise.
  if (/ResizeObserver loop limit exceeded/.test(error.message)) return false;
  return undefined;
});

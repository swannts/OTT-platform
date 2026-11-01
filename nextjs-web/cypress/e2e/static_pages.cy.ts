describe('Static Content & Policy Pages E2E', () => {
  it('renders the Terms and Conditions policy page', () => {
    cy.visit('/pages/terms-and-conditions');
    cy.contains(/Terms and Conditions/i).should('be.visible');
    cy.get('.prose').should('be.visible');
  });

  it('renders the Privacy Policy page', () => {
    cy.visit('/pages/privacy-policy');
    cy.contains(/Privacy Policy/i).should('be.visible');
    cy.get('.prose').should('be.visible');
  });

  it('renders the FAQ and Support page', () => {
    cy.visit('/pages/faq');
    cy.contains(/FAQ|Frequently Asked/i).should('be.visible');
  });

  it('renders the Refund Policy page', () => {
    cy.visit('/pages/refund-policy');
    cy.contains(/Refund Policy/i).should('be.visible');
  });
});


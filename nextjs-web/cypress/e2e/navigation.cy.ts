describe('Navigation & Route Pages E2E', () => {
  it('navigates to the Live TV page and renders channels guide', () => {
    cy.visit('/live-tv');
    cy.contains(/Channel Guide|Live TV/i).should('be.visible');
    cy.get('button').should('have.length.at.least', 2);
  });

  it('navigates to Movies catalog page', () => {
    cy.visit('/movies');
    cy.contains(/Islamic Movies|Movies/i).should('be.visible');
  });

  it('navigates to TV Shows catalog page', () => {
    cy.visit('/tv-shows');
    cy.contains(/Islamic TV Shows|TV Shows/i).should('be.visible');
  });

  it('navigates to Pricing / Premium plans page', () => {
    cy.visit('/pricing');
    cy.contains(/Premium Membership|Choose the Perfect Plan/i).should('be.visible');
    cy.contains(/Basic|Standard|Annual|Month|Subscribe/i).should('be.visible');
  });
});


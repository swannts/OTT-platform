describe('Search Functionality E2E', () => {
  it('displays initial search state with search input prompt', () => {
    cy.visit('/search');
    cy.get('input[placeholder*="Search"]').should('be.visible');
    cy.contains(/Type in the search bar above/i).should('be.visible');
  });

  it('searches for content via query parameter and displays results', () => {
    cy.visit('/search?q=Prophet');
    cy.contains('Search results for "Prophet"').should('be.visible');
  });

  it('allows user to enter search terms in the input and submit', () => {
    cy.visit('/search');
    cy.get('main input[placeholder*="Search"]').type('Quran{enter}');
    cy.contains(/Search results for "Quran"/i).should('be.visible');
  });

  it('displays empty state when no matching results found', () => {
    cy.visit('/search?q=xyznonexistentphrase999');
    cy.contains(/No results found/i).should('be.visible');
  });
});


describe('Catalog search', () => {
  it('shows an empty prompt before a query is submitted', () => {
    cy.visit('/search');
    cy.get('input[placeholder="Search titles, actors, genres..."]').should('be.visible');
    cy.contains('Type in the search bar above').should('be.visible');
  });

  it('submits a query, updates the URL, and displays its result state', () => {
    cy.intercept('GET', '**/v3/get-search-data?search=Quran', {
      statusCode: 200,
      body: { status: true, data: { data: [] } },
    }).as('search');

    cy.visit('/search');
    cy.get('input[placeholder="Search titles, actors, genres..."]').type('Quran{enter}');
    cy.location('search').should('eq', '?q=Quran');
    cy.contains('Search results for "Quran"').should('be.visible');
    cy.wait('@search');
  });

  it('uses a query-string search and gives an empty-results message', () => {
    cy.intercept('GET', '**/v3/get-search-data?search=zz-no-match-1042', {
      statusCode: 200,
      body: { status: true, data: { data: [] } },
    }).as('emptySearch');

    cy.visit('/search?q=zz-no-match-1042');
    cy.contains('Search results for "zz-no-match-1042"').should('be.visible');
    cy.wait('@emptySearch');
    cy.contains(/No results found|No content found|no titles/i).should('be.visible');
  });

  it('submits a query from the desktop navigation search', () => {
    cy.intercept('GET', '**/v3/get-search-data?search=Prophet', {
      statusCode: 200,
      body: { status: true, data: { data: [] } },
    });
    cy.visit('/');
    cy.get('nav input[placeholder*="Search"]').filter(':visible').first().type('Prophet{enter}');
    cy.location('search').should('eq', '?q=Prophet');
    cy.contains('Search results for "Prophet"').should('be.visible');
  });
});

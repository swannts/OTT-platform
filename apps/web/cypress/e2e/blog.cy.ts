describe('Journal and blog', () => {
  beforeEach(() => cy.visit('/blog'));

  it('renders the journal page and a clear empty state when the API has no articles', () => {
    cy.contains('h1', 'Stories, History & Sacred Cinema').should('be.visible');
    cy.contains('h2', 'Latest Articles & Chronicles').should('be.visible');
    cy.contains('h3', 'No articles found').should('be.visible');
    cy.get('article').should('not.exist');
  });

  it('filters the article list by search and resets filters', () => {
    cy.get('input[placeholder="Search articles, topics..."]').should('be.enabled').type('nonexistent topic');
    cy.contains('h3', 'No articles found').should('be.visible');
    cy.get('input[placeholder="Search articles, topics..."]').should('have.value', 'nonexistent topic');
    cy.contains('button', 'Reset All Filters').click();
    cy.get('input[placeholder="Search articles, topics..."]').should('have.value', '');
  });

  it('keeps the All Stories filter available when no categories are configured', () => {
    cy.contains('button', 'All Stories').should('be.visible').click();
    cy.contains('h3', 'No articles found').should('be.visible');
  });

});

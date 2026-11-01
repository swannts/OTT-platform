describe('Blog & Journal E2E Tests', () => {
  beforeEach(() => {
    cy.visit('/blog');
  });

  it('loads the blog index page with hero and article grid', () => {
    cy.contains('Stories, History & Sacred Cinema').should('be.visible');
    cy.contains('Featured Story').should('be.visible');
    cy.get('article').should('have.length.at.least', 1);
  });

  it('filters articles by category', () => {
    cy.contains('button', 'All Stories').should('be.visible');
    // Click on a category if present
    cy.get('button').contains(/Behind the Scenes|Islamic History|Cinema & Culture/).first().click();
    cy.get('article').should('have.length.at.least', 1);
  });

  it('filters articles using search input', () => {
    cy.get('input[placeholder*="articles"]').type('Cinema');
    cy.get('article').should('have.length.at.least', 1);
  });

  it('navigates to a single blog post and displays full article content', () => {
    cy.get('article a').first().click();
    cy.url().should('include', '/blog/');
    cy.contains('Back to All Stories').should('be.visible');
    cy.get('h1').should('be.visible');
    cy.contains(/min read/i).should('be.visible');
    cy.contains('NOOR Editorial').should('exist');
  });
});

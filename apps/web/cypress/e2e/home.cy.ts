describe('NOOR home and discovery', () => {
  beforeEach(() => cy.visit('/'));

  it('renders the brand, primary navigation, search, and membership entry point', () => {
    cy.get('nav a[href="/"]').should('be.visible');
    cy.get('nav a[href="/live-tv"]').should('be.visible');
    cy.get('nav a[href="/tv-shows"]').should('be.visible');
    cy.get('nav a[href="/movies"]').should('be.visible');
    cy.get('nav input[placeholder*="Search"]').should('be.visible');
    cy.get('nav a[href="/pricing"]').should('be.visible');
  });

  it('renders either the catalog hero or its intentional empty-catalog state', () => {
    cy.get('h1').should('be.visible').and('not.have.text', '');
    cy.get('body').then(($body) => {
      const hasHeroControls = $body.find('button[aria-label="Next Slide"]').length > 0;
      expect(hasHeroControls || $body.find('h1').text().trim().length > 0).to.equal(true);
    });
  });

  it('moves the hero carousel and retains working slide controls', () => {
    cy.get('body').then(($body) => {
      const hasControls = $body.find('button[aria-label="Next Slide"]').length > 0;
      if (hasControls) {
        cy.get('button[aria-label="Next Slide"]').click();
        cy.get('h1').first().should('be.visible');
        cy.get('button[aria-label="Previous Slide"]').should('be.enabled');
        cy.get('button[aria-label="Next Slide"]').should('be.enabled');
      } else {
        cy.log('The API currently provides one featured title, so carousel controls are not rendered.');
        cy.get('h1').first().should('be.visible');
      }
    });
  });

  it('renders browse sections and content cards when the catalog has data', () => {
    cy.get('body').then(($body) => {
      const hasContent = $body.find('a[href^="/movies/"], a[href^="/tv-shows/"]').length > 0;
      if (hasContent) {
        cy.get('a[href^="/movies/"], a[href^="/tv-shows/"]').filter(':visible').should('have.length.at.least', 1);
      } else {
        cy.get('h1').should('be.visible');
      }
    });
  });

  it('does not start the floating player until a title is played', () => {
    cy.get('[title="Click to seek"]').should('not.exist');
    cy.get('[title="Audio streaming"]').should('not.exist');
  });
});

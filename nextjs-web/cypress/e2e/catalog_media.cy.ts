describe('Media Catalogs & Detail Pages E2E', () => {
  describe('Movies Catalog & Detail', () => {
    it('loads the movies catalog page', () => {
      cy.visit('/movies');
      cy.contains(/Islamic Movies|Movies/i).should('be.visible');
      cy.get('.grid').should('exist');
    });

    it('navigates to movie detail page and displays media attributes', () => {
      cy.visit('/movies/01940000-0004-7000-8000-000000000006');
      cy.get('h1').should('be.visible');
      cy.contains(/Play Movie|Watch Now/i).should('be.visible');
      cy.contains(/Storyline|Overview|About/i).should('be.visible');
    });

    it('verifies add to watchlist button on movie detail', () => {
      cy.visit('/movies/01940000-0004-7000-8000-000000000006');
      cy.contains(/Add to Watchlist|In Watchlist/i).should('be.visible').click();
    });
  });

  describe('TV Shows Catalog & Detail', () => {
    it('loads the TV shows catalog page', () => {
      cy.visit('/tv-shows');
      cy.contains(/Islamic TV Shows|TV Shows/i).should('be.visible');
      cy.get('.grid').should('exist');
    });

    it('navigates to TV show detail page and lists episodes', () => {
      cy.visit('/tv-shows/01940000-0003-7000-8000-000000000001');
      cy.get('h1').should('be.visible');
      cy.contains('Episodes').should('be.visible');
      cy.contains(/Play S1 E1|Play Episode|Watch/i).should('be.visible');
    });
  });

  describe('Short Videos & Lectures Catalog', () => {
    it('loads the videos catalog page', () => {
      cy.visit('/videos');
      cy.contains(/Short Videos|Podcasts|Waz & Bayan|Videos/i).should('be.visible');
    });
  });
});


describe('Movies, series, and video catalogs', () => {
  it('renders movie browse controls and available media cards', () => {
    cy.visit('/movies');
    cy.get('h1').should('be.visible');
    cy.get('a[href^="/movies/"]').filter(':visible').should('have.length.at.least', 1);
    cy.get('input[placeholder*="Search"]').should('exist');
  });

  it('opens a real movie from the catalog and renders its detail actions', () => {
    cy.visit('/movies');
    cy.get('a[href^="/movies/"]').filter(':visible').first().invoke('attr', 'href').then((href) => {
      expect(href).to.be.a('string').and.match(/^\/movies\//);
      cy.visit(href as string);
    });

    cy.get('h1').should('be.visible');
    cy.contains(/Storyline|Overview|About/i).should('be.visible');
    cy.contains('a', /Play Movie|Watch Now/i).should('be.visible');
    cy.contains('button', /Add to Watchlist|In Watchlist/i).should('be.visible');
  });

  it('persists a movie watchlist toggle through the API client without touching local data', () => {
    cy.loginAsUser({}, '/movies');
    cy.intercept('POST', '**/save-watchlist*', {
      statusCode: 200,
      body: { status: true, data: { status: true, message: 'Saved' } },
    }).as('saveWatchlist');
    cy.intercept('POST', '**/delete-watchlist*', {
      statusCode: 200,
      body: { status: true, data: { status: true, message: 'Removed' } },
    }).as('deleteWatchlist');
    cy.get('a[href^="/movies/"]').filter(':visible').first().invoke('attr', 'href').then((href) => {
      cy.visit(href as string, {
        onBeforeLoad(win) {
          win.localStorage.setItem('token', 'mock-valid-jwt-token');
          win.localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Test Viewer', email: 'viewer@noor.tv', first_name: 'Test', last_name: 'Viewer', is_subscribe: 1 }));
        },
      });
    });
    cy.contains('button', /Add to Watchlist|In Watchlist/).then(($button) => {
      const isSaved = $button.text().includes('In Watchlist');
      cy.wrap($button).click();
      cy.wait(isSaved ? '@deleteWatchlist' : '@saveWatchlist');
      cy.contains('button', isSaved ? 'Add to Watchlist' : 'In Watchlist').should('be.visible');
    });
  });

  it('renders TV series and opens a real series detail page', () => {
    cy.visit('/tv-shows');
    cy.get('h1').should('be.visible');
    cy.get('body').then(($body) => {
      const $links = $body.find('a[href^="/tv-shows/"]:visible');
      if ($links.length === 0) {
        cy.contains('No series found in this category').should('be.visible');
        return;
      }
      const href = $links.first().attr('href');
      expect(href).to.be.a('string');
      cy.visit(href as string);
      cy.get('h1').should('be.visible');
      cy.contains(/Episodes|Season/i).should('be.visible');
    });
  });

  it('renders the short videos and lectures catalog', () => {
    cy.visit('/videos');
    cy.get('h1').should('be.visible');
    cy.contains(/Videos|Waz|Podcasts/i).should('be.visible');
  });

});

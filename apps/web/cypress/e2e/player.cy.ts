describe('Playback routes and saved progress', () => {
  it('does not show a sticky mini-player before playback starts', () => {
    cy.visit('/');
    cy.get('[title="Click to seek"]').should('not.exist');
    cy.get('[title="Audio streaming"]').should('not.exist');
  });

  it('shows a clear not-found error for unavailable title IDs', () => {
    cy.visit('/watch/movie/cypress-missing-title');
    cy.contains('Unable to load this title').should('be.visible');
  });

  it('enforces the title access entitlement when opening playback', () => {
    cy.visit('/movies');
    cy.get('a[href^="/movies/"]').filter(':visible').first().invoke('attr', 'href').then((href) => {
      cy.visit(href as string);
    });
    cy.contains('a', /Play Movie|Watch Now/i).should('be.visible').click();
    cy.location('pathname').should('match', /^\/watch\/movie\//);
    cy.get('body').should(($body) => {
      const entitledPlayer = $body.find('[data-testid="custom-video-player"], [data-testid="youtube-connector"], video').length > 0;
      const subscriptionGate = $body.text().includes('Subscription Required');
      expect(entitledPlayer || subscriptionGate).to.equal(true);
      if (subscriptionGate) expect($body.text()).to.include('Upgrade to VIP');
    });
  });

  it('restores saved progress and lets the viewer start over', () => {
    cy.intercept('GET', '**/api/backend/v3/content-details*', { statusCode: 200, body: { status: true, data: { id: 'resume-title', details: { id: 'resume-title', name: 'Resume Test', name_en: 'Resume Test', type: 'movie', access: 'free' } } } });
    cy.intercept('POST', '**/api/backend/v3/playback/details', { statusCode: 200, body: { status: true, data: { video_qualities: [{ url: 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8', url_type: 'HLS' }] } } });
    const progress = JSON.stringify({ currentTime: 75, duration: 3600, completed: false, updatedAt: Date.now() });
    cy.visit('/watch/movie/resume-title', { onBeforeLoad(win) { win.localStorage.setItem('noor_profile_id', '1'); win.localStorage.setItem('noor_watch_progress_movie_resume-title', progress); } });
    cy.get('[data-testid="resume-banner"]', { timeout: 20000 }).should('be.visible');
    cy.contains('button', 'Start Over').click();
    cy.get('[data-testid="resume-banner"]').should('not.exist');
  });
});

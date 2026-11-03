describe('Video Player E2E', () => {
  it('does not render bottom sticky player bar', () => {
    cy.visit('/');
    cy.get('[title="Click to seek"]').should('not.exist');
    cy.get('[title="Audio streaming"]').should('not.exist');
  });

  describe('Full Video Watch Player (/watch/[type]/[id])', () => {
    it('mounts video streaming player and navigation controls', () => {
      cy.visit('/watch/movie/01940000-0004-7000-8000-000000000006');
      cy.contains('button', 'Back').should('be.visible');
      cy.get('video, .relative').should('exist');
    });

    it('navigates back when clicking the player back button', () => {
      cy.visit('/watch/movie/01940000-0004-7000-8000-000000000006');
      cy.contains('button', 'Back').click();
      cy.url().should('not.include', '/watch/movie/01940000-0004-7000-8000-000000000006');
    });

    it('mounts YouTube connector when content source is YouTube', () => {
      cy.visit('/watch/movie/01940000-0004-7000-8000-000000000009');
      cy.get('[data-testid="youtube-connector"]').should('exist');
      cy.get('iframe').should('have.attr', 'src').and('include', 'youtube-nocookie.com/embed');
      cy.contains('YouTube Stream').should('exist');
      cy.contains('a', 'YouTube').should('have.attr', 'target', '_blank');
    });

    it('allows switching between YouTube and HLS stream sources', () => {
      cy.visit('/watch/movie/01940000-0004-7000-8000-000000000009');
      cy.get('[data-testid="youtube-connector"]').should('exist');
      cy.contains('button', '1080p HLS').click();
      cy.get('video').should('exist');
      cy.contains('button', 'YouTube HD').click();
      cy.get('[data-testid="youtube-connector"]').should('exist');
    });

    it('supports the resume system and allows starting over from beginning', () => {
      const contentId = '01940000-0004-7000-8000-000000000006';
      const storageKey = `noor_watch_progress_movie_${contentId}`;
      const mockSavedProgress = JSON.stringify({
        currentTime: 75,
        duration: 3600,
        completed: false,
        updatedAt: Date.now(),
      });

      cy.visit(`/watch/movie/${contentId}`, {
        onBeforeLoad(win) {
          win.localStorage.setItem(storageKey, mockSavedProgress);
        },
      });

      // Verify resume banner toast appears with saved time and allows Start Over
      cy.get('[data-testid="resume-banner"]').should('be.visible');
      cy.get('[data-testid="resume-banner"]').should('contain', 'Resumed from');
      cy.contains('button', 'Start Over').click();
      cy.get('[data-testid="resume-banner"]').should('not.exist');
    });
  });
});

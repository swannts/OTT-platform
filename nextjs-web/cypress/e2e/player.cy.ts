describe('Audio & Video Players E2E', () => {
  describe('Bottom Floating Player Dock', () => {
    beforeEach(() => {
      cy.visit('/');
    });

    it('toggles play and pause state', () => {
      cy.get('[title="Click to seek"]').should('be.visible');
      cy.get('button[aria-label="Play"], button[aria-label="Pause"]').first().as('playBtn');
      cy.get('@playBtn').click();
      cy.get('[title="Audio streaming"]').should('be.visible');
    });

    it('allows seeking via progress bar scrubber', () => {
      cy.get('[title="Click to seek"]').click('center');
    });

    it('toggles mute on volume button click', () => {
      cy.get('button[aria-label="Mute"], button[aria-label="Unmute"]').first().click();
      cy.get('button[aria-label="Unmute"]').should('exist');
    });

    it('minimizes into floating bubble and expands back', () => {
      cy.get('button[aria-label="Minimize player"]').click();
      cy.contains('Tap to expand').should('be.visible');
      cy.contains('Tap to expand').click();
      cy.get('[title="Click to seek"]').should('be.visible');
    });

    it('dismisses player dock when close button is clicked', () => {
      cy.get('button[aria-label="Dismiss player"]').click();
      cy.get('[title="Click to seek"]').should('not.exist');
    });
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
  });
});

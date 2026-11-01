describe('NOOR Home Page E2E', () => {
  beforeEach(() => {
    cy.visit('/');
  });

  it('renders the navbar with NOOR branding and essential nav items', () => {
    // Brand logo
    cy.contains('NOOR').should('be.visible');

    // Navigation links
    cy.contains('a', 'Home').should('be.visible');
    cy.contains('Explore').should('be.visible');
    cy.contains('a', 'Live TV').should('be.visible');
    cy.contains('a', 'Series').should('be.visible');
    cy.contains('a', 'Documentaries').should('be.visible');

    // Search bar
    cy.get('input[placeholder*="Search"]').should('exist');
  });

  it('displays the hero banner with cinematic title and action buttons', () => {
    // Hero title or prophet series
    cy.get('h1').should('be.visible');

    // Action buttons
    cy.contains('Watch Now').should('be.visible');
    cy.contains('More Info').should('be.visible');

    // Hero carousel controls
    cy.get('button[aria-label="Previous Slide"]').should('exist');
    cy.get('button[aria-label="Next Slide"]').should('exist');
  });

  it('renders content rows and media cards', () => {
    // Section headers
    cy.contains('Trending Now').should('be.visible');

    // Media cards loaded from backend or seeders
    cy.get('h3').should('have.length.at.least', 2);
    cy.contains(/Stories of the Prophets|Understanding the Quran|Trending/i).should('be.visible');
  });

  it('displays the Live Now section and Go Premium card', () => {
    cy.contains('Live Now').should('be.visible');
    cy.contains('All Channels').should('be.visible');

    // Go Premium promo card
    cy.contains('Go Premium').should('be.visible');
    cy.contains('Upgrade Now').should('be.visible');
  });

  it('renders the floating bottom player bar dock', () => {
    // Verify audio/media bottom dock exists
    cy.get('button[aria-label="Play"], button[aria-label="Pause"]').should('exist');
    cy.get('[title="Audio streaming"]').should('exist');
    cy.get('[title="Click to seek"]').should('exist');
  });
});


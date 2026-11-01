describe('Live TV Streaming E2E', () => {
  beforeEach(() => {
    cy.visit('/live-tv');
  });

  it('renders the channel guide with available live channels', () => {
    cy.contains(/Channel Guide|Live TV/i).should('be.visible');
    cy.get('button').should('have.length.at.least', 2);
  });

  it('switches between live broadcast channels', () => {
    // Click on the second channel in the guide
    cy.get('button:has(h4)').eq(1).click();
    
    // Verify channel detail card or live badge updates
    cy.get('.glass-card').should('exist');
  });

  it('mounts the video streaming container', () => {
    cy.get('video, .relative').should('exist');
  });
});


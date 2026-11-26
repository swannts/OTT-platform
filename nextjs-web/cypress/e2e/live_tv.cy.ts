describe('Live television', () => {
  it('renders the channel page and guide for the current catalog', () => {
    cy.visit('/live-tv');
    cy.get('h1').should('be.visible').and('contain.text', 'Live');
    cy.contains(/Channel Guide \(\d+\)/).should('be.visible');
    cy.get('button').then(($buttons) => {
      const channelButtons = [...$buttons].filter((button) => button.textContent?.includes('Live'));
      if (channelButtons.length > 0) {
        cy.get('[data-testid="channel-details-card"]').should('be.visible');
      } else {
        cy.contains('Select a channel to start streaming').should('be.visible');
      }
    });
  });

  it('changes selected channel when multiple broadcasts are available', () => {
    cy.visit('/live-tv');
    cy.get('button').then(($buttons) => {
      const channels = [...$buttons].filter((button) => button.querySelector('h4'));
      if (channels.length < 2) {
        cy.log('Channel switching needs at least two published broadcasts.');
        return;
      }
      const secondName = channels[1].querySelector('h4')?.textContent?.trim() ?? '';
      cy.wrap(channels[1]).click();
      cy.get('[data-testid="channel-details-card"] h2').should('contain.text', secondName);
    });
  });
});

describe('Primary route navigation smoke tests', () => {
  const routes = [
    { path: '/live-tv', heading: /Live Islamic Television/i },
    { path: '/movies', heading: /Movies/i },
    { path: '/tv-shows', heading: /TV Shows/i },
    { path: '/pricing', heading: /Choose Your Plan|Membership/i },
    { path: '/blog', heading: /Stories|Articles|Chronicles/i },
    { path: '/videos', heading: /Lectures|Waz|Bayan/i },
  ];

  routes.forEach(({ path, heading }) => {
    it(`renders ${path}`, () => {
      cy.visit(path);
      cy.get('h1').first().should('be.visible').invoke('text').should((text) => {
        expect(text).to.match(heading);
      });
    });
  });
});

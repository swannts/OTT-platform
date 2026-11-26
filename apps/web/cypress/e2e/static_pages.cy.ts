describe('Static informational pages', () => {
  const pages = [
    { path: '/pages/terms-and-conditions', title: /Terms and Conditions/i },
    { path: '/pages/privacy-policy', title: /Privacy Policy/i },
    { path: '/pages/faq', title: /FAQ|Frequently Asked/i },
    { path: '/pages/refund-policy', title: /Refund Policy/i },
  ];

  pages.forEach(({ path, title }) => {
    it(`loads ${path} with page content`, () => {
      cy.visit(path);
      cy.get('main').should('be.visible');
      cy.get('h1, h2').filter((_index, element) => title.test(element.textContent ?? '')).first().should('be.visible');
    });
  });

  it('uses a safe generic policy template for unknown CMS page slugs', () => {
    cy.visit('/pages/cypress-unknown-page');
    cy.contains('h1', 'Cypress Unknown Page').should('be.visible');
    cy.contains('NOOR is committed to providing').should('be.visible');
  });
});

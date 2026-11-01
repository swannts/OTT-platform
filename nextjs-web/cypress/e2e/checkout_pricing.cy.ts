describe('Pricing, Checkout & Subscription E2E', () => {
  describe('Pricing Page', () => {
    it('renders membership plans with features and pricing info', () => {
      cy.visit('/pricing');
      cy.contains(/Go Premium Membership|Choose the Perfect Plan/i).should('be.visible');
      cy.get('.glass-card, [class*="rounded-3xl"]').should('have.length.at.least', 1);
      cy.contains(/Month|Year|Subscribe|Get Started/i).should('be.visible');
    });

    it('navigates from plan card to checkout page', () => {
      cy.visit('/pricing');
      cy.get('a[href*="/checkout/"]').first().click();
      cy.url().should('include', '/checkout/');
    });
  });

  describe('Checkout Page', () => {
    beforeEach(() => {
      cy.visit('/checkout/1');
    });

    it('renders order summary and payment method options', () => {
      cy.contains('Order Summary').should('be.visible');
      cy.contains('Total Due Today').should('be.visible');
      cy.contains('Credit / Debit Card (Stripe)').should('be.visible');
      cy.contains('PayPal Checkout').should('be.visible');
    });

    it('toggles payment methods between Stripe and PayPal', () => {
      cy.contains('PayPal Checkout').click();
      cy.contains('PayPal Checkout').should('have.class', 'border-red-500');

      cy.contains('Credit / Debit Card (Stripe)').click();
      cy.contains('Credit / Debit Card (Stripe)').should('have.class', 'border-red-500');
    });

    it('handles invalid promo code input gracefully', () => {
      cy.intercept('POST', '**/subscription/calculate-price').as('calcPrice');
      cy.get('input[placeholder*="VIP20"]').type('INVALIDCODE123');
      cy.contains('button', 'Apply').click();
      cy.wait('@calcPrice');
      cy.contains(/invalid|not found|failed|expired/i).should('be.visible');
    });
  });

  describe('Payment Success Confirmation', () => {
    it('displays confirmation when visiting payment success page', () => {
      cy.visit('/payment-success?free=1');
      cy.contains(/Subscription Active|Payment Successful/i).should('be.visible');
      cy.contains(/Start Streaming Now|Start Watching/i).should('be.visible');
    });
  });
});


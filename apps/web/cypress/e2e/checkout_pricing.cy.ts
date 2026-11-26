const mockCalculation = {
  plan_id: 1,
  plan_name: 'Test Premium',
  original_price: 10,
  plan_discount_amount: 0,
  price_after_plan_discount: 10,
  coupon_discount_amount: 0,
  subtotal: 10,
  total_tax: 0,
  taxes: [],
  total_amount: 10,
  currency: 'USD',
};

function stubCheckoutApis() {
  cy.intercept('POST', '**/v3/subscription/calculate-price*', {
    statusCode: 200,
    body: { status: true, data: mockCalculation },
  }).as('calculatePrice');
  cy.intercept('GET', '**/v3/payment-methods*', {
    statusCode: 200,
    body: { status: true, data: { stripe_pay: { stripe_publickey: 'pk_test_no-charge' }, paypal_pay: { paypal_clientid: 'test-client' } } },
  }).as('paymentMethods');
  cy.intercept('GET', '**/v3/app-configuration*', {
    statusCode: 200,
    body: { status: true, data: {} },
  });
}

describe('Pricing and checkout', () => {
  it('renders membership plans and follows a plan into checkout', () => {
    cy.visit('/pricing');
    cy.contains('h1', 'Choose Your Plan for Pure Islamic Entertainment').should('be.visible');
    cy.get('a[href^="/checkout/"]').filter(':visible').should('have.length.at.least', 1);
    cy.get('a[href^="/checkout/"]').filter(':visible').first().click();
    cy.location('pathname').should('match', /^\/checkout\//);
  });

  it('shows order total and configured payment methods on a valid checkout', () => {
    stubCheckoutApis();
    cy.visit('/checkout/1');
    cy.wait('@calculatePrice');
    cy.wait('@paymentMethods');
    cy.contains('h3', 'Order Summary').should('be.visible');
    cy.contains('Total Due Today').should('be.visible');
    cy.contains('Test Premium').should('be.visible');
    cy.contains('button', 'Credit / Debit Card').should('be.visible');
    cy.contains('button', 'PayPal').should('be.visible');
  });

  it('switches payment methods without submitting a payment', () => {
    stubCheckoutApis();
    cy.visit('/checkout/1');
    cy.contains('button', 'PayPal').click();
    cy.contains('button', 'PayPal').should('have.class', 'border-[#E5B869]');
    cy.contains('button', 'Credit / Debit Card').click();
    cy.contains('button', 'Credit / Debit Card').should('have.class', 'border-[#E5B869]');
  });

  it('shows an error for an invalid coupon and keeps the checkout usable', () => {
    cy.intercept('POST', '**/v3/subscription/calculate-price*', (request) => {
      const body = typeof request.body === 'string' ? JSON.parse(request.body) as { coupon_code?: string } : request.body;
      if (body?.coupon_code) {
        request.reply({ statusCode: 422, body: { message: 'Coupon code is invalid or expired.' } });
      } else {
        request.reply({ statusCode: 200, body: { status: true, data: mockCalculation } });
      }
    }).as('calculatePrice');
    cy.intercept('GET', '**/v3/payment-methods*', { statusCode: 200, body: { status: true, data: {} } });
    cy.intercept('GET', '**/v3/app-configuration*', { statusCode: 200, body: { status: true, data: {} } });

    cy.visit('/checkout/1');
    cy.wait('@calculatePrice');
    cy.get('input[placeholder*="NOOR20"]').type('INVALID20');
    cy.contains('button', 'Apply').click();
    cy.wait('@calculatePrice');
    cy.contains('Coupon code is invalid or expired.').should('be.visible');
    cy.contains('button', 'Pay Securely Now').should('be.enabled');
  });

  it('requires authentication before a guest can continue to payment', () => {
    stubCheckoutApis();
    cy.visit('/checkout/1');
    cy.contains('button', 'Pay Securely Now').click();
    cy.location('pathname').should('eq', '/login');
    cy.location('search').should('eq', '?redirect=/checkout/1');
  });

  it('renders payment success and recovery links', () => {
    cy.visit('/payment-success?free=1');
    cy.contains(/Subscription Active|Payment Successful/i).should('be.visible');
    cy.get('a[href="/"]').should('be.visible');
  });
});

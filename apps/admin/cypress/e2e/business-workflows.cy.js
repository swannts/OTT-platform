describe('OTT business operations', () => {
  it('creates a support ticket and displays the saved ticket', () => {
    let createdTicket;
    cy.viewport(1600, 900);
    cy.visitAdmin('/dashboard/support', {
      permissions: ['admin.access', 'support.tickets.manage'],
      routes: {
        'GET /api/admin/support/overview': { data: { open_tickets: 1 } },
        'GET /api/admin/support/tickets': () => ({ data: createdTicket ? [createdTicket] : [] }),
        'GET /api/admin/support/assignees': { data: [] },
        'POST /api/admin/support/tickets': (request) => {
          createdTicket = { id: 62, customer_id: 12, status: 'open', created_at: '2026-11-26T10:00:00Z', ...request.body };
          request.reply({ statusCode: 201, body: { data: createdTicket } });
        },
      },
    });

    cy.get('h4').filter(':visible').should('contain.text', 'Customer support');
    cy.fillMuiField('Customer ID', '12');
    cy.fillMuiField('Subject', 'Playback stops on episode two');
    cy.fillMuiField('Issue details', 'The player pauses after the first minute on my television.');
    cy.contains('button', 'Create').click();
    cy.contains('Ticket created.').should('be.visible');
    cy.contains('Playback stops on episode two').should('be.visible');
    cy.contains('Customer ID').should('be.visible');
  });

  it('runs an entitlement simulation against the selected subscription plan', () => {
    let simulationRequest;
    cy.visitAdmin('/dashboard/subscriptions', {
      permissions: ['admin.access', 'subscriptions.view'],
      routes: {
        'GET /api/admin/subscriptions/plans': { data: [{ id: 7, name: 'Family plan', price: 9.99, duration: 'month', duration_value: 1, level: 2, status: true, entitlements: {} }], meta: { total: 1 } },
        'GET /api/admin/subscriptions/performance': { data: [] },
        'GET /api/admin/subscriptions/pricing-options': { data: { countries: [], currencies: [] } },
        'POST /api/admin/subscriptions/entitlement-simulation': (request) => {
          simulationRequest = request.body;
          request.reply({ statusCode: 200, body: { data: { eligible: true, checks: { plan_level: true, profile_limit: true, device_limit: true, stream_limit: true, video_quality: true } } } });
        },
      },
    });

    cy.contains('Subscription plans').should('be.visible');
    cy.contains('label', 'Plan').parent().find('[role="combobox"]').click();
    cy.get('[role="option"]').contains('Family plan').click();
    cy.contains('button', 'Simulate').click();
    cy.contains('Eligible: yes').should('be.visible');
    cy.then(() => {
      expect(simulationRequest).to.include({ plan_id: 7, required_plan_level: 1, profile_count: 1, device_count: 1, stream_count: 1, requested_video_quality: 720 });
    });
  });

  it('submits a partial refund request for independent approval', () => {
    let refundRequest;
    cy.visitAdmin('/dashboard/finance', {
      permissions: ['admin.access', 'finance.payments.view', 'finance.refunds.request'],
      routes: {
        'GET /api/admin/finance/dashboard': { data: { verified_payments: 1, initiated_payments: 0, pending_refunds: 0, open_disputes: 0 } },
        'GET /api/admin/finance/payments': { data: [{ source: 'verified_payments', source_id: 30, verified_payment_id: 30, provider_reference: 'pay_demo_30', user_id: 12, user_name: 'Samira Noor', user_email: 'samira@example.test', purchase_type: 'subscription', gateway: 'stripe', amount: 12.5, currency: 'USD', status: 'paid', refundable: true }] },
        'GET /api/admin/finance/refunds': { data: [] },
        'GET /api/admin/finance/disputes': { data: [] },
        'GET /api/admin/finance/failed-events': { data: [] },
        'POST /api/admin/finance/refunds': (request) => {
          refundRequest = request.body;
          request.reply({ statusCode: 201, body: { data: { id: 90, status: 'pending' } } });
        },
      },
    });

    cy.contains('Finance operations').should('be.visible');
    cy.contains('pay_demo_30').should('be.visible');
    cy.contains('button', 'Request refund').click();
    cy.fillMuiField('Refund amount (USD)', '5.25');
    cy.fillMuiField('Reason', 'Duplicate subscription charge');
    cy.get('[role="dialog"]').contains('button', 'Submit for approval').click();
    cy.contains('Refund request submitted for independent approval.').should('be.visible');
    cy.then(() => {
      expect(refundRequest).to.include({ verified_payment_id: 30, amount_minor: 525, reason: 'Duplicate subscription charge' });
      expect(refundRequest.idempotency_key).to.be.a('string').and.not.be.empty;
    });
  });

  it('saves a consent-scoped campaign draft', () => {
    let campaignRequest;
    cy.visitAdmin('/dashboard/marketing', {
      permissions: ['admin.access', 'marketing.campaigns.manage'],
      routes: {
        'GET /api/admin/marketing/dashboard': { data: {} },
        'GET /api/admin/marketing/campaigns': { data: [] },
        'GET /api/admin/marketing/segments': { data: {} },
        'POST /api/admin/marketing/campaigns': (request) => {
          campaignRequest = request.body;
          request.reply({ statusCode: 201, body: { data: { id: 20, ...request.body, status: 'draft' } } });
        },
      },
    });

    cy.contains('Marketing operations').should('be.visible');
    cy.contains('[role="tab"]', 'Campaign builder').click();
    cy.fillMuiField('Campaign name', 'Ramadan family collection');
    cy.fillMuiField('Message / campaign description', 'Explore a new family collection this Ramadan.');
    cy.contains('button', 'Save draft').click();
    cy.contains('Draft created (#20).').should('be.visible');
    cy.then(() => {
      expect(campaignRequest).to.include({ name: 'Ramadan family collection', channel: 'email', audience_segment: 'email_consented' });
      expect(campaignRequest.body).to.eq('Explore a new family collection this Ramadan.');
    });
  });

  it('uploads a small media file through session, chunk, and completion APIs', () => {
    const requests = [];
    cy.visitAdmin('/dashboard/media', {
      permissions: ['admin.access', 'media.view', 'media.upload'],
      routes: {
        'GET /api/admin/media/assets': { data: [] },
        'POST /api/admin/media/uploads': (request) => {
          requests.push({ method: request.method, path: new URL(request.url).pathname });
          expect(request.body.file_name).to.eq('sample.mp4');
          request.reply({ statusCode: 201, body: { data: { id: 'upload-session-1', chunk_size: 8 * 1024 * 1024, chunk_count: 1, received_chunks: [] } } });
        },
        'PUT /api/admin/media/uploads/upload-session-1/chunks/0': (request) => {
          requests.push({ method: request.method, path: new URL(request.url).pathname });
          request.reply({ statusCode: 200, body: { data: { received: true } } });
        },
        'POST /api/admin/media/uploads/upload-session-1/complete': (request) => {
          requests.push({ method: request.method, path: new URL(request.url).pathname });
          request.reply({ statusCode: 202, body: { data: { status: 'queued' } } });
        },
      },
    });

    cy.get('h4').filter(':visible').should('contain.text', 'Media library');
    cy.get('input[type="file"][accept*="video"]').selectFile({
      contents: Cypress.Buffer.from('small test video'),
      fileName: 'sample.mp4',
      mimeType: 'video/mp4',
    }, { force: true });
    cy.contains('Upload complete. Transcoding has been queued.').should('be.visible');
    cy.then(() => {
      expect(requests.map((request) => request.method)).to.deep.equal(['POST', 'PUT', 'POST']);
    });
  });
});

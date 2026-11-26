describe('content and technical operations', () => {
  it('approves a submitted catalog revision and refreshes the review queue', () => {
    let pending = true;
    cy.visitAdmin('/dashboard/content/review', {
      permissions: ['admin.access', 'content.review', 'content.approve', 'content.reject', 'content.publish'],
      routes: {
        'GET /api/admin/content/review-queue': () => ({
          data: pending ? [{ id: 501, content_id: 901, revision: 2, status: 'in_review', submitted_at: '2026-11-25T12:00:00Z', content: { name: 'Demo Seerah Series', type: 'tvshow' } }] : [],
        }),
        'POST /api/admin/content/reviews/501/approve': (request) => {
          expect(request.body).to.deep.equal({});
          pending = false;
          request.reply({ statusCode: 200, body: { data: { status: 'approved' } } });
        },
      },
    });

    cy.get('h4').filter(':visible').should('contain.text', 'Content approval');
    cy.contains('Demo Seerah Series').should('be.visible');
    cy.contains('button', /^Approve$/).click();
    cy.contains('Revision approved.').should('be.visible');
    cy.contains('No submissions in this queue.').should('be.visible');
  });

  it('retries an eligible failed job and can create a recorded incident', () => {
    let retryCount = 0;
    let incident;
    cy.visitAdmin('/dashboard/operations', {
      permissions: ['admin.access', 'operations.health.view', 'operations.logs.view', 'operations.jobs.view', 'operations.jobs.retry', 'operations.incidents.manage', 'analytics.playback.view'],
      routes: {
        'GET /api/admin/operations/health': { data: { status: 'ok', services: { application: { status: 'ok' } } } },
        'GET /api/admin/operations/logs': { data: [] },
        'GET /api/admin/operations/jobs': () => ({ data: retryCount ? [] : [{ id: 'job-17', job_type: 'CampaignDelivery', queue: 'notifications', failed_at: '2026-11-25T12:00:00Z', retry_allowed: true }] }),
        'GET /api/admin/operations/incidents': () => ({ data: incident ? [{ id: 81, ...incident, status: 'open', created_at: '2026-11-25T12:00:00Z' }] : [] }),
        'GET /api/admin/operations/playback': { data: { events: [] } },
        'POST /api/admin/operations/jobs/job-17/retry': (request) => {
          expect(request.method).to.eq('POST');
          retryCount += 1;
          request.reply({ statusCode: 202, body: { message: 'Retry queued.' } });
        },
        'POST /api/admin/operations/incidents': (request) => {
          incident = request.body;
          request.reply({ statusCode: 201, body: { data: { id: 81 } } });
        },
      },
    });

    cy.get('h4').filter(':visible').should('contain.text', 'Technical operations');
    cy.contains('CampaignDelivery').should('be.visible');
    cy.contains('button', 'Retry').click();
    cy.contains('No failed Laravel jobs.').should('be.visible');
    cy.fillMuiField('Title', 'Transcoding queue delay');
    cy.fillMuiField('Summary (no secrets or customer credentials)', 'Queue latency increased above the expected threshold.');
    cy.contains('button', 'Create incident').click();
    cy.contains('Transcoding queue delay').should('be.visible');
    cy.then(() => {
      expect(retryCount).to.eq(1);
      expect(incident).to.include({ title: 'Transcoding queue delay', severity: 'medium' });
      expect(incident.affected_services).to.deep.equal([]);
    });
  });
});

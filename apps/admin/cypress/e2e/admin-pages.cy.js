const ADMIN_PAGES = [
  ['/dashboard', 'OTT overview'],
  ['/dashboard/content/review', 'Content approval'],
  ['/dashboard/content/licensing', 'Content licensing'],
  ['/dashboard/media', 'Media library'],
  ['/dashboard/subscriptions', 'Subscription plans'],
  ['/dashboard/finance', 'Finance operations'],
  ['/dashboard/marketing', 'Marketing operations'],
  ['/dashboard/support', 'Customer support'],
  ['/dashboard/analytics', 'OTT analytics'],
  ['/dashboard/operations', 'Technical operations'],
  ['/dashboard/access/roles', 'Administrative roles'],
  ['/dashboard/access/staff', 'Staff access'],
  ['/dashboard/access/permissions', 'Permission catalog'],
  ['/dashboard/access/audit', 'Administrative audit log'],
];

describe('admin portal sections', () => {
  ADMIN_PAGES.forEach(([path, heading]) => {
    it(`loads ${path} for an authorized administrator`, () => {
      cy.visitAdmin(path);
      cy.get('h4', { timeout: 20_000 }).filter(':visible').should('contain.text', heading);
    });
  });
});

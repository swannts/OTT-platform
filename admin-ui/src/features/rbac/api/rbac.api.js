import axios from 'src/utils/axios';

const base = '/api/admin';

export const rbacApi = {
  listRoles: (params) => axios.get(`${base}/roles`, { params }).then((response) => response.data),
  getRole: (id) => axios.get(`${base}/roles/${id}`).then((response) => response.data),
  createRole: (data) => axios.post(`${base}/roles`, data).then((response) => response.data),
  updateRole: (id, data) =>
    axios.put(`${base}/roles/${id}`, data).then((response) => response.data),
  deleteRole: (id) => axios.delete(`${base}/roles/${id}`).then((response) => response.data),
  listPermissions: (params) =>
    axios.get(`${base}/permissions`, { params }).then((response) => response.data),
  listStaff: (params) => axios.get(`${base}/staff`, { params }).then((response) => response.data),
  inviteStaff: (data) =>
    axios.post(`${base}/staff/invitations`, data).then((response) => response.data),
  updateStaffRoles: (id, data) =>
    axios.put(`${base}/staff/${id}/roles`, data).then((response) => response.data),
  updateStaffStatus: (id, data) =>
    axios.patch(`${base}/staff/${id}/status`, data).then((response) => response.data),
  revokeStaffSessions: (id) =>
    axios.post(`${base}/staff/${id}/sessions/revoke`).then((response) => response.data),
  getStaffPermissions: (id) =>
    axios.get(`${base}/staff/${id}/permissions`).then((response) => response.data),
  listAuditLogs: (params) =>
    axios.get(`${base}/audit-logs`, { params }).then((response) => response.data),
  auditExportUrl: (params) => {
    const query = new URLSearchParams(params).toString();
    return `${base}/audit-logs/export${query ? `?${query}` : ''}`;
  },
  acceptInvitation: (data) =>
    axios.post('/api/admin/invitations/accept', data).then((response) => response.data),
  contentReviewQueue: (params) => axios.get(`${base}/content/review-queue`, { params }).then((response) => response.data),
  contentLicenseWindow: (type, id) =>
    axios.get(`${base}/content/${encodeURIComponent(type)}/${encodeURIComponent(id)}/license-window`).then((response) => response.data),
  updateContentLicenseWindow: (type, id, data) =>
    axios.put(`${base}/content/${encodeURIComponent(type)}/${encodeURIComponent(id)}/license-window`, data).then((response) => response.data),
  submitContent: (id) => axios.post(`${base}/content/${encodeURIComponent(id)}/submit`).then((response) => response.data),
  approveContent: (id, data) => axios.post(`${base}/content/reviews/${id}/approve`, data).then((response) => response.data),
  rejectContent: (id, data) => axios.post(`${base}/content/reviews/${id}/reject`, data).then((response) => response.data),
  publishContent: (id) => axios.post(`${base}/content/reviews/${id}/publish`).then((response) => response.data),
  mediaOverview: () => axios.get(`${base}/media/overview`).then((response) => response.data),
  subscriptionsOverview: () => axios.get(`${base}/subscriptions/overview`).then((response) => response.data),
  financeOverview: () => axios.get(`${base}/finance/overview`).then((response) => response.data),
  marketingOverview: () => axios.get(`${base}/marketing/overview`).then((response) => response.data),
  analyticsOverview: () => axios.get(`${base}/analytics/overview`).then((response) => {
    const { audience, subscriptions, content, revenue, playback } = response.data.data;
    return { data: {
      active_accounts: audience?.active_accounts ?? null,
      new_subscribers: subscriptions?.new_subscribers ?? audience?.new_subscribers ?? null,
      watch_hours: content?.watch_hours ?? null,
      paid_payments: revenue?.paid_payment_count ?? null,
      playback_startup_failures: playback?.startup_failures ?? null,
      mrr_by_currency: subscriptions?.mrr_by_currency?.map((item) => `${item.currency} ${item.mrr}`).join(', ') ?? null,
    } };
  }),
  operationsOverview: () => axios.get(`${base}/operations/overview`).then((response) => response.data),
  operationsHealth: () => axios.get(`${base}/operations/health`).then((response) => response.data),
  operationsPlayback: () => axios.get(`${base}/operations/playback`).then((response) => response.data),
  operationsJobs: (params) => axios.get(`${base}/operations/jobs`, { params }).then((response) => response.data),
  retryOperationsJob: (id) => axios.post(`${base}/operations/jobs/${encodeURIComponent(id)}/retry`).then((response) => response.data),
  operationsIncidents: (params) => axios.get(`${base}/operations/incidents`, { params }).then((response) => response.data),
  createOperationsIncident: (data) => axios.post(`${base}/operations/incidents`, data).then((response) => response.data),
  updateOperationsIncident: (id, data) => axios.patch(`${base}/operations/incidents/${id}`, data).then((response) => response.data),
};

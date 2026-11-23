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
};

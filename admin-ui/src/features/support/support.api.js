import axios from 'src/utils/axios';

const API = '/api/admin/support';

export const supportApi = {
  overview: () => axios.get(`${API}/overview`).then((r) => r.data.data),
  customers: (search) => axios.get(`${API}/customers`, { params: { search, per_page: 25 } }).then((r) => r.data),
  customer: (id) => axios.get(`${API}/customers/${id}`).then((r) => r.data.data),
  payments: (id) => axios.get(`${API}/customers/${id}/payments`).then((r) => r.data.data),
  devices: (id) => axios.get(`${API}/customers/${id}/devices`).then((r) => r.data.data),
  playback: (id) => axios.get(`${API}/customers/${id}/playback`).then((r) => r.data.data),
  revoke: (customerId, deviceId) => axios.post(`${API}/customers/${customerId}/devices/${deviceId}/revoke`),
  tickets: (params = {}) => axios.get(`${API}/tickets`, { params }).then((r) => r.data),
  assignees: () => axios.get(`${API}/assignees`).then((r) => r.data.data),
  ticket: (id) => axios.get(`${API}/tickets/${id}`).then((r) => r.data.data),
  createTicket: (data) => axios.post(`${API}/tickets`, data).then((r) => r.data.data),
  updateTicket: (id, data) => axios.patch(`${API}/tickets/${id}`, data).then((r) => r.data.data),
  addNote: (id, note) => axios.post(`${API}/tickets/${id}/notes`, { note }).then((r) => r.data.data),
  escalate: (id, data) => axios.post(`${API}/tickets/${id}/escalate`, data).then((r) => r.data.data),
};

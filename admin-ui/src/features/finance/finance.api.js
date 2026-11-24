import axios from 'src/utils/axios';

const API = '/api/admin/finance';

export const financeApi = {
  overview: () => axios.get(`${API}/dashboard`).then((response) => response.data.data),
  payments: (params) => axios.get(`${API}/payments`, { params }).then((response) => response.data),
  payment: (id) => axios.get(`${API}/payments/${id}`).then((response) => response.data.data),
  refunds: (params) => axios.get(`${API}/refunds`, { params }).then((response) => response.data),
  requestRefund: (data) => axios.post(`${API}/refunds`, data).then((response) => response.data),
  reviewRefund: (id, data) => axios.post(`${API}/refunds/${id}/review`, data).then((response) => response.data),
  executeRefund: (id) => axios.post(`${API}/refunds/${id}/execute`).then((response) => response.data),
  disputes: () => axios.get(`${API}/disputes`).then((response) => response.data),
  failedEvents: () => axios.get(`${API}/failed-events`).then((response) => response.data.data),
  reconciliation: () => axios.get(`${API}/reconciliation`).then((response) => response.data.data),
  reconciliationHistory: () => axios.get(`${API}/reconciliation/history`).then((response) => response.data.data),
  report: (params) => axios.get(`${API}/reports`, { params }).then((response) => response.data.data),
  reconcilePayment: (id) => axios.post(`${API}/payments/${id}/reconcile`).then((response) => response.data.data),
  invoice: (id) => axios.get(`${API}/payments/${id}/invoice`, { responseType: 'blob' }),
  export: (params) => axios.get(`${API}/payments/export`, { params, responseType: 'blob' }),
  exportUrl: (params) => {
    const query = new URLSearchParams(params).toString();
    return `${API}/payments/export${query ? `?${query}` : ''}`;
  },
};

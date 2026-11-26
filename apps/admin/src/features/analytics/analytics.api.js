import axios from 'src/utils/axios';

const API = '/api/admin/analytics';

export const analyticsApi = {
  dashboard: (params) => axios.get(`${API}/overview`, { params }).then((r) => r.data.data),
  reports: () => axios.get(`${API}/reports`).then((r) => r.data.data),
  report: (id) => axios.get(`${API}/reports/${id}`).then((r) => r.data.data),
  scheduleReport: (data) => axios.post(`${API}/reports`, data).then((r) => r.data.data),
  export: (params) => axios.get(`${API}/export`, { params, responseType: 'blob' }).then((r) => r.data),
};

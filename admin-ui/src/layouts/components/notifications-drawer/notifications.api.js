import axios from 'src/utils/axios';

const base = '/api/admin/notifications';

export const notificationsApi = {
  counts: () => axios.get(`${base}/counts`).then((response) => response.data.data),
  list: (params) => axios.get(base, { params }).then((response) => response.data),
  markRead: (id) => axios.patch(`${base}/${encodeURIComponent(id)}/read`).then((response) => response.data),
  markAllRead: () => axios.post(`${base}/mark-all-read`).then((response) => response.data),
  archive: (id) => axios.post(`${base}/${encodeURIComponent(id)}/archive`).then((response) => response.data),
  restore: (id) => axios.post(`${base}/${encodeURIComponent(id)}/restore`).then((response) => response.data),
};

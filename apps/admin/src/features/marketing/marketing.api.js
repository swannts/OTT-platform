import axios from 'src/utils/axios';

const API = '/api/admin/marketing';

export const marketingApi = {
  dashboard: () => axios.get(`${API}/dashboard`).then((response) => response.data.data),
  campaigns: () => axios.get(`${API}/campaigns?per_page=100`).then((response) => response.data.data),
  createCampaign: (data) => axios.post(`${API}/campaigns`, data).then((response) => response.data.data),
  updateCampaign: (id, data) => axios.put(`${API}/campaigns/${id}`, data).then((response) => response.data.data),
  submit: (id) => axios.post(`${API}/campaigns/${id}/submit`).then((response) => response.data.data),
  approve: (id) => axios.post(`${API}/campaigns/${id}/approve`).then((response) => response.data.data),
  reject: (id, data) => axios.post(`${API}/campaigns/${id}/reject`, data).then((response) => response.data.data),
  schedule: (id, data) => axios.post(`${API}/campaigns/${id}/schedule`, data).then((response) => response.data.data),
  pause: (id) => axios.post(`${API}/campaigns/${id}/pause`).then((response) => response.data.data),
  analytics: (id) => axios.get(`${API}/campaigns/${id}/analytics`).then((response) => response.data.data),
  segments: () => axios.get(`${API}/segments`).then((response) => response.data.data),
  banners: () => axios.get(`${API}/banners?per_page=100`).then((response) => response.data.data),
  uploadBannerArtwork: (file) => {
    const body = new FormData();
    body.append('image', file);
    return axios.post(`${API}/banners/artwork`, body).then((response) => response.data.data);
  },
  createBanner: (data) => axios.post(`${API}/banners`, data).then((response) => response.data.data),
  updateBanner: (id, data) => axios.put(`${API}/banners/${id}`, data).then((response) => response.data.data),
  deleteBanner: (id) => axios.delete(`${API}/banners/${id}`).then((response) => response.data.data),
  homepage: () => axios.get(`${API}/homepage`).then((response) => response.data.data),
  homepageCatalog: (search = '') => axios.get(`${API}/homepage/catalog`, { params: { ...(search.trim() ? { search: search.trim() } : {}), per_page: 20 } }).then((response) => response.data.data),
  updateHomepage: (data) => axios.put(`${API}/homepage`, data).then((response) => response.data.data),
};

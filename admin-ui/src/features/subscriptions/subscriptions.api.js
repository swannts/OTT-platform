import axios from 'src/utils/axios';

const API = '/api/admin/subscriptions';

export const subscriptionsApi = {
  plans: (params) => axios.get(`${API}/plans`, { params }).then((response) => response.data),
  createPlan: (data) => axios.post(`${API}/plans`, data).then((response) => response.data),
  updatePlan: (id, data) => axios.put(`${API}/plans/${id}`, data).then((response) => response.data),
  archivePlan: (id) => axios.post(`${API}/plans/${id}/archive`).then((response) => response.data),
  subscribers: (params) => axios.get(`${API}/subscribers`, { params }).then((response) => response.data),
  performance: () => axios.get(`${API}/performance`).then((response) => response.data),
  simulate: (data) => axios.post(`${API}/entitlement-simulation`, data).then((response) => response.data),
  coupons: (params) => axios.get(`${API}/coupons`, { params }).then((response) => response.data),
  createCoupon: (data) => axios.post(`${API}/coupons`, data).then((response) => response.data),
  updateCoupon: (id, data) => axios.put(`${API}/coupons/${id}`, data).then((response) => response.data),
  archiveCoupon: (id) => axios.post(`${API}/coupons/${id}/archive`).then((response) => response.data),
};

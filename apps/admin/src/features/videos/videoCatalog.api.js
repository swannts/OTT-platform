import axios from 'src/utils/axios';

const API = '/api/admin/videos';

export const videoCatalogApi = {
  list: (params) => axios.get(API, { params }).then((response) => response.data),
  create: (data) => axios.post(API, data).then((response) => response.data),
  update: (id, data) => axios.patch(`${API}/${encodeURIComponent(id)}`, data).then((response) => response.data),
  delete: (id) => axios.delete(`${API}/${encodeURIComponent(id)}`).then((response) => response.data),
  uploadArtwork: (file) => {
    const body = new FormData();
    body.append('image', file);
    return axios.post(`${API}/artwork`, body).then((response) => response.data);
  },
};

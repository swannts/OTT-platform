import axios from 'src/utils/axios';

const API = '/api/admin/media';

export const mediaApi = {
  list: (params) => axios.get(`${API}/assets`, { params }).then((response) => response.data),
  quality: (assetId) => axios.get(`${API}/assets/${encodeURIComponent(assetId)}/quality`).then((response) => response.data),
  beginUpload: (data) => axios.post(`${API}/uploads`, data).then((response) => response.data),
  uploadChunk: (sessionId, index, formData) =>
    axios.put(`${API}/uploads/${encodeURIComponent(sessionId)}/chunks/${index}`, formData).then((response) => response.data),
  completeUpload: (sessionId) =>
    axios.post(`${API}/uploads/${encodeURIComponent(sessionId)}/complete`).then((response) => response.data),
  retry: (assetId) => axios.post(`${API}/assets/${encodeURIComponent(assetId)}/retry`).then((response) => response.data),
  addTrack: (assetId, formData) => axios.post(`${API}/assets/${encodeURIComponent(assetId)}/tracks`, formData).then((response) => response.data),
  deleteTrack: (assetId, trackId) => axios.delete(`${API}/assets/${encodeURIComponent(assetId)}/tracks/${trackId}`).then((response) => response.data),
  archive: (assetId) => axios.delete(`${API}/assets/${encodeURIComponent(assetId)}`).then((response) => response.data),
  attachToContent: (assetId, contentId) => axios.put(`${API}/assets/${encodeURIComponent(assetId)}/content`, { content_id: contentId }).then((response) => response.data),
};

import axios from 'src/utils/axios';

const API = '/api/admin/media';

export const mediaApi = {
  list: (params) => axios.get(`${API}/assets`, { params }).then((response) => response.data),
  overview: () => axios.get('/api/admin/media/overview').then((response) => response.data),
  storage: () => axios.get(`${API}/storage`).then((response) => response.data),
  archived: (params) => axios.get(`${API}/archived`, { params }).then((response) => response.data),
  contentOptions: (search) => axios.get(`${API}/content-options`, { params: { search } }).then((response) => response.data),
  processingJobs: (params) => axios.get(`${API}/processing-jobs`, { params }).then((response) => response.data),
  cancelJob: (jobId) => axios.post(`${API}/processing-jobs/${encodeURIComponent(jobId)}/cancel`).then((response) => response.data),
  show: (assetId) => axios.get(`${API}/assets/${encodeURIComponent(assetId)}`).then((response) => response.data),
  update: (assetId, data) => axios.patch(`${API}/assets/${encodeURIComponent(assetId)}`, data).then((response) => response.data),
  preview: (assetId) => axios.get(`${API}/assets/${encodeURIComponent(assetId)}/preview`).then((response) => response.data),
  versions: (assetId) => axios.get(`${API}/assets/${encodeURIComponent(assetId)}/versions`).then((response) => response.data),
  activateVersion: (assetId) => axios.post(`${API}/assets/${encodeURIComponent(assetId)}/versions/activate`).then((response) => response.data),
  rollbackVersion: (assetId, versionId) => axios.post(`${API}/assets/${encodeURIComponent(assetId)}/versions/${encodeURIComponent(versionId)}/rollback`).then((response) => response.data),
  reviewQuality: (assetId, data) => axios.post(`${API}/assets/${encodeURIComponent(assetId)}/quality/review`, data).then((response) => response.data),
  restore: (assetId) => axios.post(`${API}/assets/${encodeURIComponent(assetId)}/restore`).then((response) => response.data),
  quality: (assetId) => axios.get(`${API}/assets/${encodeURIComponent(assetId)}/quality`).then((response) => response.data),
  beginUpload: (data) => axios.post(`${API}/uploads`, data).then((response) => response.data),
  uploadChunk: (sessionId, index, formData, config = {}) =>
    axios.put(`${API}/uploads/${encodeURIComponent(sessionId)}/chunks/${index}`, formData, config).then((response) => response.data),
  cancelUpload: (sessionId) => axios.delete(`${API}/uploads/${encodeURIComponent(sessionId)}`).then((response) => response.data),
  completeUpload: (sessionId) =>
    axios.post(`${API}/uploads/${encodeURIComponent(sessionId)}/complete`).then((response) => response.data),
  retry: (assetId) => axios.post(`${API}/assets/${encodeURIComponent(assetId)}/retry`).then((response) => response.data),
  addTrack: (assetId, formData) => axios.post(`${API}/assets/${encodeURIComponent(assetId)}/tracks`, formData).then((response) => response.data),
  deleteTrack: (assetId, trackId) => axios.delete(`${API}/assets/${encodeURIComponent(assetId)}/tracks/${trackId}`).then((response) => response.data),
  archive: (assetId, reason) => axios.delete(`${API}/assets/${encodeURIComponent(assetId)}`, { data: { reason } }).then((response) => response.data),
  attachToContent: (assetId, contentId, contentType = 'entertainment') => axios.put(`${API}/assets/${encodeURIComponent(assetId)}/content`, { content_id: contentId, content_type: contentType }).then((response) => response.data),
};

'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';

import Alert from '@mui/material/Alert';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import FormControl from '@mui/material/FormControl';
import FormControlLabel from '@mui/material/FormControlLabel';
import Grid from '@mui/material/Grid';
import InputLabel from '@mui/material/InputLabel';
import InputAdornment from '@mui/material/InputAdornment';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import Skeleton from '@mui/material/Skeleton';
import Slider from '@mui/material/Slider';
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TablePagination from '@mui/material/TablePagination';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { Iconify } from 'src/components/iconify';
import { useAuthContext } from 'src/auth/hooks';
import { hasPermission } from 'src/features/rbac/permissions';
import { rbacApi } from 'src/features/rbac/api/rbac.api';
import { videoCatalogApi } from './videoCatalog.api';

const emptyForm = {
  name: '', name_bn: '', short_desc: '', description: '', description_bn: '', poster_url: '',
  poster_tv_url: '', thumbnail_url: '', duration: '', release_date: '', access: 'free', plan_id: '',
  IMDb_rating: '', content_rating: '', is_restricted: false, price: '', available_for: '',
  purchase_type: 'rental', access_duration: '', discount: '',
};

const humanize = (value) => String(value || '—').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

const artworkRatios = [
  { label: 'Website card · 16:10', value: '16:10', ratio: 1.6 },
  { label: 'Poster rail · 2:3', value: '2:3', ratio: 2 / 3 },
  { label: 'Square · 1:1', value: '1:1', ratio: 1 },
  { label: 'Original', value: 'original', ratio: null },
];

function ArtworkField({ label, value, onChange, onUpload, uploading, disabled }) {
  const [failedUrl, setFailedUrl] = useState('');
  const [localCropSource, setLocalCropSource] = useState('');
  const pendingLocalUpload = useRef(false);
  const previousValue = useRef(value);
  const [cropOpen, setCropOpen] = useState(false);
  const [cropRatio, setCropRatio] = useState('16:10');
  const [zoom, setZoom] = useState(1);
  const [focus, setFocus] = useState([50, 50]);
  const [cropBusy, setCropBusy] = useState(false);
  const [cropError, setCropError] = useState('');
  const imageFailed = Boolean(value && failedUrl === value);
  const selectedRatio = artworkRatios.find((ratio) => ratio.value === cropRatio) || artworkRatios[0];

  useEffect(() => () => {
    if (localCropSource) URL.revokeObjectURL(localCropSource);
  }, [localCropSource]);

  useEffect(() => {
    if (previousValue.current === value) return;
    previousValue.current = value;
    if (pendingLocalUpload.current) pendingLocalUpload.current = false;
    else setLocalCropSource('');
  }, [value]);

  const applyCrop = async () => {
    setCropError('');
    setCropBusy(true);
    try {
      const image = new Image();
      image.crossOrigin = 'anonymous';
      await new Promise((resolve, reject) => {
        image.onload = resolve;
        image.onerror = () => reject(new Error('This image could not be loaded for cropping.'));
        image.src = localCropSource || value;
      });

      const sourceRatio = image.naturalWidth / image.naturalHeight;
      const targetRatio = selectedRatio.ratio || sourceRatio;
      const baseWidth = sourceRatio > targetRatio ? image.naturalHeight * targetRatio : image.naturalWidth;
      const baseHeight = sourceRatio > targetRatio ? image.naturalHeight : image.naturalWidth / targetRatio;
      const cropWidth = baseWidth / zoom;
      const cropHeight = baseHeight / zoom;
      const sourceX = (image.naturalWidth - cropWidth) * (focus[0] / 100);
      const sourceY = (image.naturalHeight - cropHeight) * (focus[1] / 100);
      const outputWidth = targetRatio < 1 ? 1000 : 1600;
      const outputHeight = Math.round(outputWidth / targetRatio);
      const canvas = document.createElement('canvas');
      canvas.width = outputWidth;
      canvas.height = outputHeight;
      const context = canvas.getContext('2d');
      if (!context) throw new Error('Image crop is not supported by this browser.');
      context.drawImage(image, sourceX, sourceY, cropWidth, cropHeight, 0, 0, outputWidth, outputHeight);
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
      if (!blob) throw new Error('The cropped image could not be created.');
      const file = new File([blob], `${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-artwork.jpg`, { type: 'image/jpeg' });
      const uploaded = await onUpload(file);
      if (uploaded) {
        setCropOpen(false);
        setLocalCropSource('');
      }
      else setCropError('The cropped image could not be uploaded. Check the page message and try again.');
    } catch (error) {
      setCropError(error.message || 'Cropping failed. For remote images, use an image host that allows browser access or upload the file first.');
    } finally {
      setCropBusy(false);
    }
  };

  return (
    <Grid item xs={12} md={6}>
      <Card variant="outlined" sx={{ p: 1.5, height: '100%', borderRadius: 2 }}>
        <Stack direction="row" spacing={1.5} alignItems="stretch">
          <Stack spacing={1} sx={{ flex: 1, minWidth: 0 }}>
            <Typography variant="subtitle2">{label}</Typography>
            <TextField fullWidth size="small" label="Image URL" value={value ?? ''} onChange={(event) => { pendingLocalUpload.current = false; setFailedUrl(''); setLocalCropSource(''); onChange(event); }} />
            <Stack direction="row" alignItems="center" spacing={1} flexWrap="wrap" useFlexGap>
              <Button component="label" size="small" variant="outlined" disabled={disabled || uploading}>
                {uploading ? 'Uploading…' : 'Upload image'}
                <input hidden type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => {
                  const file = event.currentTarget.files?.[0];
                  if (file) {
                    setLocalCropSource(URL.createObjectURL(file));
                    pendingLocalUpload.current = true;
                    setFailedUrl('');
                    onUpload(file).then((uploaded) => {
                      if (!uploaded) {
                        pendingLocalUpload.current = false;
                        setLocalCropSource('');
                      }
                    });
                  }
                  event.currentTarget.value = '';
                }} />
              </Button>
              <Button size="small" startIcon={<Iconify icon="solar:crop-minimalistic-line-duotone" />} disabled={!value || imageFailed || disabled || uploading} onClick={() => { setCropError(''); setCropOpen(true); }}>Adjust for visitor site</Button>
            </Stack>
            <Typography variant="caption" color="text.secondary">Landscape cards and portrait rails use different crops.</Typography>
          </Stack>
          <Box sx={{ width: { xs: 96, sm: 112 }, aspectRatio: '16 / 10', flexShrink: 0, alignSelf: 'center', display: 'grid', placeItems: 'center', overflow: 'hidden', bgcolor: 'action.hover', borderRadius: 1.5, border: 1, borderColor: 'divider' }}>
            {(localCropSource || value) && !imageFailed ? <Box component="img" src={localCropSource || value} alt={`${label} preview`} onError={() => setFailedUrl(value)} sx={{ display: 'block', width: '100%', height: '100%', objectFit: 'cover' }} /> : <Typography variant="caption" color="text.secondary" textAlign="center" sx={{ px: 1 }}>{imageFailed ? 'Image unavailable' : 'No image'}</Typography>}
          </Box>
        </Stack>
      </Card>

      <Dialog open={cropOpen} onClose={() => !cropBusy && setCropOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Adjust {label.toLowerCase()} for visitor cards</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            <Typography variant="body2" color="text.secondary">Choose a real website placement, then position and zoom the image. Applying uploads a new cropped image to this draft.</Typography>
            <FormControl fullWidth size="small">
              <InputLabel>Visitor placement</InputLabel>
              <Select label="Visitor placement" value={cropRatio} onChange={(event) => setCropRatio(event.target.value)}>
                {artworkRatios.map((ratio) => <MenuItem key={ratio.value} value={ratio.value}>{ratio.label}</MenuItem>)}
              </Select>
            </FormControl>
            <Box sx={{ width: '100%', maxHeight: 280, aspectRatio: selectedRatio.ratio || 'auto', overflow: 'hidden', bgcolor: 'grey.900', borderRadius: 2, position: 'relative', mx: 'auto' }}>
              <Box component="img" src={localCropSource || value} alt={`${label} crop preview`} sx={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', objectPosition: `${focus[0]}% ${focus[1]}%`, transform: `scale(${zoom})`, transformOrigin: `${focus[0]}% ${focus[1]}%` }} />
            </Box>
            <Box>
              <Stack direction="row" justifyContent="space-between"><Typography variant="caption">Zoom</Typography><Typography variant="caption" color="text.secondary">{zoom.toFixed(1)}×</Typography></Stack>
              <Slider size="small" min={1} max={3} step={0.1} value={zoom} onChange={(_, next) => setZoom(next)} aria-label="Crop zoom" />
            </Box>
            <Box>
              <Stack direction="row" justifyContent="space-between"><Typography variant="caption">Horizontal position</Typography><Typography variant="caption" color="text.secondary">{focus[0]}%</Typography></Stack>
              <Slider size="small" value={focus[0]} onChange={(_, next) => setFocus(([ , y]) => [next, y])} aria-label="Crop horizontal position" />
            </Box>
            <Box>
              <Stack direction="row" justifyContent="space-between"><Typography variant="caption">Vertical position</Typography><Typography variant="caption" color="text.secondary">{focus[1]}%</Typography></Stack>
              <Slider size="small" value={focus[1]} onChange={(_, next) => setFocus(([x]) => [x, next])} aria-label="Crop vertical position" />
            </Box>
            {cropError && <Alert severity="error">{cropError}</Alert>}
          </Stack>
        </DialogContent>
        <DialogActions sx={{ p: 2 }}>
          <Button onClick={() => setCropOpen(false)} disabled={cropBusy}>Cancel</Button>
          <Button variant="contained" onClick={applyCrop} disabled={cropBusy || uploading}>{cropBusy ? 'Applying crop…' : 'Apply crop'}</Button>
        </DialogActions>
      </Dialog>
    </Grid>
  );
}

export function VideoCatalogPage() {
  const { user } = useAuthContext();
  const canCreate = hasPermission(user, 'content.create');
  const canUpdate = hasPermission(user, 'content.update');
  const canDelete = hasPermission(user, 'content.delete');
  const canSubmit = hasPermission(user, 'content.submit');
  const canAccessMedia = hasPermission(user, 'media.view') || hasPermission(user, 'media.upload');
  const [videos, setVideos] = useState([]);
  const [plans, setPlans] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
  const [filters, setFilters] = useState({ search: '', status: '', access: '', media_source: '', artwork: '', review_status: '', sort_by: '' });
  const [applied, setApplied] = useState({});
  const [filterDialogOpen, setFilterDialogOpen] = useState(false);
  const [page, setPage] = useState(0);
  const [pageSize, setPageSize] = useState(25);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [editorOpen, setEditorOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const [artworkUploading, setArtworkUploading] = useState({});
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await videoCatalogApi.list({ ...applied, page: page + 1, per_page: pageSize });
      setVideos(response.data || []);
      setPlans(response.plans || []);
      setMeta(response.meta || { current_page: page + 1, last_page: 1, per_page: pageSize, total: 0 });
    } catch (cause) {
      setError(cause.response?.data?.message || 'The video catalog could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [applied, page, pageSize]);

  useEffect(() => { load(); }, [load]);

  const openCreate = () => { setEditing(null); setForm(emptyForm); setEditorOpen(true); };
  const openEdit = (video) => {
    if (video.status !== 'draft') return;
    setEditing(video);
    const nextForm = { ...emptyForm, ...video };
    Object.keys(emptyForm).forEach((field) => {
      if (typeof emptyForm[field] === 'string') nextForm[field] = video[field] == null ? emptyForm[field] : String(video[field]);
    });
    nextForm.access ||= 'free';
    nextForm.is_restricted = Boolean(video.is_restricted);
    setForm(nextForm);
    setEditorOpen(true);
  };

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');
    const payload = { ...form, plan_id: form.plan_id || null, price: form.price || null, available_for: form.available_for || null, access_duration: form.access_duration || null, discount: form.discount || null, purchase_type: form.access === 'pay-per-view' ? form.purchase_type : null, IMDb_rating: form.IMDb_rating || null };
    try {
      if (editing) await videoCatalogApi.update(editing.id, payload);
      else await videoCatalogApi.create(payload);
      setEditorOpen(false);
      setNotice(editing ? 'Draft video updated.' : 'Video draft created. Add its artwork and media asset, then submit it for review.');
      if (!editing) setPage(0);
      await load();
    } catch (cause) {
      setError(cause.response?.data?.message || 'The video draft could not be saved.');
    } finally {
      setSaving(false);
    }
  };

  const submitForReview = async (video) => {
    setError('');
    setNotice('');
    try {
      await rbacApi.submitContent(video.id, { content_type: 'video' });
      setNotice(`“${video.name}” was submitted for review.`);
      await load();
    } catch (cause) {
      setError(cause.response?.data?.message || 'This video could not be submitted for review.');
    }
  };

  const deleteVideo = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    setError('');
    setNotice('');
    try {
      await videoCatalogApi.delete(deleteTarget.id);
      setDeleteTarget(null);
      setNotice(`“${deleteTarget.name || 'Untitled video'}” was deleted. Its media asset remains in the media library.`);
      if (videos.length === 1 && page > 0) setPage((current) => current - 1);
      else await load();
    } catch (cause) {
      setError(cause.response?.data?.message || 'This video could not be deleted. It may be published or awaiting review.');
    } finally {
      setDeleting(false);
    }
  };

  const updateForm = (name) => (event) => setForm((current) => ({ ...current, [name]: event.target.value }));
  const updateFilter = (name) => (event) => setFilters((current) => ({ ...current, [name]: event.target.value }));
  const uploadArtwork = async (field, file) => {
    setError('');
    setNotice('');
    setArtworkUploading((current) => ({ ...current, [field]: true }));
    try {
      const response = await videoCatalogApi.uploadArtwork(file);
      setForm((current) => ({ ...current, [field]: response.data.url }));
      setNotice(`${humanize(field)} uploaded. Save the draft to keep this image.`);
      return true;
    } catch (cause) {
      setError(cause.response?.data?.message || 'The artwork image could not be uploaded.');
      return false;
    } finally {
      setArtworkUploading((current) => ({ ...current, [field]: false }));
    }
  };
  const updateAccess = (event) => setForm((current) => ({
    ...current,
    access: event.target.value,
    ...(event.target.value === 'paid' ? {} : { plan_id: '' }),
    ...(event.target.value === 'pay-per-view' ? {} : { price: '', available_for: '', purchase_type: 'rental', access_duration: '', discount: '' }),
  }));
  const applyFilters = () => {
    setPage(0);
    setApplied(Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')));
    setFilterDialogOpen(false);
  };
  const artworkBusy = Object.values(artworkUploading).some(Boolean);
  const activeFilterEntries = Object.entries(applied).filter(([, value]) => Boolean(value));
  const activeFilters = activeFilterEntries.filter(([key]) => key !== 'search').length;
  const clearAppliedFilter = (name) => {
    setApplied((current) => {
      const next = { ...current };
      delete next[name];
      return next;
    });
    setFilters((current) => ({ ...current, [name]: '' }));
    setPage(0);
  };
  const discardFilterChanges = () => {
    setFilters({
      search: applied.search || '', status: applied.status || '', access: applied.access || '',
      media_source: applied.media_source || '', artwork: applied.artwork || '',
      review_status: applied.review_status || '', sort_by: applied.sort_by || '',
    });
    setFilterDialogOpen(false);
  };
  const filterLabels = {
    search: 'Search', status: 'Status', access: 'Access', media_source: 'Media source',
    artwork: 'Artwork', review_status: 'Review', sort_by: 'Sort',
  };
  const filterValueLabels = {
    draft: 'Draft', published: 'Published', free: 'Free', paid: 'Subscription',
    'pay-per-view': 'Pay per view', linked: 'Linked asset', missing: 'Missing',
    complete: 'Complete', not_submitted: 'Not submitted', in_review: 'In review',
    approved: 'Approved', rejected: 'Rejected', recently_updated: 'Recently updated',
    oldest_updated: 'Oldest updated', title_asc: 'Title: A to Z', title_desc: 'Title: Z to A',
    release_newest: 'Release: newest', release_oldest: 'Release: oldest',
  };

  return (
    <DashboardContent>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" alignItems={{ md: 'center' }} spacing={1.5}>
          <Box>
            <Typography variant="h4">Video catalog</Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>Create and manage standalone video drafts, access rules, artwork, and review submissions.</Typography>
          </Box>
          <Stack direction="row" spacing={1}>
            {canAccessMedia && <Button component={Link} href="/dashboard/media" variant="outlined" startIcon={<Iconify icon="solar:folder-with-files-bold" />}>Media library</Button>}
            {canCreate && <Button variant="contained" onClick={openCreate} startIcon={<Iconify icon="mingcute:add-line" />}>New video</Button>}
          </Stack>
        </Stack>

        {error && <Alert severity="error" onClose={() => setError('')}>{error}</Alert>}
        {notice && <Alert severity="success" onClose={() => setNotice('')}>{notice}</Alert>}
        <Alert severity="info">For playback, upload the source in Media library, open its asset details, and attach this draft video. Reviewers can approve it after artwork and playback are ready. If the media workspace is unavailable to your role, ask an administrator for access.</Alert>

        <Grid container spacing={1.5}>
          {[
            ['All videos', meta.total],
            ['Drafts on this page', videos.filter((video) => video.status === 'draft').length],
            ['Published on this page', videos.filter((video) => video.status === 'published').length],
            ['Missing artwork on this page', videos.filter((video) => !video.poster_url && !video.thumbnail_url).length],
          ].map(([label, value]) => <Grid item xs={6} md={3} key={label}><Card><CardContent sx={{ py: 2 }}><Typography variant="body2" color="text.secondary">{label}</Typography><Typography variant="h4">{loading ? '—' : Number(value).toLocaleString()}</Typography></CardContent></Card></Grid>)}
        </Grid>

        <Card sx={{ borderRadius: 2.5 }}>
          <CardContent sx={{ p: { xs: 1.5, sm: 2 }, '&:last-child': { pb: { xs: 1.5, sm: 2 } } }}>
            <Stack spacing={activeFilterEntries.length ? 1.5 : 0}>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25}>
                <TextField
                  fullWidth
                  size="small"
                  placeholder="Search videos by title or slug"
                  value={filters.search}
                  onChange={updateFilter('search')}
                  onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); applyFilters(); } }}
                  InputProps={{ startAdornment: <InputAdornment position="start"><Iconify icon="solar:magnifer-linear" width={19} /></InputAdornment> }}
                />
                <Stack direction="row" spacing={1} flexShrink={0}>
                  <Button variant="outlined" onClick={() => setFilterDialogOpen(true)} startIcon={<Iconify icon="solar:filter-bold" />}>
                    Filters{activeFilters ? ` (${activeFilters})` : ''}
                  </Button>
                  <Button variant="contained" onClick={applyFilters} startIcon={<Iconify icon="solar:magnifer-bold" />}>Search</Button>
                </Stack>
              </Stack>

              {activeFilterEntries.length > 0 && <Stack direction="row" spacing={0.75} useFlexGap flexWrap="wrap" alignItems="center">
                <Typography variant="caption" color="text.secondary" sx={{ mr: 0.25 }}>Applied</Typography>
                {activeFilterEntries.map(([key, value]) => <Chip key={key} size="small" variant="outlined" label={`${filterLabels[key]}: ${filterValueLabels[value] || value}`} onDelete={() => clearAppliedFilter(key)} />)}
                <Button size="small" onClick={() => { setFilters({ search: '', status: '', access: '', media_source: '', artwork: '', review_status: '', sort_by: '' }); setApplied({}); setPage(0); }}>Clear all</Button>
              </Stack>}
            </Stack>
          </CardContent>
        </Card>

        <Dialog open={filterDialogOpen} onClose={discardFilterChanges} fullWidth maxWidth="sm">
          <DialogTitle sx={{ pb: 0.5 }}>Filter videos</DialogTitle>
          <DialogContent>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>Choose the catalog details you want to narrow down.</Typography>
            <Grid container spacing={1.5}>
              <Grid item xs={12} sm={6}><FormControl fullWidth size="small"><InputLabel>Status</InputLabel><Select label="Status" value={filters.status} onChange={updateFilter('status')}><MenuItem value="">All statuses</MenuItem><MenuItem value="draft">Draft</MenuItem><MenuItem value="published">Published</MenuItem></Select></FormControl></Grid>
              <Grid item xs={12} sm={6}><FormControl fullWidth size="small"><InputLabel>Access</InputLabel><Select label="Access" value={filters.access} onChange={updateFilter('access')}><MenuItem value="">All access types</MenuItem><MenuItem value="free">Free</MenuItem><MenuItem value="paid">Subscription</MenuItem><MenuItem value="pay-per-view">Pay per view</MenuItem></Select></FormControl></Grid>
              <Grid item xs={12} sm={6}><FormControl fullWidth size="small"><InputLabel>Review status</InputLabel><Select label="Review status" value={filters.review_status} onChange={updateFilter('review_status')}><MenuItem value="">Any review state</MenuItem><MenuItem value="not_submitted">Not submitted</MenuItem><MenuItem value="in_review">In review</MenuItem><MenuItem value="approved">Approved</MenuItem><MenuItem value="rejected">Rejected</MenuItem></Select></FormControl></Grid>
              <Grid item xs={12} sm={6}><FormControl fullWidth size="small"><InputLabel>Media source</InputLabel><Select label="Media source" value={filters.media_source} onChange={updateFilter('media_source')}><MenuItem value="">Any source</MenuItem><MenuItem value="linked">Linked asset</MenuItem><MenuItem value="missing">No asset linked</MenuItem></Select></FormControl></Grid>
              <Grid item xs={12} sm={6}><FormControl fullWidth size="small"><InputLabel>Artwork</InputLabel><Select label="Artwork" value={filters.artwork} onChange={updateFilter('artwork')}><MenuItem value="">Any artwork</MenuItem><MenuItem value="complete">Has artwork</MenuItem><MenuItem value="missing">Missing artwork</MenuItem></Select></FormControl></Grid>
              <Grid item xs={12} sm={6}><FormControl fullWidth size="small"><InputLabel>Sort by</InputLabel><Select label="Sort by" value={filters.sort_by} onChange={updateFilter('sort_by')}><MenuItem value="">Recently updated</MenuItem><MenuItem value="oldest_updated">Oldest updated</MenuItem><MenuItem value="title_asc">Title: A to Z</MenuItem><MenuItem value="title_desc">Title: Z to A</MenuItem><MenuItem value="release_newest">Release date: newest</MenuItem><MenuItem value="release_oldest">Release date: oldest</MenuItem></Select></FormControl></Grid>
            </Grid>
          </DialogContent>
          <DialogActions sx={{ px: 3, pb: 2.5 }}>
            <Button onClick={() => { setFilters({ search: '', status: '', access: '', media_source: '', artwork: '', review_status: '', sort_by: '' }); setApplied({}); setPage(0); setFilterDialogOpen(false); }}>Clear filters</Button>
            <Box sx={{ flex: 1 }} />
            <Button onClick={discardFilterChanges}>Cancel</Button>
            <Button variant="contained" onClick={applyFilters}>Apply filters</Button>
          </DialogActions>
        </Dialog>

        {loading ? <Stack spacing={1}>{[0, 1, 2, 3].map((row) => <Skeleton key={row} height={68} variant="rounded" />)}</Stack> : (
          <Card>
            <TableContainer>
              <Table aria-label="Video catalog">
                <TableHead><TableRow><TableCell>Video</TableCell><TableCell>Access</TableCell><TableCell>Release</TableCell><TableCell>Media source</TableCell><TableCell>Status</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead>
                <TableBody>
                  {videos.map((video) => (
                    <TableRow key={video.id} hover>
                      <TableCell><Stack direction="row" spacing={1.25} alignItems="center"><Avatar variant="rounded" src={video.poster_url || video.thumbnail_url || undefined} alt="" sx={{ width: 52, height: 64, bgcolor: 'action.hover' }}><Iconify icon="solar:videocamera-record-bold" /></Avatar><Box sx={{ minWidth: 0 }}><Typography variant="subtitle2" noWrap>{video.name || 'Untitled video'}</Typography>{video.name_bn && <Typography variant="caption" color="text.secondary" display="block" noWrap>{video.name_bn}</Typography>}<Typography variant="caption" color="text.secondary">ID {video.id} · {video.duration || 'Duration not set'}</Typography></Box></Stack></TableCell>
                      <TableCell><Typography variant="body2">{humanize(video.access)}</Typography>{video.access === 'paid' && <Typography variant="caption" color="text.secondary">{video.plan?.name || 'Plan not assigned'}</Typography>}{video.access === 'pay-per-view' && <Typography variant="caption" color="text.secondary">{video.price ? `${video.price} · ${humanize(video.purchase_type)} · ${video.purchase_type === 'rental' ? `${video.access_duration || '—'} rental days` : `${video.available_for || '—'} days available`}` : 'Price not set'}</Typography>}</TableCell>
                      <TableCell>{video.release_date || 'Unscheduled'}</TableCell>
                      <TableCell>{video.media_asset_id ? <Button size="small" component={Link} href={`/dashboard/media/assets/${video.media_asset_id}`}>Asset #{video.media_asset_id}</Button> : <Typography variant="caption" color="text.secondary">No asset linked</Typography>}</TableCell>
                      <TableCell><Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap><Chip size="small" color={video.status === 'published' ? 'success' : 'default'} label={humanize(video.status)} />{video.review_status && <Chip size="small" variant="outlined" color={video.review_status === 'rejected' ? 'error' : video.review_status === 'approved' ? 'success' : 'warning'} label={humanize(video.review_status)} />}</Stack></TableCell>
                      <TableCell align="right"><Stack direction="row" justifyContent="flex-end" spacing={0.5}>
                        {video.status === 'draft' && !['in_review', 'approved'].includes(video.review_status) && canUpdate && <Button size="small" onClick={() => openEdit(video)}>Edit</Button>}
                        {video.status === 'draft' && !['in_review', 'approved'].includes(video.review_status) && canSubmit && <Button size="small" variant="outlined" onClick={() => submitForReview(video)}>Submit for review</Button>}
                        {video.status === 'draft' && !['in_review', 'approved'].includes(video.review_status) && canDelete && <Button size="small" color="error" onClick={() => setDeleteTarget(video)}>Delete</Button>}
                      </Stack></TableCell>
                    </TableRow>
                  ))}
                  {!videos.length && <TableRow><TableCell colSpan={6}><Stack alignItems="center" spacing={1} sx={{ py: 5 }}><Iconify icon="solar:videocamera-record-bold-duotone" width={40} /><Typography variant="subtitle1">No videos found</Typography><Typography variant="body2" color="text.secondary">Try clearing filters or create a new video draft.</Typography>{canCreate && <Button onClick={openCreate}>Create video draft</Button>}</Stack></TableCell></TableRow>}
                </TableBody>
              </Table>
            </TableContainer>
            <TablePagination component="div" count={meta.total || 0} page={page} rowsPerPage={pageSize} rowsPerPageOptions={[10, 25, 50, 100]} onPageChange={(_, value) => setPage(value)} onRowsPerPageChange={(event) => { setPageSize(Number(event.target.value)); setPage(0); }} />
          </Card>
        )}
      </Stack>

      <Dialog open={editorOpen} onClose={() => !saving && !artworkBusy && setEditorOpen(false)} fullWidth maxWidth="md">
        <Box component="form" onSubmit={save}>
          <DialogTitle>{editing ? 'Edit video draft' : 'Create video draft'}</DialogTitle>
          <DialogContent>
            <Stack spacing={2} sx={{ pt: 1 }}>
              <Alert severity="info">This editor saves drafts only. Reviewers approve submissions and publishers control release; the form cannot publish a video directly.</Alert>
              <Grid container spacing={1.5}>
                <Grid item xs={12} md={6}><TextField required fullWidth label="Title" value={form.name} onChange={updateForm('name')} inputProps={{ maxLength: 255 }} /></Grid>
                <Grid item xs={12} md={6}><TextField fullWidth label="Title in Bangla" value={form.name_bn} onChange={updateForm('name_bn')} inputProps={{ maxLength: 255 }} /></Grid>
                <Grid item xs={12}><TextField fullWidth label="Short description" value={form.short_desc} onChange={updateForm('short_desc')} multiline minRows={2} inputProps={{ maxLength: 1000 }} /></Grid>
                <Grid item xs={12} md={6}><TextField fullWidth label="Description" value={form.description} onChange={updateForm('description')} multiline minRows={4} /></Grid>
                <Grid item xs={12} md={6}><TextField fullWidth label="Description in Bangla" value={form.description_bn} onChange={updateForm('description_bn')} multiline minRows={4} /></Grid>
                <ArtworkField label="Poster" value={form.poster_url} onChange={updateForm('poster_url')} onUpload={(file) => uploadArtwork('poster_url', file)} uploading={artworkUploading.poster_url} disabled={editing ? !canUpdate : !canCreate} />
                <ArtworkField label="Thumbnail" value={form.thumbnail_url} onChange={updateForm('thumbnail_url')} onUpload={(file) => uploadArtwork('thumbnail_url', file)} uploading={artworkUploading.thumbnail_url} disabled={editing ? !canUpdate : !canCreate} />
                <ArtworkField label="TV poster" value={form.poster_tv_url} onChange={updateForm('poster_tv_url')} onUpload={(file) => uploadArtwork('poster_tv_url', file)} uploading={artworkUploading.poster_tv_url} disabled={editing ? !canUpdate : !canCreate} />
                <Grid item xs={6} md={3}><TextField fullWidth label="Duration" placeholder="00:42:00" value={form.duration} onChange={updateForm('duration')} /></Grid>
                <Grid item xs={6} md={3}><TextField fullWidth type="date" label="Release date" InputLabelProps={{ shrink: true }} value={form.release_date} onChange={updateForm('release_date')} /></Grid>
                <Grid item xs={12} md={4}><FormControl fullWidth><InputLabel>Access</InputLabel><Select label="Access" value={form.access} onChange={updateAccess}><MenuItem value="free">Free</MenuItem><MenuItem value="paid">Subscription plan</MenuItem><MenuItem value="pay-per-view">Pay per view</MenuItem></Select></FormControl></Grid>
                {form.access === 'paid' && <Grid item xs={12} md={4}><FormControl fullWidth required><InputLabel>Subscription plan</InputLabel><Select label="Subscription plan" value={form.plan_id} onChange={updateForm('plan_id')}><MenuItem value="">Choose plan</MenuItem>{plans.map((plan) => <MenuItem key={plan.id} value={plan.id}>{plan.name} · Level {plan.level}</MenuItem>)}</Select></FormControl></Grid>}
                {form.access === 'pay-per-view' && <>
                  <Grid item xs={12} md={4}><FormControl fullWidth required><InputLabel>Purchase type</InputLabel><Select label="Purchase type" value={form.purchase_type} onChange={updateForm('purchase_type')}><MenuItem value="rental">Rental</MenuItem><MenuItem value="onetime">One-time purchase</MenuItem></Select></FormControl></Grid>
                  <Grid item xs={6} md={4}><TextField fullWidth required type="number" label="Price" value={form.price} onChange={updateForm('price')} inputProps={{ min: 0, step: '0.01' }} /></Grid>
                  <Grid item xs={6} md={4}><TextField fullWidth required type="number" label="Availability window (days)" value={form.available_for} onChange={updateForm('available_for')} inputProps={{ min: 1, max: 3650 }} /></Grid>
                  {form.purchase_type === 'rental' && <Grid item xs={6} md={4}><TextField fullWidth required type="number" label="Rental access (days)" value={form.access_duration} onChange={updateForm('access_duration')} inputProps={{ min: 1, max: 3650 }} /></Grid>}
                  <Grid item xs={6} md={4}><TextField fullWidth type="number" label="Discount (%)" value={form.discount} onChange={updateForm('discount')} inputProps={{ min: 0, max: 99, step: '0.01' }} /></Grid>
                </>}
                <Grid item xs={6} md={3}><TextField fullWidth type="number" label="IMDb rating" value={form.IMDb_rating} onChange={updateForm('IMDb_rating')} inputProps={{ min: 0, max: 10, step: '0.1' }} /></Grid>
                <Grid item xs={6} md={3}><TextField fullWidth label="Content rating" value={form.content_rating} onChange={updateForm('content_rating')} /></Grid>
                <Grid item xs={12}><FormControlLabel control={<Switch checked={Boolean(form.is_restricted)} onChange={(event) => setForm((current) => ({ ...current, is_restricted: event.target.checked }))} />} label="Restrict this video from child profiles" /></Grid>
              </Grid>
              {form.access === 'paid' && <Typography variant="caption" color="text.secondary">If you need the plan ID, open Subscriptions in another tab and use the plan level shown there.</Typography>}
            </Stack>
          </DialogContent>
          <DialogActions sx={{ px: 3, pb: 2.5 }}><Button onClick={() => setEditorOpen(false)} disabled={saving || artworkBusy}>Cancel</Button><Button type="submit" variant="contained" disabled={saving || artworkBusy || !canCreate && !editing}>{saving ? 'Saving…' : editing ? 'Save draft' : 'Create draft'}</Button></DialogActions>
        </Box>
      </Dialog>

      <Dialog open={Boolean(deleteTarget)} onClose={() => !deleting && setDeleteTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>Delete video draft?</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="text.secondary">
            This will remove <strong>{deleteTarget?.name || 'this video'}</strong> from the catalog. The video record is archived for recovery, and its linked media asset is retained.
          </Typography>
        </DialogContent>
        <DialogActions sx={{ p: 2 }}>
          <Button onClick={() => setDeleteTarget(null)} disabled={deleting}>Cancel</Button>
          <Button color="error" variant="contained" onClick={deleteVideo} disabled={deleting}>{deleting ? 'Deleting…' : 'Delete draft'}</Button>
        </DialogActions>
      </Dialog>
    </DashboardContent>
  );
}

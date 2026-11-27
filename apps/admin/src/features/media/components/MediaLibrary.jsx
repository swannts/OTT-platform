'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';

import Alert from '@mui/material/Alert';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import Checkbox from '@mui/material/Checkbox';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import FormControl from '@mui/material/FormControl';
import IconButton from '@mui/material/IconButton';
import InputLabel from '@mui/material/InputLabel';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TablePagination from '@mui/material/TablePagination';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import Typography from '@mui/material/Typography';
import Chip from '@mui/material/Chip';

import { Iconify } from 'src/components/iconify';
import { mediaApi } from '../media.api';

const CATEGORIES = ['movie', 'episode', 'trailer', 'teaser', 'standalone_video', 'promotional_video', 'video', 'audio', 'subtitle', 'poster', 'backdrop', 'thumbnail', 'artwork', 'other'];
const STATUSES = ['uploaded', 'queued', 'processing', 'ready', 'failed'];
const QUALITY = ['pending_inspection', 'technical_failed', 'awaiting_manual_review', 'approved', 'rejected', 'reprocessing_required'];

function formatBytes(value) {
  const bytes = Number(value || 0);
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
  if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
  return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
}

function humanize(value) { return String(value || '—').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()); }

export function MediaLibrary({ canDelete, canRetry, onRefresh }) {
  const [filters, setFilters] = useState({ search: '', status: '', category: '', quality_status: '', language: '', attached: '', uploaded_from: '', uploaded_to: '', sort: 'created_at', sort_dir: 'desc' });
  const [applied, setApplied] = useState({});
  const [assets, setAssets] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
  const [page, setPage] = useState(0);
  const [pageSize, setPageSize] = useState(25);
  const [view, setView] = useState('table');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [selected, setSelected] = useState([]);
  const [menu, setMenu] = useState({ anchor: null, asset: null });
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [archiveReason, setArchiveReason] = useState('');

  const load = useCallback(async (nextPage = page, nextPageSize = pageSize, nextFilters = applied) => {
    setLoading(true);
    setError('');
    const params = Object.fromEntries(Object.entries({ ...nextFilters, page: nextPage + 1, per_page: nextPageSize }).filter(([, value]) => value !== '' && value != null));
    try {
      const response = await mediaApi.list(params);
      setAssets(response.data || []);
      setMeta(response.meta || { current_page: nextPage + 1, last_page: 1, per_page: nextPageSize, total: 0 });
      setSelected([]);
    } catch (cause) {
      setError(cause.response?.data?.message || 'The media library could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [applied, page, pageSize]);

  useEffect(() => { load(); }, [load]);

  const applyFilters = (event) => {
    event.preventDefault();
    setPage(0);
    setApplied(Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')));
  };

  const archiveSelected = async () => {
    setConfirmOpen(false);
    setLoading(true);
    const results = await Promise.allSettled(selected.map((id) => mediaApi.archive(id, archiveReason)));
    const archivedCount = results.filter((result) => result.status === 'fulfilled').length;
    const blockedCount = results.length - archivedCount;
    setNotice(`${archivedCount} asset${archivedCount === 1 ? '' : 's'} archived${blockedCount ? `; ${blockedCount} blocked by permissions or published-content dependencies` : ''}.`);
    setArchiveReason('');
    await load(page, pageSize, applied);
    onRefresh?.();
  };

  const retry = async (asset) => {
    try {
      const response = await mediaApi.retry(asset.id);
      setNotice(response.data?.queue_available === false ? 'Retry was not accepted by the processing queue.' : `Processing retry queued for ${asset.display_name || asset.original_name}.`);
      await load();
      onRefresh?.();
    } catch (cause) { setError(cause.response?.data?.message || 'Processing retry could not be queued.'); }
  };

  const toggleSelected = (id) => setSelected((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
  const allSelected = assets.length > 0 && assets.every((asset) => selected.includes(asset.id));
  const toggleAll = () => setSelected(allSelected ? [] : assets.map((asset) => asset.id));

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error" onClose={() => setError('')}>{error}</Alert>}
      {notice && <Alert severity="info" onClose={() => setNotice('')}>{notice}</Alert>}
      <Card component="form" onSubmit={applyFilters}><CardContent>
        <Stack spacing={2}>
          <Stack direction={{ xs: 'column', lg: 'row' }} spacing={1.25}>
            <TextField label="Search title, filename, or content" size="small" value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} sx={{ flex: 2, minWidth: 220 }} />
            <FormControl size="small" sx={{ minWidth: 155 }}><InputLabel>Status</InputLabel><Select label="Status" value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}><MenuItem value="">All statuses</MenuItem>{STATUSES.map((value) => <MenuItem key={value} value={value}>{humanize(value)}</MenuItem>)}</Select></FormControl>
            <FormControl size="small" sx={{ minWidth: 170 }}><InputLabel>Category</InputLabel><Select label="Category" value={filters.category} onChange={(event) => setFilters({ ...filters, category: event.target.value })}><MenuItem value="">All categories</MenuItem>{CATEGORIES.map((value) => <MenuItem key={value} value={value}>{humanize(value)}</MenuItem>)}</Select></FormControl>
            <FormControl size="small" sx={{ minWidth: 190 }}><InputLabel>Quality control</InputLabel><Select label="Quality control" value={filters.quality_status} onChange={(event) => setFilters({ ...filters, quality_status: event.target.value })}><MenuItem value="">All QC states</MenuItem>{QUALITY.map((value) => <MenuItem key={value} value={value}>{humanize(value)}</MenuItem>)}</Select></FormControl>
            <Button type="submit" variant="contained" startIcon={<Iconify icon="solar:magnifer-bold" />}>Search</Button>
          </Stack>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.25} alignItems={{ md: 'center' }}>
            <TextField label="Track language" size="small" placeholder="en or bn" value={filters.language} onChange={(event) => setFilters({ ...filters, language: event.target.value })} sx={{ width: { xs: '100%', md: 155 } }} />
            <FormControl size="small" sx={{ minWidth: 160 }}><InputLabel>Attachment</InputLabel><Select label="Attachment" value={filters.attached} onChange={(event) => setFilters({ ...filters, attached: event.target.value })}><MenuItem value="">Any</MenuItem><MenuItem value="1">Attached</MenuItem><MenuItem value="0">Unattached</MenuItem></Select></FormControl>
            <TextField label="Uploaded from" size="small" type="date" InputLabelProps={{ shrink: true }} value={filters.uploaded_from} onChange={(event) => setFilters({ ...filters, uploaded_from: event.target.value })} />
            <TextField label="Uploaded to" size="small" type="date" InputLabelProps={{ shrink: true }} value={filters.uploaded_to} onChange={(event) => setFilters({ ...filters, uploaded_to: event.target.value })} />
            <FormControl size="small" sx={{ minWidth: 180 }}><InputLabel>Sort by</InputLabel><Select label="Sort by" value={`${filters.sort}:${filters.sort_dir}`} onChange={(event) => { const [sort, sort_dir] = event.target.value.split(':'); setFilters({ ...filters, sort, sort_dir }); }}><MenuItem value="created_at:desc">Newest upload</MenuItem><MenuItem value="created_at:asc">Oldest upload</MenuItem><MenuItem value="original_name:asc">Name A–Z</MenuItem><MenuItem value="original_name:desc">Name Z–A</MenuItem><MenuItem value="size_bytes:desc">Largest</MenuItem><MenuItem value="size_bytes:asc">Smallest</MenuItem><MenuItem value="duration:desc">Longest</MenuItem><MenuItem value="duration:asc">Shortest</MenuItem><MenuItem value="status:asc">Status</MenuItem></Select></FormControl>
            <ToggleButtonGroup size="small" exclusive value={view} onChange={(_, value) => value && setView(value)} aria-label="Media library view">
              <ToggleButton value="table" aria-label="Table view"><Iconify icon="solar:list-bold" /></ToggleButton>
              <ToggleButton value="grid" aria-label="Grid view"><Iconify icon="solar:widget-5-bold" /></ToggleButton>
            </ToggleButtonGroup>
            {selected.length > 0 && canDelete && <Button color="error" variant="outlined" onClick={() => setConfirmOpen(true)}>Archive selected ({selected.length})</Button>}
          </Stack>
        </Stack>
      </CardContent></Card>

      {loading ? <Stack spacing={1}>{[0, 1, 2, 3].map((row) => <Skeleton key={row} variant="rounded" height={74} />)}</Stack> : view === 'table' ? (
        <Card><TableContainer>
          <Table size="small" aria-label="Media assets">
            <TableHead><TableRow><TableCell padding="checkbox"><Checkbox checked={allSelected} indeterminate={selected.length > 0 && !allSelected} onChange={toggleAll} inputProps={{ 'aria-label': 'Select all visible media' }} /></TableCell><TableCell>Asset</TableCell><TableCell>Metadata</TableCell><TableCell>Availability</TableCell><TableCell>Quality</TableCell><TableCell>Content</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead>
            <TableBody>
              {assets.map((asset) => (
                <TableRow key={asset.id} hover>
                  <TableCell padding="checkbox"><Checkbox checked={selected.includes(asset.id)} onChange={() => toggleSelected(asset.id)} inputProps={{ 'aria-label': `Select ${asset.display_name || asset.original_name}` }} /></TableCell>
                  <TableCell><Stack direction="row" spacing={1.25} alignItems="center">
                    {asset.thumbnail_url ? <Avatar variant="rounded" src={asset.thumbnail_url} alt="" sx={{ width: 64, height: 42, bgcolor: 'action.hover' }} /> : <Avatar variant="rounded" sx={{ width: 64, height: 42, bgcolor: 'action.hover' }}><Iconify icon="solar:videocamera-record-bold" /></Avatar>}
                    <Box sx={{ minWidth: 0 }}><Typography variant="subtitle2" noWrap>{asset.display_name || asset.original_name}</Typography><Typography variant="caption" color="text.secondary" noWrap>{asset.original_name}</Typography><Typography variant="caption" display="block" color="text.secondary">{humanize(asset.category)} · v{asset.version} {asset.is_active_version ? '· active' : ''}</Typography></Box>
                  </Stack></TableCell>
                  <TableCell><Typography variant="body2">{formatBytes(asset.size_bytes)}</Typography><Typography variant="caption" color="text.secondary">{asset.resolution || 'Resolution pending'} · {asset.duration_seconds ? `${Math.round(asset.duration_seconds / 60)} min` : 'Duration pending'}</Typography></TableCell>
                  <TableCell><Chip size="small" label={humanize(asset.status)} color={asset.status === 'ready' ? 'success' : asset.status === 'failed' ? 'error' : 'default'} />{asset.latest_processing_job?.failure_code && <Typography variant="caption" color="error.main" display="block">{asset.latest_processing_job.failure_code}</Typography>}</TableCell>
                  <TableCell><Chip size="small" variant="outlined" label={humanize(asset.quality_status)} /></TableCell>
                  <TableCell><Stack spacing={0.25}>{(asset.content_attachments || []).map((content) => <Typography key={`${content.type}:${content.id}`} variant="caption" noWrap>{content.title} · {humanize(content.type)}</Typography>)}{!asset.content_attachments?.length && <Typography variant="caption" color="text.secondary">Unattached</Typography>}</Stack></TableCell>
                  <TableCell align="right"><IconButton aria-label={`Actions for ${asset.display_name || asset.original_name}`} onClick={(event) => setMenu({ anchor: event.currentTarget, asset })}><Iconify icon="eva:more-vertical-fill" /></IconButton></TableCell>
                </TableRow>
              ))}
              {!assets.length && <TableRow><TableCell colSpan={7}><Typography color="text.secondary" align="center" sx={{ py: 5 }}>No assets match these filters.</Typography></TableCell></TableRow>}
            </TableBody>
          </Table>
        </TableContainer><TablePagination component="div" count={meta.total || 0} page={page} rowsPerPage={pageSize} rowsPerPageOptions={[10, 25, 50, 100]} onPageChange={(_, next) => setPage(next)} onRowsPerPageChange={(event) => { setPageSize(Number(event.target.value)); setPage(0); }} /></Card>
      ) : (
        <Grid container spacing={2}>
          {assets.map((asset) => <Grid item xs={12} sm={6} lg={4} key={asset.id}>
            <Card sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
              {asset.thumbnail_url ? <CardMedia component="img" height="180" image={asset.thumbnail_url} alt="" onError={(event) => { event.currentTarget.style.display = 'none'; }} /> : <Stack alignItems="center" justifyContent="center" sx={{ height: 180, bgcolor: 'action.hover' }}><Iconify icon="solar:videocamera-record-bold" width={40} /></Stack>}
              <CardContent sx={{ flex: 1 }}><Stack spacing={1}>
                <Stack direction="row" justifyContent="space-between" alignItems="flex-start"><Box sx={{ minWidth: 0 }}><Typography variant="subtitle1" noWrap>{asset.display_name || asset.original_name}</Typography><Typography variant="caption" color="text.secondary" noWrap>{asset.original_name}</Typography></Box><Checkbox size="small" checked={selected.includes(asset.id)} onChange={() => toggleSelected(asset.id)} inputProps={{ 'aria-label': `Select ${asset.display_name || asset.original_name}` }} /></Stack>
                <Stack direction="row" spacing={0.75} flexWrap="wrap"><Chip size="small" label={humanize(asset.status)} /><Chip size="small" variant="outlined" label={humanize(asset.quality_status)} /></Stack>
                <Typography variant="body2" color="text.secondary">{humanize(asset.category)} · {formatBytes(asset.size_bytes)} · {asset.resolution || 'Resolution pending'}</Typography>
                <Button component={Link} href={`/dashboard/media/assets/${asset.id}`} size="small" endIcon={<Iconify icon="solar:arrow-right-up-linear" />}>Open asset</Button>
              </Stack></CardContent>
            </Card>
          </Grid>)}
          {!assets.length && <Grid item xs={12}><Card><CardContent><Typography align="center" color="text.secondary" sx={{ py: 4 }}>No assets match these filters.</Typography></CardContent></Card></Grid>}
          <Grid item xs={12}><TablePagination component="div" count={meta.total || 0} page={page} rowsPerPage={pageSize} rowsPerPageOptions={[10, 25, 50, 100]} onPageChange={(_, next) => setPage(next)} onRowsPerPageChange={(event) => { setPageSize(Number(event.target.value)); setPage(0); }} /></Grid>
        </Grid>
      )}

      <Menu anchorEl={menu.anchor} open={Boolean(menu.anchor)} onClose={() => setMenu({ anchor: null, asset: null })}>
        {menu.asset && <MenuItem component={Link} href={`/dashboard/media/assets/${menu.asset.id}`} onClick={() => setMenu({ anchor: null, asset: null })}>Open details</MenuItem>}
        {menu.asset && canRetry && menu.asset.latest_processing_job?.status === 'failed' && <MenuItem onClick={() => { const target = menu.asset; setMenu({ anchor: null, asset: null }); retry(target); }}>Retry processing</MenuItem>}
        {menu.asset && canDelete && <MenuItem onClick={() => { const target = menu.asset; setSelected([target.id]); setMenu({ anchor: null, asset: null }); setConfirmOpen(true); }}>Archive asset</MenuItem>}
      </Menu>

      <Dialog open={confirmOpen} onClose={() => setConfirmOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Archive selected media</DialogTitle>
        <DialogContent><Stack spacing={1.5} sx={{ pt: 1 }}>
          <Alert severity="warning">Published-content dependencies are checked separately for every asset. Blocked assets remain available in the library.</Alert>
          <TextField multiline minRows={2} label="Archive reason (optional)" value={archiveReason} onChange={(event) => setArchiveReason(event.target.value)} inputProps={{ maxLength: 500 }} />
        </Stack></DialogContent>
        <DialogActions><Button onClick={() => setConfirmOpen(false)}>Cancel</Button><Button color="error" variant="contained" onClick={archiveSelected}>Archive {selected.length} asset{selected.length === 1 ? '' : 's'}</Button></DialogActions>
      </Dialog>
    </Stack>
  );
}

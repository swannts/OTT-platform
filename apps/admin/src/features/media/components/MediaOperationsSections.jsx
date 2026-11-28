'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';

import Alert from '@mui/material/Alert';
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
import InputLabel from '@mui/material/InputLabel';
import LinearProgress from '@mui/material/LinearProgress';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TablePagination from '@mui/material/TablePagination';
import TableRow from '@mui/material/TableRow';
import TableHead from '@mui/material/TableHead';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { mediaApi } from '../media.api';

function humanize(value) { return String(value || '—').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()); }
function formatBytes(value) {
  const bytes = Number(value || 0);
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
  if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
  return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
}

export function MediaProcessingQueue({ canManage }) {
  const [jobs, setJobs] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
  const [page, setPage] = useState(0);
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async (nextPage = page, nextStatus = status) => {
    try {
      setError('');
      const response = await mediaApi.processingJobs({ page: nextPage + 1, per_page: 25, ...(nextStatus ? { status: nextStatus } : {}) });
      setJobs(response.data || []);
      if (response.meta) setMeta(response.meta);
    } catch (cause) { setError(cause.response?.data?.message || 'Processing jobs could not be loaded.'); }
    finally { setLoading(false); }
  }, [page, status]);

  useEffect(() => { setLoading(true); load(); }, [load]);
  useEffect(() => {
    const timer = window.setInterval(() => {
      if (document.visibilityState === 'visible' && jobs.some((job) => ['queued', 'processing'].includes(job.status))) load(page, status);
    }, 10000);
    return () => window.clearInterval(timer);
  }, [jobs, load, page, status]);

  const cancel = async (job) => {
    try { await mediaApi.cancelJob(job.id); await load(); }
    catch (cause) { setError(cause.response?.data?.message || 'This processing job could not be cancelled.'); }
  };

  return <Stack spacing={2}>
    {error && <Alert severity="error">{error}</Alert>}
    {!canManage && <Alert severity="info">You can inspect queue state. The media processing permission is required to cancel jobs.</Alert>}
    <Card><CardContent><Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
      <Box><Typography variant="h6">Processing queue</Typography><Typography variant="body2" color="text.secondary">Refreshes every 10 seconds only while active jobs exist and this page is visible.</Typography></Box>
      <FormControl size="small" sx={{ minWidth: 180 }}><InputLabel>Status</InputLabel><Select label="Status" value={status} onChange={(event) => { setStatus(event.target.value); setPage(0); }}><MenuItem value="">All jobs</MenuItem>{['queued', 'processing', 'completed', 'failed', 'cancelled'].map((value) => <MenuItem value={value} key={value}>{humanize(value)}</MenuItem>)}</Select></FormControl>
    </Stack></CardContent></Card>
    {loading ? <Stack spacing={1}>{[0, 1, 2].map((item) => <Skeleton key={item} height={72} variant="rounded" />)}</Stack> : <Card><Table size="small">
      <TableHead><TableRow><TableCell>Asset / job</TableCell><TableCell>Status</TableCell><TableCell>Stage</TableCell><TableCell>Progress</TableCell><TableCell>Worker</TableCell><TableCell>Attempts</TableCell><TableCell>Failure</TableCell><TableCell align="right">Action</TableCell></TableRow></TableHead>
      <TableBody>{jobs.map((job) => <TableRow key={job.id}>
        <TableCell><Typography variant="subtitle2">{job.asset_name || `Asset ${job.asset_id}`}</Typography><Typography variant="caption" color="text.secondary">#{job.id} · {humanize(job.type)}</Typography></TableCell>
        <TableCell><Chip size="small" label={humanize(job.status)} color={job.status === 'failed' ? 'error' : job.status === 'completed' ? 'success' : 'default'} /></TableCell>
        <TableCell>{humanize(job.stage)}</TableCell>
        <TableCell sx={{ minWidth: 130 }}><Stack direction="row" spacing={1} alignItems="center"><LinearProgress sx={{ flex: 1 }} variant="determinate" value={Math.min(100, Number(job.progress || 0))} /><Typography variant="caption">{job.progress ?? 0}%</Typography></Stack></TableCell>
        <TableCell>{job.worker_name || 'Waiting for worker'}</TableCell>
        <TableCell>{job.attempts} / {job.max_attempts}</TableCell>
        <TableCell><Typography variant="caption" color={job.failure_code ? 'error.main' : 'text.secondary'}>{job.failure_code || '—'}</Typography>{job.failure_message && <Typography variant="caption" display="block" color="text.secondary">{job.failure_message}</Typography>}</TableCell>
        <TableCell align="right"><Stack direction="row" justifyContent="flex-end"><Button size="small" component={Link} href={`/dashboard/media/assets/${job.asset_id}`}>Asset</Button>{canManage && ['queued', 'processing'].includes(job.status) && <Button size="small" color="error" onClick={() => cancel(job)}>Cancel</Button>}</Stack></TableCell>
      </TableRow>)}{!jobs.length && <TableRow><TableCell colSpan={8}><Typography align="center" color="text.secondary" sx={{ py: 4 }}>No processing jobs match this filter.</Typography></TableCell></TableRow>}</TableBody>
    </Table><TablePagination component="div" count={meta.total || 0} page={page} rowsPerPage={25} rowsPerPageOptions={[25]} onPageChange={(_, next) => setPage(next)} /></Card>}
  </Stack>;
}

export function MediaQualityQueue({ onChanged }) {
  const [assets, setAssets] = useState([]);
  const [meta, setMeta] = useState({ total: 0 });
  const [page, setPage] = useState(0);
  const [status, setStatus] = useState('awaiting_manual_review');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [target, setTarget] = useState(null);
  const [notes, setNotes] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await mediaApi.list({ quality_status: status, page: page + 1, per_page: 25, sort: 'created_at', sort_dir: 'asc' });
      setAssets(response.data || []);
      if (response.meta) setMeta(response.meta);
      setError('');
    } catch (cause) { setError(cause.response?.data?.message || 'Quality review items could not be loaded.'); }
    finally { setLoading(false); }
  }, [page, status]);
  useEffect(() => { load(); }, [load]);

  const inspect = async (asset) => {
    try {
      const response = await mediaApi.quality(asset.id);
      setTarget({ ...asset, technical: response.data });
      setNotes(asset.quality_notes || '');
    } catch (cause) { setError(cause.response?.data?.message || 'Technical checks could not be loaded.'); }
  };
  const decide = async (decision) => {
    if (!target || notes.trim().length < 3) return;
    try {
      await mediaApi.reviewQuality(target.id, { decision, notes: notes.trim() });
      setNotice(`Quality decision recorded: ${humanize(decision)}.`);
      setTarget(null);
      await load();
      onChanged?.();
    } catch (cause) { setError(cause.response?.data?.message || 'Quality decision could not be saved.'); }
  };

  return <Stack spacing={2}>
    {error && <Alert severity="error" onClose={() => setError('')}>{error}</Alert>}{notice && <Alert severity="success" onClose={() => setNotice('')}>{notice}</Alert>}
    <Card><CardContent><Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1}><Box><Typography variant="h6">Quality control</Typography><Typography variant="body2" color="text.secondary">Technical checks and manual decisions are kept separate from processing status.</Typography></Box><FormControl size="small" sx={{ minWidth: 220 }}><InputLabel>Review state</InputLabel><Select label="Review state" value={status} onChange={(event) => { setStatus(event.target.value); setPage(0); }}>{['pending_inspection', 'technical_failed', 'awaiting_manual_review', 'approved', 'rejected', 'reprocessing_required'].map((value) => <MenuItem value={value} key={value}>{humanize(value)}</MenuItem>)}</Select></FormControl></Stack></CardContent></Card>
    {loading ? <Stack spacing={1}>{[0, 1].map((item) => <Skeleton key={item} height={82} variant="rounded" />)}</Stack> : <Card>{assets.map((asset) => <Card key={asset.id} variant="outlined" sx={{ m: 1 }}><CardContent><Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}><Box><Typography variant="subtitle1">{asset.display_name || asset.original_name}</Typography><Typography variant="body2" color="text.secondary">{humanize(asset.status)} · {humanize(asset.quality_status)} · Version {asset.version}</Typography></Box><Button variant="outlined" onClick={() => inspect(asset)}>Inspect</Button></Stack></CardContent></Card>)}<TablePagination component="div" count={meta.total || 0} page={page} rowsPerPage={25} rowsPerPageOptions={[25]} onPageChange={(_, next) => setPage(next)} /></Card>}
    {!loading && !assets.length && <Alert severity="success">No assets are currently in this review state.</Alert>}
    <Dialog open={Boolean(target)} onClose={() => setTarget(null)} fullWidth maxWidth="md">
      <DialogTitle>Review {target?.display_name || target?.original_name}</DialogTitle>
      <DialogContent><Stack spacing={2} sx={{ pt: 1 }}>
        <Typography variant="subtitle2">Automated technical checks</Typography>
        <Stack direction="row" flexWrap="wrap" gap={1}>{Object.entries(target?.technical?.checks || {}).map(([key, passed]) => <Chip key={key} color={passed ? 'success' : 'error'} variant="outlined" label={`${humanize(key)}: ${passed ? 'Passed' : 'Failed'}`} />)}</Stack>
        {!target?.technical?.ready && <Alert severity="warning">Technical readiness has not passed. Approve is unavailable until every readiness check passes.</Alert>}
        <TextField multiline minRows={3} label="Review notes" value={notes} onChange={(event) => setNotes(event.target.value)} inputProps={{ maxLength: 2000 }} helperText="Required for audit. Approval requires a different administrator from the uploader." />
      </Stack></DialogContent>
      <DialogActions><Button onClick={() => setTarget(null)}>Close</Button><Button color="warning" disabled={notes.trim().length < 3} onClick={() => decide('reprocess')}>Request reprocessing</Button><Button color="error" disabled={notes.trim().length < 3} onClick={() => decide('reject')}>Reject</Button><Button variant="contained" disabled={!target?.technical?.ready || notes.trim().length < 3} onClick={() => decide('approve')}>Approve quality</Button></DialogActions>
    </Dialog>
  </Stack>;
}

export function MediaStoragePage() {
  const [storage, setStorage] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const load = useCallback(async () => { try { const response = await mediaApi.storage(); setStorage(response.data); } catch (cause) { setError(cause.response?.data?.message || 'Storage metrics could not be loaded.'); } finally { setLoading(false); } }, []);
  useEffect(() => { load(); }, [load]);
  if (loading) return <Skeleton height={300} variant="rounded" />;
  return <Stack spacing={2}>{error && <Alert severity="error">{error}</Alert>}<Alert severity="info">{storage?.storage_source}</Alert>
    <Card><CardContent><Typography variant="h6">Storage inventory</Typography><Typography variant="h3" sx={{ my: 1 }}>{formatBytes(storage?.source_bytes_estimated)}</Typography><Typography variant="body2" color="text.secondary">Source size from asset metadata. Rendition and provider-reported byte totals are unavailable in the current schema.</Typography><Stack direction="row" spacing={2} sx={{ mt: 2 }}><Chip label={`${storage?.incomplete_uploads ?? '—'} active upload sessions`} /><Chip label={`${storage?.unattached_assets ?? '—'} unattached assets`} /></Stack></CardContent></Card>
    <Card><CardContent><Typography variant="h6" sx={{ mb: 1 }}>Storage by category</Typography><Table size="small"><TableHead><TableRow><TableCell>Category</TableCell><TableCell align="right">Assets</TableCell><TableCell align="right">Estimated source size</TableCell></TableRow></TableHead><TableBody>{Object.entries(storage?.by_category || {}).map(([category, value]) => <TableRow key={category}><TableCell>{humanize(category)}</TableCell><TableCell align="right">{value.assets}</TableCell><TableCell align="right">{formatBytes(value.bytes_estimated)}</TableCell></TableRow>)}</TableBody></Table></CardContent></Card>
    <Card><CardContent><Typography variant="h6" sx={{ mb: 1 }}>Largest source assets</Typography><Table size="small"><TableHead><TableRow><TableCell>Asset</TableCell><TableCell>Provider disk</TableCell><TableCell>Status</TableCell><TableCell align="right">Size</TableCell></TableRow></TableHead><TableBody>{(storage?.largest_assets || []).map((asset) => <TableRow key={asset.id}><TableCell><Button component={Link} href={`/dashboard/media/assets/${asset.id}`} size="small">{asset.display_name || asset.original_name}</Button></TableCell><TableCell>{asset.disk}</TableCell><TableCell>{humanize(asset.status)}</TableCell><TableCell align="right">{formatBytes(asset.size_bytes)}</TableCell></TableRow>)}</TableBody></Table></CardContent></Card>
  </Stack>;
}

export function MediaArchivePage({ canRestore }) {
  const [search, setSearch] = useState('');
  const [appliedSearch, setAppliedSearch] = useState('');
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
  const [page, setPage] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const load = useCallback(async (nextPage = page) => {
    setLoading(true);
    try { const response = await mediaApi.archived({ page: nextPage + 1, per_page: 25, ...(appliedSearch ? { search: appliedSearch } : {}) }); setItems(response.data || []); if (response.meta) setMeta(response.meta); setError(''); }
    catch (cause) { setError(cause.response?.data?.message || 'Archived assets could not be loaded.'); }
    finally { setLoading(false); }
  }, [appliedSearch, page]);
  useEffect(() => { load(); }, [load]);
  const restore = async (asset) => {
    try { await mediaApi.restore(asset.id); setNotice(`${asset.display_name || asset.original_name} restored to the library. It was not published.`); await load(); }
    catch (cause) { setError(cause.response?.data?.message || 'This asset could not be restored.'); }
  };
  return <Stack spacing={2}>{error && <Alert severity="error">{error}</Alert>}{notice && <Alert severity="success">{notice}</Alert>}
    <Card><CardContent><Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25}><TextField fullWidth label="Search archived assets" size="small" value={search} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); setPage(0); setAppliedSearch(search); } }} /><Button variant="contained" onClick={() => { setPage(0); setAppliedSearch(search); }}>Search</Button></Stack></CardContent></Card>
    {loading ? <Skeleton height={180} variant="rounded" /> : <Card><Table size="small"><TableHead><TableRow><TableCell>Asset</TableCell><TableCell>Archived at</TableCell><TableCell>Reason</TableCell><TableCell>Uploader</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead><TableBody>{items.map((asset) => <TableRow key={asset.id}><TableCell><Typography variant="subtitle2">{asset.display_name || asset.original_name}</Typography><Typography variant="caption" color="text.secondary">{humanize(asset.category)} · {formatBytes(asset.size_bytes)}</Typography></TableCell><TableCell>{asset.archived_at || 'Date unavailable'}</TableCell><TableCell>{asset.archive_reason || 'No reason recorded'}</TableCell><TableCell>{asset.archived_by?.name || 'Unknown'}</TableCell><TableCell align="right"><Stack direction="row" justifyContent="flex-end"><Button component={Link} href={`/dashboard/media/assets/${asset.id}`} size="small">Details</Button>{canRestore && <Button size="small" onClick={() => restore(asset)}>Restore</Button>}</Stack></TableCell></TableRow>)}{!items.length && <TableRow><TableCell colSpan={5}><Typography align="center" color="text.secondary" sx={{ py: 4 }}>No archived assets match this search.</Typography></TableCell></TableRow>}</TableBody></Table><TablePagination component="div" count={meta.total || 0} page={page} rowsPerPage={25} rowsPerPageOptions={[25]} onPageChange={(_, next) => setPage(next)} /></Card>}
  </Stack>;
}

export function AudioSubtitleAssets() {
  const [assets, setAssets] = useState([]);
  const [meta, setMeta] = useState({ total: 0 });
  const [page, setPage] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const load = useCallback(async () => { setLoading(true); try { const response = await mediaApi.list({ per_page: 25, page: page + 1, has_tracks: 1 }); setAssets(response.data || []); if (response.meta) setMeta(response.meta); setError(''); } catch (cause) { setError(cause.response?.data?.message || 'Track assets could not be loaded.'); } finally { setLoading(false); } }, [page]);
  useEffect(() => { load(); }, [load]);
  return <Stack spacing={2}>{error && <Alert severity="error">{error}</Alert>}<Alert severity="info">Track changes are processed as a new rendition. The active version remains available until the new output completes.</Alert>{loading ? <Skeleton height={150} variant="rounded" /> : <Card><Table size="small"><TableHead><TableRow><TableCell>Media asset</TableCell><TableCell>Tracks</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead><TableBody>{assets.map((asset) => <TableRow key={asset.id}><TableCell><Typography variant="subtitle2">{asset.display_name || asset.original_name}</Typography><Typography variant="caption" color="text.secondary">{humanize(asset.status)} · Version {asset.version}</Typography></TableCell><TableCell><Stack direction="row" flexWrap="wrap" gap={0.75}>{(asset.asset_tracks || []).map((track) => <Chip key={track.id} size="small" variant="outlined" label={`${track.display_name || track.language} · ${humanize(track.kind)} · ${humanize(track.role)}`} />)}</Stack></TableCell><TableCell align="right"><Button component={Link} href={`/dashboard/media/assets/${asset.id}`}>Manage tracks</Button></TableCell></TableRow>)}{!assets.length && <TableRow><TableCell colSpan={3}><Typography align="center" color="text.secondary" sx={{ py: 4 }}>No uploaded audio or subtitle tracks are currently available.</Typography></TableCell></TableRow>}</TableBody></Table><TablePagination component="div" count={meta.total || 0} page={page} rowsPerPage={25} rowsPerPageOptions={[25]} onPageChange={(_, next) => setPage(next)} /></Card>}</Stack>;
}

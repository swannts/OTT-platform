'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';

import Alert from '@mui/material/Alert';
import Autocomplete from '@mui/material/Autocomplete';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import FormControlLabel from '@mui/material/FormControlLabel';
import Grid from '@mui/material/Grid';
import MenuItem from '@mui/material/MenuItem';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { MediaUploadManager } from './components/MediaUploadManager';
import { mediaApi } from './media.api';

const TRACK_ROLES = ['main', 'dub', 'commentary', 'description', 'captions', 'forced', 'other'];
const CATEGORIES = ['movie', 'episode', 'trailer', 'teaser', 'standalone_video', 'promotional_video', 'video', 'audio', 'subtitle', 'poster', 'backdrop', 'thumbnail', 'artwork', 'other'];
const humanize = (value) => String(value || '—').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const formatBytes = (value) => {
  const bytes = Number(value || 0);
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
  if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
  return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
};

export function MediaAssetDetailsPage() {
  const params = useParams();
  const assetId = params?.id;
  const { user } = useAuthContext();
  const permissions = useMemo(() => new Set(user?.permissions || []), [user?.permissions]);
  const [asset, setAsset] = useState(null);
  const [assetForm, setAssetForm] = useState({ display_name: '', description: '', category: 'video' });
  const [versions, setVersions] = useState([]);
  const [preview, setPreview] = useState(null);
  const [contentOptions, setContentOptions] = useState([]);
  const [contentSearch, setContentSearch] = useState('');
  const [selectedContent, setSelectedContent] = useState(null);
  const [trackToRemove, setTrackToRemove] = useState(null);
  const [track, setTrack] = useState({ kind: 'subtitle', language: 'en', display_name: '', role: 'main', is_default: false, is_forced: false, is_accessibility: false, is_original: false, is_dubbed: false, file: null });
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const load = useCallback(async (showLoading = true) => {
    if (!assetId) return;
    if (showLoading) setLoading(true);
    setError('');
    try {
      const [assetResponse, versionsResponse] = await Promise.all([
        mediaApi.show(assetId),
        permissions.has('media.view') ? mediaApi.versions(assetId) : Promise.resolve({ data: [] }),
      ]);
      setAsset(assetResponse.data);
      setAssetForm({ display_name: assetResponse.data?.display_name || '', description: assetResponse.data?.description || '', category: assetResponse.data?.category || 'video' });
      setVersions(versionsResponse.data || []);
    } catch (cause) {
      setError(cause.response?.data?.message || 'Asset details could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [assetId, permissions]);

  const refreshAfterUpload = useCallback(async () => {
    try {
      const [assetResponse, versionsResponse] = await Promise.all([mediaApi.show(assetId), mediaApi.versions(assetId)]);
      setAsset(assetResponse.data);
      setVersions(versionsResponse.data || []);
    } catch (cause) {
      setError(cause.response?.data?.message || 'The uploaded version is saved, but the version list could not be refreshed.');
    }
  }, [assetId]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (!permissions.has('media.preview') || !assetId || asset?.status !== 'ready') return undefined;
    let active = true;
    mediaApi.preview(assetId).then((response) => { if (active) setPreview(response.data); })
      .catch(() => { if (active) setPreview(null); });
    return () => { active = false; };
  }, [asset?.status, assetId, permissions]);

  useEffect(() => {
    if (!permissions.has('content.view') || contentSearch.trim().length < 2) { setContentOptions([]); return undefined; }
    let active = true;
    const timer = window.setTimeout(() => {
      mediaApi.contentOptions(contentSearch.trim()).then((response) => {
        if (active) setContentOptions(response.data || []);
      }).catch((cause) => { if (active) setError(cause.response?.data?.message || 'Content options could not be loaded.'); });
    }, 250);
    return () => { active = false; window.clearTimeout(timer); };
  }, [contentSearch, permissions]);

  const perform = async (operation, successMessage) => {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      await operation();
      setNotice(successMessage);
      await load(false);
    } catch (cause) {
      setError(cause.response?.data?.message || 'The media operation could not be completed.');
    } finally {
      setBusy(false);
    }
  };

  const attach = () => {
    if (!selectedContent) return;
    perform(() => mediaApi.attachToContent(asset.id, selectedContent.id, selectedContent.type), 'Asset attached to the selected draft content.');
  };

  const saveAssetDetails = (event) => {
    event.preventDefault();
    perform(() => mediaApi.update(asset.id, assetForm), 'Asset details updated.');
  };

  const addTrack = async (event) => {
    event.preventDefault();
    if (!track.file) { setError('Choose a track file before submitting.'); return; }
    const form = event.currentTarget;
    const body = new FormData();
    Object.entries(track).forEach(([key, value]) => {
      if (key === 'file') body.append('file', value, value.name);
      else if (typeof value === 'boolean') body.append(key, value ? '1' : '0');
      else if (value !== '' && value != null) body.append(key, String(value));
    });
    await perform(() => mediaApi.addTrack(asset.id, body), 'Track uploaded. Existing playback remains available while its new rendition is processed.');
    setTrack((current) => ({ ...current, display_name: '', file: null }));
    form.reset();
  };

  const removeTrack = (trackAsset) => {
    perform(() => mediaApi.deleteTrack(asset.id, trackAsset.id), 'Track removal was queued for processing.');
    setTrackToRemove(null);
  };

  const replacementSource = [...versions]
    .filter((version) => !version.archived)
    .sort((first, second) => Number(second.version) - Number(first.version))[0] || asset;

  if (loading) return <DashboardContent><Stack spacing={2}><Skeleton height={58} /><Skeleton height={220} variant="rounded" /><Skeleton height={180} variant="rounded" /></Stack></DashboardContent>;

  return (
    <DashboardContent>
      <Stack spacing={2.5}>
        <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} flexWrap="wrap" spacing={1} sx={{ minWidth: 0 }}>
          <Box sx={{ minWidth: 0, flex: '1 1 320px' }}>
            <Button component={Link} href={permissions.has('media.view') ? '/dashboard/media' : '/dashboard'} size="small" sx={{ mb: 0.5, ml: -1 }}>{permissions.has('media.view') ? '← Media operations' : '← Dashboard'}</Button>
            <Typography variant="h4">{asset?.display_name || asset?.original_name || `Asset ${assetId}`}</Typography>
            <Typography variant="body2" color="text.secondary">{asset?.original_name} · Version {asset?.version} · {humanize(asset?.category)}</Typography>
          </Box>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ minWidth: 0, maxWidth: '100%' }}>
            <Chip label={humanize(asset?.status)} color={asset?.status === 'ready' ? 'success' : asset?.status === 'failed' ? 'error' : 'default'} />
            <Chip label={`QC: ${humanize(asset?.quality_status)}`} variant="outlined" />
            {asset?.is_active_version && <Chip label="Active version" color="primary" variant="outlined" />}
          </Stack>
        </Stack>
        {error && <Alert severity="error" onClose={() => setError('')}>{error}</Alert>}
        {notice && <Alert severity="success" onClose={() => setNotice('')}>{notice}</Alert>}

        <Grid container spacing={2} sx={{ minWidth: 0 }}>
          <Grid item xs={12} md={7} sx={{ minWidth: 0 }}>
            <Card sx={{ width: '100%', minWidth: 0 }}><CardContent sx={{ minWidth: 0 }}>
              <Typography variant="h6" sx={{ mb: 2 }}>Asset information</Typography>
              <Grid container spacing={2} sx={{ minWidth: 0 }}>
                {[
                  ['Source size', formatBytes(asset?.size_bytes)], ['Resolution', asset?.resolution || 'Not available'],
                  ['Duration', asset?.duration_seconds ? `${Math.round(asset.duration_seconds)} seconds` : 'Not available'],
                  ['Aspect ratio', asset?.aspect_ratio || 'Not available'], ['Frame rate', asset?.frame_rate || 'Not available'],
                  ['Video codec', asset?.codec || 'Not available'], ['Audio codec', asset?.audio_codec || 'Not available'],
                  ['Bitrate', asset?.bitrate ? `${Math.round(Number(asset.bitrate) / 1000)} kbps` : 'Not available'],
                  ['Container', asset?.mime_type || asset?.extension || 'Not available'], ['Storage', asset?.storage_provider || 'Not available'],
                  ['Checksum', asset?.checksum || 'Not available'], ['Uploaded by', asset?.uploader?.name || 'Unknown'],
                  ['Uploaded', asset?.created_at ? new Date(asset.created_at).toLocaleString() : 'Unknown'],
                  ['Updated', asset?.updated_at ? new Date(asset.updated_at).toLocaleString() : 'Unknown'], ['Failure', asset?.failure_code || 'None'],
                ].map(([label, value]) => <Grid item xs={6} sm={4} key={label} sx={{ minWidth: 0 }}><Typography variant="caption" color="text.secondary">{label}</Typography><Typography variant="body2" title={String(value)} sx={{ minWidth: 0, overflowWrap: 'anywhere', wordBreak: 'break-word' }}>{value}</Typography></Grid>)}
              </Grid>
              {asset?.quality_notes && <Alert severity={asset.quality_status === 'rejected' ? 'error' : 'info'} sx={{ mt: 2 }}><Typography variant="subtitle2">Quality review</Typography>{asset.quality_notes}<Typography variant="caption" display="block">{asset.quality_reviewer?.name || 'Reviewer unavailable'} · {asset.quality_reviewed_at ? new Date(asset.quality_reviewed_at).toLocaleString() : 'Date unavailable'}</Typography></Alert>}
              {asset?.latest_processing_job && <Box sx={{ mt: 2, p: 1.5, bgcolor: 'background.neutral', borderRadius: 1.5 }}><Typography variant="subtitle2">Latest processing attempt</Typography><Typography variant="body2">{humanize(asset.latest_processing_job.stage)} · {asset.latest_processing_job.progress ?? 0}% · {humanize(asset.latest_processing_job.status)}</Typography>{asset.latest_processing_job.failure_message && <Typography variant="caption" color="error.main">{asset.latest_processing_job.failure_message}</Typography>}</Box>}
            </CardContent></Card>
          </Grid>
          <Grid item xs={12} md={5} sx={{ minWidth: 0 }}>
            <Card sx={{ width: '100%', minWidth: 0 }}><CardContent sx={{ minWidth: 0 }}>
              <Typography variant="h6" sx={{ mb: 1.5 }}>Authorized preview</Typography>
              {preview?.manifest_url ? <Box component="video" controls preload="metadata" src={preview.manifest_url} poster={preview.poster_url} sx={{ display: 'block', width: '100%', maxWidth: '100%', minWidth: 0, bgcolor: 'common.black', borderRadius: 1.5, aspectRatio: '16 / 9' }} /> : <Alert severity="info" sx={{ minWidth: 0, '& .MuiAlert-message': { minWidth: 0, overflowWrap: 'anywhere' } }}>{permissions.has('media.preview') ? 'A private preview is available after a rendition has been generated.' : 'Your role does not include preview permission.'}</Alert>}
              {preview?.manifest_url && <Typography variant="caption" color="text.secondary" display="block" sx={{ mt: 1 }}>Preview links expire after 2 minutes. HLS playback depends on browser support.</Typography>}
            </CardContent></Card>
          </Grid>
        </Grid>

        {permissions.has('media.update') && <Card component="form" onSubmit={saveAssetDetails}><CardContent><Stack spacing={1.5}>
          <Box><Typography variant="h6">Catalog metadata</Typography><Typography variant="body2" color="text.secondary">This changes the media-library record; it does not publish or rename the original source file.</Typography></Box>
          <Grid container spacing={1.5}>
            <Grid item xs={12} md={5}><TextField fullWidth required label="Display name" value={assetForm.display_name} onChange={(event) => setAssetForm({ ...assetForm, display_name: event.target.value })} inputProps={{ maxLength: 255 }} /></Grid>
            <Grid item xs={12} md={3}><TextField select fullWidth label="Category" value={assetForm.category} onChange={(event) => setAssetForm({ ...assetForm, category: event.target.value })}>{CATEGORIES.map((category) => <MenuItem key={category} value={category}>{humanize(category)}</MenuItem>)}</TextField></Grid>
            <Grid item xs={12} md={12}><TextField fullWidth multiline minRows={2} label="Description" value={assetForm.description} onChange={(event) => setAssetForm({ ...assetForm, description: event.target.value })} inputProps={{ maxLength: 5000 }} /></Grid>
          </Grid>
          <Box><Button type="submit" variant="contained" disabled={busy}>Save metadata</Button></Box>
        </Stack></CardContent></Card>}

        {permissions.has('media.update') && permissions.has('content.update') && <Card><CardContent>
          <Stack spacing={1.5}>
            <Box><Typography variant="h6">Attach to draft content</Typography><Typography variant="body2" color="text.secondary">Published content is protected; use the review and publishing workflow for live titles.</Typography></Box>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} alignItems={{ sm: 'flex-start' }}>
              <Autocomplete fullWidth options={contentOptions} value={selectedContent} inputValue={contentSearch} onInputChange={(_, value) => setContentSearch(value)} onChange={(_, value) => setSelectedContent(value)} getOptionLabel={(option) => `${option.title} · ${humanize(option.type)} · ${option.status}`} isOptionEqualToValue={(option, value) => option.type === value.type && option.id === value.id} renderInput={(inputParams) => <TextField {...inputParams} label="Search draft titles" helperText="Type at least two characters." />} />
              <Button variant="contained" disabled={!selectedContent || busy} onClick={attach}>Attach asset</Button>
            </Stack>
            {(asset?.content_attachments || []).length > 0 && <Stack direction="row" flexWrap="wrap" gap={1}>{asset.content_attachments.map((item) => <Chip key={`${item.type}:${item.id}`} label={`${item.title} · ${humanize(item.type)} · ${item.status}`} />)}</Stack>}
          </Stack>
        </CardContent></Card>}

        {permissions.has('media.update') && permissions.has('media.upload') && <MediaUploadManager replacementAsset={replacementSource} onCompleted={refreshAfterUpload} />}

        <Card><CardContent>
          <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1} sx={{ mb: 1.5 }}>
            <Box><Typography variant="h6">Source versions</Typography><Typography variant="body2" color="text.secondary">Activation requires a ready, quality-approved source. Published titles require the publishing workflow.</Typography></Box>
            {permissions.has('media.version.activate') && !asset?.is_active_version && <Button variant="outlined" disabled={busy || asset?.status !== 'ready' || asset?.quality_status !== 'approved'} onClick={() => perform(() => mediaApi.activateVersion(asset.id), 'Version activated for draft content.')}>Activate this version</Button>}
          </Stack>
          <Table size="small"><TableHead><TableRow><TableCell>Version</TableCell><TableCell>Filename</TableCell><TableCell>Status</TableCell><TableCell>Quality</TableCell><TableCell>Uploaded</TableCell><TableCell align="right">Action</TableCell></TableRow></TableHead>
            <TableBody>{versions.map((version) => <TableRow key={version.id} selected={version.is_active}>
              <TableCell>v{version.version}{version.is_active && <Chip size="small" sx={{ ml: 1 }} label="Active" color="primary" />}</TableCell><TableCell>{version.original_name}</TableCell><TableCell>{humanize(version.status)}</TableCell><TableCell>{humanize(version.quality_status)}</TableCell><TableCell>{version.created_at ? new Date(version.created_at).toLocaleString() : '—'}</TableCell>
              <TableCell align="right">{permissions.has('media.version.rollback') && !version.is_active && <Button size="small" disabled={busy || version.status !== 'ready' || version.quality_status !== 'approved'} onClick={() => perform(() => mediaApi.rollbackVersion(asset.id, version.id), `Version ${version.version} activated for draft content.`)}>Activate / rollback</Button>}</TableCell>
            </TableRow>)}{!versions.length && <TableRow><TableCell colSpan={6}>No replacement versions are recorded.</TableCell></TableRow>}</TableBody>
          </Table>
        </CardContent></Card>

        <Card><CardContent>
          <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1} sx={{ mb: 2 }}>
            <Box>
              <Typography variant="h6">Audio and subtitle tracks</Typography>
              <Typography variant="body2" color="text.secondary">Manage language and accessibility options for this title.</Typography>
            </Box>
            <Chip size="small" variant="outlined" label={`${asset?.asset_tracks?.length || 0} ${asset?.asset_tracks?.length === 1 ? 'track' : 'tracks'}`} />
          </Stack>

          <Stack spacing={1}>
            {(asset?.asset_tracks || []).map((item) => {
              const canRemove = item.kind === 'subtitle' ? permissions.has('media.subtitles.manage') : permissions.has('media.audio.manage');
              const flags = [item.is_default && 'Default', item.is_forced && 'Forced', item.is_accessibility && 'Accessibility', item.is_original && 'Original', item.is_dubbed && 'Dubbed'].filter(Boolean);
              return (
                <Box key={item.id} sx={{ display: 'flex', alignItems: { xs: 'flex-start', sm: 'center' }, justifyContent: 'space-between', gap: 2, p: 1.5, border: 1, borderColor: 'divider', borderRadius: 1.5, minWidth: 0 }}>
                  <Box sx={{ minWidth: 0, flex: 1 }}>
                    <Typography variant="subtitle2" noWrap title={item.display_name || item.original_name}>{item.display_name || item.original_name}</Typography>
                    <Stack direction="row" spacing={0.75} useFlexGap flexWrap="wrap" sx={{ mt: 0.75 }}>
                      <Chip size="small" label={humanize(item.kind)} />
                      <Chip size="small" variant="outlined" label={item.language?.toUpperCase() || 'Language not set'} />
                      <Chip size="small" variant="outlined" label={humanize(item.role)} />
                      <Typography variant="caption" color="text.secondary" sx={{ alignSelf: 'center' }}>{formatBytes(item.size_bytes)}</Typography>
                      {flags.map((flag) => <Chip key={flag} size="small" color={flag === 'Accessibility' ? 'info' : 'default'} variant="outlined" label={flag} />)}
                    </Stack>
                  </Box>
                  {canRemove && <Button size="small" color="error" variant="outlined" disabled={busy} onClick={() => setTrackToRemove(item)}>Remove</Button>}
                </Box>
              );
            })}
            {!asset?.asset_tracks?.length && <Box sx={{ textAlign: 'center', py: 3, px: 2, border: 1, borderStyle: 'dashed', borderColor: 'divider', borderRadius: 1.5 }}>
              <Typography variant="subtitle2">No additional tracks yet</Typography>
              <Typography variant="body2" color="text.secondary">Add subtitle or audio tracks to make playback accessible to more viewers.</Typography>
            </Box>}
          </Stack>

          {(permissions.has('media.subtitles.manage') || permissions.has('media.audio.manage')) && <Box component="form" onSubmit={addTrack} sx={{ mt: 2.5, p: { xs: 1.5, sm: 2 }, bgcolor: 'background.neutral', borderRadius: 2 }}>
            <Stack spacing={1.75}>
              <Box>
                <Typography variant="subtitle1" fontWeight={600}>Add a track</Typography>
                <Typography variant="caption" color="text.secondary">Choose the language, format, and accessibility options before uploading.</Typography>
              </Box>
              <Grid container spacing={1.5}>
                <Grid item xs={12} sm={6} md={3}><TextField select fullWidth size="small" label="Track type" value={track.kind} onChange={(event) => setTrack({ ...track, kind: event.target.value, file: null })}>{['subtitle', 'audio'].filter((kind) => kind === 'subtitle' ? permissions.has('media.subtitles.manage') : permissions.has('media.audio.manage')).map((kind) => <MenuItem key={kind} value={kind}>{humanize(kind)}</MenuItem>)}</TextField></Grid>
                <Grid item xs={12} sm={6} md={2}><TextField fullWidth size="small" required label="Language code" value={track.language} onChange={(event) => setTrack({ ...track, language: event.target.value })} inputProps={{ pattern: '[A-Za-z]{2,3}([-_][A-Za-z0-9]{2,8})?', maxLength: 16 }} helperText="For example, en or bn" /></Grid>
                <Grid item xs={12} sm={6} md={3}><TextField fullWidth size="small" label="Display name" placeholder={track.kind === 'subtitle' ? 'English subtitles' : 'Bangla audio'} value={track.display_name} onChange={(event) => setTrack({ ...track, display_name: event.target.value })} /></Grid>
                <Grid item xs={12} sm={6} md={2}><TextField select fullWidth size="small" label="Track role" value={track.role} onChange={(event) => setTrack({ ...track, role: event.target.value })}>{TRACK_ROLES.map((role) => <MenuItem key={role} value={role}>{humanize(role)}</MenuItem>)}</TextField></Grid>
                <Grid item xs={12} md={2}><Button fullWidth component="label" variant="outlined" sx={{ minHeight: 40, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{track.file?.name || 'Choose file'}<input hidden type="file" accept={track.kind === 'subtitle' ? '.vtt,.srt' : 'audio/*,.m4a,.aac,.mp3,.wav,.ogg'} onChange={(event) => setTrack({ ...track, file: event.target.files?.[0] || null })} /></Button></Grid>
                <Grid item xs={12}>
                  <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1.5}>
                    <Stack direction="row" flexWrap="wrap" useFlexGap gap={{ xs: 0.5, sm: 1 }}>
                      {[
                        ['is_default', 'Default'], ['is_forced', 'Forced'], ['is_accessibility', 'Accessibility'],
                        ...(track.kind === 'audio' ? [['is_original', 'Original'], ['is_dubbed', 'Dubbed']] : []),
                      ].map(([key, label]) => <FormControlLabel key={key} sx={{ mr: 1 }} control={<Switch size="small" checked={track[key]} onChange={(event) => setTrack({ ...track, [key]: event.target.checked })} />} label={<Typography variant="body2">{label}</Typography>} />)}
                    </Stack>
                    <Button type="submit" variant="contained" disabled={busy || !track.file || (track.kind === 'subtitle' ? !permissions.has('media.subtitles.manage') : !permissions.has('media.audio.manage'))} sx={{ flexShrink: 0 }}>
                      {busy ? 'Uploading…' : 'Upload track'}
                    </Button>
                  </Stack>
                </Grid>
              </Grid>
            </Stack>
          </Box>}
        </CardContent></Card>

        <Dialog open={Boolean(trackToRemove)} onClose={() => setTrackToRemove(null)}>
          <DialogTitle>Remove media track?</DialogTitle>
          <DialogContent><Typography>The current rendition remains available while a new version is processed. The retained track file will not be permanently deleted.</Typography></DialogContent>
          <DialogActions><Button onClick={() => setTrackToRemove(null)}>Keep track</Button><Button color="error" variant="contained" disabled={busy} onClick={() => removeTrack(trackToRemove)}>Remove track</Button></DialogActions>
        </Dialog>
      </Stack>
    </DashboardContent>
  );
}

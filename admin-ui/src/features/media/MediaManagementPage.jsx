'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import LinearProgress from '@mui/material/LinearProgress';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { mediaApi } from './media.api';

const CHUNK_SIZE = 8 * 1024 * 1024;

function formatBytes(value) {
  if (value < 1024) return `${value} B`;
  if (value < 1024 ** 2) return `${(value / 1024).toFixed(1)} KB`;
  if (value < 1024 ** 3) return `${(value / 1024 ** 2).toFixed(1)} MB`;
  return `${(value / 1024 ** 3).toFixed(2)} GB`;
}

function messageFrom(error) {
  return error?.response?.data?.message || 'The media request could not be completed.';
}

export function MediaManagementPage() {
  const { user } = useAuthContext();
  const [assets, setAssets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busyAsset, setBusyAsset] = useState(null);
  const [trackLanguage, setTrackLanguage] = useState('en');
  const [contentId, setContentId] = useState('');
  const [quality, setQuality] = useState(null);
  const inputRef = useRef(null);

  const refresh = useCallback(async () => {
    try {
      const response = await mediaApi.list({ per_page: 50 });
      setAssets(response.data || []);
      setError('');
    } catch (cause) {
      setError(messageFrom(cause));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
    const timer = window.setInterval(refresh, 10000);
    return () => window.clearInterval(timer);
  }, [refresh]);

  const upload = async (file, replacesAssetId = null) => {
    if (!file) return;
    setUploading(true);
    setProgress(0);
    setError('');
    setNotice('');
    try {
      const resumeKey = `noor_media_upload:${file.name}:${file.size}:${file.lastModified}:${replacesAssetId || 'new'}`;
      const sessionId = window.localStorage.getItem(resumeKey);
      let session;
      if (sessionId) {
        try {
          session = await mediaApi.beginUpload({ file_name: file.name, size_bytes: file.size, session_id: sessionId, ...(replacesAssetId ? { replaces_asset_id: replacesAssetId } : {}) });
        } catch {
          window.localStorage.removeItem(resumeKey);
        }
      }
      if (!session) {
        session = await mediaApi.beginUpload({ file_name: file.name, size_bytes: file.size, ...(replacesAssetId ? { replaces_asset_id: replacesAssetId } : {}) });
        window.localStorage.setItem(resumeKey, session.data.id);
      }
      const { id, chunk_size: chunkSize, chunk_count: chunkCount, received_chunks: receivedChunks = [] } = session.data;
      const received = new Set(receivedChunks);
      for (let index = 0; index < chunkCount; index += 1) {
        const start = index * chunkSize;
        const chunk = file.slice(start, Math.min(start + chunkSize, file.size));
        if (!received.has(index)) {
          const body = new FormData();
          body.append('chunk', chunk, file.name);
          await mediaApi.uploadChunk(id, index, body);
        }
        setProgress(Math.round(((index + 1) / chunkCount) * 100));
      }
      await mediaApi.completeUpload(id);
      window.localStorage.removeItem(resumeKey);
      setNotice(replacesAssetId ? 'Replacement source uploaded as a new version. Transcoding has been queued.' : 'Upload complete. Transcoding has been queued.');
      await refresh();
    } catch (cause) {
      setError(messageFrom(cause));
    } finally {
      setUploading(false);
      if (inputRef.current) inputRef.current.value = '';
    }
  };

  const addTrack = async (asset, kind, file) => {
    if (!file) return;
    setBusyAsset(asset.id);
    setError('');
    setNotice('');
    try {
      const body = new FormData();
      body.append('kind', kind);
      body.append('language', trackLanguage);
      body.append('file', file, file.name);
      await mediaApi.addTrack(asset.id, body);
      setNotice(`${kind === 'subtitle' ? 'Subtitle' : 'Audio'} track added to ${asset.original_name}.`);
      await refresh();
    } catch (cause) {
      setError(messageFrom(cause));
    } finally {
      setBusyAsset(null);
    }
  };

  const removeTrack = async (asset, track) => {
    if (!window.confirm(`Remove ${track.language} ${track.kind} track from ${asset.original_name}?`)) return;
    setBusyAsset(asset.id);
    try {
      await mediaApi.deleteTrack(asset.id, track.id);
      setNotice('Track removed.');
      await refresh();
    } catch (cause) {
      setError(messageFrom(cause));
    } finally {
      setBusyAsset(null);
    }
  };

  const archive = async (asset) => {
    if (!window.confirm(`Archive ${asset.original_name}? Media files are retained for recovery.`)) return;
    setBusyAsset(asset.id);
    try {
      await mediaApi.archive(asset.id);
      setNotice('Media asset archived.');
      await refresh();
    } catch (cause) {
      setError(messageFrom(cause));
    } finally {
      setBusyAsset(null);
    }
  };

  const attachToContent = async (asset) => {
    setBusyAsset(asset.id);
    setError('');
    setNotice('');
    try {
      await mediaApi.attachToContent(asset.id, contentId.trim());
      setNotice(`Asset attached to content ${contentId.trim()}. Publication will remain blocked until processing is ready.`);
    } catch (cause) {
      setError(messageFrom(cause));
    } finally {
      setBusyAsset(null);
    }
  };

  const inspectQuality = async (asset) => {
    setBusyAsset(asset.id);
    setError('');
    try {
      const response = await mediaApi.quality(asset.id);
      setQuality(response.data);
    } catch (cause) {
      setError(messageFrom(cause));
    } finally {
      setBusyAsset(null);
    }
  };

  const retry = async (asset) => {
    setBusyAsset(asset.id);
    setError('');
    setNotice('');
    try {
      await mediaApi.retry(asset.id);
      setNotice(`A new processing attempt was queued for ${asset.original_name}.`);
      await refresh();
    } catch (cause) {
      setError(messageFrom(cause));
    } finally {
      setBusyAsset(null);
    }
  };

  const canUpload = user?.permissions?.includes('media.upload');
  const canRetry = user?.permissions?.includes('media.transcode.retry');
  const canReplace = user?.permissions?.includes('media.update');
  const canDelete = user?.permissions?.includes('media.delete');
  const canManageSubtitles = user?.permissions?.includes('media.subtitles.manage');
  const canManageAudio = user?.permissions?.includes('media.audio.manage');
  const canAttach = user?.permissions?.includes('media.update') && user?.permissions?.includes('content.update');

  return (
    <DashboardContent>
      <Stack spacing={3}>
        <Stack direction={{ xs: 'column', sm: 'row' }} alignItems={{ sm: 'center' }} justifyContent="space-between" spacing={2}>
          <Box>
            <Typography variant="h4">Media library</Typography>
            <Typography variant="body2" color="text.secondary">Private source assets, processing state, and safe retries.</Typography>
          </Box>
          {canUpload && (
            <Button variant="contained" disabled={uploading} onClick={() => inputRef.current?.click()}>
              Upload video
              <input ref={inputRef} hidden type="file" accept="video/*,.mkv,.mov,.mp4,.webm" onChange={(event) => upload(event.target.files?.[0])} />
            </Button>
          )}
        </Stack>

        {error && <Alert severity="error">{error}</Alert>}
        {notice && <Alert severity="success">{notice}</Alert>}
        {quality && <Alert severity={quality.ready ? 'success' : 'warning'} onClose={() => setQuality(null)}>
          <Typography variant="subtitle2">Asset {quality.asset_id}: {quality.ready ? 'ready for publication' : 'not ready for publication'}</Typography>
          <Typography variant="caption">{Object.entries(quality.checks || {}).map(([check, passed]) => `${check.replaceAll('_', ' ')} ${passed ? '✓' : '✗'}`).join(' · ')}</Typography>
        </Alert>}
        {(canManageSubtitles || canManageAudio) && <TextField label="Track language" size="small" value={trackLanguage} onChange={(event) => setTrackLanguage(event.target.value)} inputProps={{ maxLength: 12, pattern: '[A-Za-z]{2,3}([-_][A-Za-z0-9]{2,8})?' }} sx={{ maxWidth: 220 }} />}
        {canAttach && <TextField label="Draft content ID" size="small" value={contentId} onChange={(event) => setContentId(event.target.value)} sx={{ maxWidth: 360 }} helperText="Attach an asset to an unpublished catalog item by its ID." />}
        {uploading && (
          <Card><CardContent><Stack spacing={1}>
            <Typography variant="body2">Uploading secure chunks… {progress}%</Typography>
            <LinearProgress variant="determinate" value={progress} />
          </Stack></CardContent></Card>
        )}

        <Card>
          <Table size="small" aria-label="Media assets">
            <TableHead><TableRow>
              <TableCell>Asset</TableCell><TableCell>Size</TableCell><TableCell>Status</TableCell><TableCell>Version</TableCell><TableCell>Tracks / processing</TableCell><TableCell align="right">Action</TableCell>
            </TableRow></TableHead>
            <TableBody>
              {assets.map((asset) => (
                <TableRow key={asset.id}>
                  <TableCell><Stack><Typography variant="subtitle2">{asset.original_name}</Typography><Typography variant="caption" color="text.secondary">{asset.mime_type}</Typography></Stack></TableCell>
                  <TableCell>{formatBytes(Number(asset.size_bytes))}</TableCell>
                  <TableCell><Stack><Typography variant="body2" sx={{ textTransform: 'capitalize' }}>{asset.status}</Typography>{asset.failure_code && <Typography variant="caption" color="error.main">{asset.failure_code}</Typography>}</Stack></TableCell>
                  <TableCell>{asset.version}</TableCell>
                  <TableCell><Stack spacing={0.5}>{(asset.asset_tracks || []).map((track) => <Stack key={track.id} direction="row" spacing={0.5} alignItems="center"><Typography variant="caption">{track.language} {track.kind}: {track.original_name}</Typography>{((track.kind === 'subtitle' && canManageSubtitles) || (track.kind === 'audio' && canManageAudio)) && <Button size="small" disabled={busyAsset === asset.id} onClick={() => removeTrack(asset, track)}>Remove</Button>}</Stack>)}{(asset.processing_jobs || []).map((job) => <Typography key={job.id} variant="caption">{job.type}: {job.status} ({job.attempts}/{job.max_attempts})</Typography>)}{!(asset.asset_tracks || []).length && !(asset.processing_jobs || []).length && '—'}</Stack></TableCell>
                  <TableCell align="right"><Stack direction="row" justifyContent="flex-end" flexWrap="wrap">
                    {canRetry && asset.status === 'failed' && <Button size="small" disabled={busyAsset === asset.id} onClick={() => retry(asset)}>Retry</Button>}
                    <Button size="small" disabled={busyAsset === asset.id} onClick={() => inspectQuality(asset)}>QC</Button>
                    {canReplace && <Button component="label" size="small" disabled={busyAsset === asset.id}>Replace<input hidden type="file" accept="video/*,.mkv,.mov,.mp4,.webm" onChange={(event) => upload(event.target.files?.[0], asset.id)} /></Button>}
                    {canManageSubtitles && <Button component="label" size="small" disabled={busyAsset === asset.id}>Subtitle<input hidden type="file" accept=".srt,.vtt" onChange={(event) => addTrack(asset, 'subtitle', event.target.files?.[0])} /></Button>}
                    {canManageAudio && <Button component="label" size="small" disabled={busyAsset === asset.id}>Audio<input hidden type="file" accept=".aac,.m4a,.mp3" onChange={(event) => addTrack(asset, 'audio', event.target.files?.[0])} /></Button>}
                    {canDelete && <Button size="small" color="error" disabled={busyAsset === asset.id || ['processing', 'queued'].includes(asset.status)} onClick={() => archive(asset)}>Archive</Button>}
                    {canAttach && <Button size="small" disabled={busyAsset === asset.id || !contentId.trim()} onClick={() => attachToContent(asset)}>Attach</Button>}
                  </Stack></TableCell>
                </TableRow>
              ))}
              {!loading && assets.length === 0 && <TableRow><TableCell colSpan={6}><Typography color="text.secondary" align="center" sx={{ py: 4 }}>No media assets have been uploaded.</Typography></TableCell></TableRow>}
              {loading && assets.length === 0 && <TableRow><TableCell colSpan={6}><Typography color="text.secondary" align="center" sx={{ py: 4 }}>Loading media assets…</Typography></TableCell></TableRow>}
            </TableBody>
          </Table>
        </Card>
      </Stack>
    </DashboardContent>
  );
}

'use client';

import { useCallback, useEffect, useRef, useState } from 'react';

import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import LinearProgress from '@mui/material/LinearProgress';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { mediaApi } from '../media.api';

const CATEGORIES = [
  ['movie', 'Movie'], ['episode', 'TV episode'], ['trailer', 'Trailer'], ['teaser', 'Teaser'],
  ['standalone_video', 'Standalone video'], ['promotional_video', 'Promotional video'], ['video', 'Other video'],
];

function formatBytes(value) {
  if (value < 1024) return `${value} B`;
  if (value < 1024 ** 2) return `${(value / 1024).toFixed(1)} KB`;
  if (value < 1024 ** 3) return `${(value / 1024 ** 2).toFixed(1)} MB`;
  return `${(value / 1024 ** 3).toFixed(2)} GB`;
}

async function hashChunk(blob) {
  const digest = await window.crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
  return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function errorMessage(error) {
  return error.response?.data?.data?.message || error.response?.data?.message || error.message || 'Upload failed.';
}

export function MediaUploadManager({ onCompleted, replacementAsset = null }) {
  const [items, setItems] = useState([]);
  const [category, setCategory] = useState(replacementAsset?.category || 'video');
  const [concurrency, setConcurrency] = useState(2);
  const [dragOver, setDragOver] = useState(false);
  const [notice, setNotice] = useState('');
  const tasks = useRef(new Map());
  const queue = useRef([]);
  const active = useRef(0);
  const concurrencyRef = useRef(concurrency);
  concurrencyRef.current = concurrency;
  const refreshRows = useCallback(() => setItems(Array.from(tasks.current.values()).map((task) => ({ ...task, file: undefined }))), []);

  const updateTask = (id, changes) => {
    const task = tasks.current.get(id);
    if (!task) return;
    Object.assign(task, changes);
    refreshRows();
  };

  const isCancelled = (task) => task.cancelled;
  const waitIfPaused = async (task) => {
    while (task.paused && !task.cancelled) await new Promise((resolve) => window.setTimeout(resolve, 120));
    if (task.cancelled) throw new Error('Upload cancelled.');
  };

  const uploadTask = async (task) => {
    const key = `noor_media_upload:${task.category}:${task.displayName}:${task.replacesAssetId || 'new'}:${task.file.name}:${task.file.size}:${task.file.lastModified}`;
    let sessionId = window.localStorage.getItem(key);
    const begin = (id) => mediaApi.beginUpload({
      file_name: task.file.name, size_bytes: task.file.size, display_name: task.displayName,
      category: task.category, ...(task.replacesAssetId ? { replaces_asset_id: task.replacesAssetId } : {}), ...(id ? { session_id: id } : {}),
    });
    let session;
    if (sessionId) {
      try {
        session = await begin(sessionId);
      } catch {
        await mediaApi.cancelUpload(sessionId).catch(() => {});
        window.localStorage.removeItem(key);
        sessionId = null;
      }
    }
    if (!session) {
      session = await begin(null);
      sessionId = session.data.id;
      window.localStorage.setItem(key, sessionId);
    }

    let chunkSize = session.data.chunk_size;
    let chunkCount = session.data.chunk_count;
    let received = new Set(session.data.received_chunks || []);
    const savedChecksums = session.data.chunk_checksums || {};
    let needsRestart = false;
    for (const index of received) {
      const start = index * chunkSize;
      const checksum = await hashChunk(task.file.slice(start, Math.min(start + chunkSize, task.file.size)));
      if (savedChecksums[String(index)] && savedChecksums[String(index)] !== checksum) {
        needsRestart = true;
        break;
      }
    }
    if (needsRestart) {
      await mediaApi.cancelUpload(sessionId).catch(() => {});
      window.localStorage.removeItem(key);
      session = await begin(null);
      sessionId = session.data.id;
      window.localStorage.setItem(key, sessionId);
      chunkSize = session.data.chunk_size;
      chunkCount = session.data.chunk_count;
      received = new Set();
    }
    task.sessionId = sessionId;
    task.totalBytes = task.file.size;
    task.uploadedBytes = Array.from(received).reduce((sum, index) => sum + Math.min(chunkSize, task.file.size - index * chunkSize), 0);
    task.startedAt = Date.now();
    updateTask(task.id, { status: task.paused ? 'paused' : 'uploading', uploadedBytes: task.uploadedBytes, progress: Math.round(task.uploadedBytes * 100 / task.totalBytes) });

    for (let index = 0; index < chunkCount; index += 1) {
      await waitIfPaused(task);
      const start = index * chunkSize;
      const chunk = task.file.slice(start, Math.min(start + chunkSize, task.file.size));
      if (!received.has(index)) {
        const chunkHash = await hashChunk(chunk);
        const body = new FormData();
        body.append('chunk', chunk, task.file.name);
        body.append('chunk_sha256', chunkHash);
        await mediaApi.uploadChunk(sessionId, index, body);
        task.uploadedBytes += chunk.size;
      }
      const elapsedSeconds = Math.max((Date.now() - task.startedAt) / 1000, 0.1);
      const speed = task.uploadedBytes / elapsedSeconds;
      const remaining = Math.max(task.totalBytes - task.uploadedBytes, 0);
      updateTask(task.id, {
        uploadedBytes: task.uploadedBytes, progress: Math.round(task.uploadedBytes * 100 / task.totalBytes),
        speedBytesPerSecond: speed, etaSeconds: speed ? Math.ceil(remaining / speed) : null,
      });
    }

    await waitIfPaused(task);
    updateTask(task.id, { status: 'processing_queue', progress: 100 });
    let completion;
    try {
      completion = await mediaApi.completeUpload(sessionId);
    } catch (error) {
      const duplicate = error.response?.data?.data;
      if (error.response?.status === 409 && duplicate?.duplicate) {
        window.localStorage.removeItem(key);
        updateTask(task.id, { status: 'duplicate', message: `Exact duplicate of asset ${duplicate.existing_asset_id}.` });
        return;
      }
      throw error;
    }
    const result = completion.data || {};
    window.localStorage.removeItem(key);
    if (result.duplicate) {
      updateTask(task.id, { status: 'duplicate', message: `Exact duplicate of asset ${result.existing_asset_id}.` });
      return;
    }
    if (result.processing_queue === 'unavailable') {
      updateTask(task.id, { status: 'queue_unavailable', message: 'Upload stored, but processing was not queued. An administrator can retry it.' });
    } else {
      updateTask(task.id, { status: 'complete', message: task.replacesAssetId ? 'Replacement uploaded as a new version and processing was queued.' : 'Upload completed and processing was queued.' });
    }
    onCompleted?.(result);
  };

  const startQueue = useCallback(() => {
    while (active.current < concurrencyRef.current && queue.current.length > 0) {
      const id = queue.current.shift();
      const task = tasks.current.get(id);
      if (!task || task.cancelled || task.status !== 'queued') continue;
      active.current += 1;
      updateTask(id, { status: 'uploading', error: '' });
      uploadTask(task)
        .catch((error) => {
          if (!isCancelled(task)) updateTask(id, { status: 'failed', error: errorMessage(error) });
        })
        .finally(() => {
          active.current -= 1;
          startQueue();
        });
    }
  }, [onCompleted]);

  useEffect(() => { startQueue(); }, [concurrency, startQueue]);

  const addFiles = (fileList) => {
    if (replacementAsset && !['ready', 'failed'].includes(replacementAsset.status)) {
      setNotice('Wait for the latest source version to finish processing before uploading another replacement.');
      return;
    }
    if (replacementAsset && Array.from(tasks.current.values()).some((task) => task.replacesAssetId === replacementAsset.id && !['complete', 'duplicate', 'queue_unavailable', 'cancelled'].includes(task.status))) {
      setNotice('A replacement upload is already active for this source version.');
      return;
    }
    const selectedFiles = Array.from(fileList || []);
    const files = replacementAsset ? selectedFiles.slice(0, 1) : selectedFiles;
    if (replacementAsset && selectedFiles.length > 1) setNotice('Only one replacement source can be queued for this asset at a time.');
    const added = [];
    for (const file of files) {
      const displayName = replacementAsset?.display_name || file.name.replace(/\.[^.]+$/, '').trim();
      if (Array.from(tasks.current.values()).some((task) => task.file.name === file.name && task.file.size === file.size && !['failed', 'cancelled'].includes(task.status))) continue;
      const id = window.crypto.randomUUID();
      const task = { id, file, displayName, category: replacementAsset?.category || category, replacesAssetId: replacementAsset?.id || null, status: 'queued', progress: 0, uploadedBytes: 0, totalBytes: file.size, cancelled: false, paused: false };
      tasks.current.set(id, task);
      queue.current.push(id);
      added.push(id);
    }
    if (added.length) {
      setNotice(`${added.length} file${added.length === 1 ? '' : 's'} added to the upload queue.`);
      refreshRows();
      startQueue();
    }
  };

  const pauseTask = (id) => {
    const task = tasks.current.get(id);
    if (!task || !['uploading', 'queued'].includes(task.status)) return;
    task.paused = true;
    updateTask(id, { status: 'paused' });
  };

  const resumeTask = (id) => {
    const task = tasks.current.get(id);
    if (!task || task.cancelled) return;
    task.paused = false;
    if (task.status === 'failed') {
      task.sessionId = null;
      updateTask(id, { status: 'queued' });
      queue.current.push(id);
      startQueue();
    } else if (task.sessionId) updateTask(id, { status: 'uploading' });
    else {
      updateTask(id, { status: 'queued' });
      queue.current.push(id);
      startQueue();
    }
  };

  const cancelTask = async (id) => {
    const task = tasks.current.get(id);
    if (!task) return;
    task.cancelled = true;
    task.paused = false;
    if (task.sessionId) await mediaApi.cancelUpload(task.sessionId).catch(() => {});
    updateTask(id, { status: 'cancelled', error: '', message: 'Upload cancelled; temporary chunks were removed.' });
  };

  useEffect(() => () => {
    for (const task of tasks.current.values()) {
      task.paused = true;
      task.cancelled = true;
    }
  }, []);

  return (
    <Stack spacing={2.5}>
      {notice && <Alert severity="info" onClose={() => setNotice('')}>{notice}</Alert>}
      <Card><CardContent>
        <Stack spacing={2}>
          <Box>
            <Typography variant="h6">Upload manager</Typography>
            <Typography variant="body2" color="text.secondary">Resumable, checksum-verified video uploads. Files are read in bounded chunks.{replacementAsset ? ` This upload creates a replacement version for ${replacementAsset.display_name || replacementAsset.original_name}.` : ''}</Typography>
          </Box>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            {!replacementAsset && <TextField select label="Asset category" size="small" value={category} onChange={(event) => setCategory(event.target.value)} sx={{ minWidth: 220 }}>
              {CATEGORIES.map(([value, label]) => <MenuItem key={value} value={value}>{label}</MenuItem>)}
            </TextField>}
            <TextField select label="Concurrent uploads" size="small" value={concurrency} onChange={(event) => setConcurrency(Number(event.target.value))} sx={{ minWidth: 190 }}>
              {[1, 2, 3].map((value) => <MenuItem key={value} value={value}>{value} at a time</MenuItem>)}
            </TextField>
            <Button component="label" variant="contained" disabled={Boolean(replacementAsset && !['ready', 'failed'].includes(replacementAsset.status))}>{replacementAsset ? 'Select replacement source' : 'Select video files'}<input hidden multiple={!replacementAsset} type="file" accept="video/*,.mkv,.mov,.mp4,.webm" onChange={(event) => { addFiles(event.target.files); event.target.value = ''; }} /></Button>
          </Stack>
          <Box
            onDragOver={(event) => { event.preventDefault(); setDragOver(true); }}
            onDragLeave={() => setDragOver(false)}
            onDrop={(event) => { event.preventDefault(); setDragOver(false); addFiles(event.dataTransfer.files); }}
            sx={{ p: 3, textAlign: 'center', border: 1, borderStyle: 'dashed', borderColor: dragOver ? 'primary.main' : 'divider', borderRadius: 2, bgcolor: dragOver ? 'action.hover' : 'background.neutral', opacity: replacementAsset && !['ready', 'failed'].includes(replacementAsset.status) ? 0.55 : 1 }}
          >
            <Typography variant="body2">{replacementAsset ? 'Drop one replacement source file here.' : 'Drop one or more video files here to add them to the queue.'}</Typography>
          </Box>
        </Stack>
      </CardContent></Card>

      {items.length > 0 && (() => {
        const totalBytes = items.reduce((sum, item) => sum + (item.totalBytes || 0), 0);
        const uploadedBytes = items.reduce((sum, item) => sum + (item.uploadedBytes || 0), 0);
        return <Card><CardContent><Stack spacing={1}>
          <Stack direction="row" justifyContent="space-between"><Typography variant="subtitle2">Overall upload progress</Typography><Typography variant="body2">{formatBytes(uploadedBytes)} / {formatBytes(totalBytes)}</Typography></Stack>
          <LinearProgress variant="determinate" value={totalBytes ? uploadedBytes * 100 / totalBytes : 0} />
        </Stack></CardContent></Card>;
      })()}

      {items.map((item) => (
        <Card key={item.id}><CardContent>
          <Stack spacing={1.25}>
            <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1}>
              <Box sx={{ minWidth: 0 }}>
                <Typography variant="subtitle2" noWrap>{item.displayName}</Typography>
                <Typography variant="caption" color="text.secondary">{item.file?.name || 'Selected media'} · {formatBytes(item.uploadedBytes || 0)} / {formatBytes(item.totalBytes || 0)}</Typography>
              </Box>
              <Stack direction="row" alignItems="center" spacing={0.5}>
                <Typography variant="caption" color={item.error ? 'error.main' : 'text.secondary'} sx={{ textTransform: 'capitalize' }}>{item.status.replaceAll('_', ' ')}</Typography>
                {['queued', 'uploading'].includes(item.status) && <Button size="small" onClick={() => pauseTask(item.id)}>Pause</Button>}
                {item.status === 'paused' && <Button size="small" onClick={() => resumeTask(item.id)}>Resume</Button>}
                {!['complete', 'duplicate', 'queue_unavailable', 'cancelled', 'processing_queue'].includes(item.status) && <Button size="small" color="error" onClick={() => cancelTask(item.id)}>Cancel</Button>}
                {item.status === 'failed' && <Button size="small" onClick={() => resumeTask(item.id)}>Retry</Button>}
              </Stack>
            </Stack>
            <LinearProgress variant="determinate" value={item.progress || 0} />
            <Stack direction="row" justifyContent="space-between" flexWrap="wrap" gap={0.5}>
              {item.message && <Typography variant="caption" color={item.status === 'queue_unavailable' ? 'warning.main' : 'text.secondary'}>{item.message}</Typography>}
              {item.error && <Typography variant="caption" color="error.main">{item.error}</Typography>}
              {item.status === 'uploading' && item.speedBytesPerSecond > 0 && <Typography variant="caption" color="text.secondary">{formatBytes(item.speedBytesPerSecond)}/s · about {Math.ceil((item.etaSeconds || 0) / 60)} min left</Typography>}
            </Stack>
          </Stack>
        </CardContent></Card>
      ))}
      {!items.length && <Typography color="text.secondary" align="center" sx={{ py: 4 }}>No upload tasks yet.</Typography>}
    </Stack>
  );
}

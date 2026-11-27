'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';

import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Grid from '@mui/material/Grid';
import LinearProgress from '@mui/material/LinearProgress';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

import { mediaApi } from '../media.api';

const metrics = [
  ['Total assets', 'total_assets'], ['Ready', 'ready_assets'], ['Uploaded', 'uploaded_assets'], ['Queued', 'queued_assets'],
  ['Processing', 'processing_assets'], ['Failed assets', 'failed_assets'], ['Awaiting QC', 'awaiting_quality_review'],
  ['Archived', 'archived_assets'], ['Active jobs', 'active_processing_jobs'], ['Failed jobs', 'failed_processing_jobs'],
];

function formatBytes(bytes) {
  if (bytes == null) return 'Unavailable';
  const value = Number(bytes);
  if (!value) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const index = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
  return `${(value / 1024 ** index).toFixed(index > 1 ? 1 : 0)} ${units[index]}`;
}

export function MediaOverview() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const refresh = useCallback(async () => {
    setError('');
    try {
      const response = await mediaApi.overview();
      setData(response.data);
    } catch (cause) {
      setError(cause.response?.data?.message || 'Media operations data could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  if (loading) return <Grid container spacing={2}>{metrics.map(([label]) => <Grid item xs={6} md={3} key={label}><Skeleton height={112} variant="rounded" /></Grid>)}</Grid>;

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}
      <Grid container spacing={2}>
        {metrics.map(([label, key]) => (
          <Grid item xs={6} sm={4} lg={3} key={key}>
            <Card sx={{ height: '100%' }}><CardContent>
              <Typography color="text.secondary" variant="body2">{label}</Typography>
              <Typography variant="h4" sx={{ mt: 1 }}>{data?.[key] == null ? '—' : Number(data[key]).toLocaleString()}</Typography>
            </CardContent></Card>
          </Grid>
        ))}
      </Grid>

      <Grid container spacing={2}>
        <Grid item xs={12} md={5}>
          <Card sx={{ height: '100%' }}><CardContent>
            <Typography variant="h6">Source storage</Typography>
            <Typography variant="h3" sx={{ mt: 1 }}>{formatBytes(data?.source_storage_bytes_estimated)}</Typography>
            <Typography variant="caption" color="text.secondary">Application metadata estimate. Provider-reported usage is unavailable.</Typography>
            <Stack spacing={1.5} sx={{ mt: 3 }}>
              {Object.entries(data?.storage_by_category_bytes_estimated || {}).map(([category, bytes]) => {
                const total = Number(data?.source_storage_bytes_estimated || 0);
                const percent = total ? Math.min(100, Number(bytes) * 100 / total) : 0;
                return <Box key={category}>
                  <Stack direction="row" justifyContent="space-between" sx={{ mb: 0.5 }}>
                    <Typography variant="body2" sx={{ textTransform: 'capitalize' }}>{category.replaceAll('_', ' ')}</Typography>
                    <Typography variant="caption" color="text.secondary">{formatBytes(bytes)}</Typography>
                  </Stack>
                  <LinearProgress variant="determinate" value={percent} />
                </Box>;
              })}
              {!Object.keys(data?.storage_by_category_bytes_estimated || {}).length && <Typography color="text.secondary" variant="body2">No storage records are available.</Typography>}
            </Stack>
          </CardContent></Card>
        </Grid>
        <Grid item xs={12} md={7}>
          <Card sx={{ height: '100%' }}><CardContent>
            <Typography variant="h6" sx={{ mb: 2 }}>Recent processing failures</Typography>
            <Stack spacing={1.5}>
              {(data?.recent_processing_failures || []).map((failure) => (
                <Box key={failure.id} sx={{ p: 1.5, border: 1, borderColor: 'divider', borderRadius: 1.5 }}>
                  <Typography variant="subtitle2">{failure.display_name || failure.original_name || `Asset ${failure.media_asset_id}`}</Typography>
                  <Typography variant="body2" color="error.main">{failure.failure_code || 'Unknown failure'}</Typography>
                  <Typography variant="caption" color="text.secondary">{failure.updated_at ? new Date(failure.updated_at).toLocaleString() : 'Timestamp unavailable'}</Typography>
                </Box>
              ))}
              {!data?.recent_processing_failures?.length && <Typography color="text.secondary" variant="body2">No processing failures are recorded.</Typography>}
            </Stack>
          </CardContent></Card>
        </Grid>
      </Grid>

      <Card><CardContent>
        <Typography variant="h6" sx={{ mb: 2 }}>Recent uploads</Typography>
        <Stack spacing={1.25}>
          {(data?.recent_uploads || []).map((upload) => <Box key={upload.id} component={Link} href={`/dashboard/media/assets/${upload.id}`} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 2, p: 1.25, border: 1, borderColor: 'divider', borderRadius: 1.5, color: 'inherit', textDecoration: 'none', '&:hover': { bgcolor: 'action.hover' } }}>
            <Box sx={{ minWidth: 0 }}><Typography variant="subtitle2" noWrap>{upload.display_name || upload.original_name}</Typography><Typography variant="caption" color="text.secondary">{upload.created_at ? new Date(upload.created_at).toLocaleString() : 'Date unavailable'} · {formatBytes(upload.size_bytes)}</Typography></Box>
            <Typography variant="caption" sx={{ textTransform: 'capitalize', flexShrink: 0 }}>{String(upload.status || '').replaceAll('_', ' ')}</Typography>
          </Box>)}
          {!data?.recent_uploads?.length && <Typography variant="body2" color="text.secondary">No uploads are recorded.</Typography>}
        </Stack>
      </CardContent></Card>
    </Stack>
  );
}

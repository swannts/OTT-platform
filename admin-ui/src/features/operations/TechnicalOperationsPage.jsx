'use client';

import { useCallback, useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Grid from '@mui/material/Grid';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { hasPermission } from 'src/features/rbac/permissions';
import { rbacApi } from 'src/features/rbac/api/rbac.api';
import { RbacPageHeader } from 'src/features/rbac/components/RbacPageHeader';

const serviceLabels = {
  application: 'Laravel API', database: 'Database', redis: 'Redis', rabbitmq: 'RabbitMQ', rabbitmq_management: 'RabbitMQ management', go_worker: 'Go worker', backups: 'Backups',
};

function StatusChip({ status }) {
  const color = status === 'ok' ? 'success' : status === 'degraded' ? 'warning' : 'error';
  return <Chip size="small" color={color} label={status || 'unknown'} />;
}

function formatBytes(value) {
  if (typeof value !== 'number' || !Number.isFinite(value)) return 'not reported';
  if (value < 1024) return `${value} B`;
  const units = ['KiB', 'MiB', 'GiB', 'TiB'];
  let size = value;
  let unit = -1;
  do {
    size /= 1024;
    unit += 1;
  } while (size >= 1024 && unit < units.length - 1);
  return `${size.toFixed(1)} ${units[unit]}`;
}

function formatPercent(value) {
  return typeof value === 'number' && Number.isFinite(value) ? `${value}%` : 'not reported';
}

export function TechnicalOperationsPage() {
  const { user } = useAuthContext();
  const canHealth = hasPermission(user, 'operations.health.view');
  const canJobs = hasPermission(user, 'operations.jobs.view');
  const canRetry = hasPermission(user, 'operations.jobs.retry');
  const canIncidents = hasPermission(user, 'operations.incidents.manage');
  const canPlayback = hasPermission(user, 'analytics.playback.view');
  const [health, setHealth] = useState(null);
  const [jobs, setJobs] = useState([]);
  const [incidents, setIncidents] = useState([]);
  const [playback, setPlayback] = useState([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ title: '', summary: '', severity: 'medium' });

  const loadOperations = useCallback(() => Promise.allSettled([
      ...(canHealth ? [rbacApi.operationsHealth()] : []),
      ...(canJobs ? [rbacApi.operationsJobs({ per_page: 50 })] : []),
      ...(canIncidents ? [rbacApi.operationsIncidents({ per_page: 50 })] : []),
      ...(canPlayback ? [rbacApi.operationsPlayback()] : []),
    ]), [canHealth, canIncidents, canJobs, canPlayback]);

  const applyOperations = useCallback((requests) => {
    let index = 0;
    const failures = [];
    if (canHealth) { const result = requests[index++]; if (result.status === 'fulfilled') setHealth(result.value.data); else failures.push('service health'); }
    if (canJobs) { const result = requests[index++]; if (result.status === 'fulfilled') setJobs(result.value.data || []); else failures.push('failed jobs'); }
    if (canIncidents) { const result = requests[index++]; if (result.status === 'fulfilled') setIncidents(result.value.data || []); else failures.push('incidents'); }
    if (canPlayback) { const result = requests[index++]; if (result.status === 'fulfilled') setPlayback(result.value.data?.events || []); else failures.push('playback diagnostics'); }
    if (failures.length) setError(`Could not load ${failures.join(', ')}.`);
  }, [canHealth, canJobs, canIncidents, canPlayback]);

  const refresh = useCallback(async () => {
    setError('');
    applyOperations(await loadOperations());
  }, [applyOperations, loadOperations]);

  useEffect(() => {
    let active = true;
    loadOperations()
      .then((requests) => {
        if (active) applyOperations(requests);
      })
      .catch(() => {
        if (active) setError('The operations overview could not be loaded.');
      });
    return () => {
      active = false;
    };
  }, [applyOperations, loadOperations]);

  const runAction = async (action) => {
    setBusy(true);
    setError('');
    try { await action(); await refresh(); }
    catch (actionError) { setError(actionError.response?.data?.message || 'The operation could not be completed.'); }
    finally { setBusy(false); }
  };

  const createIncident = (event) => {
    event.preventDefault();
    return runAction(async () => {
      await rbacApi.createOperationsIncident({ ...form, affected_services: [] });
      setForm({ title: '', summary: '', severity: 'medium' });
    });
  };

  return <DashboardContent>
    <RbacPageHeader title="Technical operations" description="Live dependency checks, bounded failure queues, playback error aggregates, and an audited incident workflow." />
    {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
    <Stack direction="row" justifyContent="flex-end" sx={{ mb: 2 }}><Button onClick={refresh} disabled={busy}>Refresh</Button></Stack>

    {canHealth && <Card sx={{ mb: 3 }}><CardContent>
      <Stack direction="row" justifyContent="space-between" sx={{ mb: 2 }}>
        <Typography variant="h6">Service health</Typography>
        <StatusChip status={health?.status || 'loading'} />
      </Stack>
      <Grid container spacing={2}>
        {Object.entries(health?.services || {}).map(([key, check]) => <Grid item xs={12} sm={6} md={4} key={key}>
          <Stack direction="row" alignItems="center" justifyContent="space-between" sx={{ p: 1.5, border: 1, borderColor: 'divider', borderRadius: 1 }}>
            <Typography>{serviceLabels[key] || key.replaceAll('_', ' ')}</Typography><StatusChip status={check.status} />
          </Stack>
        </Grid>)}
      </Grid>
      <Typography variant="caption" color="text.secondary" display="block" sx={{ mt: 2 }}>
        Queue connection: {health?.queue?.connection || '—'} · Waiting: {health?.queue?.queued ?? 'not reported'} · Reserved: {health?.queue?.reserved ?? 'not reported'} · Failed: {health?.queue?.failed ?? 'not reported'} · Checked: {health?.checked_at || '—'}
      </Typography>
      {health?.rabbitmq_queues && <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap" sx={{ mt: 1.5 }}>
        {health.rabbitmq_queues.map((queue) => <Chip key={queue.name} variant="outlined" label={`${queue.name}: ${queue.messages} messages, ${queue.consumers} consumers`} />)}
      </Stack>}
      {health?.backups?.disks?.length > 0 && <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap" sx={{ mt: 1.5 }}>
        {health.backups.disks.map((disk, index) => <Chip key={index} variant="outlined" label={`Backup disk ${index + 1}: ${disk.backup_count} archives · ${disk.latest_at || 'no recent backup'}`} />)}
      </Stack>}
      {health?.runtime_resources && <Stack spacing={0.5} sx={{ mt: 2 }}>
        <Typography variant="subtitle2">Container resources</Typography>
        <Typography variant="caption" color="text.secondary">
          Memory: {formatBytes(health.runtime_resources.memory?.used_bytes)} / {formatBytes(health.runtime_resources.memory?.limit_bytes)} ({formatPercent(health.runtime_resources.memory?.used_percent)}) ·
          CPU used: {health.runtime_resources.cpu?.usage_seconds ?? 'not reported'} s ·
          Local storage available: {formatBytes(health.runtime_resources.storage?.available_bytes)} / {formatBytes(health.runtime_resources.storage?.total_bytes)}
        </Typography>
      </Stack>}
    </CardContent></Card>}

    {canPlayback && <Card sx={{ mb: 3 }}><CardContent>
      <Typography variant="h6" sx={{ mb: 2 }}>Playback events · last 24 hours</Typography>
      {playback.length ? <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap">
        {playback.map((event) => <Chip key={event.event_code} variant="outlined" label={`${event.event_code}: ${event.event_count}`} />)}
      </Stack> : <Typography color="text.secondary">No playback diagnostic events in this window.</Typography>}
    </CardContent></Card>}

    {canJobs && <Card sx={{ mb: 3 }}><CardContent>
      <Typography variant="h6" sx={{ mb: 2 }}>Failed Laravel jobs</Typography>
      <Table size="small"><TableHead><TableRow><TableCell>Job</TableCell><TableCell>Queue</TableCell><TableCell>Failed at</TableCell><TableCell align="right">Action</TableCell></TableRow></TableHead>
        <TableBody>{jobs.map((job) => <TableRow key={job.id}><TableCell>{job.job_type}</TableCell><TableCell>{job.queue}</TableCell><TableCell>{job.failed_at}</TableCell>
          <TableCell align="right">{canRetry && job.retry_allowed && <Button size="small" disabled={busy} onClick={() => runAction(() => rbacApi.retryOperationsJob(job.id))}>Retry</Button>}</TableCell>
        </TableRow>)}{jobs.length === 0 && <TableRow><TableCell colSpan={4} align="center">No failed Laravel jobs.</TableCell></TableRow>}</TableBody>
      </Table>
      <Typography variant="caption" color="text.secondary" display="block" sx={{ mt: 1 }}>Payloads and exception traces are not exposed. Only explicitly idempotent campaign delivery jobs can be retried here.</Typography>
    </CardContent></Card>}

    {canIncidents && <Grid container spacing={3}>
      <Grid item xs={12} md={5}><Card><CardContent component="form" onSubmit={createIncident}>
        <Typography variant="h6" sx={{ mb: 2 }}>Open incident</Typography>
        <Stack spacing={2}>
          <TextField required label="Title" inputProps={{ maxLength: 180 }} value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} />
          <TextField required multiline minRows={3} label="Summary (no secrets or customer credentials)" value={form.summary} onChange={(event) => setForm({ ...form, summary: event.target.value })} />
          <TextField select label="Severity" value={form.severity} onChange={(event) => setForm({ ...form, severity: event.target.value })}>
            {['low', 'medium', 'high', 'critical'].map((severity) => <MenuItem key={severity} value={severity}>{severity}</MenuItem>)}
          </TextField>
          <Button type="submit" variant="contained" disabled={busy}>Create incident</Button>
        </Stack>
      </CardContent></Card></Grid>
      <Grid item xs={12} md={7}><Card><CardContent>
        <Typography variant="h6" sx={{ mb: 2 }}>Incident log</Typography>
        <Stack spacing={1.5}>{incidents.map((incident) => <Stack key={incident.id} direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'center' }} justifyContent="space-between" sx={{ p: 1.5, border: 1, borderColor: 'divider', borderRadius: 1 }}>
          <Stack><Typography variant="subtitle2">{incident.title}</Typography><Typography variant="body2" color="text.secondary">{incident.summary}</Typography><Typography variant="caption">{incident.severity} · opened {incident.created_at}</Typography></Stack>
          <Stack direction="row" spacing={1} alignItems="center"><Chip size="small" label={incident.status} />
            {incident.status === 'open' && <Button size="small" disabled={busy} onClick={() => runAction(() => rbacApi.updateOperationsIncident(incident.id, { status: 'acknowledged' }))}>Acknowledge</Button>}
            {incident.status !== 'resolved' && <Button size="small" disabled={busy} onClick={() => runAction(() => rbacApi.updateOperationsIncident(incident.id, { status: 'resolved' }))}>Resolve</Button>}
          </Stack>
        </Stack>)}{incidents.length === 0 && <Typography color="text.secondary">No incidents recorded.</Typography>}</Stack>
      </CardContent></Card></Grid>
    </Grid>}
  </DashboardContent>;
}

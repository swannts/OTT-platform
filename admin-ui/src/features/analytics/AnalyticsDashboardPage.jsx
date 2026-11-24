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
import { hasAnyPermission, hasPermission } from 'src/features/rbac/permissions';
import { RbacPageHeader } from 'src/features/rbac/components/RbacPageHeader';
import { analyticsApi } from './analytics.api';

const GROUPS = [
  { key: 'audience', label: 'Audience', permission: 'analytics.audience.view' },
  { key: 'subscriptions', label: 'Subscriptions', permission: 'analytics.subscriptions.view' },
  { key: 'content', label: 'Content', permission: 'analytics.content.view' },
  { key: 'revenue', label: 'Revenue', permission: 'analytics.revenue.view' },
  { key: 'playback', label: 'Playback quality', permission: 'analytics.playback.view' },
];
const today = new Date().toISOString().slice(0, 10);
const priorMonth = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
const errorText = (error) => error?.response?.data?.message || 'The analytics request could not be completed.';
const dateText = (value) => (value ? new Date(value).toLocaleString() : '—');

function Metric({ title, value }) {
  return <Card><CardContent><Typography variant="body2" color="text.secondary">{title}</Typography><Typography variant="h4">{value ?? '—'}</Typography></CardContent></Card>;
}

function DataTable({ title, columns, rows, keyField }) {
  return <Card sx={{ mt: 2 }}><CardContent><Typography variant="h6" sx={{ mb: 1 }}>{title}</Typography>
    {!rows?.length ? <Typography color="text.secondary">No qualifying data for this period.</Typography> : <Table size="small"><TableHead><TableRow>{columns.map((column) => <TableCell key={column.key}>{column.label}</TableCell>)}</TableRow></TableHead>
      <TableBody>{rows.map((row, index) => <TableRow key={row[keyField] || `${title}-${index}`}>{columns.map((column) => <TableCell key={column.key}>{row[column.key] ?? '—'}</TableCell>)}</TableRow>)}</TableBody></Table>}
  </CardContent></Card>;
}

export function AnalyticsDashboardPage() {
  const { user } = useAuthContext();
  const permissions = GROUPS.filter((group) => hasPermission(user, group.permission));
  const canExport = hasPermission(user, 'analytics.export');
  const [from, setFrom] = useState(priorMonth);
  const [to, setTo] = useState(today);
  const [data, setData] = useState(null);
  const [reports, setReports] = useState([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const canAccess = hasAnyPermission(user, GROUPS.map((group) => group.permission));

  const loadDashboard = useCallback(async () => {
    const result = await analyticsApi.dashboard({ from, to });
    const reportPage = canExport ? await analyticsApi.reports() : null;
    return { result, reportPage };
  }, [canExport, from, to]);

  const refresh = useCallback(async () => {
    setBusy(true);
    setError('');
    try {
      const { result, reportPage } = await loadDashboard();
      setData(result);
      if (reportPage) setReports(reportPage.data || []);
    } catch (cause) {
      setError(errorText(cause));
    } finally {
      setBusy(false);
    }
  }, [loadDashboard]);

  useEffect(() => {
    if (!canAccess) return undefined;

    let active = true;
    loadDashboard()
      .then(({ result, reportPage }) => {
        if (!active) return;
        setData(result);
        if (reportPage) setReports(reportPage.data || []);
        setError('');
      })
      .catch((cause) => {
        if (active) setError(errorText(cause));
      })
      .finally(() => {
        if (active) setBusy(false);
      });
    return () => {
      active = false;
    };
  }, [canAccess, loadDashboard]);

  const download = async (group) => {
    try {
      const blob = await analyticsApi.export({ group, from, to });
      const url = window.URL.createObjectURL(blob); const anchor = document.createElement('a');
      anchor.href = url; anchor.download = `ott-${group}-analytics-${from}-${to}.csv`; anchor.click(); window.URL.revokeObjectURL(url);
    } catch (cause) { setError(errorText(cause)); }
  };

  const schedule = async (event) => {
    event.preventDefault(); const form = Object.fromEntries(new FormData(event.currentTarget).entries());
    setBusy(true); setError(''); setNotice('');
    try {
      await analyticsApi.scheduleReport({ group: form.group, from, to, scheduled_at: new Date(form.scheduled_at).toISOString() });
      setNotice('Aggregate report scheduled. It will appear here after the report runner processes it.');
      const reportPage = await analyticsApi.reports(); setReports(reportPage.data || []);
    } catch (cause) { setError(errorText(cause)); }
    finally { setBusy(false); }
  };

  const downloadReport = async (id) => {
    try {
      const report = await analyticsApi.report(id);
      const payload = typeof report.result === 'string' ? JSON.parse(report.result) : report.result;
      const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
      const url = window.URL.createObjectURL(blob); const anchor = document.createElement('a');
      anchor.href = url; anchor.download = `ott-${report.metric_group}-report-${report.date_from}-${report.date_to}.json`; anchor.click(); window.URL.revokeObjectURL(url);
    } catch (cause) { setError(errorText(cause)); }
  };

  if (!canAccess) return <DashboardContent><Alert severity="error">You do not have permission to view analytics.</Alert></DashboardContent>;
  const audience = data?.audience; const subscriptions = data?.subscriptions; const content = data?.content; const revenue = data?.revenue; const playback = data?.playback;

  return <DashboardContent>
    <RbacPageHeader title="OTT analytics" description="Permission-scoped audience, content, subscription, revenue, and playback metrics from the selected date range." />
    {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}{notice && <Alert severity="success" sx={{ mb: 2 }}>{notice}</Alert>}
    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ mb: 2 }}>
      <TextField label="From" type="date" size="small" value={from} onChange={(e) => { setBusy(true); setFrom(e.target.value); }} InputLabelProps={{ shrink: true }} />
      <TextField label="To" type="date" size="small" value={to} onChange={(e) => { setBusy(true); setTo(e.target.value); }} InputLabelProps={{ shrink: true }} />
      <Button variant="contained" disabled={busy} onClick={refresh}>Apply period</Button>
      <Chip label={data ? `${data.range.from} – ${data.range.to}` : 'Loading metrics'} variant="outlined" />
    </Stack>
    {permissions.map((group) => canExport && <Button key={group.key} size="small" onClick={() => download(group.key)} disabled={busy} sx={{ mr: 1, mb: 1 }}>Export {group.label} CSV</Button>)}

    {audience && <><Typography variant="h6" sx={{ mt: 2 }}>Audience</Typography><Grid container spacing={2} sx={{ mt: 0.25 }}>
      <Grid item xs={6} md={3}><Metric title="New accounts" value={audience.new_accounts} /></Grid><Grid item xs={6} md={3}><Metric title="Active accounts" value={audience.active_accounts} /></Grid><Grid item xs={6} md={3}><Metric title="New subscribers" value={audience.new_subscribers} /></Grid>
    </Grid><DataTable title="Audience by country (groups under five are hidden)" columns={[{ key: 'country', label: 'Country code' }, { key: 'accounts', label: 'Active accounts' }]} rows={audience.geography} keyField="country" />
      <DataTable title="Popular search terms (minimum five searches)" columns={[{ key: 'term', label: 'Search term' }, { key: 'searches', label: 'Searches' }]} rows={audience.popular_search_terms} keyField="term" />
    </>}

    {subscriptions && <><Typography variant="h6" sx={{ mt: 3 }}>Subscriptions</Typography><Grid container spacing={2} sx={{ mt: 0.25 }}>
      <Grid item xs={6} md={3}><Metric title="Active subscriptions" value={subscriptions.active_subscriptions} /></Grid><Grid item xs={6} md={3}><Metric title="Acquired in period" value={subscriptions.acquired_subscriptions} /></Grid><Grid item xs={6} md={3}><Metric title="Churned in period" value={subscriptions.churned_subscriptions} /></Grid><Grid item xs={6} md={3}><Metric title="Trial to paid" value={`${subscriptions.trial_to_paid_percent}%`} /></Grid>
    </Grid><DataTable title="Recurring revenue by currency" columns={[{ key: 'currency', label: 'Currency' }, { key: 'mrr', label: 'MRR' }, { key: 'arr', label: 'ARR' }]} rows={subscriptions.mrr_by_currency} keyField="currency" /></>}

    {content && <><Typography variant="h6" sx={{ mt: 3 }}>Content performance</Typography><Grid container spacing={2} sx={{ mt: 0.25 }}>
      <Grid item xs={6} md={3}><Metric title="Watch hours" value={content.watch_hours} /></Grid><Grid item xs={6} md={3}><Metric title="Completion rate" value={`${content.completion_rate_percent}%`} /></Grid><Grid item xs={6} md={3}><Metric title="Audience retention proxy" value={`${content.audience_retention_percent}%`} /></Grid>
    </Grid><DataTable title="Popular content" columns={[{ key: 'title', label: 'Title' }, { key: 'type', label: 'Type' }, { key: 'views', label: 'Views' }]} rows={content.popular_content} keyField="content_id" /></>}

    {revenue && <><Typography variant="h6" sx={{ mt: 3 }}>Revenue</Typography><DataTable title="Paid revenue by day and currency" columns={[{ key: 'day', label: 'Day' }, { key: 'currency', label: 'Currency' }, { key: 'payments', label: 'Payments' }, { key: 'gross_amount', label: 'Gross amount' }]} rows={revenue.payment_series} keyField="day" /></>}

    {playback && <><Typography variant="h6" sx={{ mt: 3 }}>Playback quality</Typography><Grid container spacing={2} sx={{ mt: 0.25 }}>
      <Grid item xs={6} md={3}><Metric title="Startup failures" value={playback.startup_failures} /></Grid><Grid item xs={6} md={3}><Metric title="Rebuffer events" value={playback.rebuffer_events} /></Grid><Grid item xs={6} md={3}><Metric title="Average startup (ms)" value={playback.average_startup_ms === null ? 'Not collected' : Math.round(playback.average_startup_ms)} /></Grid>
    </Grid><DataTable title="Playback event counts" columns={[{ key: 'event_code', label: 'Event' }, { key: 'occurrences', label: 'Count' }]} rows={playback.event_counts} keyField="event_code" />
      <DataTable title="Selected quality distribution" columns={[{ key: 'quality', label: 'Quality' }, { key: 'occurrences', label: 'Events' }]} rows={playback.quality_distribution} keyField="quality" />
      <DataTable title="Devices by platform" columns={[{ key: 'platform', label: 'Platform' }, { key: 'devices', label: 'Devices' }, { key: 'accounts', label: 'Accounts' }]} rows={playback.platform_distribution} keyField="platform" />
    </>}

    {data?.metric_definitions && <Card sx={{ mt: 3 }}><CardContent><Typography variant="h6">Metric definitions</Typography>{Object.entries(data.metric_definitions).map(([key, value]) => <Typography key={key} variant="body2" sx={{ mt: 0.75 }}><b>{key.replaceAll('_', ' ')}:</b> {value}</Typography>)}</CardContent></Card>}

    {canExport && <><Typography variant="h6" sx={{ mt: 3 }}>Report builder</Typography><Card><CardContent>
      <Stack component="form" onSubmit={schedule} direction={{ xs: 'column', md: 'row' }} spacing={1}>
        <TextField select name="group" label="Report" defaultValue={permissions[0]?.key || 'audience'} size="small" sx={{ minWidth: 190 }}>
          {permissions.map((group) => <MenuItem key={group.key} value={group.key}>{group.label}</MenuItem>)}
        </TextField>
        <TextField name="scheduled_at" label="Run at" type="datetime-local" required size="small" InputLabelProps={{ shrink: true }} />
        <Button type="submit" variant="contained" disabled={busy}>Schedule report</Button>
      </Stack>
    </CardContent></Card>
    <DataTable title="Scheduled and generated reports" columns={[{ key: 'metric_group', label: 'Report' }, { key: 'date_from', label: 'From' }, { key: 'date_to', label: 'To' }, { key: 'scheduled_at', label: 'Scheduled' }, { key: 'status', label: 'Status' }, { key: 'generated_at', label: 'Generated' }, { key: 'error_code', label: 'Issue' }]} rows={reports.map((report) => ({ ...report, scheduled_at: dateText(report.scheduled_at), generated_at: dateText(report.generated_at) }))} keyField="id" />
    <Stack direction="row" spacing={1} sx={{ mt: 1, flexWrap: 'wrap' }}>{reports.filter((report) => report.status === 'ready').map((report) => <Button key={report.id} size="small" onClick={() => downloadReport(report.id)}>Download report #{report.id}</Button>)}</Stack>
    </>}
  </DashboardContent>;
}

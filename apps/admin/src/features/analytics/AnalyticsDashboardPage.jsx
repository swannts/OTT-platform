'use client';

import { useCallback, useEffect, useState } from 'react';
import dayjs from 'dayjs';
import Alert from '@mui/material/Alert';
import Accordion from '@mui/material/Accordion';
import AccordionDetails from '@mui/material/AccordionDetails';
import AccordionSummary from '@mui/material/AccordionSummary';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import MenuItem from '@mui/material/MenuItem';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableContainer from '@mui/material/TableContainer';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import Typography from '@mui/material/Typography';
import { useTheme } from '@mui/material/styles';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { Iconify } from 'src/components/iconify';
import { Chart, useChart } from 'src/components/chart';
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
const METRIC_KEYS_BY_GROUP = {
  audience: ['active_accounts', 'new_accounts', 'geography', 'popular_search_terms'],
  subscriptions: ['active_subscriptions', 'mrr_by_currency', 'arr', 'trial_to_paid_percent'],
  content: ['watch_hours', 'playback_session_count', 'session_watch_hours', 'session_completion_rate_percent', 'popular_playback_content', 'completion_rate_percent', 'audience_retention_percent'],
  revenue: [],
  playback: ['playback_quality'],
};
const today = new Date().toISOString().slice(0, 10);
const priorMonth = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
const errorText = (error) => error?.response?.data?.message || 'The analytics request could not be completed.';
const dateText = (value) => (value ? new Date(value).toLocaleString() : '—');

function Metric({ title, value }) {
  const metricIcons = {
    'New accounts': 'solar:user-plus-bold-duotone',
    'Active accounts': 'solar:users-group-rounded-bold-duotone',
    'New subscribers': 'solar:star-bold-duotone',
    'Active subscriptions': 'solar:card-recive-bold-duotone',
    'Acquired in period': 'solar:graph-up-bold-duotone',
    'Churned in period': 'solar:graph-down-bold-duotone',
    'Trial to paid': 'solar:check-circle-bold-duotone',
    'Watch hours': 'solar:play-circle-bold-duotone',
    'Completion rate': 'solar:check-read-bold-duotone',
    'Audience retention proxy': 'solar:users-group-rounded-bold-duotone',
    'Startup failures': 'solar:danger-triangle-bold-duotone',
    'Rebuffer events': 'solar:restart-bold-duotone',
    'Average startup (ms)': 'solar:stopwatch-bold-duotone',
  };

  return <Card sx={{ height: '100%', border: 1, borderColor: 'divider', boxShadow: 'none' }}>
    <CardContent sx={{ p: 2.5, '&:last-child': { pb: 2.5 } }}>
      <Stack direction="row" justifyContent="space-between" alignItems="flex-start" spacing={1}>
        <Box>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>{title}</Typography>
          <Typography variant="h4" sx={{ fontWeight: 700, letterSpacing: '-0.03em' }}>{value ?? '—'}</Typography>
        </Box>
        <Box sx={{ display: 'grid', placeItems: 'center', width: 42, height: 42, borderRadius: 1.5, bgcolor: 'primary.lighter', color: 'primary.dark' }}>
          <Iconify icon={metricIcons[title] || 'solar:chart-2-bold-duotone'} width={24} />
        </Box>
      </Stack>
    </CardContent>
  </Card>;
}

function RevenuePaymentsChart({ rows }) {
  const theme = useTheme();
  const byDay = (rows || []).reduce((totals, row) => {
    totals[row.day] = (totals[row.day] || 0) + Number(row.payments || 0);
    return totals;
  }, {});
  const categories = Object.keys(byDay).sort();
  const options = useChart({
    colors: [theme.palette.primary.main],
    chart: { type: 'bar' },
    plotOptions: { bar: { borderRadius: 4, columnWidth: '48%' } },
    dataLabels: { enabled: false },
    xaxis: { categories, labels: { rotate: -35, hideOverlappingLabels: true } },
    yaxis: { min: 0, forceNiceScale: true, title: { text: 'Paid transactions' } },
    tooltip: { y: { formatter: (value) => `${value} transactions` } },
    grid: { strokeDashArray: 4 },
  });

  return (
    <Card sx={{ mt: 2, border: 1, borderColor: 'divider', boxShadow: 'none' }}>
      <CardContent>
        <Typography variant="subtitle1" sx={{ fontWeight: 650 }}>Paid transactions by day</Typography>
        <Typography variant="body2" color="text.secondary">Transaction counts are combined across currencies; revenue totals remain separated below.</Typography>
        {categories.length ? (
          <Chart type="bar" series={[{ name: 'Paid transactions', data: categories.map((day) => byDay[day]) }]} options={options} height={280} sx={{ mt: 1 }} />
        ) : (
          <Box sx={{ display: 'grid', placeItems: 'center', minHeight: 180 }}>
            <Typography color="text.secondary">No paid transactions in this period.</Typography>
          </Box>
        )}
      </CardContent>
    </Card>
  );
}

function DataTable({ title, columns, rows, keyField }) {
  return <Card sx={{ mt: 2, border: 1, borderColor: 'divider', boxShadow: 'none' }}>
    <CardContent sx={{ p: 0 }}>
      <Typography variant="subtitle1" sx={{ px: 2.5, py: 2, fontWeight: 650 }}>{title}</Typography>
      <Divider />
      {!rows?.length ? <Typography color="text.secondary" sx={{ p: 2.5 }}>No qualifying data for this period.</Typography> :
        <TableContainer>
          <Table size="small" sx={{ minWidth: 480 }}>
            <TableHead><TableRow>{columns.map((column) => <TableCell key={column.key} sx={{ fontWeight: 650, color: 'text.secondary' }}>{column.label}</TableCell>)}</TableRow></TableHead>
            <TableBody>{rows.map((row, index) => <TableRow hover key={row[keyField] || `${title}-${index}`}>{columns.map((column) => <TableCell key={column.key}>{row[column.key] ?? '—'}</TableCell>)}</TableRow>)}</TableBody>
          </Table>
        </TableContainer>}
    </CardContent>
  </Card>;
}

function SectionHeading({ title }) {
  return <Stack direction="row" alignItems="center" spacing={1} sx={{ mt: 3, mb: 1.5 }}>
    <Box sx={{ width: 4, height: 22, borderRadius: 4, bgcolor: 'primary.main' }} />
    <Typography variant="h6" sx={{ fontWeight: 700 }}>{title}</Typography>
  </Stack>;
}

export function AnalyticsDashboardPage() {
  const { user } = useAuthContext();
  const permissions = GROUPS.filter((group) => hasPermission(user, group.permission));
  const canExport = hasPermission(user, 'analytics.export');
  const [selectedGroup, setSelectedGroup] = useState('audience');
  const [from, setFrom] = useState(priorMonth);
  const [to, setTo] = useState(today);
  const [appliedRange, setAppliedRange] = useState({ from: priorMonth, to: today });
  const [data, setData] = useState(null);
  const [reports, setReports] = useState([]);
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const canAccess = hasAnyPermission(user, GROUPS.map((group) => group.permission));
  const activeGroup = permissions.some((group) => group.key === selectedGroup)
    ? selectedGroup
    : permissions[0]?.key || (canExport ? 'reports' : '');
  const selectedPermissionGroup = permissions.find((group) => group.key === activeGroup);
  const visibleMetricDefinitions = Object.entries(data?.metric_definitions || {}).filter(([key]) =>
    (METRIC_KEYS_BY_GROUP[activeGroup] || []).includes(key)
  );
  const fromDate = from ? dayjs(from) : null;
  const toDate = to ? dayjs(to) : null;
  const setPreset = (days) => {
    const end = dayjs();
    setFrom(end.subtract(days - 1, 'day').format('YYYY-MM-DD'));
    setTo(end.format('YYYY-MM-DD'));
  };

  const loadDashboard = useCallback(async () => {
    const result = await analyticsApi.dashboard(appliedRange);
    const reportPage = canExport ? await analyticsApi.reports() : null;
    return { result, reportPage };
  }, [appliedRange, canExport]);

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
      const blob = await analyticsApi.export({ group, ...appliedRange });
      const url = window.URL.createObjectURL(blob); const anchor = document.createElement('a');
      anchor.href = url; anchor.download = `ott-${group}-analytics-${appliedRange.from}-${appliedRange.to}.csv`; anchor.click(); window.URL.revokeObjectURL(url);
    } catch (cause) { setError(errorText(cause)); }
  };

  const schedule = async (event) => {
    event.preventDefault(); const form = Object.fromEntries(new FormData(event.currentTarget).entries());
    setBusy(true); setError(''); setNotice('');
    try {
      await analyticsApi.scheduleReport({ group: form.group, ...appliedRange, scheduled_at: new Date(form.scheduled_at).toISOString() });
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
    <Card sx={{ mb: 3, border: 1, borderColor: 'divider', borderRadius: 2.5, boxShadow: '0 8px 24px rgba(15, 23, 42, 0.04)', overflow: 'visible' }}>
      <CardContent sx={{ p: { xs: 2, md: 2.5 }, '&:last-child': { pb: { xs: 2, md: 2.5 } } }}>
        <Stack direction={{ xs: 'column', lg: 'row' }} spacing={2} justifyContent="space-between" alignItems={{ lg: 'center' }}>
          <Stack direction="row" spacing={1.5} alignItems="center" sx={{ minWidth: { lg: 250 } }}>
            <Box sx={{ display: 'grid', placeItems: 'center', width: 44, height: 44, borderRadius: 1.5, bgcolor: 'primary.lighter', color: 'primary.dark', flexShrink: 0 }}>
              <Iconify icon="solar:calendar-date-bold-duotone" width={25} />
            </Box>
            <Box>
              <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>Reporting period</Typography>
              <Typography variant="body2" color="text.secondary">Choose dates for the metrics below.</Typography>
            </Box>
          </Stack>
          <Stack component="form" onSubmit={(event) => { event.preventDefault(); setBusy(true); setAppliedRange({ from, to }); }} direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'center' }} sx={{ p: 1, borderRadius: 2, bgcolor: 'background.neutral' }}>
            <DatePicker
              label="From"
              value={fromDate}
              maxDate={toDate || undefined}
              onChange={(value) => setFrom(value?.isValid() ? value.format('YYYY-MM-DD') : '')}
              slotProps={{ textField: { size: 'small', sx: { width: { xs: '100%', sm: 160 }, bgcolor: 'background.paper', borderRadius: 1 } } }}
            />
            <DatePicker
              label="To"
              value={toDate}
              minDate={fromDate || undefined}
              onChange={(value) => setTo(value?.isValid() ? value.format('YYYY-MM-DD') : '')}
              slotProps={{ textField: { size: 'small', sx: { width: { xs: '100%', sm: 160 }, bgcolor: 'background.paper', borderRadius: 1 } } }}
            />
            <Button type="submit" variant="contained" disabled={busy || !from || !to || from > to} sx={{ minHeight: 40, px: 2, whiteSpace: 'nowrap' }}>Apply period</Button>
            <Button type="button" variant="outlined" onClick={refresh} disabled={busy} startIcon={<Iconify icon="solar:refresh-bold" />} sx={{ minHeight: 40, px: 1.5, whiteSpace: 'nowrap', bgcolor: 'background.paper' }}>Refresh</Button>
          </Stack>
        </Stack>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} justifyContent="space-between" alignItems={{ sm: 'center' }} sx={{ mt: 1.5 }}>
          <Stack direction="row" spacing={0.75} alignItems="center">
            <Typography variant="caption" color="text.secondary" sx={{ mr: 0.5 }}>Quick range</Typography>
            {[7, 30, 90].map((days) => (
              <Button key={days} size="small" variant="text" onClick={() => setPreset(days)} disabled={busy} sx={{ minWidth: 0, px: 1, borderRadius: 1.5, color: 'text.primary', fontWeight: 600 }}>
                {days} days
              </Button>
            ))}
          </Stack>
          <Chip size="small" icon={<Iconify icon="solar:calendar-minimalistic-bold" />} label={data ? `${data.range.from} – ${data.range.to}` : 'Loading metrics'} variant="outlined" sx={{ alignSelf: { xs: 'flex-start', sm: 'center' }, bgcolor: 'background.paper', borderColor: 'divider', fontWeight: 600 }} />
        </Stack>
        {canExport && selectedPermissionGroup && <>
          <Divider sx={{ my: 2.25 }} />
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }} justifyContent="space-between" sx={{ p: 1.5, borderRadius: 2, bgcolor: 'action.hover' }}>
            <Stack direction="row" spacing={1.25} alignItems="center">
              <Box sx={{ display: 'grid', placeItems: 'center', width: 38, height: 38, borderRadius: 1.25, bgcolor: 'background.paper', color: 'primary.main' }}>
                <Iconify icon="solar:download-square-bold-duotone" width={22} />
              </Box>
              <Box>
              <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Export data</Typography>
                <Typography variant="caption" color="text.secondary">CSV for {selectedPermissionGroup.label.toLowerCase()} · selected period</Typography>
              </Box>
            </Stack>
            <Button size="small" variant="outlined" startIcon={<Iconify icon="solar:download-outline" />} onClick={() => download(selectedPermissionGroup.key)} disabled={busy} sx={{ minHeight: 40, px: 1.75, whiteSpace: 'nowrap', bgcolor: 'background.paper', borderColor: 'divider' }}>
              Export CSV
            </Button>
          </Stack>
        </>}
      </CardContent>
    </Card>

    {busy && !data && <Grid container spacing={2} sx={{ mb: 3 }}>
      {[0, 1, 2, 3].map((item) => <Grid item xs={6} md={3} key={item}>
        <Card sx={{ p: 2.5 }}><Skeleton variant="text" width="55%" height={24} /><Skeleton variant="text" width="42%" height={48} /></Card>
      </Grid>)}
      <Grid item xs={12}><Skeleton variant="rounded" height={220} /></Grid>
    </Grid>}

    <Card sx={{ mb: 3, border: 1, borderColor: 'divider', boxShadow: 'none' }}>
      <CardContent sx={{ p: { xs: 1.5, sm: 2 }, '&:last-child': { pb: { xs: 1.5, sm: 2 } } }}>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} alignItems={{ sm: 'center' }} justifyContent="space-between">
          <Box>
            <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>Explore analytics</Typography>
            <Typography variant="body2" color="text.secondary">Choose one area to focus the dashboard.</Typography>
          </Box>
          <ToggleButtonGroup
            exclusive
            size="small"
            value={activeGroup}
            onChange={(_, value) => value && setSelectedGroup(value)}
            aria-label="Analytics area"
            sx={{ maxWidth: '100%', overflowX: 'auto', '& .MuiToggleButton-root': { px: 1.5, whiteSpace: 'nowrap' } }}
          >
            {permissions.map((group) => <ToggleButton key={group.key} value={group.key}>{group.label}</ToggleButton>)}
            {canExport && <ToggleButton value="reports">Reports</ToggleButton>}
          </ToggleButtonGroup>
        </Stack>
      </CardContent>
    </Card>

    {activeGroup === 'audience' && audience && <><SectionHeading title="Audience" /><Grid container spacing={2}>
      <Grid item xs={6} md={3}><Metric title="New accounts" value={audience.new_accounts} /></Grid><Grid item xs={6} md={3}><Metric title="Active accounts" value={audience.active_accounts} /></Grid><Grid item xs={6} md={3}><Metric title="New subscribers" value={audience.new_subscribers} /></Grid>
    </Grid><DataTable title="Audience by country (groups under five are hidden)" columns={[{ key: 'country', label: 'Country code' }, { key: 'accounts', label: 'Active accounts' }]} rows={audience.geography} keyField="country" />
      <DataTable title="Popular search terms (minimum five searches)" columns={[{ key: 'term', label: 'Search term' }, { key: 'searches', label: 'Searches' }]} rows={audience.popular_search_terms} keyField="term" />
    </>}

    {activeGroup === 'subscriptions' && subscriptions && <><SectionHeading title="Subscriptions" /><Grid container spacing={2}>
      <Grid item xs={6} md={3}><Metric title="Active subscriptions" value={subscriptions.active_subscriptions} /></Grid><Grid item xs={6} md={3}><Metric title="Acquired in period" value={subscriptions.acquired_subscriptions} /></Grid><Grid item xs={6} md={3}><Metric title="Churned in period" value={subscriptions.churned_subscriptions} /></Grid><Grid item xs={6} md={3}><Metric title="Trial to paid" value={`${subscriptions.trial_to_paid_percent}%`} /></Grid>
    </Grid><DataTable title="Recurring revenue by currency" columns={[{ key: 'currency', label: 'Currency' }, { key: 'mrr', label: 'MRR' }, { key: 'arr', label: 'ARR' }]} rows={subscriptions.mrr_by_currency} keyField="currency" /></>}

    {activeGroup === 'content' && content && <><SectionHeading title="Content performance" /><Grid container spacing={2}>
      <Grid item xs={6} md={3}><Metric title="Watch hours" value={content.watch_hours} /></Grid><Grid item xs={6} md={3}><Metric title="Completion rate" value={`${content.completion_rate_percent}%`} /></Grid><Grid item xs={6} md={3}><Metric title="Audience retention proxy" value={`${content.audience_retention_percent}%`} /></Grid>
    </Grid><DataTable title="Popular content" columns={[{ key: 'title', label: 'Title' }, { key: 'type', label: 'Type' }, { key: 'views', label: 'Views' }]} rows={content.popular_content} keyField="content_id" /></>}

    {activeGroup === 'revenue' && revenue && <><SectionHeading title="Revenue" /><Grid container spacing={2}>
      <Grid item xs={6} md={3}><Metric title="Paid transactions" value={revenue.paid_payment_count} /></Grid>
    </Grid><RevenuePaymentsChart rows={revenue.payment_series} /><DataTable title="Paid revenue by day and currency" columns={[{ key: 'day', label: 'Day' }, { key: 'currency', label: 'Currency' }, { key: 'payments', label: 'Payments' }, { key: 'gross_amount', label: 'Gross amount' }]} rows={revenue.payment_series} keyField="id" /></>}

    {activeGroup === 'playback' && playback && <><SectionHeading title="Playback quality" /><Grid container spacing={2}>
      <Grid item xs={6} md={3}><Metric title="Startup failures" value={playback.startup_failures} /></Grid><Grid item xs={6} md={3}><Metric title="Rebuffer events" value={playback.rebuffer_events} /></Grid><Grid item xs={6} md={3}><Metric title="Average startup (ms)" value={playback.average_startup_ms === null ? 'Not collected' : Math.round(playback.average_startup_ms)} /></Grid>
    </Grid><DataTable title="Playback event counts" columns={[{ key: 'event_code', label: 'Event' }, { key: 'occurrences', label: 'Count' }]} rows={playback.event_counts} keyField="event_code" />
      <DataTable title="Selected quality distribution" columns={[{ key: 'quality', label: 'Quality' }, { key: 'occurrences', label: 'Events' }]} rows={playback.quality_distribution} keyField="quality" />
      <DataTable title="Devices by platform" columns={[{ key: 'platform', label: 'Platform' }, { key: 'devices', label: 'Devices' }, { key: 'accounts', label: 'Accounts' }]} rows={playback.platform_distribution} keyField="platform" />
    </>}

    {visibleMetricDefinitions.length > 0 && <Accordion sx={{ mt: 3, border: 1, borderColor: 'divider', boxShadow: 'none', '&:before': { display: 'none' } }}>
      <AccordionSummary expandIcon={<Iconify icon="solar:alt-arrow-down-line-duotone" />}><Typography variant="subtitle2">Metric definitions</Typography></AccordionSummary>
      <AccordionDetails sx={{ pt: 0 }}>{visibleMetricDefinitions.map(([key, value]) => <Typography key={key} variant="body2" sx={{ mt: 0.75 }}><b>{key.replaceAll('_', ' ')}:</b> {value}</Typography>)}</AccordionDetails>
    </Accordion>}

    {activeGroup === 'reports' && canExport && <><Typography variant="h6" sx={{ mt: 3, mb: 1.5 }}>Report builder</Typography><Card><CardContent>
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

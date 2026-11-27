'use client';

import { useCallback, useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Grid from '@mui/material/Grid';
import InputAdornment from '@mui/material/InputAdornment';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
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
import { useAuthContext } from 'src/auth/hooks';
import { hasAnyPermission, hasPermission } from 'src/features/rbac/permissions';
import { RbacPageHeader } from 'src/features/rbac/components/RbacPageHeader';
import { supportApi } from './support.api';
import { Iconify } from 'src/components/iconify';

const errorText = (error) => error?.response?.data?.message || 'The support request could not be completed.';
const dateText = (value) => (value ? new Date(value).toLocaleString() : '—');
const paginationMeta = (response) => response?.meta || {
  total: response?.total || 0,
  current_page: response?.current_page || 1,
  last_page: response?.last_page || 1,
};

function Metric({ label, value }) {
  return <Card><CardContent><Typography variant="body2" color="text.secondary">{label}</Typography><Typography variant="h4">{value ?? '—'}</Typography></CardContent></Card>;
}

export function CustomerSupportPage() {
  const { user } = useAuthContext();
  const canTickets = hasPermission(user, 'support.tickets.manage');
  const canAccounts = hasPermission(user, 'support.accounts.view');
  const canPayments = hasPermission(user, 'support.payments.view');
  const canDevices = hasPermission(user, 'support.devices.view');
  const canPlayback = hasPermission(user, 'support.playback.view');
  const canRevoke = hasPermission(user, 'support.sessions.revoke');
  const canAccess = hasAnyPermission(user, ['support.tickets.manage', 'support.accounts.view']);
  const [tab, setTab] = useState(0);
  const [overview, setOverview] = useState(null);
  const [tickets, setTickets] = useState([]);
  const [ticketMeta, setTicketMeta] = useState({ total: 0, current_page: 1, last_page: 1 });
  const [ticketPage, setTicketPage] = useState(0);
  const [ticketRowsPerPage, setTicketRowsPerPage] = useState(25);
  const [ticketLoading, setTicketLoading] = useState(true);
  const [ticketSearch, setTicketSearch] = useState('');
  const [ticketFilters, setTicketFilters] = useState({ search: '', status: '', priority: '', category: '', assigned_to: '', escalated: '' });
  const [assignees, setAssignees] = useState([]);
  const [selectedTicket, setSelectedTicket] = useState(null);
  const [customers, setCustomers] = useState([]);
  const [customer, setCustomer] = useState(null);
  const [payments, setPayments] = useState([]);
  const [devices, setDevices] = useState([]);
  const [playback, setPlayback] = useState(null);
  const [search, setSearch] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const loadSupport = useCallback(async () => {
    const result = { customers: [] };
    const tasks = [];
    if (canTickets) {
      tasks.push(supportApi.overview().then((value) => { result.overview = value; }));
      tasks.push(supportApi.assignees().then((value) => { result.assignees = value; }));
    }
    if (canAccounts && search.trim().length >= 2) {
      tasks.push(supportApi.customers(search.trim()).then((value) => { result.customers = value.data || []; }));
    }
    await Promise.all(tasks);
    return result;
  }, [canAccounts, canTickets, search]);

  const fetchTickets = useCallback(() => {
    const filters = Object.fromEntries(Object.entries(ticketFilters).filter(([, value]) => value !== ''));
    return supportApi.tickets({ ...filters, page: ticketPage + 1, per_page: ticketRowsPerPage });
  }, [ticketFilters, ticketPage, ticketRowsPerPage]);

  const applySupport = useCallback((result) => {
    setOverview(result.overview || null);
    setAssignees(result.assignees || []);
    setCustomers(result.customers || []);
  }, []);

  const refresh = useCallback(async () => {
    setError('');
    if (canTickets) setTicketLoading(true);
    try {
      const [supportResult, ticketResult] = await Promise.all([
        loadSupport(),
        canTickets ? fetchTickets() : Promise.resolve(null),
      ]);
      applySupport(supportResult);
      if (ticketResult) {
        setTickets(ticketResult.data || []);
        setTicketMeta(paginationMeta(ticketResult));
      }
    } catch (cause) {
      setError(errorText(cause));
    } finally {
      setTicketLoading(false);
    }
  }, [applySupport, canTickets, fetchTickets, loadSupport]);

  useEffect(() => {
    if (!canAccess) return undefined;

    let active = true;
    loadSupport()
      .then((result) => {
        if (active) {
          applySupport(result);
          setError('');
        }
      })
      .catch((cause) => {
        if (active) setError(errorText(cause));
      });
    return () => {
      active = false;
    };
  }, [applySupport, canAccess, loadSupport]);

  useEffect(() => {
    if (!canTickets) return undefined;
    let active = true;
    fetchTickets()
      .then((result) => {
        if (!active) return;
        setTickets(result.data || []);
        setTicketMeta(paginationMeta(result));
        setError('');
      })
      .catch((cause) => {
        if (active) setError(errorText(cause));
      })
      .finally(() => {
        if (active) setTicketLoading(false);
      });
    return () => { active = false; };
  }, [canTickets, fetchTickets]);

  const updateTicketFilter = (key, value) => {
    setTicketLoading(true);
    setTicketPage(0);
    setTicketFilters((current) => ({ ...current, [key]: value }));
  };

  const searchTickets = (event) => {
    event.preventDefault();
    setTicketLoading(true);
    setTicketPage(0);
    setTicketFilters((current) => ({ ...current, search: ticketSearch.trim() }));
  };

  const run = async (action, message) => {
    setBusy(true); setError(''); setNotice('');
    try { await action(); setNotice(message); await refresh(); return true; }
    catch (cause) { setError(errorText(cause)); return false; }
    finally { setBusy(false); }
  };

  const openCustomer = async (id) => {
    setError('');
    try {
      const next = await supportApi.customer(id); setCustomer(next);
      const jobs = [];
      if (canPayments) jobs.push(supportApi.payments(id).then(setPayments));
      if (canDevices) jobs.push(supportApi.devices(id).then(setDevices));
      if (canPlayback) jobs.push(supportApi.playback(id).then(setPlayback));
      await Promise.all(jobs);
    } catch (cause) { setError(errorText(cause)); }
  };

  const openTicket = async (id) => {
    try { setSelectedTicket(await supportApi.ticket(id)); setError(''); }
    catch (cause) { setError(errorText(cause)); }
  };

  const createTicket = async (event) => {
    event.preventDefault(); const formElement = event.currentTarget; const form = new FormData(formElement);
    const created = await run(async () => supportApi.createTicket({
      customer_id: Number(form.get('customer_id')), subject: String(form.get('subject')).trim(),
      description: String(form.get('description')).trim(), category: form.get('category'), priority: form.get('priority'),
      assigned_to: form.get('assigned_to') ? Number(form.get('assigned_to')) : null,
    }), 'Ticket created.');
    if (created) formElement.reset();
  };

  const addNote = async (event) => {
    event.preventDefault(); const formElement = event.currentTarget; const form = new FormData(formElement); const note = String(form.get('note') || '').trim();
    if (!note || !selectedTicket) return;
    const saved = await run(async () => { await supportApi.addNote(selectedTicket.id, note); setSelectedTicket(await supportApi.ticket(selectedTicket.id)); }, 'Internal note added.');
    if (saved) formElement.reset();
  };

  const escalate = async (event) => {
    event.preventDefault(); const form = new FormData(event.currentTarget);
    const saved = await run(async () => { await supportApi.escalate(selectedTicket.id, { target: form.get('target'), reason: String(form.get('reason')).trim() }); setSelectedTicket(await supportApi.ticket(selectedTicket.id)); }, 'Ticket escalated.');
    if (saved) event.currentTarget.reset();
  };

  if (!canAccess) return <DashboardContent><Alert severity="error">You do not have permission to access customer support.</Alert></DashboardContent>;

  return (
    <DashboardContent>
      <RbacPageHeader title="Customer support" description="Investigate accounts, coordinate internal support tickets, and review authorized device and playback diagnostics." />
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {notice && <Alert severity="success" sx={{ mb: 2 }}>{notice}</Alert>}
      {canTickets && <Grid container spacing={2} sx={{ mb: 2 }}>
        <Grid item xs={6} md={3}><Metric label="Open tickets" value={overview?.open_tickets} /></Grid>
        <Grid item xs={6} md={3}><Metric label="Unassigned" value={overview?.unassigned_tickets} /></Grid>
        <Grid item xs={6} md={3}><Metric label="Escalated" value={overview?.escalated_tickets} /></Grid>
        <Grid item xs={6} md={3}><Metric label="Resolved in 7 days" value={overview?.resolved_last_7_days} /></Grid>
        <Grid item xs={6} md={3}><Metric label="Median first response (sec)" value={overview?.median_first_response_seconds} /></Grid>
        <Grid item xs={6} md={3}><Metric label="Median resolution (sec)" value={overview?.median_resolution_seconds} /></Grid>
      </Grid>}
      <Tabs value={tab} variant="standard" onChange={(_, value) => setTab(value)} sx={{ mb: 2 }}>
        {canTickets && <Tab label="Ticket inbox" />}{canAccounts && <Tab label="Customer 360°" />}
      </Tabs>

      {canTickets && tab === 0 && <Stack spacing={2}>
        <Card sx={{ border: 1, borderColor: 'divider', boxShadow: 'none' }}><CardContent sx={{ p: { xs: 2, md: 2.5 } }}>
          <Stack spacing={0.5} sx={{ mb: 2.5 }}>
            <Typography variant="h6">Create support ticket</Typography>
            <Typography variant="body2" color="text.secondary">Record the customer issue and route it to the right support owner.</Typography>
          </Stack>
          <Stack component="form" onSubmit={createTicket} spacing={2}>
            <Grid container spacing={1.5}>
              <Grid item xs={12} sm={6} md={2}><TextField name="customer_id" label="Customer ID" type="number" required fullWidth inputProps={{ min: 1 }} /></Grid>
              <Grid item xs={12} sm={6} md={3}><TextField name="subject" label="Subject" required fullWidth inputProps={{ maxLength: 180 }} /></Grid>
              <Grid item xs={12} sm={6} md={2}><TextField name="category" label="Category" select defaultValue="general" fullWidth><MenuItem value="account">Account</MenuItem><MenuItem value="billing">Billing</MenuItem><MenuItem value="playback">Playback</MenuItem><MenuItem value="device">Device</MenuItem><MenuItem value="subscription">Subscription</MenuItem><MenuItem value="general">General</MenuItem></TextField></Grid>
              <Grid item xs={12} sm={6} md={2}><TextField name="priority" label="Priority" select defaultValue="normal" fullWidth><MenuItem value="low">Low</MenuItem><MenuItem value="normal">Normal</MenuItem><MenuItem value="high">High</MenuItem><MenuItem value="urgent">Urgent</MenuItem></TextField></Grid>
              <Grid item xs={12} sm={6} md={3}><TextField name="assigned_to" label="Assign to" select defaultValue="" fullWidth><MenuItem value="">Unassigned</MenuItem>{assignees.map((a) => <MenuItem key={a.id} value={a.id}>{a.name || a.email}</MenuItem>)}</TextField></Grid>
              <Grid item xs={12} md={10}><TextField name="description" label="Issue details" required multiline minRows={2} fullWidth /></Grid>
              <Grid item xs={12} md={2} sx={{ display: 'flex', alignItems: 'flex-end' }}><Button type="submit" variant="contained" disabled={busy} fullWidth sx={{ minHeight: 48 }}>Create ticket</Button></Grid>
            </Grid>
          </Stack>
        </CardContent></Card>
        <Card sx={{ border: 1, borderColor: 'divider', boxShadow: 'none' }}><CardContent sx={{ p: { xs: 1.5, md: 2.5 } }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ sm: 'center' }} spacing={1} sx={{ mb: 2 }}>
            <Box><Typography variant="h6">Ticket inbox</Typography><Typography variant="body2" color="text.secondary">Search and narrow tickets by current status and ownership.</Typography></Box>
            <Chip size="small" variant="outlined" label={`${ticketMeta.total} tickets`} />
          </Stack>
          <Stack component="form" onSubmit={searchTickets} spacing={1.5} sx={{ p: { xs: 1.25, sm: 1.75 }, mb: 2, borderRadius: 2, bgcolor: 'background.neutral' }}>
            <Grid container spacing={1.25}>
              <Grid item xs={12} sm={6} md={4}>
                <TextField value={ticketSearch} onChange={(event) => setTicketSearch(event.target.value)} size="small" fullWidth placeholder="Subject, ticket ID, or customer ID" aria-label="Search support tickets" InputProps={{ startAdornment: <InputAdornment position="start"><Iconify icon="solar:magnifer-linear" /></InputAdornment> }} />
              </Grid>
              <Grid item xs={6} sm={3} md={2}><TextField select size="small" label="Status" value={ticketFilters.status} onChange={(event) => updateTicketFilter('status', event.target.value)} fullWidth><MenuItem value="">All statuses</MenuItem>{[['open', 'Open'], ['in_progress', 'In progress'], ['waiting_customer', 'Waiting on customer'], ['escalated', 'Escalated'], ['resolved', 'Resolved'], ['closed', 'Closed']].map(([value, label]) => <MenuItem key={value} value={value}>{label}</MenuItem>)}</TextField></Grid>
              <Grid item xs={6} sm={3} md={2}><TextField select size="small" label="Priority" value={ticketFilters.priority} onChange={(event) => updateTicketFilter('priority', event.target.value)} fullWidth><MenuItem value="">All priorities</MenuItem>{['low', 'normal', 'high', 'urgent'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField></Grid>
              <Grid item xs={6} sm={3} md={2}><TextField select size="small" label="Category" value={ticketFilters.category} onChange={(event) => updateTicketFilter('category', event.target.value)} fullWidth><MenuItem value="">All categories</MenuItem>{['account', 'billing', 'playback', 'device', 'subscription', 'general'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField></Grid>
              <Grid item xs={6} sm={3} md={2}><TextField select size="small" label="Assignee" value={ticketFilters.assigned_to} onChange={(event) => updateTicketFilter('assigned_to', event.target.value)} fullWidth><MenuItem value="">Anyone</MenuItem><MenuItem value="unassigned">Unassigned</MenuItem>{assignees.map((a) => <MenuItem key={a.id} value={String(a.id)}>{a.name || a.email}</MenuItem>)}</TextField></Grid>
            </Grid>
            <Stack direction="row" spacing={1} justifyContent="space-between" alignItems="center">
              <TextField select size="small" label="Escalation" value={ticketFilters.escalated} onChange={(event) => updateTicketFilter('escalated', event.target.value)} sx={{ minWidth: { xs: 150, sm: 190 } }}><MenuItem value="">All tickets</MenuItem><MenuItem value="1">Escalated only</MenuItem><MenuItem value="0">Not escalated</MenuItem></TextField>
              <Stack direction="row" spacing={1}>
                <Button type="button" size="small" onClick={() => { setTicketSearch(''); setTicketLoading(true); setTicketPage(0); setTicketFilters({ search: '', status: '', priority: '', category: '', assigned_to: '', escalated: '' }); }}>Reset filters</Button>
                <Button type="submit" variant="contained" size="small" startIcon={<Iconify icon="solar:magnifer-linear" />}>Search tickets</Button>
              </Stack>
            </Stack>
          </Stack>
          <TableContainer>
            <Table size="small" sx={{ minWidth: 850 }}><TableHead><TableRow>{['Ticket', 'Customer', 'Subject', 'Priority', 'Status', 'Escalation', 'Created'].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead>
              <TableBody>{tickets.map((ticket) => <TableRow key={ticket.id} hover onClick={() => openTicket(ticket.id)} sx={{ cursor: 'pointer' }}>
                <TableCell>#{ticket.id}</TableCell><TableCell>#{ticket.customer_id}</TableCell><TableCell>{ticket.subject}</TableCell><TableCell><Chip size="small" color={ticket.priority === 'urgent' ? 'error' : ticket.priority === 'high' ? 'warning' : 'default'} label={ticket.priority} /></TableCell><TableCell><Chip size="small" variant="outlined" color={ticket.status === 'escalated' ? 'error' : ticket.status === 'resolved' ? 'success' : ticket.status === 'waiting_customer' ? 'warning' : 'default'} label={ticket.status.replaceAll('_', ' ')} /></TableCell><TableCell>{ticket.escalation_target?.replaceAll('_', ' ') || '—'}</TableCell><TableCell>{dateText(ticket.created_at)}</TableCell>
              </TableRow>)}
                {!ticketLoading && tickets.length === 0 && <TableRow><TableCell colSpan={7} align="center" sx={{ py: 5 }}><Typography variant="subtitle2">No tickets match these filters</Typography><Typography variant="body2" color="text.secondary">Try a different search or reset your filters.</Typography></TableCell></TableRow>}
                {ticketLoading && <TableRow><TableCell colSpan={7} align="center" sx={{ py: 4 }}><Typography variant="body2" color="text.secondary">Loading tickets…</Typography></TableCell></TableRow>}
              </TableBody>
            </Table>
          </TableContainer>
          <TablePagination component="div" count={ticketMeta.total} page={ticketPage} onPageChange={(_, page) => { setTicketLoading(true); setTicketPage(page); }} rowsPerPage={ticketRowsPerPage} onRowsPerPageChange={(event) => { setTicketLoading(true); setTicketRowsPerPage(Number(event.target.value)); setTicketPage(0); }} rowsPerPageOptions={[10, 25, 50]} />
        </CardContent></Card>
        {selectedTicket && <Card><CardContent><Stack direction="row" justifyContent="space-between"><Typography variant="h6">Ticket #{selectedTicket.id}: {selectedTicket.subject}</Typography><Chip label={selectedTicket.status} /></Stack>
          <Typography variant="body2" sx={{ my: 1, whiteSpace: 'pre-wrap' }}>{selectedTicket.description}</Typography>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ mb: 2 }}>
            <TextField label="Status" select size="small" value={selectedTicket.status} onChange={(e) => run(async () => { await supportApi.updateTicket(selectedTicket.id, { status: e.target.value }); await openTicket(selectedTicket.id); }, 'Ticket status updated.')} sx={{ minWidth: 180 }}>
              {['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'].map((s) => <MenuItem key={s} value={s}>{s.replace('_', ' ')}</MenuItem>)}
            </TextField>
            <TextField label="Priority" select size="small" value={selectedTicket.priority} onChange={(e) => run(async () => { await supportApi.updateTicket(selectedTicket.id, { priority: e.target.value }); await openTicket(selectedTicket.id); }, 'Priority updated.')} sx={{ minWidth: 140 }}>
              {['low', 'normal', 'high', 'urgent'].map((s) => <MenuItem key={s} value={s}>{s}</MenuItem>)}
            </TextField>
            <TextField label="Assignee" select size="small" value={selectedTicket.assigned_to || ''} onChange={(e) => run(async () => { await supportApi.updateTicket(selectedTicket.id, { assigned_to: e.target.value ? Number(e.target.value) : null }); await openTicket(selectedTicket.id); }, 'Ticket assignment updated.')} sx={{ minWidth: 200 }}>
              <MenuItem value="">Unassigned</MenuItem>{assignees.map((a) => <MenuItem key={a.id} value={a.id}>{a.name || a.email}</MenuItem>)}
            </TextField>
            <Button variant="outlined" onClick={() => openCustomer(selectedTicket.customer_id)}>Open customer 360°</Button>
          </Stack>
          <Typography variant="subtitle2">Internal notes</Typography>
          {(selectedTicket.notes || []).map((note) => <Alert key={note.id} severity="info" sx={{ my: 1 }}><Typography variant="caption">Staff #{note.author_id} · {dateText(note.created_at)}</Typography><br />{note.note}</Alert>)}
          <Stack component="form" onSubmit={addNote} direction="row" spacing={1}><TextField name="note" required multiline label="Add internal note" fullWidth size="small" /><Button type="submit" variant="contained" disabled={busy}>Add note</Button></Stack>
          <Stack component="form" onSubmit={escalate} direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ mt: 2 }}>
            <TextField name="target" label="Escalate to" select defaultValue="technical_operations" size="small" sx={{ minWidth: 210 }}><MenuItem value="finance">Finance</MenuItem><MenuItem value="technical_operations">Technical Operations</MenuItem></TextField>
            <TextField name="reason" required label="Escalation reason" size="small" fullWidth /><Button type="submit" variant="outlined" disabled={busy}>Escalate</Button>
          </Stack>
        </CardContent></Card>}
      </Stack>}

      {canAccounts && tab === (canTickets ? 1 : 0) && <Stack spacing={2}>
        <Stack direction="row" spacing={1} component="form" onSubmit={(e) => { e.preventDefault(); refresh(); }}>
          <TextField value={search} onChange={(e) => setSearch(e.target.value)} label="Search customer by email, name, username or ID" size="small" fullWidth />
          <Button type="submit" variant="contained">Search</Button>
        </Stack>
        <Card><CardContent><Typography variant="h6">Customer matches</Typography><Table size="small"><TableHead><TableRow>{['ID', 'Name', 'Email', 'Status', 'Joined', ''].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead><TableBody>
          {customers.map((item) => <TableRow key={item.id}><TableCell>{item.id}</TableCell><TableCell>{item.name || '—'}</TableCell><TableCell>{item.email}</TableCell><TableCell>{item.banned ? 'Banned' : item.status ? 'Active' : 'Disabled'}</TableCell><TableCell>{dateText(item.created_at)}</TableCell><TableCell><Button size="small" onClick={() => openCustomer(item.id)}>View</Button></TableCell></TableRow>)}
        </TableBody></Table></CardContent></Card>
        {customer && <Card><CardContent><Typography variant="h6">Customer 360° — {customer.customer.name} (#{customer.customer.id})</Typography><Typography color="text.secondary">{customer.customer.email} · {customer.customer.banned ? 'Banned' : customer.customer.status ? 'Active' : 'Disabled'} · Joined {dateText(customer.customer.created_at)}</Typography>
          {customer.subscriptions && <><Typography variant="subtitle1" sx={{ mt: 2 }}>Subscription history (read-only)</Typography><Table size="small"><TableHead><TableRow>{['Plan', 'Status', 'Start', 'End', 'Amount', 'Level'].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead><TableBody>{customer.subscriptions.map((s) => <TableRow key={s.id}><TableCell>{s.name}</TableCell><TableCell>{s.status}</TableCell><TableCell>{dateText(s.start_date)}</TableCell><TableCell>{dateText(s.end_date)}</TableCell><TableCell>{s.amount}</TableCell><TableCell>{s.level}</TableCell></TableRow>)}</TableBody></Table></>}
          {canPayments && <><Typography variant="subtitle1" sx={{ mt: 2 }}>Payment investigation (masked references)</Typography><Table size="small"><TableHead><TableRow>{['Source', 'Gateway', 'Reference', 'Purchase', 'Amount', 'Status', 'Date'].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead><TableBody>{payments.map((p) => <TableRow key={`${p.source}-${p.id}`}><TableCell>{p.source}</TableCell><TableCell>{p.gateway}</TableCell><TableCell>{p.reference || '—'}</TableCell><TableCell>{p.purchase_type} #{p.purchase_id}</TableCell><TableCell>{(p.amount_minor / 100).toFixed(2)} {p.currency}</TableCell><TableCell>{p.status}</TableCell><TableCell>{dateText(p.created_at)}</TableCell></TableRow>)}</TableBody></Table></>}
          {canDevices && <><Typography variant="subtitle1" sx={{ mt: 2 }}>Registered devices and sessions</Typography><Table size="small"><TableHead><TableRow>{['Device', 'Platform', 'Profile', 'Session', 'Last activity', ''].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead><TableBody>{devices.map((d) => <TableRow key={d.id}><TableCell>{d.device_name || `Device #${d.id}`}</TableCell><TableCell>{d.platform}</TableCell><TableCell>{d.active_profile || '—'}</TableCell><TableCell>{d.session_active ? 'Active' : 'Inactive'}</TableCell><TableCell>{dateText(d.last_activity)}</TableCell><TableCell>{canRevoke && d.session_active && <Button color="error" size="small" disabled={busy} onClick={() => { if (window.confirm('Revoke this customer device session?')) run(() => supportApi.revoke(customer.customer.id, d.id).then(() => openCustomer(customer.customer.id)), 'Device session revoked.'); }}>Revoke</Button>}</TableCell></TableRow>)}</TableBody></Table></>}
          {canPlayback && <><Typography variant="subtitle1" sx={{ mt: 2 }}>Playback diagnostics</Typography><Table size="small"><TableHead><TableRow>{['Event', 'Session', 'Content', 'Occurred'].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead><TableBody>{(playback?.events || []).map((event) => <TableRow key={event.id}><TableCell>{event.event_code}</TableCell><TableCell>{event.session_reference || '—'}</TableCell><TableCell>{event.content_type} #{event.content_id}</TableCell><TableCell>{dateText(event.occurred_at)}</TableCell></TableRow>)}</TableBody></Table><Typography variant="subtitle2" sx={{ mt: 1 }}>Recent playback sessions</Typography><Table size="small"><TableHead><TableRow>{['Session', 'Content', 'Last heartbeat', 'Status', ''].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead><TableBody>{(playback?.sessions || []).map((session) => <TableRow key={session.id}><TableCell>{session.session_reference}</TableCell><TableCell>{session.content_type} #{session.content_id}</TableCell><TableCell>{dateText(session.last_heartbeat_at)}</TableCell><TableCell>{session.ended_at ? 'Ended' : 'Active'}</TableCell><TableCell>{canRevoke && !session.ended_at && <Button color="error" size="small" disabled={busy} onClick={() => { if (window.confirm('End this playback session for the customer?')) run(() => supportApi.revokePlaybackSession(customer.customer.id, session.id).then(() => openCustomer(customer.customer.id)), 'Playback session revoked.'); }}>Revoke</Button>}</TableCell></TableRow>)}</TableBody></Table></>}
        </CardContent></Card>}
      </Stack>}
    </DashboardContent>
  );
}

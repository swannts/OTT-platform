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
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
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
import { supportApi } from './support.api';

const errorText = (error) => error?.response?.data?.message || 'The support request could not be completed.';
const dateText = (value) => (value ? new Date(value).toLocaleString() : '—');

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
      tasks.push(supportApi.tickets().then((value) => { result.tickets = value.data || []; }));
      tasks.push(supportApi.assignees().then((value) => { result.assignees = value; }));
    }
    if (canAccounts && search.trim().length >= 2) {
      tasks.push(supportApi.customers(search.trim()).then((value) => { result.customers = value.data || []; }));
    }
    await Promise.all(tasks);
    return result;
  }, [canAccounts, canTickets, search]);

  const applySupport = useCallback((result) => {
    setOverview(result.overview || null);
    setTickets(result.tickets || []);
    setAssignees(result.assignees || []);
    setCustomers(result.customers || []);
  }, []);

  const refresh = useCallback(async () => {
    setError('');
    try {
      applySupport(await loadSupport());
    } catch (cause) {
      setError(errorText(cause));
    }
  }, [applySupport, loadSupport]);

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
      <Tabs value={tab} onChange={(_, value) => setTab(value)} sx={{ mb: 2 }}>
        {canTickets && <Tab label="Ticket inbox" />}{canAccounts && <Tab label="Customer 360°" />}
      </Tabs>

      {canTickets && tab === 0 && <Stack spacing={2}>
        <Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>Create support ticket</Typography>
          <Stack component="form" onSubmit={createTicket} direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
            <TextField name="customer_id" label="Customer ID" type="number" required size="small" />
            <TextField name="subject" label="Subject" required size="small" sx={{ minWidth: 180 }} />
            <TextField name="category" label="Category" select defaultValue="general" size="small" sx={{ minWidth: 140 }}><MenuItem value="account">Account</MenuItem><MenuItem value="billing">Billing</MenuItem><MenuItem value="playback">Playback</MenuItem><MenuItem value="device">Device</MenuItem><MenuItem value="subscription">Subscription</MenuItem><MenuItem value="general">General</MenuItem></TextField>
            <TextField name="priority" label="Priority" select defaultValue="normal" size="small" sx={{ minWidth: 120 }}><MenuItem value="low">Low</MenuItem><MenuItem value="normal">Normal</MenuItem><MenuItem value="high">High</MenuItem><MenuItem value="urgent">Urgent</MenuItem></TextField>
            <TextField name="assigned_to" label="Assign to" select defaultValue="" size="small" sx={{ minWidth: 170 }}><MenuItem value="">Unassigned</MenuItem>{assignees.map((a) => <MenuItem key={a.id} value={a.id}>{a.name || a.email}</MenuItem>)}</TextField>
            <TextField name="description" label="Issue details" required multiline minRows={1} sx={{ flex: 1 }} />
            <Button type="submit" variant="contained" disabled={busy}>Create</Button>
          </Stack>
        </CardContent></Card>
        <Card><CardContent><Typography variant="h6" sx={{ mb: 1 }}>Ticket inbox</Typography>
          <Table size="small"><TableHead><TableRow>{['Ticket', 'Customer', 'Subject', 'Priority', 'Status', 'Escalation', 'Created'].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead>
            <TableBody>{tickets.map((ticket) => <TableRow key={ticket.id} hover onClick={() => openTicket(ticket.id)} sx={{ cursor: 'pointer' }}>
              <TableCell>#{ticket.id}</TableCell><TableCell>#{ticket.customer_id}</TableCell><TableCell>{ticket.subject}</TableCell><TableCell><Chip size="small" label={ticket.priority} /></TableCell><TableCell>{ticket.status}</TableCell><TableCell>{ticket.escalation_target || '—'}</TableCell><TableCell>{dateText(ticket.created_at)}</TableCell>
            </TableRow>)}</TableBody>
          </Table>
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
          {canPlayback && <><Typography variant="subtitle1" sx={{ mt: 2 }}>Playback diagnostics</Typography><Table size="small"><TableHead><TableRow>{['Event', 'Session', 'Content', 'Occurred'].map((x) => <TableCell key={x}>{x}</TableCell>)}</TableRow></TableHead><TableBody>{(playback?.events || []).map((event) => <TableRow key={event.id}><TableCell>{event.event_code}</TableCell><TableCell>{event.session_reference || '—'}</TableCell><TableCell>{event.content_type} #{event.content_id}</TableCell><TableCell>{dateText(event.occurred_at)}</TableCell></TableRow>)}</TableBody></Table><Typography variant="subtitle2" sx={{ mt: 1 }}>Recent playback sessions</Typography>{(playback?.sessions || []).map((session) => <Chip key={session.session_reference} sx={{ m: 0.5 }} label={`${session.content_type} #${session.content_id} · ${session.session_reference}`} />)}</>}
        </CardContent></Card>}
      </Stack>}
    </DashboardContent>
  );
}

'use client';

import { useCallback, useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
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
import { financeApi } from './finance.api';

const errorText = (error) => error?.response?.data?.message || 'The finance request could not be completed.';
const zeroDecimal = new Set(['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF']);
const toMinor = (amount, currency) => Math.round(Number(amount) * (zeroDecimal.has(String(currency).toUpperCase()) ? 1 : 100));
const money = (amount, currency) => `${currency || 'UNRECORDED'} ${Number(amount || 0).toFixed(2)}`;

function Metric({ title, value }) {
  return <Card><CardContent><Typography variant="body2" color="text.secondary">{title}</Typography><Typography variant="h4">{value ?? '—'}</Typography></CardContent></Card>;
}

export function FinanceOperationsPage() {
  const { user } = useAuthContext();
  const [overview, setOverview] = useState(null);
  const [payments, setPayments] = useState([]);
  const [refunds, setRefunds] = useState([]);
  const [disputes, setDisputes] = useState([]);
  const [failedEvents, setFailedEvents] = useState([]);
  const [reconciliation, setReconciliation] = useState(null);
  const [reconciliationHistory, setReconciliationHistory] = useState([]);
  const [report, setReport] = useState(null);
  const [selected, setSelected] = useState(null);
  const [refundTarget, setRefundTarget] = useState(null);
  const [search, setSearch] = useState('');
  const [gateway, setGateway] = useState('');
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const canViewPayments = hasPermission(user, 'finance.payments.view');
  const canViewInvoices = hasPermission(user, 'finance.invoices.view');
  const canRequestRefund = hasPermission(user, 'finance.refunds.request');
  const canApproveRefund = hasPermission(user, 'finance.refunds.approve');
  const canReconcile = hasPermission(user, 'finance.reconciliation.view');
  const canExport = hasPermission(user, 'finance.reports.export');
  const canAccess = hasAnyPermission(user, ['finance.payments.view', 'finance.invoices.view', 'finance.refunds.request', 'finance.refunds.approve', 'finance.reconciliation.view']);

  const loadFinance = useCallback(async () => {
    const result = { paymentsEnabled: canViewPayments, reconciliationEnabled: canReconcile };
    const tasks = [];
    if (canViewPayments) {
      tasks.push(financeApi.overview().then((value) => { result.overview = value; }));
      tasks.push(financeApi.payments({ search: search || undefined, gateway: gateway || undefined, status: status || undefined, per_page: 50 }).then((value) => { result.payments = value.data || []; }));
      tasks.push(financeApi.refunds({ per_page: 50 }).then((value) => { result.refunds = value.data || []; }));
      tasks.push(financeApi.disputes().then((value) => { result.disputes = value.data || []; }));
      tasks.push(financeApi.failedEvents().then((value) => { result.failedEvents = value; }));
    }
    if (canReconcile) {
      tasks.push(financeApi.reconciliation().then((value) => { result.reconciliation = value; }));
      tasks.push(financeApi.reconciliationHistory().then((value) => { result.reconciliationHistory = value; }));
      tasks.push(financeApi.report({}).then((value) => { result.report = value; }));
    }
    await Promise.all(tasks);
    return result;
  }, [canReconcile, canViewPayments, gateway, search, status]);

  const applyFinance = useCallback((result) => {
    setOverview(result.paymentsEnabled ? result.overview : null);
    setPayments(result.paymentsEnabled ? result.payments : []);
    setRefunds(result.paymentsEnabled ? result.refunds : []);
    setDisputes(result.paymentsEnabled ? result.disputes : []);
    setFailedEvents(result.paymentsEnabled ? result.failedEvents : []);
    setReconciliation(result.reconciliationEnabled ? result.reconciliation : null);
    setReconciliationHistory(result.reconciliationEnabled ? result.reconciliationHistory : []);
    setReport(result.reconciliationEnabled ? result.report : null);
  }, []);

  const refresh = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      applyFinance(await loadFinance());
    } catch (cause) {
      setError(errorText(cause));
    } finally {
      setLoading(false);
    }
  }, [applyFinance, loadFinance]);

  useEffect(() => {
    if (!canAccess) return undefined;

    let active = true;
    loadFinance()
      .then((result) => {
        if (active) {
          applyFinance(result);
          setError('');
        }
      })
      .catch((cause) => {
        if (active) setError(errorText(cause));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [applyFinance, canAccess, loadFinance]);

  const openPayment = async (payment) => {
    setSelected({ payment, refunds: [], disputes: [] });
    if (!payment.verified_payment_id) return;
    try { setSelected(await financeApi.payment(payment.verified_payment_id)); }
    catch (cause) { setError(errorText(cause)); }
  };

  const submitRefund = async (event) => {
    event.preventDefault();
    setBusy(true); setError(''); setNotice('');
    const form = new FormData(event.currentTarget);
    try {
      await financeApi.requestRefund({
        verified_payment_id: refundTarget.verified_payment_id,
        amount_minor: toMinor(form.get('amount'), refundTarget.currency),
        reason: String(form.get('reason') || '').trim(),
        idempotency_key: window.crypto.randomUUID(),
      });
      setRefundTarget(null); setNotice('Refund request submitted for independent approval.'); await refresh();
    } catch (cause) { setError(errorText(cause)); }
    finally { setBusy(false); }
  };

  const reviewRefund = async (refund, decision) => {
    setBusy(true); setError(''); setNotice('');
    try { await financeApi.reviewRefund(refund.id, { decision }); setNotice(decision === 'approve' ? 'Refund approved.' : 'Refund rejected.'); await refresh(); }
    catch (cause) { setError(errorText(cause)); }
    finally { setBusy(false); }
  };

  const executeRefund = async (refund) => {
    setBusy(true); setError(''); setNotice('');
    try { const result = await financeApi.executeRefund(refund.id); setNotice(result.message); await refresh(); }
    catch (cause) { setError(errorText(cause)); }
    finally { setBusy(false); }
  };

  const reconcilePayment = async (payment) => {
    setBusy(true); setError(''); setNotice('');
    try {
      const result = await financeApi.reconcilePayment(payment.verified_payment_id);
      setNotice(`Provider reconciliation: ${result.result}.`);
    } catch (cause) { setError(errorText(cause)); }
    finally { setBusy(false); }
  };

  const openInvoice = async (payment) => {
    const invoiceWindow = window.open('about:blank', '_blank');
    if (!invoiceWindow) { setError('Allow pop-ups to open the invoice.'); return; }
    setBusy(true); setError('');
    try {
      const response = await financeApi.invoice(payment.verified_payment_id);
      const url = URL.createObjectURL(response.data);
      invoiceWindow.location.href = url;
      window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (cause) { invoiceWindow.close(); setError(errorText(cause)); }
    finally { setBusy(false); }
  };

  const exportPayments = async () => {
    setBusy(true); setError('');
    try {
      const response = await financeApi.export({ search, gateway, status });
      const url = URL.createObjectURL(response.data);
      const link = document.createElement('a');
      link.href = url; link.download = 'finance-payments.csv'; link.click(); URL.revokeObjectURL(url);
    } catch (cause) { setError(errorText(cause)); }
    finally { setBusy(false); }
  };

  if (!canAccess) return <DashboardContent><Alert severity="error">You do not have permission to access finance operations.</Alert></DashboardContent>;

  return (
    <DashboardContent>
      <RbacPageHeader title="Finance operations" description="Review verified payments, invoices, refunds, disputes, and reconciliation results." />
      <Stack spacing={2}>
        {error && <Alert severity="error">{error}</Alert>}
        {notice && <Alert severity="success">{notice}</Alert>}
        {canViewPayments && <Grid container spacing={2}>
          <Grid item xs={12} sm={6} md={3}><Metric title="Verified payments" value={overview?.verified_payments} /></Grid>
          <Grid item xs={12} sm={6} md={3}><Metric title="Initiated payments" value={overview?.initiated_payments} /></Grid>
          <Grid item xs={12} sm={6} md={3}><Metric title="Pending refunds" value={overview?.pending_refunds} /></Grid>
          <Grid item xs={12} sm={6} md={3}><Metric title="Open disputes" value={overview?.open_disputes} /></Grid>
        </Grid>}
        {canViewPayments && <Card><CardContent>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} alignItems={{ md: 'center' }} sx={{ mb: 2 }}>
            <Typography variant="h6" sx={{ flexGrow: 1 }}>Payment transactions</Typography>
            <TextField size="small" label="Search reference or customer" value={search} onChange={(event) => { setLoading(true); setError(''); setSearch(event.target.value); }} />
            <TextField size="small" select label="Gateway" value={gateway} onChange={(event) => { setLoading(true); setError(''); setGateway(event.target.value); }} sx={{ minWidth: 130 }}><MenuItem value="">All</MenuItem>{['stripe', 'razorpay', 'paystack', 'revenuecat'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField>
            <TextField size="small" select label="Status" value={status} onChange={(event) => { setLoading(true); setError(''); setStatus(event.target.value); }} sx={{ minWidth: 130 }}><MenuItem value="">All</MenuItem>{['paid', 'initiated', 'failed', 'pending'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField>
            {canExport && <Button disabled={busy} onClick={exportPayments}>Export CSV</Button>}
          </Stack>
          {loading ? <Typography color="text.secondary">Loading transactions…</Typography> : payments.length === 0 ? <Typography color="text.secondary">No payment records matched these filters.</Typography> : (
            <Table size="small"><TableHead><TableRow><TableCell>Reference</TableCell><TableCell>Customer</TableCell><TableCell>Purchase</TableCell><TableCell>Gateway</TableCell><TableCell>Amount</TableCell><TableCell>Status</TableCell><TableCell>Source</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead>
              <TableBody>{payments.map((payment) => <TableRow key={`${payment.source}-${payment.source_id}`} hover>
                <TableCell>{payment.provider_reference || `record-${payment.source_id}`}</TableCell><TableCell>{payment.user_name || `#${payment.user_id}`}<Typography display="block" variant="caption" color="text.secondary">{payment.user_email}</Typography></TableCell><TableCell>{payment.purchase_type}</TableCell><TableCell>{payment.gateway}</TableCell><TableCell>{money(payment.amount, payment.currency)}</TableCell><TableCell>{payment.status}</TableCell><TableCell>{payment.source.replaceAll('_', ' ')}</TableCell>
                <TableCell align="right"><Stack direction="row" justifyContent="flex-end" spacing={0.5}><Button size="small" onClick={() => openPayment(payment)}>Details</Button>{payment.verified_payment_id && canViewInvoices && <Button size="small" disabled={busy} onClick={() => openInvoice(payment)}>Invoice</Button>}{payment.refundable && canRequestRefund && <Button size="small" onClick={() => setRefundTarget(payment)}>Request refund</Button>}{payment.verified_payment_id && canReconcile && <Button size="small" disabled={busy} onClick={() => reconcilePayment(payment)}>Reconcile</Button>}</Stack></TableCell>
              </TableRow>)}</TableBody>
            </Table>
          )}
        </CardContent></Card>}
        {canViewPayments && <Card><CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>Refund requests</Typography>
          {refunds.length === 0 ? <Typography color="text.secondary">No refund requests.</Typography> : <Table size="small"><TableHead><TableRow><TableCell>Payment</TableCell><TableCell>Requester</TableCell><TableCell>Amount</TableCell><TableCell>Reason</TableCell><TableCell>Status</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead>
            <TableBody>{refunds.map((refund) => <TableRow key={refund.id}><TableCell>{refund.payment?.gateway} · {refund.payment?.provider_reference}</TableCell><TableCell>{refund.requester?.full_name || refund.requester?.email}</TableCell><TableCell>{money(refund.amount_minor / (zeroDecimal.has(refund.currency) ? 1 : 100), refund.currency)}</TableCell><TableCell>{refund.reason}</TableCell><TableCell>{refund.status}</TableCell><TableCell align="right"><Stack direction="row" justifyContent="flex-end">{canApproveRefund && refund.status === 'pending' && <><Button size="small" color="error" disabled={busy} onClick={() => reviewRefund(refund, 'reject')}>Reject</Button><Button size="small" disabled={busy} onClick={() => reviewRefund(refund, 'approve')}>Approve</Button></>}{canApproveRefund && ['approved', 'processing'].includes(refund.status) && <Button size="small" disabled={busy} onClick={() => executeRefund(refund)}>{refund.status === 'processing' ? 'Retry safely' : 'Execute'}</Button>}</Stack></TableCell></TableRow>)}</TableBody>
          </Table>}
        </CardContent></Card>}
        {canViewPayments && <Card><CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>Disputes</Typography>
          {disputes.length === 0 ? <Typography color="text.secondary">No provider disputes received.</Typography> : <Table size="small"><TableHead><TableRow><TableCell>Provider dispute</TableCell><TableCell>Payment reference</TableCell><TableCell>Reason</TableCell><TableCell>Amount</TableCell><TableCell>Status</TableCell><TableCell>Evidence due</TableCell></TableRow></TableHead><TableBody>{disputes.map((dispute) => <TableRow key={`${dispute.gateway}-${dispute.provider_dispute_id}`}><TableCell>{dispute.gateway} · {dispute.provider_dispute_id}</TableCell><TableCell>{dispute.provider_reference || '—'}</TableCell><TableCell>{dispute.reason || '—'}</TableCell><TableCell>{dispute.amount_minor ? money(dispute.amount_minor / (zeroDecimal.has(dispute.currency) ? 1 : 100), dispute.currency) : '—'}</TableCell><TableCell>{dispute.status}</TableCell><TableCell>{dispute.evidence_due_at ? new Date(dispute.evidence_due_at).toLocaleString() : '—'}</TableCell></TableRow>)}</TableBody></Table>}
        </CardContent></Card>}
        {canViewPayments && <Card><CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>Failed payment events</Typography>
          {failedEvents.length === 0 ? <Typography color="text.secondary">No failed or pending provider webhook events.</Typography> : <Table size="small"><TableHead><TableRow><TableCell>Provider</TableCell><TableCell>Event</TableCell><TableCell>Reference</TableCell><TableCell>Status</TableCell><TableCell>Attempts</TableCell><TableCell>Failure</TableCell><TableCell>Last update</TableCell></TableRow></TableHead><TableBody>{failedEvents.map((event) => <TableRow key={event.id}><TableCell>{event.gateway}</TableCell><TableCell>{event.event_type}</TableCell><TableCell>{event.provider_reference || '—'}</TableCell><TableCell>{event.status}</TableCell><TableCell>{event.attempts}</TableCell><TableCell>{event.failure_code || '—'}</TableCell><TableCell>{new Date(event.updated_at).toLocaleString()}</TableCell></TableRow>)}</TableBody></Table>}
        </CardContent></Card>}
        {canReconcile && <Grid container spacing={2}>
          <Grid item xs={12} md={6}><Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>Reconciliation</Typography><Typography>Verified fulfillments missing a payment ledger: {reconciliation?.verified_fulfillments_missing_ledger ?? '—'}</Typography><Typography>Open disputes: {reconciliation?.open_disputes ?? '—'}</Typography><Typography>Provider checks compare verified amount, currency, and status. Refund matching is shown where the provider exposes refunded totals.</Typography></CardContent></Card></Grid>
          <Grid item xs={12} md={6}><Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>Revenue report</Typography>{!report?.payments?.length ? <Typography color="text.secondary">No verified revenue in the selected period.</Typography> : report.payments.map((row) => <Stack key={`${row.settlement_day}-${row.gateway}-${row.currency}`} direction="row" justifyContent="space-between"><Typography variant="body2">{row.settlement_day} · {row.gateway} · {row.currency}</Typography><Typography variant="body2">Gross {row.gross_minor} minor units</Typography></Stack>)}<Typography variant="caption" color="text.secondary">Amounts are grouped by payment currency; this is a ledger report, not a provider payout statement.</Typography></CardContent></Card></Grid>
        </Grid>}
        {canReconcile && <Card><CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>Recent provider reconciliation runs</Typography>
          {reconciliationHistory.length === 0 ? <Typography color="text.secondary">No provider checks have been recorded.</Typography> : <Table size="small"><TableHead><TableRow><TableCell>Payment</TableCell><TableCell>Provider status</TableCell><TableCell>Result</TableCell><TableCell>Amount</TableCell><TableCell>Refund comparison</TableCell><TableCell>Checked by</TableCell><TableCell>Time</TableCell></TableRow></TableHead><TableBody>{reconciliationHistory.map((run) => <TableRow key={run.id}><TableCell>{run.gateway} · {run.provider_reference}</TableCell><TableCell>{run.provider_status || '—'}</TableCell><TableCell>{run.result}</TableCell><TableCell>{run.provider_amount_minor === null ? '—' : `${run.provider_amount_minor} ${run.provider_currency}`}</TableCell><TableCell>{run.refunds_match === null ? 'not available' : run.refunds_match ? 'matched' : `mismatch (${run.local_refunded_minor} vs ${run.provider_refunded_minor})`}</TableCell><TableCell>{[run.performer_first_name, run.performer_last_name].filter(Boolean).join(' ') || run.performer_email || '—'}</TableCell><TableCell>{new Date(run.created_at).toLocaleString()}</TableCell></TableRow>)}</TableBody></Table>}
        </CardContent></Card>}
      </Stack>

      <Dialog open={Boolean(refundTarget)} onClose={() => !busy && setRefundTarget(null)} fullWidth maxWidth="sm">
        <DialogTitle>Request refund</DialogTitle>
        <form onSubmit={submitRefund}><DialogContent><Stack spacing={2} sx={{ pt: 1 }}>
          <Alert severity="info">An independent approver must approve this request before the refund is sent to {refundTarget?.gateway}. The original payment remains unchanged.</Alert>
          <Typography variant="body2">Payment {refundTarget?.provider_reference} · remaining check occurs on the server</Typography>
          <TextField name="amount" label={`Refund amount (${refundTarget?.currency || 'currency units'})`} type="number" required inputProps={{ min: 0.01, max: refundTarget?.amount, step: '0.01' }} defaultValue={refundTarget?.amount ?? ''} />
          <TextField name="reason" label="Reason" required multiline minRows={3} inputProps={{ minLength: 5, maxLength: 2000 }} />
        </Stack></DialogContent><DialogActions><Button onClick={() => setRefundTarget(null)} disabled={busy}>Cancel</Button><Button type="submit" variant="contained" disabled={busy}>{busy ? 'Submitting…' : 'Submit for approval'}</Button></DialogActions></form>
      </Dialog>
      <Dialog open={Boolean(selected)} onClose={() => setSelected(null)} fullWidth maxWidth="md">
        <DialogTitle>Payment details</DialogTitle><DialogContent><Stack spacing={1} sx={{ pt: 1 }}>
          {selected?.payment && Object.entries(selected.payment).map(([key, value]) => <Stack key={key} direction="row" justifyContent="space-between" spacing={2}><Typography variant="body2" color="text.secondary">{key.replaceAll('_', ' ')}</Typography><Typography variant="body2">{typeof value === 'object' ? JSON.stringify(value) : String(value ?? '—')}</Typography></Stack>)}
          {selected?.refunds?.length > 0 && <Typography variant="subtitle2" sx={{ mt: 2 }}>Refunds: {selected.refunds.length}</Typography>}
        </Stack></DialogContent><DialogActions><Button onClick={() => setSelected(null)}>Close</Button></DialogActions>
      </Dialog>
    </DashboardContent>
  );
}

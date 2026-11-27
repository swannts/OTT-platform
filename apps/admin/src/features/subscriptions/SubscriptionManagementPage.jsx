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
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { hasPermission } from 'src/features/rbac/permissions';
import { RbacPageHeader } from 'src/features/rbac/components/RbacPageHeader';
import { Iconify } from 'src/components/iconify';
import { subscriptionsApi } from './subscriptions.api';

const emptyPlan = { name: '', price: '', level: '1', duration: 'month', duration_value: '1', trial_days: '0', description: '', status: true };
const errorMessage = (error) => error?.response?.data?.message || 'The subscription request could not be completed.';
const numberOrNull = (value) => value === '' || value === null || value === undefined ? null : Number(value);

function SubscriptionTableSkeletonRows({ columns, rows = 4 }) {
  return Array.from({ length: rows }, (_, rowIndex) => (
    <TableRow key={`loading-row-${rowIndex}`} aria-hidden="true">
      {Array.from({ length: columns }, (_, columnIndex) => (
        <TableCell key={`loading-cell-${columnIndex}`}>
          <Skeleton variant="text" width={columnIndex === 0 ? '78%' : '58%'} />
        </TableCell>
      ))}
    </TableRow>
  ));
}

export function SubscriptionManagementPage() {
  const { user } = useAuthContext();
  const [plans, setPlans] = useState([]);
  const [pricingOptions, setPricingOptions] = useState({ countries: [], currencies: [] });
  const [coupons, setCoupons] = useState([]);
  const [performance, setPerformance] = useState([]);
  const [subscribers, setSubscribers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [editing, setEditing] = useState(null);
  const [editingCoupon, setEditingCoupon] = useState(null);
  const [busy, setBusy] = useState(false);
  const [simulation, setSimulation] = useState({ plan_id: '', required_plan_level: '1', profile_count: '1', device_count: '1', stream_count: '1', requested_video_quality: '720' });
  const [simulationResult, setSimulationResult] = useState(null);
  const [simulating, setSimulating] = useState(false);
  const [activeSection, setActiveSection] = useState('plans');

  const canView = hasPermission(user, 'subscriptions.view');
  const canCreate = hasPermission(user, 'subscriptions.plans.create');
  const canUpdate = hasPermission(user, 'subscriptions.plans.update');
  const canArchive = hasPermission(user, 'subscriptions.plans.archive');
  const canActOnPlans = canUpdate || canArchive;
  const canManageCoupons = hasPermission(user, 'subscriptions.coupons.manage');
  const canManageTrials = hasPermission(user, 'subscriptions.trials.manage');
  const canViewSubscribers = hasPermission(user, 'subscriptions.subscribers.view');

  const loadSubscriptions = useCallback(async () => {
    const [plansResponse, performanceResponse, pricingOptionsResponse] = await Promise.all([
      subscriptionsApi.plans({ per_page: 100 }),
      subscriptionsApi.performance(),
      subscriptionsApi.pricingOptions(),
    ]);
    const subscribersResponse = canViewSubscribers
      ? await subscriptionsApi.subscribers({ per_page: 10 })
      : null;
    const couponsResponse = canManageCoupons
      ? await subscriptionsApi.coupons({ per_page: 100 })
      : null;
    return { plansResponse, performanceResponse, pricingOptionsResponse, subscribersResponse, couponsResponse };
  }, [canManageCoupons, canViewSubscribers]);

  const applySubscriptions = useCallback(({ plansResponse, performanceResponse, pricingOptionsResponse, subscribersResponse, couponsResponse }) => {
    setPlans(plansResponse.data || []);
    setPricingOptions(pricingOptionsResponse.data || { countries: [], currencies: [] });
    setPerformance(performanceResponse.data || []);
    setSubscribers(subscribersResponse?.data || []);
    setCoupons(couponsResponse?.data || []);
  }, []);

  const refresh = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      applySubscriptions(await loadSubscriptions());
    } catch (cause) {
      setError(errorMessage(cause));
    } finally {
      setLoading(false);
    }
  }, [applySubscriptions, loadSubscriptions]);

  useEffect(() => {
    if (!canView) return undefined;

    let active = true;
    loadSubscriptions()
      .then((result) => {
        if (active) {
          applySubscriptions(result);
          setError('');
        }
      })
      .catch((cause) => {
        if (active) setError(errorMessage(cause));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [applySubscriptions, canView, loadSubscriptions]);

  const save = async (event) => {
    event.preventDefault();
    setBusy(true); setError(''); setNotice('');
    const form = new FormData(event.currentTarget);
    const values = Object.fromEntries(form.entries());
    delete values['name_translations.bn'];
    values.name_translations = {
      en: String(form.get('name') || '').trim(),
      bn: String(form.get('name_translations.bn') || '').trim(),
    };
    values.price = Number(values.price);
    values.level = Number(values.level);
    values.duration_value = Number(values.duration_value);
    values.trial_days = Number(values.trial_days || 0);
    values.status = values.status === 'true';
    values.regional_prices = editing?.regional_prices || [];
    values.limits = {
      device_limit: numberOrNull(form.get('device_limit')),
      profile_limit: numberOrNull(form.get('profile_limit')),
      simultaneous_streams: numberOrNull(form.get('simultaneous_streams')),
      max_video_quality: numberOrNull(form.get('max_video_quality')),
    };
    try {
      if (editing?.id) await subscriptionsApi.updatePlan(editing.id, values);
      else await subscriptionsApi.createPlan(values);
      setEditing(null); setNotice(editing?.id ? 'Plan updated.' : 'Plan created.'); await refresh();
    } catch (cause) { setError(errorMessage(cause)); }
    finally { setBusy(false); }
  };

  const archive = async (plan) => {
    if (!window.confirm(`Archive ${plan.name}? Existing subscribers keep their saved entitlement snapshot.`)) return;
    setBusy(true); setError(''); setNotice('');
    try { await subscriptionsApi.archivePlan(plan.id); setNotice(`${plan.name} archived.`); await refresh(); }
    catch (cause) { setError(errorMessage(cause)); }
    finally { setBusy(false); }
  };

  const runSimulation = async (event) => {
    event.preventDefault(); setSimulationResult(null); setError(''); setSimulating(true);
    try {
      const response = await subscriptionsApi.simulate({
        ...simulation,
        plan_id: Number(simulation.plan_id),
        required_plan_level: Number(simulation.required_plan_level),
        profile_count: Number(simulation.profile_count),
        device_count: Number(simulation.device_count),
        stream_count: Number(simulation.stream_count),
        requested_video_quality: Number(simulation.requested_video_quality),
      });
      setSimulationResult(response.data);
    } catch (cause) { setError(errorMessage(cause)); }
    finally { setSimulating(false); }
  };

  const saveCoupon = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('');
    const form = new FormData(event.currentTarget);
    const values = {
      code: String(form.get('code') || '').trim().toUpperCase(),
      discount: Number(form.get('discount')),
      discount_type: form.get('discount_type'),
      max_redemptions: numberOrNull(form.get('max_redemptions')),
      per_user_limit: numberOrNull(form.get('per_user_limit')),
      start_date: form.get('start_date') || null,
      expire_date: form.get('expire_date') || null,
      description: form.get('description') || null,
      status: form.get('status') === 'true',
      subscription_plan_ids: String(form.get('subscription_plan_ids') || '').split(',').filter(Boolean).map(Number),
    };
    try {
      if (editingCoupon?.id) await subscriptionsApi.updateCoupon(editingCoupon.id, values);
      else await subscriptionsApi.createCoupon(values);
      setEditingCoupon(null); setNotice(editingCoupon?.id ? 'Coupon updated.' : 'Coupon created.'); await refresh();
    } catch (cause) { setError(errorMessage(cause)); }
    finally { setBusy(false); }
  };

  const archiveCoupon = async (coupon) => {
    if (!window.confirm(`Deactivate coupon ${coupon.code}? Existing redemptions remain recorded.`)) return;
    setBusy(true); setError('');
    try { await subscriptionsApi.archiveCoupon(coupon.id); setNotice(`${coupon.code} deactivated.`); await refresh(); }
    catch (cause) { setError(errorMessage(cause)); }
    finally { setBusy(false); }
  };

  if (!canView) return <DashboardContent><Alert severity="error">You do not have permission to view subscriptions.</Alert></DashboardContent>;

  return (
    <DashboardContent>
      <RbacPageHeader title="Subscriptions" description="Manage plans, subscriber activity, coupons, and entitlement rules." action={activeSection === 'plans' && canCreate ? <Button variant="contained" onClick={() => setEditing({ ...emptyPlan })}>Create plan</Button> : activeSection === 'coupons' && canManageCoupons ? <Button variant="contained" onClick={() => setEditingCoupon({ code: '', discount: '', discount_type: 'percentage', status: true, subscription_plan_ids: [] })}>Create coupon</Button> : null} />
      <Stack spacing={2}>
        {error && <Alert severity="error">{error}</Alert>}
        {notice && <Alert severity="success">{notice}</Alert>}
        <Card sx={{ px: 1 }}>
          <Tabs value={activeSection} onChange={(_, value) => setActiveSection(value)} variant="scrollable" allowScrollButtonsMobile aria-label="Subscription management sections">
            <Tab value="plans" label="Plans" />
            {canViewSubscribers && <Tab value="subscribers" label="Subscribers" />}
            <Tab value="performance" label="Performance" />
            {canManageCoupons && <Tab value="coupons" label="Coupons" />}
            <Tab value="entitlements" label="Entitlement simulator" />
          </Tabs>
        </Card>
        {activeSection === 'plans' && <>
        <Alert severity="info">Plan changes apply after payment verification. Existing subscriber entitlements remain unchanged.</Alert>
        <Card><CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>Plans</Typography>
          {loading || plans.length > 0 ? (
            <Table size="small"><TableHead><TableRow><TableCell>Name</TableCell><TableCell>Base price</TableCell><TableCell>Regional prices</TableCell><TableCell>Billing</TableCell><TableCell>Level</TableCell><TableCell>Subscribers</TableCell><TableCell>Status</TableCell>{canActOnPlans && <TableCell align="right">Actions</TableCell>}</TableRow></TableHead>
              <TableBody>{loading ? <SubscriptionTableSkeletonRows columns={canActOnPlans ? 8 : 7} /> : plans.map((plan) => <TableRow key={plan.id} hover>
                <TableCell><Typography variant="subtitle2">{plan.name_translations?.en || plan.name}</Typography>{plan.name_translations?.bn && <Typography variant="caption" color="text.secondary" display="block">{plan.name_translations.bn}</Typography>}<Typography variant="caption" color="text.secondary">{plan.identifier}</Typography></TableCell>
                <TableCell>{plan.price}</TableCell><TableCell>{plan.regional_prices?.length || 0} countries</TableCell><TableCell>{plan.duration_value} {plan.duration}{plan.duration_value === 1 ? '' : 's'}</TableCell><TableCell>{plan.level}</TableCell><TableCell>{plan.subscriptions_count ?? 0}</TableCell><TableCell>{plan.status ? 'Active' : 'Archived'}</TableCell>
                {canActOnPlans && <TableCell align="right"><Stack direction="row" justifyContent="flex-end" spacing={1}>{canUpdate && <Button size="small" onClick={() => setEditing({ ...plan, price: String(plan.price ?? ''), level: String(plan.level), duration_value: String(plan.duration_value), trial_days: String(plan.trial_days ?? 0), device_limit: String(plan.entitlements?.['device-limit'] ?? ''), profile_limit: String(plan.entitlements?.['profile-limit'] ?? ''), simultaneous_streams: String(plan.entitlements?.['simultaneous-streams'] ?? ''), max_video_quality: String(plan.entitlements?.['max-video-quality'] ?? '') })}>Edit</Button>}{canArchive && Boolean(plan.status) && <Button size="small" color="warning" disabled={busy} onClick={() => archive(plan)}>Archive</Button>}</Stack></TableCell>}
              </TableRow>)}</TableBody>
            </Table>
          ) : <Typography color="text.secondary">No plans found.</Typography>}
        </CardContent></Card>
        </>}
        {activeSection === 'subscribers' && canViewSubscribers && <Card><CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>Recent subscribers</Typography>
          {loading || subscribers.length > 0 ? <Table size="small"><TableHead><TableRow><TableCell>Account</TableCell><TableCell>Plan</TableCell><TableCell>Status</TableCell><TableCell>Started</TableCell><TableCell>Expires</TableCell></TableRow></TableHead>
            <TableBody>{loading ? <SubscriptionTableSkeletonRows columns={5} rows={3} /> : subscribers.map((subscriber) => <TableRow key={subscriber.id}><TableCell>#{subscriber.user_id}</TableCell><TableCell>{subscriber.name}</TableCell><TableCell>{subscriber.status}</TableCell><TableCell>{subscriber.start_date ? new Date(subscriber.start_date).toLocaleDateString() : '—'}</TableCell><TableCell>{subscriber.end_date ? new Date(subscriber.end_date).toLocaleDateString() : '—'}</TableCell></TableRow>)}</TableBody>
          </Table> : <Typography color="text.secondary">No subscriber records found.</Typography>}
        </CardContent></Card>}
        {activeSection === 'performance' && <Card><CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>Plan comparison and performance</Typography>
          {loading || performance.length > 0 ? <Table size="small"><TableHead><TableRow><TableCell>Plan</TableCell><TableCell>Price</TableCell><TableCell>Entitlement level</TableCell><TableCell>Active subscribers</TableCell><TableCell>Paid transactions</TableCell><TableCell>Gross revenue</TableCell></TableRow></TableHead>
            <TableBody>{loading ? <SubscriptionTableSkeletonRows columns={6} /> : performance.map((plan) => <TableRow key={plan.id}><TableCell>{plan.name}</TableCell><TableCell>{plan.price} / {plan.duration_value} {plan.duration}</TableCell><TableCell>{plan.level}</TableCell><TableCell>{plan.active_subscribers}</TableCell><TableCell>{plan.paid_transactions}</TableCell><TableCell>{Object.entries(plan.revenue_by_currency || {}).map(([currency, amount]) => `${currency}: ${amount}`).join(' · ') || '—'}</TableCell></TableRow>)}</TableBody>
          </Table> : <Typography color="text.secondary">No subscription performance data yet.</Typography>}
          <Typography variant="caption" color="text.secondary" display="block" sx={{ mt: 1 }}>Amounts are grouped by recorded transaction currency. Older transactions without a stored currency are labeled UNRECORDED; no conversion is applied.</Typography>
        </CardContent></Card>}
        {activeSection === 'coupons' && canManageCoupons && <Card><CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>Coupons</Typography>
          {loading || coupons.length > 0 ? <Table size="small"><TableHead><TableRow><TableCell>Code</TableCell><TableCell>Discount</TableCell><TableCell>Plans</TableCell><TableCell>Uses / cap</TableCell><TableCell>Status</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead><TableBody>{loading ? <SubscriptionTableSkeletonRows columns={6} /> : coupons.map((coupon) => <TableRow key={coupon.id}><TableCell>{coupon.code}</TableCell><TableCell>{coupon.discount_type === 'percentage' ? `${coupon.discount}%` : coupon.discount}</TableCell><TableCell>{(coupon.subscription_plans || []).map((plan) => plan.name).join(', ')}</TableCell><TableCell>{coupon.redemptions_count ?? 0} / {coupon.max_redemptions ?? '∞'}<Typography display="block" variant="caption" color="text.secondary">{coupon.pending_reservations_count ?? 0} reserved · {coupon.per_user_limit ?? '∞'} per account</Typography></TableCell><TableCell>{coupon.status ? 'Active' : 'Inactive'}</TableCell><TableCell align="right"><Stack direction="row" justifyContent="flex-end"><Button size="small" onClick={() => setEditingCoupon({ ...coupon, subscription_plan_ids: (coupon.subscription_plans || []).map((plan) => String(plan.id)) })}>Edit</Button>{Boolean(coupon.status) && <Button size="small" color="warning" disabled={busy} onClick={() => archiveCoupon(coupon)}>Deactivate</Button>}</Stack></TableCell></TableRow>)}</TableBody></Table> : <Typography color="text.secondary">No coupons found.</Typography>}
        </CardContent></Card>}
        {activeSection === 'entitlements' && <Card><CardContent sx={{ p: { xs: 2, md: 3 } }}>
          <Stack spacing={0.5} sx={{ mb: 2.5 }}>
            <Typography variant="h6">Entitlement simulator</Typography>
            <Typography variant="body2" color="text.secondary">Check whether a plan can satisfy a member’s access and playback requirements.</Typography>
          </Stack>
          <Stack component="form" onSubmit={runSimulation} spacing={2.5}>
            <Grid container spacing={2}>
              <Grid item xs={12} sm={6} md={4}>
                <TextField select required fullWidth label="Plan to test" value={simulation.plan_id} onChange={(event) => { setSimulation({ ...simulation, plan_id: event.target.value }); setSimulationResult(null); }}>
                  {plans.map((plan) => <MenuItem key={plan.id} value={plan.id}>{plan.name_translations?.en || plan.name}</MenuItem>)}
                </TextField>
              </Grid>
              {[
                { key: 'required_plan_level', label: 'Required plan level', min: 0 },
                { key: 'profile_count', label: 'Profiles needed', min: 1 },
                { key: 'device_count', label: 'Devices needed', min: 1 },
                { key: 'stream_count', label: 'Concurrent streams', min: 1 },
              ].map(({ key, label, min }) => (
                <Grid item xs={6} sm={3} md={2} key={key}>
                  <TextField fullWidth type="number" label={label} value={simulation[key]} onChange={(event) => { setSimulation({ ...simulation, [key]: event.target.value }); setSimulationResult(null); }} inputProps={{ min, required: true }} />
                </Grid>
              ))}
            </Grid>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }} justifyContent="space-between" sx={{ pt: 2, borderTop: 1, borderColor: 'divider' }}>
              <TextField select label="Requested video quality" value={simulation.requested_video_quality} onChange={(event) => { setSimulation({ ...simulation, requested_video_quality: event.target.value }); setSimulationResult(null); }} sx={{ width: { xs: '100%', sm: 260 } }}>
                {[480, 720, 1080, 1440, 2160].map((quality) => <MenuItem key={quality} value={String(quality)}>{quality}p</MenuItem>)}
              </TextField>
              <Button type="submit" variant="contained" startIcon={<Iconify icon="solar:play-bold" />} disabled={simulating || loading || plans.length === 0 || !simulation.plan_id} sx={{ minWidth: 156, minHeight: 44 }}>
                {simulating ? 'Checking…' : 'Run simulation'}
              </Button>
            </Stack>
          </Stack>
          {simulationResult && <Alert sx={{ mt: 2.5 }} severity={simulationResult.eligible ? 'success' : 'warning'}>
            <Stack spacing={1}>
              <Typography variant="subtitle2">{simulationResult.eligible ? 'This plan meets the requested entitlements' : 'This plan does not meet all requirements'}</Typography>
              <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap">
                {Object.entries(simulationResult.checks).map(([key, value]) => <Typography key={key} variant="caption" sx={{ px: 1, py: 0.5, borderRadius: 1, bgcolor: 'background.paper', textTransform: 'capitalize' }}>{key.replaceAll('_', ' ')} · {value ? 'Pass' : 'Fail'}</Typography>)}
              </Stack>
            </Stack>
          </Alert>}
        </CardContent></Card>}
      </Stack>
      <Dialog open={Boolean(editing)} onClose={() => !busy && setEditing(null)} fullWidth maxWidth="md">
        <DialogTitle>{editing?.id ? 'Edit plan' : 'Create plan'}</DialogTitle>
        <form onSubmit={save}><DialogContent><Stack spacing={2} sx={{ pt: 1}}>
          <TextField name="name" label="Plan name (English)" defaultValue={editing?.name_translations?.en ?? editing?.name ?? ''} required inputProps={{ maxLength: 120 }} />
          <TextField name="name_translations.bn" label="Plan name (বাংলা)" defaultValue={editing?.name_translations?.bn ?? ''} inputProps={{ maxLength: 120 }} />
          <TextField name="price" label="Base price" type="number" defaultValue={editing?.price ?? ''} required inputProps={{ min: 0, step: '0.01' }} />
          <Stack spacing={1}>
            <Stack direction="row" alignItems="center" justifyContent="space-between">
              <Typography variant="subtitle2">Regional price overrides</Typography>
              <Button size="small" onClick={() => setEditing({ ...editing, regional_prices: [...(editing.regional_prices || []), { country_code: '', currency_code: pricingOptions.currencies[0]?.code || '', amount: '' }] })}>Add country price</Button>
            </Stack>
            <Typography variant="caption" color="text.secondary">With no overrides, checkout uses the base price and primary currency. Once overrides exist, checkout requires a trusted edge country and an exact country price.</Typography>
            {(editing?.regional_prices || []).map((regionalPrice, index) => <Stack key={`${regionalPrice.country_code}-${index}`} direction={{ xs: 'column', md: 'row' }} spacing={1} alignItems={{ md: 'center' }}>
              <TextField select label="Country" value={regionalPrice.country_code} onChange={(event) => {
                const next = [...editing.regional_prices];
                next[index] = { ...next[index], country_code: event.target.value };
                setEditing({ ...editing, regional_prices: next });
              }} fullWidth>
                {pricingOptions.countries.filter((country) => country.code === regionalPrice.country_code || !editing.regional_prices.some((row, rowIndex) => rowIndex !== index && row.country_code === country.code)).map((country) => <MenuItem key={country.code} value={country.code}>{country.name} ({country.code})</MenuItem>)}
              </TextField>
              <TextField select label="Currency" value={regionalPrice.currency_code} onChange={(event) => {
                const next = [...editing.regional_prices];
                next[index] = { ...next[index], currency_code: event.target.value };
                setEditing({ ...editing, regional_prices: next });
              }} fullWidth>{pricingOptions.currencies.map((currency) => <MenuItem key={currency.code} value={currency.code}>{currency.name} ({currency.code})</MenuItem>)}</TextField>
              <TextField label="Amount" type="number" value={regionalPrice.amount} onChange={(event) => {
                const next = [...editing.regional_prices];
                next[index] = { ...next[index], amount: event.target.value };
                setEditing({ ...editing, regional_prices: next });
              }} inputProps={{ min: 0, step: pricingOptions.currencies.find((currency) => currency.code === regionalPrice.currency_code)?.decimal_places === 0 ? '1' : '0.01' }} fullWidth />
              <Button color="error" onClick={() => setEditing({ ...editing, regional_prices: editing.regional_prices.filter((_, rowIndex) => rowIndex !== index) })}>Remove</Button>
            </Stack>)}
          </Stack>
          <TextField name="level" label="Entitlement level" type="number" defaultValue={editing?.level ?? '1'} required inputProps={{ min: 0 }} />
          <Stack direction="row" spacing={2}><TextField name="duration_value" label="Duration count" type="number" defaultValue={editing?.duration_value ?? '1'} required inputProps={{ min: 1, max: 120 }} /><TextField name="duration" label="Billing period" select defaultValue={editing?.duration ?? 'month'} fullWidth>{['day', 'week', 'month', 'year'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField></Stack>
          {canManageTrials && <TextField name="trial_days" label="Free trial days (first subscription only)" type="number" defaultValue={editing?.trial_days ?? '0'} inputProps={{ min: 0, max: 90 }} />}
          <Typography variant="subtitle2">Entitlement limits</Typography>
          <Stack direction="row" spacing={2}><TextField name="device_limit" label="Registered devices" type="number" defaultValue={editing?.device_limit ?? '1'} inputProps={{ min: 1, max: 100 }} /><TextField name="profile_limit" label="Profiles" type="number" defaultValue={editing?.profile_limit ?? '1'} inputProps={{ min: 1, max: 50 }} /><TextField name="simultaneous_streams" label="Concurrent streams" type="number" defaultValue={editing?.simultaneous_streams ?? '1'} inputProps={{ min: 1, max: 10 }} /></Stack>
          <TextField name="max_video_quality" label="Maximum playback quality" select defaultValue={editing?.max_video_quality ?? ''}><MenuItem value="">Unrestricted</MenuItem>{[480, 720, 1080, 1440, 2160].map((quality) => <MenuItem key={quality} value={String(quality)}>{quality}p</MenuItem>)}</TextField>
          <TextField name="description" label="Description" defaultValue={editing?.description ?? ''} multiline minRows={3} />
          {!editing?.id && <TextField name="status" label="Status" select defaultValue="true"><MenuItem value="true">Active</MenuItem><MenuItem value="false">Draft</MenuItem></TextField>}
        </Stack></DialogContent><DialogActions><Button onClick={() => setEditing(null)} disabled={busy}>Cancel</Button><Button type="submit" variant="contained" disabled={busy}>{busy ? 'Saving…' : 'Save'}</Button></DialogActions></form>
      </Dialog>
      <Dialog open={Boolean(editingCoupon)} onClose={() => !busy && setEditingCoupon(null)} fullWidth maxWidth="sm">
        <DialogTitle>{editingCoupon?.id ? 'Edit coupon' : 'Create coupon'}</DialogTitle>
        <form onSubmit={saveCoupon}><DialogContent><Stack spacing={2} sx={{ pt: 1 }}>
          <TextField name="code" label="Coupon code" defaultValue={editingCoupon?.code ?? ''} required inputProps={{ maxLength: 64 }} />
          <Stack direction="row" spacing={2}><TextField name="discount_type" label="Discount type" select defaultValue={editingCoupon?.discount_type ?? 'percentage'} fullWidth><MenuItem value="percentage">Percentage</MenuItem><MenuItem value="fixed">Fixed amount</MenuItem></TextField><TextField name="discount" label="Discount" type="number" defaultValue={editingCoupon?.discount ?? ''} required inputProps={{ min: 0, step: '0.01' }} /></Stack>
          <Stack direction="row" spacing={2}><TextField name="max_redemptions" label="Total uses (blank is unlimited)" type="number" defaultValue={editingCoupon?.max_redemptions ?? ''} inputProps={{ min: 1, max: 10000000 }} fullWidth /><TextField name="per_user_limit" label="Uses per account (blank is unlimited)" type="number" defaultValue={editingCoupon?.per_user_limit ?? ''} inputProps={{ min: 1, max: 1000 }} fullWidth /></Stack>
          <TextField name="subscription_plan_ids" label="Eligible plans" select SelectProps={{ multiple: true }} defaultValue={editingCoupon?.subscription_plan_ids ?? []} required>{plans.filter((plan) => plan.status).map((plan) => <MenuItem key={plan.id} value={String(plan.id)}>{plan.name_translations?.en || plan.name}{plan.name_translations?.bn ? ` (${plan.name_translations.bn})` : ''}</MenuItem>)}</TextField>
          <Stack direction="row" spacing={2}><TextField name="start_date" type="date" label="Starts" defaultValue={editingCoupon?.start_date?.slice?.(0, 10) ?? ''} InputLabelProps={{ shrink: true }} fullWidth /><TextField name="expire_date" type="date" label="Expires" defaultValue={editingCoupon?.expire_date?.slice?.(0, 10) ?? ''} InputLabelProps={{ shrink: true }} fullWidth /></Stack>
          <TextField name="description" label="Description" defaultValue={editingCoupon?.description ?? ''} multiline minRows={2} />
          <TextField name="status" label="Status" select defaultValue={editingCoupon?.status ? 'true' : 'false'}><MenuItem value="true">Active</MenuItem><MenuItem value="false">Inactive</MenuItem></TextField>
        </Stack></DialogContent><DialogActions><Button onClick={() => setEditingCoupon(null)} disabled={busy}>Cancel</Button><Button type="submit" variant="contained" disabled={busy}>{busy ? 'Saving…' : 'Save'}</Button></DialogActions></form>
      </Dialog>
    </DashboardContent>
  );
}

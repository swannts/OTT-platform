'use client';

import { useCallback, useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
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
import { hasPermission } from 'src/features/rbac/permissions';
import { RbacPageHeader } from 'src/features/rbac/components/RbacPageHeader';
import { marketingApi } from './marketing.api';

const message = (error) => error?.response?.data?.message || 'The marketing request could not be completed.';

function Metric({ label, value }) {
  return <Card><CardContent><Typography variant="body2" color="text.secondary">{label}</Typography><Typography variant="h4">{value ?? '—'}</Typography></CardContent></Card>;
}

export function MarketingOperationsPage() {
  const { user } = useAuthContext();
  const [tab, setTab] = useState(0);
  const [dashboard, setDashboard] = useState(null);
  const [campaigns, setCampaigns] = useState([]);
  const [segments, setSegments] = useState(null);
  const [banners, setBanners] = useState([]);
  const [selected, setSelected] = useState(null);
  const [analytics, setAnalytics] = useState(null);
  const [bannerEdit, setBannerEdit] = useState(null);
  const [channel, setChannel] = useState('email');
  const [audienceSegment, setAudienceSegment] = useState('email_consented');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const canManage = hasPermission(user, 'marketing.campaigns.manage');
  const canApprove = hasPermission(user, 'marketing.campaigns.approve');
  const canManageBanners = hasPermission(user, 'marketing.banners.manage');

  const loadMarketing = useCallback(async () => {
    const requests = [marketingApi.dashboard(), marketingApi.campaigns(), marketingApi.segments()];
    if (canManageBanners) requests.push(marketingApi.banners());
    const [nextDashboard, nextCampaigns, nextSegments, nextBanners] = await Promise.all(requests);
    return { nextDashboard, nextCampaigns, nextSegments, nextBanners };
  }, [canManageBanners]);

  const applyMarketing = useCallback(({ nextDashboard, nextCampaigns, nextSegments, nextBanners }) => {
    setDashboard(nextDashboard);
    setCampaigns(nextCampaigns?.data || []);
    setSegments(nextSegments);
    setBanners(nextBanners?.data || []);
  }, []);

  const refresh = useCallback(async () => {
    setError('');
    try {
      applyMarketing(await loadMarketing());
    } catch (cause) {
      setError(message(cause));
    }
  }, [applyMarketing, loadMarketing]);

  useEffect(() => {
    if (!canManage) return undefined;

    let active = true;
    loadMarketing()
      .then((result) => {
        if (active) {
          applyMarketing(result);
          setError('');
        }
      })
      .catch((cause) => {
        if (active) setError(message(cause));
      });
    return () => {
      active = false;
    };
  }, [applyMarketing, canManage, loadMarketing]);

  const perform = async (action, success) => {
    setBusy(true); setError(''); setNotice('');
    try { await action(); setNotice(success); await refresh(); return true; }
    catch (cause) { setError(message(cause)); return false; }
    finally { setBusy(false); }
  };

  const submitCampaign = async (event) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    const kind = String(form.get('kind'));
    const channel = String(form.get('channel') || '');
    const body = String(form.get('body') || '').trim();
    const data = {
      name: String(form.get('name') || '').trim(), kind, channel: channel || null,
      audience_segment: String(form.get('audience_segment')), subject: String(form.get('subject') || '').trim() || null,
      body, call_to_action: String(form.get('call_to_action') || '').trim() || null,
      destination_url: String(form.get('destination_url') || '').trim() || null,
      coupon_id: form.get('coupon_id') ? Number(form.get('coupon_id')) : null,
      featured_content: String(form.get('featured_content') || '').split(',').map((id) => Number(id.trim())).filter(Number.isInteger).filter((id) => id > 0),
      landing_page: { headline: String(form.get('headline') || '').trim(), description: String(form.get('description') || '').trim(), hero_image: String(form.get('hero_image') || '').trim() || null },
      variant_b: String(form.get('variant_b_body') || '').trim() ? { subject: String(form.get('variant_b_subject') || '').trim(), body: String(form.get('variant_b_body')).trim(), destination_url: String(form.get('variant_b_url') || '').trim() || null } : null,
      variant_b_percent: Number(form.get('variant_b_percent') || 0),
    };
    setBusy(true); setError(''); setNotice('');
    try {
      const campaign = selected ? await marketingApi.updateCampaign(selected.id, data) : await marketingApi.createCampaign(data);
      setSelected(null); setChannel('email'); setAudienceSegment('email_consented'); formElement.reset(); setNotice(selected ? 'Draft updated.' : `Draft created (#${campaign.id}).`); await refresh();
    } catch (cause) { setError(message(cause)); }
    finally { setBusy(false); }
  };

  const submitBanner = async (event) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    const data = Object.fromEntries(new FormData(formElement).entries());
    data.status = data.status === 'true';
    data.starts_at = data.starts_at || null; data.ends_at = data.ends_at || null;
    const saved = await perform(() => bannerEdit?.id ? marketingApi.updateBanner(bannerEdit.id, data) : marketingApi.createBanner(data), 'Banner saved.');
    if (saved) { setBannerEdit(null); formElement.reset(); }
  };

  const scheduleCampaign = async (campaign) => {
    const scheduleValue = window.prompt('Schedule time (ISO format, e.g. 2026-12-01T09:00:00Z)');
    if (!scheduleValue) return;
    const expiryValue = window.prompt('Optional expiry time (ISO format), or leave blank');
    await perform(() => marketingApi.schedule(campaign.id, { scheduled_at: scheduleValue, expires_at: expiryValue || null }), 'Campaign scheduled.');
  };

  const rejectCampaign = async (campaign) => {
    const review_note = window.prompt('Why should this campaign be revised?');
    if (!review_note) return;
    await perform(() => marketingApi.reject(campaign.id, { review_note }), 'Campaign returned for revision.');
  };

  if (!canManage) return <DashboardContent><Alert severity="error">You do not have permission to access marketing operations.</Alert></DashboardContent>;

  return (
    <DashboardContent>
      <RbacPageHeader title="Marketing operations" description="Manage homepage banners, approved campaigns, consent-based notifications, and campaign performance." />
      <Stack spacing={2}>
        {error && <Alert severity="error">{error}</Alert>}{notice && <Alert severity="success">{notice}</Alert>}
        <Grid container spacing={2}>
          <Grid item xs={6} md={2}><Metric label="Campaigns" value={dashboard?.campaigns} /></Grid>
          <Grid item xs={6} md={2}><Metric label="Scheduled" value={dashboard?.scheduled} /></Grid>
          <Grid item xs={6} md={2}><Metric label="Published" value={dashboard?.active} /></Grid>
          <Grid item xs={6} md={2}><Metric label="Home banners" value={dashboard?.banners} /></Grid>
          <Grid item xs={6} md={2}><Metric label="Email consent" value={dashboard?.email_consents} /></Grid>
          <Grid item xs={6} md={2}><Metric label="Push consent" value={dashboard?.push_consents} /></Grid>
        </Grid>
        <Card>
          <Tabs
            value={tab}
            onChange={(_, value) => setTab(value)}
            variant="standard"
            sx={{
              '& .MuiTabs-scroller': { overflowX: 'auto !important' },
              '& .MuiTabs-flexContainer': { width: 'max-content' },
              '& .MuiTab-root': { flexShrink: 0 },
            }}
          >
            <Tab label="Campaign calendar" />
            <Tab label="Campaign builder" />
            <Tab label="Audience segments" />
            {canManageBanners && <Tab label="Homepage banners" />}
          </Tabs>
        </Card>

        {tab === 0 && <Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>Campaign calendar and approvals</Typography>
          {campaigns.length === 0 ? <Typography color="text.secondary">No campaigns have been created.</Typography> : <Table size="small"><TableHead><TableRow><TableCell>Name</TableCell><TableCell>Type / channel</TableCell><TableCell>Segment</TableCell><TableCell>Status</TableCell><TableCell>Schedule</TableCell><TableCell>Delivery</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead><TableBody>{campaigns.map((campaign) => <TableRow key={campaign.id}><TableCell>{campaign.name}</TableCell><TableCell>{campaign.kind}{campaign.channel ? ` · ${campaign.channel}` : ''}</TableCell><TableCell>{campaign.audience_segment}</TableCell><TableCell>{campaign.status}</TableCell><TableCell>{campaign.scheduled_at ? new Date(campaign.scheduled_at).toLocaleString() : '—'}{campaign.expires_at ? ` – ${new Date(campaign.expires_at).toLocaleString()}` : ''}</TableCell><TableCell>{campaign.sent_count ?? 0} / {campaign.recipients_count ?? 0} sent · {campaign.failed_count ?? 0} failed</TableCell><TableCell align="right"><Stack direction="row" justifyContent="flex-end">{['draft', 'rejected'].includes(campaign.status) && <Button size="small" disabled={busy} onClick={() => { setSelected(campaign); setChannel(campaign.channel || 'email'); setAudienceSegment(campaign.audience_segment); setTab(1); }}>Edit</Button>}{campaign.status === 'draft' && <Button size="small" disabled={busy} onClick={() => perform(() => marketingApi.submit(campaign.id), 'Submitted for independent approval.')}>Submit</Button>}{canApprove && campaign.status === 'pending_approval' && <><Button size="small" color="error" disabled={busy} onClick={() => rejectCampaign(campaign)}>Reject</Button><Button size="small" disabled={busy} onClick={() => perform(() => marketingApi.approve(campaign.id), 'Campaign approved.')}>Approve</Button></>}{campaign.status === 'approved' && <Button size="small" disabled={busy} onClick={() => scheduleCampaign(campaign)}>Schedule</Button>}{['scheduled', 'published'].includes(campaign.status) && <Button size="small" disabled={busy} onClick={() => perform(() => marketingApi.pause(campaign.id), 'Campaign deactivated.')}>Deactivate</Button>}{campaign.published_at && <Button size="small" onClick={async () => { try { setAnalytics(await marketingApi.analytics(campaign.id)); } catch (cause) { setError(message(cause)); } }}>Analytics</Button>}</Stack></TableCell></TableRow>)}</TableBody></Table>}
          {analytics && <Stack sx={{ mt: 2 }} spacing={1}><Typography variant="subtitle1">Performance — {analytics.campaign.name}</Typography>{analytics.performance.map((row) => <Typography key={`${row.channel}-${row.variant}-${row.status}`} variant="body2">{row.channel} · variant {row.variant} · {row.status}: {row.count}, opens {row.opens}, clicks {row.clicks}</Typography>)}</Stack>}
        </CardContent></Card>}

        {tab === 1 && <Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>{selected ? `Edit draft: ${selected.name}` : 'Campaign builder and notification composer'}</Typography>
          <form onSubmit={submitCampaign}><Stack spacing={2}>
            <Grid container spacing={2}><Grid item xs={12} md={6}><TextField name="name" label="Campaign name" fullWidth required defaultValue={selected?.name || ''} /></Grid><Grid item xs={12} md={3}><TextField name="kind" select label="Campaign type" fullWidth defaultValue={selected?.kind || 'notification'}>{['campaign', 'promotion', 'landing_page', 'featured_collection', 'notification'].map((value) => <MenuItem key={value} value={value}>{value.replaceAll('_', ' ')}</MenuItem>)}</TextField></Grid><Grid item xs={12} md={3}><TextField name="channel" select label="Channel" fullWidth value={channel} onChange={(event) => { const nextChannel = event.target.value; setChannel(nextChannel); setAudienceSegment((current) => current === 'email_consented' || current === 'push_consented' ? `${nextChannel}_consented` : current); }}>{['email', 'push'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField></Grid></Grid>
            <Grid container spacing={2}><Grid item xs={12} md={4}><TextField name="audience_segment" select label="Consent-based audience" fullWidth value={audienceSegment} onChange={(event) => setAudienceSegment(event.target.value)}>{['all_consented', 'subscribers', 'registered', 'email_consented', 'push_consented'].map((value) => <MenuItem key={value} value={value}>{value.replaceAll('_', ' ')}</MenuItem>)}</TextField></Grid><Grid item xs={12} md={8}><TextField name="subject" label="Email / push subject" fullWidth defaultValue={selected?.subject || ''} /></Grid></Grid>
            <TextField name="body" label="Message / campaign description" required multiline minRows={4} defaultValue={selected?.body || ''} />
            <Grid container spacing={2}><Grid item xs={12} md={4}><TextField name="call_to_action" label="Call to action" fullWidth defaultValue={selected?.call_to_action || ''} /></Grid><Grid item xs={12} md={8}><TextField name="destination_url" label="Destination URL" fullWidth defaultValue={selected?.destination_url || ''} /></Grid></Grid>
            <Grid container spacing={2}><Grid item xs={12} md={3}><TextField name="coupon_id" label="Linked coupon ID (promotion)" type="number" fullWidth defaultValue={selected?.coupon_id || ''} /></Grid><Grid item xs={12} md={9}><TextField name="featured_content" label="Featured content IDs (comma separated)" fullWidth defaultValue={selected?.featured_content?.join(', ') || ''} /></Grid></Grid>
            <Typography variant="subtitle2">Promotional landing page</Typography><Grid container spacing={2}><Grid item xs={12} md={4}><TextField name="headline" label="Headline" fullWidth defaultValue={selected?.landing_page?.headline || ''} /></Grid><Grid item xs={12} md={4}><TextField name="description" label="Description" fullWidth defaultValue={selected?.landing_page?.description || ''} /></Grid><Grid item xs={12} md={4}><TextField name="hero_image" label="Hero image URL" fullWidth defaultValue={selected?.landing_page?.hero_image || ''} /></Grid></Grid>
            <Typography variant="subtitle2">A/B variant (optional)</Typography><Grid container spacing={2}><Grid item xs={12} md={3}><TextField name="variant_b_percent" label="Variant B traffic % (max 50)" type="number" inputProps={{ min: 0, max: 50 }} fullWidth defaultValue={selected?.variant_b_percent || 0} /></Grid><Grid item xs={12} md={3}><TextField name="variant_b_subject" label="B subject" fullWidth defaultValue={selected?.variant_b?.subject || ''} /></Grid><Grid item xs={12} md={3}><TextField name="variant_b_body" label="B body" fullWidth defaultValue={selected?.variant_b?.body || ''} /></Grid><Grid item xs={12} md={3}><TextField name="variant_b_url" label="B destination URL" fullWidth defaultValue={selected?.variant_b?.destination_url || ''} /></Grid></Grid>
            <Stack direction="row" spacing={1}><Button type="submit" variant="contained" disabled={busy}>{busy ? 'Saving…' : selected ? 'Update draft' : 'Save draft'}</Button>{selected && <Button onClick={() => { setSelected(null); setChannel('email'); setAudienceSegment('email_consented'); }}>Cancel edit</Button>}</Stack>
          </Stack></form>
        </CardContent></Card>}

        {tab === 2 && <Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>Audience segments</Typography><Alert severity="info" sx={{ mb: 2 }}>Only aggregate counts are shown. Campaign dispatch selects active users who explicitly consented to the selected channel; verified email is required for email delivery.</Alert><Table size="small"><TableHead><TableRow><TableCell>Segment</TableCell><TableCell>Email eligible</TableCell><TableCell>Push eligible</TableCell></TableRow></TableHead><TableBody>{['all_consented', 'subscribers', 'registered'].map((key) => <TableRow key={key}><TableCell>{key.replaceAll('_', ' ')}</TableCell><TableCell>{segments?.[key]?.email ?? segments?.[key] ?? 0}</TableCell><TableCell>{segments?.[key]?.push ?? 0}</TableCell></TableRow>)}</TableBody></Table></CardContent></Card>}

        {tab === 3 && canManageBanners && <Grid container spacing={2}><Grid item xs={12} md={7}><Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>Homepage banner editor</Typography>{banners.length === 0 ? <Typography color="text.secondary">No banners are configured.</Typography> : <Table size="small"><TableHead><TableRow><TableCell>Title</TableCell><TableCell>Placement</TableCell><TableCell>Content</TableCell><TableCell>Status</TableCell><TableCell>Schedule</TableCell><TableCell /></TableRow></TableHead><TableBody>{banners.map((banner) => <TableRow key={banner.id}><TableCell>{banner.title}</TableCell><TableCell>{banner.banner_for}</TableCell><TableCell>{banner.type_name || banner.type || '—'}</TableCell><TableCell>{banner.status ? 'active' : 'inactive'}</TableCell><TableCell>{banner.starts_at ? new Date(banner.starts_at).toLocaleString() : 'Now'}{banner.ends_at ? ` – ${new Date(banner.ends_at).toLocaleString()}` : ''}</TableCell><TableCell><Button size="small" onClick={() => setBannerEdit(banner)}>Edit</Button></TableCell></TableRow>)}</TableBody></Table>}</CardContent></Card></Grid><Grid item xs={12} md={5}><Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>{bannerEdit ? 'Edit banner' : 'Create banner'}</Typography><form onSubmit={submitBanner}><Stack spacing={2}><TextField name="title" label="Title" required fullWidth defaultValue={bannerEdit?.title || ''} /><TextField name="banner_for" select label="Placement" defaultValue={bannerEdit?.banner_for || 'home'}>{['home', 'movie', 'tv_show', 'livetv', 'video', 'promotional'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField><TextField name="type" select label="Content type" defaultValue={bannerEdit?.type || ''}><MenuItem value="">Promotional / custom</MenuItem>{['movie', 'tvshow', 'livetv', 'video'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField><TextField name="type_id" label="Content ID" defaultValue={bannerEdit?.type_id || ''} /><TextField name="type_name" label="Content title" defaultValue={bannerEdit?.type_name || ''} /><TextField name="poster_url" label="Poster image URL" defaultValue={bannerEdit?.poster_url || ''} /><TextField name="file_url" label="Background image URL" defaultValue={bannerEdit?.file_url || ''} /><TextField name="click_url" label="Click URL" defaultValue={bannerEdit?.click_url || ''} /><TextField name="description" label="Description" multiline minRows={2} defaultValue={bannerEdit?.description || ''} /><TextField name="starts_at" label="Starts at" type="datetime-local" InputLabelProps={{ shrink: true }} defaultValue={bannerEdit?.starts_at?.slice(0, 16) || ''} /><TextField name="ends_at" label="Ends at" type="datetime-local" InputLabelProps={{ shrink: true }} defaultValue={bannerEdit?.ends_at?.slice(0, 16) || ''} /><TextField name="status" select label="Status" defaultValue={String(bannerEdit?.status ?? true)}><MenuItem value="true">Active</MenuItem><MenuItem value="false">Inactive</MenuItem></TextField><Stack direction="row" spacing={1}><Button type="submit" variant="contained" disabled={busy}>Save banner</Button>{bannerEdit && <Button onClick={() => setBannerEdit(null)}>Cancel</Button>}</Stack></Stack></form></CardContent></Card></Grid></Grid>}
      </Stack>
    </DashboardContent>
  );
}

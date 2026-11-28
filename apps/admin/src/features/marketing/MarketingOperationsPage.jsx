'use client';

import { useCallback, useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Accordion from '@mui/material/Accordion';
import AccordionDetails from '@mui/material/AccordionDetails';
import AccordionSummary from '@mui/material/AccordionSummary';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
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
import TableContainer from '@mui/material/TableContainer';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Switch from '@mui/material/Switch';
import FormControlLabel from '@mui/material/FormControlLabel';
import Slider from '@mui/material/Slider';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { hasPermission } from 'src/features/rbac/permissions';
import { RbacPageHeader } from 'src/features/rbac/components/RbacPageHeader';
import { marketingApi } from './marketing.api';
import { toast } from 'src/components/snackbar';

const message = (error) => error?.response?.data?.message || 'The marketing request could not be completed.';
const isBannerEnabled = (banner) => banner?.status === true || banner?.status === 1 || banner?.status === '1';
const DEFAULT_HOMEPAGE = {
  sections: [
    ['latest-movies', 'Trending Now'], ['live-now', 'Live Now'], ['top-10', 'Top 10 Today'],
    ['popular-tvshows', 'Popular TV Shows & Series'], ['popular-movies', 'Popular Movies'], ['pay-per-view', 'Pay-Per-View Exclusives'],
  ].map(([key, title]) => ({ key, title, title_bn: '', enabled: true, content_ids: null })),
  premium_card: { enabled: true, title: 'Go Premium', description: 'Unlock exclusive content, ad-free experience and more.', image_url: '', cta: 'Upgrade Now', url: '/pricing' },
  latest_movies: [],
};

const bannerCropPresets = {
  poster: { label: 'Poster artwork · 2:3', ratio: 2 / 3, width: 1000 },
  background: { label: 'Homepage hero · 16:9', ratio: 16 / 9, width: 1920 },
};

function BannerArtworkField({ kind, value, onChange, onUpload, onError, disabled }) {
  const [source, setSource] = useState('');
  const [preview, setPreview] = useState('');
  const [cropOpen, setCropOpen] = useState(false);
  const [zoom, setZoom] = useState(1);
  const [focus, setFocus] = useState([50, 50]);
  const [working, setWorking] = useState(false);
  const preset = bannerCropPresets[kind];

  useEffect(() => () => {
    if (source.startsWith('blob:')) URL.revokeObjectURL(source);
  }, [source]);

  const selectFile = (file) => {
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      onError(new Error('Choose a JPG, PNG, or WebP image smaller than 10 MB.'));
      return;
    }
    setSource(URL.createObjectURL(file));
    setZoom(1);
    setFocus([50, 50]);
    setCropOpen(true);
  };

  const applyCrop = async () => {
    setWorking(true);
    try {
      const image = new Image();
      image.crossOrigin = 'anonymous';
      await new Promise((resolve, reject) => {
        image.onload = resolve;
        image.onerror = () => reject(new Error('This image cannot be cropped. Upload the image if its host blocks browser access.'));
        image.src = source || value;
      });
      const sourceRatio = image.naturalWidth / image.naturalHeight;
      const cropWidthBase = sourceRatio > preset.ratio ? image.naturalHeight * preset.ratio : image.naturalWidth;
      const cropHeightBase = sourceRatio > preset.ratio ? image.naturalHeight : image.naturalWidth / preset.ratio;
      const cropWidth = cropWidthBase / zoom;
      const cropHeight = cropHeightBase / zoom;
      const x = (image.naturalWidth - cropWidth) * focus[0] / 100;
      const y = (image.naturalHeight - cropHeight) * focus[1] / 100;
      const canvas = document.createElement('canvas');
      canvas.width = preset.width;
      canvas.height = Math.round(preset.width / preset.ratio);
      const context = canvas.getContext('2d');
      if (!context) throw new Error('Image cropping is not supported by this browser.');
      context.drawImage(image, x, y, cropWidth, cropHeight, 0, 0, canvas.width, canvas.height);
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
      if (!blob) throw new Error('The cropped image could not be created.');
      const result = await onUpload(new File([blob], `banner-${kind}.jpg`, { type: 'image/jpeg' }), kind);
      if (result) {
        onChange(result);
        setPreview(result);
        setCropOpen(false);
        setSource('');
      }
    } catch (error) {
      onError(error);
    } finally {
      setWorking(false);
    }
  };

  const image = source || preview || value;
  const fieldName = kind === 'poster' ? 'poster_url' : 'file_url';
  const isPoster = kind === 'poster';
  return <>
    <Stack spacing={1.25}>
      <input type="hidden" name={fieldName} value={value || ''} />
      <TextField size="small" label={isPoster ? 'Poster image URL' : 'Hero image URL'} fullWidth value={value || ''} onChange={(event) => { onChange(event.target.value); setPreview(''); }} />
      <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
        <Button component="label" size="small" variant="outlined" disabled={disabled}>{isPoster ? 'Upload poster' : 'Upload hero image'}<input hidden type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => { selectFile(event.currentTarget.files?.[0]); event.currentTarget.value = ''; }} /></Button>
        <Button size="small" disabled={!value || disabled} onClick={() => { setSource(''); setZoom(1); setFocus([50, 50]); setCropOpen(true); }}>Crop existing image</Button>
        <Typography variant="caption" color="text.secondary">{preset.label} · crop to fit · up to 10 MB</Typography>
      </Stack>
      {image && <Box sx={{ aspectRatio: `${preset.ratio}`, alignSelf: isPoster ? 'flex-start' : 'stretch', width: isPoster ? 112 : '100%', height: isPoster ? 168 : 'auto', maxWidth: '100%', overflow: 'hidden', borderRadius: 1.5, bgcolor: 'grey.900', border: '1px solid', borderColor: 'divider' }}><Box component="img" src={image} alt={`${isPoster ? 'Poster' : 'Homepage hero'} artwork preview`} sx={{ display: 'block', width: '100%', height: '100%', objectFit: 'cover' }} /></Box>}
    </Stack>
    <Dialog open={cropOpen} onClose={() => !working && setCropOpen(false)} fullWidth maxWidth="sm">
      <DialogTitle>Crop {kind === 'poster' ? 'website card artwork' : 'hero background'}</DialogTitle>
      <DialogContent><Stack spacing={2} sx={{ pt: 1 }}>
        <Typography variant="body2" color="text.secondary">Crop this image to {preset.label} ({preset.width} × {Math.round(preset.width / preset.ratio)} pixels).</Typography>
        <Box sx={{ width: '100%', aspectRatio: `${preset.ratio}`, maxHeight: 300, overflow: 'hidden', position: 'relative', bgcolor: 'grey.900', borderRadius: 1.5 }}>
          <Box component="img" src={source || value} alt="Crop preview" sx={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', objectPosition: `${focus[0]}% ${focus[1]}%`, transform: `scale(${zoom})`, transformOrigin: `${focus[0]}% ${focus[1]}%` }} />
        </Box>
        <Box><Typography variant="caption">Zoom · {zoom.toFixed(1)}×</Typography><Slider size="small" min={1} max={3} step={0.1} value={zoom} onChange={(_, next) => setZoom(next)} aria-label="Crop zoom" /></Box>
        <Box><Typography variant="caption">Horizontal position · {focus[0]}%</Typography><Slider size="small" value={focus[0]} onChange={(_, next) => setFocus(([, y]) => [next, y])} aria-label="Crop horizontal position" /></Box>
        <Box><Typography variant="caption">Vertical position · {focus[1]}%</Typography><Slider size="small" value={focus[1]} onChange={(_, next) => setFocus(([x]) => [x, next])} aria-label="Crop vertical position" /></Box>
      </Stack></DialogContent>
      <DialogActions><Button onClick={() => setCropOpen(false)} disabled={working}>Cancel</Button><Button variant="contained" onClick={applyCrop} disabled={working || disabled}>{working ? 'Cropping and uploading…' : 'Apply crop and upload'}</Button></DialogActions>
    </Dialog>
  </>;
}

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
  const [homepage, setHomepage] = useState(DEFAULT_HOMEPAGE);
  const [trendingSelection, setTrendingSelection] = useState([]);
  const [catalogSearch, setCatalogSearch] = useState('');
  const [catalogItems, setCatalogItems] = useState([]);
  const [selected, setSelected] = useState(null);
  const [analytics, setAnalytics] = useState(null);
  const [bannerEdit, setBannerEdit] = useState(null);
  const [bannerDelete, setBannerDelete] = useState(null);
  const [bannerDialogOpen, setBannerDialogOpen] = useState(false);
  const [bannerSearch, setBannerSearch] = useState('');
  const [bannerPlacement, setBannerPlacement] = useState('all');
  const [bannerStatus, setBannerStatus] = useState('all');
  const [posterPreview, setPosterPreview] = useState('');
  const [backgroundPreview, setBackgroundPreview] = useState('');
  const [uploadingArtwork, setUploadingArtwork] = useState('');
  const [channel, setChannel] = useState('email');
  const [audienceSegment, setAudienceSegment] = useState('email_consented');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const canManage = hasPermission(user, 'marketing.campaigns.manage');
  const canApprove = hasPermission(user, 'marketing.campaigns.approve');
  const canManageBanners = hasPermission(user, 'marketing.banners.manage');
  const filteredBanners = banners.filter((banner) => {
    const query = bannerSearch.trim().toLowerCase();
    return (!query || `${banner.title || ''} ${banner.type_name || ''} ${banner.type_name_bn || ''}`.toLowerCase().includes(query))
      && (bannerPlacement === 'all' || banner.banner_for === bannerPlacement)
      && (bannerStatus === 'all' || isBannerEnabled(banner) === (bannerStatus === 'active'));
  });

  const loadMarketing = useCallback(async () => {
    const requests = [marketingApi.dashboard(), marketingApi.campaigns(), marketingApi.segments()];
    if (canManageBanners) requests.push(marketingApi.banners(), marketingApi.homepage());
    const [nextDashboard, nextCampaigns, nextSegments, nextBanners, nextHomepage] = await Promise.all(requests);
    return { nextDashboard, nextCampaigns, nextSegments, nextBanners, nextHomepage };
  }, [canManageBanners]);

  const applyMarketing = useCallback(({ nextDashboard, nextCampaigns, nextSegments, nextBanners, nextHomepage }) => {
    setDashboard(nextDashboard);
    setCampaigns(Array.isArray(nextCampaigns) ? nextCampaigns : nextCampaigns?.data || []);
    setSegments(nextSegments);
    setBanners(Array.isArray(nextBanners) ? nextBanners : nextBanners?.data || []);
    if (nextHomepage) {
      setHomepage(nextHomepage);
      setTrendingSelection(nextHomepage.latest_movies || []);
    }
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

  useEffect(() => {
    if (!canManageBanners || tab !== 4) return undefined;
    let active = true;
    const timer = window.setTimeout(() => {
      marketingApi.homepageCatalog(catalogSearch)
        .then((result) => { if (active) setCatalogItems(result?.data || []); })
        .catch((cause) => { if (active) setError(message(cause)); });
    }, 250);
    return () => { active = false; window.clearTimeout(timer); };
  }, [canManageBanners, catalogSearch, tab]);

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
    for (const field of ['title', 'type_id', 'type_name', 'type_name_bn', 'poster_url', 'file_url', 'click_url', 'description', 'description_bn']) {
      data[field] = String(data[field] || '').trim() || null;
    }
    data.type = data.type || null;
    data.starts_at = data.starts_at || null;
    data.ends_at = data.ends_at || null;
    if (data.starts_at && data.ends_at && new Date(data.ends_at) <= new Date(data.starts_at)) {
      const validationMessage = 'The end time must be later than the start time.';
      setError(validationMessage);
      toast.error(validationMessage);
      return;
    }
    const isEditing = Boolean(bannerEdit?.id);
    setBusy(true);
    setError('');
    setNotice('');
    try {
      if (isEditing) await marketingApi.updateBanner(bannerEdit.id, data);
      else await marketingApi.createBanner(data);

      setBannerEdit(null);
      setBannerDialogOpen(false);
      setPosterPreview('');
      setBackgroundPreview('');
      toast.success(isEditing ? 'Banner updated successfully.' : 'Banner created successfully.');
      void refresh();
    } catch (cause) {
      const saveError = message(cause);
      setError(saveError);
      toast.error(saveError);
    } finally {
      setBusy(false);
    }
  };

  const startNewBanner = () => {
    setBannerEdit(null);
    setPosterPreview('');
    setBackgroundPreview('');
    setError('');
    setBannerDialogOpen(true);
  };

  const closeBannerDialog = () => {
    if (busy || uploadingArtwork) return;
    setBannerDialogOpen(false);
    setBannerEdit(null);
    setPosterPreview('');
    setBackgroundPreview('');
    setError('');
  };

  const uploadBannerImage = async (file, target) => {
    if (!file) return null;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      setError('Choose a JPG, PNG, or WebP image smaller than 10 MB.');
      return null;
    }

    setUploadingArtwork(target);
    setError('');
    try {
      const uploaded = await marketingApi.uploadBannerArtwork(file);
      setNotice('Cropped artwork uploaded. Save the banner to publish this image.');
      return uploaded.url;
    } catch (cause) {
      setError(message(cause));
      return null;
    } finally {
      setUploadingArtwork('');
    }
  };

  const editBanner = (banner) => {
    setBannerEdit(banner);
    setBannerDialogOpen(true);
    setPosterPreview(banner.poster_url || '');
    setBackgroundPreview(banner.file_url || '');
    setError('');
  };

  const confirmBannerDelete = async () => {
    if (!bannerDelete) return;
    const banner = bannerDelete;
    const saved = await perform(() => marketingApi.deleteBanner(banner.id), 'Banner deleted.');
    if (saved) {
      if (bannerEdit?.id === banner.id) closeBannerDialog();
      setBannerDelete(null);
    }
  };

  const bannerForm = (
    <form key={bannerEdit?.id ?? 'new-banner'} onSubmit={submitBanner}>
      <Stack spacing={1.5}>
        <Box sx={{ p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 2, bgcolor: 'background.neutral' }}>
          <Typography variant="subtitle2" sx={{ mb: 0.5 }}>Banner details</Typography>
          <Typography variant="caption" color="text.secondary">The internal name is only used to find this banner in the admin.</Typography>
          <TextField name="title" label="Internal banner name" required fullWidth defaultValue={bannerEdit?.title || ''} inputProps={{ maxLength: 120 }} sx={{ mt: 1.5 }} />
        </Box>
        <Grid container spacing={1.5}>
          <Grid item xs={12} sm={6}><TextField name="banner_for" select label="Placement" fullWidth defaultValue={bannerEdit?.banner_for || 'home'}>{['home', 'movie', 'tv_show', 'livetv', 'video', 'promotional'].map((value) => <MenuItem key={value} value={value}>{value.replace('_', ' ')}</MenuItem>)}</TextField></Grid>
          <Grid item xs={12} sm={6}><TextField name="type" select label="Content type" fullWidth defaultValue={bannerEdit?.type || ''}><MenuItem value="">Promotional / custom</MenuItem>{['movie', 'tvshow', 'livetv', 'video'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField></Grid>
        </Grid>
        <Box sx={{ p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 2 }}>
          <Typography variant="subtitle2" sx={{ mb: 0.5 }}>Website content</Typography>
          <Typography variant="caption" color="text.secondary">Link a catalog item and set the title visitors see. Leave the ID empty for a promotional banner.</Typography>
          <Grid container spacing={1.5} sx={{ mt: 0.25 }}>
          <Grid item xs={12} sm={5}><TextField name="type_id" label="Linked content ID" fullWidth defaultValue={bannerEdit?.type_id || ''} helperText="Optional catalog record ID" /></Grid>
          <Grid item xs={12} sm={7}><TextField name="type_name" label="Display title · English" fullWidth defaultValue={bannerEdit?.type_name || ''} inputProps={{ maxLength: 191 }} /></Grid>
          <Grid item xs={12}><TextField name="type_name_bn" label="Display title · Bangla" fullWidth defaultValue={bannerEdit?.type_name_bn || ''} inputProps={{ maxLength: 191 }} /></Grid>
          </Grid>
        </Box>
        <Box sx={{ p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 2 }}>
          <Typography variant="subtitle2" sx={{ mb: 0.5 }}>Homepage hero artwork</Typography>
          <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 1.5 }}>This is the wide image displayed behind the banner title on the website. One image is enough.</Typography>
          <BannerArtworkField kind="background" value={backgroundPreview} onChange={setBackgroundPreview} onUpload={uploadBannerImage} onError={(cause) => setError(cause.message)} disabled={Boolean(uploadingArtwork) || busy} />
        </Box>
        <Accordion disableGutters variant="outlined" defaultExpanded={Boolean(bannerEdit?.poster_url)} sx={{ borderRadius: '12px !important', '&:before': { display: 'none' } }}>
          <AccordionSummary expandIcon={<Box component="span" aria-hidden="true" sx={{ fontSize: 22, lineHeight: 1 }}>⌄</Box>}><Box><Typography variant="subtitle2">Optional poster artwork</Typography><Typography variant="caption" color="text.secondary">The homepage hero uses the wide image above. Add this portrait image only when another banner surface needs a poster; it is optional.</Typography></Box></AccordionSummary>
          <AccordionDetails><BannerArtworkField kind="poster" value={posterPreview} onChange={setPosterPreview} onUpload={uploadBannerImage} onError={(cause) => setError(cause.message)} disabled={Boolean(uploadingArtwork) || busy} /></AccordionDetails>
        </Accordion>
        <TextField name="click_url" label="Click destination (https://...)" fullWidth defaultValue={bannerEdit?.click_url || ''} helperText="Leave blank to use the linked content destination." />
        <Grid container spacing={1.5}>
          <Grid item xs={12} md={6}><TextField name="description" label="Description (English)" multiline minRows={3} fullWidth defaultValue={bannerEdit?.description_en || bannerEdit?.description || ''} inputProps={{ maxLength: 5000 }} /></Grid>
          <Grid item xs={12} md={6}><TextField name="description_bn" label="Description (Bangla)" multiline minRows={3} fullWidth defaultValue={bannerEdit?.description_bn || ''} inputProps={{ maxLength: 5000 }} /></Grid>
        </Grid>
        <Typography variant="subtitle2" sx={{ pt: 0.5 }}>Publish window</Typography>
        <Grid container spacing={1.5}>
          <Grid item xs={12} sm={6}><TextField name="starts_at" label="Starts at" type="datetime-local" fullWidth InputLabelProps={{ shrink: true }} defaultValue={bannerEdit?.starts_at?.slice(0, 16) || ''} helperText="Blank means available immediately." /></Grid>
          <Grid item xs={12} sm={6}><TextField name="ends_at" label="Ends at" type="datetime-local" fullWidth InputLabelProps={{ shrink: true }} defaultValue={bannerEdit?.ends_at?.slice(0, 16) || ''} helperText="Optional end time." /></Grid>
        </Grid>
        <TextField name="status" select label="Publication status" fullWidth defaultValue={bannerEdit ? String(isBannerEnabled(bannerEdit)) : 'true'}><MenuItem value="true">Enabled</MenuItem><MenuItem value="false">Disabled</MenuItem></TextField>
        <Stack direction="row" spacing={1} justifyContent="flex-end">
          <Button type="button" onClick={closeBannerDialog} disabled={busy || Boolean(uploadingArtwork)}>Cancel</Button>
          <Button type="submit" variant="contained" disabled={busy || Boolean(uploadingArtwork)}>{uploadingArtwork ? 'Uploading artwork…' : busy ? 'Saving…' : bannerEdit ? 'Save changes' : 'Create banner'}</Button>
        </Stack>
      </Stack>
    </form>
  );

  const saveHomepage = (event) => {
    event.preventDefault();
    const payload = {
      ...homepage,
      sections: homepage.sections.map((section) => section.key === 'latest-movies'
        ? { ...section, content_ids: trendingSelection.map((item) => item.id) }
        : section),
    };
    void perform(() => marketingApi.updateHomepage(payload), 'Homepage layout saved. Changes will appear on the customer website shortly.');
  };

  const moveSection = (index, direction) => {
    setHomepage((current) => {
      const sections = [...current.sections];
      const target = index + direction;
      if (target < 0 || target >= sections.length) return current;
      [sections[index], sections[target]] = [sections[target], sections[index]];
      return { ...current, sections };
    });
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
            {canManageBanners && <Tab label="Homepage layout" />}
          </Tabs>
        </Card>

        {tab === 0 && <Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>Campaign calendar and approvals</Typography>
          {campaigns.length === 0 ? <Typography color="text.secondary">No campaigns have been created.</Typography> : <Table size="small"><TableHead><TableRow><TableCell>Name</TableCell><TableCell>Type / channel</TableCell><TableCell>Segment</TableCell><TableCell>Status</TableCell><TableCell>Schedule</TableCell><TableCell>Delivery</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead><TableBody>{campaigns.map((campaign) => <TableRow key={campaign.id}><TableCell>{campaign.name}</TableCell><TableCell>{campaign.kind}{campaign.channel ? ` · ${campaign.channel}` : ''}</TableCell><TableCell>{campaign.audience_segment}</TableCell><TableCell>{campaign.status}</TableCell><TableCell>{campaign.scheduled_at ? new Date(campaign.scheduled_at).toLocaleString() : '—'}{campaign.expires_at ? ` – ${new Date(campaign.expires_at).toLocaleString()}` : ''}</TableCell><TableCell>{campaign.sent_count ?? 0} / {campaign.recipients_count ?? 0} sent · {campaign.failed_count ?? 0} failed</TableCell><TableCell align="right"><Stack direction="row" justifyContent="flex-end">{['draft', 'rejected'].includes(campaign.status) && <Button size="small" disabled={busy} onClick={() => { setSelected(campaign); setChannel(campaign.channel || 'email'); setAudienceSegment(campaign.audience_segment); setTab(1); }}>Edit</Button>}{campaign.status === 'draft' && <Button size="small" disabled={busy} onClick={() => perform(() => marketingApi.submit(campaign.id), 'Submitted for independent approval.')}>Submit</Button>}{canApprove && campaign.status === 'pending_approval' && <><Button size="small" color="error" disabled={busy} onClick={() => rejectCampaign(campaign)}>Reject</Button><Button size="small" disabled={busy} onClick={() => perform(() => marketingApi.approve(campaign.id), 'Campaign approved.')}>Approve</Button></>}{campaign.status === 'approved' && <Button size="small" disabled={busy} onClick={() => scheduleCampaign(campaign)}>Schedule</Button>}{['scheduled', 'published'].includes(campaign.status) && <Button size="small" disabled={busy} onClick={() => perform(() => marketingApi.pause(campaign.id), 'Campaign deactivated.')}>Deactivate</Button>}{campaign.published_at && <Button size="small" onClick={async () => { try { setAnalytics(await marketingApi.analytics(campaign.id)); } catch (cause) { setError(message(cause)); } }}>Analytics</Button>}</Stack></TableCell></TableRow>)}</TableBody></Table>}
          {analytics && <Stack sx={{ mt: 2 }} spacing={1}><Typography variant="subtitle1">Performance — {analytics.campaign.name}</Typography>{analytics.performance.map((row) => <Typography key={`${row.channel}-${row.variant}-${row.status}`} variant="body2">{row.channel} · variant {row.variant} · {row.status}: {row.count}, opens {row.opens}, clicks {row.clicks}</Typography>)}</Stack>}
        </CardContent></Card>}

        {tab === 1 && <Card><CardContent><Typography variant="h6" sx={{ mb: 2 }}>{selected ? `Edit draft: ${selected.name}` : 'Campaign builder and notification composer'}</Typography>
          <form key={selected?.id ?? 'new-campaign'} onSubmit={submitCampaign}><Stack spacing={2}>
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

        {tab === 3 && canManageBanners && <Grid container spacing={2} alignItems="flex-start">
          <Grid item xs={12}>
            <Card>
              <CardContent>
                <Stack direction={{ xs: 'column', sm: 'row' }} alignItems={{ sm: 'center' }} justifyContent="space-between" spacing={1.5} sx={{ mb: 2 }}>
                  <Box><Typography variant="h6">Homepage banners</Typography><Typography variant="body2" color="text.secondary">Manage the artwork and schedule shown across the customer website.</Typography></Box>
                  <Button variant="contained" onClick={startNewBanner}>Create banner</Button>
                </Stack>
                <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap" sx={{ mb: 2 }}>
                  <Chip size="small" variant="outlined" label={`${banners.length} total`} />
                  <Chip size="small" color="success" variant="outlined" label={`${banners.filter(isBannerEnabled).length} active`} />
                  <Chip size="small" variant="outlined" label={`${banners.filter((banner) => !isBannerEnabled(banner)).length} inactive`} />
                  {(bannerSearch || bannerPlacement !== 'all' || bannerStatus !== 'all') && <Button size="small" onClick={() => { setBannerSearch(''); setBannerPlacement('all'); setBannerStatus('all'); }}>Clear filters</Button>}
                </Stack>
                <Grid container spacing={1.5} sx={{ mb: 2 }}>
                  <Grid item xs={12} md={6}><TextField fullWidth size="small" label="Search banners" placeholder="Title or linked content" value={bannerSearch} onChange={(event) => setBannerSearch(event.target.value)} /></Grid>
                  <Grid item xs={6} md={3}><TextField fullWidth size="small" select label="Placement" value={bannerPlacement} onChange={(event) => setBannerPlacement(event.target.value)}><MenuItem value="all">All placements</MenuItem>{['home', 'movie', 'tv_show', 'livetv', 'video', 'promotional'].map((value) => <MenuItem key={value} value={value}>{value.replace('_', ' ')}</MenuItem>)}</TextField></Grid>
                  <Grid item xs={6} md={3}><TextField fullWidth size="small" select label="Status" value={bannerStatus} onChange={(event) => setBannerStatus(event.target.value)}><MenuItem value="all">All statuses</MenuItem><MenuItem value="active">Active</MenuItem><MenuItem value="inactive">Inactive</MenuItem></TextField></Grid>
                </Grid>
                {banners.length === 0 ? <Alert severity="info">No banners yet. Create one to configure homepage or placement-specific artwork.</Alert> : <TableContainer sx={{ overflowX: 'auto' }}><Table size="small" sx={{ minWidth: 720 }}><TableHead><TableRow><TableCell sx={{ width: '40%' }}>Banner</TableCell><TableCell sx={{ width: '14%' }}>Placement</TableCell><TableCell sx={{ width: '12%' }}>Status</TableCell><TableCell sx={{ width: '26%' }}>Schedule</TableCell><TableCell align="right" sx={{ width: 90 }}>Action</TableCell></TableRow></TableHead><TableBody>
                  {filteredBanners.map((banner) => <TableRow key={banner.id} hover selected={bannerEdit?.id === banner.id}>
                    <TableCell sx={{ minWidth: 190 }}><Stack direction="row" spacing={1.25} alignItems="center"><Box sx={{ width: 64, height: 42, flexShrink: 0, borderRadius: 1, bgcolor: 'action.hover', backgroundImage: `linear-gradient(0deg, rgba(0,0,0,.5), transparent), url("${banner.file_url || banner.poster_url || ''}")`, backgroundSize: 'cover', backgroundPosition: 'center' }} /><Box sx={{ minWidth: 0 }}><Typography variant="body2" fontWeight={600} noWrap>{banner.title}</Typography><Typography variant="caption" color="text.secondary" noWrap>{banner.type_name || 'Custom promotion'}</Typography></Box></Stack></TableCell>
                    <TableCell>{(banner.banner_for || '—').replace('_', ' ')}</TableCell><TableCell><Chip size="small" color={isBannerEnabled(banner) ? 'success' : 'default'} variant={isBannerEnabled(banner) ? 'filled' : 'outlined'} label={isBannerEnabled(banner) ? 'Active' : 'Inactive'} /></TableCell>
                    <TableCell><Stack spacing={0.25}><Typography variant="caption" color="text.primary">{banner.starts_at ? new Date(banner.starts_at).toLocaleString() : 'Starts immediately'}</Typography><Typography variant="caption" color="text.secondary">{banner.ends_at ? `Ends ${new Date(banner.ends_at).toLocaleString()}` : 'No end date'}</Typography></Stack></TableCell>
                    <TableCell align="right"><Stack direction="row" spacing={0.5} justifyContent="flex-end"><Button size="small" onClick={() => editBanner(banner)}>Edit</Button><Button size="small" color="error" onClick={() => setBannerDelete(banner)}>Delete</Button></Stack></TableCell>
                  </TableRow>)}
                  {filteredBanners.length === 0 && <TableRow><TableCell colSpan={5} align="center" sx={{ py: 5, color: 'text.secondary' }}>No banners match these filters.</TableCell></TableRow>}
                </TableBody></Table></TableContainer>}
              </CardContent>
            </Card>
          </Grid>
        </Grid>}
        <Dialog open={bannerDialogOpen} onClose={closeBannerDialog} fullWidth maxWidth="md" aria-labelledby="banner-dialog-title">
          <DialogTitle id="banner-dialog-title">{bannerEdit ? 'Edit homepage banner' : 'Create homepage banner'}</DialogTitle>
          <DialogContent dividers>{error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}{bannerForm}</DialogContent>
        </Dialog>
        <Dialog open={Boolean(bannerDelete)} onClose={() => !busy && setBannerDelete(null)} maxWidth="xs" fullWidth aria-labelledby="delete-banner-title">
          <DialogTitle id="delete-banner-title">Delete banner?</DialogTitle>
          <DialogContent><Typography>“{bannerDelete?.title}” will be removed from the banner list and customer website. The banner record is retained for recovery.</Typography></DialogContent>
          <DialogActions><Button onClick={() => setBannerDelete(null)} disabled={busy}>Cancel</Button><Button color="error" variant="contained" onClick={confirmBannerDelete} disabled={busy}>{busy ? 'Deleting…' : 'Delete banner'}</Button></DialogActions>
        </Dialog>
        {tab === 4 && canManageBanners && <Stack spacing={2}>
          <Card><CardContent><Typography variant="h6">Homepage sections</Typography><Typography color="text.secondary" variant="body2" sx={{ mb: 2 }}>Choose which catalog rows appear and set their order and bilingual headings.</Typography>
            <form onSubmit={saveHomepage}><Stack spacing={1.5}>{homepage.sections.map((section, index) => <Card variant="outlined" key={section.key}><CardContent><Grid container spacing={1.5} alignItems="center">
              <Grid item xs={12} md={2}><Typography fontWeight={600}>{section.key}</Typography><Stack direction="row"><Button type="button" size="small" disabled={index === 0} onClick={() => moveSection(index, -1)}>Move up</Button><Button type="button" size="small" disabled={index === homepage.sections.length - 1} onClick={() => moveSection(index, 1)}>Move down</Button></Stack></Grid>
              <Grid item xs={12} md={4}><TextField fullWidth size="small" label="Heading (English)" value={section.title} onChange={(event) => setHomepage((current) => ({ ...current, sections: current.sections.map((item, i) => i === index ? { ...item, title: event.target.value } : item) }))} /></Grid>
              <Grid item xs={12} md={4}><TextField fullWidth size="small" label="Heading (Bangla)" value={section.title_bn} onChange={(event) => setHomepage((current) => ({ ...current, sections: current.sections.map((item, i) => i === index ? { ...item, title_bn: event.target.value } : item) }))} /></Grid>
              <Grid item xs={12} md={2}><FormControlLabel control={<Switch checked={section.enabled} onChange={(event) => setHomepage((current) => ({ ...current, sections: current.sections.map((item, i) => i === index ? { ...item, enabled: event.target.checked } : item) }))} />} label="Visible" /></Grid>
            </Grid></CardContent></Card>)}
            <Typography variant="h6" sx={{ mt: 2 }}>Premium promotion card</Typography><Grid container spacing={2}>
              <Grid item xs={12} md={4}><TextField fullWidth label="Title" value={homepage.premium_card.title} onChange={(event) => setHomepage((current) => ({ ...current, premium_card: { ...current.premium_card, title: event.target.value } }))} /></Grid>
              <Grid item xs={12} md={8}><TextField fullWidth label="Description" value={homepage.premium_card.description} onChange={(event) => setHomepage((current) => ({ ...current, premium_card: { ...current.premium_card, description: event.target.value } }))} /></Grid>
              <Grid item xs={12} md={4}><TextField fullWidth label="Artwork URL" value={homepage.premium_card.image_url || ''} onChange={(event) => setHomepage((current) => ({ ...current, premium_card: { ...current.premium_card, image_url: event.target.value } }))} /></Grid>
              <Grid item xs={12} md={3}><TextField fullWidth label="Button label" value={homepage.premium_card.cta} onChange={(event) => setHomepage((current) => ({ ...current, premium_card: { ...current.premium_card, cta: event.target.value } }))} /></Grid>
              <Grid item xs={12} md={3}><TextField fullWidth label="Destination" value={homepage.premium_card.url} onChange={(event) => setHomepage((current) => ({ ...current, premium_card: { ...current.premium_card, url: event.target.value } }))} /></Grid>
              <Grid item xs={12} md={2}><FormControlLabel control={<Switch checked={homepage.premium_card.enabled} onChange={(event) => setHomepage((current) => ({ ...current, premium_card: { ...current.premium_card, enabled: event.target.checked } }))} />} label="Visible" /></Grid>
            </Grid>
            <Typography variant="h6" sx={{ mt: 2 }}>Trending Now content</Typography>
            <Typography variant="body2" color="text.secondary">Search released movies, add them to the row, and adjust their display order. An empty selection hides this row.</Typography>
            <TextField fullWidth label="Search movies" value={catalogSearch} onChange={(event) => setCatalogSearch(event.target.value)} />
            <Grid container spacing={2}>
              <Grid item xs={12} md={6}><Typography variant="subtitle2" sx={{ mb: 1 }}>Search results</Typography><Stack spacing={1}>{catalogItems.filter((item) => !trendingSelection.some((selectedItem) => String(selectedItem.id) === String(item.id))).map((item) => <Card variant="outlined" key={item.id}><CardContent sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, py: '10px !important' }}><Typography variant="body2">{item.name}{item.name_bn ? ` · ${item.name_bn}` : ''}</Typography><Button type="button" size="small" disabled={trendingSelection.length >= 30} onClick={() => setTrendingSelection((current) => [...current, item])}>Add</Button></CardContent></Card>)}{catalogItems.length === 0 && <Typography variant="body2" color="text.secondary">No matching published movies.</Typography>}</Stack></Grid>
              <Grid item xs={12} md={6}><Typography variant="subtitle2" sx={{ mb: 1 }}>Selected Trending Now titles ({trendingSelection.length}/30)</Typography><Stack spacing={1}>{trendingSelection.map((item, index) => <Card variant="outlined" key={item.id}><CardContent sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 1, py: '8px !important' }}><Typography variant="body2" sx={{ minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis' }}>{index + 1}. {item.name}{item.name_bn ? ` · ${item.name_bn}` : ''}</Typography><Stack direction="row" flexShrink={0}><Button type="button" size="small" disabled={index === 0} onClick={() => setTrendingSelection((current) => { const next = [...current]; [next[index - 1], next[index]] = [next[index], next[index - 1]]; return next; })}>↑</Button><Button type="button" size="small" disabled={index === trendingSelection.length - 1} onClick={() => setTrendingSelection((current) => { const next = [...current]; [next[index + 1], next[index]] = [next[index], next[index + 1]]; return next; })}>↓</Button><Button type="button" size="small" color="error" onClick={() => setTrendingSelection((current) => current.filter((selectedItem) => String(selectedItem.id) !== String(item.id)))}>Remove</Button></Stack></CardContent></Card>)}{trendingSelection.length === 0 && <Typography variant="body2" color="text.secondary">No movies selected yet.</Typography>}</Stack></Grid>
            </Grid><Button type="submit" variant="contained" disabled={busy} sx={{ mt: 2 }}>Save homepage layout</Button></Stack></form>
          </CardContent></Card>
        </Stack>}
      </Stack>
    </DashboardContent>
  );
}

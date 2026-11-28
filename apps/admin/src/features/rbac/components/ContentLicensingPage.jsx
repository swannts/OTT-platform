'use client';

import { useState } from 'react';

import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { Iconify } from 'src/components/iconify';
import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';

import { hasPermission } from '../permissions';
import { rbacApi } from '../api/rbac.api';
import { RbacPageHeader } from './RbacPageHeader';

const CONTENT_TYPES = [
  { value: 'entertainment', label: 'Movie or series' },
  { value: 'episode', label: 'Episode' },
  { value: 'video', label: 'Video' },
];

function toUtcInput(value) {
  return value ? new Date(value).toISOString().slice(0, 16) : '';
}

function fromUtcInput(value) {
  if (!value) return null;
  const date = new Date(`${value}:00Z`);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

function normalizeCountryCodes(value) {
  return value
    .split(',')
    .map((code) => code.trim().toUpperCase())
    .filter(Boolean);
}

function firstErrorMessage(error, fallback) {
  const validationErrors = error.response?.data?.errors;
  return (
    (validationErrors && Object.values(validationErrors).flat()[0]) ||
    error.response?.data?.message ||
    fallback
  );
}

export function ContentLicensingPage() {
  const { user } = useAuthContext();
  const [contentType, setContentType] = useState('entertainment');
  const [contentId, setContentId] = useState('');
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [allowedCountryCodes, setAllowedCountryCodes] = useState('');
  const [baseline, setBaseline] = useState(null);
  const [busyAction, setBusyAction] = useState('');
  const [loadedContentKey, setLoadedContentKey] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const canSchedule = hasPermission(user, 'content.schedule');
  const currentContentKey = `${contentType}:${contentId.trim()}`;
  const isLoaded = Boolean(contentId.trim()) && loadedContentKey === currentContentKey;
  const hasSchedule = Boolean(startsAt || endsAt);
  const isDirty = baseline
    ? JSON.stringify({ startsAt, endsAt, allowedCountryCodes }) !== JSON.stringify(baseline)
    : false;

  const resetLoadedWindow = () => {
    setStartsAt('');
    setEndsAt('');
    setAllowedCountryCodes('');
    setBaseline(null);
    setLoadedContentKey('');
    setError('');
    setMessage('');
  };

  const loadWindow = async (event) => {
    event.preventDefault();
    const requestedType = contentType;
    const requestedId = contentId.trim();
    if (!requestedId) return;

    setBusyAction('load');
    setError('');
    setMessage('');
    setLoadedContentKey('');
    try {
      const response = await rbacApi.contentLicenseWindow(requestedType, requestedId);
      const nextWindow = {
        startsAt: toUtcInput(response.data?.starts_at),
        endsAt: toUtcInput(response.data?.ends_at),
        allowedCountryCodes: (response.data?.allowed_country_codes || []).join(', '),
      };
      setStartsAt(nextWindow.startsAt);
      setEndsAt(nextWindow.endsAt);
      setAllowedCountryCodes(nextWindow.allowedCountryCodes);
      setBaseline(nextWindow);
      setLoadedContentKey(`${requestedType}:${requestedId}`);
      setMessage('Licensing settings loaded for this catalog item.');
    } catch (loadError) {
      resetLoadedWindow();
      setError(firstErrorMessage(loadError, 'The licensing settings could not be loaded.'));
    } finally {
      setBusyAction('');
    }
  };

  const saveWindow = async (event) => {
    event.preventDefault();
    const startsAtUtc = fromUtcInput(startsAt);
    const endsAtUtc = fromUtcInput(endsAt);
    const countryCodes = normalizeCountryCodes(allowedCountryCodes);

    if ((startsAt && !startsAtUtc) || (endsAt && !endsAtUtc)) {
      setError('Enter valid start and end dates, or leave a date blank for an open boundary.');
      return;
    }
    if (startsAtUtc && endsAtUtc && new Date(endsAtUtc) <= new Date(startsAtUtc)) {
      setError('The end date must be later than the start date.');
      return;
    }
    if (countryCodes.some((code) => !/^[A-Z]{2}$/.test(code))) {
      setError('Use two-letter ISO country codes separated by commas, such as BD, MY, ID.');
      return;
    }
    if (new Set(countryCodes).size !== countryCodes.length) {
      setError('Remove duplicate country codes before saving.');
      return;
    }

    setBusyAction('save');
    setError('');
    setMessage('');
    try {
      const response = await rbacApi.updateContentLicenseWindow(contentType, contentId.trim(), {
        starts_at: startsAtUtc,
        ends_at: endsAtUtc,
        allowed_country_codes: countryCodes,
      });
      const savedWindow = {
        startsAt: toUtcInput(response.data?.starts_at),
        endsAt: toUtcInput(response.data?.ends_at),
        allowedCountryCodes: (response.data?.allowed_country_codes || []).join(', '),
      };
      setStartsAt(savedWindow.startsAt);
      setEndsAt(savedWindow.endsAt);
      setAllowedCountryCodes(savedWindow.allowedCountryCodes);
      setBaseline(savedWindow);
      setMessage(
        'Licensing settings saved. Playback availability updates using UTC dates and the configured country allowlist.'
      );
    } catch (saveError) {
      setError(firstErrorMessage(saveError, 'The licensing settings could not be saved.'));
    } finally {
      setBusyAction('');
    }
  };

  const clearSchedule = async () => {
    setBusyAction('clear');
    setError('');
    setMessage('');
    try {
      await rbacApi.updateContentLicenseWindow(contentType, contentId.trim(), {
        starts_at: null,
        ends_at: null,
      });
      const clearedSchedule = { ...baseline, startsAt: '', endsAt: '' };
      setStartsAt('');
      setEndsAt('');
      setBaseline(clearedSchedule);
      setMessage('The date schedule was cleared. The country allowlist remains in effect.');
    } catch (clearError) {
      setError(firstErrorMessage(clearError, 'The schedule could not be cleared.'));
    } finally {
      setBusyAction('');
    }
  };

  return (
    <DashboardContent>
      <RbacPageHeader
        title="Content licensing"
        description="Control when and where catalog items can play. Episode playback also honors its parent series restrictions."
      />

      <Stack spacing={2.5}>
        {error && (
          <Alert severity="error" onClose={() => setError('')}>
            {error}
          </Alert>
        )}
        {message && (
          <Alert severity="success" onClose={() => setMessage('')}>
            {message}
          </Alert>
        )}
        {!canSchedule && (
          <Alert severity="info">
            You can view licensing settings. The content scheduling permission is required to change
            them.
          </Alert>
        )}

        <Card
          sx={{ border: 1, borderColor: 'divider', boxShadow: '0 8px 24px rgba(15, 23, 42, 0.04)' }}
        >
          <CardContent sx={{ p: { xs: 2, sm: 3 }, '&:last-child': { pb: { xs: 2, sm: 3 } } }}>
            <Stack spacing={3}>
              <Box>
                <Typography variant="h6">Find a catalog item</Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                  Load an item first to view or update its playback rules.
                </Typography>
              </Box>

              <Stack component="form" onSubmit={loadWindow} spacing={2}>
                <Grid container spacing={1.5} alignItems="flex-start">
                  <Grid item xs={12} sm={5} md={4}>
                    <TextField
                      select
                      fullWidth
                      label="Content type"
                      value={contentType}
                      onChange={(event) => {
                        setContentType(event.target.value);
                        setContentId('');
                        resetLoadedWindow();
                      }}
                      disabled={Boolean(busyAction)}
                    >
                      {CONTENT_TYPES.map((type) => (
                        <MenuItem key={type.value} value={type.value}>
                          {type.label}
                        </MenuItem>
                      ))}
                    </TextField>
                  </Grid>
                  <Grid item xs={12} sm={5} md={6}>
                    <TextField
                      required
                      fullWidth
                      label="Content ID"
                      placeholder="Paste the movie, episode, or video ID"
                      value={contentId}
                      onChange={(event) => {
                        setContentId(event.target.value);
                        resetLoadedWindow();
                      }}
                      inputProps={{ maxLength: 64 }}
                      disabled={Boolean(busyAction)}
                    />
                  </Grid>
                  <Grid item xs={12} sm={2}>
                    <Button
                      type="submit"
                      fullWidth
                      variant="outlined"
                      startIcon={
                        <Iconify
                          icon={
                            busyAction === 'load' ? 'solar:refresh-bold' : 'solar:magnifer-bold'
                          }
                        />
                      }
                      disabled={Boolean(busyAction) || !contentId.trim()}
                      sx={{ minHeight: 56, whiteSpace: 'nowrap' }}
                    >
                      {busyAction === 'load' ? 'Loading…' : 'Load settings'}
                    </Button>
                  </Grid>
                </Grid>
              </Stack>

              {isLoaded && (
                <Stack direction="row" alignItems="center" spacing={1} useFlexGap flexWrap="wrap">
                  <Chip
                    icon={<Iconify icon="solar:check-circle-bold" />}
                    color="success"
                    variant="soft"
                    label="Item loaded"
                  />
                  <Typography variant="body2" color="text.secondary">
                    {CONTENT_TYPES.find((type) => type.value === contentType)?.label} · ID{' '}
                    {contentId.trim()}
                  </Typography>
                </Stack>
              )}

              <Divider />

              <Box>
                <Stack
                  direction={{ xs: 'column', sm: 'row' }}
                  justifyContent="space-between"
                  alignItems={{ sm: 'center' }}
                  spacing={0.5}
                  sx={{ mb: 2 }}
                >
                  <Box>
                    <Typography variant="subtitle1" sx={{ fontWeight: 650 }}>
                      Playback schedule
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Leave a boundary blank to keep it open.
                    </Typography>
                  </Box>
                  <Chip
                    size="small"
                    variant="outlined"
                    icon={<Iconify icon="solar:clock-circle-bold" />}
                    label="All times UTC"
                  />
                </Stack>
                <Grid container spacing={1.5}>
                  <Grid item xs={12} md={6}>
                    <TextField
                      fullWidth
                      type="datetime-local"
                      label="Starts at"
                      value={startsAt}
                      onChange={(event) => setStartsAt(event.target.value)}
                      InputLabelProps={{ shrink: true }}
                      inputProps={{ max: endsAt || undefined }}
                      disabled={!isLoaded || !canSchedule || Boolean(busyAction)}
                      helperText="Blank means playback is already available."
                    />
                  </Grid>
                  <Grid item xs={12} md={6}>
                    <TextField
                      fullWidth
                      type="datetime-local"
                      label="Ends at"
                      value={endsAt}
                      onChange={(event) => setEndsAt(event.target.value)}
                      InputLabelProps={{ shrink: true }}
                      inputProps={{ min: startsAt || undefined }}
                      disabled={!isLoaded || !canSchedule || Boolean(busyAction)}
                      helperText="Blank means playback does not expire."
                    />
                  </Grid>
                </Grid>
              </Box>

              <Box>
                <Typography variant="subtitle1" sx={{ fontWeight: 650, mb: 0.5 }}>
                  Territory allowlist
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
                  Playback is allowed only in the listed countries. Leave empty to allow all
                  territories.
                </Typography>
                <TextField
                  fullWidth
                  label="ISO country codes"
                  placeholder="BD, MY, ID"
                  helperText="Enter two-letter country codes separated by commas. A trusted edge country signal is required for restricted content."
                  value={allowedCountryCodes}
                  onChange={(event) => setAllowedCountryCodes(event.target.value)}
                  disabled={!isLoaded || !canSchedule || Boolean(busyAction)}
                />
              </Box>

              {canSchedule && (
                <>
                  <Divider />
                  <Stack
                    direction={{ xs: 'column-reverse', sm: 'row' }}
                    justifyContent="space-between"
                    alignItems={{ sm: 'center' }}
                    spacing={1.5}
                  >
                    <Typography variant="caption" color="text.secondary">
                      Changes apply to playback checks after they are saved.
                    </Typography>
                    <Stack direction="row" spacing={1} justifyContent="flex-end">
                      <Button
                        type="button"
                        color="inherit"
                        onClick={clearSchedule}
                        disabled={Boolean(busyAction) || !isLoaded || !hasSchedule}
                      >
                        Clear dates
                      </Button>
                      <Button
                        type="button"
                        variant="contained"
                        onClick={saveWindow}
                        disabled={Boolean(busyAction) || !isLoaded || !isDirty}
                        startIcon={
                          <Iconify
                            icon={
                              busyAction === 'save' ? 'solar:refresh-bold' : 'solar:diskette-bold'
                            }
                          />
                        }
                      >
                        {busyAction === 'save' ? 'Saving…' : 'Save licensing'}
                      </Button>
                    </Stack>
                  </Stack>
                </>
              )}
            </Stack>
          </CardContent>
        </Card>
      </Stack>
    </DashboardContent>
  );
}

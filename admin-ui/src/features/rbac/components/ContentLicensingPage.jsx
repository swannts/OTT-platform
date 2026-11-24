'use client';

import { useState } from 'react';

import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

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
  return value ? new Date(`${value}:00Z`).toISOString() : null;
}

export function ContentLicensingPage() {
  const { user } = useAuthContext();
  const [contentType, setContentType] = useState('entertainment');
  const [contentId, setContentId] = useState('');
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [allowedCountryCodes, setAllowedCountryCodes] = useState('');
  const [loading, setLoading] = useState(false);
  const [loadedContentKey, setLoadedContentKey] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const canSchedule = hasPermission(user, 'content.schedule');
  const isLoaded = loadedContentKey === `${contentType}:${contentId.trim()}`;

  const loadWindow = async (event) => {
    event.preventDefault();
    if (!contentId.trim()) return;

    setLoading(true);
    setError('');
    setMessage('');
    try {
      const response = await rbacApi.contentLicenseWindow(contentType, contentId.trim());
      setStartsAt(toUtcInput(response.data?.starts_at));
      setEndsAt(toUtcInput(response.data?.ends_at));
      setAllowedCountryCodes((response.data?.allowed_country_codes || []).join(', '));
      setContentId(contentId.trim());
      setLoadedContentKey(`${contentType}:${contentId.trim()}`);
      setMessage('Licensing window loaded.');
    } catch (loadError) {
      setLoadedContentKey('');
      setError(loadError.response?.data?.message || 'The licensing window could not be loaded.');
    } finally {
      setLoading(false);
    }
  };

  const saveWindow = async (event) => {
    event.preventDefault();
    setLoading(true);
    setError('');
    setMessage('');
    try {
      await rbacApi.updateContentLicenseWindow(contentType, contentId.trim(), {
        starts_at: fromUtcInput(startsAt),
        ends_at: fromUtcInput(endsAt),
        allowed_country_codes: allowedCountryCodes.split(',').map((code) => code.trim().toUpperCase()).filter(Boolean),
      });
      setMessage('Playback licensing saved. Dates use UTC; country restrictions require a trusted edge country signal.');
    } catch (saveError) {
      const validationErrors = saveError.response?.data?.errors;
      const firstValidationError = validationErrors ? Object.values(validationErrors).flat()[0] : null;
      setError(
        firstValidationError ||
          saveError.response?.data?.message ||
          'The licensing window could not be saved.'
      );
    } finally {
      setLoading(false);
    }
  };

  const clearWindow = async () => {
    setLoading(true);
    setError('');
    setMessage('');
    try {
      await rbacApi.updateContentLicenseWindow(contentType, contentId.trim(), { starts_at: null, ends_at: null });
      setStartsAt('');
      setEndsAt('');
      setMessage('Schedule cleared. Any country restrictions remain in effect.');
    } catch (clearError) {
      setError(clearError.response?.data?.message || 'The licensing window could not be cleared.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <DashboardContent>
      <RbacPageHeader
        title="Content licensing"
        description="Set UTC playback dates and an optional country allowlist. Episodes also honor their parent series restrictions."
      />
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {message && <Alert severity="success" sx={{ mb: 2 }}>{message}</Alert>}
      <Card component="form" onSubmit={loadWindow} sx={{ p: 3 }}>
        <Stack spacing={2}>
          <Typography variant="subtitle1">Find a catalog item</Typography>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
            <TextField
              select
              fullWidth
              label="Content type"
              value={contentType}
              onChange={(event) => {
                setContentType(event.target.value);
                setLoadedContentKey('');
                setStartsAt('');
                setEndsAt('');
              }}
            >
              {CONTENT_TYPES.map((type) => <MenuItem key={type.value} value={type.value}>{type.label}</MenuItem>)}
            </TextField>
            <TextField
              fullWidth
              label="Content ID"
              value={contentId}
              onChange={(event) => {
                setContentId(event.target.value);
                setLoadedContentKey('');
              }}
              inputProps={{ maxLength: 64 }}
            />
            <Button type="submit" variant="outlined" disabled={loading || !contentId.trim()}>
              Load window
            </Button>
          </Stack>
          <TextField
            fullWidth
            label="Allowed country codes"
            placeholder="BD, MY, ID"
            helperText="Optional ISO country codes separated by commas. Blank allows all territories. Restricted titles deny playback when a trusted edge country cannot be resolved."
            value={allowedCountryCodes}
            onChange={(event) => setAllowedCountryCodes(event.target.value)}
            disabled={!isLoaded || !canSchedule}
          />
          <Typography variant="caption" color="text.secondary">
            Dates use UTC. Leave either date blank for an open boundary. Leave both blank to remove the schedule.
          </Typography>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
            <TextField
              fullWidth
              type="datetime-local"
              label="Starts at (UTC)"
              value={startsAt}
              onChange={(event) => setStartsAt(event.target.value)}
              InputLabelProps={{ shrink: true }}
              disabled={!isLoaded || !canSchedule}
            />
            <TextField
              fullWidth
              type="datetime-local"
              label="Ends at (UTC)"
              value={endsAt}
              onChange={(event) => setEndsAt(event.target.value)}
              InputLabelProps={{ shrink: true }}
              disabled={!isLoaded || !canSchedule}
            />
          </Stack>
          {canSchedule && (
            <Stack direction="row" spacing={1} justifyContent="flex-end">
              <Button onClick={clearWindow} disabled={loading || !isLoaded}>Clear schedule</Button>
              <Button type="button" variant="contained" onClick={saveWindow} disabled={loading || !isLoaded}>
                Save licensing window
              </Button>
            </Stack>
          )}
        </Stack>
      </Card>
    </DashboardContent>
  );
}

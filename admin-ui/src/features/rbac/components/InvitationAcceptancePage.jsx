'use client';

import { useState } from 'react';
import { useRouter } from 'src/routes/hooks';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { rbacApi } from '../api/rbac.api';

export function InvitationAcceptancePage({ token }) {
  const router = useRouter();
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    password: '',
    password_confirmation: '',
  });
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [accepted, setAccepted] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setSaving(true);
    try {
      await rbacApi.acceptInvitation({ token, ...form });
      setAccepted(true);
    } catch (submitError) {
      setError(submitError.message || 'This invitation could not be accepted.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Box sx={{ minHeight: '100vh', display: 'grid', placeItems: 'center', p: 2 }}>
      <Card sx={{ width: '100%', maxWidth: 520 }}>
        <CardContent sx={{ p: { xs: 3, md: 5 } }}>
          <Stack spacing={2.5} component="form" onSubmit={submit}>
            <Box>
              <Typography variant="h4">Accept staff invitation</Typography>
              <Typography color="text.secondary" sx={{ mt: 1 }}>
                Create your secure administrative account to continue.
              </Typography>
            </Box>
            {error && <Alert severity="error">{error}</Alert>}
            {accepted ? (
              <>
                <Alert severity="success">
                  Your invitation has been accepted. You can sign in now.
                </Alert>
                <Button variant="contained" onClick={() => router.push('/login')}>
                  Go to sign in
                </Button>
              </>
            ) : (
              <>
                <TextField
                  required
                  label="First name"
                  autoComplete="given-name"
                  value={form.first_name}
                  onChange={(event) => setForm({ ...form, first_name: event.target.value })}
                />
                <TextField
                  label="Last name"
                  autoComplete="family-name"
                  value={form.last_name}
                  onChange={(event) => setForm({ ...form, last_name: event.target.value })}
                />
                <TextField
                  required
                  type="password"
                  label="Password (at least 14 characters)"
                  autoComplete="new-password"
                  inputProps={{ minLength: 14 }}
                  value={form.password}
                  onChange={(event) => setForm({ ...form, password: event.target.value })}
                />
                <TextField
                  required
                  type="password"
                  label="Confirm password"
                  autoComplete="new-password"
                  inputProps={{ minLength: 14 }}
                  value={form.password_confirmation}
                  onChange={(event) =>
                    setForm({ ...form, password_confirmation: event.target.value })
                  }
                />
                <Button type="submit" variant="contained" size="large" disabled={saving || !token}>
                  {saving ? 'Accepting…' : 'Accept invitation'}
                </Button>
              </>
            )}
          </Stack>
        </CardContent>
      </Card>
    </Box>
  );
}

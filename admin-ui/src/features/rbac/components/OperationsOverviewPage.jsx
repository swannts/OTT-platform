'use client';

import { useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { hasPermission } from '../permissions';
import { rbacApi } from '../api/rbac.api';
import { RbacPageHeader } from './RbacPageHeader';

const sections = [
  { permission: 'media.view', title: 'Media', load: rbacApi.mediaOverview },
  { permission: 'subscriptions.view', title: 'Subscriptions', load: rbacApi.subscriptionsOverview },
  { permission: 'finance.payments.view', title: 'Finance', load: rbacApi.financeOverview },
  { permission: 'marketing.campaigns.manage', title: 'Marketing', load: rbacApi.marketingOverview },
  { permission: 'analytics.audience.view', title: 'Analytics', load: rbacApi.analyticsOverview },
  { permission: 'operations.health.view', title: 'Technical operations', load: rbacApi.operationsOverview },
];

export function OperationsOverviewPage() {
  const { user } = useAuthContext();
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    const available = sections.filter((section) => hasPermission(user, section.permission));
    Promise.all(available.map(async (section) => {
      try {
        const response = await section.load();
        return { ...section, data: response.data, error: '' };
      } catch (error) {
        return { ...section, data: null, error: error.response?.data?.message || 'This summary could not be loaded.' };
      }
    })).then((items) => {
      if (active) { setResults(items); setLoading(false); }
    });
    return () => { active = false; };
  }, [user]);

  return (
    <DashboardContent>
      <RbacPageHeader title="Operations & reports" description="Domain summaries are loaded from permission-protected Laravel endpoints. Sensitive payment, customer, and integration data are not included." />
      {loading ? <Typography color="text.secondary">Loading authorized summaries…</Typography> : (
        <Grid container spacing={2}>
          {results.map((section) => (
            <Grid key={section.title} size={{ xs: 12, sm: 6, lg: 4 }}>
              <Card sx={{ height: 1 }}>
                <CardContent>
                  <Typography variant="h6" sx={{ mb: 2 }}>{section.title}</Typography>
                  {section.error ? <Alert severity="error">{section.error}</Alert> : (
                    <Stack spacing={1}>
                      {Object.entries(section.data || {}).map(([label, value]) => (
                        <Stack key={label} direction="row" justifyContent="space-between" spacing={2}>
                          <Typography variant="body2" color="text.secondary">{label.replaceAll('_', ' ')}</Typography>
                          <Typography variant="subtitle2">{value === null ? 'Not configured' : String(value)}</Typography>
                        </Stack>
                      ))}
                    </Stack>
                  )}
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      )}
    </DashboardContent>
  );
}

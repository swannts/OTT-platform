'use client';

import { useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Grid from '@mui/material/Unstable_Grid2';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import CircularProgress from '@mui/material/CircularProgress';
import { DashboardContent } from 'src/layouts/dashboard';

const metrics = [
  ['Viewers', 'viewers', 'Registered viewer accounts'],
  ['Subscribers', 'subscribers', 'Viewer accounts marked subscribed'],
  ['Movies', 'movies', 'Published movies'],
  ['Series', 'series', 'Published TV series'],
  ['Videos', 'videos', 'Active video records'],
  ['Live channels', 'liveChannels', 'Active live TV channels'],
];

export function OttDashboardView() {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    fetch('/api/admin/dashboard', { cache: 'no-store' })
      .then(async (response) => {
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || 'Could not load OTT metrics.');
        setData(body);
      })
      .catch((reason) => setError(reason.message));
  }, []);

  return (
    <DashboardContent maxWidth="xl">
      <Stack spacing={3}>
        <Box>
          <Typography variant="h4">OTT overview</Typography>
          <Typography color="text.secondary">Live platform totals from Laravel.</Typography>
        </Box>
        {error && <Alert severity="error">{error}</Alert>}
        {!data && !error && <CircularProgress size={28} aria-label="Loading OTT overview" />}
        {data && (
          <Grid container spacing={3}>
            {metrics.map(([title, key, detail]) => (
              <Grid key={key} xs={12} sm={6} md={4}>
                <Card>
                  <CardContent>
                    <Stack spacing={1}>
                      <Typography color="text.secondary" variant="subtitle2">{title}</Typography>
                      <Typography variant="h3">{Number(data[key]).toLocaleString()}</Typography>
                      <Typography color="text.secondary" variant="caption">{detail}</Typography>
                    </Stack>
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        )}
      </Stack>
    </DashboardContent>
  );
}

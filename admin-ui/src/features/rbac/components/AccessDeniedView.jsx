'use client';

import Box from '@mui/material/Box';
import Alert from '@mui/material/Alert';
import Typography from '@mui/material/Typography';
import { DashboardContent } from 'src/layouts/dashboard';

export function AccessDeniedView() {
  return (
    <DashboardContent>
      <Box sx={{ maxWidth: 640, mx: 'auto', py: { xs: 6, md: 12 } }}>
        <Alert severity="error" sx={{ alignItems: 'flex-start' }}>
          <Typography variant="h6" sx={{ mb: 0.5 }}>
            Access denied
          </Typography>
          You do not have permission to open this administrative page. If your access has changed,
          refresh your session or contact a platform administrator.
        </Alert>
      </Box>
    </DashboardContent>
  );
}

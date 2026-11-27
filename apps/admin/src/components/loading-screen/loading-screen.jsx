'use client';

import Box from '@mui/material/Box';
import Portal from '@mui/material/Portal';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';

// ----------------------------------------------------------------------

export function LoadingScreen({ portal, sx, ...other }) {
  const content = (
    <Box
      sx={{
        px: 5,
        width: 1,
        flexGrow: 1,
        minHeight: 1,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        ...sx,
      }}
      {...other}
    >
      <Stack spacing={3} sx={{ width: 1, maxWidth: 1080 }}>
        <Stack direction="row" spacing={2}>
          <Skeleton variant="rounded" width={160} height={40} />
          <Skeleton variant="rounded" width={120} height={40} />
        </Stack>
        <Skeleton variant="rounded" height={180} />
        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(3, 1fr)' },
            gap: 2,
          }}
        >
          {[0, 1, 2].map((item) => (
            <Stack key={item} spacing={1.5}>
              <Skeleton variant="rounded" height={120} />
              <Skeleton variant="text" width="65%" height={28} />
              <Skeleton variant="text" width="90%" />
            </Stack>
          ))}
        </Box>
      </Stack>
    </Box>
  );

  if (portal) {
    return <Portal>{content}</Portal>;
  }

  return content;
}

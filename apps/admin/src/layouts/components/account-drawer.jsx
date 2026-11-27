'use client';

import { useState, useCallback } from 'react';

import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Drawer from '@mui/material/Drawer';
import MenuItem from '@mui/material/MenuItem';
import { useTheme } from '@mui/material/styles';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';
import Skeleton from '@mui/material/Skeleton';

import { paths } from 'src/routes/paths';
import { useRouter, usePathname } from 'src/routes/hooks';

import { Label } from 'src/components/label';
import { Iconify } from 'src/components/iconify';
import { Scrollbar } from 'src/components/scrollbar';
import { AnimateAvatar } from 'src/components/animate';

import { useAuthContext } from 'src/auth/hooks';
import { hasPermission } from 'src/features/rbac/permissions';

import { AccountButton } from './account-button';
import { SignOutButton } from './sign-out-button';

// ----------------------------------------------------------------------

export function AccountDrawer({ data = [], sx, ...other }) {
  const theme = useTheme();

  const router = useRouter();

  const pathname = usePathname();

  const { user, loading } = useAuthContext();
  const displayName = user?.name || user?.full_name || user?.email || 'Admin user';
  const avatarUrl = user?.avatar_url || user?.profile_image || user?.photoURL || undefined;
  const accountItems = data.filter(
    (item) => !item.permission || hasPermission(user, item.permission)
  );

  const [open, setOpen] = useState(false);

  const handleOpenDrawer = useCallback(() => {
    setOpen(true);
  }, []);

  const handleCloseDrawer = useCallback(() => {
    setOpen(false);
  }, []);

  const handleClickItem = useCallback(
    (path) => {
      handleCloseDrawer();
      router.push(path);
    },
    [handleCloseDrawer, router]
  );

  return (
    <>
      <AccountButton
        open={open}
        onClick={handleOpenDrawer}
        photoURL={avatarUrl}
        displayName={displayName}
        sx={sx}
        {...other}
      />

      <Drawer
        open={open}
        onClose={handleCloseDrawer}
        anchor="right"
        slotProps={{ backdrop: { invisible: true } }}
        PaperProps={{ sx: { width: 320 } }}
      >
        <IconButton
          aria-label="Close account menu"
          onClick={handleCloseDrawer}
          sx={{ top: 12, left: 12, zIndex: 9, position: 'absolute' }}
        >
          <Iconify icon="mingcute:close-line" />
        </IconButton>

        <Stack sx={{ height: 1, minHeight: 0 }}>
          <Scrollbar sx={{ flex: '1 1 auto' }}>
            {loading ? (
              <Stack
                alignItems="center"
                spacing={1}
                sx={{ pt: 8, px: 2 }}
                aria-label="Loading account"
              >
                <Skeleton variant="circular" width={88} height={88} />
                <Skeleton variant="text" width={148} height={28} sx={{ mt: 1 }} />
                <Skeleton variant="text" width={184} height={22} />
                <Skeleton variant="rounded" width={84} height={24} />
              </Stack>
            ) : (
              <Stack alignItems="center" sx={{ pt: 8, px: 2 }}>
                <AnimateAvatar
                  width={88}
                  slotProps={{ avatar: { src: avatarUrl, alt: displayName } }}
                >
                  {displayName.charAt(0).toUpperCase()}
                </AnimateAvatar>

                <Typography variant="subtitle1" noWrap sx={{ mt: 2 }}>
                  {displayName}
                </Typography>

                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }} noWrap>
                  {user?.email}
                </Typography>
                {user?.role && (
                  <Label color="primary" variant="soft" sx={{ mt: 1, textTransform: 'capitalize' }}>
                    {user.role.replaceAll('_', ' ').replaceAll('-', ' ')}
                  </Label>
                )}
              </Stack>
            )}

            <Stack
              sx={{
                py: 3,
                px: 2.5,
                borderTop: `dashed 1px ${theme.vars.palette.divider}`,
                borderBottom: `dashed 1px ${theme.vars.palette.divider}`,
              }}
            >
              {loading
                ? [0, 1, 2, 3, 4].map((item) => (
                    <Stack
                      key={item}
                      direction="row"
                      alignItems="center"
                      spacing={2}
                      sx={{ px: 1.5, py: 1.5 }}
                    >
                      <Skeleton variant="rounded" width={24} height={24} />
                      <Skeleton variant="text" width={`${48 + item * 7}%`} height={24} />
                    </Stack>
                  ))
                : accountItems.map((option) => (
                    <MenuItem
                      key={option.label}
                      selected={
                        pathname === option.href ||
                        (option.href !== paths.dashboard.root &&
                          pathname.startsWith(`${option.href}/`))
                      }
                      onClick={() => handleClickItem(option.href)}
                      sx={{
                        py: 1,
                        color: 'text.secondary',
                        '& svg': { width: 24, height: 24 },
                        '&:hover': { color: 'text.primary' },
                      }}
                    >
                      {option.icon}

                      <Box component="span" sx={{ ml: 2 }}>
                        {option.label}
                      </Box>
                    </MenuItem>
                  ))}
            </Stack>
          </Scrollbar>
          <Box sx={{ p: 2.5, pt: 1.5 }}>
            {loading ? (
              <Skeleton variant="rounded" height={48} />
            ) : (
              <SignOutButton onClose={handleCloseDrawer} />
            )}
          </Box>
        </Stack>
      </Drawer>
    </>
  );
}

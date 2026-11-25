'use client';

import { m } from 'framer-motion';
import { useCallback, useEffect, useState } from 'react';

import Alert from '@mui/material/Alert';
import Tab from '@mui/material/Tab';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Badge from '@mui/material/Badge';
import Drawer from '@mui/material/Drawer';
import Button from '@mui/material/Button';
import SvgIcon from '@mui/material/SvgIcon';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';
import CircularProgress from '@mui/material/CircularProgress';

import { useBoolean } from 'src/hooks/use-boolean';

import { Iconify } from 'src/components/iconify';
import { varHover } from 'src/components/animate';
import { Scrollbar } from 'src/components/scrollbar';
import { CustomTabs } from 'src/components/custom-tabs';

import { NotificationItem } from './notification-item';
import { notificationsApi } from './notifications.api';

const EMPTY_COUNTS = { all: 0, unread: 0, archived: 0 };
const PAGE_SIZE = 20;
const TABS = [
  { value: 'all', label: 'All' },
  { value: 'unread', label: 'Unread' },
  { value: 'archived', label: 'Archived' },
];

const getErrorMessage = (error) =>
  error?.response?.data?.message || 'Notifications could not be loaded. Please try again.';

export function NotificationsDrawer({ sx, ...other }) {
  const drawer = useBoolean();
  const [currentTab, setCurrentTab] = useState('all');
  const [notifications, setNotifications] = useState([]);
  const [counts, setCounts] = useState(EMPTY_COUNTS);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [busyId, setBusyId] = useState('');
  const [error, setError] = useState('');

  const refreshCounts = useCallback(async () => {
    try {
      setCounts(await notificationsApi.counts());
    } catch {
      // Keep the last known unread count if a background refresh is unavailable.
    }
  }, []);

  const loadNotifications = useCallback(
    async (nextPage = 1, append = false, status = currentTab) => {
      if (append) setLoadingMore(true);
      else setLoading(true);
      setError('');

      try {
        const response = await notificationsApi.list({ status, page: nextPage, per_page: PAGE_SIZE });
        setNotifications((current) =>
          append ? [...current, ...(response.data || [])] : response.data || []
        );
        setCounts(response.counts || EMPTY_COUNTS);
        setPage(nextPage);
        setHasMore(nextPage < (response.meta?.last_page || 1));
      } catch (cause) {
        setError(getErrorMessage(cause));
      } finally {
        setLoading(false);
        setLoadingMore(false);
      }
    },
    [currentTab]
  );

  useEffect(() => {
    const initialRefresh = window.setTimeout(refreshCounts, 0);
    const interval = window.setInterval(refreshCounts, 60_000);
    return () => {
      window.clearTimeout(initialRefresh);
      window.clearInterval(interval);
    };
  }, [refreshCounts]);

  const handleChangeTab = useCallback((event, nextTab) => {
    setCurrentTab(nextTab);
    loadNotifications(1, false, nextTab);
  }, [loadNotifications]);

  const handleAction = async (id, action) => {
    setBusyId(id);
    setError('');
    try {
      await action(id);
      await Promise.all([loadNotifications(page), refreshCounts()]);
    } catch (cause) {
      setError(getErrorMessage(cause));
    } finally {
      setBusyId('');
    }
  };

  const handleMarkAllRead = async () => {
    setBusyId('all');
    setError('');
    try {
      await notificationsApi.markAllRead();
      await Promise.all([loadNotifications(1), refreshCounts()]);
    } catch (cause) {
      setError(getErrorMessage(cause));
    } finally {
      setBusyId('');
    }
  };

  const renderHead = (
    <Stack direction="row" alignItems="center" sx={{ py: 2, pl: 2.5, pr: 1, minHeight: 68 }}>
      <Typography variant="h6" sx={{ flexGrow: 1 }}>
        Notifications
      </Typography>

      {!!counts.unread && (
        <Tooltip title="Mark all as read">
          <span>
            <IconButton color="primary" onClick={handleMarkAllRead} disabled={busyId === 'all'}>
              <Iconify icon="eva:done-all-fill" />
            </IconButton>
          </span>
        </Tooltip>
      )}

      <IconButton onClick={drawer.onFalse} sx={{ display: { xs: 'inline-flex', sm: 'none' } }}>
        <Iconify icon="mingcute:close-line" />
      </IconButton>
    </Stack>
  );

  const renderTabs = (
    <CustomTabs variant="fullWidth" value={currentTab} onChange={handleChangeTab}>
      {TABS.map((tab) => (
        <Tab
          key={tab.value}
          iconPosition="end"
          value={tab.value}
          label={tab.label}
          icon={<Box component="span" sx={{ typography: 'caption', fontWeight: 700 }}>{counts[tab.value]}</Box>}
        />
      ))}
    </CustomTabs>
  );

  const renderList = (
    <Scrollbar>
      {error && <Alert severity="error" sx={{ m: 2 }}>{error}</Alert>}
      {loading ? (
        <Stack alignItems="center" justifyContent="center" sx={{ minHeight: 180 }}>
          <CircularProgress size={28} />
        </Stack>
      ) : notifications.length ? (
        <Box component="ul" sx={{ m: 0, p: 0, listStyle: 'none' }}>
          {notifications.map((notification) => (
            <Box component="li" key={notification.id}>
              <NotificationItem
                notification={notification}
                busy={busyId === notification.id}
                onMarkRead={(id) => handleAction(id, notificationsApi.markRead)}
                onArchive={(id) => handleAction(id, notificationsApi.archive)}
                onRestore={(id) => handleAction(id, notificationsApi.restore)}
              />
            </Box>
          ))}
        </Box>
      ) : (
        <Typography color="text.secondary" align="center" sx={{ px: 3, py: 8 }}>
          {currentTab === 'unread' ? 'You are all caught up.' : `No ${currentTab} notifications.`}
        </Typography>
      )}
    </Scrollbar>
  );

  return (
    <>
      <IconButton
        component={m.button}
        whileTap="tap"
        whileHover="hover"
        variants={varHover(1.05)}
        onClick={() => {
          drawer.onTrue();
          loadNotifications(1);
        }}
        sx={sx}
        aria-label={`Notifications, ${counts.unread} unread`}
        {...other}
      >
        <Badge badgeContent={counts.unread} color="error">
          <SvgIcon>
            <path
              fill="currentColor"
              d="M18.75 9v.704c0 .845.24 1.671.692 2.374l1.108 1.723c1.011 1.574.239 3.713-1.52 4.21a25.794 25.794 0 0 1-14.06 0c-1.759-.497-2.531-2.636-1.52-4.21l1.108-1.723a4.393 4.393 0 0 0 .693-2.374V9c0-3.866 3.022-7 6.749-7s6.75 3.134 6.75 7"
              opacity="0.5"
            />
            <path
              fill="currentColor"
              d="M12.75 6a.75.75 0 0 0-1.5 0v4a.75.75 0 0 0 1.5 0zM7.243 18.545a5.002 5.002 0 0 0 9.513 0c-3.145.59-6.367.59-9.513 0"
            />
          </SvgIcon>
        </Badge>
      </IconButton>

      <Drawer
        open={drawer.value}
        onClose={drawer.onFalse}
        anchor="right"
        slotProps={{ backdrop: { invisible: true } }}
        PaperProps={{ sx: { width: 1, maxWidth: 420 } }}
      >
        {renderHead}
        {renderTabs}
        {renderList}
        {hasMore && !loading && (
          <Box sx={{ p: 1 }}>
            <Button fullWidth size="large" onClick={() => loadNotifications(page + 1, true)} disabled={loadingMore}>
              {loadingMore ? 'Loading…' : 'Load more'}
            </Button>
          </Box>
        )}
      </Drawer>
    </>
  );
}

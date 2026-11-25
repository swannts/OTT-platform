import Stack from '@mui/material/Stack';
import Avatar from '@mui/material/Avatar';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';

import { fToNow } from 'src/utils/format-time';
import { Iconify } from 'src/components/iconify';

export function NotificationItem({ notification, busy, onMarkRead, onArchive, onRestore }) {
  const isUnread = !notification.read_at;
  const isArchived = Boolean(notification.archived_at);

  return (
    <Stack
      direction="row"
      spacing={1.5}
      sx={{
        p: 2.5,
        alignItems: 'flex-start',
        borderBottom: (theme) => `dashed 1px ${theme.vars.palette.divider}`,
        bgcolor: isUnread ? 'action.hover' : 'background.paper',
      }}
    >
      <Avatar sx={{ width: 40, height: 40, bgcolor: 'background.neutral', color: 'text.secondary' }}>
        <Iconify icon="solar:bell-bold-duotone" width={20} />
      </Avatar>

      <Stack spacing={0.5} sx={{ minWidth: 0, flexGrow: 1 }}>
        <Typography variant="subtitle2">{notification.title}</Typography>
        {notification.message && (
          <Typography variant="body2" color="text.secondary" sx={{ overflowWrap: 'anywhere' }}>
            {notification.message}
          </Typography>
        )}
        <Typography variant="caption" color="text.disabled">
          {notification.created_at ? fToNow(notification.created_at) : 'Date unavailable'}
          {notification.category ? ` · ${notification.category}` : ''}
          {isArchived ? ' · Archived' : ''}
        </Typography>
      </Stack>

      {isUnread && !isArchived && (
        <Tooltip title="Mark as read">
          <span>
            <IconButton size="small" aria-label="Mark notification as read" disabled={busy} onClick={() => onMarkRead(notification.id)}>
              <Iconify icon="eva:checkmark-circle-2-outline" />
            </IconButton>
          </span>
        </Tooltip>
      )}

      <Tooltip title={isArchived ? 'Restore notification' : 'Archive notification'}>
        <span>
          <IconButton
            size="small"
            aria-label={isArchived ? 'Restore notification' : 'Archive notification'}
            disabled={busy}
            onClick={() => (isArchived ? onRestore(notification.id) : onArchive(notification.id))}
          >
            <Iconify icon={isArchived ? 'solar:restart-bold' : 'solar:archive-down-bold'} />
          </IconButton>
        </span>
      </Tooltip>
    </Stack>
  );
}

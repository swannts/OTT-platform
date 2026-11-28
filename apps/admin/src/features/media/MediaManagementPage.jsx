'use client';

import { useMemo, useState } from 'react';

import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { MediaLibrary } from './components/MediaLibrary';
import { MediaOverview } from './components/MediaOverview';
import { AudioSubtitleAssets, MediaArchivePage, MediaProcessingQueue, MediaQualityQueue, MediaStoragePage } from './components/MediaOperationsSections';
import { MediaUploadManager } from './components/MediaUploadManager';

const TABS = [
  { id: 'overview', label: 'Overview', permission: 'media.view' },
  { id: 'library', label: 'Media library', permission: 'media.view' },
  { id: 'uploads', label: 'Upload manager', permission: 'media.upload' },
  { id: 'processing', label: 'Processing queue', permission: 'media.view' },
  { id: 'quality', label: 'Quality control', permission: 'media.quality.review' },
  { id: 'tracks', label: 'Audio and subtitles', permission: 'media.view' },
  { id: 'storage', label: 'Storage', permission: 'media.storage.view' },
  { id: 'archive', label: 'Archive', permission: 'media.view' },
];

export function MediaManagementPage() {
  const { user } = useAuthContext();
  const permissions = useMemo(() => new Set(user?.permissions || []), [user?.permissions]);
  const visibleTabs = TABS.filter(({ id, permission }) => permissions.has(permission) && (id !== 'quality' || permissions.has('media.quality.review')));
  const [tab, setTab] = useState('overview');

  const activeTab = visibleTabs.some((item) => item.id === tab) ? tab : visibleTabs[0]?.id;
  const canRetry = permissions.has('media.transcode.retry');
  const canArchive = permissions.has('media.delete');
  const canManageProcessing = permissions.has('media.processing.manage');
  const canRestore = permissions.has('media.archive.restore');

  const content = {
    overview: <MediaOverview />,
    library: <MediaLibrary canDelete={canArchive} canRetry={canRetry} />,
    processing: <MediaProcessingQueue canManage={canManageProcessing} />,
    quality: <MediaQualityQueue />,
    tracks: <AudioSubtitleAssets />,
    storage: <MediaStoragePage />,
    archive: <MediaArchivePage canRestore={canRestore} />,
  };

  if (!visibleTabs.length) {
    return <DashboardContent><Alert severity="error">Your account does not have permission to view media operations.</Alert></DashboardContent>;
  }

  return (
    <DashboardContent>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4">Media operations</Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mt: 0.75 }}>
          Manage private media assets, processing, quality review, storage, and recovery.
        </Typography>
      </Box>
      <Tabs
        value={activeTab}
        onChange={(_, value) => setTab(value)}
        variant="scrollable"
        allowScrollButtonsMobile
        sx={{ mb: 3, borderBottom: 1, borderColor: 'divider' }}
      >
        {visibleTabs.map(({ id, label }) => <Tab key={id} value={id} label={label} />)}
      </Tabs>
      {permissions.has('media.upload') && (
        <Box sx={{ display: activeTab === 'uploads' ? 'block' : 'none' }}>
          <MediaUploadManager />
        </Box>
      )}
      {activeTab !== 'uploads' && content[activeTab]}
    </DashboardContent>
  );
}

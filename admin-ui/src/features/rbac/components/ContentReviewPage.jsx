'use client';

import { useCallback, useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { hasPermission } from '../permissions';
import { rbacApi } from '../api/rbac.api';
import { RbacPageHeader } from './RbacPageHeader';

export function ContentReviewPage() {
  const { user } = useAuthContext();
  const [status, setStatus] = useState('in_review');
  const [rows, setRows] = useState([]);
  const [contentId, setContentId] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const canSubmit = hasPermission(user, 'content.submit');
  const canReview = hasPermission(user, 'content.review');
  const canApprove = hasPermission(user, 'content.approve');
  const canReject = hasPermission(user, 'content.reject');
  const canPublish = hasPermission(user, 'content.publish');
  const activeStatus = canReview ? (status === 'approved' && !canPublish ? 'in_review' : status) : 'approved';

  const load = useCallback(async () => {
    if (!(activeStatus === 'approved' ? canPublish : canReview)) {
      return null;
    }
    return rbacApi.contentReviewQueue({ status: activeStatus, per_page: 50 });
  }, [activeStatus, canPublish, canReview]);

  useEffect(() => {
    if (!(activeStatus === 'approved' ? canPublish : canReview)) return undefined;

    let active = true;
    load()
      .then((response) => {
        if (!active || !response) return;
        setRows(response.data || []);
        setError('');
      })
      .catch((loadError) => {
        if (active) setError(loadError.response?.data?.message || 'The content workflow could not be loaded.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [activeStatus, canPublish, canReview, load]);

  const run = async (operation, successMessage) => {
    setLoading(true);
    setError('');
    setMessage('');
    try {
      await operation();
      setMessage(successMessage);
      const response = await load();
      if (response) setRows(response.data || []);
      setLoading(false);
    } catch (actionError) {
      setError(actionError.response?.data?.message || 'The workflow action could not be completed.');
      setLoading(false);
    }
  };

  return (
    <DashboardContent>
      <RbacPageHeader title="Content approval" description="Submit catalog items for review, keep reviewer decisions separate from publishing, and track each revision." />
      <Stack spacing={2}>
        {error && <Alert severity="error">{error}</Alert>}
        {message && <Alert severity="success">{message}</Alert>}
        {canSubmit && (
          <Card sx={{ p: 2 }}>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <TextField size="small" label="Catalog content ID" value={contentId} onChange={(event) => setContentId(event.target.value)} />
              <Button variant="contained" disabled={!contentId.trim()} onClick={() => run(async () => { await rbacApi.submitContent(contentId.trim()); setContentId(''); }, 'Content submitted for review.')}>Submit revision</Button>
            </Stack>
          </Card>
        )}
        {(canReview || canPublish) && (
          <Card>
            <Tabs
              value={activeStatus}
              variant="standard"
              onChange={(_, value) => {
                setLoading(true);
                setError('');
                setStatus(value);
              }}
              aria-label="Content review status"
            >
              {canReview && <Tab value="in_review" label="Needs review" />}
              {canPublish && <Tab value="approved" label="Approved" />}
            </Tabs>
            <TableContainer>
              <Table>
                <TableHead><TableRow><TableCell>Content</TableCell><TableCell>Type</TableCell><TableCell>Revision</TableCell><TableCell>Submitted (UTC)</TableCell><TableCell>Status</TableCell><TableCell>Actions</TableCell></TableRow></TableHead>
                <TableBody>
                  {rows.map((row) => (
                    <TableRow key={row.id} hover>
                      <TableCell><Typography variant="subtitle2">{row.content?.name || `Catalog item ${row.content_id}`}</Typography><Typography variant="caption" color="text.secondary">ID {row.content_id}</Typography></TableCell>
                      <TableCell>{row.content?.type || '—'}</TableCell>
                      <TableCell>#{row.revision}</TableCell>
                      <TableCell>{row.submitted_at ? new Date(row.submitted_at).toLocaleString(undefined, { timeZone: 'UTC' }) : '—'}</TableCell>
                      <TableCell><Chip size="small" color={row.status === 'approved' ? 'success' : 'warning'} label={row.status} /></TableCell>
                      <TableCell>
                        <Stack direction="row" spacing={1}>
                          {row.status === 'in_review' && canApprove && <Button size="small" onClick={() => run(() => rbacApi.approveContent(row.id, {}), 'Revision approved.')}>Approve</Button>}
                          {row.status === 'in_review' && canReject && <Button size="small" color="error" onClick={() => { const note = window.prompt('Reason for rejection (required):'); if (note?.trim()) run(() => rbacApi.rejectContent(row.id, { note: note.trim() }), 'Revision rejected with feedback.'); }}>Reject</Button>}
                          {row.status === 'approved' && canPublish && <Button size="small" variant="contained" onClick={() => run(() => rbacApi.publishContent(row.id), 'Approved content published.')}>Publish</Button>}
                        </Stack>
                      </TableCell>
                    </TableRow>
                  ))}
                  {!loading && rows.length === 0 && <TableRow><TableCell colSpan={6} align="center">No submissions in this queue.</TableCell></TableRow>}
                  {loading && <TableRow><TableCell colSpan={6} align="center">Loading submissions…</TableCell></TableRow>}
                </TableBody>
              </Table>
            </TableContainer>
          </Card>
        )}
      </Stack>
    </DashboardContent>
  );
}

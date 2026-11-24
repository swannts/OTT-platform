'use client';

import { useCallback, useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TablePagination from '@mui/material/TablePagination';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { Iconify } from 'src/components/iconify';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { hasPermission } from '../permissions';
import { rbacApi } from '../api/rbac.api';
import { RbacPageHeader } from './RbacPageHeader';

export function AuditLogPage() {
  const { user } = useAuthContext();
  const [rows, setRows] = useState([]);
  const [filters, setFilters] = useState({
    actor_id: '',
    action: '',
    resource_id: '',
    from: '',
    to: '',
  });
  const [page, setPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    return rbacApi.listAuditLogs({ ...filters, page: page + 1, per_page: 25 });
  }, [filters, page]);

  useEffect(() => {
    let active = true;
    load()
      .then((response) => {
        if (!active) return;
        setRows(response.data || []);
        setTotal(response.meta?.total || 0);
        setError('');
      })
      .catch((loadError) => {
        if (active) setError(loadError.message || 'Audit records could not be loaded.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [load]);

  const updateFilter = (name, value) => {
    setLoading(true);
    setError('');
    setFilters((current) => ({ ...current, [name]: value }));
    setPage(0);
  };
  const exportUrl = rbacApi.auditExportUrl(
    Object.fromEntries(Object.entries(filters).filter(([, value]) => value))
  );

  return (
    <DashboardContent>
      <RbacPageHeader
        title="Administrative audit log"
        description="Review recorded role, permission, invitation, account and session changes. Audit records are read-only."
        action={
          hasPermission(user, 'audit.export') ? (
            <Button
              component="a"
              href={exportUrl}
              variant="outlined"
              startIcon={<Iconify icon="solar:download-outline" />}
            >
              Export CSV
            </Button>
          ) : null
        }
      />
      <Stack spacing={2}>
        {error && <Alert severity="error">{error}</Alert>}
        <Card>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ p: 2 }}>
            <TextField
              size="small"
              type="number"
              label="Actor ID"
              value={filters.actor_id}
              onChange={(event) => updateFilter('actor_id', event.target.value)}
            />
            <TextField
              size="small"
              label="Action"
              value={filters.action}
              onChange={(event) => updateFilter('action', event.target.value)}
            />
            <TextField
              size="small"
              label="Resource ID"
              value={filters.resource_id}
              onChange={(event) => updateFilter('resource_id', event.target.value)}
            />
            <TextField
              size="small"
              type="date"
              label="From"
              InputLabelProps={{ shrink: true }}
              value={filters.from}
              onChange={(event) => updateFilter('from', event.target.value)}
            />
            <TextField
              size="small"
              type="date"
              label="To"
              InputLabelProps={{ shrink: true }}
              value={filters.to}
              onChange={(event) => updateFilter('to', event.target.value)}
            />
          </Stack>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Time (UTC)</TableCell>
                  <TableCell>Actor</TableCell>
                  <TableCell>Action</TableCell>
                  <TableCell>Resource</TableCell>
                  <TableCell>Outcome</TableCell>
                  <TableCell>Change</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.id} hover>
                    <TableCell>
                      {row.created_at
                        ? new Date(row.created_at).toLocaleString(undefined, { timeZone: 'UTC' })
                        : '—'}
                    </TableCell>
                    <TableCell>{row.actor?.name || row.actor_id || 'System'}</TableCell>
                    <TableCell>
                      <Typography variant="body2" sx={{ fontFamily: 'monospace' }}>
                        {row.action}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      {row.resource_type?.split('\\').pop() || '—'}{' '}
                      {row.resource_id ? `#${row.resource_id}` : ''}
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        color={row.outcome === 'success' ? 'success' : 'error'}
                        label={row.outcome}
                      />
                    </TableCell>
                    <TableCell>
                      <Typography
                        variant="caption"
                        component="pre"
                        sx={{ maxWidth: 360, overflow: 'auto', m: 0 }}
                      >
                        {JSON.stringify(row.after || {}, null, 2)}
                      </Typography>
                    </TableCell>
                  </TableRow>
                ))}
                {!loading && rows.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} align="center">
                      No audit records match these filters.
                    </TableCell>
                  </TableRow>
                )}
                {loading && (
                  <TableRow>
                    <TableCell colSpan={6} align="center">
                      Loading audit records…
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
          <TablePagination
            component="div"
            count={total}
            page={page}
            onPageChange={(_, nextPage) => {
              setLoading(true);
              setError('');
              setPage(nextPage);
            }}
            rowsPerPage={25}
            rowsPerPageOptions={[25]}
          />
        </Card>
      </Stack>
    </DashboardContent>
  );
}

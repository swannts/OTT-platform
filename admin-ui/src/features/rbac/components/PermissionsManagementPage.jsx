'use client';

import { useEffect, useMemo, useState } from 'react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import Chip from '@mui/material/Chip';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

import { DashboardContent } from 'src/layouts/dashboard';
import { rbacApi } from '../api/rbac.api';
import { RbacPageHeader } from './RbacPageHeader';

export function PermissionsManagementPage() {
  const [permissions, setPermissions] = useState([]);
  const [search, setSearch] = useState('');
  const [module, setModule] = useState('all');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    setLoading(true);
    rbacApi
      .listPermissions({ search, module: module === 'all' ? undefined : module })
      .then((response) => {
        if (active) setPermissions(response.data || []);
      })
      .catch((loadError) => {
        if (active) setError(loadError.message || 'Permissions could not be loaded.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [module, search]);

  const modules = useMemo(
    () => [...new Set(permissions.map((permission) => permission.module))],
    [permissions]
  );

  return (
    <DashboardContent>
      <RbacPageHeader
        title="Permission catalog"
        description="Recognized permissions are defined by the backend registry. New arbitrary permission strings cannot be created here."
      />
      <Stack spacing={2}>
        {error && <Alert severity="error">{error}</Alert>}
        <Card>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ p: 2 }}>
            <TextField
              size="small"
              label="Search permissions"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
            <Select
              size="small"
              value={module}
              aria-label="Filter by module"
              onChange={(event) => setModule(event.target.value)}
            >
              <MenuItem value="all">All modules</MenuItem>
              {modules.map((name) => (
                <MenuItem key={name} value={name}>
                  {name}
                </MenuItem>
              ))}
            </Select>
          </Stack>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Permission</TableCell>
                  <TableCell>Description</TableCell>
                  <TableCell>Module</TableCell>
                  <TableCell>Assigned roles</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {permissions.map((permission) => (
                  <TableRow key={permission.name} hover>
                    <TableCell>
                      <Typography variant="subtitle2" sx={{ fontFamily: 'monospace' }}>
                        {permission.name}
                      </Typography>
                    </TableCell>
                    <TableCell>{permission.description}</TableCell>
                    <TableCell>
                      <Chip size="small" label={permission.module} />
                    </TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={0.5} useFlexGap flexWrap="wrap">
                        {permission.roles.map((role) => (
                          <Chip key={role.id} size="small" variant="outlined" label={role.title} />
                        ))}
                      </Stack>
                    </TableCell>
                  </TableRow>
                ))}
                {!loading && permissions.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={4} align="center">
                      No permissions match your filters.
                    </TableCell>
                  </TableRow>
                )}
                {loading && (
                  <TableRow>
                    <TableCell colSpan={4} align="center">
                      Loading permission catalog…
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Card>
      </Stack>
    </DashboardContent>
  );
}

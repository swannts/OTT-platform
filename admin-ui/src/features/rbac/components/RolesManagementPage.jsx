'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Checkbox from '@mui/material/Checkbox';
import Chip from '@mui/material/Chip';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import FormControlLabel from '@mui/material/FormControlLabel';
import IconButton from '@mui/material/IconButton';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
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

const emptyForm = { title: '', description: '', is_active: true, permissions: [] };

export function RolesManagementPage() {
  const { user, checkUserSession } = useAuthContext();
  const [roles, setRoles] = useState([]);
  const [permissionGroups, setPermissionGroups] = useState({});
  const [search, setSearch] = useState('');
  const [scope, setScope] = useState('all');
  const [page, setPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [dialog, setDialog] = useState(false);
  const [history, setHistory] = useState(null);
  const [selected, setSelected] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);

  const canCreateRoles = hasPermission(user, 'admin.roles.create');
  const canUpdateRoles = hasPermission(user, 'admin.roles.update');
  const canManagePermissions = hasPermission(user, 'admin.permissions.manage');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [roleResponse, permissionResponse] = await Promise.all([
        rbacApi.listRoles({
          search,
          scope: scope === 'all' ? undefined : scope,
          page: page + 1,
          per_page: 25,
        }),
        hasPermission(user, 'admin.permissions.view')
          ? rbacApi.listPermissions()
          : Promise.resolve({ groups: {} }),
      ]);
      setRoles(roleResponse.data || []);
      setTotal(roleResponse.meta?.total || 0);
      setPermissionGroups(permissionResponse.groups || {});
    } catch (loadError) {
      setError(loadError.message || 'Roles could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [page, scope, search, user]);

  useEffect(() => {
    load();
  }, [load]);

  const permissionOptions = useMemo(() => Object.entries(permissionGroups), [permissionGroups]);

  const startCreate = () => {
    setSelected(null);
    setForm(emptyForm);
    setDialog(true);
  };

  const startEdit = (role) => {
    setSelected(role);
    setForm({
      title: role.title,
      description: role.description || '',
      is_active: role.is_active,
      permissions: role.permissions || [],
    });
    setDialog(true);
  };

  const togglePermission = (name) =>
    setForm((current) => ({
      ...current,
      permissions: current.permissions.includes(name)
        ? current.permissions.filter((permission) => permission !== name)
        : [...current.permissions, name],
    }));

  const toggleModule = (items) => {
    const names = items.map((permission) => permission.name);
    const allSelected = names.every((name) => form.permissions.includes(name));
    setForm((current) => ({
      ...current,
      permissions: allSelected
        ? current.permissions.filter((name) => !names.includes(name))
        : [...new Set([...current.permissions, ...names])],
    }));
  };

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        title: form.title,
        description: form.description,
        is_active: form.is_active,
      };
      if (canManagePermissions) payload.permissions = form.permissions;
      if (selected) await rbacApi.updateRole(selected.id, payload);
      else await rbacApi.createRole(payload);
      setDialog(false);
      setNotice(selected ? 'Role updated.' : 'Role created.');
      await load();
      await checkUserSession();
    } catch (saveError) {
      setError(saveError.message || 'The role could not be saved.');
    } finally {
      setSaving(false);
    }
  };

  const removeRole = async (role) => {
    if (!window.confirm(`Delete the custom role “${role.title}”?`)) return;
    try {
      await rbacApi.deleteRole(role.id);
      setNotice('Role deleted.');
      await load();
    } catch (deleteError) {
      setError(deleteError.message || 'The role could not be deleted.');
    }
  };

  const showHistory = async (role) => {
    try {
      setHistory(await rbacApi.getRole(role.id));
    } catch (historyError) {
      setError(historyError.message || 'Role details could not be loaded.');
    }
  };

  return (
    <DashboardContent>
      <RbacPageHeader
        title="Administrative roles"
        description="Review staff roles and manage custom role permissions. System roles are protected."
        action={
          canCreateRoles ? (
            <Button
              variant="contained"
              startIcon={<Iconify icon="solar:add-circle-outline" />}
              onClick={startCreate}
            >
              Create role
            </Button>
          ) : null
        }
      />
      <Stack spacing={2}>
        {error && (
          <Alert severity="error" onClose={() => setError('')}>
            {error}
          </Alert>
        )}
        {notice && (
          <Alert severity="success" onClose={() => setNotice('')}>
            {notice}
          </Alert>
        )}
        <Card>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ p: 2 }}>
            <TextField
              label="Search roles"
              size="small"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(0);
              }}
            />
            <Select
              size="small"
              value={scope}
              onChange={(event) => {
                setScope(event.target.value);
                setPage(0);
              }}
              aria-label="Filter roles"
            >
              <MenuItem value="all">All roles</MenuItem>
              <MenuItem value="system">System roles</MenuItem>
              <MenuItem value="custom">Custom roles</MenuItem>
            </Select>
          </Stack>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Role</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>Permissions</TableCell>
                  <TableCell>Assigned staff</TableCell>
                  <TableCell align="right">Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {roles.map((role) => (
                  <TableRow key={role.id} hover>
                    <TableCell>
                      <Typography variant="subtitle2">{role.title}</Typography>
                      <Typography variant="caption" color="text.secondary">
                        {role.name}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={1}>
                        <Chip
                          size="small"
                          label={role.is_system ? 'System' : 'Custom'}
                          variant="outlined"
                        />
                        <Chip
                          size="small"
                          color={role.is_active ? 'success' : 'default'}
                          label={role.is_active ? 'Active' : 'Inactive'}
                        />
                      </Stack>
                    </TableCell>
                    <TableCell>{role.permissions?.length || 0}</TableCell>
                    <TableCell>{role.staff_count}</TableCell>
                    <TableCell align="right">
                      <IconButton
                        aria-label={`View ${role.title} history`}
                        onClick={() => showHistory(role)}
                      >
                        <Iconify icon="solar:eye-outline" />
                      </IconButton>
                      {canUpdateRoles && !role.is_system && (
                        <IconButton
                          aria-label={`Edit ${role.title}`}
                          onClick={() => startEdit(role)}
                        >
                          <Iconify icon="solar:pen-outline" />
                        </IconButton>
                      )}
                      {hasPermission(user, 'admin.roles.delete') && !role.is_system && (
                        <IconButton
                          aria-label={`Delete ${role.title}`}
                          color="error"
                          onClick={() => removeRole(role)}
                        >
                          <Iconify icon="solar:trash-bin-trash-outline" />
                        </IconButton>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
                {!loading && roles.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={5} align="center">
                      No roles match your filters.
                    </TableCell>
                  </TableRow>
                )}
                {loading && (
                  <TableRow>
                    <TableCell colSpan={5} align="center">
                      Loading roles…
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
            onPageChange={(_, nextPage) => setPage(nextPage)}
            rowsPerPage={25}
            rowsPerPageOptions={[25]}
          />
        </Card>
      </Stack>

      <Dialog open={dialog} onClose={() => setDialog(false)} fullWidth maxWidth="md">
        <Box component="form" onSubmit={save}>
          <DialogTitle>{selected ? 'Edit custom role' : 'Create custom role'}</DialogTitle>
          <DialogContent dividers>
            <Stack spacing={2.5}>
              <TextField
                required
                label="Role name"
                value={form.title}
                onChange={(event) => setForm({ ...form, title: event.target.value })}
                inputProps={{ maxLength: 191 }}
              />
              <TextField
                multiline
                minRows={2}
                label="Description"
                value={form.description}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
              />
              <FormControlLabel
                control={
                  <Checkbox
                    checked={form.is_active}
                    onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
                  />
                }
                label="Role is active"
              />
              {!canManagePermissions && (
                <Alert severity="info">
                  Your access does not include permission assignment. This role will have no
                  additional permissions.
                </Alert>
              )}
              {permissionOptions.map(([module, items]) => (
                <Box key={module} sx={{ p: 2, border: 1, borderColor: 'divider', borderRadius: 1 }}>
                  <FormControlLabel
                    control={
                      <Checkbox
                        checked={items.every((item) => form.permissions.includes(item.name))}
                        indeterminate={
                          items.some((item) => form.permissions.includes(item.name)) &&
                          !items.every((item) => form.permissions.includes(item.name))
                        }
                        disabled={!canManagePermissions}
                        onChange={() => toggleModule(items)}
                      />
                    }
                    label={
                      <Typography variant="subtitle2" sx={{ textTransform: 'capitalize' }}>
                        {module}
                      </Typography>
                    }
                  />
                  <Box
                    sx={{
                      display: 'grid',
                      gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)' },
                      pl: 2,
                    }}
                  >
                    {items.map((permission) => (
                      <FormControlLabel
                        key={permission.name}
                        control={
                          <Checkbox
                            size="small"
                            disabled={!canManagePermissions}
                            checked={form.permissions.includes(permission.name)}
                            onChange={() => togglePermission(permission.name)}
                          />
                        }
                        label={
                          <Box>
                            <Typography variant="body2">{permission.name}</Typography>
                            <Typography variant="caption" color="text.secondary">
                              {permission.description}
                            </Typography>
                          </Box>
                        }
                      />
                    ))}
                  </Box>
                </Box>
              ))}
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialog(false)}>Cancel</Button>
            <Button type="submit" variant="contained" disabled={saving}>
              {saving ? 'Saving…' : 'Save role'}
            </Button>
          </DialogActions>
        </Box>
      </Dialog>

      <Dialog open={Boolean(history)} onClose={() => setHistory(null)} fullWidth maxWidth="md">
        <DialogTitle>{history?.data?.title} details</DialogTitle>
        <DialogContent dividers>
          <Typography variant="subtitle2">
            Assigned staff ({history?.staff?.length || 0})
          </Typography>
          <Stack spacing={1} sx={{ my: 1 }}>
            {history?.staff?.map((person) => (
              <Typography key={person.id} variant="body2">
                {person.first_name} {person.last_name} · {person.email}
              </Typography>
            ))}
          </Stack>
          <Typography variant="subtitle2" sx={{ mt: 3 }}>
            Recent role history
          </Typography>
          <Stack spacing={1} sx={{ mt: 1 }}>
            {history?.history?.length ? (
              history.history.map((entry) => (
                <Typography key={entry.id} variant="body2">
                  {entry.action} ·{' '}
                  {new Date(entry.created_at).toLocaleString(undefined, { timeZone: 'UTC' })}
                </Typography>
              ))
            ) : (
              <Typography variant="body2" color="text.secondary">
                No recorded changes yet.
              </Typography>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setHistory(null)}>Close</Button>
        </DialogActions>
      </Dialog>
    </DashboardContent>
  );
}

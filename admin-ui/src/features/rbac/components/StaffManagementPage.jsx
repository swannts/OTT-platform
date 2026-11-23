'use client';

import { useCallback, useEffect, useState } from 'react';
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
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TablePagination from '@mui/material/TablePagination';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import { Iconify } from 'src/components/iconify';

import { DashboardContent } from 'src/layouts/dashboard';
import { useAuthContext } from 'src/auth/hooks';
import { rbacApi } from '../api/rbac.api';
import { hasPermission } from '../permissions';
import { RbacPageHeader } from './RbacPageHeader';

export function StaffManagementPage() {
  const { user } = useAuthContext();
  const [staff, setStaff] = useState([]);
  const [roles, setRoles] = useState([]);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [inviteOpen, setInviteOpen] = useState(false);
  const [roleEditor, setRoleEditor] = useState(null);
  const [permissionViewer, setPermissionViewer] = useState(null);
  const [invite, setInvite] = useState({ email: '', role_names: [], current_password: '' });
  const [roleNames, setRoleNames] = useState([]);
  const [currentPassword, setCurrentPassword] = useState('');
  const [permissionList, setPermissionList] = useState([]);

  const canInvite = hasPermission(user, 'admin.staff.invite');
  const canUpdateRoles = hasPermission(user, 'admin.staff.roles.update');
  const canUpdateStatus = hasPermission(user, 'admin.staff.status.update');
  const canRevokeSessions = hasPermission(user, 'admin.staff.sessions.revoke');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [staffResponse, roleResponse] = await Promise.all([
        rbacApi.listStaff({ search, page: page + 1, per_page: 25 }),
        hasPermission(user, 'admin.roles.view')
          ? rbacApi.listRoles({ per_page: 100, active: true })
          : Promise.resolve({ data: [] }),
      ]);
      setStaff(staffResponse.data || []);
      setTotal(staffResponse.meta?.total || 0);
      setRoles((roleResponse.data || []).filter((role) => role.is_active));
    } catch (loadError) {
      setError(loadError.message || 'Staff could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [page, search, user]);

  useEffect(() => {
    load();
  }, [load]);

  const openRoleEditor = (person) => {
    setRoleEditor(person);
    setRoleNames(person.roles.map((role) => role.name));
    setCurrentPassword('');
  };

  const changeRole = (name, checked) =>
    setRoleNames((current) =>
      checked ? [...new Set([...current, name])] : current.filter((role) => role !== name)
    );

  const submitInvite = async (event) => {
    event.preventDefault();
    setError('');
    try {
      await rbacApi.inviteStaff(invite);
      setInviteOpen(false);
      setInvite({ email: '', role_names: [], current_password: '' });
      setNotice('Invitation sent. It expires after 48 hours.');
    } catch (inviteError) {
      setError(inviteError.message || 'The invitation could not be sent.');
    }
  };

  const saveRoles = async (event) => {
    event.preventDefault();
    try {
      await rbacApi.updateStaffRoles(roleEditor.id, {
        role_names: roleNames,
        current_password: currentPassword || undefined,
      });
      setRoleEditor(null);
      setNotice('Staff roles updated.');
      await load();
    } catch (roleError) {
      setError(roleError.message || 'Staff roles could not be updated.');
    }
  };

  const updateStatus = async (person) => {
    try {
      await rbacApi.updateStaffStatus(person.id, { active: !person.active });
      setNotice(person.active ? 'Staff account deactivated.' : 'Staff account activated.');
      await load();
    } catch (statusError) {
      setError(statusError.message || 'Staff status could not be changed.');
    }
  };

  const revokeSessions = async (person) => {
    if (!window.confirm(`Revoke all sessions for ${person.name}?`)) return;
    try {
      await rbacApi.revokeStaffSessions(person.id);
      setNotice('Staff sessions revoked.');
    } catch (sessionError) {
      setError(sessionError.message || 'Sessions could not be revoked.');
    }
  };

  const showPermissions = async (person) => {
    try {
      const response = await rbacApi.getStaffPermissions(person.id);
      setPermissionViewer({ person, permissions: response.data || [] });
    } catch (permissionError) {
      setError(permissionError.message || 'Effective permissions could not be loaded.');
    }
  };

  const inviteHasSuperAdmin = invite.role_names.includes('super-admin');
  const editorHasSuperAdmin =
    roleNames.includes('super-admin') ||
    roleEditor?.roles?.some((role) => role.name === 'super-admin');

  return (
    <DashboardContent>
      <RbacPageHeader
        title="Staff access"
        description="Invite staff, review effective access, and control administrative sessions. Customer accounts are excluded."
        action={
          canInvite ? (
            <Button
              variant="contained"
              startIcon={<Iconify icon="solar:user-plus-outline" />}
              onClick={() => setInviteOpen(true)}
            >
              Invite staff
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
          <Box sx={{ p: 2 }}>
            <TextField
              size="small"
              label="Search staff"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(0);
              }}
            />
          </Box>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Staff member</TableCell>
                  <TableCell>Roles</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell align="right">Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {staff.map((person) => (
                  <TableRow key={person.id} hover>
                    <TableCell>
                      <Box sx={{ fontWeight: 600 }}>{person.name}</Box>
                      <Box sx={{ color: 'text.secondary', typography: 'caption' }}>
                        {person.email}
                      </Box>
                    </TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={0.5} useFlexGap flexWrap="wrap">
                        {person.roles.map((role) => (
                          <Chip key={role.name} size="small" label={role.title} />
                        ))}
                      </Stack>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        label={person.active ? 'Active' : 'Inactive'}
                        color={person.active ? 'success' : 'default'}
                      />
                    </TableCell>
                    <TableCell align="right">
                      <IconButton
                        aria-label={`View effective permissions for ${person.name}`}
                        onClick={() => showPermissions(person)}
                      >
                        <Iconify icon="solar:shield-keyhole-outline" />
                      </IconButton>
                      {canUpdateRoles && (
                        <Button size="small" onClick={() => openRoleEditor(person)}>
                          Roles
                        </Button>
                      )}
                      {canUpdateStatus && (
                        <Button
                          size="small"
                          color={person.active ? 'warning' : 'success'}
                          onClick={() => updateStatus(person)}
                        >
                          {person.active ? 'Deactivate' : 'Activate'}
                        </Button>
                      )}
                      {canRevokeSessions && (
                        <Button size="small" color="error" onClick={() => revokeSessions(person)}>
                          Revoke sessions
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
                {!loading && staff.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={4} align="center">
                      No staff accounts found.
                    </TableCell>
                  </TableRow>
                )}
                {loading && (
                  <TableRow>
                    <TableCell colSpan={4} align="center">
                      Loading staff…
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

      <Dialog open={inviteOpen} onClose={() => setInviteOpen(false)} fullWidth maxWidth="sm">
        <Box component="form" onSubmit={submitInvite}>
          <DialogTitle>Invite staff member</DialogTitle>
          <DialogContent dividers>
            <Stack spacing={2}>
              <TextField
                required
                type="email"
                label="Work email"
                value={invite.email}
                onChange={(event) => setInvite({ ...invite, email: event.target.value })}
              />
              <Box>
                <Box sx={{ mb: 1, typography: 'subtitle2' }}>Assign roles</Box>
                {roles.map((role) => (
                  <FormControlLabel
                    key={role.name}
                    control={
                      <Checkbox
                        checked={invite.role_names.includes(role.name)}
                        onChange={(event) =>
                          setInvite({
                            ...invite,
                            role_names: event.target.checked
                              ? [...invite.role_names, role.name]
                              : invite.role_names.filter((name) => name !== role.name),
                          })
                        }
                      />
                    }
                    label={role.title}
                  />
                ))}
              </Box>
              {inviteHasSuperAdmin && (
                <TextField
                  required
                  type="password"
                  label="Re-enter your password to grant Super Admin"
                  value={invite.current_password}
                  onChange={(event) =>
                    setInvite({ ...invite, current_password: event.target.value })
                  }
                />
              )}
              <Alert severity="info">
                The invitation link is single-use and expires in 48 hours. The account is created
                only after the invitee sets a password.
              </Alert>
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setInviteOpen(false)}>Cancel</Button>
            <Button
              type="submit"
              variant="contained"
              disabled={!invite.email || invite.role_names.length === 0}
            >
              Send invitation
            </Button>
          </DialogActions>
        </Box>
      </Dialog>

      <Dialog
        open={Boolean(roleEditor)}
        onClose={() => setRoleEditor(null)}
        fullWidth
        maxWidth="sm"
      >
        <Box component="form" onSubmit={saveRoles}>
          <DialogTitle>Administrative roles</DialogTitle>
          <DialogContent dividers>
            <Stack spacing={1}>
              {roles.map((role) => (
                <FormControlLabel
                  key={role.name}
                  control={
                    <Checkbox
                      checked={roleNames.includes(role.name)}
                      onChange={(event) => changeRole(role.name, event.target.checked)}
                    />
                  }
                  label={role.title}
                />
              ))}
            </Stack>
            {editorHasSuperAdmin && (
              <TextField
                required
                fullWidth
                sx={{ mt: 2 }}
                type="password"
                label="Re-enter your password for Super Admin access changes"
                value={currentPassword}
                onChange={(event) => setCurrentPassword(event.target.value)}
              />
            )}
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setRoleEditor(null)}>Cancel</Button>
            <Button type="submit" variant="contained">
              Save roles
            </Button>
          </DialogActions>
        </Box>
      </Dialog>

      <Dialog
        open={Boolean(permissionViewer)}
        onClose={() => setPermissionViewer(null)}
        fullWidth
        maxWidth="md"
      >
        <DialogTitle>Effective permissions · {permissionViewer?.person?.name}</DialogTitle>
        <DialogContent dividers>
          <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap">
            {permissionViewer?.permissions?.map((permission) => (
              <Chip
                key={permission.name}
                color={permission.direct ? 'primary' : 'default'}
                label={permission.name}
                title={permission.description}
              />
            ))}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPermissionViewer(null)}>Close</Button>
        </DialogActions>
      </Dialog>
    </DashboardContent>
  );
}

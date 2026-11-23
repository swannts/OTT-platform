<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\AdminStaffInvitationMail;
use App\Models\AdminInvitation;
use App\Models\Role;
use App\Models\User;
use App\Policies\AdminRolePolicy;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminInvitationController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit)
    {
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'admin.staff.invite'), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'role_names' => ['required', 'array', 'min:1', 'max:10'],
            'role_names.*' => ['required', 'string', 'distinct'],
            'current_password' => ['nullable', 'string', 'max:255'],
        ]);
        $email = mb_strtolower($data['email']);
        abort_if(User::query()->whereRaw('LOWER(email) = ?', [$email])->exists(), 409, 'This email already has an account.');

        $roles = Role::query()->where('guard_name', 'web')->where('is_admin_role', true)
            ->where('is_active', true)->whereIn('name', $data['role_names'])->get();
        abort_if($roles->count() !== count($data['role_names']), 422, 'One or more selected roles are unavailable.');
        foreach ($roles as $role) {
            abort_unless(app(AdminRolePolicy::class)->assign($request->user(), $role), 403);
        }

        if ($roles->contains(static fn (Role $role): bool => $role->name === 'super-admin')) {
            abort_unless(isset($data['current_password']) && Hash::check($data['current_password'], $request->user()->password), 403, 'Re-enter your password to invite a Super Admin.');
        }

        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($data, $email, $roles, $token, $request): AdminInvitation {
            AdminInvitation::query()->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->update(['revoked_at' => now()]);

            $invitation = AdminInvitation::query()->create([
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'role_names' => $roles->pluck('name')->values()->all(),
                'invited_by' => $request->user()->getKey(),
                'expires_at' => now()->addHours(48),
            ]);
            $this->audit->record($request->user(), 'staff.invitation.created', AdminInvitation::class, $invitation->getKey(), null, [
                'email' => $this->maskedEmail($email),
                'role_names' => $invitation->role_names,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ]);

            return $invitation;
        });

        $adminUiUrl = rtrim((string) config('services.admin_ui.url'), '/');
        if ($adminUiUrl === '') {
            $invitation->forceFill(['revoked_at' => now()])->save();
            abort(503, 'Staff invitation delivery is not configured.');
        }

        $invitationUrl = $adminUiUrl.'/admin/invitations/accept?token='.urlencode($token);
        try {
            Mail::to($email)->send(new AdminStaffInvitationMail($invitationUrl));
        } catch (\Throwable $exception) {
            $invitation->forceFill(['revoked_at' => now()])->save();
            $this->audit->record($request->user(), 'staff.invitation.delivery_failed', AdminInvitation::class, $invitation->getKey(), null, ['email' => $this->maskedEmail($email)], 'failure');
            report($exception);

            return response()->json(['message' => 'The invitation could not be delivered.'], 503);
        }

        return response()->json(['message' => 'Invitation sent.', 'expires_at' => $invitation->expires_at->toIso8601String()], 201);
    }

    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['nullable', 'string', 'max:191'],
            'password' => ['required', 'string', 'min:14', 'confirmed'],
        ]);

        $invitation = AdminInvitation::query()->where('token_hash', hash('sha256', $data['token']))
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($invitation, 422, 'This invitation is invalid or expired.');

        $user = DB::transaction(function () use ($data, $invitation): User {
            $lockedInvitation = AdminInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();
            abort_unless($lockedInvitation && !$lockedInvitation->accepted_at && !$lockedInvitation->revoked_at && $lockedInvitation->expires_at->isFuture(), 422, 'This invitation is invalid or expired.');
            abort_if(User::query()->whereRaw('LOWER(email) = ?', [$lockedInvitation->email])->exists(), 409, 'This email already has an account.');

            $roles = Role::query()->where('guard_name', 'web')->where('is_admin_role', true)
                ->where('is_active', true)->whereIn('name', $lockedInvitation->role_names)->lockForUpdate()->get();
            abort_if($roles->count() !== count($lockedInvitation->role_names), 422, 'A role in this invitation is no longer available. Request a new invitation.');

            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'username' => $lockedInvitation->email,
                'email' => $lockedInvitation->email,
                'password' => Hash::make($data['password']),
                'user_type' => 'admin',
                'status' => 1,
                'is_banned' => 0,
            ]);
            $user->syncRoles($roles);
            $lockedInvitation->forceFill(['accepted_at' => now()])->save();
            $this->audit->record($user, 'staff.invitation.accepted', User::class, $user->getKey(), null, ['role_names' => $roles->pluck('name')->all()]);

            return $user;
        });

        return response()->json(['message' => 'Invitation accepted. You can now sign in.', 'user_id' => $user->getKey()], 201);
    }

    private function maskedEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return ($local === '' ? '' : mb_substr($local, 0, 1).'***').'@'.$domain;
    }
}

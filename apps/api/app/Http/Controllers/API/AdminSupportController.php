<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AdminSupportController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->authorizeSupport($request, 'support.tickets.manage');
        $tickets = DB::table('support_tickets');
        $open = (clone $tickets)->whereNotIn('status', ['resolved', 'closed'])->count();
        $resolvedDurations = DB::table('support_tickets')->whereNotNull('resolved_at')
            ->selectRaw('TIMESTAMPDIFF(SECOND, created_at, resolved_at) as duration_seconds')->pluck('duration_seconds')->filter();
        $responseDurations = DB::table('support_tickets')->whereNotNull('first_response_at')
            ->selectRaw('TIMESTAMPDIFF(SECOND, created_at, first_response_at) as duration_seconds')->pluck('duration_seconds')->filter();

        return response()->json(['data' => [
            'open_tickets' => $open,
            'unassigned_tickets' => (clone $tickets)->whereNull('assigned_to')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'escalated_tickets' => (clone $tickets)->where('status', 'escalated')->count(),
            'resolved_last_7_days' => (clone $tickets)->where('status', 'resolved')->where('resolved_at', '>=', now()->subDays(7))->count(),
            'median_resolution_seconds' => $this->median($resolvedDurations->map(fn ($v) => (int) $v)->all()),
            'median_first_response_seconds' => $this->median($responseDurations->map(fn ($v) => (int) $v)->all()),
        ]]);
    }

    public function customers(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.accounts.view');
        $data = $request->validate(['search' => ['required', 'string', 'min:2', 'max:120'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);
        $term = trim($data['search']);
        $customers = User::query()->whereDoesntHave('roles')->where(function ($query) use ($term): void {
            if (ctype_digit($term)) {
                $query->orWhere('id', (int) $term);
            }
            $query->orWhere('email', 'like', '%'.$term.'%')->orWhere('username', 'like', '%'.$term.'%')
                ->orWhere('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%');
        })->orderBy('id')->paginate($data['per_page'] ?? 25, ['id', 'first_name', 'last_name', 'username', 'email', 'status', 'is_banned', 'created_at']);
        $customers->getCollection()->transform(fn (User $user): array => $this->customerSummary($user));
        $audit->record($request->user(), 'support.customer.search', 'customer_search', null, null, ['query_hash' => hash('sha256', mb_strtolower($term)), 'result_count' => $customers->count()]);

        return response()->json($customers);
    }

    public function customer(Request $request, int $customer, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.accounts.view');
        $user = User::query()->whereDoesntHave('roles')->select(['id', 'first_name', 'last_name', 'username', 'email', 'status', 'is_banned', 'is_subscribe', 'created_at', 'email_verified_at', 'last_notification_seen'])->findOrFail($customer);
        $data = ['customer' => $this->customerSummary($user) + ['email_verified_at' => $user->email_verified_at, 'last_notification_seen' => $user->last_notification_seen]];
        if (ExplicitPermission::allows($request->user(), 'subscriptions.view')) {
            $data['subscriptions'] = DB::table('subscriptions')->where('user_id', $user->id)->whereNull('deleted_at')->orderByDesc('id')
                ->limit(20)->get(['id', 'name', 'identifier', 'status', 'start_date', 'end_date', 'amount', 'level']);
        }
        $audit->record($request->user(), 'support.customer.viewed', 'customer', $customer, null, ['subscriptions_included' => array_key_exists('subscriptions', $data)]);

        return response()->json(['data' => $data]);
    }

    public function payments(Request $request, int $customer, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.payments.view');
        $this->findCustomer($customer);
        $payments = DB::table('verified_payments')->where('user_id', $customer)->orderByDesc('id')->limit(50)
            ->get(['id', 'gateway', 'provider_reference', 'purchase_type', 'purchase_id', 'amount_minor', 'currency', 'fulfilled_at', 'created_at'])
            ->map(fn ($payment): array => [
                'id' => (int) $payment->id, 'source' => 'verified_payment', 'gateway' => $payment->gateway,
                'reference' => $this->maskReference((string) $payment->provider_reference),
                'purchase_type' => $payment->purchase_type, 'purchase_id' => (int) $payment->purchase_id,
                'amount_minor' => (int) $payment->amount_minor, 'currency' => $payment->currency,
                'status' => $payment->fulfilled_at ? 'fulfilled' : 'verified', 'created_at' => $payment->created_at,
            ]);
        if (ExplicitPermission::allows($request->user(), 'subscriptions.view')) {
            $legacyPayments = DB::table('subscriptions_transactions')->where('user_id', $customer)->whereNull('deleted_at')->orderByDesc('id')->limit(50)
                ->get(['id', 'subscriptions_id', 'payment_type', 'transaction_id', 'amount', 'currency_code', 'payment_status', 'created_at'])
                ->map(fn ($payment): array => [
                    'id' => (int) $payment->id, 'source' => 'subscription_transaction', 'gateway' => $payment->payment_type ?: 'unknown',
                    'reference' => $payment->transaction_id ? $this->maskReference((string) $payment->transaction_id) : null,
                    'purchase_type' => 'subscription', 'purchase_id' => $payment->subscriptions_id,
                    'amount_minor' => (int) round(((float) $payment->amount) * 100), 'currency' => $payment->currency_code ?: 'USD',
                    'status' => $payment->payment_status ?: 'unknown', 'created_at' => $payment->created_at,
                ]);
            $payments = $payments->concat($legacyPayments)->sortByDesc('created_at')->values();
        }
        $audit->record($request->user(), 'support.customer.payments_viewed', 'customer', $customer, null, ['payment_count' => $payments->count(), 'masked' => true]);

        return response()->json(['data' => $payments]);
    }

    public function devices(Request $request, int $customer, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.devices.view');
        $this->findCustomer($customer);
        $devices = Device::query()->where('user_id', $customer)->orderByDesc('last_activity')->limit(100)
            ->get(['id', 'device_id', 'device_name', 'platform', 'active_profile', 'session_id', 'last_activity', 'created_at'])
            ->map(fn (Device $device): array => [
                'id' => (int) $device->id, 'device_name' => $device->device_name, 'platform' => $device->platform,
                'active_profile' => $device->active_profile, 'session_active' => (bool) $device->session_id,
                'last_activity' => $device->last_activity, 'created_at' => $device->created_at,
            ]);
        $audit->record($request->user(), 'support.customer.devices_viewed', 'customer', $customer, null, ['device_count' => $devices->count()]);

        return response()->json(['data' => $devices]);
    }

    public function playback(Request $request, int $customer, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.playback.view');
        $this->findCustomer($customer);
        $sessions = DB::table('playback_sessions')->where('user_id', $customer)->orderByDesc('updated_at')->limit(50)
            ->get(['id', 'session_id', 'profile_id', 'content_id', 'content_type', 'last_heartbeat_at', 'expires_at', 'ended_at']);
        $events = DB::table('playback_diagnostic_events')->where('user_id', $customer)->orderByDesc('occurred_at')->limit(100)
            ->get(['id', 'session_id', 'event_code', 'content_type', 'content_id', 'occurred_at']);
        $data = ['sessions' => $sessions->map(fn ($s) => [
            'id' => (int) $s->id,
            'session_reference' => substr((string) $s->session_id, 0, 8), 'profile_id' => (int) $s->profile_id,
            'content_id' => $s->content_id, 'content_type' => $s->content_type, 'last_heartbeat_at' => $s->last_heartbeat_at,
            'expires_at' => $s->expires_at, 'ended_at' => $s->ended_at,
        ]), 'events' => $events->map(fn ($e) => [
            'id' => (int) $e->id, 'session_reference' => $e->session_id ? substr((string) $e->session_id, 0, 8) : null,
            'event_code' => $e->event_code, 'content_type' => $e->content_type, 'content_id' => $e->content_id, 'occurred_at' => $e->occurred_at,
        ])];
        $audit->record($request->user(), 'support.customer.playback_viewed', 'customer', $customer, null, ['session_count' => $sessions->count(), 'event_count' => $events->count()]);

        return response()->json(['data' => $data]);
    }

    public function revokePlaybackSession(Request $request, int $customer, int $session, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.sessions.revoke');
        $this->findCustomer($customer);
        $result = DB::transaction(function () use ($customer, $session, $request, $audit): array {
            $item = DB::table('playback_sessions')->where('user_id', $customer)->where('id', $session)->lockForUpdate()->first();
            abort_if(! $item, 404);
            $wasActive = $item->ended_at === null;
            if ($wasActive) {
                DB::table('playback_sessions')->where('id', $session)->update(['ended_at' => now(), 'updated_at' => now()]);
            }
            $audit->record($request->user(), 'support.playback_session.revoked', 'playback_session', $session,
                ['ended' => ! $wasActive], ['ended' => true, 'customer_id' => $customer]);

            return ['session_id' => $session, 'revoked' => $wasActive];
        });

        return response()->json(['data' => $result]);
    }

    public function tickets(Request $request): JsonResponse
    {
        $this->authorizeSupport($request, 'support.tickets.manage');
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'in:open,in_progress,waiting_customer,escalated,resolved,closed'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'category' => ['sometimes', 'in:account,billing,playback,device,subscription,general'],
            'assigned_to' => ['sometimes', 'regex:/^(unassigned|[1-9][0-9]*)$/'],
            'escalated' => ['sometimes', 'in:0,1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = DB::table('support_tickets')->select(['id', 'public_id', 'customer_id', 'subject', 'category', 'priority', 'status', 'escalation_target', 'assigned_to', 'created_at']);
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($searchQuery) use ($term): void {
                if (ctype_digit($term)) {
                    $searchQuery->orWhere('support_tickets.id', (int) $term)
                        ->orWhere('support_tickets.customer_id', (int) $term);
                }
                $searchQuery->orWhere('support_tickets.subject', 'like', '%'.$term.'%')
                    ->orWhere('support_tickets.public_id', 'like', '%'.$term.'%');
            });
        }
        foreach (['status', 'priority', 'category'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where('support_tickets.'.$filter, $filters[$filter]);
            }
        }
        if (isset($filters['assigned_to'])) {
            if ($filters['assigned_to'] === 'unassigned') {
                $query->whereNull('support_tickets.assigned_to');
            } else {
                $query->where('support_tickets.assigned_to', (int) $filters['assigned_to']);
            }
        }
        if (isset($filters['escalated'])) {
            $filters['escalated'] === '1'
                ? $query->whereNotNull('support_tickets.escalation_target')
                : $query->whereNull('support_tickets.escalation_target');
        }

        return response()->json($query->orderByDesc('support_tickets.created_at')->paginate($filters['per_page'] ?? 25));
    }

    public function assignees(Request $request): JsonResponse
    {
        $this->authorizeSupport($request, 'support.tickets.manage');
        $users = User::query()->where('status', 1)->where(function ($query): void {
            $query->whereHas('permissions', fn ($permissions) => $permissions->where('name', 'support.tickets.manage'))
                ->orWhereHas('roles', fn ($roles) => $roles->where('roles.is_active', true)->whereHas('permissions', fn ($permissions) => $permissions->where('name', 'support.tickets.manage')));
        })->orderBy('id')->get(['id', 'first_name', 'last_name', 'username', 'email'])
            ->map(fn (User $user): array => ['id' => (int) $user->id, 'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->username, 'email' => $user->email]);

        return response()->json(['data' => $users]);
    }

    public function createTicket(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.tickets.manage');
        $data = $request->validate(['customer_id' => ['required', 'integer', 'exists:users,id'], 'subject' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:10000'], 'category' => ['sometimes', 'in:account,billing,playback,device,subscription,general'], 'priority' => ['sometimes', 'in:low,normal,high,urgent'], 'assigned_to' => ['nullable', 'integer', 'exists:users,id']]);
        $this->findCustomer((int) $data['customer_id']);
        if (! empty($data['assigned_to'])) {
            $this->assertSupportAssignee((int) $data['assigned_to']);
        }
        $ticket = DB::transaction(function () use ($data, $request, $audit): object {
            $id = DB::table('support_tickets')->insertGetId([
                'public_id' => (string) Str::uuid(), 'customer_id' => $data['customer_id'], 'created_by' => $request->user()->id,
                'assigned_to' => $data['assigned_to'] ?? null, 'subject' => trim($data['subject']), 'description' => trim($data['description']),
                'category' => $data['category'] ?? 'general', 'priority' => $data['priority'] ?? 'normal', 'status' => 'open',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $audit->record($request->user(), 'support.ticket.created', 'support_ticket', $id, null, ['customer_id' => $data['customer_id'], 'status' => 'open']);

            return DB::table('support_tickets')->find($id);
        });

        return response()->json(['data' => $ticket], 201);
    }

    public function showTicket(Request $request, int $ticket, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.tickets.manage');
        $item = DB::table('support_tickets')->where('id', $ticket)->first();
        abort_if(! $item, 404);
        $item->notes = DB::table('support_ticket_notes')->where('ticket_id', $ticket)->orderBy('id')->get(['id', 'author_id', 'note', 'is_internal', 'created_at']);
        $audit->record($request->user(), 'support.ticket.viewed', 'support_ticket', $ticket, null, ['note_count' => $item->notes->count()]);

        return response()->json(['data' => $item]);
    }

    public function updateTicket(Request $request, int $ticket, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.tickets.manage');
        $data = $request->validate(['status' => ['sometimes', 'in:open,in_progress,waiting_customer,escalated,resolved,closed'], 'priority' => ['sometimes', 'in:low,normal,high,urgent'], 'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id']]);
        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] !== null) {
            $this->assertSupportAssignee((int) $data['assigned_to']);
        }
        abort_if(! $data, 422);
        $result = DB::transaction(function () use ($ticket, $data, $request, $audit): object {
            $row = DB::table('support_tickets')->where('id', $ticket)->lockForUpdate()->first();
            abort_if(! $row, 404);
            $before = ['status' => $row->status, 'priority' => $row->priority, 'assigned_to' => $row->assigned_to];
            $updates = $data;
            if (($data['status'] ?? null) === 'resolved') {
                $updates['resolved_at'] = now();
            }
            if (isset($data['status']) && $data['status'] !== 'resolved') {
                $updates['resolved_at'] = null;
            }
            $updates['updated_at'] = now();
            DB::table('support_tickets')->where('id', $ticket)->update($updates);
            $audit->record($request->user(), 'support.ticket.updated', 'support_ticket', $ticket, $before, $updates);

            return DB::table('support_tickets')->find($ticket);
        });

        return response()->json(['data' => $result]);
    }

    public function addNote(Request $request, int $ticket, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.tickets.manage');
        $data = $request->validate(['note' => ['required', 'string', 'max:10000']]);
        $result = DB::transaction(function () use ($ticket, $data, $request, $audit): object {
            $row = DB::table('support_tickets')->where('id', $ticket)->lockForUpdate()->first();
            abort_if(! $row, 404);
            $first = $row->first_response_at ? null : now();
            $noteId = DB::table('support_ticket_notes')->insertGetId(['ticket_id' => $ticket, 'author_id' => $request->user()->id, 'note' => trim($data['note']), 'is_internal' => true, 'created_at' => now(), 'updated_at' => now()]);
            if ($first) {
                DB::table('support_tickets')->where('id', $ticket)->update(['first_response_at' => $first, 'updated_at' => now()]);
            }
            $audit->record($request->user(), 'support.ticket.note_added', 'support_ticket', $ticket, null, ['note_id' => $noteId, 'internal' => true]);

            return DB::table('support_ticket_notes')->find($noteId);
        });

        return response()->json(['data' => $result], 201);
    }

    public function escalate(Request $request, int $ticket, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.tickets.manage');
        $data = $request->validate(['target' => ['required', 'in:finance,technical_operations'], 'reason' => ['required', 'string', 'max:2000']]);
        $updated = DB::transaction(function () use ($ticket, $data, $request, $audit): object {
            $row = DB::table('support_tickets')->where('id', $ticket)->lockForUpdate()->first();
            abort_if(! $row, 404);
            DB::table('support_tickets')->where('id', $ticket)->update(['escalation_target' => $data['target'], 'status' => 'escalated', 'updated_at' => now()]);
            DB::table('support_ticket_notes')->insert(['ticket_id' => $ticket, 'author_id' => $request->user()->id, 'note' => '[Escalation to '.$data['target'].'] '.trim($data['reason']), 'is_internal' => true, 'created_at' => now(), 'updated_at' => now()]);
            $audit->record($request->user(), 'support.ticket.escalated', 'support_ticket', $ticket, ['target' => $row->escalation_target], ['target' => $data['target']]);

            return DB::table('support_tickets')->find($ticket);
        });

        return response()->json(['data' => $updated]);
    }

    public function revokeDeviceSession(Request $request, int $customer, int $device, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeSupport($request, 'support.sessions.revoke');
        $result = DB::transaction(function () use ($customer, $device, $request, $audit): array {
            $item = Device::query()->where('user_id', $customer)->whereKey($device)->lockForUpdate()->firstOrFail();
            $tokenId = $item->sanctum_token_id;
            $sessionId = $item->session_id;
            if ($tokenId) {
                DB::table('playback_sessions')->where('user_id', $customer)->where('device_token_id', $tokenId)->whereNull('ended_at')->update(['ended_at' => now(), 'updated_at' => now()]);
                PersonalAccessToken::query()->whereKey($tokenId)->delete();
            }
            $item->forceFill(['sanctum_token_id' => null, 'session_id' => null])->save();
            $audit->record($request->user(), 'support.device_session.revoked', 'device', $item->id, ['session_active' => (bool) $sessionId], ['session_active' => false]);

            return ['device_id' => $item->id, 'revoked' => (bool) $tokenId];
        });

        return response()->json(['data' => $result]);
    }

    private function authorizeSupport(Request $request, string $permission): void
    {
        abort_unless($request->user() && ExplicitPermission::allows($request->user(), $permission), 403);
    }

    private function findCustomer(int $customer): User
    {
        return User::query()->whereDoesntHave('roles')->findOrFail($customer);
    }

    private function assertSupportAssignee(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        abort_unless(ExplicitPermission::allows($user, 'support.tickets.manage'), 422, 'Assignee must have support ticket access.');
    }

    private function customerSummary(User $user): array
    {
        return ['id' => (int) $user->id, 'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->username,
            'email' => $user->email, 'status' => (int) $user->status, 'banned' => (bool) $user->is_banned,
            'created_at' => $user->created_at];
    }

    private function maskReference(string $reference): string
    {
        $length = strlen($reference);

        return $length <= 4 ? str_repeat('*', $length) : substr($reference, 0, 2).str_repeat('*', min(12, $length - 4)).substr($reference, -2);
    }

    private function median(array $values): ?int
    {
        sort($values);
        $count = count($values);
        if ($count === 0) {
            return null;
        }
        $middle = intdiv($count, 2);

        return $count % 2 ? $values[$middle] : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}

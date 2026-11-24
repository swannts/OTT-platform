<?php

namespace Modules\Entertainment\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Services\PlanEntitlementSnapshot;

class PlaybackSessionService
{
    private const HEARTBEAT_TTL_SECONDS = 120;

    /** @return array{session_id:string, expires_at:string} */
    public function start(User $user, PersonalAccessToken $token, int $profileId, string $contentId, string $contentType): array
    {
        return DB::transaction(function () use ($user, $token, $profileId, $contentId, $contentType): array {
            // Serialize starts per account so simultaneous requests cannot exceed the limit.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $now = now();
            DB::table('playback_sessions')
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->where('expires_at', '<=', $now)
                ->update(['ended_at' => $now, 'updated_at' => $now]);

            $active = DB::table('playback_sessions')
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->where('expires_at', '>', $now)
                ->where('device_token_id', $token->getKey())
                ->where('profile_id', $profileId)
                ->where('content_id', $contentId)
                ->where('content_type', $contentType)
                ->lockForUpdate()
                ->first();

            if (! $active) {
                $limit = $this->concurrentStreamLimit($user);
                $activeCount = DB::table('playback_sessions')
                    ->where('user_id', $user->id)
                    ->whereNull('ended_at')
                    ->where('expires_at', '>', $now)
                    ->count();
                if ($activeCount >= $limit) {
                    throw new AuthorizationException('The simultaneous stream limit has been reached.');
                }

                $sessionId = (string) Str::uuid();
                DB::table('playback_sessions')->insert([
                    'session_id' => $sessionId,
                    'user_id' => $user->id,
                    'device_token_id' => $token->getKey(),
                    'profile_id' => $profileId,
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'last_heartbeat_at' => $now,
                    'expires_at' => $now->copy()->addSeconds(self::HEARTBEAT_TTL_SECONDS),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $sessionId = $active->session_id;
                DB::table('playback_sessions')->where('id', $active->id)->update([
                    'last_heartbeat_at' => $now,
                    'expires_at' => $now->copy()->addSeconds(self::HEARTBEAT_TTL_SECONDS),
                    'updated_at' => $now,
                ]);
            }

            return [
                'session_id' => $sessionId,
                'expires_at' => $now->copy()->addSeconds(self::HEARTBEAT_TTL_SECONDS)->toIso8601String(),
            ];
        }, 3);
    }

    public function heartbeat(User $user, PersonalAccessToken $token, string $sessionId): string
    {
        $now = now();
        $updated = DB::table('playback_sessions')
            ->where('session_id', $sessionId)
            ->where('user_id', $user->id)
            ->where('device_token_id', $token->getKey())
            ->whereNull('ended_at')
            ->where('expires_at', '>', $now)
            ->update([
                'last_heartbeat_at' => $now,
                'expires_at' => $now->copy()->addSeconds(self::HEARTBEAT_TTL_SECONDS),
                'updated_at' => $now,
            ]);

        if ($updated !== 1) {
            throw new AuthorizationException('Playback session has expired or was revoked.');
        }

        return $now->copy()->addSeconds(self::HEARTBEAT_TTL_SECONDS)->toIso8601String();
    }

    /** @return array{content_type:string, content_id:string}|null Session details only when this call ended it. */
    public function end(User $user, PersonalAccessToken $token, string $sessionId): ?array
    {
        return DB::transaction(function () use ($user, $token, $sessionId): ?array {
            $session = DB::table('playback_sessions')
                ->where('session_id', $sessionId)
                ->where('user_id', $user->id)
                ->where('device_token_id', $token->getKey())
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw new AuthorizationException('Playback session has expired or was revoked.');
            }

            if ($session->ended_at) {
                return null;
            }

            DB::table('playback_sessions')->where('id', $session->id)->update([
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

            return ['content_type' => (string) $session->content_type, 'content_id' => (string) $session->content_id];
        }, 3);
    }

    private function concurrentStreamLimit(User $user): int
    {
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>', now()))
            ->latest('id')
            ->first();
        $limit = PlanEntitlementSnapshot::integerLimit($subscription?->plan_type, 'simultaneous-streams')
            ?? PlanEntitlementSnapshot::integerLimit($subscription?->plan_type, 'concurrent-streams');

        return $limit === null ? 1 : min(10, $limit);
    }
}

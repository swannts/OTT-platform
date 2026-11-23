<?php

namespace Modules\Entertainment\Services;

use App\Models\User;
use App\Models\UserMultiProfile;
use App\Models\Device;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Modules\Entertainment\Models\Entertainment;
use Modules\Episode\Models\Episode;
use Modules\Frontend\Models\PayPerView;
use Modules\Subscriptions\Models\Subscription;
use Modules\Video\Models\Video;

class PlaybackEntitlementService
{
    /** @return array{content: Entertainment, profile: UserMultiProfile} */
    public function authorize(User $user, string $contentId, string $type, int $profileId, Request $request): array
    {
        $profile = UserMultiProfile::query()
            ->where('id', $profileId)
            ->where('user_id', $user->id)
            ->first();

        if (!$profile) {
            throw new AuthorizationException('The selected profile is unavailable.');
        }

        $activeProfileId = Device::query()
            ->where('user_id', $user->id)
            ->where('device_id', $request->ip())
            ->value('active_profile');
        if ((string) $activeProfileId !== (string) $profile->id) {
            throw new AuthorizationException('Select this profile on the current device before playback.');
        }

        $model = match ($type) {
            'movie', 'tvshow' => Entertainment::class,
            'episode' => Episode::class,
            'video' => Video::class,
            default => null,
        };

        if (!$model) {
            throw new AuthorizationException('This content type is not available for playback.');
        }

        $contentQuery = $model::query()
            ->with('plan:id,level')
            ->whereKey($contentId)
            ->where('status', 1)
            ->whereNull('deleted_at');
        if ($model === Entertainment::class) {
            $contentQuery->where('type', $type);
        }
        $content = $contentQuery->first();

        if (!$content) {
            throw new AuthorizationException('This content is unavailable.');
        }

        if ((int) $profile->is_child_profile === 1 && (int) $content->is_restricted === 1) {
            throw new AuthorizationException('This title is not available for a child profile.');
        }

        $access = strtolower((string) ($content->movie_access ?? $content->access));
        if ($access === 'free') {
            return ['content' => $content, 'profile' => $profile];
        }

        if (in_array($access, ['pay-per-view', 'unlock'], true)) {
            $purchase = PayPerView::query()
                ->where('user_id', $user->id)
                ->where('movie_id', $content->id)
                ->whereIn('type', [$type, $content->type])
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('payperviewstransactions')
                        ->whereColumn('payperviewstransactions.pay_per_view_id', 'pay_per_views.id')
                        ->where('payperviewstransactions.payment_status', 'paid');
                })
                ->where(function ($query) {
                    $query->whereNull('view_expiry_date')->orWhere('view_expiry_date', '>', now());
                })
                ->exists();

            if ($purchase) {
                return ['content' => $content, 'profile' => $profile];
            }

            throw new AuthorizationException('An active purchase is required to watch this title.');
        }

        if (in_array($access, ['paid', 'premium', 'subscription'], true)) {
            $subscription = Subscription::query()
                ->with('plan:id,level')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
                })
                ->latest('id')
                ->first();

            $subscriberLevel = (int) ($subscription?->plan?->level ?? $subscription?->level ?? 0);
            $requiredLevel = (int) ($content->plan?->level ?? 0);

            if ($subscription && $requiredLevel > 0 && $subscriberLevel >= $requiredLevel) {
                return ['content' => $content, 'profile' => $profile];
            }

            throw new AuthorizationException('An active subscription with the required plan is needed.');
        }

        throw new AuthorizationException('This title does not have a valid access policy.');
    }
}

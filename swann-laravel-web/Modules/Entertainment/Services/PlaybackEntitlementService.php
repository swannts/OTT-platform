<?php

namespace Modules\Entertainment\Services;

use App\Models\ContentLicenseWindow;
use App\Models\Device;
use App\Models\User;
use App\Models\UserMultiProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Entertainment\Models\Entertainment;
use Modules\Episode\Models\Episode;
use Modules\Frontend\Models\PayPerView;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Services\PlanEntitlementSnapshot;
use Modules\Video\Models\Video;

class PlaybackEntitlementService
{
    public function maximumVideoQuality(User $user): ?int
    {
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>', now()))
            ->latest('id')
            ->first();

        return PlanEntitlementSnapshot::integerLimit($subscription?->plan_type, 'max-video-quality');
    }

    /** @return array{content: Entertainment, profile: UserMultiProfile} */
    public function authorize(User $user, string $contentId, string $type, int $profileId, Request $request): array
    {
        $profile = UserMultiProfile::query()
            ->where('id', $profileId)
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            throw new AuthorizationException('The selected profile is unavailable.');
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            throw new AuthorizationException('A registered playback session is required.');
        }

        $device = Device::query()
            ->where('user_id', $user->id)
            ->where('sanctum_token_id', $token->getKey())
            ->where('active_profile', $profile->id)
            ->whereNull('deleted_at')
            ->first();
        if (! $device) {
            throw new AuthorizationException('Select this profile on the current device before playback.');
        }
        $device->forceFill(['last_activity' => now()])->save();

        $model = match ($type) {
            'movie', 'tvshow' => Entertainment::class,
            'episode' => Episode::class,
            'video' => Video::class,
            default => null,
        };

        if (! $model) {
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

        if (! $content) {
            throw new AuthorizationException('This content is unavailable.');
        }

        $this->assertWithinLicensingWindow($content, $type);

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
                        ->where(function ($transaction) {
                            $transaction->where(function ($free) {
                                $free->where('payperviewstransactions.payment_type', 'free')
                                    ->where('payperviewstransactions.amount', 0);
                            })->orWhereExists(function ($receipt) {
                                $receipt->selectRaw('1')
                                    ->from('verified_payments')
                                    ->whereColumn('verified_payments.gateway', 'payperviewstransactions.payment_type')
                                    ->whereColumn('verified_payments.provider_reference', 'payperviewstransactions.transaction_id')
                                    ->whereColumn('verified_payments.user_id', 'payperviewstransactions.user_id')
                                    ->where('verified_payments.purchase_type', 'pay_per_view')
                                    ->where('verified_payments.fulfillment_type', 'pay_per_view')
                                    ->whereColumn('verified_payments.fulfillment_id', 'payperviewstransactions.pay_per_view_id')
                                    ->whereNotNull('verified_payments.fulfilled_at');
                            });
                        });
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
                    $query->whereNull('end_date')->orWhere('end_date', '>', now());
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

    private function assertWithinLicensingWindow(object $content, string $type): void
    {
        $contentType = match ($type) {
            'movie', 'tvshow' => 'entertainment',
            'episode' => 'episode',
            'video' => 'video',
        };

        $windows = collect();
        if ($type === 'episode' && $content->entertainment_id) {
            $parentWindow = ContentLicenseWindow::query()
                ->where('content_type', 'entertainment')
                ->where('content_id', (string) $content->entertainment_id)
                ->first();
            if ($parentWindow) {
                $windows->push($parentWindow);
            }
        }

        $window = ContentLicenseWindow::query()
            ->where('content_type', $contentType)
            ->where('content_id', (string) $content->getKey())
            ->first();
        if ($window) {
            $windows->push($window);
        }

        $now = now();
        foreach ($windows as $licenseWindow) {
            if (($licenseWindow->starts_at && $licenseWindow->starts_at->greaterThan($now))
                || ($licenseWindow->ends_at && $licenseWindow->ends_at->lessThanOrEqualTo($now))
                || ($licenseWindow->starts_at && $licenseWindow->ends_at && $licenseWindow->ends_at->lessThanOrEqualTo($licenseWindow->starts_at))) {
                throw new AuthorizationException('This title is outside its licensed playback window.');
            }
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\DispatchMarketingCampaign;
use App\Models\MarketingCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishMarketingCampaigns extends Command
{
    protected $signature = 'marketing:publish-scheduled';
    protected $description = 'Publish approved marketing campaigns when their scheduled time arrives and expire completed campaigns.';

    public function handle(): int
    {
        MarketingCampaign::query()->where('status', 'scheduled')->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    $published = DB::transaction(function () use ($campaign): bool {
                        $locked = MarketingCampaign::query()->lockForUpdate()->find($campaign->id);
                        if (!$locked || $locked->status !== 'scheduled' || !$locked->scheduled_at || $locked->scheduled_at->isFuture()) return false;
                        $locked->forceFill(['status' => 'published', 'published_at' => now()])->save();
                        return true;
                    });
                    if ($published) {
                        app(\App\Support\Rbac\AdminAuditLogger::class)->record(null, 'marketing.campaign.published', MarketingCampaign::class, $campaign->id, ['status' => 'scheduled'], ['status' => 'published', 'published_at' => now()]);
                        if ($campaign->kind === 'notification') DispatchMarketingCampaign::dispatch($campaign->id)->onQueue('notifications');
                    }
                }
            });

        $expiredCampaigns = MarketingCampaign::query()->where('status', 'published')->where(function ($query): void {
            $query->where(fn ($expiry) => $expiry->whereNotNull('expires_at')->where('expires_at', '<=', now()))
                ->orWhere(fn ($coupon) => $coupon->where('kind', 'promotion')->whereExists(function ($linkedCoupon): void {
                    $linkedCoupon->selectRaw('1')->from('coupons')->whereColumn('coupons.id', 'marketing_campaigns.coupon_id')
                        ->where(fn ($date) => $date->where('status', false)->orWhereDate('expire_date', '<', today()));
                }));
        })->get(['id']);
        foreach ($expiredCampaigns as $expiredCampaign) {
            $changed = MarketingCampaign::query()->whereKey($expiredCampaign->id)->where('status', 'published')
                ->update(['status' => 'expired', 'deactivated_at' => now(), 'updated_at' => now()]);
            if ($changed) app(\App\Support\Rbac\AdminAuditLogger::class)->record(null, 'marketing.campaign.expired', MarketingCampaign::class, $expiredCampaign->id, ['status' => 'published'], ['status' => 'expired']);
        }

        return self::SUCCESS;
    }
}

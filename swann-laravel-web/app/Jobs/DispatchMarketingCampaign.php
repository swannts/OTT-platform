<?php

namespace App\Jobs;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignDelivery;
use App\Support\Marketing\CampaignAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DispatchMarketingCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = MarketingCampaign::query()->find($this->campaignId);
        if (!$campaign || $campaign->status !== 'published' || $campaign->kind !== 'notification' || !$campaign->channel) return;

        CampaignAudience::query($campaign->audience_segment, $campaign->channel)
            ->select('users.id')->chunkById(500, function ($users) use ($campaign): void {
                foreach ($users as $user) {
                    $variant = $campaign->variant_b_percent > 0
                        && (hexdec(substr(hash('sha256', $campaign->id.':'.$user->id), 0, 4)) % 100) < $campaign->variant_b_percent ? 'B' : 'A';
                    DB::table('marketing_campaign_deliveries')->insertOrIgnore([
                        'campaign_id' => $campaign->id, 'user_id' => $user->id, 'channel' => $campaign->channel,
                        'variant' => $variant, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $delivery = MarketingCampaignDelivery::query()->where('campaign_id', $campaign->id)
                        ->where('user_id', $user->id)->where('channel', $campaign->channel)->first();
                    // Re-dispatch queued rows when this batch job is retried after a queue outage.
                    if ($delivery?->status === 'queued') SendMarketingCampaignDelivery::dispatch($delivery->id)->onQueue('notifications');
                }
            }, 'users.id', 'id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'opened_at' => 'datetime', 'clicked_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}

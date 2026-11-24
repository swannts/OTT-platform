<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'featured_content' => 'array', 'landing_page' => 'array', 'variant_b' => 'array',
            'scheduled_at' => 'datetime', 'expires_at' => 'datetime', 'approved_at' => 'datetime',
            'published_at' => 'datetime', 'deactivated_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MarketingCampaignDelivery::class, 'campaign_id');
    }
}

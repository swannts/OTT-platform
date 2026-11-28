<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAssetTrack extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_forced' => 'boolean',
        'is_accessibility' => 'boolean',
        'is_original' => 'boolean',
        'is_dubbed' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $hidden = ['path'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}

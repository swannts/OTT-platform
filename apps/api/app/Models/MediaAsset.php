<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['source_path', 'rendition_path', 'sha256'];

    protected $casts = [
        'metadata' => 'array',
        'tracks' => 'array',
        'ready_at' => 'datetime',
        'archived_at' => 'datetime',
        'quality_reviewed_at' => 'datetime',
        'is_active_version' => 'boolean',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function qualityReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_reviewed_by');
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(MediaProcessingJob::class);
    }

    public function latestProcessingJob(): HasOne
    {
        return $this->hasOne(MediaProcessingJob::class)->latestOfMany();
    }

    public function assetTracks(): HasMany
    {
        return $this->hasMany(MediaAssetTrack::class);
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_asset_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_asset_id');
    }

    public function entertainments(): HasMany
    {
        return $this->hasMany(\Modules\Entertainment\Models\Entertainment::class, 'media_asset_id');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(\Modules\Episode\Models\Episode::class, 'media_asset_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(\Modules\Video\Models\Video::class, 'media_asset_id');
    }
}

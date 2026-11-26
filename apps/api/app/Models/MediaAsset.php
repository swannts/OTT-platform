<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['source_path', 'rendition_path', 'sha256'];

    protected $casts = [
        'metadata' => 'array',
        'tracks' => 'array',
        'ready_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(MediaProcessingJob::class);
    }

    public function assetTracks(): HasMany
    {
        return $this->hasMany(MediaAssetTrack::class);
    }
}

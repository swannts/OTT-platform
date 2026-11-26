<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaUploadSession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

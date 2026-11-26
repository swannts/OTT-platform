<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ContentLicenseWindow extends Model
{
    protected $fillable = [
        'content_type',
        'content_id',
        'starts_at',
        'ends_at',
        'created_by',
        'updated_by',
    ];

    public function getStartsAtAttribute(?string $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->utc();
    }

    public function setStartsAtAttribute(mixed $value): void
    {
        $this->attributes['starts_at'] = $value === null ? null : Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }

    public function getEndsAtAttribute(?string $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->utc();
    }

    public function setEndsAtAttribute(mixed $value): void
    {
        $this->attributes['ends_at'] = $value === null ? null : Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}

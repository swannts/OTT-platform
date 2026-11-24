<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProviderEvent extends Model
{
    protected $fillable = [
        'gateway', 'event_id', 'event_type', 'provider_reference', 'payload_sha256',
        'status', 'attempts', 'failure_code', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifiedPayment extends Model
{
    protected $fillable = [
        'gateway',
        'provider_reference',
        'user_id',
        'purchase_type',
        'purchase_id',
        'amount_minor',
        'currency',
        'fulfillment_type',
        'fulfillment_id',
        'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'fulfilled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

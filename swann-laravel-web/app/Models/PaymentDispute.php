<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDispute extends Model
{
    protected $fillable = [
        'gateway', 'provider_dispute_id', 'provider_reference', 'verified_payment_id',
        'amount_minor', 'currency', 'status', 'reason', 'evidence_due_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['evidence_due_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function payment()
    {
        return $this->belongsTo(VerifiedPayment::class, 'verified_payment_id');
    }
}

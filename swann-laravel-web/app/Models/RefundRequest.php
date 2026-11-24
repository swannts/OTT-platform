<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'verified_payment_id', 'amount_minor', 'currency', 'reason', 'status',
        'idempotency_key', 'requested_by', 'reviewed_by', 'reviewed_at', 'executed_at',
        'provider_refund_id', 'failure_code', 'failure_message',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'executed_at' => 'datetime'];
    }

    public function payment()
    {
        return $this->belongsTo(VerifiedPayment::class, 'verified_payment_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

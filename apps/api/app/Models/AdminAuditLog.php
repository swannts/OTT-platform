<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

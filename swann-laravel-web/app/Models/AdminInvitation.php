<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminInvitation extends Model
{
    protected $table = 'admin_invitations';

    protected $guarded = ['id'];

    protected $casts = [
        'role_names' => 'array',
        'expires_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];
}

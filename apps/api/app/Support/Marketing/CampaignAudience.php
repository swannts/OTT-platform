<?php

namespace App\Support\Marketing;

use Illuminate\Support\Facades\DB;

class CampaignAudience
{
    public static function query(string $segment, string $channel)
    {
        return DB::table('users')->join('marketing_consents as c', 'c.user_id', '=', 'users.id')
            ->where('users.status', 1)->whereNull('users.deleted_at')
            ->where('c.channel', $channel)->where('c.consented', true)
            ->when($channel === 'email', fn ($query) => $query->whereNotNull('users.email_verified_at')->whereNotNull('users.email'))
            ->when($segment === 'subscribers', fn ($query) => $query->where('users.is_subscribe', 1))
            ->when($segment === 'registered', fn ($query) => $query->where('users.is_subscribe', 0));
    }
}

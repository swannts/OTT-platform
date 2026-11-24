<?php

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;

class PlanRegionalPrice extends Model
{
    protected $table = 'plan_regional_prices';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2'];
}

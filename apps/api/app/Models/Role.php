<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\PermissionRegistrar;

class Role extends \Spatie\Permission\Models\Role
{
    /**
     * Resolve staff independently of the current request guard. API requests
     * switch the default guard to Sanctum, which has no user provider.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key'),
        );
    }

    /**
     * Name should be lowercase.
     *
     * @param  string  $value  Name value
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtolower($value);
    }
}

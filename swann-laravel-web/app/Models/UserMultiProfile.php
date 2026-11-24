<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Casts\Attribute;

class UserMultiProfile extends BaseModel
{
    use HasFactory;
    protected $table = 'user_multi_profiles';

    protected $fillable = [
        'user_id',
        'name',
        'avatar',
        'is_child_profile'
    ];

    protected function isChildProfile(): Attribute
    {
      return Attribute::make(
        get: fn (?int $value) => $value,
    );
    }
    
    public function activeprofile()
    {
        $token = request()->user()?->currentAccessToken();
        return $this->hasOne(Device::class, 'active_profile')
            ->where('sanctum_token_id', $token instanceof \Laravel\Sanctum\PersonalAccessToken ? $token->getKey() : 0);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }


}

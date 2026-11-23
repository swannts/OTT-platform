<?php

namespace Modules\User\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class UserMultiProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'is_active' => $this->activeprofile !== null ? 1 : 0,
            'is_child_profile' => $this->is_child_profile,
            'is_protected_profile' => $this->is_child_profile == 1 ? 0 : ($this->user->is_parental_lock_enable ?? 0),
        ];
    }
}

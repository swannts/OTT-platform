<?php

namespace Modules\Subscriptions\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {

        return [
            'plan_id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'price' => $this->display_price ?? $this->price,
            'currency' => $this->display_currency ?? (GetcurrentCurrency() ?: null),
            'pricing_country' => $this->pricing_country ?? null,
            'discount' => $this->discount,
            'discount_percentage' => floatval($this->discount_percentage),
            'total_price' => $this->display_total_price ?? $this->total_price,
            'level' => $this->level,
            'duration' => $this->duration,
            'duration_value' => $this->duration_value,
            'trial_days' => (int) ($this->trial_days ?? 0),
            'description' => strip_tags($this->description),
            'plan_type' => PlanlimitationMappingResource::collection($this->planLimitation),
            'android_identifier' => $this->android_identifier,
            'apple_identifier' => $this->apple_identifier,
        ];
    }
}

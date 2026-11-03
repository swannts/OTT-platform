<?php

namespace Modules\LiveTV\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Transformers\PlanResource;
use Modules\Subscriptions\Models\Plan;

class LiveTvChannelResourceBannerV3 extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'poster_image' => setBaseUrlWithFileName($this->poster_image,'image','livetv'),
            'poster_tv_image' => setBaseUrlWithFileName($this->poster_tv_url,'image','livetv'),
            'name' => $this->name,
            'title' => $this->name,
            'name_bn' => $this->name_bn,
            'name_en' => $this->name_en ?? $this->name,
            'description' => $this->description,
            'description_bn' => $this->description_bn,
            'description_en' => $this->description_en ?? $this->description,
            'details'=>[
                'id' => $this->id,
                'name' => $this->name,
                'title' => $this->name,
                'name_bn' => $this->name_bn,
                'name_en' => $this->name_en ?? $this->name,
                'description' => $this->description,
                'description_bn' => $this->description_bn,
                'description_en' => $this->description_en ?? $this->description,
                'type' => 'livetv',
                'access' => $this->access,
                'is_device_supported'=> $this->isDeviceSupported ?? null,
                "has_content_access"=> $this->has_content_access ?? 0,
                "required_plan_level"=> $this->required_plan_level ?? null,
                'imdb_rating' => $this->IMDb_rating ?? $this->imdb_rating,
                'language' => $this->language ?? 'english',
                'duration' => $this->duration ?? null,
                'is_restricted' => $this->is_restricted ?? 0,
                'is_in_watchlist' => $this->is_watch_list ?? 0,

            ]
        ];
    }
}

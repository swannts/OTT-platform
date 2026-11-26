<?php

namespace Modules\Entertainment\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\Watchlist;

class CommanResourceV3 extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
       $access = in_array($this->type, ['movie', 'tvshow']) ? $this->movie_access : $this->access;
          return [
            'id' => $this->id,
            'poster_image' => setBaseUrlWithFileName($this->poster_image,'image',$this->type),
            'poster_tv_image' => setBaseUrlWithFileName($this->poster_tv_url,'image',$this->type),
            'blurhash' => $this->blurhash,
            'details'=>[
                'name' => $this->name,
                'title' => $this->name,
                'name_bn' => $this->name_bn ?? $this->getRawOriginal('name_bn'),
                'name_en' => $this->getRawOriginal('name') ?? $this->name,
                'type' => $this->type ?? 'livetv',
                'blurhash' => $this->blurhash,
                'access' => $access ,
                'imdb_rating'=> $this->IMDb_rating ?? $this->imdb_rating ?? null,
                'is_device_supported'=> $this->isDeviceSupported,
                "has_content_access"=> $this->has_content_access, // $access == 'free'|| $access == 'pay-per-view' ? 1 : $this->plan_id,
                "required_plan_level"=>$this->required_plan_level, // $this->userPlanId  >= $this->plan_id ? 1 : 0  ,
            ],
            'trailer_data'=>[
                'trailer_url_type' => $this->trailer_url_type,
                'trailer_url' => $this->trailer_url_type == 'Local' ? setBaseUrlWithFileName($this->trailer_url) : $this->trailer_url,
            ],
        ];
    }
}

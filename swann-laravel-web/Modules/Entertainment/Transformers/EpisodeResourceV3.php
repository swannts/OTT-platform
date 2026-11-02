<?php

namespace Modules\Entertainment\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class EpisodeResourceV3 extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        $genre_data = [];
        // Safely access entertainmentdata relationship with null check
        if ($this->entertainmentdata && $this->entertainmentdata->entertainmentGenerMappings) {
            $genres = $this->entertainmentdata->entertainmentGenerMappings;
            if (!empty($genres)) {
                foreach ($genres as $genre) {
                    if ($genre->genre && isset($genre->genre->status) && (int)$genre->genre->status == 1) {
                        $genre_data[] = $genre->genre->name;
                    }
                }
            }
        }

        $poster = $this->poster_url ?? $this->poster_image;
        $posterTv = $this->poster_tv_url ?? $this->poster_url ?? $this->poster_tv_image;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'duration' => $this->duration,
            'description' => $this->description ?? $this->short_desc,
            'poster_image' => setBaseUrlWithFileName($poster, 'image', 'episode'),
            'poster_tv_image' => setBaseUrlWithFileName($posterTv, 'image', 'episode'),
            'blurhash' => $this->blurhash,
            'details' => [
                'name' => $this->name,
                'type' => 'episode',
                'slug' => $this->slug,
                'genres' => $genre_data,
                'language' => $this->entertainmentdata->language ?? null,
                'release_date' => $this->release_date ? formatDate($this->release_date):null,
                'access' => $this->access,
                'is_restricted' => $this->is_restricted,
                'is_device_supported' => $this->isDeviceSupported,
                'has_content_access' => $this->has_content_access,
                'required_plan_level' => $this->required_plan_level,
                'imdb_rating' => $this->IMDb_rating ?? $this->imdb_rating ?? null,
                'tv_show_data' => $this->tv_show_data,
                'season_data' => $this->season_data,
                'duration' => $this->duration,
                'description' => $this->description ?? $this->short_desc,
                'short_description' => $this->short_desc ?? $this->description,
                'watched_duration' => $this->watched_time ?? '00:00:01',
            ],
            'download_data' => $this->download_data,
            'rental_data' => $this->rental ?? []
        ];
    }
}

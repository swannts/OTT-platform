<?php

namespace Modules\Genres\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class GenresResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        // dd($this);
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? $this->genre->name ?? null,
            'title' => $this->name ?? $this->genre->name ?? null,
            'name_bn' => $this->name_bn ?? optional($this->genre)->name_bn ?? null,
            'name_en' => ($this->getRawOriginal('name') ?? $this->name) ?? optional($this->genre)->name ?? null,
            'poster_image' => setBaseUrlWithFileName($this->file_url, 'image', 'genres'),
            'status' => $this->status ?? $this->genre->status ??  null,
        ];
    }
}

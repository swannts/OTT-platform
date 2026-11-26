<?php

namespace Modules\Genres\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Entertainment\Models\EntertainmentGenerMapping;

class Genres extends BaseModel
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'genres';

    protected $fillable = ['name', 'name_bn', 'slug', 'file_url', 'description', 'description_bn', 'status'];

    public function getNameAttribute($value)
    {
        if (app()->getLocale() === 'bn') {
            if (array_key_exists('name_bn', $this->attributes) && !empty($this->attributes['name_bn'])) {
                return $this->attributes['name_bn'];
            }
            if (!array_key_exists('name_bn', $this->attributes) && !empty($this->attributes['id'] ?? null)) {
                $bn = \Illuminate\Support\Facades\DB::table('genres')
                    ->where('id', $this->attributes['id'])
                    ->value('name_bn');
                if (!empty($bn)) {
                    $this->attributes['name_bn'] = $bn;
                    return $bn;
                }
            }
        }
        return $value;
    }

    public function getDescriptionAttribute($value)
    {
        if (app()->getLocale() === 'bn') {
            if (array_key_exists('description_bn', $this->attributes) && !empty($this->attributes['description_bn'])) {
                return $this->attributes['description_bn'];
            }
            if (!array_key_exists('description_bn', $this->attributes) && !empty($this->attributes['id'] ?? null)) {
                $bn = \Illuminate\Support\Facades\DB::table('genres')
                    ->where('id', $this->attributes['id'])
                    ->value('description_bn');
                if (!empty($bn)) {
                    $this->attributes['description_bn'] = $bn;
                    return $bn;
                }
            }
        }
        return $value;
    }


    public function setSlugAttribute($value)
    {
        $this->attributes['slug'] = slug_format(trim($value));

        if (empty($value)) {
            $this->attributes['slug'] = slug_format(trim($this->attributes['name']));
        }
    }


    public function entertainmentGenerMappings()
    {
        return $this->hasMany(EntertainmentGenerMapping::class,'genre_id','id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($genre) {

            if ($genre->isForceDeleting()) {

                $genre->entertainmentGenerMappings()->forcedelete();

            } else {
                $genre->entertainmentGenerMappings()->delete();
             }

        });

        static::restoring(function ($genre) {

            $genre->entertainmentGenerMappings()->withTrashed()->restore();
            
        });
    }

    
}
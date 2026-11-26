<?php

namespace Modules\LiveTV\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LiveTV\Models\TvChannelStreamContentMapping;
use Modules\Subscriptions\Models\Plan;

class LiveTvChannel extends BaseModel
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'live_tv_channel';
    protected $fillable = [
        'name','name_bn','slug','category_id','poster_url','thumb_url','access','plan_id','description','description_bn','status','poster_tv_url'
    ];
    // protected $appends = ['poster_url'];

    public function getNameAttribute($value)
    {
        if (app()->getLocale() === 'bn') {
            if (array_key_exists('name_bn', $this->attributes) && !empty($this->attributes['name_bn'])) {
                return $this->attributes['name_bn'];
            }
            if (!array_key_exists('name_bn', $this->attributes) && !empty($this->attributes['id'] ?? null)) {
                $bn = \Illuminate\Support\Facades\DB::table('live_tv_channel')
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
                $bn = \Illuminate\Support\Facades\DB::table('live_tv_channel')
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

    public function getBaseUrlAttribute($value)
    {
        return !empty($value) ? setBaseUrlWithFileNameV2() : NULL;
    }

    public function TvChannelStreamContentMappings()
    {
        return $this->hasOne(TvChannelStreamContentMapping::class,'tv_channel_id','id');
    }
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($liveTvChannel) {
            if (empty($liveTvChannel->slug) && !empty($liveTvChannel->name)) {
                $liveTvChannel->slug = \Illuminate\Support\Str::slug(trim($liveTvChannel->name));
            }
        });

        static::updating(function ($liveTvChannel) {
            if ($liveTvChannel->isDirty('name') && !empty($liveTvChannel->name)) {
                $liveTvChannel->slug = \Illuminate\Support\Str::slug(trim($liveTvChannel->name));
            }
            
            // Clear LiveTV dashboard cache when status or deleted_at changes
            if ($liveTvChannel->isDirty('status') || $liveTvChannel->isDirty('deleted_at')) {
                if (function_exists('clearLiveTvDashboardCache')) {
                    clearLiveTvDashboardCache();
                }
            }
        });

        static::deleting(function ($liveTvChannel) {
            // Clear LiveTV dashboard cache when channel is soft deleted
            if (function_exists('clearLiveTvDashboardCache')) {
                clearLiveTvDashboardCache();
            }
        });

        static::restoring(function ($liveTvChannel) {
            // Clear LiveTV dashboard cache when channel is restored
            if (function_exists('clearLiveTvDashboardCache')) {
                clearLiveTvDashboardCache();
            }
        });
        
        // Clear cache when channel is restored (after restore completes)
        static::restored(function ($liveTvChannel) {
            if (function_exists('clearLiveTvDashboardCache')) {
                clearLiveTvDashboardCache();
            }
        });
    }

    public function TvCategory()
    {
        return $this->hasOne(LiveTvCategory::class,'id','category_id');
    }

    public function plan()
    {
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    public static function get_top_channel($channelIdsArray)
    {
        $channelIdsArray = is_array($channelIdsArray) ? $channelIdsArray : (array) $channelIdsArray;
        $channelIdsArray = array_values(array_filter($channelIdsArray, static fn ($id) => $id !== null && $id !== ''));

        if (count($channelIdsArray) === 0) {
            return collect();
        }

        $items = LiveTvChannel::select([
            'id','name','name_bn','slug','plan_id','description','description_bn','status','access','category_id','poster_url','poster_tv_url',
        ])
        ->with([
            'plan:id,level',
            'TvCategory:id,name',
            'TvChannelStreamContentMappings:id,tv_channel_id,stream_type,embedded,server_url,server_url1'
        ])
        ->whereIn('id', $channelIdsArray)
        ->where('status', 1)
        ->where('deleted_at', null)
        ->latest('id')
        ->get();

        return $items->map(function ($item) {
            $item->plan_level = optional($item->plan)->level;
            $item->category = optional($item->TvCategory)->name;
            $item->stream_type = optional($item->TvChannelStreamContentMappings)->stream_type;
            $item->embedded = optional($item->TvChannelStreamContentMappings)->embedded;
            $item->server_url = optional($item->TvChannelStreamContentMappings)->server_url;
            $item->server_url1 = optional($item->TvChannelStreamContentMappings)->server_url1;
            $item->base_url = $item->poster_url; // preserve alias
            return $item;
        });
    }

    public static function get_channel()
    {
        $items = LiveTvChannel::select([
            'id','category_id','name','name_bn','plan_id','slug','description','description_bn','status','access','poster_url','poster_tv_url',
        ])
        ->with([
            'plan:id,level',
            'TvCategory:id,name',
            'TvChannelStreamContentMappings:id,tv_channel_id,stream_type,embedded,server_url,server_url1'
        ])
        ->where('status',1)
        ->where('deleted_at',null)
        ->orderBy('updated_at', 'desc')
        ->take(6)
        ->get();

        return $items->map(function ($item) {
            $item->plan_level = optional($item->plan)->level;
            $item->category = optional($item->TvCategory)->name;
            $item->stream_type = optional($item->TvChannelStreamContentMappings)->stream_type;
            $item->embedded = optional($item->TvChannelStreamContentMappings)->embedded;
            $item->server_url = optional($item->TvChannelStreamContentMappings)->server_url;
            $item->server_url1 = optional($item->TvChannelStreamContentMappings)->server_url1;
            $item->base_url = $item->poster_url;
            return $item;
        });
    }

    public static function get_tvChannels_catgory_wise($category)
    {
        $items = LiveTvChannel::select([
            'id','name','name_bn','plan_id','description','description_bn','status','access','category_id','poster_url','poster_tv_url'
        ])
        ->with([
            'plan:id,level',
            'TvCategory:id,name',
            'TvChannelStreamContentMappings:id,tv_channel_id,stream_type,embedded,server_url,server_url1'
        ])
        ->where('category_id',$category)
        ->where('status',1)
        ->orderBy('updated_at', 'desc')
        ->get();

        return $items->map(function ($item) {
            $item->plan_level = optional($item->plan)->level;
            $item->category = optional($item->TvCategory)->name;
            $item->stream_type = optional($item->TvChannelStreamContentMappings)->stream_type;
            $item->embedded = optional($item->TvChannelStreamContentMappings)->embedded;
            $item->server_url = optional($item->TvChannelStreamContentMappings)->server_url;
            $item->server_url1 = optional($item->TvChannelStreamContentMappings)->server_url1;
            $item->base_url = $item->poster_url;
            return $item;
        });
    }
}

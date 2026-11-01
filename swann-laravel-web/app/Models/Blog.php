<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Model
{
    use SoftDeletes;

    protected $table = 'blogs';

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'image_url',
        'category',
        'tags',
        'author_name',
        'author_avatar',
        'reading_time',
        'views_count',
        'is_featured',
        'status',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_featured' => 'boolean',
        'status' => 'integer',
        'reading_time' => 'integer',
        'views_count' => 'integer',
        'published_at' => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 1);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeCategory($query, $category)
    {
        if (!empty($category) && strtolower($category) !== 'all') {
            return $query->where('category', $category);
        }
        return $query;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_api_serves_requested_locale_and_filters_by_localized_category(): void
    {
        Blog::query()->create([
            'title' => 'Demo: Stories of Mercy',
            'title_bn' => 'ডেমো: দয়ার গল্প',
            'slug' => 'demo-stories-of-mercy',
            'summary' => 'English summary',
            'summary_bn' => 'বাংলা সারাংশ',
            'content' => 'English article body',
            'content_bn' => 'বাংলা লেখার বিষয়বস্তু',
            'category' => 'Demo: Faith & Values',
            'category_bn' => 'ডেমো: বিশ্বাস ও মূল্যবোধ',
            'tags' => ['demo', 'values'],
            'tags_bn' => ['ডেমো', 'মূল্যবোধ'],
            'author_name' => 'NOOR Demo Editorial',
            'reading_time' => 3,
            'views_count' => 0,
            'is_featured' => true,
            'status' => 1,
            'published_at' => now(),
        ]);

        $this->withHeader('app-locale', 'bn')
            ->getJson('/api/v3/blogs?category='.urlencode('ডেমো: বিশ্বাস ও মূল্যবোধ'))
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'ডেমো: দয়ার গল্প')
            ->assertJsonPath('data.data.0.summary', 'বাংলা সারাংশ')
            ->assertJsonPath('data.data.0.category', 'ডেমো: বিশ্বাস ও মূল্যবোধ')
            ->assertJsonPath('data.data.0.tags.1', 'মূল্যবোধ');

        $this->withHeader('app-locale', 'bn')
            ->getJson('/api/v3/blogs/demo-stories-of-mercy')
            ->assertOk()
            ->assertJsonPath('data.post.content', 'বাংলা লেখার বিষয়বস্তু');

        $this->withHeader('app-locale', 'en')
            ->getJson('/api/v3/blogs?category='.urlencode('Demo: Faith & Values'))
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Demo: Stories of Mercy')
            ->assertJsonPath('data.data.0.category', 'Demo: Faith & Values');
    }

    public function test_blog_filters_by_bengali_tags_and_preserves_english_fallback(): void
    {
        $published = Blog::query()->create([
            'title' => 'English fallback',
            'slug' => 'english-fallback',
            'content' => 'English article body',
            'category' => 'Faith',
            'tags' => ['mercy'],
            'tags_bn' => ['দয়া'],
            'status' => 1,
            'published_at' => now(),
        ]);
        Blog::query()->create([
            'title' => 'Unpublished',
            'slug' => 'unpublished-tag-match',
            'content' => 'Unpublished article body',
            'category' => 'Faith',
            'tags_bn' => ['দয়া'],
            'status' => 0,
            'published_at' => now(),
        ]);

        $this->withHeader('app-locale', 'bn')
            ->getJson('/api/v3/blogs?tag='.urlencode('দয়া'))
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $published->id)
            ->assertJsonPath('data.data.0.title', 'English fallback')
            ->assertJsonPath('data.data.0.tags.0', 'দয়া');

        $this->withHeader('app-locale', 'en')
            ->getJson('/api/v3/blogs?tag=mercy')
            ->assertOk()
            ->assertJsonPath('data.data.0.tags.0', 'mercy');
    }
}

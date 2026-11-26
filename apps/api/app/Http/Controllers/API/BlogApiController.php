<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlogApiController extends Controller
{
    /**
     * Get paginated list of blog articles with filters
     */
    public function index(Request $request)
    {
        $query = Blog::published();

        // Filter by category
        if ($request->filled('category') && strtolower($request->category) !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by featured
        if ($request->boolean('featured')) {
            $query->featured();
        }

        // Search in title or summary
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('summary', 'LIKE', "%{$search}%")
                  ->orWhere('author_name', 'LIKE', "%{$search}%");
            });
        }

        // Filter by tag
        if ($request->filled('tag')) {
            $tag = trim($request->tag);
            $query->where(function ($q) use ($tag) {
                $q->whereJsonContains('tags', $tag)
                  ->orWhere('tags', 'LIKE', "%{$tag}%");
            });
        }

        $perPage = max(1, min(50, (int) $request->input('per_page', 9)));
        $blogs = $query->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success($blogs, 'Blog articles retrieved successfully');
    }

    /**
     * Get single blog article details by slug or id, with related articles
     */
    public function show(string $slugOrId)
    {
        $blog = Blog::published()
            ->where(function ($q) use ($slugOrId) {
                $q->where('slug', $slugOrId)
                  ->orWhere('id', $slugOrId);
            })
            ->first();

        if (!$blog) {
            return ApiResponse::error('Blog article not found', 404);
        }

        // Increment view count quietly
        try {
            $blog->increment('views_count');
        } catch (\Throwable $e) {}

        // Fetch related articles in the same category or latest
        $related = Blog::published()
            ->where('id', '!=', $blog->id)
            ->where(function ($q) use ($blog) {
                $q->where('category', $blog->category);
            })
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        if ($related->isEmpty()) {
            $related = Blog::published()
                ->where('id', '!=', $blog->id)
                ->orderByDesc('published_at')
                ->take(3)
                ->get();
        }

        return ApiResponse::success([
            'post' => $blog,
            'related' => $related,
        ], 'Blog article details retrieved successfully');
    }

    /**
     * Get list of categories with post counts
     */
    public function categories()
    {
        $categories = Blog::published()
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        return ApiResponse::success($categories, 'Blog categories retrieved successfully');
    }
}


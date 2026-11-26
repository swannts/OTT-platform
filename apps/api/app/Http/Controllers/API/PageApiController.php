<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Modules\Page\Models\Page;
use Modules\Page\Transformers\PageResource;

class PageApiController extends Controller
{
    /**
     * Get single page details by slug or ID
     */
    public function getPageDetails(Request $request, string $slug)
    {
        $page = Page::where('slug', $slug)
            ->orWhere('id', $slug)
            ->where('status', 1)
            ->first();

        if (! $page) {
            return ApiResponse::error('Page not found.', 404);
        }

        return ApiResponse::success(new PageResource($page), 'Page details retrieved successfully');
    }
}


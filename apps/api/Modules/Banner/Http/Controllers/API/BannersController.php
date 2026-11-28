<?php

namespace Modules\Banner\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Modules\Banner\Models\Banner;

class BannersController extends Controller
{
    /**
     * Get all currently visible banners.
     */
    public function getBanners()
    {
        try {
            $banners = Banner::query()
                ->select('id', 'title', 'file_url', 'poster_url', 'type', 'type_id', 'type_name', 'type_name_bn', 'description', 'description_bn', 'banner_for', 'click_url', 'status');
            // Only active, in-window banners belong in public catalog responses.
            $banners = $banners->currentlyVisible()->get();

            return ApiResponse::success($banners);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}

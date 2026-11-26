<?php

namespace Modules\Banner\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Modules\Banner\Models\Banner;
use Illuminate\Validation\Rule;

class BannersController extends Controller
{
    /**
     * Get Banners by Type
     * @param Request $request
     * @return Response
     */
    public function getBanners(Request $request)
    {
        try {
            // Validate request parameters
            $request->validate([
                'type' => ['nullable', Rule::in(['movie', 'tv_show', 'video', 'all'])],
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);

            $query = Banner::query()
                ->select('id', 'title', 'file_url', 'poster_url', 'type', 'type_id', 'type_name', 'banner_for', 'click_url', 'status');


            if ($request->type && $request->type !== 'all') {
                $query->where('type', $request->type);
            }


            // Only active, in-window banners belong in public catalog responses.
            $query->currentlyVisible();


            


            $banners = $query->paginate($request->per_page ?? 10);

            return ApiResponse::success(
                $banners,
                null,
                200,
                [
                    'meta' => [
                        'types' => ['movie', 'tv_show', 'video'],
                        'current_type' => $request->type ?? 'all'
                    ]
                ]
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}

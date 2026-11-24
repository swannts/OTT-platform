<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ContentLicenseWindow;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Entertainment\Models\Entertainment;
use Modules\Episode\Models\Episode;
use Modules\Video\Models\Video;

class AdminContentLicensingController extends Controller
{
    /** @var array<string, class-string> */
    private const CONTENT_MODELS = [
        'entertainment' => Entertainment::class,
        'episode' => Episode::class,
        'video' => Video::class,
    ];

    public function show(Request $request, string $contentType, string $contentId): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.view'), 403);
        $this->findContent($contentType, $contentId);

        $window = ContentLicenseWindow::query()
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->first();

        return response()->json(['data' => $this->payload($window)]);
    }

    public function update(Request $request, string $contentType, string $contentId, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.schedule'), 403);
        $content = $this->findContent($contentType, $contentId);
        $data = $request->validate([
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $existing = ContentLicenseWindow::query()
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->first();
        $startInput = array_key_exists('starts_at', $data) ? $data['starts_at'] : $existing?->starts_at;
        $endInput = array_key_exists('ends_at', $data) ? $data['ends_at'] : $existing?->ends_at;
        $startsAt = $startInput ? Carbon::parse($startInput)->utc() : null;
        $endsAt = $endInput ? Carbon::parse($endInput)->utc() : null;

        if ($startsAt && $endsAt && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['The licensing window end must be later than its start.'],
            ]);
        }

        $window = DB::transaction(function () use ($audit, $content, $contentId, $contentType, $endsAt, $request, $startsAt): ContentLicenseWindow {
            $content->newQuery()->whereKey($contentId)->lockForUpdate()->firstOrFail();
            $window = ContentLicenseWindow::query()
                ->where('content_type', $contentType)
                ->where('content_id', $contentId)
                ->lockForUpdate()
                ->first();
            $before = $window ? $this->payload($window) : null;

            if (! $startsAt && ! $endsAt) {
                if ($window) {
                    $window->delete();
                }
                $audit->record($request->user(), 'content.license_window.updated', get_class($content), $contentId, $before, null);

                return new ContentLicenseWindow([
                    'content_type' => $contentType,
                    'content_id' => $contentId,
                ]);
            }

            $window ??= new ContentLicenseWindow(['content_type' => $contentType, 'content_id' => $contentId]);
            $window->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => $window->exists ? $window->created_by : $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ])->save();
            $audit->record($request->user(), 'content.license_window.updated', get_class($content), $contentId, $before, $this->payload($window));

            return $window;
        }, 3);

        return response()->json(['data' => $this->payload($window)]);
    }

    private function findContent(string $contentType, string $contentId): Model
    {
        abort_if($contentId === '' || strlen($contentId) > 64, 404);
        $model = self::CONTENT_MODELS[$contentType] ?? null;
        abort_unless($model, 404, 'This content type cannot have a licensing window.');

        return $model::query()->findOrFail($contentId);
    }

    private function payload(?ContentLicenseWindow $window): array
    {
        return [
            'starts_at' => $window?->starts_at?->utc()->toIso8601String(),
            'ends_at' => $window?->ends_at?->utc()->toIso8601String(),
        ];
    }
}

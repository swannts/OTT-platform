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
use Illuminate\Validation\Rule;
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

        return response()->json(['data' => $this->payload($window, $contentType, $contentId)]);
    }

    public function update(Request $request, string $contentType, string $contentId, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.schedule'), 403);
        $content = $this->findContent($contentType, $contentId);
        $countryCodes = $request->input('allowed_country_codes');
        if (is_array($countryCodes)) {
            $request->merge([
                'allowed_country_codes' => array_map(
                    static fn ($code) => is_string($code) ? strtoupper(trim($code)) : $code,
                    $countryCodes
                ),
            ]);
        }
        $data = $request->validate([
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'allowed_country_codes' => ['sometimes', 'array', 'max:249'],
            'allowed_country_codes.*' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
                'distinct:strict',
                Rule::exists('countries', 'code')->where(fn ($query) => $query->where('status', 1)->whereNull('deleted_at')),
            ],
        ]);

        $existing = ContentLicenseWindow::query()
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->first();
        $startInput = array_key_exists('starts_at', $data) ? $data['starts_at'] : $existing?->starts_at;
        $endInput = array_key_exists('ends_at', $data) ? $data['ends_at'] : $existing?->ends_at;
        $startsAt = $startInput ? Carbon::parse($startInput)->utc() : null;
        $endsAt = $endInput ? Carbon::parse($endInput)->utc() : null;
        $allowedCountryCodes = array_key_exists('allowed_country_codes', $data)
            ? array_values(array_unique($data['allowed_country_codes']))
            : null;

        if ($startsAt && $endsAt && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['The licensing window end must be later than its start.'],
            ]);
        }

        $window = DB::transaction(function () use ($allowedCountryCodes, $audit, $content, $contentId, $contentType, $endsAt, $request, $startsAt): ContentLicenseWindow {
            $content->newQuery()->whereKey($contentId)->lockForUpdate()->firstOrFail();
            $window = ContentLicenseWindow::query()
                ->where('content_type', $contentType)
                ->where('content_id', $contentId)
                ->lockForUpdate()
                ->first();
            $before = $this->payload($window, $contentType, $contentId);

            if (! $startsAt && ! $endsAt) {
                if ($window) {
                    $window->delete();
                }
                $window = new ContentLicenseWindow([
                    'content_type' => $contentType,
                    'content_id' => $contentId,
                ]);
            } else {
                $window ??= new ContentLicenseWindow(['content_type' => $contentType, 'content_id' => $contentId]);
                $window->forceFill([
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'created_by' => $window->exists ? $window->created_by : $request->user()->getKey(),
                    'updated_by' => $request->user()->getKey(),
                ])->save();
            }

            if ($allowedCountryCodes !== null) {
                DB::table('content_license_territories')
                    ->where('content_type', $contentType)
                    ->where('content_id', $contentId)
                    ->delete();

                if ($allowedCountryCodes !== []) {
                    $now = now();
                    DB::table('content_license_territories')->insert(array_map(
                        static fn (string $countryCode): array => [
                            'content_type' => $contentType,
                            'content_id' => $contentId,
                            'country_code' => $countryCode,
                            'created_by' => $request->user()->getKey(),
                            'updated_by' => $request->user()->getKey(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $allowedCountryCodes
                    ));
                }
            }

            $audit->record($request->user(), 'content.license_window.updated', get_class($content), $contentId, $before, $this->payload($window, $contentType, $contentId));

            return $window;
        }, 3);

        return response()->json(['data' => $this->payload($window, $contentType, $contentId)]);
    }

    private function findContent(string $contentType, string $contentId): Model
    {
        abort_if($contentId === '' || strlen($contentId) > 64, 404);
        $model = self::CONTENT_MODELS[$contentType] ?? null;
        abort_unless($model, 404, 'This content type cannot have a licensing window.');

        return $model::query()->findOrFail($contentId);
    }

    private function payload(?ContentLicenseWindow $window, string $contentType, string $contentId): array
    {
        return [
            'starts_at' => $window?->starts_at?->utc()->toIso8601String(),
            'ends_at' => $window?->ends_at?->utc()->toIso8601String(),
            'allowed_country_codes' => DB::table('content_license_territories')
                ->where('content_type', $contentType)
                ->where('content_id', $contentId)
                ->orderBy('country_code')
                ->pluck('country_code')
                ->all(),
        ];
    }
}

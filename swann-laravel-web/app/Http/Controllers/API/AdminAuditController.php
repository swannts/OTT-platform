<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'audit.view'), 403);

        $this->validateFilters($request);

        $query = $this->filteredQuery($request)->with('actor:id,first_name,last_name,email');
        $logs = $query->latest('created_at')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => collect($logs->items())->map(fn (AdminAuditLog $log): array => [
                'id' => $log->id,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => trim(($log->actor->first_name ?? '').' '.($log->actor->last_name ?? '')) ?: $log->actor->email,
                ] : null,
                'action' => $log->action,
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'request_id' => $log->request_id,
                'before' => $log->before_values,
                'after' => $log->after_values,
                'outcome' => $log->outcome,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage(), 'per_page' => $logs->perPage(), 'total' => $logs->total()],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'audit.export'), 403);
        $this->validateFilters($request);

        return response()->streamDownload(function () use ($request): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['id', 'actor_id', 'action', 'resource_type', 'resource_id', 'request_id', 'before', 'after', 'outcome', 'created_at']);
            $this->filteredQuery($request)->orderBy('id')->chunkById(500, function ($logs) use ($stream): void {
                foreach ($logs as $log) {
                    fputcsv($stream, [
                        $log->id, $log->actor_id, $log->action, $log->resource_type, $log->resource_id,
                        $log->request_id, json_encode($log->before_values), json_encode($log->after_values),
                        $log->outcome, $log->created_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($stream);
        }, 'admin-audit-'.now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'actor_id' => ['sometimes', 'nullable', 'integer'],
            'action' => ['sometimes', 'nullable', 'string', 'max:125'],
            'resource_type' => ['sometimes', 'nullable', 'string', 'max:125'],
            'resource_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'outcome' => ['sometimes', 'nullable', 'in:success,failure'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function filteredQuery(Request $request)
    {
        $query = AdminAuditLog::query();
        foreach (['actor_id', 'action', 'resource_type', 'resource_id', 'outcome'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from')->toDateString());
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to')->toDateString());
        }

        return $query;
    }
}

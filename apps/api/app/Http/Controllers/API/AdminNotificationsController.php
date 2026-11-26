<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminNotificationsController extends Controller
{
    public function counts(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications();

        return response()->json(['data' => [
            'all' => (clone $notifications)->whereNull('archived_at')->count(),
            'unread' => (clone $notifications)->whereNull('archived_at')->whereNull('read_at')->count(),
            'archived' => (clone $notifications)->whereNotNull('archived_at')->count(),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['all', 'unread', 'archived'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $validated['status'] ?? 'all';
        $query = $request->user()->notifications()->latest('created_at');
        if ($status === 'archived') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
            if ($status === 'unread') {
                $query->whereNull('read_at');
            }
        }

        $page = $query->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $notification): array => $this->present($notification))->values(),
            'counts' => [
                'all' => (clone $request->user()->notifications())->whereNull('archived_at')->count(),
                'unread' => (clone $request->user()->notifications())->whereNull('archived_at')->whereNull('read_at')->count(),
                'archived' => (clone $request->user()->notifications())->whereNotNull('archived_at')->count(),
            ],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $this->findOwnNotification($request, $notification);
        $record->forceFill(['read_at' => $record->read_at ?? now()])->save();

        return response()->json(['data' => $this->present($record->refresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->whereNull('archived_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Unread notifications marked as read.']);
    }

    public function archive(Request $request, string $notification): JsonResponse
    {
        $record = $this->findOwnNotification($request, $notification);
        $record->forceFill(['read_at' => $record->read_at ?? now(), 'archived_at' => $record->archived_at ?? now()])->save();

        return response()->json(['data' => $this->present($record->refresh())]);
    }

    public function restore(Request $request, string $notification): JsonResponse
    {
        $record = $this->findOwnNotification($request, $notification);
        $record->forceFill(['archived_at' => null])->save();

        return response()->json(['data' => $this->present($record->refresh())]);
    }

    private function findOwnNotification(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $nested = data_get($data, 'data', []);
        $nested = is_array($nested) ? $nested : [];
        $type = data_get($nested, 'notification_type', data_get($data, 'notification_type', class_basename($notification->type)));
        $subject = data_get($data, 'subject', data_get($nested, 'subject'));
        $message = data_get($nested, 'message', data_get($data, 'message', data_get($data, 'description')));

        return [
            'id' => $notification->id,
            'title' => $this->plainText(is_string($subject) && $subject !== '' ? $subject : $this->humanize((string) $type)),
            'message' => $this->plainText(is_string($message) ? $message : ''),
            'category' => $this->humanize((string) $type),
            'created_at' => $notification->created_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
            'archived_at' => $notification->getAttribute('archived_at')
                ? Carbon::parse($notification->getAttribute('archived_at'))->toIso8601String()
                : null,
        ];
    }

    private function plainText(string $value): string
    {
        return trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function humanize(string $value): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $value));
    }
}

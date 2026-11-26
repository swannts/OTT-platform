<?php

namespace App\Support\Rbac;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AdminAuditLogger
{
    public function record(
        ?User $actor,
        string $action,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?array $before = null,
        ?array $after = null,
        string $outcome = 'success',
    ): AdminAuditLog {
        /** @var Request|null $request */
        $request = app()->bound('request') ? request() : null;
        $requestId = $request?->header('X-Request-ID');
        if (!is_string($requestId) || !preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $requestId)) {
            $requestId = (string) Str::uuid();
        }

        return AdminAuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId === null ? null : (string) $resourceId,
            'request_id' => $requestId,
            'before_values' => $before,
            'after_values' => $after,
            'outcome' => $outcome,
            'created_at' => now('UTC'),
        ]);
    }
}

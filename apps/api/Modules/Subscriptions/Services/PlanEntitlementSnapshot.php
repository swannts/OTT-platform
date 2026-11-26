<?php

namespace Modules\Subscriptions\Services;

final class PlanEntitlementSnapshot
{
    /** @return array<string, array{enabled:bool,limit:mixed}> */
    public static function limitations(mixed $snapshot): array
    {
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }
        if (!is_array($snapshot)) return [];

        $found = [];
        $walk = static function (array $node) use (&$walk, &$found): void {
            if (isset($node['slug']) && is_string($node['slug'])) {
                $found[$node['slug']] = [
                    'enabled' => (bool) ($node['limitation_value'] ?? $node['enabled'] ?? false),
                    'limit' => $node['limit'] ?? null,
                ];
            }
            foreach ($node as $child) {
                if (is_array($child)) $walk($child);
            }
        };
        $walk($snapshot);
        return $found;
    }

    public static function integerLimit(mixed $snapshot, string $slug): ?int
    {
        $limitation = self::limitations($snapshot)[$slug] ?? null;
        if (!$limitation || !$limitation['enabled']) return null;
        $value = $limitation['limit'];
        if (is_array($value)) $value = $value['value'] ?? null;
        return is_numeric($value) ? max(1, (int) $value) : null;
    }
}

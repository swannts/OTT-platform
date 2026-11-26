<?php

namespace App\Support\Staging;

use InvalidArgumentException;

final readonly class StagingProfile
{
    /** @param array<string, int> $counts */
    private function __construct(
        public string $name,
        public array $counts,
        public int $historyMonths,
        public int $batchSize,
    ) {}

    /** @param array<string, int> $overrides */
    public static function fromConfig(string $name, int $historyMonths, int $batchSize, array $overrides = []): self
    {
        $profiles = (array) config('staging-data.profiles');
        if (! isset($profiles[$name])) {
            throw new InvalidArgumentException("Unknown staging profile: {$name}");
        }
        if ($historyMonths < 1 || $historyMonths > 120) {
            throw new InvalidArgumentException('History months must be between 1 and 120.');
        }
        if ($batchSize < 1 || $batchSize > 10000) {
            throw new InvalidArgumentException('Batch size must be between 1 and 10000.');
        }

        $counts = $profiles[$name];
        $explicitOverrides = $overrides;
        foreach ($overrides as $entity => $count) {
            if (! array_key_exists($entity, $counts) || $count < 0) {
                throw new InvalidArgumentException("Unsupported or invalid staging count override: {$entity}");
            }
            $counts[$entity] = $count;
        }
        if (array_key_exists('customers', $explicitOverrides)) {
            $baseCustomerCount = max(1, (int) $profiles[$name]['customers']);
            foreach (['profiles', 'devices'] as $dependent) {
                if (! array_key_exists($dependent, $explicitOverrides)) {
                    $counts[$dependent] = (int) round($counts['customers'] * $profiles[$name][$dependent] / $baseCustomerCount);
                }
            }
        }
        if ($counts['staff'] < count(array_keys(\App\Support\Rbac\RbacRegistry::roles()))) {
            throw new InvalidArgumentException('Staging staff count must include at least one account for every canonical administrative role.');
        }
        if ($counts['customers'] === 0 && ($counts['profiles'] > 0 || $counts['devices'] > 0)) {
            throw new InvalidArgumentException('Profile and device targets require at least one customer.');
        }
        if ($counts['customers'] > 0 && ($counts['profiles'] < $counts['customers'] || $counts['profiles'] > $counts['customers'] * 4)) {
            throw new InvalidArgumentException('Profile target must allow at least one and no more than four profiles per customer.');
        }
        if ($counts['subscriptions'] > 0 && ($counts['customers'] === 0 || $counts['plans'] === 0)) {
            throw new InvalidArgumentException('Subscription targets require staging customers and plans.');
        }
        if ($counts['payments'] > 0 && $counts['plans'] === 0) {
            throw new InvalidArgumentException('Payment targets require staging plans.');
        }
        if ($counts['movies'] + $counts['series'] > 0 && $counts['cast_crew'] < 4) {
            throw new InvalidArgumentException('Catalog content requires at least three cast members and one director.');
        }

        return new self($name, $counts, $historyMonths, $batchSize);
    }
}

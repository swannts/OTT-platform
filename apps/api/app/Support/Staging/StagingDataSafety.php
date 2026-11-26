<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingDataSafety
{
    /** @return list<string> */
    public function violations(string $profile): array
    {
        $resourceChecks = $profile === 'large' ? $this->largeProfileViolations() : [];
        $checks = [
            'staging seeding must be explicitly enabled' => (bool) config('staging-data.enabled'),
            'APP_ENV must be local, testing, or staging' => app()->environment(['local', 'testing', 'staging']),
            'staging generation currently requires an explicit MySQL connection' => config('database.connections.'.config('database.default').'.driver') === 'mysql',
            'profile must be small, standard, or large' => array_key_exists($profile, (array) config('staging-data.profiles')),
            'an explicitly allowed staging database name is required' => filled(config('staging-data.allowed_database')),
            'an explicitly allowed staging database host is required' => filled(config('staging-data.allowed_database_host')),
            'the active database must exactly match the allowed staging database' => (string) config('database.connections.'.config('database.default').'.database') !== ''
                && hash_equals((string) config('staging-data.allowed_database'), (string) config('database.connections.'.config('database.default').'.database')),
            'the active database host must exactly match the allowed staging host' => (string) config('database.connections.'.config('database.default').'.host') !== ''
                && hash_equals((string) config('staging-data.allowed_database_host'), (string) config('database.connections.'.config('database.default').'.host')),
            'the allowed database must not be identified as production' => preg_match('/(^|[_-])(prod|production|live)([_-]|$)/i', (string) config('staging-data.allowed_database')) === 0,
            'an isolated cache prefix is required and must be active' => filled(config('staging-data.isolated_cache_prefix'))
                && hash_equals((string) config('staging-data.isolated_cache_prefix'), (string) config('cache.prefix')),
            'the selected cache store must support cross-process locks' => in_array(config('cache.default'), ['file', 'database', 'redis', 'memcached'], true),
            'queue isolation must be explicitly confirmed and use a non-external connection' => (bool) config('staging-data.queue_isolated')
                && in_array(config('queue.default'), ['database', 'null'], true)
                && (config('queue.default') !== 'database'
                    || in_array(config('queue.connections.database.connection'), [null, config('database.default')], true)),
            'search index isolation must be explicitly confirmed' => (bool) config('staging-data.search_isolated'),
            'an explicit non-production search endpoint is required' => $this->hasSafeSearchEndpoint(),
            'a reserved staging-only search index prefix is required' => preg_match('/^ott_staging_[a-z0-9_]{2,24}$/', (string) config('staging-data.search_index_prefix')) === 1,
            'media storage isolation must be explicitly confirmed' => (bool) config('staging-data.media_isolated')
                && in_array(config('filesystems.default'), ['local', 'public'], true),
            'external email and push delivery must be disabled' => (bool) config('staging-data.external_delivery_disabled')
                && in_array(config('mail.default'), ['array', 'log'], true),
            'synthetic payment gateway must be selected' => config('staging-data.payment_gateway') === 'synthetic',
            'a private staging staff password of at least 16 characters is required' => mb_strlen((string) config('staging-data.staff_password')) >= 16,
            'a private staging customer password of at least 16 characters is required' => mb_strlen((string) config('staging-data.customer_password')) >= 16,
        ];

        return [...array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed)), ...$resourceChecks];
    }

    private function hasSafeSearchEndpoint(): bool
    {
        $url = parse_url((string) config('staging-data.search_index_url'));
        if (! is_array($url) || ! in_array($url['scheme'] ?? null, ['http', 'https'], true) || empty($url['host'])) return false;
        return preg_match('/(^|[-.])(prod|production|live)([-.]|$)/i', (string) $url['host']) === 0;
    }

    public function assertSafe(string $profile): void
    {
        $violations = $this->violations($profile);
        if ($violations !== []) {
            throw new RuntimeException("Staging data safety checks failed:\n- ".implode("\n- ", $violations));
        }

        $connection = DB::connection();
        if ($connection->getName() !== config('database.default')) {
            throw new RuntimeException('Staging data requires an explicitly selected primary database connection.');
        }

        $databaseIdentity = $connection->selectOne('SELECT DATABASE() AS database_name');
        $connectedDatabase = (string) ($databaseIdentity->database_name ?? '');
        if ($connectedDatabase === '' || ! hash_equals((string) config('staging-data.allowed_database'), $connectedDatabase)) {
            throw new RuntimeException('The live MySQL connection did not reach the explicitly allowed staging database.');
        }
    }

    /** @return list<string> */
    private function largeProfileViolations(): array
    {
        $violations = [];
        if (! config('staging-data.large_profile_capacity_confirmed')) {
            $violations[] = 'large profile requires explicit database-capacity confirmation';
        }

        $memoryLimit = $this->memoryLimitBytes();
        $requiredMemory = (int) config('staging-data.large_min_memory_mb') * 1024 * 1024;
        if ($memoryLimit < $requiredMemory) {
            $violations[] = 'PHP memory_limit is below the configured Large-profile minimum';
        }

        $freeDiskBytes = is_dir(storage_path()) ? disk_free_space(storage_path()) : false;
        $requiredDisk = (int) config('staging-data.large_min_free_disk_gb') * 1024 * 1024 * 1024;
        if ($freeDiskBytes === false || $freeDiskBytes < $requiredDisk) {
            $violations[] = 'available local storage is below the configured Large-profile minimum';
        }

        return $violations;
    }

    private function memoryLimitBytes(): int
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        if (! preg_match('/^(\d+)\s*([KMG]?)$/i', $limit, $matches)) {
            return 0;
        }

        $multipliers = ['' => 1, 'K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3];

        return (int) $matches[1] * $multipliers[strtoupper($matches[2])];
    }
}

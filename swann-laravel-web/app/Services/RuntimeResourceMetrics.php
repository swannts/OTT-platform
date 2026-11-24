<?php

namespace App\Services;

final class RuntimeResourceMetrics
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        [$memoryUsed, $memoryLimit] = $this->memory();
        [$cpuUsageSeconds] = $this->cpu();
        $storagePath = storage_path();
        $storageAvailable = is_dir($storagePath) ? @disk_free_space($storagePath) : false;
        $storageTotal = is_dir($storagePath) ? @disk_total_space($storagePath) : false;

        return [
            'scope' => 'laravel_container',
            'memory' => [
                'used_bytes' => $memoryUsed,
                'limit_bytes' => $memoryLimit,
                'used_percent' => $memoryUsed !== null && $memoryLimit !== null && $memoryLimit > 0
                    ? round(($memoryUsed / $memoryLimit) * 100, 1)
                    : null,
            ],
            'cpu' => ['usage_seconds' => $cpuUsageSeconds],
            'storage' => [
                'available_bytes' => $storageAvailable === false ? null : (int) $storageAvailable,
                'total_bytes' => $storageTotal === false ? null : (int) $storageTotal,
                'available_percent' => $storageAvailable !== false && $storageTotal !== false && $storageTotal > 0
                    ? round(($storageAvailable / $storageTotal) * 100, 1)
                    : null,
            ],
        ];
    }

    /** @return array{?int, ?int} */
    private function memory(): array
    {
        $used = $this->readInteger('/sys/fs/cgroup/memory.current')
            ?? $this->readInteger('/sys/fs/cgroup/memory/memory.usage_in_bytes');
        $limitText = $this->readText('/sys/fs/cgroup/memory.max')
            ?? $this->readText('/sys/fs/cgroup/memory/memory.limit_in_bytes');

        if ($limitText === null || $limitText === 'max' || ! ctype_digit($limitText)) {
            return [$used, null];
        }

        $limit = (int) $limitText;
        if ($limit >= PHP_INT_MAX - 4096) {
            $limit = null;
        }

        return [$used, $limit];
    }

    /** @return array{?float} */
    private function cpu(): array
    {
        $usage = $this->readCgroupV2CpuUsage() ?? $this->readInteger('/sys/fs/cgroup/cpuacct/cpuacct.usage');

        return [$usage === null ? null : round($usage / 1_000_000_000, 2)];
    }

    private function readCgroupV2CpuUsage(): ?int
    {
        $stats = $this->readText('/sys/fs/cgroup/cpu.stat');
        if ($stats === null) {
            return null;
        }

        foreach (preg_split('/\R/', $stats) ?: [] as $line) {
            if (preg_match('/^usage_usec\s+(\d+)$/', trim($line), $matches)) {
                return (int) $matches[1] * 1000;
            }
        }

        return null;
    }

    private function readInteger(string $path): ?int
    {
        $value = $this->readText($path);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    private function readText(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $value = @file_get_contents($path);

        return $value === false ? null : trim($value);
    }
}

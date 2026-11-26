<?php

namespace App\Support\Staging;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

final readonly class StagingGenerationContext
{
    public Randomizer $random;
    public CarbonImmutable $referenceDate;

    public function __construct(
        public string $datasetId,
        public int $seed,
        public StagingProfile $profile,
        string $referenceDate,
    ) {
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{2,99}$/', $datasetId)) {
            throw new InvalidArgumentException('Dataset identifier must be 3-100 safe characters.');
        }
        if ($seed < 0) {
            throw new InvalidArgumentException('Random seed must be a non-negative integer.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
            throw new InvalidArgumentException('Reference date must use YYYY-MM-DD format.');
        }
        $parsedDate = CarbonImmutable::createFromFormat('!Y-m-d', $referenceDate);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $referenceDate) {
            throw new InvalidArgumentException('Reference date is not a valid calendar date.');
        }
        $this->referenceDate = $parsedDate;
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public function historyStart(): CarbonImmutable
    {
        return $this->referenceDate->subMonths($this->profile->historyMonths);
    }
}

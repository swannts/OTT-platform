<?php

namespace Database\Seeders\Concerns;

use LogicException;

trait LocalOnlySeeder
{
    protected function ensureLocalOrTestingEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException(static::class.' contains development fixtures or destructive resets and may only run in local or testing.');
        }
    }
}

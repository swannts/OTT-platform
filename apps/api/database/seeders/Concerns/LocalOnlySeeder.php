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

    protected function ensureDemoCatalogReplacementEnabled(): void
    {
        if (! (bool) config('app.demo_catalog_replace', false)) {
            throw new LogicException(
                static::class.' replaces shared catalog tables. Set DEMO_CATALOG_REPLACE=true only for an intentional local demo reset.'
            );
        }
    }
}

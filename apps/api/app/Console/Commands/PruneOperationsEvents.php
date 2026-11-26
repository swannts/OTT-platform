<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneOperationsEvents extends Command
{
    protected $signature = 'operations:prune-events';

    protected $description = 'Remove sanitized operational event aggregates older than 30 days.';

    public function handle(): int
    {
        if (! Schema::hasTable('operations_events')) {
            return self::SUCCESS;
        }

        $deleted = DB::table('operations_events')->where('created_at', '<', now('UTC')->subDays(30))->delete();
        $this->info("Pruned {$deleted} expired operational event aggregates.");

        return self::SUCCESS;
    }
}

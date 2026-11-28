<?php

namespace App\Console\Commands;

use App\Models\MediaUploadSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanExpiredMediaUploads extends Command
{
    protected $signature = 'media:uploads-clean-expired';

    protected $description = 'Remove temporary files for expired media upload sessions.';

    public function handle(): int
    {
        $count = 0;
        MediaUploadSession::query()
            ->whereIn('status', ['uploading', 'assembling'])
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->chunkById(100, function ($sessions) use (&$count): void {
                foreach ($sessions as $session) {
                    Storage::disk('private')->deleteDirectory("media/uploads/{$session->id}");
                    $session->update(['status' => 'expired']);
                    $count++;
                }
            }, 'id');

        $this->info("Removed temporary files for {$count} expired media upload sessions.");
        return self::SUCCESS;
    }
}

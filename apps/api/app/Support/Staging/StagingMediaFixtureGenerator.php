<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

final class StagingMediaFixtureGenerator
{
    /** @return array{assets: int, linked_titles: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = min((int) ($context->profile->counts['media_assets'] ?? 0), 50);
        if ($target === 0) {
            return ['assets' => 0, 'linked_titles' => 0];
        }
        $disk = Storage::disk('private');
        $root = $disk->path('media/staging/'.$context->datasetId);
        if (! is_dir($root) && ! mkdir($root, 0750, true) && ! is_dir($root)) {
            throw new RuntimeException('Could not create the isolated staging media directory.');
        }
        $ffmpeg = (string) config('staging-data.ffmpeg_binary', 'ffmpeg');
        $ffprobe = (string) config('staging-data.ffprobe_binary', 'ffprobe');
        $this->assertBinary($ffmpeg, ['-version']);
        $this->assertBinary($ffprobe, ['-version']);

        $titles = DB::table('staging_dataset_records as owned')
            ->join('entertainments', 'entertainments.id', '=', 'owned.record_id')
            ->where('owned.dataset_id', $context->datasetId)
            ->where('owned.entity_type', 'entertainment')
            ->where('entertainments.type', 'movie')
            ->whereNull('entertainments.media_asset_id')
            ->orderBy('entertainments.id')
            ->limit($target)
            ->get(['entertainments.id', 'entertainments.name']);
        $existing = DB::table('staging_dataset_records')
            ->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'media_asset')
            ->count();
        $remaining = max(0, $target - $existing);
        $titles = $titles->take($remaining);
        foreach ($titles as $offset => $title) {
            $number = $existing + $offset + 1;
            $base = 'media/staging/'.$context->datasetId.'/fixture-'.$number;
            $sourcePath = $base.'/source.mp4';
            $source = $disk->path($sourcePath);
            $renditionPath = $base.'/renditions';
            $renditions = $disk->path($renditionPath);
            if (! is_dir(dirname($source)) && ! mkdir(dirname($source), 0750, true) && ! is_dir(dirname($source))) {
                throw new RuntimeException('Could not create a staging media fixture directory.');
            }
            if (! is_dir($renditions) && ! mkdir($renditions, 0750, true) && ! is_dir($renditions)) {
                throw new RuntimeException('Could not create a staging media rendition directory.');
            }

            $this->run($ffmpeg, [
                '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
                '-f', 'lavfi', '-i', 'testsrc2=size=1280x720:rate=24',
                '-f', 'lavfi', '-i', 'sine=frequency='.(420 + ($number * 13)).':sample_rate=48000',
                '-t', '12', '-c:v', 'libx264', '-preset', 'veryfast', '-pix_fmt', 'yuv420p',
                '-c:a', 'aac', '-b:a', '96k', '-metadata', 'title='.(string) $title->name,
                '-movflags', '+faststart', $source,
            ]);
            $metadata = $this->probe($ffprobe, $source);
            $this->transcodeHls($ffmpeg, $source, $renditions);
            $this->run($ffmpeg, ['-hide_banner', '-loglevel', 'error', '-nostdin', '-y', '-i', $source, '-map', '0:v:0', '-map', '0:a?', '-c:v', 'libx264', '-preset', 'veryfast', '-c:a', 'aac', '-f', 'dash', '-seg_duration', '6', $renditions.'/manifest.mpd']);
            $this->run($ffmpeg, ['-hide_banner', '-loglevel', 'error', '-nostdin', '-y', '-ss', '2', '-i', $source, '-frames:v', '1', '-vf', 'scale=640:-2', $renditions.'/poster.jpg']);
            $this->run($ffmpeg, ['-hide_banner', '-loglevel', 'error', '-nostdin', '-y', '-i', $source, '-t', '6', '-c:v', 'libx264', '-preset', 'veryfast', '-c:a', 'aac', $renditions.'/preview.mp4']);

            $vtt = "WEBVTT\n\n00:00:00.000 --> 00:00:06.000\nSynthetic staging media fixture.\n\n00:00:06.000 --> 00:00:12.000\nGenerated for local playback validation.\n";
            $srt = "1\n00:00:00,000 --> 00:00:06,000\nSynthetic staging media fixture.\n\n2\n00:00:06,000 --> 00:00:12,000\nGenerated for local playback validation.\n";
            $subtitleVtt = $base.'/tracks/en.vtt';
            $subtitleSrt = $base.'/tracks/en.srt';
            $disk->put($subtitleVtt, $vtt);
            $disk->put($subtitleSrt, $srt);
            file_put_contents($renditions.'/subtitles-en.m3u8', "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:12\n#EXT-X-MEDIA-SEQUENCE:0\n#EXT-X-PLAYLIST-TYPE:VOD\n#EXTINF:12.000,\n../tracks/en.vtt\n#EXT-X-ENDLIST\n");
            $masterPath = $renditions.'/master.m3u8';
            file_put_contents($masterPath, "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID=\"audio\",NAME=\"Original audio\",LANGUAGE=\"en\",DEFAULT=YES,AUTOSELECT=YES,URI=\"audio.m3u8\"\n#EXT-X-MEDIA:TYPE=SUBTITLES,GROUP-ID=\"subs\",NAME=\"English\",LANGUAGE=\"en\",DEFAULT=NO,AUTOSELECT=YES,URI=\"subtitles-en.m3u8\"\n#EXT-X-STREAM-INF:BANDWIDTH=900000,RESOLUTION=640x360,AUDIO=\"audio\",SUBTITLES=\"subs\"\n360p.m3u8\n#EXT-X-STREAM-INF:BANDWIDTH=2800000,RESOLUTION=1280x720,AUDIO=\"audio\",SUBTITLES=\"subs\"\n720p.m3u8\n");

            $size = filesize($source);
            if ($size === false || $size < 1 || ! is_file($renditions.'/master.m3u8') || ! is_file($renditions.'/manifest.mpd') || ! is_file($renditions.'/poster.jpg')) {
                throw new RuntimeException('Generated media fixture failed its output-file checks.');
            }
            $publicPosterPath = 'staging/'.$context->datasetId.'/fixture-'.$number.'/poster.jpg';
            $posterContents = file_get_contents($renditions.'/poster.jpg');
            if (! is_string($posterContents) || ! Storage::disk('public')->put($publicPosterPath, $posterContents)) {
                throw new RuntimeException('Could not create the public catalog poster for a staging media fixture.');
            }
            $sha256 = hash_file('sha256', $source);
            if (! is_string($sha256)) {
                throw new RuntimeException('Could not calculate a media fixture checksum.');
            }
            $now = now();
            DB::transaction(function () use ($context, $title, $number, $base, $sourcePath, $renditionPath, $publicPosterPath, $size, $sha256, $metadata, $now): void {
                $assetId = DB::table('media_assets')->insertGetId([
                    'uploaded_by' => null,
                    'display_name' => 'Staging media fixture '.$number,
                    'category' => 'movie',
                    'original_name' => 'staging-fixture-'.$number.'.mp4',
                    'disk' => 'private',
                    'source_path' => $sourcePath,
                    'rendition_path' => $renditionPath,
                    'mime_type' => 'video/mp4',
                    'extension' => 'mp4',
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                    'status' => 'ready',
                    'quality_status' => 'pending_inspection',
                    'is_active_version' => true,
                    'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                    'tracks' => json_encode([['kind' => 'subtitle', 'language' => 'en']], JSON_THROW_ON_ERROR),
                    'ready_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $jobId = DB::table('media_processing_jobs')->insertGetId([
                    'media_asset_id' => $assetId,
                    'type' => 'staging.fixture.transcode',
                    'status' => 'completed',
                    'attempts' => 1,
                    'max_attempts' => 1,
                    'idempotency_key' => 'staging-'.$context->datasetId.'-media-'.$number,
                    'started_at' => $now,
                    'completed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $trackOwnerRows = [];
                foreach ([['en.vtt', 'text/vtt'], ['en.srt', 'application/x-subrip']] as [$file, $mime]) {
                    $trackPath = $base.'/tracks/'.$file;
                    $trackId = DB::table('media_asset_tracks')->insertGetId([
                        'media_asset_id' => $assetId,
                        'kind' => 'subtitle',
                        'language' => 'en',
                        'original_name' => $file,
                        'disk' => 'private',
                        'path' => $trackPath,
                        'mime_type' => $mime,
                        'size_bytes' => filesize(Storage::disk('private')->path($trackPath)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $trackOwnerRows[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'media_asset_track', 'entity_key' => 'fixture-'.$number.'-'.$file, 'record_id' => (string) $trackId, 'created_at' => $now, 'updated_at' => $now];
                }
                $posterUrl = Storage::disk('public')->url($publicPosterPath);
                DB::table('entertainments')->where('id', $title->id)->update([
                    'media_asset_id' => $assetId, 'poster_url' => $posterUrl,
                    'thumbnail_url' => $posterUrl, 'poster_tv_url' => $posterUrl, 'updated_at' => $now,
                ]);
                DB::table('staging_dataset_records')->insert([
                    ['dataset_id' => $context->datasetId, 'entity_type' => 'media_asset', 'entity_key' => 'fixture-'.$number, 'record_id' => (string) $assetId, 'created_at' => $now, 'updated_at' => $now],
                    ['dataset_id' => $context->datasetId, 'entity_type' => 'media_processing_job', 'entity_key' => 'fixture-'.$number, 'record_id' => (string) $jobId, 'created_at' => $now, 'updated_at' => $now],
                    ['dataset_id' => $context->datasetId, 'entity_type' => 'entertainment_media_link', 'entity_key' => (string) $title->id, 'record_id' => (string) $assetId, 'created_at' => $now, 'updated_at' => $now],
                    ...$trackOwnerRows,
                ]);
            });
        }

        return [
            'assets' => (int) DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'media_asset')->count(),
            'linked_titles' => (int) DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'entertainment_media_link')->count(),
        ];
    }

    /** @return array{Format: array{duration: string, format_name: string}, Streams: list<array<string, mixed>>} */
    private function probe(string $binary, string $source): array
    {
        $process = new Process([$binary, '-v', 'error', '-show_entries', 'format=duration,format_name:stream=index,codec_type,codec_name,width,height:stream_tags=language', '-of', 'json', $source]);
        $process->setTimeout(120);
        $process->mustRun();
        $metadata = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($metadata['streams'] ?? null) || ! is_array($metadata['format'] ?? null) || (float) ($metadata['format']['duration'] ?? 0) <= 0) {
            throw new RuntimeException('FFprobe returned invalid staging source metadata.');
        }
        return ['Format' => $metadata['format'], 'Streams' => $metadata['streams']];
    }

    private function transcodeHls(string $binary, string $source, string $output): void
    {
        foreach ([360, 720] as $height) {
            $this->run($binary, ['-hide_banner', '-loglevel', 'error', '-nostdin', '-y', '-i', $source, '-map', '0:v:0', '-an', '-vf', 'scale=-2:'.$height, '-c:v', 'libx264', '-preset', 'veryfast', '-pix_fmt', 'yuv420p', '-b:v', ($height === 360 ? '700k' : '2200k'), '-f', 'hls', '-hls_time', '6', '-hls_playlist_type', 'vod', '-hls_segment_filename', $output.'/'.$height.'p_%03d.ts', $output.'/'.$height.'p.m3u8']);
        }
        $this->run($binary, ['-hide_banner', '-loglevel', 'error', '-nostdin', '-y', '-i', $source, '-map', '0:a:0', '-vn', '-c:a', 'aac', '-b:a', '96k', '-f', 'hls', '-hls_time', '6', '-hls_playlist_type', 'vod', '-hls_segment_filename', $output.'/audio_%03d.ts', $output.'/audio.m3u8']);
    }

    /** @param list<string> $arguments */
    private function run(string $binary, array $arguments): void
    {
        $process = new Process([$binary, ...$arguments]);
        $process->setTimeout(600);
        $process->mustRun();
    }

    /** @param list<string> $arguments */
    private function assertBinary(string $binary, array $arguments): void
    {
        try {
            $process = new Process([$binary, ...$arguments]);
            $process->setTimeout(20);
            $process->mustRun();
        } catch (\Throwable $exception) {
            throw new RuntimeException("Staging media fixtures require the configured executable {$binary}.", previous: $exception);
        }
    }
}

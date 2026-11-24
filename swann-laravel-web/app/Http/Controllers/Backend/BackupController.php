<?php

namespace App\Http\Controllers\Backend;

use App\Authorizable;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laracasts\Flash\Flash;
use Throwable;

class BackupController extends Controller
{
    use Authorizable;

    public function __construct()
    {
        // Page Title
        $this->module_title = 'backup.title';

        // module name
        $this->module_name = 'backups';

        // directory path of the module
        $this->module_path = 'backups';

        // module icon
        $this->module_icon = 'fas fa-archive';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $module_title = $this->module_title;
        $module_name = $this->module_name;
        $module_path = $this->module_path;
        $module_icon = $this->module_icon;
        $module_name_singular = Str::singular($module_name);

        $module_action = 'List';

        $$module_name = [];

        foreach (config('backup.backup.destination.disks', []) as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                foreach ($disk->files(config('backup.backup.name')) as $filePath) {
                    if (! str_ends_with(strtolower($filePath), '.zip') || ! $disk->exists($filePath)) {
                        continue;
                    }

                    $modified = $disk->lastModified($filePath);
                    $size = $disk->size($filePath);
                    $$module_name[] = [
                        'disk' => $diskName,
                        'file_name' => basename($filePath),
                        'file_size_byte' => $size,
                        'file_size' => humanFilesize($size),
                        'last_modified_timestamp' => $modified,
                        'date_created' => Carbon::createFromTimestamp($modified)->isoFormat('llll'),
                        'date_ago' => Carbon::createFromTimestamp($modified)->diffForHumans(),
                    ];
                }
            } catch (Throwable $exception) {
                Log::warning('Backup listing failed for a configured destination.', [
                    'disk' => $diskName,
                    'exception' => $exception::class,
                ]);
            }
        }

        usort($$module_name, static fn (array $left, array $right): int => $right['last_modified_timestamp'] <=> $left['last_modified_timestamp']);

        return view(
            "backend.$module_path.backups",
            compact('module_title', 'module_name', "$module_name", 'module_path', 'module_icon', 'module_action', 'module_name_singular')
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        try {
            // start the backup process
            $exitCode = Artisan::call('backup:run');

            if ($exitCode !== 0) {
                Log::error('Laravel backup command failed.', ['exit_code' => $exitCode]);
                Flash::error('The backup could not be created. Check the application logs.');

                return redirect()->back();
            }

            flash("<i class='fas fa-check'></i> New backup created")->success()->important();

            return redirect()->back();
        } catch (Throwable $exception) {
            Log::error('Laravel backup command raised an exception.', ['exception' => $exception::class]);
            Flash::error('The backup could not be created. Check the application logs.');

            return redirect()->back();
        }
    }

    /**
     * Downloads a backup zip file from one of the configured destination disks.
     */
    public function download(Request $request, string $file_name)
    {
        $diskName = $this->requestedDisk($request);
        $file = $this->backupPath($file_name);
        $disk = Storage::disk($diskName);

        if ($disk->exists($file)) {
            return $disk->download($file, $file_name);
        } else {
            abort(404, "The backup file doesn't exist.");
        }
    }

    /**
     * Deletes a backup file.
     */
    public function delete(Request $request, string $file_name)
    {
        $diskName = $this->requestedDisk($request);
        $file = $this->backupPath($file_name);
        $disk = Storage::disk($diskName);

        if ($disk->exists($file)) {
            $disk->delete($file);
            flash('Backup deleted')->success();

            return redirect()->back();
        } else {
            abort(404, "The backup file doesn't exist.");
        }
    }

    private function requestedDisk(Request $request): string
    {
        $disk = (string) $request->input('disk', '');
        abort_unless(in_array($disk, config('backup.backup.destination.disks', []), true), 404);

        return $disk;
    }

    private function backupPath(string $fileName): string
    {
        abort_unless(
            basename($fileName) === $fileName
                && preg_match('/^[A-Za-z0-9._-]+\.zip$/i', $fileName)
                && ! str_contains($fileName, '..'),
            404
        );

        return trim((string) config('backup.backup.name'), '/').'/'.$fileName;
    }
}

<?php

namespace App\Http\Controllers\Backend\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use kornrunner\Blurhash\Blurhash;

class ImageBlurHashController extends Controller
{
    public function show(Request $request)
    {
        $imageUrl = $request->query('url');
        $imagePath = is_string($imageUrl) ? parse_url($imageUrl, PHP_URL_PATH) : null;

        if (!is_string($imagePath) || !str_starts_with($imagePath, '/storage/')) {
            return response()->json(['message' => 'Only public storage images are supported.'], 404);
        }

        $relativePath = rawurldecode(substr($imagePath, strlen('/storage/')));
        $segments = explode('/', $relativePath);
        if ($relativePath === '' || str_contains($relativePath, "\0") || in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return response()->json(['message' => 'Invalid image path.'], 404);
        }

        $disk = Storage::disk('public');
        $filePath = $disk->path($relativePath);
        if (!is_file($filePath) || !is_readable($filePath) || filesize($filePath) > 30 * 1024 * 1024) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $cacheKey = 'image_blurhash:' . sha1($relativePath . ':' . filemtime($filePath) . ':' . filesize($filePath));
        $hash = Cache::remember($cacheKey, now()->addYear(), function () use ($filePath) {
            $source = @imagecreatefromstring(file_get_contents($filePath));
            if ($source === false) {
                return null;
            }

            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $scale = min(1, 32 / max($sourceWidth, $sourceHeight));
            $width = max(1, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * $scale));
            $thumbnail = imagecreatetruecolor($width, $height);
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

            $pixels = [];
            for ($y = 0; $y < $height; $y++) {
                $row = [];
                for ($x = 0; $x < $width; $x++) {
                    $color = imagecolorat($thumbnail, $x, $y);
                    $row[] = [($color >> 16) & 0xff, ($color >> 8) & 0xff, $color & 0xff];
                }
                $pixels[] = $row;
            }

            imagedestroy($thumbnail);
            imagedestroy($source);

            return Blurhash::encode($pixels, 4, 3);
        });

        if (!is_string($hash) || $hash === '') {
            return response()->json(['message' => 'Could not create image placeholder.'], 422);
        }

        return response()->json(['blur_hash' => $hash])->header('Cache-Control', 'public, max-age=31536000');
    }
}

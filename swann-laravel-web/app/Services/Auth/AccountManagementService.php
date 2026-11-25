<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Entertainment\Models\ContinueWatch;
use Modules\Entertainment\Models\EntertainmentDownload;
use Modules\Entertainment\Models\UserReminder;
use Modules\Entertainment\Models\Watchlist;
use Modules\NotificationTemplate\Jobs\SendBulkNotification;
use Modules\Subscriptions\Models\Subscription;
use Spatie\Image\Image;
use Throwable;

class AccountManagementService
{
    /**
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    public function changePassword(User $authenticatedUser, Request $request): array
    {
        $user = User::where('id', $authenticatedUser->id)->first();
        if (! $user) {
            return ['ok' => false, 'message' => __('messages.user_notfound'), 'status' => 400];
        }

        if ((string) $request->new_password === (string) $request->old_password) {
            return ['ok' => false, 'message' => __('messages.same_pass'), 'status' => 400];
        }

        if (! Hash::check((string) $request->old_password, (string) $user->password)) {
            return ['ok' => false, 'message' => __('messages.check_old_password'), 'status' => 200];
        }

        $currentToken = $authenticatedUser->currentAccessToken();

        $success = DB::transaction(function () use ($user, $currentToken, $request): array {
            if ($currentToken && method_exists($currentToken, 'getKey')) {
                DB::table('playback_sessions')
                    ->where('user_id', $user->id)
                    ->where('device_token_id', $currentToken->getKey())
                    ->whereNull('ended_at')
                    ->update(['ended_at' => now(), 'updated_at' => now()]);
                Device::where('user_id', $user->id)
                    ->where('sanctum_token_id', $currentToken->getKey())
                    ->delete();
                if (method_exists($currentToken, 'delete')) {
                    $currentToken->delete();
                } elseif (method_exists($currentToken, 'revoke')) {
                    $currentToken->revoke();
                }
            }

            $user->fill([
                'password' => Hash::make((string) $request->new_password),
            ])->save();

            $issuedToken = $user->createToken(setting('app_name'));
            Device::create([
                'user_id' => $user->id,
                'sanctum_token_id' => $issuedToken->accessToken->getKey(),
                'device_id' => (string) Str::uuid(),
                'device_name' => $request->input('device_name', 'OTT device'),
                'platform' => $request->input('platform', 'unknown'),
                'active_profile' => $user->userMultiProfile()->value('id'),
                'session_id' => session()->getId(),
                'last_activity' => now(),
            ]);

            return [
                'api_token' => $issuedToken->plainTextToken,
                'name' => $user->name,
            ];
        });

        SendBulkNotification::dispatch([
            'notification_type' => 'change_password',
            'user_id' => $user->id,
            'user_name' => $user->full_name,
        ])->onQueue('notifications');

        return [
            'ok' => true,
            'data' => $success,
            'message' => __('messages.pass_successfull'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateProfile(User $user, Request $request): array
    {
        $data = $request->except([
            'is_banned', 'status', 'user_type', 'password', 'id', 'email_verified_at',
            'is_subscribe', 'pin', 'otp', 'is_parental_lock_enable', 'deleted_at',
            'created_at', 'updated_at', 'file_url',
        ]);

        $user->update($data);

        $fileUrl = $user->file_url;
        if ($request->hasFile('file_url')) {
            $file = $this->stripExif($request->file('file_url'));
            $activeDisk = env('ACTIVE_STORAGE', 'local');
            $filename = $file->getClientOriginalName();

            if ($activeDisk === 'local') {
                $filePath = $file->storeAs('swann-laravel', $filename, 'public');
                $storedUrl = '/storage/'.$filePath;
            } else {
                $folderPath = 'swann-laravel/'.$filename;
                Storage::disk($activeDisk)->put($folderPath, file_get_contents($file));
                $storedUrl = env('DO_SPACES_URL').'/'.$folderPath;
            }

            $fileUrl = extractFileNameFromUrl($storedUrl, 'users');
        }

        $user->update(['file_url' => $fileUrl]);
        $userData = User::find($user->id);
        $userData->save();

        $userData['user_role'] = $user->getRoleNames();
        $userData['file_url'] = setBaseUrlWithFileName($user->file_url, 'image', 'users');

        $payload = $userData->toArray();
        $payload['date_of_birth'] = $userData->date_of_birth
            ? Carbon::parse($userData->date_of_birth)->format('Y-m-d')
            : null;
        unset($payload['roles'], $payload['media']);

        return $payload;
    }

    public function deleteAccount(int $userId): bool
    {
        $user = User::with('userMultiProfile')->where('id', $userId)->first();
        if (! $user) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            Device::where('user_id', $user->id)->forceDelete();
            $user->userMultiProfile()->forceDelete();
            Subscription::where('user_id', $user->id)->update(['status' => 'deactivated']);
            ContinueWatch::where('user_id', $user->id)->delete();
            Watchlist::where('user_id', $user->id)->delete();
            EntertainmentDownload::where('user_id', $user->id)->delete();
            UserReminder::where('user_id', $user->id)->delete();
            $user->forceDelete();
        });

        return true;
    }

    private function stripExif(UploadedFile $file): UploadedFile
    {
        try {
            if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
                return $file;
            }

            $tempPath = sys_get_temp_dir().'/'.uniqid('clean_').'_'.$file->getClientOriginalName();
            Image::load($file->getRealPath())->optimize()->save($tempPath);

            return new UploadedFile(
                $tempPath,
                $file->getClientOriginalName(),
                $file->getMimeType(),
                null,
                true
            );
        } catch (Throwable $e) {
            Log::warning('Could not strip image EXIF metadata.', ['exception' => $e::class]);

            return $file;
        }
    }
}

<?php

namespace App\Services\Settings;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SettingsAccountService
{
    public function updateProfile(User $user, Request $request): void
    {
        $data = User::findOrFail($user->id);
        $requestData = $request->except('profile_image');
        $data->update($requestData);

        if ($request->custom_fields_data) {
            $data->updateCustomFieldData(json_decode($request->custom_fields_data));
        }

        if ($request->file('profile_image')) {
            storeMediaFile($data, $request->file('profile_image'), 'profile_image', 'avatar');
        }
    }

    /**
     * @return array{ok: bool, status?: int, type: string, message: string}
     */
    public function changePassword(?User $user, string $oldPassword, string $newPassword): array
    {
        $isDemoAdmin = $user && ($user->hasRole('demo_admin') || $user->user_type === 'demo_admin');
        if (setting('demo_login', 0) == 1 && $isDemoAdmin) {
            return [
                'ok' => false,
                'status' => 403,
                'type' => 'error',
                'message' => __('messages.user_does_not_have_permission_to_change_password'),
            ];
        }

        if (setting('demo_login')) {
            return [
                'ok' => false,
                'status' => 403,
                'type' => 'error',
                'message' => __('messages.permission_denied'),
            ];
        }

        if (! $user) {
            return [
                'ok' => false,
                'status' => 404,
                'type' => 'error',
                'message' => __('messages.user_not_found'),
            ];
        }

        $data = User::findOrFail($user->id);
        $hashedPassword = $data->password;

        if (! Hash::check($oldPassword, $hashedPassword)) {
            return [
                'ok' => false,
                'status' => 422,
                'type' => 'error',
                'message' => __('messages.check_old_password'),
            ];
        }

        if (Hash::check($newPassword, $hashedPassword)) {
            return [
                'ok' => false,
                'status' => 422,
                'type' => 'error',
                'message' => __('messages.same_pass'),
            ];
        }

        $data->fill([
            'password' => Hash::make($newPassword),
        ])->save();

        return [
            'ok' => true,
            'type' => 'success',
            'message' => __('messages.pass_successfull'),
        ];
    }

    public function resetDatabase(bool $dummyData): bool
    {
        if (! $dummyData) {
            Artisan::call('config:clear');
        }

        $this->setEnvValue('IS_DUMMY_DATA', $dummyData ? 'true' : 'false');

        $artisanPath = base_path('artisan');
        $output = [];
        $resultCode = 0;
        exec("php {$artisanPath} migrate:fresh --seed --force 2>&1", $output, $resultCode);

        Log::info('Migration Output: '.implode("\n", $output));
        Log::info('Migration Result Code: '.$resultCode);

        if ($resultCode !== 0) {
            Log::error('Migration failed with result code: '.$resultCode);
            Log::error('Migration error output: '.implode("\n", $output));

            return false;
        }

        Log::info('IS_DUMMY_DATA value: '.env('IS_DUMMY_DATA'));
        Log::info('Database reset completed.');

        return true;
    }

    public function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $envContent = (string) file_get_contents($envPath);
        $pattern = "/^{$key}=.*/m";
        if (preg_match($pattern, $envContent)) {
            $envContent = (string) preg_replace($pattern, "{$key}={$value}", $envContent);
        } else {
            $envContent .= "\n{$key}={$value}";
        }

        file_put_contents($envPath, $envContent);
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
    }
}

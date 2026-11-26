<?php

namespace App\Services\Auth;

use App\Mail\sendOtp as SendOtpMail;
use App\Models\User;
use App\Notifications\CommonNotification;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ParentalControlService
{
    /**
     * @return array{ok: true, message: string, extra: array<string, mixed>}|array{ok: false, message: string, status: int}
     */
    public function changePin(User $user, string $pin): array
    {
        $storedPin = (string) ($user->pin_hash ?: $user->pin);
        if ($storedPin !== '' && $this->pinMatches($storedPin, $pin)) {
            return ['ok' => false, 'message' => __('frontend.new_pin_must_be_different'), 'status' => 400];
        }

        $message = ! empty($user->otp)
            ? __('messages.change_pin_successfull')
            : __('messages.set_pin_successfull');

        $isNewPin = empty($user->pin_hash) && empty($user->pin);
        $updateData = [
            'pin_hash' => Hash::make($pin),
            'pin' => null,
        ];

        if ($isNewPin) {
            $updateData['is_parental_lock_enable'] = 1;
        }

        $user->update($updateData);

        return [
            'ok' => true,
            'message' => $message,
            'extra' => ['is_parental_lock_enable' => $user->is_parental_lock_enable],
        ];
    }

    public function sendOtp(User $user): void
    {
        $otp = rand(config('constant.OTP_MIN'), config('constant.OTP_MAX'));
        $user->update(['otp' => $otp]);

        try {
            $notificationData = [
                'user_type' => 'user',
                'user_id' => $user->id,
                'user_name' => $user->full_name ?? $user->first_name.' '.$user->last_name,
                'otp' => $otp,
                'notification_type' => 'parental_control_otp',
            ];

            $user->notify((new CommonNotification('parental_control_otp', $notificationData))->onQueue('notifications'));
        } catch (Exception $e) {
            Log::error('OTP notification delivery failed.', ['exception' => $e::class]);
            $bodyData = ['body' => 'Change Your Pin OTP is : '.$otp];
            Mail::to($user->email)->send(new SendOtpMail($bodyData));
        }
    }

    public function verifyOtp(User $user, string $otp): bool
    {
        $matched = User::where([['id', '=', $user->id], ['otp', '=', $otp]])->first();
        if (! $matched) {
            return false;
        }

        $matched->update(['otp' => null]);

        return true;
    }

    public function verifyPin(User $user, string $pin): bool
    {
        $storedPin = (string) ($user->pin_hash ?: $user->pin);
        if (! $this->pinMatches($storedPin, $pin)) {
            return false;
        }

        if (! $user->pin_hash) {
            $user->forceFill([
                'pin_hash' => Hash::make($pin),
                'pin' => null,
            ])->save();
        }

        return true;
    }

    public function pinMatches(string $storedPin, string $providedPin): bool
    {
        if ($storedPin === '') {
            return false;
        }

        if (str_starts_with($storedPin, '$2y$') || str_starts_with($storedPin, '$argon2')) {
            return Hash::check($providedPin, $storedPin);
        }

        return hash_equals($storedPin, $providedPin);
    }

    public function setParentalLock(User $user, int $enabled): bool
    {
        return (bool) User::where('id', $user->id)->update([
            'is_parental_lock_enable' => $enabled,
        ]);
    }
}

<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use App\Models\User;
use Modules\Currency\Models\Currency;
use Exception;
use Google_Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\NotificationTemplate\Models\NotificationTemplate;
use Modules\NotificationTemplate\Notifications\CommonNotification;
use Modules\Subscriptions\Models\Subscription;

class PushNotificationHelperService
{
    public function sendNotification(array $data): void
    {
        Log::info('Notification dispatch requested.', ['notification_type' => $data['notification_type'] ?? 'unknown']);
        $mailable = NotificationTemplate::where('type', $data['notification_type'])
            ->with('defaultNotificationTemplateMap')
            ->where('status', 1)
            ->first();

        if ($mailable == null || $mailable->to == null) {
            return;
        }

        $mails = json_decode($mailable->to);
        foreach ($mails as $mailTo) {
            $data['type'] = $data['notification_type'];
            $user = Auth::user();
            $data['logged_in_user_fullname'] = $data['logged_in_user_fullname'] ?? $user?->full_name ?? '';
            $data['logged_in_user_role'] = $data['logged_in_user_role'] ?? $user?->user_type ?? '';
            $data['company_name'] = $data['company_name'] ?? app_name() ?? '';
            $data['company_contact_info'] = $data['company_contact_info'] ?? setting('helpline_number') ?? ''.setting('inquriy_email') ?? '';
            $data['user_id'] = $data['user_id'] ?? '';
            $data['user_name'] = $data['user_name'] ?? '';
            $data['movie_name'] = $data['movie_name'] ?? '';
            $data['tvshow_name'] = $data['tvshow_name'] ?? '';
            $data['season_name'] = $data['season_name'] ?? '';
            $data['episode_name'] = $data['episode_name'] ?? '';
            $data['end_date'] = formatDateTimeWithTimezone($data['end_date'] ?? '', 'date');
            $data['content_type'] = $data['content_type'] ?? '';
            $data['start_date'] = formatDateTimeWithTimezone($data['start_date'] ?? '');
            $data['app_name'] = $data['app_name'] ?? env('APP_NAME') ?? '';

            if (isset($data['amount']) && is_numeric($data['amount'])) {
                $data['amount_formatted'] = Currency::format($data['amount']);
            }

            $subscription = $data['subscription'] ?? '';
            if (isset($subscription) && $subscription != null) {
                $data['id'] = $subscription['id'];
                $data['user_id'] = $subscription['user_id'];
                $data['plan_id'] = $subscription['plan_id'];
                $data['subscription_plan'] = $subscription['name'];
                $data['identifier'] = $subscription['identifier'];
                $data['type'] = $subscription['type'];
                $data['status'] = $subscription['status'];
                $data['amount'] = ($subscription['total_amount'] ?? $subscription['amount']) - ($subscription['tax_amount'] ?? 0);
                $data['amount_formatted'] = Currency::format($data['amount']);
                $data['tax_amount'] = $subscription['tax_amount'] ?? 0;
                $data['tax_amount_formatted'] = Currency::format($subscription['tax_amount'] ?? 0);
                $data['total_amount'] = $subscription['total_amount'] ?? $subscription['amount'];
                $data['total_amount_formatted'] = Currency::format($subscription['total_amount'] ?? $subscription['amount']);
                $data['coupon_discount'] = $subscription['coupon_discount'] ?? 0;
                $data['coupon_discount_formatted'] = Currency::format($subscription['coupon_discount'] ?? 0);
                $data['duration'] = $subscription['duration'] ?? '';
                $data['duration_formatted'] = $subscription['duration'].' '.ucfirst($subscription['type']);
                $data['plan_type'] = $subscription['plan_type'];
                $data['user_name'] = $subscription['user']->full_name ?? '';
                $data['user_email'] = $subscription['user']->email ?? '';
                $data['user_mobile'] = $subscription['user']->mobile ?? '-';
                $data['start_date'] = formatDateTimeWithTimezone($subscription['start_date'] ?? '', 'date');
                $data['end_date'] = formatDateTimeWithTimezone($subscription['end_date'] ?? '', 'date');
                $data['days'] = $subscription['days'] ?? $subscription['days_remaining'] ?? 0;
                $data['days_remaining'] = $subscription['days_remaining'] ?? $subscription['days'] ?? 0;
                $data['notification_group'] = 'subscription';
                $data['site_url'] = env('APP_URL');

                $data['price'] = $data['amount'];
                $data['price_formatted'] = $data['amount_formatted'];
                $data['discount'] = $data['coupon_discount'];
                $data['discount_formatted'] = $data['coupon_discount_formatted'];
                $data['Tax'] = $data['tax_amount_formatted'];

                $subId = $subscription['id'] ?? null;
                $paymentMethod = '-';
                if ($subId) {
                    $subModel = Subscription::with('subscription_transaction')->find($subId);
                    $paymentMethod = $subModel ? ucfirst(optional($subModel->subscription_transaction)->payment_type ?? '-') : '-';
                }
                $data['payment_method'] = $paymentMethod;

                $subscriptionUserId = $subscription['user_id'] ?? null;
                unset($data['subscription']);
            } else {
                $subscriptionUserId = null;
            }

            $originalUserId = $data['user_id'] ?? null;
            $originalUserName = $data['user_name'] ?? null;
            $originalSubscriptionUserId = $subscriptionUserId;

            switch ($mailTo) {
                case 'admin':
                    $admin = User::role('admin')->first();

                    if (isset($admin->email)) {
                        try {
                            $data['user_type'] = $mailTo;
                            $data['user_id'] = $admin->id;
                            $data['user_name'] = $admin->full_name;
                            $admin->notify((new CommonNotification($data['notification_type'], $data))->onQueue('notifications'));
                        } catch (Exception $e) {
                            Log::error('Admin notification dispatch failed.', ['exception' => $e::class]);
                        }
                    }
                    $data['user_id'] = $originalUserId;
                    $data['user_name'] = $originalUserName;
                    break;
                case 'demo_admin':
                    $demoadmin = User::role('demo_Admin')->first();

                    if (isset($demoadmin->email)) {
                        try {
                            $data['user_type'] = $mailTo;
                            $data['user_id'] = $demoadmin->id;
                            $data['user_name'] = $demoadmin->full_name;
                            $demoadmin->notify((new CommonNotification($data['notification_type'], $data))->onQueue('notifications'));
                        } catch (Exception $e) {
                            Log::error('Demo admin notification dispatch failed.', ['exception' => $e::class]);
                        }
                    }
                    $data['user_id'] = $originalUserId;
                    $data['user_name'] = $originalUserName;
                    break;
                case 'user':
                    if (in_array($data['notification_type'], ['movie_add', 'tv_show_add', 'episode_add', 'season_add', 'video_add', 'upcoming'])) {
                        User::where('user_type', 'user')->chunk(500, function ($users) use ($data) {
                            foreach ($users as $user) {
                                try {
                                    $data['user_id'] = $user->id;
                                    $data['user_name'] = $user->full_name;
                                    $data['user_type'] = 'user';
                                    $user->notify(
                                        (new CommonNotification($data['notification_type'], $data))
                                            ->onQueue('notifications')
                                    );
                                } catch (Exception $e) {
                                    Log::error('Campaign notification dispatch failed.', ['exception' => $e::class]);
                                }
                            }
                        });
                    } else {
                        $targetUserId = $originalSubscriptionUserId ?? $data['user_id'] ?? null;

                        if ($targetUserId) {
                            $user = User::find($targetUserId);
                            if ($user) {
                                try {
                                    $data['user_type'] = $mailTo;
                                    $user->notify((new CommonNotification($data['notification_type'], $data))->onQueue('notifications'));
                                } catch (Exception $e) {
                                    Log::error('Customer notification dispatch failed.', ['exception' => $e::class]);
                                }
                            }
                        }
                    }
                    break;
            }
        }
    }

    public function sendPushNotifications(array $data): void
    {
        $heading = '#'.$data['id'].' '.str_replace('_', ' ', $data['name']);
        $content = strip_tags($data['description']);
        $appName = env('APP_NAME');
        $topic = str_replace(' ', '_', strtolower((string) $appName));
        $type = $data['type'];
        $additionalData = json_encode($data);

        $thumbnailImage = null;
        if (! empty($data['poster_url'])) {
            $thumbnailImage = getThumbnail($data['name'] ?? null, strtolower($data['type']));
        }

        if (! $thumbnailImage || strpos($thumbnailImage, 'default-image/') !== false) {
            $thumbnailImage = null;
        }

        $message = [
            'topic' => $topic,
            'notification' => [
                'title' => $heading,
                'body' => $content,
            ],
            'data' => [
                'sound' => 'default',
                'story_id' => 'story_12345',
                'type' => $type,
                'additional_data' => $additionalData,
            ],
            'android' => [
                'priority' => 'HIGH',
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'category' => $type,
                        'mutable-content' => 1,
                    ],
                ],
            ],
        ];

        if (! empty($thumbnailImage)) {
            $message['notification']['image'] = $thumbnailImage;
            $message['data']['image'] = $thumbnailImage;
            $message['data']['picture'] = $thumbnailImage;
            $message['data']['large_icon'] = $thumbnailImage;
            $message['android']['notification'] = ['image' => $thumbnailImage];
            $message['apns']['fcm_options'] = ['image' => $thumbnailImage];
        }

        $this->sendFcm([
            'message' => $message,
        ]);
    }

    public function sendFcm(array $fields): void
    {
        $otherSetting = Setting::where('type', 'appconfig')->where('name', 'firebase_key')->first();
        $projectID = $otherSetting->val ?? null;
        $accessToken = $this->getFirebaseAccessToken();

        $headers = [
            'Authorization: Bearer '.$accessToken,
            'Content-Type: application/json',
        ];
        $ch = curl_init('https://fcm.googleapis.com/v1/projects/'.$projectID.'/messages:send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            Log::warning('FCM delivery request failed.', ['http_status' => $httpCode]);
        }

        curl_close($ch);
    }

    public function getFirebaseAccessToken(): ?string
    {
        $directory = storage_path('app/data');
        $credentialsFiles = File::glob($directory.'/*.json');

        if (empty($credentialsFiles)) {
            return null;
        }

        $client = new Google_Client;
        if (empty($client)) {
            return null;
        }
        $client->setAuthConfig($credentialsFiles[0]);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        try {
            $token = $client->fetchAccessTokenWithAssertion();

            return $token['access_token'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignDelivery;
use App\Models\MarketingConsent;
use App\Mail\MarketingCampaignMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class SendMarketingCampaignDelivery implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(): void
    {
        $delivery = MarketingCampaignDelivery::query()->with('campaign')->find($this->deliveryId);
        if (!$delivery || $delivery->status === 'sent') return;
        $campaign = MarketingCampaign::query()->find($delivery->campaign_id);
        if (!$campaign || $campaign->status !== 'published' || ($campaign->expires_at && $campaign->expires_at->isPast())) {
            $delivery->forceFill(['status' => 'cancelled'])->save();
            return;
        }
        $consent = MarketingConsent::query()->where('user_id', $delivery->user_id)
            ->where('channel', $delivery->channel)->where('consented', true)->first();
        $user = DB::table('users')->where('id', $delivery->user_id)->where('status', 1)->whereNull('deleted_at')->first();
        if (!$consent || !$user || ($delivery->channel === 'email' && (empty($user->email_verified_at) || empty($user->email)))) {
            $delivery->forceFill(['status' => 'cancelled'])->save();
            return;
        }
        if (($campaign->audience_segment === 'subscribers' && !(bool) $user->is_subscribe)
            || ($campaign->audience_segment === 'registered' && (bool) $user->is_subscribe)) {
            $delivery->forceFill(['status' => 'cancelled'])->save();
            return;
        }

        $variant = $delivery->variant === 'B' ? ($campaign->variant_b ?? []) : [];
        $subject = (string) ($variant['subject'] ?? $campaign->subject ?? $campaign->name);
        $body = (string) ($variant['body'] ?? $campaign->body);
        $destination = (string) ($variant['destination_url'] ?? $campaign->destination_url ?? '');
        $token = bin2hex(random_bytes(32));
        $unsubscribeToken = $delivery->channel === 'email' ? bin2hex(random_bytes(32)) : null;
        $delivery->forceFill([
            'tracking_token_hash' => hash('sha256', $token),
            'unsubscribe_token_hash' => $unsubscribeToken ? hash('sha256', $unsubscribeToken) : null,
        ])->save();
        $trackedDestination = $destination !== '' ? route('marketing.track-click', ['token' => $token]) : null;

        try {
            if ($delivery->channel === 'email') {
                Mail::to($user->email)->send(new MarketingCampaignMail(
                    $subject, $body, $campaign->call_to_action, $trackedDestination,
                    route('marketing.unsubscribe', ['token' => $unsubscribeToken]),
                    route('marketing.track-open', ['token' => $token]),
                ));
            } else {
                $targetUrl = $trackedDestination ?: url('/');
                $message = CloudMessage::withTarget('topic', 'user_'.$delivery->user_id)
                    ->withNotification(FcmNotification::create($subject, $body))
                    ->withData(['campaign_id' => (string) $campaign->id, 'url' => $targetUrl]);
                Firebase::messaging()->send($message);
            }
            $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'failure_code' => null])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill(['status' => 'failed', 'failure_code' => substr($exception::class, 0, 100)])->save();
            throw $exception;
        }
    }
}

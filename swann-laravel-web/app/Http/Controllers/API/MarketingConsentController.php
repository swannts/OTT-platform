<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketingConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketingConsentController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $consents = MarketingConsent::query()->where('user_id', $request->user()->id)
            ->get(['channel', 'consented', 'consented_at', 'updated_at'])->keyBy('channel');
        return response()->json(['data' => collect(['email', 'push'])->mapWithKeys(fn (string $channel) => [
            $channel => ['consented' => (bool) ($consents[$channel]->consented ?? false), 'updated_at' => $consents[$channel]->updated_at ?? null],
        ])]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['email', 'push'])], 'consented' => ['required', 'boolean']]);
        $consent = DB::transaction(function () use ($request, $data): MarketingConsent {
            $consent = MarketingConsent::query()->firstOrNew(['user_id' => $request->user()->id, 'channel' => $data['channel']]);
            $consent->forceFill([
                'consented' => (bool) $data['consented'], 'consented_at' => $data['consented'] ? now() : null,
                'source' => 'account',
            ])->save();
            return $consent;
        });
        return response()->json(['data' => [
            'channel' => $consent->channel, 'consented' => $consent->consented,
            'consented_at' => $consent->consented_at,
        ]]);
    }

    public function unsubscribePrompt(string $token)
    {
        abort_unless(strlen($token) === 64 && ctype_xdigit($token), 404);
        $exists = DB::table('marketing_campaign_deliveries')->where('channel', 'email')
            ->where('unsubscribe_token_hash', hash('sha256', $token))->exists();
        abort_unless($exists, 404);
        $action = e(route('marketing.unsubscribe', ['token' => $token]));
        return response('<!doctype html><html><head><meta charset="utf-8"><title>Email preferences</title></head><body><main><h1>Email preferences</h1><p>Confirm that you want to stop marketing email.</p><form method="post" action="'.$action.'"><button type="submit">Unsubscribe</button></form></main></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function unsubscribe(string $token)
    {
        abort_unless(strlen($token) === 64 && ctype_xdigit($token), 404);
        $delivery = \App\Models\MarketingCampaignDelivery::query()->where('channel', 'email')
            ->where('unsubscribe_token_hash', hash('sha256', $token))->firstOrFail();
        MarketingConsent::query()->where('user_id', $delivery->user_id)->where('channel', 'email')
            ->update(['consented' => false, 'consented_at' => null, 'source' => 'unsubscribe_link', 'updated_at' => now()]);
        $delivery->forceFill(['unsubscribe_token_hash' => null])->save();
        return response('<!doctype html><html><head><meta charset="utf-8"><title>Email preferences</title></head><body><main><h1>Email preferences updated</h1><p>You have been unsubscribed from marketing email.</p></main></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function trackClick(string $token)
    {
        abort_unless(strlen($token) === 64 && ctype_xdigit($token), 404);
        $delivery = DB::table('marketing_campaign_deliveries as d')
            ->join('marketing_campaigns as c', 'c.id', '=', 'd.campaign_id')
            ->where('d.tracking_token_hash', hash('sha256', $token))->where('d.status', 'sent')
            ->where('c.status', 'published')->where(fn ($query) => $query->whereNull('c.expires_at')->orWhere('c.expires_at', '>', now()))
            ->select('d.id', 'd.campaign_id', 'd.variant', 'c.destination_url', 'c.variant_b')->first();
        abort_unless($delivery, 404);
        DB::table('marketing_campaign_deliveries')->where('id', $delivery->id)->update(['clicked_at' => DB::raw('COALESCE(clicked_at, NOW())')]);
        $destination = $delivery->destination_url;
        if ($delivery->variant === 'B' && $delivery->variant_b) {
            $variant = json_decode($delivery->variant_b, true);
            $destination = $variant['destination_url'] ?? $destination;
        }
        $scheme = is_string($destination) ? parse_url($destination, PHP_URL_SCHEME) : null;
        abort_unless(is_string($destination) && filter_var($destination, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) $scheme), ['http', 'https'], true), 404);
        return redirect()->away($destination);
    }

    public function trackOpen(string $token)
    {
        abort_unless(strlen($token) === 64 && ctype_xdigit($token), 404);
        DB::table('marketing_campaign_deliveries as d')->join('marketing_campaigns as c', 'c.id', '=', 'd.campaign_id')
            ->where('d.tracking_token_hash', hash('sha256', $token))->where('d.status', 'sent')->where('c.status', 'published')
            ->where(fn ($query) => $query->whereNull('c.expires_at')->orWhere('c.expires_at', '>', now()))
            ->update(['d.opened_at' => DB::raw('COALESCE(d.opened_at, NOW())')]);
        return response(base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='), 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0']);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventPublisher
{
    /**
     * Publish an event to RabbitMQ topic exchange (ott.events)
     */
    public static function publish(string $routingKey, array $payload, ?string $correlationId = null): bool
    {
        try {
            $envelope = [
                'id' => (string) Str::uuid(),
                'event' => $routingKey,
                'timestamp' => now()->timestamp,
                'correlation_id' => $correlationId ?: ('req-' . Str::random(12)),
                'retry_count' => 0,
                'payload' => $payload,
            ];

            $rabbitmqHost = env('RABBITMQ_HOST', 'rabbitmq');
            $rabbitmqPort = env('RABBITMQ_MANAGEMENT_PORT', 15672);
            $user = env('RABBITMQ_USER', 'guest');
            $pass = env('RABBITMQ_PASSWORD', 'guest');
            $vhost = urlencode('/');
            $exchange = 'ott.events';

            $url = "http://{$rabbitmqHost}:{$rabbitmqPort}/api/exchanges/{$vhost}/{$exchange}/publish";

            $response = Http::withBasicAuth($user, $pass)
                ->timeout(3)
                ->post($url, [
                    'properties' => (object)[],
                    'routing_key' => $routingKey,
                    'payload' => json_encode($envelope),
                    'payload_encoding' => 'string',
                ]);

            if ($response->successful()) {
                Log::info("[EventPublisher] Published {$routingKey} to RabbitMQ", ['id' => $envelope['id']]);
                return true;
            }

            Log::warning("[EventPublisher] RabbitMQ returned non-200: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("[EventPublisher] Failed to publish {$routingKey}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispatch thumbnail / image BlurHash generation to the Go background worker
     */
    public static function dispatchThumbnailBlurHash(string $table, string|int $id, string $imageUrl, string $pageType = 'thumbnail'): bool
    {
        return self::publish('media.thumbnail.blurhash', [
            'target_table' => $table,
            'target_id' => (string) $id,
            'image_url' => $imageUrl,
            'page_type' => $pageType,
        ]);
    }
}


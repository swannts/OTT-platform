<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiRequestIdTest extends TestCase
{
    public function test_api_generates_a_request_id_and_scopes_correlation_context(): void
    {
        Route::get('/api/test-request-id', static fn () => response()->json([
            'logged_request_id' => Context::get('request_id'),
        ]));

        $response = $this->getJson('/api/test-request-id', ['X-Request-Id' => 'caller-supplied-id']);
        $response->assertOk();
        $firstId = $response->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($firstId));
        $response->assertExactJson(['logged_request_id' => $firstId]);
        $this->assertNull(Context::get('request_id'));

        $error = $this->getJson('/api/nonexistent-request-id-test');
        $error->assertNotFound();
        $this->assertTrue(Str::isUuid($error->headers->get('X-Request-Id')));
        $this->assertNotSame($firstId, $error->headers->get('X-Request-Id'));
        $this->assertNull(Context::get('request_id'));
    }

    public function test_process_health_response_stays_outside_api_correlation(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeaderMissing('X-Request-Id');
    }
}

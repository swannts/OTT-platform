<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Entertainment\Models\Entertainment;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_v3_dashboard_detail_and_banner_data_return_success(): void
    {
        $this->getJson('/api/v3/dashboard-detail')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->getJson('/api/v3/banner-data?banner_for=home')
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_v2_pay_per_view_list_returns_unlocked_ppv_items(): void
    {
        $movie = Entertainment::query()->create([
            'name' => 'PPV Film '.Str::random(6),
            'slug' => 'ppv-film-'.Str::lower(Str::random(6)),
            'type' => 'movie',
            'status' => 1,
            'movie_access' => 'pay-per-view',
            'price' => 9.99,
        ]);

        $this->getJson('/api/v2/pay-per-view-list')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonFragment(['id' => $movie->id]);
    }
}

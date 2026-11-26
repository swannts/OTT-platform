<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings\SettingsAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Episode\Repositories\EpisodeRepositoryInterface;
use Modules\Episode\Services\EpisodeService;
use Tests\TestCase;

class SettingsAndEpisodeServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_account_service_change_password_and_profile_update(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
            'first_name' => 'Old',
            'last_name' => 'Name',
        ]);

        $service = new SettingsAccountService;

        $wrongOld = $service->changePassword($user, 'wrong-password', 'new-password-456');
        $this->assertFalse($wrongOld['ok']);
        $this->assertSame(422, $wrongOld['status']);

        $samePass = $service->changePassword($user, 'old-password-123', 'old-password-123');
        $this->assertFalse($samePass['ok']);
        $this->assertSame(422, $samePass['status']);

        $changed = $service->changePassword($user, 'old-password-123', 'new-password-456');
        $this->assertTrue($changed['ok']);
        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));

        $request = Request::create('/profile', 'POST', [
            'first_name' => 'Updated',
            'last_name' => 'Person',
        ]);
        $service->updateProfile($user, $request);
        $this->assertSame('Updated', $user->fresh()->first_name);
    }

    public function test_episode_service_import_episode_from_tmdb_formats_payload(): void
    {
        $repo = $this->createMock(EpisodeRepositoryInterface::class);

        $service = new class($repo) extends EpisodeService
        {
            public function getConfiguration()
            {
                return json_encode(['images' => ['secure_base_url' => 'https://image.tmdb.org/t/p/']]);
            }

            public function getEpisodeDetails(int $tvshow_id, int $season_id, int $episode_id)
            {
                return json_encode([
                    'still_path' => '/still.jpg',
                    'name' => 'Pilot',
                    'overview' => 'First episode',
                    'runtime' => 75,
                    'air_date' => '2026-01-01',
                ]);
            }

            public function getEpisodevideo(int $tvshow_id, int $season_id, int $episode_id)
            {
                return json_encode([
                    'results' => [
                        ['type' => 'Trailer', 'site' => 'YouTube', 'key' => 'abc123', 'size' => 1080],
                        ['type' => 'Featurette', 'site' => 'YouTube', 'key' => 'def456', 'size' => 720],
                    ],
                ]);
            }
        };

        $result = $service->importEpisodeFromTmdb(100, 1, 1);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['payload']['success']);
        $this->assertSame('01:15', $result['payload']['data']['duration']);
        $this->assertSame('https://image.tmdb.org/t/p/original/still.jpg', $result['payload']['data']['poster_url']);
        $this->assertTrue($result['payload']['data']['enable_quality']);
        $this->assertCount(1, $result['payload']['data']['episodeStreamContentMappings']);
    }
}

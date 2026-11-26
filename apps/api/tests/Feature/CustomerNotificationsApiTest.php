<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_list_returns_owned_items_with_the_notification_id_and_unread_count(): void
    {
        $user = User::factory()->create();
        $id = $this->insertNotification($user, ['subject' => 'A real update', 'message' => 'An episode is ready.']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notification-list')
            ->assertOk()
            ->assertJsonPath('unread_notification_count', 1)
            ->assertJsonPath('notification_data.0.notification_id', $id)
            ->assertJsonPath('notification_data.0.title', 'A real update')
            ->assertJsonPath('notification_data.0.message', 'An episode is ready.')
            ->assertJsonPath('notification_data.0.is_already_read', 0);
    }

    public function test_user_can_mark_only_their_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownedId = $this->insertNotification($user);
        $foreignId = $this->insertNotification($other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notification/{$ownedId}/mark-read")
            ->assertOk();

        $this->assertDatabaseHas('notifications', ['id' => $ownedId, 'notifiable_id' => $user->id]);
        $this->assertNotNull(DB::table('notifications')->where('id', $ownedId)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $foreignId)->value('read_at'));

        $this->actingAs($user, 'sanctum')->postJson("/api/notification/{$foreignId}/mark-read")->assertNotFound();
    }

    public function test_user_can_mark_all_their_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownedId = $this->insertNotification($user);
        $foreignId = $this->insertNotification($other);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/notification-mark-all-read')
            ->assertOk();

        $this->assertNotNull(DB::table('notifications')->where('id', $ownedId)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $foreignId)->value('read_at'));
    }

    public function test_user_can_delete_only_their_own_notification(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownedId = $this->insertNotification($user);
        $foreignId = $this->insertNotification($other);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v3/delete-notification', ['id' => $ownedId])
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $ownedId]);
        $this->assertDatabaseHas('notifications', ['id' => $foreignId, 'notifiable_id' => $other->id]);
    }

    private function insertNotification(User $user, array $data = []): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'database',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->getKey(),
            'data' => json_encode(array_merge(['subject' => 'Test update', 'message' => 'Test message', 'data' => []], $data), JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}

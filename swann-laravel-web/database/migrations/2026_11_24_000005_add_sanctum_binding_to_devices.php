<?php

use App\Models\Device;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('devices') || Schema::hasColumn('devices', 'sanctum_token_id')) {
            return;
        }

        Schema::table('devices', function (Blueprint $table): void {
            $table->foreignId('sanctum_token_id')->nullable()->after('user_id')
                ->constrained('personal_access_tokens')->nullOnDelete();
        });

        Device::query()->whereNull('sanctum_token_id')->orderBy('id')->chunkById(200, function ($devices): void {
            foreach ($devices as $device) {
                $token = PersonalAccessToken::query()
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->where('tokenable_id', $device->user_id)
                    ->where('name', (string) $device->device_id)
                    ->latest('id')
                    ->first();
                if ($token) {
                    $device->forceFill(['sanctum_token_id' => $token->getKey()])->save();
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('devices') && Schema::hasColumn('devices', 'sanctum_token_id')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('sanctum_token_id');
            });
        }
    }
};

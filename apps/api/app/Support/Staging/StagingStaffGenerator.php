<?php

namespace App\Support\Staging;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class StagingStaffGenerator
{
    public function generate(StagingGenerationContext $context, string $password): int
    {
        $roles = array_keys(RbacRegistry::roles());
        $roleModels = Role::query()->where('guard_name', 'web')->whereIn('name', $roles)->get()->keyBy('name');
        if ($roleModels->count() !== count($roles)) {
            throw new RuntimeException('Canonical RBAC roles must be seeded before staging staff generation.');
        }

        $passwordHash = Hash::make($password);
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $created = 0;
        $historyStart = $context->historyStart();
        $historySeconds = max(1, $historyStart->diffInSeconds($context->referenceDate));
        $staffTarget = max(1, $context->profile->counts['staff']);

        $indexes = $context->profile->counts['staff'] === 0
            ? []
            : range(0, $context->profile->counts['staff'] - 1);
        foreach (array_chunk($indexes, $context->profile->batchSize) as $indexes) {
            DB::transaction(function () use ($indexes, $roles, $roleModels, $context, $passwordHash, $datasetPart, $historyStart, $historySeconds, $staffTarget, &$created): void {
                foreach ($indexes as $index) {
                    $roleName = $roles[$index % count($roles)];
                    $email = sprintf('staging.%s.staff-%03d@example.test', $datasetPart, $index + 1);
                    $ownership = DB::table('staging_dataset_records')
                        ->where('dataset_id', $context->datasetId)
                        ->where('entity_type', 'staff_user')
                        ->where('entity_key', $email)
                        ->exists();
                    $existing = User::withTrashed()->where('email', $email)->first();

                    if ($existing !== null) {
                        if (! $ownership || $existing->trashed() || $existing->user_type !== 'admin' || ! $existing->hasRole($roleModels[$roleName])) {
                            throw new RuntimeException("Refusing to adopt or modify existing staff account {$email}.");
                        }

                        continue;
                    }

                    $active = ! ($index >= count($roles) && $index % 11 === 10);
                    $user = User::query()->create([
                        'first_name' => self::FIRST_NAMES[$index % count(self::FIRST_NAMES)],
                        'last_name' => self::LAST_NAMES[intdiv($index, 3) % count(self::LAST_NAMES)],
                        'email' => $email,
                        'password' => $passwordHash,
                        'user_type' => 'admin',
                        'status' => $active ? 1 : 0,
                        'is_banned' => 0,
                        'email_verified_at' => $context->referenceDate,
                        'created_at' => $historyStart->addSeconds((int) floor($historySeconds * ($index + 1) / $staffTarget)),
                    ]);
                    $user->syncRoles([$roleModels[$roleName]->getKey()]);
                    DB::table('staging_dataset_records')->insert([
                        'dataset_id' => $context->datasetId,
                        'entity_type' => 'staff_user',
                        'entity_key' => $email,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            });
        }

        return $created;
    }

    private const FIRST_NAMES = ['Amin', 'Farah', 'Imran', 'Nadia', 'Sami', 'Tasnim', 'Rafi', 'Mariam', 'Zayd', 'Safiya', 'Omar', 'Lina'];
    private const LAST_NAMES = ['Rahman', 'Hossain', 'Karim', 'Ahmed', 'Chowdhury', 'Hasan', 'Islam', 'Siddiqui'];
}

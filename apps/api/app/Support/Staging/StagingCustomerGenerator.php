<?php

namespace App\Support\Staging;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class StagingCustomerGenerator
{
    private const FIRST_NAMES = ['Amina', 'Yusuf', 'Samira', 'Ilyas', 'Nadia', 'Rafi', 'Mariam', 'Zayd', 'Safiya', 'Omar', 'Lina', 'Hasan'];
    private const LAST_NAMES = ['Rahman', 'Hossain', 'Karim', 'Ahmed', 'Chowdhury', 'Hasan', 'Islam', 'Siddiqui'];
    private const DEVICES = [
        ['platform' => 'web', 'name' => 'Desktop browser'],
        ['platform' => 'android', 'name' => 'Android phone'],
        ['platform' => 'ios', 'name' => 'iPhone'],
        ['platform' => 'android_tv', 'name' => 'Android TV'],
        ['platform' => 'smart_tv', 'name' => 'Smart TV'],
    ];

    public function generate(StagingGenerationContext $context, string $password): array
    {
        $customerTarget = $context->profile->counts['customers'];
        $deviceTarget = $context->profile->counts['devices'];
        if ($customerTarget < 0 || $context->profile->counts['profiles'] < 0 || $deviceTarget < 0) {
            throw new RuntimeException('Customer, profile, and device targets must be non-negative.');
        }
        if ($customerTarget === 0 && ($context->profile->counts['profiles'] > 0 || $deviceTarget > 0)) {
            throw new RuntimeException('Profile and device targets require at least one customer.');
        }

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $passwordHash = Hash::make($password);
        $historyStart = $context->historyStart();
        $historySeconds = max(1, $historyStart->diffInSeconds($context->referenceDate));
        $created = ['customers' => 0, 'profiles' => 0, 'devices' => 0];

        for ($offset = 0; $offset < $customerTarget; $offset += $context->profile->batchSize) {
            $end = min($customerTarget, $offset + $context->profile->batchSize);
            DB::transaction(function () use ($offset, $end, $customerTarget, $deviceTarget, $datasetPart, $passwordHash, $historyStart, $historySeconds, $context, &$created): void {
                $records = [];
                for ($index = $offset; $index < $end; $index++) {
                    $email = sprintf('customer.%s.%08d@example.test', $datasetPart, $index + 1);
                    $records[$email] = [
                        'index' => $index,
                        'created_at' => $historyStart->addSeconds((int) floor($historySeconds * ($index + 1) / max(1, $customerTarget))),
                    ];
                }
                $emails = array_keys($records);
                $owned = DB::table('staging_dataset_records')
                    ->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'customer')
                    ->whereIn('entity_key', $emails)
                    ->pluck('entity_key')->all();
                $ownedMap = array_fill_keys($owned, true);
                $existingUsers = User::withTrashed()->whereIn('email', $emails)->get()->keyBy('email');
                $newRows = [];
                foreach ($records as $email => $record) {
                    $existing = $existingUsers->get($email);
                    if ($existing !== null) {
                        if (! isset($ownedMap[$email]) || $existing->trashed() || $existing->user_type !== 'user') {
                            throw new RuntimeException("Refusing to adopt or modify existing customer account {$email}.");
                        }
                        continue;
                    }
                    $index = $record['index'];
                    $createdAt = $record['created_at'];
                    $newRows[] = [
                        'username' => 'staging:'.$context->datasetId,
                        'first_name' => self::FIRST_NAMES[$index % count(self::FIRST_NAMES)],
                        'last_name' => self::LAST_NAMES[intdiv($index, 5) % count(self::LAST_NAMES)],
                        'email' => $email,
                        'password' => $passwordHash,
                        'user_type' => 'user',
                        'status' => $index % 50 === 49 ? 0 : 1,
                        'is_banned' => 0,
                        'is_subscribe' => 0,
                        'email_verified_at' => $index % 10 === 0 ? null : $createdAt,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }

                if ($newRows === []) {
                    return;
                }

                DB::table('users')->insert($newRows);
                $users = DB::table('users')->whereIn('email', array_column($newRows, 'email'))->get(['id', 'email', 'created_at'])->keyBy('email');
                $profiles = [];
                $devices = [];
                $owners = [];
                $numberOfCustomers = count($newRows);
                $baseDevices = intdiv($deviceTarget, max(1, $customerTarget));
                $extraDevices = $deviceTarget % max(1, $customerTarget);
                foreach ($users as $email => $user) {
                    $index = $records[$email]['index'];
                    $bucket = $index % 100;
                    $naturalProfileAverage = $context->profile->name === 'small' ? 1.62 : 1.8;
                    $naturalProfileTarget = (int) round($customerTarget * $naturalProfileAverage);
                    if (abs($context->profile->counts['profiles'] - $naturalProfileTarget) <= $context->profile->counts['profiles'] * 0.1) {
                        $householdProfileCount = $context->profile->name === 'small'
                            ? ($bucket < 55 ? 1 : ($bucket < 85 ? 2 : ($bucket < 98 ? 3 : 4)))
                            : ($bucket < 45 ? 1 : ($bucket < 80 ? 2 : ($bucket < 95 ? 3 : 4)));
                    } else {
                        $baseProfiles = intdiv($context->profile->counts['profiles'], $customerTarget);
                        $extraProfiles = $context->profile->counts['profiles'] % $customerTarget;
                        $householdProfileCount = $baseProfiles + ($index < $extraProfiles ? 1 : 0);
                    }
                    for ($profileIndex = 0; $profileIndex < $householdProfileCount; $profileIndex++) {
                        $profileName = $profileIndex === 0 ? 'Primary' : 'Family profile '.($profileIndex + 1);
                        $profiles[] = [
                            'user_id' => $user->id,
                            'name' => $profileName,
                            'avatar' => null,
                            'is_child_profile' => $profileIndex > 0 && $profileIndex % 2 === 0,
                            'created_at' => $user->created_at,
                            'updated_at' => $user->created_at,
                        ];
                    }
                    $deviceCount = $baseDevices + ($index < $extraDevices ? 1 : 0);
                    for ($deviceIndex = 0; $deviceIndex < $deviceCount; $deviceIndex++) {
                        $deviceType = self::DEVICES[($index + $deviceIndex) % count(self::DEVICES)];
                        $deviceId = sprintf('stg-%s-%08d-%02d', $datasetPart, $index + 1, $deviceIndex + 1);
                        $dateEntropy = hash('sha256', $context->seed.':'.$index.':'.$deviceIndex);
                        $offsetDays = hexdec(substr($dateEntropy, 0, 8)) % 46;
                        $deviceCreatedAt = \Carbon\CarbonImmutable::parse($user->created_at)->addDays($offsetDays);
                        if ($deviceCreatedAt->greaterThan($context->referenceDate)) {
                            $deviceCreatedAt = $context->referenceDate;
                        }
                        $devices[] = [
                            'user_id' => (int) $user->id,
                            'device_id' => $deviceId,
                            'device_name' => $deviceType['name'],
                            'active_profile' => null,
                            'platform' => $deviceType['platform'],
                            'created_at' => $deviceCreatedAt,
                            'updated_at' => $deviceCreatedAt,
                        ];
                    }
                    $owners[] = [
                        'dataset_id' => $context->datasetId,
                        'entity_type' => 'customer',
                        'entity_key' => $email,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($profiles !== []) {
                    DB::table('user_multi_profiles')->insert($profiles);
                    $profileOwners = DB::table('user_multi_profiles')
                        ->whereIn('user_id', $users->pluck('id')->all())->get(['id', 'user_id']);
                    foreach ($profileOwners as $profile) {
                        $owners[] = [
                            'dataset_id' => $context->datasetId,
                            'entity_type' => 'user_multi_profile',
                            'entity_key' => (string) $profile->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $created['profiles']++;
                    }
                }
                if ($devices !== []) {
                    DB::table('devices')->insert($devices);
                    foreach ($devices as $device) {
                        $owners[] = [
                            'dataset_id' => $context->datasetId,
                            'entity_type' => 'device',
                            'entity_key' => $device['device_id'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $created['devices']++;
                    }
                }
                foreach (array_chunk($owners, $context->profile->batchSize) as $ownerChunk) {
                    DB::table('staging_dataset_records')->insert($ownerChunk);
                }
                $created['customers'] += $numberOfCustomers;
            });
        }

        return $created;
    }
}

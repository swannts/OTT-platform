<?php

namespace App\Support\Staging;

use App\Models\StagingDatasetManifest;
use App\Models\User;
use App\Models\MediaAsset;
use App\Support\Rbac\RbacRegistry;
use App\Services\Media\MediaReadinessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StagingDataIntegrityValidator
{
    /** @return array{valid: bool, complete: bool, coverage: string, checked_at: string, datasets: array<int, array<string, mixed>>, violations: array<int, string>} */
    public function report(?string $datasetId = null): array
    {
        $violations = [];
        if (! Schema::hasTable('staging_dataset_manifests') || ! Schema::hasTable('staging_dataset_records')) {
            return [
                'valid' => false,
                'complete' => false,
                'coverage' => 'manifest, staff, customers, profiles, devices, bilingual catalog, cast/crew credits, series hierarchy, plans, subscriptions, and synthetic transactions',
                'checked_at' => now()->toIso8601String(),
                'datasets' => [],
                'violations' => ['Staging manifest and ownership tables are missing; run database migrations first.'],
            ];
        }

        $query = StagingDatasetManifest::query()->orderBy('dataset_id');
        if ($datasetId !== null) {
            $query->where('dataset_id', $datasetId);
        }

        $datasets = $query->get()->map(function (StagingDatasetManifest $manifest) use (&$violations): array {
            $groupedCounts = DB::table('staging_dataset_records')
                ->where('dataset_id', $manifest->dataset_id)
                ->select('entity_type', DB::raw('count(*) as records'))
                ->groupBy('entity_type')
                ->pluck('records', 'entity_type')
                ->map(static fn ($count): int => (int) $count)
                ->all();
            $counts = [
                'staff' => $groupedCounts['staff_user'] ?? 0,
                'customers' => $groupedCounts['customer'] ?? 0,
                'profiles' => $groupedCounts['user_multi_profile'] ?? 0,
                'devices' => $groupedCounts['device'] ?? 0,
                'plans' => $groupedCounts['plan'] ?? 0,
                'subscriptions' => $groupedCounts['subscription'] ?? 0,
                'payments' => $groupedCounts['subscription_transaction'] ?? 0,
                'cast_crew' => $groupedCounts['cast_crew'] ?? 0,
                'license_windows' => $groupedCounts['content_license_window'] ?? 0,
                'license_territories' => $groupedCounts['content_license_territory'] ?? 0,
                'media_assets' => $groupedCounts['media_asset'] ?? 0,
                'media_processing_jobs' => $groupedCounts['media_processing_job'] ?? 0,
                'media_asset_tracks' => $groupedCounts['media_asset_track'] ?? 0,
                'entertainment_media_links' => $groupedCounts['entertainment_media_link'] ?? 0,
                'blogs' => $groupedCounts['blog'] ?? 0,
                'coupons' => $groupedCounts['coupon'] ?? 0,
                'coupon_plan_mappings' => $groupedCounts['coupon_plan_mapping'] ?? 0,
                'search_history' => $groupedCounts['user_search_history'] ?? 0,
                'support_tickets' => $groupedCounts['support_ticket'] ?? 0,
                'support_ticket_notes' => $groupedCounts['support_ticket_note'] ?? 0,
                'marketing_campaigns' => $groupedCounts['marketing_campaign'] ?? 0,
                'marketing_consents' => $groupedCounts['marketing_consent'] ?? 0,
                'notifications' => $groupedCounts['notification'] ?? 0,
            ];
            $supportedTypes = ['staff_user', 'customer', 'user_multi_profile', 'device', 'genre', 'entertainment', 'entertainment_genre_mapping', 'season', 'episode', 'cast_crew', 'entertainment_talent_mapping', 'content_license_window', 'content_license_territory', 'plan', 'planlimitation_mapping', 'subscription', 'subscription_transaction', 'media_asset', 'media_processing_job', 'media_asset_track', 'entertainment_media_link', 'blog', 'coupon', 'coupon_plan_mapping', 'user_search_history', 'support_ticket', 'support_ticket_note', 'marketing_consent', 'marketing_campaign', 'notification'];
            $unknownTypes = array_diff(array_keys($groupedCounts), $supportedTypes);
            foreach ($unknownTypes as $type) {
                $violations[] = "Dataset {$manifest->dataset_id} has an unsupported ownership type {$type}.";
            }
            $registeredUserTypes = ['staff_user', 'customer'];
            $invalidUsers = DB::table('staging_dataset_records as records')
                ->leftJoin('users', 'users.email', '=', 'records.entity_key')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->whereIn('records.entity_type', $registeredUserTypes)
                ->where(function ($query): void {
                    $query->whereNull('users.id')->orWhereNotNull('users.deleted_at');
                })
                ->count();
            if ($invalidUsers > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidUsers} missing or soft-deleted owned user accounts.";
            }
            $invalidStaff = DB::table('staging_dataset_records as records')
                ->join('users', 'users.email', '=', 'records.entity_key')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'staff_user')
                ->where(function ($query): void {
                    $query->whereNull('users.user_type')->orWhere('users.user_type', '!=', 'admin');
                })
                ->count();
            $invalidCustomers = DB::table('staging_dataset_records as records')
                ->join('users', 'users.email', '=', 'records.entity_key')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'customer')
                ->where(function ($query): void {
                    $query->whereNull('users.user_type')->orWhere('users.user_type', '!=', 'user');
                })
                ->count();
            if ($invalidStaff > 0 || $invalidCustomers > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidStaff} invalid staff and {$invalidCustomers} invalid customer account types.";
            }
            $invalidProfiles = DB::table('staging_dataset_records as records')
                ->leftJoin('user_multi_profiles as profiles', 'profiles.id', '=', 'records.entity_key')
                ->leftJoin('users', 'users.id', '=', 'profiles.user_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'user_multi_profile')
                ->where(function ($query): void {
                    $query->whereNull('profiles.id')->orWhereNotNull('profiles.deleted_at')->orWhereNull('users.id')->orWhereNotNull('users.deleted_at');
                })
                ->count();
            if ($invalidProfiles > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidProfiles} missing or invalid customer profiles.";
            }
            $invalidDevices = DB::table('staging_dataset_records as records')
                ->leftJoin('devices', 'devices.device_id', '=', 'records.entity_key')
                ->leftJoin('users', 'users.id', '=', 'devices.user_id')
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'users.email')
                        ->where('customer_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('customer_owner.entity_type', '=', 'customer');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'device')
                ->where(function ($query): void {
                    $query->whereNull('devices.id')->orWhereNotNull('devices.deleted_at')->orWhereNull('users.id')->orWhereNull('customer_owner.id');
                })
                ->count();
            if ($invalidDevices > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidDevices} missing or incorrectly owned devices.";
            }
            $invalidCatalogQuery = DB::table('staging_dataset_records as records')
                ->leftJoin('entertainments', 'entertainments.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'entertainment')
                ->where(function ($query): void {
                    $query->whereNull('entertainments.id')->orWhereNotNull('entertainments.deleted_at')
                        ->orWhere('entertainments.status', '!=', 0);
                });
            $invalidCatalog = $invalidCatalogQuery->count();
            $invalidBlogs = DB::table('staging_dataset_records as owners')
                ->leftJoin('blogs', 'blogs.id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $manifest->dataset_id)
                ->where('owners.entity_type', 'blog')
                ->where(function ($query): void {
                    $query->whereNull('blogs.id')->orWhereNotNull('blogs.deleted_at')
                        ->orWhereColumn('blogs.slug', '!=', 'owners.entity_key')
                        ->orWhereNull('blogs.title')->orWhere('blogs.title', '')
                        ->orWhereNull('blogs.title_bn')->orWhere('blogs.title_bn', '')
                        ->orWhereNull('blogs.summary')->orWhere('blogs.summary', '')
                        ->orWhereNull('blogs.summary_bn')->orWhere('blogs.summary_bn', '')
                        ->orWhereNull('blogs.content')->orWhere('blogs.content', '')
                        ->orWhereNull('blogs.content_bn')->orWhere('blogs.content_bn', '')
                        ->orWhereNull('blogs.status')->orWhereNotIn('blogs.status', [0, 1])
                        ->orWhere('blogs.reading_time', '<', 1)->orWhere('blogs.views_count', '<', 0)
                        ->orWhere(function ($query): void {
                            $query->where('blogs.status', 1)->whereNull('blogs.published_at');
                        })
                        ->orWhere(function ($query): void {
                            $query->where('blogs.status', 0)->whereNotNull('blogs.published_at');
                        });
                })->count();
            if ($invalidBlogs > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidBlogs} invalid or incompletely localized editorial articles.";
            }
            $invalidCoupons = DB::table('staging_dataset_records as owners')
                ->leftJoin('coupons', 'coupons.id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $manifest->dataset_id)
                ->where('owners.entity_type', 'coupon')
                ->where(function ($query): void {
                    $query->whereNull('coupons.id')->orWhereNotNull('coupons.deleted_at')
                        ->orWhereColumn('coupons.code', '!=', 'owners.entity_key')
                        ->orWhereNull('coupons.status')->orWhere('coupons.discount', '<=', 0)
                        ->orWhereNotIn('coupons.discount_type', ['fixed', 'percentage'])
                        ->orWhere(function ($query): void {
                            $query->where('coupons.discount_type', 'percentage')->where('coupons.discount', '>', 100);
                        })
                        ->orWhere(function ($query): void {
                            $query->whereNotNull('coupons.start_date')->whereNotNull('coupons.expire_date')->whereColumn('coupons.expire_date', '<', 'coupons.start_date');
                        })
                        ->orWhereNull('coupons.max_redemptions')->orWhere('coupons.max_redemptions', '<', 1)
                        ->orWhereNull('coupons.per_user_limit')->orWhere('coupons.per_user_limit', '<', 1);
                })->count();
            $invalidCouponMappings = DB::table('staging_dataset_records as owners')
                ->leftJoin('coupon_subscription_plan as mappings', 'mappings.id', '=', 'owners.record_id')
                ->leftJoin('staging_dataset_records as coupon_owner', function ($join) use ($manifest): void {
                    $join->on('coupon_owner.record_id', '=', 'mappings.coupon_id')
                        ->where('coupon_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('coupon_owner.entity_type', '=', 'coupon');
                })
                ->leftJoin('staging_dataset_records as plan_owner', function ($join) use ($manifest): void {
                    $join->on('plan_owner.record_id', '=', 'mappings.subscription_plan_id')
                        ->where('plan_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('plan_owner.entity_type', '=', 'plan');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)
                ->where('owners.entity_type', 'coupon_plan_mapping')
                ->where(function ($query): void {
                    $query->whereNull('mappings.id')->orWhereNull('coupon_owner.id')->orWhereNull('plan_owner.id')
                        ->orWhereColumn('owners.entity_key', '!=', DB::raw("CONCAT(coupon_owner.entity_key, ':', mappings.subscription_plan_id)"));
                })->count();
            if ($invalidCoupons > 0 || $invalidCouponMappings > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidCoupons} invalid staging coupons and {$invalidCouponMappings} invalid coupon-to-plan mappings.";
            }
            $invalidSearchHistory = DB::table('staging_dataset_records as owners')
                ->leftJoin('user_search_histories as history', 'history.id', '=', 'owners.record_id')
                ->leftJoin('users', 'users.id', '=', 'history.user_id')
                ->leftJoin('user_multi_profiles as profiles', function ($join): void {
                    $join->on('profiles.id', '=', 'history.profile_id')->on('profiles.user_id', '=', 'history.user_id');
                })
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'users.email')
                        ->where('customer_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('customer_owner.entity_type', '=', 'customer');
                })
                ->leftJoin('staging_dataset_records as profile_owner', function ($join) use ($manifest): void {
                    $join->where('profile_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('profile_owner.entity_type', '=', 'user_multi_profile')
                        ->whereRaw('profile_owner.entity_key = CAST(profiles.id AS CHAR)');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)
                ->where('owners.entity_type', 'user_search_history')
                ->where(function ($query) use ($manifest): void {
                    $query->whereNull('history.id')->orWhereNull('users.id')->orWhereNull('profiles.id')
                        ->orWhereNull('customer_owner.id')->orWhereNull('profile_owner.id')
                        ->orWhereNull('history.search_query')->orWhere('history.search_query', '')
                        ->orWhereNotIn('history.type', ['movie', 'tvshow'])
                        ->orWhereColumn('owners.record_id', '!=', 'history.id')
                        ->orWhereColumn('history.created_at', '<', 'users.created_at')
                        ->orWhereDate('history.created_at', '>', $manifest->reference_date);
                })->count();
            if ($invalidSearchHistory > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidSearchHistory} search-history rows with invalid customer/profile ownership, content type, or timestamp.";
            }
            $invalidSupportTickets = DB::table('staging_dataset_records as owners')
                ->leftJoin('support_tickets as tickets', 'tickets.id', '=', 'owners.record_id')
                ->leftJoin('users as customers', 'customers.id', '=', 'tickets.customer_id')
                ->leftJoin('users as assignees', 'assignees.id', '=', 'tickets.assigned_to')
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'customers.email')
                        ->where('customer_owner.dataset_id', '=', $manifest->dataset_id)->where('customer_owner.entity_type', '=', 'customer');
                })
                ->leftJoin('staging_dataset_records as staff_owner', function ($join) use ($manifest): void {
                    $join->where('staff_owner.dataset_id', '=', $manifest->dataset_id)->where('staff_owner.entity_type', '=', 'staff_user')
                        ->on('staff_owner.entity_key', '=', 'assignees.email');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'support_ticket')
                ->where(function ($query) use ($manifest): void {
                    $query->whereNull('tickets.id')->orWhereNull('customer_owner.id')->orWhereNull('staff_owner.id')
                        ->orWhereColumn('tickets.customer_id', '!=', 'tickets.created_by')
                        ->orWhereColumn('tickets.created_at', '<', 'customers.created_at')
                        ->orWhereNotIn('tickets.category', ['account', 'billing', 'playback', 'device', 'subscription', 'general'])
                        ->orWhereNotIn('tickets.priority', ['low', 'normal', 'high', 'urgent'])
                        ->orWhereNotIn('tickets.status', ['open', 'in_progress', 'waiting_customer', 'escalated', 'resolved', 'closed'])
                        ->orWhereColumn('tickets.updated_at', '<', 'tickets.created_at')
                        ->orWhereColumn('tickets.first_response_at', '<', 'tickets.created_at')
                        ->orWhereColumn('tickets.resolved_at', '<', 'tickets.created_at')
                        ->orWhereDate('tickets.created_at', '>', $manifest->reference_date)
                        ->orWhereColumn('owners.record_id', '!=', 'tickets.id');
                })->count();
            if ($invalidSupportTickets > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidSupportTickets} support tickets with invalid customer, staff, status, or lifecycle relationships.";
            }
            $invalidSupportNotes = DB::table('staging_dataset_records as owners')
                ->leftJoin('support_ticket_notes as notes', 'notes.id', '=', 'owners.record_id')
                ->leftJoin('support_tickets as tickets', 'tickets.id', '=', 'notes.ticket_id')
                ->leftJoin('staging_dataset_records as ticket_owner', function ($join) use ($manifest): void {
                    $join->on('ticket_owner.record_id', '=', 'notes.ticket_id')->where('ticket_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('ticket_owner.entity_type', '=', 'support_ticket');
                })
                ->leftJoin('users as authors', 'authors.id', '=', 'notes.author_id')
                ->leftJoin('staging_dataset_records as staff_owner', function ($join) use ($manifest): void {
                    $join->where('staff_owner.dataset_id', '=', $manifest->dataset_id)->where('staff_owner.entity_type', '=', 'staff_user')
                        ->on('staff_owner.entity_key', '=', 'authors.email');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'support_ticket_note')
                ->where(function ($query): void {
                    $query->whereNull('notes.id')->orWhereNull('ticket_owner.id')->orWhereNull('staff_owner.id')
                        ->orWhere('notes.is_internal', '!=', true)->orWhereColumn('notes.created_at', '<', 'tickets.created_at');
                })->count();
            if ($invalidSupportNotes > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidSupportNotes} support notes without owned tickets and staff authors.";
            }
            $invalidMarketingConsents = DB::table('staging_dataset_records as owners')
                ->leftJoin('marketing_consents as consents', 'consents.id', '=', 'owners.record_id')
                ->leftJoin('users', 'users.id', '=', 'consents.user_id')
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'users.email')->where('customer_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('customer_owner.entity_type', '=', 'customer');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'marketing_consent')
                ->where(function ($query): void {
                    $query->whereNull('consents.id')->orWhereNull('customer_owner.id')
                        ->orWhereNotIn('consents.channel', ['email', 'push'])
                        ->orWhere(function ($unconfirmed): void { $unconfirmed->where('consents.consented', true)->whereNull('consents.consented_at'); })
                        ->orWhere(function ($falseConsent): void { $falseConsent->where('consents.consented', false)->whereNotNull('consents.consented_at'); })
                        ->orWhereColumn('owners.record_id', '!=', 'consents.id');
                })->count();
            if ($invalidMarketingConsents > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidMarketingConsents} marketing consent rows with invalid customer ownership or consent timestamps.";
            }
            $invalidCampaigns = DB::table('staging_dataset_records as owners')
                ->leftJoin('marketing_campaigns as campaigns', 'campaigns.id', '=', 'owners.record_id')
                ->leftJoin('users as creators', 'creators.id', '=', 'campaigns.created_by')
                ->leftJoin('users as approvers', 'approvers.id', '=', 'campaigns.approved_by')
                ->leftJoin('staging_dataset_records as creator_owner', function ($join) use ($manifest): void {
                    $join->on('creator_owner.entity_key', '=', 'creators.email')->where('creator_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('creator_owner.entity_type', '=', 'staff_user');
                })
                ->leftJoin('staging_dataset_records as approver_owner', function ($join) use ($manifest): void {
                    $join->on('approver_owner.entity_key', '=', 'approvers.email')->where('approver_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('approver_owner.entity_type', '=', 'staff_user');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'marketing_campaign')
                ->where(function ($query): void {
                    $query->whereNull('campaigns.id')->orWhereNull('creator_owner.id')
                        ->orWhereNotIn('campaigns.kind', ['campaign', 'promotion', 'landing_page', 'notification'])
                        ->orWhereNotIn('campaigns.status', ['draft', 'pending_approval', 'approved', 'rejected'])
                        ->orWhere(function ($approved): void {
                            $approved->where('campaigns.status', 'approved')->where(function ($incomplete): void {
                                $incomplete->whereNull('campaigns.approved_by')->orWhereNull('campaigns.approved_at')
                                    ->orWhereNull('approver_owner.id')->orWhereColumn('campaigns.created_by', '=', 'campaigns.approved_by')
                                    ->orWhereColumn('campaigns.approved_at', '<', 'campaigns.created_at');
                            });
                        })
                        ->orWhere(function ($notApproved): void {
                            $notApproved->where('campaigns.status', '!=', 'approved')->where(function ($fields): void {
                                $fields->whereNotNull('campaigns.approved_by')->orWhereNotNull('campaigns.approved_at');
                            });
                        })
                        ->orWhere(function ($invalidPromotion): void {
                            $invalidPromotion->where('campaigns.kind', 'promotion')->whereNull('campaigns.coupon_id');
                        })
                        ->orWhere(function ($invalidNotification): void {
                            $invalidNotification->where('campaigns.kind', 'notification')->whereNotIn('campaigns.channel', ['email', 'push']);
                        })
                        ->orWhereColumn('owners.record_id', '!=', 'campaigns.id');
                })->count();
            if ($invalidCampaigns > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidCampaigns} marketing campaigns with invalid ownership, status, or maker-checker approval relationships.";
            }
            $invalidNotifications = DB::table('staging_dataset_records as owners')
                ->leftJoin('notifications', 'notifications.id', '=', 'owners.record_id')
                ->leftJoin('users', 'users.id', '=', 'notifications.notifiable_id')
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'users.email')->where('customer_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('customer_owner.entity_type', '=', 'customer');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'notification')
                ->where(function ($query) use ($manifest): void {
                    $query->whereNull('notifications.id')->orWhereNull('customer_owner.id')
                        ->orWhere('notifications.notifiable_type', '!=', 'App\\Models\\User')
                        ->orWhereColumn('notifications.created_at', '<', 'users.created_at')
                        ->orWhereColumn('notifications.updated_at', '<', 'notifications.created_at')
                        ->orWhereColumn('notifications.read_at', '<', 'notifications.created_at')
                        ->orWhereColumn('notifications.archived_at', '<', 'notifications.created_at')
                        ->orWhere('notifications.type', '!=', 'App\\Notifications\\CommonNotification')
                        ->orWhereRaw('JSON_VALID(notifications.data) = 0')
                        ->orWhereColumn('owners.record_id', '!=', 'notifications.id');
                })->count();
            if ($invalidNotifications > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidNotifications} notifications with invalid customer ownership, type, payload, or timestamps.";
            }
            $ownedMedia = DB::table('staging_dataset_records')
                ->where('dataset_id', $manifest->dataset_id)
                ->where('entity_type', 'media_asset')
                ->pluck('record_id');
            if ($ownedMedia->isNotEmpty()) {
                $invalidMedia = DB::table('staging_dataset_records as owners')
                    ->leftJoin('media_assets as assets', 'assets.id', '=', 'owners.record_id')
                    ->where('owners.dataset_id', $manifest->dataset_id)
                    ->where('owners.entity_type', 'media_asset')
                    ->where(function ($query): void {
                        $query->whereNull('assets.id')->orWhereNotNull('assets.deleted_at')->orWhere('assets.status', '!=', 'ready')->orWhere('assets.disk', '!=', 'private');
                    })->count();
                $invalidMediaLinks = DB::table('staging_dataset_records as owners')
                    ->leftJoin('entertainments', 'entertainments.id', '=', 'owners.entity_key')
                    ->leftJoin('media_assets as assets', 'assets.id', '=', 'owners.record_id')
                    ->where('owners.dataset_id', $manifest->dataset_id)
                    ->where('owners.entity_type', 'entertainment_media_link')
                    ->where(function ($query): void {
                        $query->whereNull('entertainments.id')->orWhereNull('assets.id')->orWhereColumn('entertainments.media_asset_id', '!=', 'assets.id');
                    })->count();
                $invalidMediaJobs = DB::table('staging_dataset_records as owners')
                    ->leftJoin('media_processing_jobs as jobs', 'jobs.id', '=', 'owners.record_id')
                    ->leftJoin('staging_dataset_records as assets', function ($join) use ($manifest): void {
                        $join->on('assets.record_id', '=', 'jobs.media_asset_id')
                            ->where('assets.dataset_id', '=', $manifest->dataset_id)
                            ->where('assets.entity_type', '=', 'media_asset');
                    })
                    ->where('owners.dataset_id', $manifest->dataset_id)
                    ->where('owners.entity_type', 'media_processing_job')
                    ->where(function ($query): void {
                        $query->whereNull('jobs.id')->orWhere('jobs.status', '!=', 'completed')->orWhereNull('assets.id');
                    })->count();
                $invalidMediaTracks = DB::table('staging_dataset_records as owners')
                    ->leftJoin('media_asset_tracks as tracks', 'tracks.id', '=', 'owners.record_id')
                    ->leftJoin('staging_dataset_records as assets', function ($join) use ($manifest): void {
                        $join->on('assets.record_id', '=', 'tracks.media_asset_id')
                            ->where('assets.dataset_id', '=', $manifest->dataset_id)
                            ->where('assets.entity_type', '=', 'media_asset');
                    })
                    ->where('owners.dataset_id', $manifest->dataset_id)
                    ->where('owners.entity_type', 'media_asset_track')
                    ->where(function ($query): void {
                        $query->whereNull('tracks.id')->orWhereNotNull('tracks.deleted_at')->orWhereNull('assets.id');
                    })->count();
                if ($invalidMedia > 0 || $invalidMediaLinks > 0 || $invalidMediaJobs > 0 || $invalidMediaTracks > 0) {
                    $violations[] = "Dataset {$manifest->dataset_id} has {$invalidMedia} invalid media assets, {$invalidMediaLinks} invalid content links, {$invalidMediaJobs} incomplete media jobs, and {$invalidMediaTracks} invalid subtitle tracks.";
                }
                $readiness = app(MediaReadinessService::class);
                foreach (MediaAsset::query()->whereIn('id', $ownedMedia)->with('assetTracks')->get() as $asset) {
                    $inspection = $readiness->inspect($asset);
                    if (! $inspection['ready']) {
                        $failedChecks = implode(', ', array_keys(array_filter($inspection['checks'], static fn (bool $passed): bool => ! $passed)));
                        $violations[] = "Dataset {$manifest->dataset_id} media asset {$asset->id} is not playable-ready ({$failedChecks}).";
                    }
                }
            }
            $invalidGenres = DB::table('staging_dataset_records as records')
                ->leftJoin('genres', 'genres.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'genre')
                ->where(function ($query): void {
                    $query->whereNull('genres.id')->orWhereNotNull('genres.deleted_at')->orWhere('genres.status', '!=', 1);
                })->count();
            $invalidMappings = DB::table('staging_dataset_records as records')
                ->leftJoin('entertainment_gener_mapping as mappings', 'mappings.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as entertainment_owner', function ($join) use ($manifest): void {
                    $join->on('entertainment_owner.record_id', '=', 'mappings.entertainment_id')
                        ->where('entertainment_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('entertainment_owner.entity_type', '=', 'entertainment');
                })
                ->leftJoin('staging_dataset_records as genre_owner', function ($join) use ($manifest): void {
                    $join->on('genre_owner.record_id', '=', 'mappings.genre_id')
                        ->where('genre_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('genre_owner.entity_type', '=', 'genre');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'entertainment_genre_mapping')
                ->where(function ($query): void {
                    $query->whereNull('mappings.id')->orWhereNotNull('mappings.deleted_at')
                        ->orWhereNull('entertainment_owner.id')->orWhereNull('genre_owner.id');
                })->count();
            $invalidSeasons = DB::table('staging_dataset_records as records')
                ->leftJoin('seasons', 'seasons.id', '=', 'records.record_id')
                ->leftJoin('entertainments as parent_series', 'parent_series.id', '=', 'seasons.entertainment_id')
                ->leftJoin('staging_dataset_records as series_owner', function ($join) use ($manifest): void {
                    $join->on('series_owner.record_id', '=', 'seasons.entertainment_id')
                        ->where('series_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('series_owner.entity_type', '=', 'entertainment');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'season')
                ->where(function ($query): void {
                    $query->whereNull('seasons.id')->orWhereNotNull('seasons.deleted_at')
                        ->orWhere('seasons.status', '!=', 0)->orWhereNull('series_owner.id')
                        ->orWhereNull('parent_series.id')->orWhere('parent_series.type', '!=', 'tvshow');
                })->count();
            $invalidEpisodes = DB::table('staging_dataset_records as records')
                ->leftJoin('episodes', 'episodes.id', '=', 'records.record_id')
                ->leftJoin('seasons', 'seasons.id', '=', 'episodes.season_id')
                ->leftJoin('staging_dataset_records as season_owner', function ($join) use ($manifest): void {
                    $join->on('season_owner.record_id', '=', 'episodes.season_id')
                        ->where('season_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('season_owner.entity_type', '=', 'season');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'episode')
                ->where(function ($query): void {
                    $query->whereNull('episodes.id')->orWhereNotNull('episodes.deleted_at')
                        ->orWhere('episodes.status', '!=', 0)->orWhereNull('seasons.id')
                        ->orWhereColumn('episodes.entertainment_id', '!=', 'seasons.entertainment_id')
                        ->orWhereNull('season_owner.id');
                })->count();
            if ($invalidCatalog || $invalidGenres || $invalidMappings || $invalidSeasons || $invalidEpisodes) {
                $violations[] = "Dataset {$manifest->dataset_id} has invalid catalog relationships: {$invalidGenres} genres, {$invalidCatalog} catalog records, {$invalidMappings} genre mappings, {$invalidSeasons} seasons, {$invalidEpisodes} episodes.";
            }
            $invalidPlans = DB::table('staging_dataset_records as records')
                ->leftJoin('plan', 'plan.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'plan')
                ->where(function ($query): void {
                    $query->whereNull('plan.id')->orWhereNotNull('plan.deleted_at')->orWhere('plan.status', '!=', 1);
                })->count();
            $invalidPlanMappings = DB::table('staging_dataset_records as records')
                ->leftJoin('planlimitation_mapping as mappings', 'mappings.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as plan_owner', function ($join) use ($manifest): void {
                    $join->where('plan_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('plan_owner.entity_type', '=', 'plan')
                        ->whereRaw('plan_owner.record_id = CAST(mappings.plan_id AS CHAR)');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'planlimitation_mapping')
                ->where(function ($query): void {
                    $query->whereNull('mappings.id')->orWhereNotNull('mappings.deleted_at')->orWhereNull('plan_owner.id');
                })->count();
            $invalidSubscriptions = DB::table('staging_dataset_records as records')
                ->leftJoin('subscriptions', 'subscriptions.id', '=', 'records.record_id')
                ->leftJoin('users', 'users.id', '=', 'subscriptions.user_id')
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'users.email')
                        ->where('customer_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('customer_owner.entity_type', '=', 'customer');
                })
                ->leftJoin('staging_dataset_records as plan_owner', function ($join) use ($manifest): void {
                    $join->where('plan_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('plan_owner.entity_type', '=', 'plan')
                        ->whereRaw('plan_owner.record_id = CAST(subscriptions.plan_id AS CHAR)');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'subscription')
                ->where(function ($query): void {
                    $query->whereNull('subscriptions.id')->orWhereNotNull('subscriptions.deleted_at')
                        ->orWhereNull('customer_owner.id')->orWhereNull('plan_owner.id')
                        ->orWhereNull('subscriptions.start_date')->orWhereNull('subscriptions.end_date')
                        ->orWhereColumn('subscriptions.end_date', '<=', 'subscriptions.start_date')
                        ->orWhere(function ($query): void {
                            $query->where('subscriptions.status', 'active')->where('subscriptions.is_trial', 0)
                                ->whereNull('subscriptions.payment_id');
                        })
                        ->orWhere(function ($query): void {
                            $query->whereNotNull('subscriptions.payment_id')->whereNotExists(function ($subquery): void {
                                $subquery->selectRaw('1')->from('subscriptions_transactions as paid_transaction')
                                    ->whereColumn('paid_transaction.id', 'subscriptions.payment_id')
                                    ->whereColumn('paid_transaction.subscriptions_id', 'subscriptions.id')
                                    ->whereColumn('paid_transaction.user_id', 'subscriptions.user_id')
                                    ->where('paid_transaction.payment_type', 'synthetic-staging')
                                    ->where('paid_transaction.payment_status', 'paid')
                                    ->whereNull('paid_transaction.deleted_at');
                            });
                        })
                        ->orWhere(function ($query): void {
                            $query->where('subscriptions.is_trial', 1)
                                ->where(function ($trial): void {
                                    $trial->where('subscriptions.status', '!=', 'active')->orWhere('subscriptions.total_amount', '!=', 0);
                                });
                        })
                        ->orWhereNotIn('subscriptions.status', ['active', 'inactive']);
                })->count();
            $invalidPayments = DB::table('staging_dataset_records as records')
                ->leftJoin('subscriptions_transactions as transactions', 'transactions.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as subscription_owner', function ($join) use ($manifest): void {
                    $join->where('subscription_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('subscription_owner.entity_type', '=', 'subscription')
                        ->whereRaw('subscription_owner.record_id = CAST(transactions.subscriptions_id AS CHAR)');
                })
                ->leftJoin('subscriptions', 'subscriptions.id', '=', 'transactions.subscriptions_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'subscription_transaction')
                ->where(function ($query): void {
                    $query->whereNull('transactions.id')->orWhereNotNull('transactions.deleted_at')
                        ->orWhereNull('subscription_owner.id')->orWhereColumn('transactions.user_id', '!=', 'subscriptions.user_id')
                        ->orWhere('transactions.payment_type', '!=', 'synthetic-staging')
                        ->orWhereNotIn('transactions.payment_status', ['paid', 'failed']);
                })->count();
            if ($invalidPlans || $invalidPlanMappings || $invalidSubscriptions || $invalidPayments) {
                $violations[] = "Dataset {$manifest->dataset_id} has invalid subscription relationships: {$invalidPlans} plans, {$invalidPlanMappings} plan limitations, {$invalidSubscriptions} subscriptions, {$invalidPayments} synthetic transactions.";
            }
            $invalidTalent = DB::table('staging_dataset_records as records')
                ->leftJoin('cast_crew', 'cast_crew.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'cast_crew')
                ->where(function ($query): void {
                    $query->whereNull('cast_crew.id')->orWhereNotNull('cast_crew.deleted_at')
                        ->orWhere('cast_crew.status', '!=', 1)->orWhereNotIn('cast_crew.type', ['actor', 'director']);
                })->count();
            $invalidTalentMappings = DB::table('staging_dataset_records as records')
                ->leftJoin('entertainment_talent_mapping as mappings', 'mappings.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as content_owner', function ($join) use ($manifest): void {
                    $join->on('content_owner.record_id', '=', 'mappings.entertainment_id')
                        ->where('content_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('content_owner.entity_type', '=', 'entertainment');
                })
                ->leftJoin('staging_dataset_records as talent_owner', function ($join) use ($manifest): void {
                    $join->on('talent_owner.record_id', '=', 'mappings.talent_id')
                        ->where('talent_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('talent_owner.entity_type', '=', 'cast_crew');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'entertainment_talent_mapping')
                ->where(function ($query): void {
                    $query->whereNull('mappings.id')->orWhereNotNull('mappings.deleted_at')
                        ->orWhereNull('content_owner.id')->orWhereNull('talent_owner.id');
                })->count();
            if ($invalidTalent || $invalidTalentMappings) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidTalent} invalid fictional cast/crew records and {$invalidTalentMappings} invalid title credits.";
            }
            $invalidLicenseWindows = DB::table('staging_dataset_records as records')
                ->leftJoin('content_license_windows as windows', 'windows.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as content_owner', function ($join) use ($manifest): void {
                    $join->on('content_owner.record_id', '=', 'windows.content_id')
                        ->where('content_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('content_owner.entity_type', '=', 'entertainment');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'content_license_window')
                ->where(function ($query): void {
                    $query->whereNull('windows.id')->orWhere('windows.content_type', '!=', 'entertainment')
                        ->orWhereNull('content_owner.id')->orWhereNull('windows.starts_at')->orWhereNull('windows.ends_at')
                        ->orWhereColumn('windows.ends_at', '<=', 'windows.starts_at');
                })->count();
            $invalidLicenseTerritories = DB::table('staging_dataset_records as records')
                ->leftJoin('staging_dataset_records as content_owner', function ($join) use ($manifest): void {
                    $join->on('content_owner.dataset_id', '=', 'records.dataset_id')
                        ->where('content_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('content_owner.entity_type', '=', 'entertainment')
                        ->whereRaw("content_owner.entity_key = SUBSTRING_INDEX(records.entity_key, ':', 1)");
                })
                ->leftJoin('content_license_territories as territories', function ($join): void {
                    $join->on('territories.content_id', '=', 'content_owner.record_id')
                        ->where('territories.content_type', '=', 'entertainment')
                        ->whereRaw("territories.country_code = SUBSTRING_INDEX(records.entity_key, ':', -1)");
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'content_license_territory')
                ->where(function ($query): void {
                    $query->whereNull('territories.country_code')->orWhereNull('content_owner.id')
                        ->orWhereColumn('territories.content_id', '!=', 'content_owner.record_id');
                })->count();
            if ($invalidLicenseWindows || $invalidLicenseTerritories) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidLicenseWindows} invalid content-license windows and {$invalidLicenseTerritories} invalid territorial rules.";
            }
            $roleNames = array_keys(RbacRegistry::roles());
            DB::table('staging_dataset_records')
                ->where('dataset_id', $manifest->dataset_id)
                ->where('entity_type', 'staff_user')
                ->orderBy('id')
                ->chunk(200, function ($staffRecords) use ($roleNames, $manifest, &$violations): void {
                    foreach ($staffRecords as $record) {
                        if (! preg_match('/\.staff-(\d+)@example\.test$/', $record->entity_key, $matches)) {
                            $violations[] = "Dataset {$manifest->dataset_id} has an invalid synthetic staff email {$record->entity_key}.";
                            continue;
                        }
                        $user = User::query()->where('email', $record->entity_key)->first();
                        $roleName = $roleNames[((int) $matches[1] - 1) % count($roleNames)] ?? null;
                        if ($user === null || $roleName === null || $user->getRoleNames()->all() !== [$roleName]) {
                            $violations[] = "Dataset {$manifest->dataset_id} staff user {$record->entity_key} has an unexpected role assignment.";
                        }
                    }
                });
            $recordCounts = (array) $manifest->record_counts;
            foreach ($counts as $entity => $actual) {
                if (($recordCounts[$entity] ?? 0) !== $actual) {
                    $violations[] = "Dataset {$manifest->dataset_id} manifest {$entity} count does not match its ownership records.";
                }
            }
            $configuredCounts = (array) ($manifest->configuration['counts'] ?? []);
            foreach (['staff' => 'staff', 'customers' => 'customers', 'devices' => 'devices', 'cast_crew' => 'cast_crew', 'plans' => 'plans', 'subscriptions' => 'subscriptions', 'payments' => 'payments', 'media_assets' => 'media_assets', 'coupons' => 'coupons', 'search_history' => 'search_history', 'support_tickets' => 'support_tickets', 'marketing_campaigns' => 'campaigns', 'notifications' => 'notifications'] as $entity => $targetKey) {
                if (isset($configuredCounts[$targetKey]) && $counts[$entity] !== (int) $configuredCounts[$targetKey]) {
                    $violations[] = "Dataset {$manifest->dataset_id} has {$counts[$entity]} {$entity}; configured target is {$configuredCounts[$targetKey]}.";
                }
            }
            if (isset($configuredCounts['profiles'])) {
                $profileTarget = (int) $configuredCounts['profiles'];
                $tolerance = (int) ceil($profileTarget * 0.1);
                if (abs($counts['profiles'] - $profileTarget) > $tolerance) {
                    $violations[] = "Dataset {$manifest->dataset_id} profile count is outside the configured target's 10% tolerance.";
                }
            }
            $catalogCounts = [
                'genres' => $groupedCounts['genre'] ?? 0,
                'movies' => DB::table('staging_dataset_records as records')->join('entertainments', 'entertainments.id', '=', 'records.record_id')->where('records.dataset_id', $manifest->dataset_id)->where('records.entity_type', 'entertainment')->where('entertainments.type', 'movie')->count(),
                'series' => DB::table('staging_dataset_records as records')->join('entertainments', 'entertainments.id', '=', 'records.record_id')->where('records.dataset_id', $manifest->dataset_id)->where('records.entity_type', 'entertainment')->where('entertainments.type', 'tvshow')->count(),
                'seasons' => $groupedCounts['season'] ?? 0,
                'episodes' => $groupedCounts['episode'] ?? 0,
                'cast_crew_mappings' => $groupedCounts['entertainment_talent_mapping'] ?? 0,
            ];
            foreach ($catalogCounts as $entity => $actual) {
                if (($recordCounts[$entity] ?? 0) !== $actual) {
                    $violations[] = "Dataset {$manifest->dataset_id} manifest {$entity} count does not match its catalog ownership records.";
                }
                if (isset($configuredCounts[$entity]) && $actual !== (int) $configuredCounts[$entity]) {
                    $violations[] = "Dataset {$manifest->dataset_id} has {$actual} {$entity}; configured target is {$configuredCounts[$entity]}.";
                }
            }
            $registeredCount = array_sum($counts) + array_sum($catalogCounts) + ($groupedCounts['entertainment_genre_mapping'] ?? 0)
                + ($groupedCounts['planlimitation_mapping'] ?? 0);
            return [
                'dataset_id' => $manifest->dataset_id,
                'profile' => $manifest->profile,
                'status' => $manifest->status,
                'registered_records' => $registeredCount,
                'manifest_counts' => $manifest->record_counts,
                'started_at' => $manifest->started_at?->toIso8601String(),
                'completed_at' => $manifest->completed_at?->toIso8601String(),
            ];
        })->all();

        if ($datasets === []) {
            $violations[] = $datasetId === null
                ? 'No staging dataset manifests exist.'
                : "No staging dataset manifest exists for {$datasetId}.";
        }

        $complete = $datasets !== [];
        foreach ($datasets as $dataset) {
            $complete = $complete && $dataset['status'] === 'complete';
        }

        return [
            'valid' => $violations === [],
            'complete' => $complete,
            'coverage' => 'manifest, staff role, customer identity, profile and device ownership, unpublished bilingual catalog, genre mappings, fictional cast/crew credits, bilingual editorial articles, coupon and plan mappings, customer search-history ownership, support ticket and staff-note lifecycle ownership, marketing consent and maker-checker campaign ownership, customer-owned in-app notification payloads and timestamps, licensing windows and territories, actual media fixture readiness and ownership, series hierarchy, plans, subscription lifecycle, and synthetic payment attempts; remaining business-domain checks are pending',
            'checked_at' => now()->toIso8601String(),
            'datasets' => $datasets,
            'violations' => array_values(array_unique($violations)),
        ];
    }
}

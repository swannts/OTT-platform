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
                'playback_sessions' => $groupedCounts['playback_session'] ?? 0,
                'playback_tokens' => $groupedCounts['playback_token'] ?? 0,
                'search_documents' => $groupedCounts['search_document'] ?? 0,
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
                'incidents' => $groupedCounts['operations_incident'] ?? 0,
                'audit_logs' => $groupedCounts['admin_audit_log'] ?? 0,
                'analytics_reports' => $groupedCounts['analytics_report_run'] ?? 0,
                'content_review_submissions' => $groupedCounts['content_review_submission'] ?? 0,
                'live_tv_categories' => $groupedCounts['live_tv_category'] ?? 0,
                'live_tv_channels' => $groupedCounts['live_tv_channel'] ?? 0,
                'videos' => $groupedCounts['video'] ?? 0,
            ];
            $supportedTypes = ['staff_user', 'customer', 'user_multi_profile', 'device', 'playback_token', 'playback_session', 'search_document', 'genre', 'entertainment', 'entertainment_genre_mapping', 'season', 'episode', 'cast_crew', 'entertainment_talent_mapping', 'content_license_window', 'content_license_territory', 'plan', 'planlimitation_mapping', 'subscription', 'subscription_transaction', 'media_asset', 'media_processing_job', 'media_asset_track', 'entertainment_media_link', 'blog', 'coupon', 'coupon_plan_mapping', 'user_search_history', 'support_ticket', 'support_ticket_note', 'marketing_consent', 'marketing_campaign', 'notification', 'operations_incident', 'admin_audit_log', 'analytics_report_run', 'content_review_submission', 'live_tv_category', 'live_tv_channel', 'video'];
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
            $referenceStart = $manifest->reference_date->copy()->startOfDay()->toDateTimeString();
            $referenceEnd = $manifest->reference_date->copy()->endOfDay()->toDateTimeString();
            $historyStart = $manifest->reference_date->copy()
                ->subMonthsNoOverflow((int) ($manifest->configuration['history_months'] ?? 12))
                ->startOfDay()->toDateTimeString();
            $invalidPlaybackTokens = DB::table('staging_dataset_records as owners')
                ->leftJoin('devices', 'devices.device_id', '=', 'owners.entity_key')
                ->leftJoin('personal_access_tokens as tokens', 'tokens.id', '=', 'owners.record_id')
                ->leftJoin('user_multi_profiles as profiles', 'profiles.id', '=', 'devices.active_profile')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'playback_token')
                ->where(function ($query) use ($manifest): void {
                    $query->whereNull('devices.id')->orWhereNull('tokens.id')
                        ->orWhereColumn('devices.sanctum_token_id', '!=', 'tokens.id')
                        ->orWhereColumn('tokens.tokenable_id', '!=', 'devices.user_id')
                        ->orWhere('tokens.tokenable_type', '!=', User::class)
                        ->orWhere('tokens.name', '!=', 'staging-playback-fixture')
                        ->orWhere('tokens.abilities', '!=', '[]')
                        ->orWhereNull('tokens.expires_at')->orWhere('tokens.expires_at', '<', $manifest->reference_date->copy()->endOfDay()->toDateTimeString())
                        ->orWhereNull('profiles.id')->orWhereColumn('profiles.user_id', '!=', 'devices.user_id')
                        ->orWhereRaw("LENGTH(tokens.token) != 64 OR tokens.token NOT REGEXP '^[0-9a-f]{64}$'");
                })->count();
            $invalidPlaybackSessions = DB::table('staging_dataset_records as owners')
                ->leftJoin('playback_sessions as sessions', 'sessions.session_id', '=', 'owners.record_id')
                ->leftJoin('users as session_users', 'session_users.id', '=', 'sessions.user_id')
                ->leftJoin('devices', 'devices.sanctum_token_id', '=', 'sessions.device_token_id')
                ->leftJoin('user_multi_profiles as profiles', 'profiles.id', '=', 'sessions.profile_id')
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'session_users.email')
                        ->where('customer_owner.dataset_id', '=', $manifest->dataset_id)->where('customer_owner.entity_type', '=', 'customer');
                })
                ->leftJoin('staging_dataset_records as content_owner', function ($join) use ($manifest): void {
                    $join->on('content_owner.record_id', '=', 'sessions.content_id')
                        ->where('content_owner.dataset_id', '=', $manifest->dataset_id)->where('content_owner.entity_type', '=', 'entertainment');
                })
                ->leftJoin('entertainments', 'entertainments.id', '=', 'sessions.content_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'playback_session')
                ->where(function ($query) use ($historyStart, $referenceEnd): void {
                    $query->whereNull('sessions.session_id')->orWhereColumn('owners.entity_key', '!=', 'sessions.session_id')
                        ->orWhereNull('customer_owner.id')->orWhereNull('content_owner.id')
                        ->orWhereNull('session_users.id')->orWhereNull('devices.id')->orWhereNull('profiles.id')
                        ->orWhereColumn('sessions.user_id', '!=', 'devices.user_id')
                        ->orWhereColumn('profiles.user_id', '!=', 'sessions.user_id')
                        ->orWhere('profiles.is_child_profile', '!=', 0)
                        ->orWhereColumn('devices.active_profile', '!=', 'sessions.profile_id')
                        ->orWhereColumn('devices.sanctum_token_id', '!=', 'sessions.device_token_id')
                        ->orWhere('sessions.content_type', '!=', 'movie')->orWhere('entertainments.type', '!=', 'movie')
                        ->orWhere('entertainments.status', '!=', 1)->orWhere('entertainments.movie_access', '!=', 'free')
                        ->orWhereColumn('entertainments.release_date', '>', 'sessions.created_at')
                        ->orWhereNull('sessions.ended_at')->orWhereColumn('sessions.last_heartbeat_at', '>', 'sessions.ended_at')
                        ->orWhereColumn('sessions.created_at', '>', 'sessions.ended_at')
                        ->orWhere('sessions.created_at', '<', $historyStart)->orWhere('sessions.ended_at', '>', $referenceEnd)
                        ->orWhereNotExists(function ($license): void {
                            $license->selectRaw('1')->from('content_license_windows as windows')
                                ->whereColumn('windows.content_id', 'sessions.content_id')->where('windows.content_type', 'entertainment')
                                ->where(function ($starts): void { $starts->whereNull('windows.starts_at')->orWhereColumn('windows.starts_at', '<=', 'sessions.created_at'); })
                                ->where(function ($ends): void { $ends->whereNull('windows.ends_at')->orWhereColumn('windows.ends_at', '>=', 'sessions.ended_at'); });
                        })
                        ->orWhereNotExists(function ($territory): void {
                            $territory->selectRaw('1')->from('content_license_territories as territories')
                                ->whereColumn('territories.content_id', 'sessions.content_id')
                                ->where('territories.content_type', 'entertainment')->where('territories.country_code', 'BD');
                        });
                })->count();
            if ($invalidPlaybackTokens > 0 || $invalidPlaybackSessions > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidPlaybackTokens} usable or incorrectly owned playback tokens and {$invalidPlaybackSessions} invalid historical playback sessions.";
            }
            $invalidSearchDocuments = DB::table('staging_dataset_records as owners')
                ->leftJoin('entertainments', 'entertainments.id', '=', 'owners.record_id')
                ->leftJoin('staging_dataset_records as content_owner', function ($join) use ($manifest): void {
                    $join->on('content_owner.record_id', '=', 'owners.record_id')
                        ->where('content_owner.dataset_id', '=', $manifest->dataset_id)->where('content_owner.entity_type', '=', 'entertainment');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'search_document')
                ->where(function ($query): void {
                    $query->whereNull('entertainments.id')->orWhereNull('content_owner.id')
                        ->orWhereColumn('owners.entity_key', '!=', 'owners.record_id')
                        ->orWhereNotIn('entertainments.type', ['movie', 'tvshow']);
                })->count();
            if ($invalidSearchDocuments > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidSearchDocuments} search-index ownership records without matching catalog content.";
            }
            $invalidCatalogQuery = DB::table('staging_dataset_records as records')
                ->leftJoin('entertainments', 'entertainments.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'entertainment')
                ->where(function ($query): void {
                    $query->whereNull('entertainments.id')->orWhereNotNull('entertainments.deleted_at')
                        ->orWhereNotIn('entertainments.status', [0, 1]);
                });
            $invalidCatalog = $invalidCatalogQuery->count();
            $invalidPublishedContent = DB::table('staging_dataset_records as owners')
                ->join('entertainments', 'entertainments.id', '=', 'owners.record_id')
                ->leftJoin('media_assets as assets', 'assets.id', '=', 'entertainments.media_asset_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'entertainment')
                ->where('entertainments.status', 1)
                ->where(function ($query) use ($referenceStart, $referenceEnd): void {
                    $query->whereNull('entertainments.poster_url')->orWhere('entertainments.poster_url', '')
                        ->orWhereNull('entertainments.thumbnail_url')->orWhere('entertainments.thumbnail_url', '')
                        ->orWhereNull('entertainments.media_asset_id')->orWhereNull('assets.id')
                        ->orWhere('assets.status', '!=', 'ready')->orWhere('assets.disk', '!=', 'private')
                        ->orWhere('entertainments.movie_access', '!=', 'free')
                        ->orWhereNotExists(function ($review) {
                            $review->selectRaw('1')->from('admin_content_review_submissions as submissions')
                                ->whereColumn('submissions.content_id', 'entertainments.id')
                                ->where('submissions.content_type', 'entertainment')->where('submissions.status', 'published');
                        })
                        ->orWhereNotExists(function ($license) use ($referenceStart, $referenceEnd): void {
                            $license->selectRaw('1')->from('content_license_windows as windows')
                                ->whereColumn('windows.content_id', 'entertainments.id')->where('windows.content_type', 'entertainment')
                                ->where(function ($starts) use ($referenceEnd): void { $starts->whereNull('windows.starts_at')->orWhere('windows.starts_at', '<=', $referenceEnd); })
                                ->where(function ($ends) use ($referenceStart): void { $ends->whereNull('windows.ends_at')->orWhere('windows.ends_at', '>=', $referenceStart); });
                        })
                        ->orWhereNotExists(function ($territory): void {
                            $territory->selectRaw('1')->from('content_license_territories as territories')
                                ->whereColumn('territories.content_id', 'entertainments.id')
                                ->where('territories.content_type', 'entertainment')->where('territories.country_code', 'BD');
                        });
                })->count();
            if ($invalidCatalog > 0 || $invalidPublishedContent > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidCatalog} invalid catalog records and {$invalidPublishedContent} published titles without current owned media, artwork, review, or license requirements.";
            }
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
            $invalidReviewLifecycle = DB::table('staging_dataset_records as owners')
                ->leftJoin('admin_content_review_submissions as submissions', 'submissions.id', '=', 'owners.record_id')
                ->leftJoin('staging_dataset_records as content_owner', function ($join) use ($manifest): void {
                    $join->on('content_owner.record_id', '=', 'submissions.content_id')
                        ->where('content_owner.dataset_id', '=', $manifest->dataset_id)->where('content_owner.entity_type', '=', 'entertainment');
                })
                ->leftJoin('entertainments', 'entertainments.id', '=', 'submissions.content_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'content_review_submission')
                ->where(function ($query): void {
                    $query->whereNull('submissions.id')->orWhereNull('content_owner.id')->orWhereNull('entertainments.id')
                        ->orWhereColumn('owners.record_id', '!=', 'submissions.id')
                        ->orWhere('submissions.content_type', '!=', 'entertainment')
                        ->orWhereNotIn('submissions.status', ['in_review', 'approved', 'rejected', 'published'])
                        ->orWhereColumn('submissions.submitted_by', '=', 'submissions.reviewed_by')
                        ->orWhere(function ($pending): void {
                            $pending->where('submissions.status', 'in_review')->where(function ($fields): void {
                                $fields->whereNotNull('submissions.reviewed_by')->orWhereNotNull('submissions.reviewed_at');
                            });
                        })
                        ->orWhere(function ($decided): void {
                            $decided->whereIn('submissions.status', ['approved', 'rejected', 'published'])->where(function ($fields): void {
                                $fields->whereNull('submissions.reviewed_by')->orWhereNull('submissions.reviewed_at');
                            });
                        })
                        ->orWhere(function ($published): void {
                            $published->where('submissions.status', 'published')->where('entertainments.status', '!=', 1);
                        })
                        ->orWhereColumn('submissions.reviewed_at', '<', 'submissions.submitted_at')
                        ->orWhereNull('submissions.snapshot')->orWhereNull('submissions.snapshot_hash');
                })->count();
            if ($invalidReviewLifecycle > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidReviewLifecycle} content reviews with invalid ownership, lifecycle, snapshot, or publication state.";
            }
            DB::table('staging_dataset_records as owners')
                ->join('admin_content_review_submissions as submissions', 'submissions.id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'content_review_submission')
                ->orderBy('submissions.id')->select(['owners.entity_key', 'submissions.content_id', 'submissions.revision', 'submissions.snapshot', 'submissions.snapshot_hash'])
                ->chunk(500, function ($reviews) use ($manifest, &$violations): void {
                    foreach ($reviews as $review) {
                        $expectedKey = (string) $review->content_id.':'.(string) $review->revision;
                        $snapshot = is_array($review->snapshot) ? $review->snapshot : json_decode((string) $review->snapshot, true);
                        if ($review->entity_key !== $expectedKey || ! is_array($snapshot)) {
                            $violations[] = "Dataset {$manifest->dataset_id} has a content review with an invalid ownership key or snapshot.";
                            continue;
                        }
                        ksort($snapshot);
                        $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (! is_string($encoded) || ! hash_equals((string) $review->snapshot_hash, hash('sha256', $encoded))) {
                            $violations[] = "Dataset {$manifest->dataset_id} has a content review with a mismatched snapshot hash.";
                            break;
                        }
                    }
                });
            $hasReviewPermissions = DB::table('staging_dataset_records as owners')
                ->join('admin_content_review_submissions as submissions', 'submissions.id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'content_review_submission')
                ->where(function ($query): void {
                    $query->whereNotExists(function ($submitter): void {
                        $submitter->selectRaw('1')->from('users as submitters')
                            ->join('model_has_roles as role_assignments', function ($join): void {
                                $join->on('role_assignments.model_id', '=', 'submitters.id')->where('role_assignments.model_type', '=', User::class);
                            })->join('roles', 'roles.id', '=', 'role_assignments.role_id')
                            ->join('staging_dataset_records as staff_owner', function ($join): void {
                                $join->on('staff_owner.entity_key', '=', 'submitters.email')->where('staff_owner.entity_type', '=', 'staff_user');
                            })->whereColumn('submitters.id', 'submissions.submitted_by')->where('roles.name', 'content_editor');
                    })->orWhere(function ($decided): void {
                        $decided->whereIn('submissions.status', ['approved', 'rejected', 'published'])->whereNotExists(function ($reviewer): void {
                            $reviewer->selectRaw('1')->from('users as reviewers')
                                ->join('model_has_roles as role_assignments', function ($join): void {
                                    $join->on('role_assignments.model_id', '=', 'reviewers.id')->where('role_assignments.model_type', '=', User::class);
                                })->join('roles', 'roles.id', '=', 'role_assignments.role_id')
                                ->join('staging_dataset_records as staff_owner', function ($join): void {
                                    $join->on('staff_owner.entity_key', '=', 'reviewers.email')->where('staff_owner.entity_type', '=', 'staff_user');
                                })->whereColumn('reviewers.id', 'submissions.reviewed_by')->where('roles.name', 'content_reviewer');
                        });
                    });
                })->count();
            if ($hasReviewPermissions > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$hasReviewPermissions} content reviews without owned staff holding the required submitter or reviewer roles.";
            }
            $invalidLiveTvCategories = DB::table('staging_dataset_records as owners')
                ->leftJoin('live_tv_category as categories', 'categories.id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'live_tv_category')
                ->where(function ($query): void {
                    $query->whereNull('categories.id')->orWhereNotNull('categories.deleted_at')
                        ->orWhere('categories.status', '!=', 1)->orWhereColumn('categories.slug', '!=', 'owners.entity_key');
                })->count();
            $invalidLiveTvChannels = DB::table('staging_dataset_records as owners')
                ->leftJoin('live_tv_channel as channels', 'channels.id', '=', 'owners.record_id')
                ->leftJoin('staging_dataset_records as category_owner', function ($join) use ($manifest): void {
                    $join->on('category_owner.record_id', '=', 'channels.category_id')
                        ->where('category_owner.dataset_id', '=', $manifest->dataset_id)->where('category_owner.entity_type', '=', 'live_tv_category');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'live_tv_channel')
                ->where(function ($query): void {
                    $query->whereNull('channels.id')->orWhereNotNull('channels.deleted_at')
                        ->orWhere('channels.status', '!=', 0)->orWhereNull('channels.name')->orWhere('channels.name', '')
                        ->orWhereNull('channels.name_bn')->orWhere('channels.name_bn', '')
                        ->orWhereNull('channels.description_bn')->orWhere('channels.description_bn', '')
                        ->orWhereNull('category_owner.id')->orWhereColumn('channels.slug', '!=', 'owners.entity_key');
                })->count();
            $mappedDraftChannels = DB::table('staging_dataset_records as owners')
                ->join('live_tv_stream_content_mapping as mappings', 'mappings.tv_channel_id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'live_tv_channel')->whereNull('mappings.deleted_at')->count();
            if ($invalidLiveTvCategories > 0 || $invalidLiveTvChannels > 0 || $mappedDraftChannels > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidLiveTvCategories} invalid Live TV categories, {$invalidLiveTvChannels} invalid channel drafts, or {$mappedDraftChannels} unverified stream mappings.";
            }
            $invalidVideoDrafts = DB::table('staging_dataset_records as owners')
                ->leftJoin('videos', 'videos.id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'video')
                ->where(function ($query): void {
                    $query->whereNull('videos.id')->orWhereNotNull('videos.deleted_at')
                        ->orWhere('videos.status', '!=', 0)->orWhere('videos.type', '!=', 'video')
                        ->orWhereNull('videos.name')->orWhere('videos.name', '')
                        ->orWhereNull('videos.name_bn')->orWhere('videos.name_bn', '')
                        ->orWhereNull('videos.description_bn')->orWhere('videos.description_bn', '')
                        ->orWhereNotNull('videos.video_url_input')->orWhereColumn('videos.slug', '!=', 'owners.entity_key');
                })->count();
            $mappedDraftVideos = DB::table('staging_dataset_records as owners')
                ->join('video_stream_content_mapping as mappings', 'mappings.video_id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'video')->count();
            if ($invalidVideoDrafts > 0 || $mappedDraftVideos > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidVideoDrafts} invalid standalone video drafts or {$mappedDraftVideos} stream mappings without a verified media asset.";
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
            $invalidIncidents = DB::table('staging_dataset_records as owners')
                ->leftJoin('operations_incidents as incidents', 'incidents.id', '=', 'owners.record_id')
                ->leftJoin('users as openers', 'openers.id', '=', 'incidents.opened_by')
                ->leftJoin('users as assignees', 'assignees.id', '=', 'incidents.assigned_to')
                ->leftJoin('users as resolvers', 'resolvers.id', '=', 'incidents.resolved_by')
                ->leftJoin('staging_dataset_records as opener_owner', function ($join) use ($manifest): void {
                    $join->on('opener_owner.entity_key', '=', 'openers.email')->where('opener_owner.dataset_id', '=', $manifest->dataset_id)->where('opener_owner.entity_type', '=', 'staff_user');
                })
                ->leftJoin('staging_dataset_records as assignee_owner', function ($join) use ($manifest): void {
                    $join->on('assignee_owner.entity_key', '=', 'assignees.email')->where('assignee_owner.dataset_id', '=', $manifest->dataset_id)->where('assignee_owner.entity_type', '=', 'staff_user');
                })
                ->leftJoin('staging_dataset_records as resolver_owner', function ($join) use ($manifest): void {
                    $join->on('resolver_owner.entity_key', '=', 'resolvers.email')->where('resolver_owner.dataset_id', '=', $manifest->dataset_id)->where('resolver_owner.entity_type', '=', 'staff_user');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'operations_incident')
                ->where(function ($query) use ($manifest): void {
                    $query->whereNull('incidents.id')->orWhereNull('opener_owner.id')
                        ->orWhereNotIn('incidents.severity', ['low', 'medium', 'high', 'critical'])
                        ->orWhereNotIn('incidents.status', ['open', 'acknowledged', 'resolved'])
                        ->orWhereColumn('incidents.updated_at', '<', 'incidents.created_at')
                        ->orWhereDate('incidents.created_at', '>', $manifest->reference_date)
                        ->orWhere(function ($acknowledged): void {
                            $acknowledged->where('incidents.status', '!=', 'open')->where(function ($missing): void {
                                $missing->whereNull('incidents.acknowledged_at')->orWhereColumn('incidents.acknowledged_at', '<', 'incidents.created_at');
                            });
                        })
                        ->orWhere(function ($resolved): void {
                            $resolved->where('incidents.status', 'resolved')->where(function ($missing): void {
                                $missing->whereNull('incidents.resolved_at')->orWhereNull('resolver_owner.id')
                                    ->orWhereColumn('incidents.resolved_at', '<', 'incidents.acknowledged_at');
                            });
                        })
                        ->orWhere(function ($notResolved): void {
                            $notResolved->where('incidents.status', '!=', 'resolved')->where(function ($fields): void {
                                $fields->whereNotNull('incidents.resolved_at')->orWhereNotNull('incidents.resolved_by');
                            });
                        })
                        ->orWhereColumn('owners.record_id', '!=', 'incidents.id');
                })->count();
            if ($invalidIncidents > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidIncidents} operations incidents with invalid ownership, severity, or lifecycle timestamps.";
            }
            $invalidAuditLogs = DB::table('staging_dataset_records as owners')
                ->leftJoin('admin_audit_logs as logs', 'logs.id', '=', 'owners.record_id')
                ->leftJoin('users as actors', 'actors.id', '=', 'logs.actor_id')
                ->leftJoin('staging_dataset_records as actor_owner', function ($join) use ($manifest): void {
                    $join->on('actor_owner.entity_key', '=', 'actors.email')->where('actor_owner.dataset_id', '=', $manifest->dataset_id)->where('actor_owner.entity_type', '=', 'staff_user');
                })
                ->leftJoin('staging_dataset_records as source_owner', function ($join) use ($manifest): void {
                    $join->on('source_owner.id', '=', DB::raw('CAST(logs.resource_id AS UNSIGNED)'))
                        ->where('source_owner.dataset_id', '=', $manifest->dataset_id)->where('source_owner.entity_type', '!=', 'admin_audit_log');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'admin_audit_log')
                ->where(function ($query): void {
                    $query->whereNull('logs.id')->orWhereNull('actor_owner.id')->orWhereNull('source_owner.id')
                        ->orWhere('logs.action', '!=', 'staging.fixture.generated')->orWhere('logs.outcome', '!=', 'success')
                        ->orWhereColumn('owners.record_id', '!=', 'logs.id')
                        ->orWhereRaw('JSON_VALID(logs.after_values) = 0')
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(logs.after_values, '$.synthetic')) != 'true'");
                })->count();
            if ($invalidAuditLogs > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidAuditLogs} audit events without owned actors, source records, and synthetic markers.";
            }
            $invalidAnalyticsReports = DB::table('staging_dataset_records as owners')
                ->leftJoin('analytics_report_runs as reports', 'reports.id', '=', 'owners.record_id')
                ->leftJoin('users', 'users.id', '=', 'reports.requested_by')
                ->leftJoin('model_has_roles as analytics_roles', function ($join): void {
                    $join->on('analytics_roles.model_id', '=', 'users.id')->where('analytics_roles.model_type', '=', 'App\\Models\\User');
                })
                ->leftJoin('roles', 'roles.id', '=', 'analytics_roles.role_id')
                ->leftJoin('staging_dataset_records as analyst_owner', function ($join) use ($manifest): void {
                    $join->on('analyst_owner.entity_key', '=', 'users.email')->where('analyst_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('analyst_owner.entity_type', '=', 'staff_user');
                })
                ->where('owners.dataset_id', $manifest->dataset_id)->where('owners.entity_type', 'analytics_report_run')
                ->where(function ($query) use ($manifest): void {
                    $query->whereNull('reports.id')->orWhereNull('analyst_owner.id')->orWhere('roles.name', '!=', 'analytics_manager')
                        ->orWhereNotIn('reports.metric_group', ['audience', 'subscriptions', 'content', 'revenue', 'playback'])
                        ->orWhere('reports.status', '!=', 'ready')->orWhereNull('reports.generated_at')
                        ->orWhereRaw('JSON_VALID(reports.result) = 0')
                        ->orWhereColumn('reports.date_from', '>', 'reports.date_to')
                        ->orWhereDate('reports.date_to', '!=', $manifest->reference_date)
                        ->orWhereColumn('owners.record_id', '!=', 'reports.id');
                })->count();
            if ($invalidAnalyticsReports > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidAnalyticsReports} analytics reports without valid owned analysts, groups, dates, or aggregate results.";
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
            foreach (['staff' => 'staff', 'customers' => 'customers', 'devices' => 'devices', 'playback_sessions' => 'playback_sessions', 'cast_crew' => 'cast_crew', 'plans' => 'plans', 'subscriptions' => 'subscriptions', 'payments' => 'payments', 'media_assets' => 'media_assets', 'coupons' => 'coupons', 'search_history' => 'search_history', 'support_tickets' => 'support_tickets', 'marketing_campaigns' => 'campaigns', 'notifications' => 'notifications', 'incidents' => 'incidents', 'audit_logs' => 'audit_logs', 'analytics_reports' => 'analytics_reports', 'content_review_submissions' => 'content_review_submissions', 'live_tv_channels' => 'live_channels', 'videos' => 'videos'] as $entity => $targetKey) {
                if (isset($configuredCounts[$targetKey]) && $counts[$entity] !== (int) $configuredCounts[$targetKey]) {
                    $violations[] = "Dataset {$manifest->dataset_id} has {$counts[$entity]} {$entity}; configured target is {$configuredCounts[$targetKey]}.";
                }
            }
            if (isset($configuredCounts['playback_sessions'])) {
                $hasPlaybackTarget = (int) $configuredCounts['playback_sessions'] > 0;
                if ((! $hasPlaybackTarget && $counts['playback_tokens'] !== 0) || ($hasPlaybackTarget && $counts['playback_tokens'] === 0)) {
                    $violations[] = "Dataset {$manifest->dataset_id} has {$counts['playback_tokens']} playback tokens for a configured target of {$configuredCounts['playback_sessions']} sessions.";
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
            if ($counts['search_documents'] !== $catalogCounts['movies'] + $catalogCounts['series']) {
                $violations[] = "Dataset {$manifest->dataset_id} search-document ownership does not cover its catalog titles.";
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
            'coverage' => 'manifest, staff role, customer identity, profile and device ownership, ended historical playback and unusable token ownership, bilingual catalog and publication prerequisites, dataset-isolated English/Bengali search-index documents and filter query verification, genre mappings, fictional cast/crew credits, bilingual editorial articles, coupon and plan mappings, customer search-history ownership, support ticket and staff-note lifecycle ownership, marketing consent and maker-checker campaign ownership, customer-owned in-app notification payloads and timestamps, synthetic operations incident lifecycles and explicitly labeled staging audit events, analytics reports derived from generated source data, licensing windows and territories, actual media fixture readiness and ownership, series hierarchy, plans, subscription lifecycle, and synthetic payment attempts; remaining business-domain checks are pending',
            'checked_at' => now()->toIso8601String(),
            'datasets' => $datasets,
            'violations' => array_values(array_unique($violations)),
        ];
    }
}

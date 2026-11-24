<?php

namespace App\Support\Rbac;

/**
 * The canonical administrative permission catalog and least-privilege role map.
 * Customer subscription status and viewer profiles intentionally are not roles.
 */
final class RbacRegistry
{
    /** @return array<string, string> Permission name => human-readable description. */
    public static function permissions(): array
    {
        return [
            'admin.access' => 'Sign in to the administrative application.',
            'admin.dashboard.view' => 'View the administrative dashboard.',
            'admin.users.view' => 'View customer accounts.',
            'admin.users.create' => 'Create customer accounts.',
            'admin.users.update' => 'Update customer accounts.',
            'admin.users.suspend' => 'Suspend or reactivate customer accounts.',
            'admin.staff.view' => 'View administrative staff accounts.',
            'admin.staff.invite' => 'Invite staff into the administrative platform.',
            'admin.staff.roles.update' => 'Assign and revoke staff administrative roles.',
            'admin.staff.status.update' => 'Activate or deactivate staff accounts.',
            'admin.staff.sessions.revoke' => 'Revoke staff sessions.',
            'admin.roles.view' => 'View administrative roles.',
            'admin.roles.create' => 'Create administrative roles.',
            'admin.roles.update' => 'Update administrative roles.',
            'admin.roles.delete' => 'Delete eligible custom roles.',
            'admin.roles.assign' => 'Assign non-super-admin roles to staff.',
            'admin.roles.super_admin.assign' => 'Grant or revoke the Super Admin role.',
            'admin.permissions.manage' => 'Manage assignments from the recognized permission catalog.',
            'admin.permissions.view' => 'View the recognized permission catalog and role usage.',
            'content.view' => 'View catalog content.',
            'content.create' => 'Create content drafts.',
            'content.update' => 'Update content within assigned scope.',
            'content.delete' => 'Delete eligible content.',
            'content.submit' => 'Submit content for review.',
            'content.review' => 'Review submitted content.',
            'content.approve' => 'Approve submitted revisions.',
            'content.reject' => 'Reject submitted revisions.',
            'content.publish' => 'Publish approved content.',
            'content.unpublish' => 'Unpublish catalog content.',
            'content.schedule' => 'Schedule approved content.',
            'media.view' => 'View media assets and processing status.',
            'media.upload' => 'Upload source media.',
            'media.update' => 'Replace or update media assets through controlled workflows.',
            'media.delete' => 'Delete eligible media assets.',
            'media.transcode' => 'Start media transcoding.',
            'media.transcode.retry' => 'Retry failed media transcoding jobs.',
            'media.subtitles.manage' => 'Manage subtitle tracks.',
            'media.audio.manage' => 'Manage audio tracks.',
            'media.drm.configure' => 'Configure DRM workflows without exposing raw keys.',
            'subscriptions.view' => 'View plans and subscription states.',
            'subscriptions.subscribers.view' => 'View paginated subscriber records for subscription operations.',
            'subscriptions.plans.create' => 'Create subscription plans.',
            'subscriptions.plans.update' => 'Update subscription plans.',
            'subscriptions.plans.archive' => 'Archive eligible subscription plans.',
            'subscriptions.coupons.manage' => 'Manage subscription coupons.',
            'subscriptions.trials.manage' => 'Manage trial policies.',
            'finance.payments.view' => 'View payment records.',
            'finance.invoices.view' => 'View invoices.',
            'finance.refunds.request' => 'Request refunds for review.',
            'finance.refunds.approve' => 'Approve refunds subject to applicable controls.',
            'finance.reconciliation.view' => 'View billing reconciliation.',
            'finance.reports.export' => 'Export authorized finance reports.',
            'marketing.banners.manage' => 'Manage homepage banners.',
            'marketing.campaigns.manage' => 'Manage featured campaigns.',
            'marketing.notifications.manage' => 'Manage promotional notification templates and sends.',
            'marketing.promotions.manage' => 'Manage marketing promotions.',
            'support.accounts.view' => 'View support-authorized customer account information.',
            'support.tickets.manage' => 'Manage support tickets and internal notes.',
            'support.playback.view' => 'Investigate customer playback failures.',
            'support.devices.view' => 'View registered customer devices.',
            'support.sessions.revoke' => 'Revoke customer sessions through approved workflows.',
            'analytics.audience.view' => 'View audience analytics.',
            'analytics.subscriptions.view' => 'View subscription analytics.',
            'analytics.content.view' => 'View content performance analytics.',
            'analytics.revenue.view' => 'View aggregated revenue analytics.',
            'analytics.playback.view' => 'View playback quality analytics.',
            'analytics.export' => 'Export authorized aggregate analytics.',
            'operations.health.view' => 'View service health.',
            'operations.logs.view' => 'View sanitized application logs.',
            'operations.jobs.view' => 'View background job status.',
            'operations.jobs.retry' => 'Retry approved background jobs.',
            'operations.incidents.manage' => 'Manage operational incidents.',
            'settings.general.manage' => 'Manage general platform settings.',
            'settings.security.manage' => 'Manage security policies.',
            'settings.integrations.manage' => 'Manage integration configuration.',
            'audit.view' => 'View authorized audit records.',
            'audit.export' => 'Export authorized audit records.',
        ];
    }

    /** @return array<string, list<string>> Canonical role name => permission names. */
    public static function rolePermissions(): array
    {
        $all = array_keys(self::permissions());

        return [
            'super-admin' => $all,
            'platform_admin' => [
                'admin.access', 'admin.dashboard.view', 'admin.users.view', 'admin.users.create',
                'admin.users.update', 'admin.users.suspend', 'content.view', 'subscriptions.view',
                'analytics.audience.view', 'analytics.subscriptions.view', 'analytics.content.view',
                'analytics.playback.view', 'operations.health.view', 'settings.general.manage', 'audit.view',
                'admin.staff.view',
            ],
            'content_manager' => ['admin.access', 'admin.dashboard.view', 'content.view', 'content.create', 'content.update', 'content.delete', 'content.publish', 'content.unpublish', 'content.schedule', 'media.view', 'media.update'],
            'content_editor' => ['admin.access', 'admin.dashboard.view', 'content.view', 'content.create', 'content.update', 'content.submit'],
            'content_reviewer' => ['admin.access', 'admin.dashboard.view', 'content.view', 'content.review', 'content.approve', 'content.reject'],
            'media_manager' => ['admin.access', 'admin.dashboard.view', 'content.view', 'media.view', 'media.upload', 'media.update', 'media.delete', 'media.transcode', 'media.transcode.retry', 'media.subtitles.manage', 'media.audio.manage', 'media.drm.configure'],
            'subscription_manager' => ['admin.access', 'admin.dashboard.view', 'subscriptions.view', 'subscriptions.subscribers.view', 'subscriptions.plans.create', 'subscriptions.plans.update', 'subscriptions.plans.archive', 'subscriptions.coupons.manage', 'subscriptions.trials.manage'],
            'finance_manager' => ['admin.access', 'admin.dashboard.view', 'finance.payments.view', 'finance.invoices.view', 'finance.refunds.request', 'finance.reconciliation.view', 'finance.reports.export', 'analytics.revenue.view'],
            'marketing_manager' => ['admin.access', 'admin.dashboard.view', 'content.view', 'marketing.banners.manage', 'marketing.campaigns.manage', 'marketing.notifications.manage', 'marketing.promotions.manage'],
            'customer_support' => ['admin.access', 'admin.dashboard.view', 'support.accounts.view', 'support.tickets.manage', 'support.playback.view', 'support.devices.view', 'support.sessions.revoke', 'subscriptions.view'],
            'analytics_manager' => ['admin.access', 'admin.dashboard.view', 'analytics.audience.view', 'analytics.subscriptions.view', 'analytics.content.view', 'analytics.revenue.view', 'analytics.playback.view', 'analytics.export'],
            'technical_operations_admin' => ['admin.access', 'admin.dashboard.view', 'operations.health.view', 'operations.logs.view', 'operations.jobs.view', 'operations.jobs.retry', 'operations.incidents.manage', 'analytics.playback.view'],
        ];
    }

    /** @return array<string, string> Canonical role name => display title. */
    public static function roles(): array
    {
        return [
            'super-admin' => 'Super Admin',
            'platform_admin' => 'Platform Admin',
            'content_manager' => 'Content Manager',
            'content_editor' => 'Content Editor',
            'content_reviewer' => 'Content Reviewer',
            'media_manager' => 'Media Manager',
            'subscription_manager' => 'Subscription Manager',
            'finance_manager' => 'Finance Manager',
            'marketing_manager' => 'Marketing Manager',
            'customer_support' => 'Customer Support',
            'analytics_manager' => 'Analytics Manager',
            'technical_operations_admin' => 'Technical Operations Admin',
        ];
    }
}

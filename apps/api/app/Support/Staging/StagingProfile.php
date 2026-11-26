<?php

namespace App\Support\Staging;

use InvalidArgumentException;

final readonly class StagingProfile
{
    /** @param array<string, int> $counts */
    private function __construct(
        public string $name,
        public array $counts,
        public int $historyMonths,
        public int $batchSize,
    ) {}

    /** @param array<string, int> $overrides */
    public static function fromConfig(string $name, int $historyMonths, int $batchSize, array $overrides = []): self
    {
        $profiles = (array) config('staging-data.profiles');
        if (! isset($profiles[$name])) {
            throw new InvalidArgumentException("Unknown staging profile: {$name}");
        }
        if ($historyMonths < 1 || $historyMonths > 120) {
            throw new InvalidArgumentException('History months must be between 1 and 120.');
        }
        if ($batchSize < 1 || $batchSize > 10000) {
            throw new InvalidArgumentException('Batch size must be between 1 and 10000.');
        }

        $counts = $profiles[$name];
        $explicitOverrides = $overrides;
        foreach ($overrides as $entity => $count) {
            if (! array_key_exists($entity, $counts) || $count < 0) {
                throw new InvalidArgumentException("Unsupported or invalid staging count override: {$entity}");
            }
            $counts[$entity] = $count;
        }
        if (array_key_exists('customers', $explicitOverrides)) {
            $baseCustomerCount = max(1, (int) $profiles[$name]['customers']);
            foreach (['profiles', 'devices'] as $dependent) {
                if (! array_key_exists($dependent, $explicitOverrides)) {
                    $counts[$dependent] = (int) round($counts['customers'] * $profiles[$name][$dependent] / $baseCustomerCount);
                }
            }
        }
        if (array_intersect(['customers', 'playback_sessions', 'profiles', 'media_assets'], array_keys($explicitOverrides)) !== []
            && ! array_key_exists('continue_watch', $explicitOverrides)) {
            $counts['continue_watch'] = min(
                (int) $counts['continue_watch'],
                intdiv((int) $counts['playback_sessions'], 50),
                (int) $counts['profiles'] * (int) $counts['media_assets'],
            );
        }
        if (array_intersect(['media_assets', 'movies', 'series'], array_keys($explicitOverrides)) !== [] && ! array_key_exists('banners', $explicitOverrides)) {
            $counts['banners'] = min((int) $counts['banners'], (int) $counts['media_assets'], (int) $counts['movies'] + (int) $counts['series']);
        }
        if ($counts['staff'] < count(array_keys(\App\Support\Rbac\RbacRegistry::roles()))) {
            throw new InvalidArgumentException('Staging staff count must include at least one account for every canonical administrative role.');
        }
        if ($counts['customers'] === 0 && ($counts['profiles'] > 0 || $counts['devices'] > 0)) {
            throw new InvalidArgumentException('Profile and device targets require at least one customer.');
        }
        if ($counts['customers'] > 0 && ($counts['profiles'] < $counts['customers'] || $counts['profiles'] > $counts['customers'] * 4)) {
            throw new InvalidArgumentException('Profile target must allow at least one and no more than four profiles per customer.');
        }
        if ($counts['subscriptions'] > 0 && ($counts['customers'] === 0 || $counts['plans'] === 0)) {
            throw new InvalidArgumentException('Subscription targets require staging customers and plans.');
        }
        if ($counts['payments'] > 0 && $counts['plans'] === 0) {
            throw new InvalidArgumentException('Payment targets require staging plans.');
        }
        if ($counts['coupons'] > 0 && $counts['plans'] === 0) {
            throw new InvalidArgumentException('Coupon targets require staging plans.');
        }
        if ($counts['search_history'] > 0 && ($counts['customers'] === 0 || $counts['profiles'] === 0 || $counts['movies'] + $counts['series'] === 0)) {
            throw new InvalidArgumentException('Search history requires staging customers, profiles, and catalog titles.');
        }
        if (($counts['watchlists'] > 0 || $counts['watch_history'] > 0) && ($counts['customers'] === 0 || $counts['profiles'] === 0 || $counts['movies'] + $counts['series'] === 0)) {
            throw new InvalidArgumentException('Staging watchlists and watch history require customers, profiles, and catalog titles.');
        }
        if ($counts['reviews'] > 0 && ($counts['customers'] === 0 || $counts['movies'] + $counts['series'] === 0)) {
            throw new InvalidArgumentException('Staging customer reviews require customers and catalog titles.');
        }
        if ($counts['content_review_submissions'] > 0 && $counts['movies'] + $counts['series'] === 0) {
            throw new InvalidArgumentException('Content review submissions require staging movies or series.');
        }
        if ($counts['playback_sessions'] > 0 && ($counts['customers'] === 0 || $counts['profiles'] === 0 || $counts['devices'] === 0 || $counts['movies'] === 0 || $counts['media_assets'] === 0)) {
            throw new InvalidArgumentException('Playback sessions require staging customers, profiles, devices, movies, and media assets.');
        }
        if ($counts['continue_watch'] > 0 && $counts['playback_sessions'] === 0) {
            throw new InvalidArgumentException('Continue-watching progress requires generated playback sessions.');
        }
        if ($counts['continue_watch'] > $counts['playback_sessions'] || $counts['continue_watch'] > $counts['profiles'] * $counts['media_assets']) {
            throw new InvalidArgumentException('Continue-watching target cannot exceed playback sessions or the profile/media-pair capacity.');
        }
        if ($counts['banners'] > 0 && ($counts['movies'] + $counts['series'] === 0 || $counts['media_assets'] === 0)) {
            throw new InvalidArgumentException('Staging homepage banners require published catalog titles and generated media posters.');
        }
        if ($counts['banners'] > min($counts['movies'] + $counts['series'], $counts['media_assets'])) {
            throw new InvalidArgumentException('Staging banner target cannot exceed available catalog title or media poster capacity.');
        }
        if ($counts['support_tickets'] > 0 && ($counts['customers'] === 0 || $counts['staff'] === 0)) {
            throw new InvalidArgumentException('Support tickets require staging customers and staff accounts.');
        }
        if ($counts['campaigns'] > 0 && ($counts['customers'] === 0 || $counts['coupons'] === 0)) {
            throw new InvalidArgumentException('Staging campaigns require customers for consent records and coupons for promotion campaigns.');
        }
        if ($counts['notifications'] > 0 && $counts['customers'] === 0) {
            throw new InvalidArgumentException('Staging notifications require customer accounts.');
        }
        if ($counts['analytics_reports'] > 0 && $counts['staff'] < count(array_keys(\App\Support\Rbac\RbacRegistry::roles()))) {
            throw new InvalidArgumentException('Staging analytics reports require canonical administrative staff.');
        }
        if ($counts['movies'] + $counts['series'] > 0 && $counts['cast_crew'] < 4) {
            throw new InvalidArgumentException('Catalog content requires at least three cast members and one director.');
        }

        return new self($name, $counts, $historyMonths, $batchSize);
    }
}

<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [

        'Modules\Subscriptions\Console\Commands\CheckSubscription',
        \App\Console\Commands\OptimizeImages::class,
        \App\Console\Commands\CleanLatestMovies::class,
        \App\Console\Commands\CleanExpiredMediaUploads::class,
        \App\Console\Commands\PublishMarketingCampaigns::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('queue:work --tries=3 --stop-when-empty')->name('queue-worker')->withoutOverlapping();

        
        
        // Notification commands - run daily
        $schedule->command('subscriptions:notify')->dailyAt('09:00')->description('Send subscription expiry reminders');
        $schedule->command('reminder:notify')->dailyAt('10:00')->description('Send upcoming content reminders');
        $schedule->command('continuewatch:notify')->dailyAt('11:00')->description('Send continue watching reminders');
        $schedule->command('swann:clean-latest-movies')->dailyAt('11:00')->description('Remove old movies from latest-movies list');
        $schedule->command('media:uploads-clean-expired')->name('media-upload-cleanup')->hourly()->withoutOverlapping()->description('Clean expired private media upload chunks');
        $schedule->command('marketing:publish-scheduled')->name('marketing:publish-scheduled')->everyMinute()->withoutOverlapping()->description('Publish and expire approved marketing campaigns');
        $schedule->command('analytics:generate-scheduled-reports')->name('analytics:generate-scheduled-reports')->everyMinute()->withoutOverlapping()->description('Generate due privacy-preserving analytics reports');
        $schedule->command('backup:run')->name('production-backup')->dailyAt('01:30')->withoutOverlapping();
        $schedule->command('backup:clean')->name('production-backup-cleanup')->dailyAt('02:15')->withoutOverlapping();
        $schedule->command('backup:monitor')->name('production-backup-monitor')->dailyAt('02:45')->withoutOverlapping();
        $schedule->call(function () {
            if (\Illuminate\Support\Facades\Schema::hasTable('subscription_coupon_reservations')) {
                \Illuminate\Support\Facades\DB::table('subscription_coupon_reservations')
                    ->where('status', 'pending')->where('expires_at', '<=', now())
                    ->update(['status' => 'expired', 'updated_at' => now()]);
            }
        })->name('expire-subscription-coupon-reservations')->hourly()->withoutOverlapping()->description('Expire unused subscription coupon checkout reservations');

        $schedule->call(function () {
            \App\Models\Device::whereNotNull('session_id')
                ->where('last_activity', '<', now()->subMinutes(config('session.lifetime')))
                ->delete();
        })->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

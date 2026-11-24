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
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('queue:work --tries=3 --stop-when-empty')->withoutOverlapping();

        
        
        // Notification commands - run daily
        $schedule->command('subscriptions:notify')->dailyAt('09:00')->description('Send subscription expiry reminders');
        $schedule->command('reminder:notify')->dailyAt('10:00')->description('Send upcoming content reminders');
        $schedule->command('continuewatch:notify')->dailyAt('11:00')->description('Send continue watching reminders');
        $schedule->command('swann:clean-latest-movies')->dailyAt('11:00')->description('Remove old movies from latest-movies list');
        $schedule->command('media:uploads-clean-expired')->hourly()->withoutOverlapping()->description('Clean expired private media upload chunks');
        $schedule->call(function () {
            if (\Illuminate\Support\Facades\Schema::hasTable('subscription_coupon_reservations')) {
                \Illuminate\Support\Facades\DB::table('subscription_coupon_reservations')
                    ->where('status', 'pending')->where('expires_at', '<=', now())
                    ->update(['status' => 'expired', 'updated_at' => now()]);
            }
        })->hourly()->withoutOverlapping()->description('Expire unused subscription coupon checkout reservations');

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

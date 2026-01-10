<?php

namespace App\Console;

use App\Jobs\FetchExchangeRates;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
                // $schedule->job(new FetchExchangeRates)->everyMinute();
                  $schedule->job(new FetchExchangeRates)->everyMinute()
                   ->withoutOverlapping(10)
                  ->appendOutputTo(storage_path('logs/schedule.log'));;

    // 2) Then run a worker to drain the queue (no overlap)
    $schedule->command('queue:work --stop-when-empty --sleep=3 --tries=3')
        ->everyMinute()
        ->withoutOverlapping(10)
          ->appendOutputTo(storage_path('logs/queue_worker.log'));

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        
        require base_path('routes/console.php');
    }
}

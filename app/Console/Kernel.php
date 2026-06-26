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
        $schedule->job(new FetchExchangeRates)
            ->everyMinute()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/schedule.log'));

        $schedule->command('wallet:reconcile-on-chain')
            ->dailyAt('01:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/reconcile-on-chain.log'));

        $schedule->command('audit:daily-ai')
            ->dailyAt('07:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/ai-audit.log'));

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

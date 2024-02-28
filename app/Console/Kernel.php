<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $directory = '/assets/img/qrcodeimg';
            $disk = 'qrcodeimg';

            $files = Storage::disk($disk)->allFiles($directory);
            foreach ($files as $file) {
                if(file_exists($file)){
                    $time = Storage::disk($disk)->lastModified($file);
                    $fileModifiedDateTime = Carbon::parse($time);
                        
                    if (Carbon::now()->gt($fileModifiedDateTime->addHour(1))) {
                        Storage::disk($disk)->delete($file);
                    }
                }
            }
        })->hourly();
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

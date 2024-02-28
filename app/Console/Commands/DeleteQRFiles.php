<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Log;

class DeleteQRFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:delete-qr-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deleting expired and unused qr code files in public folder';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directory = '/assets/img/qrcodeimg';
        $disk = 'qrcodeimg';
        
        $files = Storage::disk($disk)->allFiles($directory);
        foreach ($files as $file) {
            $time = Storage::disk($disk)->lastModified($file);
            $fileModifiedDateTime = Carbon::parse($time);
                
            if (Carbon::now()->gt($fileModifiedDateTime->addHour(1))) {
                Storage::disk($disk)->delete($file);
            }
        }

        return Command::SUCCESS;
    }
}

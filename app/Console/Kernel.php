<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

use App\Models\Clients;
use App\Models\Invoices;
use Xendit\Xendit;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function() {
            $reponse = $this->CancelPendingPayment();
            Log::info(['CRON NAME'=>'Cancel Pending Payment', 'CRON RESPONSE'=>$reponse]);
        })->everyFourHours();
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

    public function GetSettingValue($setting_key)
    {
        $setting = Setting::where('setting_key', $setting_key)->first();
        $setting_value = $setting->value_text;
        return $setting_value;
    }
    
    /** CRON FUNCTION TO CHANGE STATUS OF INVOICE */
    public function CancelPendingPayment()
    {                     
        date_default_timezone_set('Asia/Jakarta');
        $dateNow = date('Y-m-d H:i:s');
        // Delete Expired
        return null;
    }
}

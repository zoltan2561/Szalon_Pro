<?php

namespace App\Console\Commands;

use App\Services\OperationsHealth;
use App\Services\SmsNotificationSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('salon:sms-notify')]
#[Description('Send due SMS reminders when the optional SMS module is enabled')]
class SendSmsNotifications extends Command
{
    public function handle(SmsNotificationSender $sender, OperationsHealth $health): int
    {
        try {
            $this->info('SMS-emlékeztetők feldolgozva: '.$sender->sendDue().'.');
        } finally {
            $health->recordRun('sms');
        }

        return self::SUCCESS;
    }
}

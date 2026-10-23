<?php

namespace App\Console\Commands;

use App\Services\SmsNotificationSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('salon:sms-notify')]
#[Description('Send due SMS reminders when the optional SMS module is enabled')]
class SendSmsNotifications extends Command
{
    public function handle(SmsNotificationSender $sender): int
    {
        $this->info('SMS-emlékeztetők feldolgozva: '.$sender->sendDue().'.');

        return self::SUCCESS;
    }
}

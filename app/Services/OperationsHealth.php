<?php

namespace App\Services;

use App\Console\Commands\BackupSalon;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationsHealth
{
    /** @return list<string> */
    public function pendingMigrations(): array
    {
        $migrator = app('migrator');
        $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));

        return $migrator->repositoryExists() ? array_values(array_diff($files, $migrator->getRepository()->getRan())) : $files;
    }

    public function schemaReady(): bool
    {
        try {
            if ($this->pendingMigrations() !== []) {
                return false;
            }
            foreach (BackupSalon::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function heartbeatKey(string $channel): string
    {
        return 'salon:heartbeat:'.hash('sha256', (string) config('database.connections.'.config('database.default').'.database')).':'.$channel;
    }

    public function recordRun(string $channel): void
    {
        Cache::forever($this->heartbeatKey($channel), now()->toIso8601String());
    }

    public function lastRun(string $channel): ?CarbonImmutable
    {
        $value = Cache::get($this->heartbeatKey($channel));

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    /** @return array{email: int, sms: int} */
    public function dueCounts(): array
    {
        $counts = [];
        foreach (['email' => 'outbox_messages', 'sms' => 'sms_outbox_messages'] as $channel => $table) {
            $counts[$channel] = Schema::hasTable($table) ? DB::table($table)->whereIn('status', ['pending', 'retry', 'failed', 'uncertain'])->where('scheduled_at', '<=', now())->count() : 0;
        }

        return $counts;
    }

    /** @return array<string, string> */
    public function warnings(): array
    {
        $warnings = [];
        if (Schema::hasTable('fiscal_documents')) {
            $billingIssues = DB::table('fiscal_documents')->where(function ($query): void {
                $query->whereIn('status', ['failed', 'uncertain', 'pending'])->orWhere(fn ($stale) => $stale->where('status', 'sending')->where('last_attempt_at', '<', now()->subMinutes(5)));
            })->count();
            if ($billingIssues) {
                $warnings['billing_documents'] = $billingIssues.' bizonylatkérés egyeztetésre vagy javításra vár.';
            }
        }
        if (! $this->schemaReady()) {
            $warnings['schema'] = 'Adatbázis-frissítés szükséges. Kérd az üzemeltető segítségét; a foglalási adatok megmaradnak.';
        }
        if (app()->environment('production') && config('app.debug')) {
            $warnings['debug'] = 'Az éles környezet hibakeresési módja bekapcsolva maradt.';
        }
        foreach (['email' => 'E-mail', 'sms' => 'SMS'] as $channel => $label) {
            $lastRun = $this->lastRun($channel);
            if (! $lastRun || $lastRun->lt(now()->subMinutes(5))) {
                $warnings['heartbeat_'.$channel] = $label.': az időzített feldolgozás több mint öt perce nem jelentkezett.';
            }
        }
        foreach ($this->dueCounts() as $channel => $count) {
            if ($count > 0) {
                $warnings['due_'.$channel] = ($channel === 'sms' ? 'SMS' : 'E-mail').': '.$count.' esedékes értesítés feldolgozásra vagy ellenőrzésre vár.';
            }
        }
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        $mailReady = match ($transport) {
            'smtp' => filled(config('mail.mailers.smtp.host')) && ! in_array(config('mail.mailers.smtp.host'), ['127.0.0.1', 'localhost']),
            'sendmail' => filled(config('mail.mailers.sendmail.path')),
            'postmark' => filled(config('services.postmark.key')),
            'resend' => filled(config('services.resend.key')),
            'ses', 'ses-v2' => filled(config('services.ses.key')) && filled(config('services.ses.secret')),
            default => false,
        };
        if (! $mailReady || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            $warnings['mail'] = 'A valódi e-mail-küldés nincs beállítva. A helyi levélpróba nem kézbesítés.';
        }
        if (Schema::hasTable('salons') && Salon::current()->featureEnabled('sms') && ! app(SmsGateway::class)->configured()) {
            $warnings['sms'] = 'Az SMS-modul aktív, de a szolgáltató nincs beállítva.';
        }
        if (Schema::hasColumn('salons', 'billing_agent_key') && Salon::current()->featureEnabled('billing') && ! app(BillingService::class)->configured()) {
            $warnings['billing_configuration'] = 'A bizonylatküldés még nincs beállítva vagy engedélyezve.';
        }

        return $warnings;
    }
}

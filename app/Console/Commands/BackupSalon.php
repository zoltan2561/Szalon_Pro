<?php

namespace App\Console\Commands;

use App\Models\Salon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BackupSalon extends Command
{
    public const LEGACY_TABLES = ['salons', 'staff', 'services', 'service_staff', 'guests', 'users', 'bookings', 'time_blocks', 'work_exceptions', 'outbox_messages', 'booking_events'];

    public const TABLES = [
        ...self::LEGACY_TABLES,
        'google_calendar_connections', 'google_calendar_events', 'google_calendar_busies',
        'sms_outbox_messages', 'waitlist_entries', 'benefit_settings', 'pass_plans',
        'guest_passes', 'pass_uses', 'loyalty_entries',
    ];

    protected $signature = 'salon:backup {--path= : Titkosított mentés célfájlja}';

    protected $description = 'Tranzakciós, titkosított adatmentés; a visszaállításhoz az eredeti APP_KEY szükséges';

    public function handle(): int
    {
        $path = $this->option('path') ?: storage_path('app/private/backups/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.backup');
        if (file_exists($path)) {
            $this->error('A célfájl már létezik.');

            return self::FAILURE;
        }
        $snapshot = DB::transaction(function (): array {
            Salon::whereKey(1)->increment('lock_version');
            $tables = [];
            foreach (self::TABLES as $table) {
                $tables[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            }
            $media = [];
            $salon = Salon::current();
            foreach (array_unique(array_filter(array_merge([$salon->logo, $salon->hero_image], $salon->gallery ?? []))) as $file) {
                if (! preg_match('#^(branding|gallery)/[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$#', $file)) {
                    throw new \RuntimeException('Invalid media path');
                }
                $media[$file] = base64_encode(Storage::disk('public')->get($file));
            }

            return ['format' => 2, 'created_at' => now()->toIso8601String(), 'tables' => $tables, 'media' => $media];
        }, 5);
        File::ensureDirectoryExists(dirname($path));
        if (file_put_contents($path, Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR)), LOCK_EX) === false) {
            $this->error('A mentés írása sikertelen.');

            return self::FAILURE;
        }
        $this->info('A titkosított mentés elkészült: '.$path);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RestoreSalon extends Command
{
    protected $signature = 'salon:restore {path : Titkosított mentés}';

    protected $description = 'Mentés visszaállítása kizárólag migrált, teljesen üres adatbázisba';

    public function handle(): int
    {
        foreach (BackupSalon::TABLES as $table) {
            if (DB::table($table)->exists()) {
                $this->error('A cél nem üres. Külön, üres helyre állíts vissza; meglévő adatot nem írunk felül.');

                return self::FAILURE;
            }
        }
        $created = [];
        try {
            $raw = file_get_contents($this->argument('path'));
            $snapshot = json_decode(Crypt::decryptString($raw), true, 512, JSON_THROW_ON_ERROR);
            $tables = match ($snapshot['format'] ?? null) {
                1 => BackupSalon::LEGACY_TABLES,
                2 => BackupSalon::V2_TABLES,
                3 => BackupSalon::V3_TABLES,
                4 => BackupSalon::V4_TABLES,
                5 => BackupSalon::TABLES,
                default => null,
            };
            if ($tables === null || ! isset($snapshot['tables']) || array_keys($snapshot['tables']) !== $tables) {
                throw new \RuntimeException('format');
            }
            if (in_array($snapshot['format'] ?? null, [4, 5], true)) {
                $referenced = [];
                foreach ($snapshot['tables']['bookings'] as $booking) {
                    foreach (json_decode($booking['work_photos'] ?? '[]', true, flags: JSON_THROW_ON_ERROR) ?? [] as $path) {
                        if (! preg_match('#^visit-photos/'.$booking['id'].'/[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$#', $path) || ! array_key_exists($path, $snapshot['private_media'] ?? [])) {
                            throw new \RuntimeException('private media reference');
                        }
                        $referenced[] = $path;
                    }
                }
                $referenced = array_values(array_unique($referenced));
                $provided = array_keys($snapshot['private_media'] ?? []);
                sort($referenced);
                sort($provided);
                if ($referenced !== $provided) {
                    throw new \RuntimeException('unreferenced private media');
                }
            }
            foreach ($snapshot['media'] ?? [] as $path => $contents) {
                if (! preg_match('#^(branding|gallery)/[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$#', $path) || Storage::disk('public')->exists($path) || base64_decode($contents, true) === false) {
                    throw new \RuntimeException('media');
                }
            }
            foreach ($snapshot['private_media'] ?? [] as $path => $contents) {
                if (! preg_match('#^visit-photos/[0-9]+/[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$#', $path) || Storage::disk('local')->exists($path) || base64_decode($contents, true) === false) {
                    throw new \RuntimeException('private media');
                }
            }
            foreach ($snapshot['media'] ?? [] as $path => $contents) {
                if (! Storage::disk('public')->put($path, base64_decode($contents, true))) {
                    throw new \RuntimeException('media write');
                }
                $created[] = ['disk' => 'public', 'path' => $path];
            }
            foreach ($snapshot['private_media'] ?? [] as $path => $contents) {
                if (! Storage::disk('local')->put($path, base64_decode($contents, true))) {
                    throw new \RuntimeException('private media write');
                }
                $created[] = ['disk' => 'local', 'path' => $path];
            }
            DB::transaction(function () use ($snapshot, $tables): void {
                foreach ($tables as $table) {
                    foreach (array_chunk($snapshot['tables'][$table], 50) as $rows) {
                        DB::table($table)->insert($rows);
                    }
                }
            });
        } catch (\Throwable) {
            foreach ($created as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            } $this->error('A visszaállítás sikertelen. Ellenőrizd a fájlt, az APP_KEY-t és a séma verzióját.');

            return self::FAILURE;
        }
        $this->info('Visszaállítás kész. Ellenőrizd a vendégeket, időpontokat és az értesítések sorát a küldés engedélyezése előtt.');

        return self::SUCCESS;
    }
}

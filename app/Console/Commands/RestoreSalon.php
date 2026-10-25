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
                3 => BackupSalon::TABLES,
                default => null,
            };
            if ($tables === null || ! isset($snapshot['tables']) || array_keys($snapshot['tables']) !== $tables) {
                throw new \RuntimeException('format');
            }
            foreach ($snapshot['media'] ?? [] as $path => $contents) {
                if (! preg_match('#^(branding|gallery)/[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$#', $path) || Storage::disk('public')->exists($path) || base64_decode($contents, true) === false) {
                    throw new \RuntimeException('media');
                }
            }
            foreach ($snapshot['media'] ?? [] as $path => $contents) {
                if (! Storage::disk('public')->put($path, base64_decode($contents, true))) {
                    throw new \RuntimeException('media write');
                }
                $created[] = $path;
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
                Storage::disk('public')->delete($file);
            } $this->error('A visszaállítás sikertelen. Ellenőrizd a fájlt, az APP_KEY-t és a séma verzióját.');

            return self::FAILURE;
        }
        $this->info('Visszaállítás kész. Ellenőrizd a vendégeket, időpontokat és az értesítések sorát a küldés engedélyezése előtt.');

        return self::SUCCESS;
    }
}

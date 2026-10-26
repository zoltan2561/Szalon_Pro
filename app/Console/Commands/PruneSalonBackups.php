<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PruneSalonBackups extends Command
{
    protected $signature = 'salon:prune-backups {--days= : Megőrzési idő napban; alapérték 30} {--dry-run : Csak a törlendő fájlok felsorolása}';

    protected $description = 'Régi automatikus mentések takarítása; a legutóbbi mentés mindig megmarad';

    public function handle(): int
    {
        $days = filter_var($this->option('days') ?? config('salon.backup_retention_days'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 3650) {
            $this->error('A megőrzési idő 1 és 3650 közötti egész nap legyen.');

            return self::FAILURE;
        }
        $directory = config('salon.backup_directory');
        if (! File::isDirectory($directory)) {
            $this->info('Nincs takarítandó mentés.');

            return self::SUCCESS;
        }
        $backups = collect(File::files($directory))
            ->filter(fn (\SplFileInfo $file): bool => ! $file->isLink() && preg_match('/^\d{8}-\d{6}-[a-f0-9]{8}\.backup$/', $file->getFilename()))
            ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime());
        $expired = $backups->skip(1)->filter(fn (\SplFileInfo $file): bool => $file->getMTime() < now()->subDays($days)->timestamp);
        $count = 0;
        foreach ($expired as $file) {
            if ($this->option('dry-run')) {
                $this->line('Törölhető: '.$file->getFilename());
            } elseif (! File::delete($file->getPathname())) {
                $this->error('Egy mentés törlése sikertelen.');

                return self::FAILURE;
            }
            $count++;
        }
        $this->info($this->option('dry-run') ? $count.' régi mentés törölhető; nem töröltünk fájlt.' : $count.' régi mentés törölve.');

        return self::SUCCESS;
    }
}

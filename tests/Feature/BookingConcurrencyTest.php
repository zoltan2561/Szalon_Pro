<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    public function test_two_independent_processes_cannot_reserve_the_same_slot(): void
    {
        $path = storage_path('framework/race-'.Str::uuid().'.sqlite');
        $gate = $path.'.go';
        $processes = [];
        touch($path);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');
        try {
            Artisan::call('migrate', ['--force' => true]);
            Salon::factory()->create();
            $service = Service::factory()->create();
            $staff = $service->staff()->first();
            $date = now('Europe/Budapest')->addDays(3)->toDateString();
            $script = <<<'PHP'
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[1].'.ready.'.$argv[2],'ready');
$deadline=microtime(true)+15;
while (!file_exists($argv[1].'.go') && microtime(true)<$deadline) { usleep(10000); }
try {
 app(\App\Services\BookingService::class)->create(['service_id'=>(int)$argv[3],'staff_id'=>(int)$argv[4],'starts_at'=>$argv[5].'T10:00:00'.\Carbon\CarbonImmutable::parse($argv[5].' 10:00','Europe/Budapest')->format('P'),'name'=>'Race test','email'=>'race@example.test','request_key'=>\Illuminate\Support\Str::uuid()->toString()]);
 echo 'saved';
} catch (\Illuminate\Validation\ValidationException) { echo 'conflict'; }
catch (\Throwable $e) { echo get_class($e); exit(2); }
PHP;
            foreach ([1, 2] as $i) {
                $p = new Process([PHP_BINARY, '-r', $script, $path, (string) $i, (string) $service->id, (string) $staff->id, $date], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'CACHE_STORE' => 'array', 'BOOST_ENABLED' => 'false']);
                $p->setTimeout(30);
                $p->start();
                $processes[] = $p;
            }
            $deadline = microtime(true) + 15;
            while ((! file_exists($path.'.ready.1') || ! file_exists($path.'.ready.2')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($path.'.ready.1');
            $this->assertFileExists($path.'.ready.2');
            touch($gate);
            $out = [];
            foreach ($processes as $p) {
                $p->wait();
                $this->assertTrue($p->isSuccessful(), $p->getOutput());
                $out[] = $p->getOutput();
            }
            sort($out);
            $this->assertSame(['conflict', 'saved'], $out);
            $this->assertSame(1, DB::table('bookings')->count());
            $this->assertSame(1, DB::table('guests')->count());
        } finally {
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }
            DB::disconnect('sqlite');
            foreach ([$path, $gate, $path.'.ready.1', $path.'.ready.2', $path.'-wal', $path.'-shm'] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}

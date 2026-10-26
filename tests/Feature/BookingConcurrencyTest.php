<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use App\Services\OperationsHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    public function test_twenty_concurrent_http_post_pairs_use_database_cache_without_server_errors(): void
    {
        $path = storage_path('framework/http-race-'.Str::uuid().'.sqlite');
        $processes = [];
        touch($path);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');
        try {
            Artisan::call('migrate', ['--force' => true]);
            Salon::factory()->create(['horizon_days' => 90]);
            $service = Service::factory()->create();
            $staff = $service->staff()->first();
            $this->assertSame('wal', DB::selectOne('PRAGMA journal_mode')->journal_mode);
            $this->assertSame(10000, DB::selectOne('PRAGMA busy_timeout')->timeout);
            $this->assertArrayNotHasKey('sqlite_wal', app(OperationsHealth::class)->warnings());
            DB::statement('PRAGMA journal_mode=DELETE');
            $this->assertArrayHasKey('sqlite_wal', app(OperationsHealth::class)->warnings());
            DB::statement('PRAGMA journal_mode=WAL');
            $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$start = \Carbon\CarbonImmutable::parse($argv[5].' 10:00', 'Europe/Budapest');
$request = \Illuminate\Http\Request::create('http://localhost/foglalas', 'POST', [
 'service_id' => (int) $argv[3], 'slot' => $argv[4].'|'.$start->toIso8601String(),
 'name' => 'HTTP Race', 'email' => 'race@example.test', 'terms' => 1,
 'request_key' => \Illuminate\Support\Str::uuid()->toString(),
], [], [], ['REMOTE_ADDR' => '192.0.2.'.$argv[6], 'HTTP_REFERER' => 'http://localhost/foglalas', 'HTTP_ACCEPT' => 'text/html']);
file_put_contents($argv[1].'.ready.'.$argv[2], 'ready');
$deadline = microtime(true) + 15;
while (!file_exists($argv[1].'.go') && microtime(true) < $deadline) { usleep(1000); }
$response = $kernel->handle($request);
$errorBag = $request->hasSession() ? $request->session()->get('errors') : null;
$errors = $errorBag instanceof \Illuminate\Support\ViewErrorBag ? $errorBag->all() : array_merge(...array_values($errorBag['default']['messages'] ?? []));
echo json_encode(['status' => $response->getStatusCode(), 'errors' => $errors, 'location' => $response->headers->get('Location')], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
PHP;
            for ($round = 1; $round <= 20; $round++) {
                $date = now('Europe/Budapest')->addDays($round + 2)->toDateString();
                $processes = [];
                foreach ([1, 2] as $index) {
                    $process = new Process([PHP_BINARY, '-r', $script, $path, (string) $index, (string) $service->id, (string) $staff->id, $date, (string) $round], base_path(), ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database', 'BOOST_ENABLED' => 'false']);
                    $process->setTimeout(30);
                    $process->start();
                    $processes[] = $process;
                }
                $deadline = microtime(true) + 15;
                while ((! file_exists($path.'.ready.1') || ! file_exists($path.'.ready.2')) && microtime(true) < $deadline) {
                    usleep(1000);
                }
                $this->assertFileExists($path.'.ready.1');
                $this->assertFileExists($path.'.ready.2');
                touch($path.'.go');
                $conflicts = 0;
                foreach ($processes as $process) {
                    $process->wait();
                    $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame(302, $result['status'], 'Round '.$round.': '.$process->getOutput());
                    if ($result['errors'] !== []) {
                        $conflicts++;
                        $this->assertStringContainsString('már nem szabad', implode(' ', $result['errors']));
                        $this->assertSame('http://localhost/foglalas', $result['location']);
                    } else {
                        $this->assertSame('http://localhost/foglalas/kesz', $result['location']);
                    }
                }
                $this->assertSame(1, $conflicts);
                $this->assertSame($round, DB::table('bookings')->count());
                foreach (['.go', '.ready.1', '.ready.2'] as $suffix) {
                    unlink($path.$suffix);
                }
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::disconnect('sqlite');
            foreach (['', '.go', '.ready.1', '.ready.2', '-wal', '-shm'] as $suffix) {
                if (file_exists($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }

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

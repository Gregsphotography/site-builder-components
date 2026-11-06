<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use SiteBuilder\Components\Media\MediaAsset;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use Symfony\Component\Process\Process;

#[Group('mysql-race')]
class MySqlMediaConcurrencyTest extends DatabaseTestCase
{
    public function test_competing_processes_accept_exactly_one_shared_alt_edit(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Shared resource concurrency runs on isolated MySQL 8.4.');
        }
        $asset = MediaAsset::query()->create(['disk' => 'public', 'path' => 'site-builder-media/race.png', 'name' => 'Race', 'mime' => 'image/png', 'size' => 1, 'alt' => ['en' => 'Original', 'de' => 'Unverändert']]);
        $configuration = config('database.connections.mysql');
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $configuration['host'], 'DB_PORT' => (string) $configuration['port'],
            'DB_SOCKET' => (string) $configuration['unix_socket'], 'DB_DATABASE' => $configuration['database'],
            'DB_USERNAME' => $configuration['username'], 'DB_PASSWORD' => (string) $configuration['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ];
        $autoload = dirname((new ReflectionClass(Application::class))->getFileName(), 6).'/autoload.php';
        $bootstrap = getenv('SITE_BUILDER_TEST_BOOTSTRAP') ?: __DIR__.'/../Fixtures/worker-app/bootstrap/app.php';
        $barrier = sys_get_temp_dir().'/site-builder-media-race-'.Str::uuid();
        mkdir($barrier, 0700);
        $processes = [];
        $results = [];
        try {
            foreach ([1, 2, 3] as $worker) {
                $process = new Process([PHP_BINARY, __DIR__.'/../Fixtures/media-worker.php', $autoload, $bootstrap, $asset->id, $barrier, (string) $worker], null, $environment);
                $process->setTimeout(25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (count(glob($barrier.'/ready-*')) < 3) {
                if (microtime(true) > $deadline || array_any($processes, fn (Process $process): bool => ! $process->isRunning())) {
                    $this->fail('Media workers did not reach their barrier: '.implode('\n', array_map(fn (Process $process): string => $process->getErrorOutput().$process->getOutput(), $processes)));
                }
                usleep(1000);
            }
            file_put_contents($barrier.'/go', 'go');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $results[] = trim($process->getOutput());
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($barrier.'/*') as $file) {
                unlink($file);
            }
            rmdir($barrier);
        }
        $counts = array_count_values($results);
        $this->assertSame(1, $counts['accepted'] ?? 0);
        $this->assertSame(2, $counts['stale_media_version'] ?? 0);
        $this->assertSame(1, $asset->fresh()->write_version);
        $this->assertSame('Unverändert', $asset->fresh()->alt['de']);
        $this->assertContains($asset->fresh()->alt['en'], ['Worker 1', 'Worker 2', 'Worker 3']);
    }
}

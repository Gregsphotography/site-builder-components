<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use SiteBuilder\Components\ComponentsServiceProvider;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Core\Exceptions\WriteConflict;
use SiteBuilder\Core\SiteBuilderServiceProvider;

require $argv[1];
$database = getenv('DB_DATABASE') ?: '';
if (getenv('DB_CONNECTION') !== 'mysql' || ! str_starts_with($database, 'site_builder_') || ! str_ends_with($database, '_test')) {
    throw new RuntimeException('Media concurrency worker requires an isolated MySQL test database.');
}
$app = require $argv[2];
$app->make(Kernel::class)->bootstrap();
$app->register(SiteBuilderServiceProvider::class);
$app->register(ComponentsServiceProvider::class);
if (! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
    throw new RuntimeException('MySQL 8.4 is required.');
}
config(['site-builder.locales' => ['en' => [], 'de' => []]]);
$barrier = $argv[4];
if (realpath(dirname($barrier)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($barrier), 'site-builder-media-race-')) {
    throw new RuntimeException('Invalid media test barrier.');
}
file_put_contents($barrier.'/ready-'.$argv[5], 'ready');
$deadline = microtime(true) + 20;
while (! is_file($barrier.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Media concurrency barrier timed out.');
    }
    usleep(1000);
}
try {
    app(MediaLibrary::class)->updateAlt($argv[3], ['en' => 'Worker '.$argv[5]], 0);
    echo 'accepted';
} catch (WriteConflict $exception) {
    echo $exception->reason;
}

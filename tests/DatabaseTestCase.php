<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use SiteBuilder\Components\ComponentsServiceProvider;
use SiteBuilder\Core\SiteBuilderServiceProvider;

abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() === 'mysql') {
            $this->assertStringStartsWith('8.4.', DB::selectOne('SELECT VERSION() AS version')->version);
        }
        foreach ($this->migrationPaths() as $path) {
            $this->artisan('migrate', ['--path' => $path, '--realpath' => true, '--force' => true])->assertExitCode(0);
        }
    }

    protected function tearDown(): void
    {
        // Dedicated disposable schemas may include optional core feature stores.
        Schema::dropAllTables();
        parent::tearDown();
    }

    private function migrationPaths(): array
    {
        return array_map(
            fn (string $provider): string => dirname((new ReflectionClass($provider))->getFileName(), 2).'/database/migrations',
            [SiteBuilderServiceProvider::class, ComponentsServiceProvider::class],
        );
    }
}

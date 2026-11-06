<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests;

use LogicException;
use Orchestra\Testbench\TestCase as Orchestra;
use SiteBuilder\Components\ComponentsServiceProvider;
use SiteBuilder\Core\SiteBuilderServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SiteBuilderServiceProvider::class, ComponentsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $driver = getenv('DB_CONNECTION') ?: 'sqlite';
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new LogicException('Component tests support isolated SQLite or MySQL only.');
        }
        $app['config']->set('database.default', $driver);
        if ($driver === 'sqlite') {
            $app['config']->set('database.connections.sqlite.database', ':memory:');
        } else {
            $database = getenv('DB_DATABASE') ?: '';
            if (! str_starts_with($database, 'site_builder_') || ! str_ends_with($database, '_test')) {
                throw new LogicException('MySQL component tests require a dedicated site_builder_*_test database.');
            }
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql', 'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306', 'unix_socket' => getenv('DB_SOCKET') ?: '',
                'database' => $database, 'username' => getenv('DB_USERNAME') ?: 'root', 'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            ]);
        }
        $app['config']->set('site-builder.locales', ['en' => ['label' => 'English'], 'de' => ['label' => 'Deutsch'], 'fr' => ['label' => 'Français']]);
        $app['config']->set('filesystems.disks.public', ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => '/storage', 'visibility' => 'public', 'throw' => true]);
    }
}

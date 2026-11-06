<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\ServiceProvider;
use SiteBuilder\Components\ComponentsServiceProvider;
use SiteBuilder\Components\References\StandardCopyReferenceInspector;
use SiteBuilder\Components\Tests\TestCase;
use SiteBuilder\Core\Components\Reference;
use SiteBuilder\Core\Composition\CopyContext;
use SiteBuilder\Core\Contracts\MediaResolver;
use SiteBuilder\Core\SiteBuilderServiceProvider;

class HostMediaAdapterTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SiteBuilderServiceProvider::class, HostMediaProvider::class, ComponentsServiceProvider::class];
    }

    public function test_custom_host_resolver_wins_even_for_a_path_under_the_owned_prefix(): void
    {
        $this->assertSame('/host-owned/site-builder-media%2Fhost.webp', app(MediaResolver::class)->url('site-builder-media/host.webp'));
        $this->assertNull(app(StandardCopyReferenceInspector::class)->inspect(new GenericUser(['id' => 1]), new Reference('source', null, '/props/image', 'media', 'site-builder-media/host.webp'), new CopyContext(1, 'en')));
    }
}

class HostMediaProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MediaResolver::class, fn () => new class implements MediaResolver
        {
            public function url(string $path): ?string
            {
                return '/host-owned/'.rawurlencode($path);
            }
        });
    }
}

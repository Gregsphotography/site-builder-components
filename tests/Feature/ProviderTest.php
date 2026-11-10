<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Components\Tests\TestCase;

class ProviderTest extends TestCase
{
    public function test_boot_does_not_install_media_schema_and_package_manifest_has_no_editor_dependency(): void
    {
        $this->assertFalse(Schema::hasTable('site_builder_media_assets'));
        $this->assertFalse(app(MediaLibrary::class)->available());
        $manifest = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('MIT', $manifest['license']);
        $this->assertSame('^8.5', $manifest['require']['php']);
        $this->assertSame('0.5.0-rc.1', $manifest['require']['gregsphotography/site-builder-core']);
        foreach (array_keys($manifest['require']) as $dependency) {
            $this->assertStringNotContainsString('filament', $dependency);
            $this->assertStringNotContainsString('livewire', $dependency);
            $this->assertStringNotContainsString('atelier', $dependency);
        }
    }
}

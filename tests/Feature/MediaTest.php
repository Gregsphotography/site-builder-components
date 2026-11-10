<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use SiteBuilder\Components\Media\MediaAsset;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Components\References\StandardCopyReferenceInspector;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Components\Reference;
use SiteBuilder\Core\Composition\CopyContext;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Contracts\MediaResolver;
use SiteBuilder\Core\Exceptions\WriteConflict;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\RenderContext;

class MediaTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_local_upload_retains_metadata_resolves_a_stable_reference_and_reports_missing_files(): void
    {
        $library = app(MediaLibrary::class);
        $asset = $library->store(UploadedFile::fake()->image('landscape.png', 1200, 800), ['en' => 'English landscape', 'de' => 'Deutsche Landschaft'], 'A landscape');
        $this->assertTrue($library->available());
        $this->assertMatchesRegularExpression('~^site-builder-media/[a-f0-9-]{36}\.png$~', $asset->path);
        $this->assertSame('image/png', $asset->mime);
        $this->assertSame(1200, $asset->width);
        $this->assertSame(800, $asset->height);
        $this->assertGreaterThan(0, $asset->size);
        $this->assertSame(0, $asset->fresh()->write_version);
        $this->assertCount(1, $library->entries());
        $this->assertStringContainsString('Deutsche Landschaft', $library->options('de')[$asset->path]);
        Storage::disk('public')->assertExists($asset->path);
        $this->assertStringContainsString('/storage/'.$asset->path, app(MediaResolver::class)->url($asset->path));
        $this->assertSame('/host/path.webp', app(MediaResolver::class)->url('/host/path.webp'));
        Storage::disk('public')->delete($asset->path);
        $this->assertNull(app(MediaResolver::class)->url($asset->path));
    }

    public function test_rendering_checks_the_media_table_once_per_scope_and_keeps_files_and_disks_live(): void
    {
        $paths = [];
        foreach (range(1, 3) as $number) {
            $paths[] = app(MediaLibrary::class)->store(UploadedFile::fake()->image('image'.$number.'.png'))->path;
        }
        $this->app->forgetScopedInstances();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            foreach ($paths as $path) {
                $this->assertNotNull(app(MediaResolver::class)->url($path));
            }
            $this->assertCount(4, DB::getQueryLog());
            Storage::disk('public')->delete($paths[0]);
            $this->assertNull(app(MediaResolver::class)->url($paths[0]));
            config(['filesystems.disks.public.visibility' => 'private']);
            $this->assertFalse(app(MediaLibrary::class)->available());
            $this->assertNull(app(MediaResolver::class)->url($paths[1]));
            config(['filesystems.disks.public.visibility' => 'public']);
            $this->assertNotNull(app(MediaResolver::class)->url($paths[1]));
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_availability_refreshes_after_a_scope_reset_and_after_migrations(): void
    {
        $library = app(MediaLibrary::class);
        $this->assertTrue($library->available());
        Schema::drop('site_builder_media_assets');
        $this->app->forgetScopedInstances();
        $fresh = app(MediaLibrary::class);
        $this->assertNotSame($library, $fresh);
        $this->assertFalse($fresh->available());
        DB::table('migrations')->where('migration', '0001_01_01_000200_create_site_builder_media_assets_table')->delete();
        $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
        $this->assertTrue($fresh->available());
    }

    public function test_availability_is_keyed_by_database_connection(): void
    {
        $library = app(MediaLibrary::class);
        $this->assertTrue($library->available());
        $original = DB::getDefaultConnection();
        config(['database.connections.media_empty' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        try {
            DB::setDefaultConnection('media_empty');
            $this->assertFalse($library->available());
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('media_empty');
        }
        $this->assertTrue($library->available());
    }

    public function test_alt_edits_merge_only_given_locale_and_reject_stale_versions_without_lost_updates(): void
    {
        $library = app(MediaLibrary::class);
        $asset = $library->store(UploadedFile::fake()->image('image.png'), ['en' => 'Original', 'de' => 'Unverändert']);
        $updated = $library->updateAlt($asset, ['en' => 'Changed'], 0);
        $this->assertEquals(['en' => 'Changed', 'de' => 'Unverändert'], $updated->alt);
        $this->assertSame(1, $updated->write_version);
        try {
            $library->updateAlt($asset->id, ['de' => 'Stale overwrite'], 0);
            $this->fail('A stale shared asset edit must fail.');
        } catch (WriteConflict $exception) {
            $this->assertSame('stale_media_version', $exception->reason);
            $this->assertSame(1, $exception->currentVersion);
        }
        $this->assertEquals(['en' => 'Changed', 'de' => 'Unverändert'], $asset->fresh()->alt);
        $updated = $library->updateAlt($asset->id, ['de' => 'Aktualisiert'], 1);
        $this->assertEquals(['en' => 'Changed', 'de' => 'Aktualisiert'], $updated->alt);
        $this->assertSame(2, $updated->write_version);
    }

    public function test_private_disks_are_never_exposed_by_the_default_public_adapter(): void
    {
        $library = app(MediaLibrary::class);
        $asset = $library->store(UploadedFile::fake()->image('image.png'));
        $reference = new Reference('source', null, '/props/image', 'media', $asset->path);
        $inspector = app(StandardCopyReferenceInspector::class);
        $actor = new GenericUser(['id' => 1]);
        $context = new CopyContext(1, 'en');
        $this->assertSame('valid', $inspector->inspect($actor, $reference, $context)?->status);
        config(['filesystems.disks.public.visibility' => 'private']);
        $this->assertFalse($library->available());
        $this->assertNull(app(MediaResolver::class)->url($asset->path));
        $this->assertSame([], $library->options('en'));
        $this->assertSame('missing', $inspector->inspect($actor, $reference, $context)?->status);
        $this->expectException(LogicException::class);
        $library->store(UploadedFile::fake()->image('forbidden.png'));
    }

    public function test_invalid_upload_is_rejected_before_file_or_database_writes(): void
    {
        try {
            app(MediaLibrary::class)->store(UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml'));
            $this->fail('Active SVG uploads require a host-owned sanitizer/adapter and must be refused by default.');
        } catch (ValidationException) {
            $this->assertSame(0, MediaAsset::query()->count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
        $this->expectException(LogicException::class);
        app(MediaLibrary::class)->store(UploadedFile::fake()->image('image.png'), ['unknown' => 'Not a configured locale']);
    }

    public function test_image_alt_decorative_caption_dimensions_and_responsive_sources_use_sanitized_properties(): void
    {
        $asset = app(MediaLibrary::class)->store(UploadedFile::fake()->image('image.png', 1200, 800), ['en' => 'Asset library alt']);
        $node = new Node('image', 'standard.image', props: new JsonObject(['image' => $asset->path, 'width' => 1200, 'height' => 800, 'sources' => [(object) ['image' => '/images/small.webp', 'width' => 600], (object) ['image' => '/images/large.webp', 'width' => 1200], (object) ['image' => 'javascript:bad()', 'width' => 1800]]]), translations: new JsonObject(['en' => (object) ['alt' => 'English alt'], 'de' => (object) ['alt' => 'Deutscher Alternativtext', 'caption' => 'Deutsche Bildlegende']]));
        $renderer = app(DocumentRenderer::class);
        $html = (string) $renderer->render(new Document([$node]), new RenderContext('de'))->html;
        $this->assertStringContainsString('alt="Deutscher Alternativtext"', $html);
        $this->assertStringContainsString('<figcaption>Deutsche Bildlegende</figcaption>', $html);
        $this->assertStringContainsString('width="1200" height="800"', $html);
        $this->assertStringContainsString('srcset="/images/small.webp 600w, /images/large.webp 1200w"', $html);
        $this->assertStringContainsString('sizes="(max-width: 48rem) 100vw, 72rem"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $decorative = new Node('decorative', 'standard.image', props: new JsonObject(['image' => $asset->path, 'decorative' => true]), translations: $node->translations);
        $html = (string) $renderer->render(new Document([$decorative]), new RenderContext('de'))->html;
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('role="presentation"', $html);
        $this->assertStringNotContainsString('Asset library alt', $html);
    }
}

<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SiteBuilder\Components\Definitions\LeafDefinition;
use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Components\References\StandardCopyReferenceInspector;
use SiteBuilder\Components\Tests\TestCase;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Components\Reference;
use SiteBuilder\Core\Components\ReferenceScanner;
use SiteBuilder\Core\Composition\CopyContext;
use SiteBuilder\Core\Composition\DocumentCopies;
use SiteBuilder\Core\Composition\StartingPointRegistry;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\RenderContext;

class ExamplesTest extends TestCase
{
    private string $examplePublicPath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->examplePublicPath = sys_get_temp_dir().'/site-builder-example-public-'.Str::uuid();
        $app->usePublicPath($this->examplePublicPath);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->examplePublicPath);
        parent::tearDown();
    }

    public function test_code_owned_catalog_snapshots_are_deterministic_valid_and_use_actual_renderers_for_every_variant(): void
    {
        $registry = app(StartingPointRegistry::class);
        $this->assertCount(51, $registry->all());
        foreach ($registry->all() as $descriptor) {
            $this->assertNotSame('', $descriptor->label);
            $this->assertNotSame('', $descriptor->description);
            $this->assertNotSame('', $descriptor->category);
            $this->assertNotEmpty($descriptor->keywords);
            $variants = array_keys($descriptor->variants) ?: [null];
            foreach ($variants as $variant) {
                $source = $registry->snapshot($descriptor->id, $descriptor->version, 'de', $variant);
                $again = $registry->snapshot($descriptor->id, (string) $descriptor->version, 'de', $variant);
                $this->assertSame($source->digest, $again->digest);
                $this->assertSame(app(CanonicalJson::class)->digest($source->document), $source->digest);
                $this->assertSame($descriptor->sourceKind, $source->kind);
                $this->assertSame([], app(DocumentValidator::class)->validate($source->document, true, ['de']));
                $copy = app(DocumentCopies::class)->copy($source->document);
                foreach ($copy->nodes as $index => $node) {
                    $this->assertNotSame($source->document->nodes[$index]->id, $node->id);
                    $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $node->id);
                    $this->assertSame(json_encode($source->document->nodes[$index]->props), json_encode($node->props));
                    $this->assertSame(json_encode($source->document->nodes[$index]->translations), json_encode($node->translations));
                }
                $result = app(DocumentRenderer::class)->render($source->document, new RenderContext('de', 'preview'));
                $this->assertSame([], $result->diagnostics);
                $this->assertStringContainsString('sb-block', (string) $result->html);
                $this->assertStringNotContainsString('src="https://', (string) $result->html);
                $this->assertStringNotContainsString('<form', (string) $result->html);
                $this->assertSame([], $result->structuredData);
            }
        }
    }

    public function test_examples_publish_only_allowlisted_local_assets_and_reference_inspection_never_fetches_remote_media(): void
    {
        $examples = app(StandardExamples::class);
        $inspector = app(StandardCopyReferenceInspector::class);
        $actor = new GenericUser(['id' => 'example-editor']);
        $context = new CopyContext(1, 'en');
        $assets = [];
        foreach ($examples->entries() as $type => $entry) {
            foreach (array_keys($entry['variants']) as $variant) {
                foreach (app(ReferenceScanner::class)->scan($examples->document($type, 'en', $variant)) as $reference) {
                    if ($reference->kind === 'media') {
                        $this->assertStringStartsWith('/vendor/site-builder-components/examples/', $reference->value);
                        $this->assertSame('valid', $inspector->inspect($actor, $reference, $context)?->status);
                        $assets[$reference->value] = true;
                    }
                }
            }
        }
        $this->assertCount(8, $assets);
        foreach (array_keys($assets) as $asset) {
            $file = __DIR__.'/../../resources/dist/examples/'.basename($asset);
            $svg = file_get_contents($file);
            $this->assertStringContainsString('<svg ', $svg);
            $this->assertStringNotContainsString('<script', $svg);
            $this->assertStringNotContainsString('href=', $svg);
            $this->assertStringNotContainsString('url(', $svg);
            $this->assertStringNotContainsString('onload=', $svg);
        }
        $unknown = new Reference('source', null, '/props/image', 'media', '/vendor/site-builder-components/examples/missing.svg');
        $this->assertSame('missing', $inspector->inspect($actor, $unknown, $context)?->status);
        $unsafe = new Reference('source', null, '/props/image', 'media', '//vendor/site-builder-components/examples/landscape.svg');
        $this->assertSame('missing', $inspector->inspect($actor, $unsafe, $context)?->status);
        $remote = new Reference('source', null, '/props/image', 'media', 'https://127.0.0.1:9/no-network-probe.webp');
        $this->assertNull($inspector->inspect($actor, $remote, $context));
        $host = new Reference('source', null, '/props/image', 'media', '/host/media.webp');
        $this->assertNull($inspector->inspect($actor, $host, $context));
    }

    public function test_unrecognized_host_definitions_do_not_receive_fabricated_standard_previews(): void
    {
        app(ComponentRegistry::class)->register(new class extends LeafDefinition
        {
            public function type(): string
            {
                return 'host.special';
            }

            public function defaults(): PropertyValues
            {
                return new PropertyValues;
            }

            public function properties(): array
            {
                return [];
            }

            public function view(): string
            {
                return 'host::special';
            }
        });
        $this->assertArrayNotHasKey('host.special', app(StandardExamples::class)->entries());
        $this->assertNull(app(StartingPointRegistry::class)->find('component:host.special'));
        $this->expectException(InvalidArgumentException::class);
        app(StandardExamples::class)->document('host.special', 'en');
    }

    public function test_example_reference_requires_matching_published_assets_and_reports_missing_or_stale_public_files(): void
    {
        $inspector = app(StandardCopyReferenceInspector::class);
        $actor = new GenericUser(['id' => 1]);
        $context = new CopyContext(1, 'en');
        $reference = new Reference('source', null, '/props/image', 'media', StandardExamples::asset('landscape'));
        $published = public_path(ltrim($reference->value, '/'));
        $this->assertSame('valid', $inspector->inspect($actor, $reference, $context)?->status);
        File::delete($published);
        $missing = $inspector->inspect($actor, $reference, $context);
        $this->assertSame('missing', $missing?->status);
        $this->assertStringContainsString('site-builder:components-install', $missing->message);
        File::put($published, '<svg xmlns="http://www.w3.org/2000/svg"><title>Stale local asset</title></svg>');
        $this->assertSame('missing', $inspector->inspect($actor, $reference, $context)?->status);
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
        $this->assertSame('valid', $inspector->inspect($actor, $reference, $context)?->status);
    }

    public function test_example_rejects_a_tampered_variant_and_preserves_source_identity(): void
    {
        $examples = app(StandardExamples::class);
        $before = app(CanonicalJson::class)->digest($examples->document('standard.hero', 'en', 'split'));
        try {
            $examples->document('standard.hero', 'en', '../../views/anything');
            $this->fail('Example variants must be declared choices.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, app(CanonicalJson::class)->digest($examples->document('standard.hero', 'en', 'split')));
        }
        $this->expectException(InvalidArgumentException::class);
        $examples->document('standard.hero', 'unknown', 'split');
    }
}

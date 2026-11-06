<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Components\ReferenceScanner;
use SiteBuilder\Core\Composition\DocumentCopies;
use SiteBuilder\Core\Composition\PublishedPartReference;
use SiteBuilder\Core\Composition\StartingPointRegistry;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Exceptions\InvalidDocument;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\LayoutRegistry;
use SiteBuilder\Core\Rendering\PageResponse;
use SiteBuilder\Core\Rendering\PublicAssets;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\SiteBuilderServiceProvider;
use SiteBuilder\Core\SiteParts\SitePartCommands;
use SiteBuilder\Core\SiteParts\SitePartLocks;
use SiteBuilder\Core\SiteParts\SitePartPayload;

class CollectionsTest extends DatabaseTestCase
{
    public function test_collection_bounds_apply_to_hidden_unconfigured_locales_and_single_testimonial_hidden_items(): void
    {
        foreach ([['standard.gallery', array_fill(0, 49, (object) [])], ['standard.gallery', [(object) ['sources' => array_fill(0, 9, (object) ['image' => '/a.jpg', 'width' => 10])]]], ['standard.testimonials', array_fill(0, 13, (object) [])]] as [$type, $items]) {
            $doc = new Document([new Node('collection', $type, translations: new JsonObject(['zz' => (object) ['items' => $items]]), visibility: 'hidden')]);
            try {
                app(DocumentValidator::class)->validate($doc);
                $this->fail('Oversized hidden collection accepted.');
            } catch (InvalidDocument) {
                $this->addToAssertionCount(1);
            }
        }
        $doc = new Document([new Node('quote', 'standard.testimonials', props: new JsonObject(['variant' => 'single']), translations: new JsonObject(['en' => (object) ['items' => [(object) ['quote' => 'A quote', 'name' => 'Avery'], (object) ['quote' => '', 'name' => 'Hidden item']]]]))]);
        $this->expectException(InvalidDocument::class);
        app(DocumentValidator::class)->validate($doc, true);
    }

    public function test_publication_checks_accessible_images_and_separate_link_labels_with_locale_fallback(): void
    {
        $valid = $this->gallery([(object) ['image' => '/a.jpg', 'alt' => '', 'decorative' => true, 'caption' => 'A caption']]);
        $this->assertSame([], app(DocumentValidator::class)->validate($valid, true, ['en', 'de']));
        foreach ([(object) ['image' => null, 'decorative' => true], (object) ['image' => '/a.jpg', 'alt' => ''], (object) ['image' => '/a.jpg', 'alt' => 'A', 'page' => 1, 'link_label' => '']] as $item) {
            try {
                app(DocumentValidator::class)->validate($this->gallery([$item]), true);
                $this->fail('Incomplete image accepted.');
            } catch (InvalidDocument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_public_collections_escape_text_keep_destinations_separate_and_never_expose_missing_owned_media(): void
    {
        $page = Page::factory()->create(['status' => 'draft']);
        $document = $this->gallery([(object) ['image' => '/a.jpg', 'full_image' => '/full.jpg', 'alt' => 'A <photo>', 'caption' => '<script>caption</script>', 'url' => '/stale', 'page' => $page->id, 'link_label' => 'Read more'], (object) ['image' => 'site-builder-media/private-secret.jpg', 'alt' => 'Unavailable'], (object) ['image' => '/decorative.jpg', 'decorative' => true, 'alt' => 'Ignored decorative alt', 'caption' => '']]);
        $assets = app(PublicAssets::class);
        $assets->scope(function () use ($document, $assets): void {
            $html = (string) app(DocumentRenderer::class)->render($document, new RenderContext('en'))->html;
            $this->assertStringContainsString('href="/full.jpg"', $html);
            $this->assertStringContainsString('&lt;script&gt;caption&lt;/script&gt;', $html);
            $this->assertStringContainsString('Image unavailable', $html);
            $this->assertStringNotContainsString('private-secret', $html);
            $this->assertStringNotContainsString('/stale', $html);
            $this->assertSame(1, substr_count($html, 'data-sb-gallery-image '));
            $this->assertStringContainsString('gallery.js?v=', (string) $assets->html());
        });
        $quote = new Document([new Node('quotes', 'standard.testimonials', props: new JsonObject(['variant' => 'single']), translations: new JsonObject(['en' => (object) ['items' => [(object) ['quote' => '<b>Plain quotation</b>', 'name' => 'Avery', 'portrait' => 'site-builder-media/private-secret.jpg'], (object) ['quote' => 'Hidden second quote', 'name' => 'Robin', 'page' => 77]]]]))]);
        $html = (string) app(DocumentRenderer::class)->render($quote, new RenderContext('en'))->html;
        $this->assertStringContainsString('&lt;b&gt;Plain quotation&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('Hidden second quote', $html);
        $this->assertStringNotContainsString('private-secret', $html);
        $this->assertSame(77, app(ReferenceScanner::class)->scan($quote)[1]->value);
        $this->assertSame([], app(DocumentRenderer::class)->render($quote, new RenderContext('en'))->structuredData);
    }

    public function test_response_assets_are_once_after_all_regions_and_reset_for_the_next_response_in_the_same_worker(): void
    {
        $node = $this->gallery([(object) ['image' => '/a.jpg', 'alt' => 'Example']])->nodes[0];
        app(LayoutRegistry::class)->register('test.regions', 'site-builder::layouts.site', ['header', 'body', 'footer']);
        $page = Page::factory()->create(['layout' => 'components.business', 'status' => 'published', 'published_content' => new Document([$node, new Node('other', $node->type, props: $node->props, translations: $node->translations)])]);
        $html = app(PageResponse::class)->render($page, 'en')->getContent();
        $this->assertSame(1, substr_count($html, '<script type="module" src="/vendor/site-builder-components/gallery.js?'));
        preg_match_all('/<dialog id="([^"]+)"/', $html, $ids);
        $this->assertCount(2, array_unique($ids[1]));
        $page->published_content = new Document;
        $this->assertStringNotContainsString('gallery.js', app(PageResponse::class)->render($page, 'en')->getContent());
        $static = new Node($node->id, $node->type, props: new JsonObject(['columns' => 'two', 'lightbox' => false]), translations: $node->translations);
        $page->published_content = new Document([$static]);
        $staticHtml = app(PageResponse::class)->render($page, 'en')->getContent();
        $this->assertStringNotContainsString('gallery.js', $staticHtml);
        $this->assertStringNotContainsString('<dialog', $staticHtml);
        $this->assertStringContainsString('href="/a.jpg"', $staticHtml);
        $page->draft_content = new Document([$node]);
        $this->assertStringNotContainsString('gallery.js', app(PageResponse::class)->render($page, 'en', true)->getContent());
    }

    public function test_repeated_immutable_global_gallery_mounts_keep_distinct_dom_ids_and_one_asset(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
        $this->artisan('migrate', ['--path' => realpath(dirname((new \ReflectionClass(SiteBuilderServiceProvider::class))->getFileName(), 2).'/database/site-parts-migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
        $actor = new GenericUser(['id' => 'collection-editor']);
        Gate::before(fn () => true);
        $part = app(SitePartCommands::class)->create($actor, 'section', ['name' => 'Repeated gallery'], SitePartPayload::document('section', $this->gallery([(object) ['image' => '/a.jpg', 'alt' => 'Example']])))->part;
        $lease = app(SitePartLocks::class)->acquire($actor, $part->id);
        $part = app(SitePartCommands::class)->publish($actor, $part->id, $lease->token, 0, app(CanonicalJson::class)->digest($part->draft_payload))->part;
        $revision = $part->revisions()->sole();
        $reference = new PublishedPartReference($part->id, $revision->id, $revision->digest);
        $regions = [];
        foreach (['header', 'footer'] as $kind) {
            $region = app(SitePartCommands::class)->create($actor, $kind, ['name' => 'Gallery '.$kind], SitePartPayload::document($kind, $this->gallery([(object) ['image' => '/a.jpg', 'alt' => 'Region image']])))->part;
            $regionLease = app(SitePartLocks::class)->acquire($actor, $region->id);
            $region = app(SitePartCommands::class)->publish($actor, $region->id, $regionLease->token, 0, app(CanonicalJson::class)->digest($region->draft_payload))->part;
            $regionRevision = $region->revisions()->sole();
            $regions[$kind] = (new PublishedPartReference($region->id, $regionRevision->id, $regionRevision->digest))->jsonSerialize();
        }
        $mount = fn ($id) => new Node($id, 'core.global-reference', props: new JsonObject(['revision' => $reference->jsonSerialize()]));
        $document = new Document([$mount('first'), $mount('second')], new JsonObject(['site-builder.render' => (object) ['version' => 1, 'composition' => (object) ['version' => 1, ...$regions]]]));
        $page = Page::factory()->create(['layout' => 'components.business', 'status' => 'published', 'published_content' => $document]);
        $response = app(PageResponse::class)->render($page, 'en');
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $html = $response->getContent();
        preg_match_all('/<dialog id="([^"]+)"/', $html, $ids);
        $this->assertCount(4, array_unique($ids[1]));
        $this->assertSame(1, substr_count($html, 'src="/vendor/site-builder-components/gallery.js?'));
    }

    public function test_new_pattern_variants_use_fresh_recursive_copy_ids_and_render_in_every_preset(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
        foreach (['team' => ['two', 'three'], 'process' => ['three'], 'pricing' => ['two', 'three']] as $name => $variants) {
            foreach ($variants as $variant) {
                $source = app(StartingPointRegistry::class)->snapshot('pattern:standard.'.$name, 1, 'de', $variant);
                $copy = app(DocumentCopies::class)->copy($source->document);
                $this->assertSame([], app(DocumentValidator::class)->validate($copy, true, ['de']));
                $this->assertSame($source->digest, app(StartingPointRegistry::class)->snapshot('pattern:standard.'.$name, 1, 'de', $variant)->digest);
                $collect = function (array $nodes) use (&$collect): array {
                    return array_merge(...array_map(fn ($node) => [$node->id, ...$collect($node->children)], $nodes));
                };
                $this->assertSame([], array_intersect($collect($source->document->nodes), $collect($copy->nodes)));
                foreach (['minimal', 'business', 'editorial', 'host'] as $preset) {
                    $page = Page::factory()->create(['layout' => 'components.'.$preset, 'published_content' => $copy]);
                    $response = app(PageResponse::class)->render($page, 'de');
                    $this->assertSame(200, $response->getStatusCode());
                    $this->assertStringContainsString('data-site-builder-theme="'.$preset.'"', $response->getContent());
                    $this->assertStringNotContainsString('gallery.js', $response->getContent());
                }
            }
        }
    }

    private function gallery(array $items): Document
    {
        return new Document([new Node('gallery', 'standard.gallery', props: new JsonObject(['columns' => 'three', 'lightbox' => true]), translations: new JsonObject(['en' => (object) ['label' => 'Example images', 'heading' => '', 'items' => $items], 'de' => (object) ['items' => null]]), extensions: new JsonObject(['opaque' => (object) ['object' => (object) [], 'list' => [], 'nullable' => null]]))]);
    }
}

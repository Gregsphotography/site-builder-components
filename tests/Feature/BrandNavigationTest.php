<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Components\ReferenceScanner;
use SiteBuilder\Core\Composition\DocumentCopies;
use SiteBuilder\Core\Composition\StartingPointRegistry;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Exceptions\InvalidDocument;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\RenderContext;

class BrandNavigationTest extends DatabaseTestCase
{
    public function test_nested_page_references_scan_all_languages_and_empty_locale_does_not_merge(): void
    {
        $doc = $this->navigation([(object) ['label' => 'Group', 'children' => [(object) ['label' => 'Second', 'children' => [(object) ['label' => 'Third', 'page' => 7]]]]]]);
        $node = $doc->nodes[0];
        $doc = new Document([new Node($node->id, $node->type, props: $node->props, translations: new JsonObject(['en' => $node->translations->get('en'), 'de' => (object) ['label' => 'Menü', 'items' => []], 'fr' => (object) ['items' => [(object) ['label' => 'Autre', 'page' => 8]]]]))]);
        $references = app(ReferenceScanner::class)->scan($doc);
        $this->assertSame(['/translations/en/items/0/children/0/children/0/page', '/translations/fr/items/0/page'], array_map(fn ($ref) => $ref->path, $references));
        $de = (string) app(DocumentRenderer::class)->render($doc, new RenderContext('de'))->html;
        $this->assertStringNotContainsString('<nav', $de);
        $this->assertStringNotContainsString('Third', $de);
        $this->assertSame([], app(DocumentValidator::class)->validate($doc));
    }

    public function test_navigation_aggregate_limit_and_fourth_level_fail_even_in_hidden_unconfigured_values(): void
    {
        $items = array_fill(0, 50, (object) ['label' => 'Parent', 'url' => '/parent', 'children' => [(object) ['label' => 'Child', 'url' => '/child']]]);
        $valid = $this->navigation($items);
        $this->assertSame([], app(DocumentValidator::class)->validate($valid, true));
        $items[] = (object) ['label' => 'One too many', 'url' => '/extra'];
        $bad = $this->navigation($items);
        $node = $bad->nodes[0];
        $bad = new Document([new Node('nav', $node->type, translations: new JsonObject(['zz' => $node->translations->get('en')]), visibility: 'hidden')]);
        try {
            app(DocumentValidator::class)->validate($bad);
            $this->fail('Aggregate limit accepted.');
        } catch (InvalidDocument $exception) {
            $this->assertStringContainsString('100 navigation', $exception->getMessage());
        }
        $this->expectException(InvalidDocument::class);
        app(DocumentValidator::class)->validate($this->navigation([(object) ['children' => [(object) ['children' => [(object) ['children' => [(object) ['url' => '/four']]]]]]]]));
    }

    public function test_publication_requires_accessible_names_destinations_and_a_logo_for_logo_mode(): void
    {
        foreach ([$this->navigation([(object) ['label' => 'Missing destination']]), new Document([new Node('brand', 'standard.brand', props: new JsonObject(['variant' => 'logo']), translations: new JsonObject(['en' => (object) ['name' => 'Example', 'logo' => null]]))])] as $document) {
            app(DocumentValidator::class)->validate($document);
            try {
                app(DocumentValidator::class)->validate($document, true);
                $this->fail('Incomplete publication accepted.');
            } catch (InvalidDocument $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_public_links_use_typed_precedence_and_current_page_without_unsafe_or_stale_fallbacks(): void
    {
        $page = Page::factory()->create(['status' => 'published', 'published_content' => new Document]);
        $page->slugs()->create(['locale' => 'en', 'slug' => 'current']);
        $doc = $this->navigation([(object) ['label' => '<script>Current</script>', 'page' => $page->id, 'url' => '/stale', 'children' => [(object) ['label' => 'Child', 'url' => '/child']]], (object) ['label' => 'Unsafe', 'url' => 'javascript:alert(1)']]);
        $en = (string) app(DocumentRenderer::class)->render($doc, new RenderContext('en', pageId: $page->id))->html;
        $this->assertStringContainsString('aria-current="page"', $en);
        $this->assertStringContainsString('&lt;script&gt;Current&lt;/script&gt;', $en);
        $this->assertStringContainsString('<details>', $en);
        $this->assertStringNotContainsString('<summary><a', $en);
        $this->assertStringNotContainsString('/stale', $en);
        $this->assertStringNotContainsString('javascript:', $en);
        $page->forceFill(['status' => 'draft'])->save();
        $missing = (string) app(DocumentRenderer::class)->render($doc, new RenderContext('en'))->html;
        $this->assertStringNotContainsString('/current', $missing);
        $this->assertStringNotContainsString('/stale', $missing);
    }

    public function test_brand_missing_owned_logo_falls_back_to_name_and_header_patterns_copy_fresh_recursive_ids(): void
    {
        $brand = new Node('brand', 'standard.brand', props: new JsonObject(['variant' => 'logo']), translations: new JsonObject(['en' => (object) ['name' => 'Example', 'logo' => 'site-builder-media/missing.webp', 'url' => '/']]));
        $html = (string) app(DocumentRenderer::class)->render(new Document([$brand]), new RenderContext('en'))->html;
        $this->assertStringContainsString('Example', $html);
        $this->assertStringNotContainsString('<img', $html);
        foreach (['header', 'footer'] as $kind) {
            $source = app(StartingPointRegistry::class)->snapshot('pattern:standard.'.$kind, 1, 'de');
            $copy = app(DocumentCopies::class)->copy($source->document);
            $this->assertNotSame($source->document->nodes[0]->id, $copy->nodes[0]->id);
            $this->assertNotSame($source->document->nodes[0]->children[0]->id, $copy->nodes[0]->children[0]->id);
            $this->assertSame([], app(DocumentValidator::class)->validate($copy, true, ['de']));
            $this->assertSame($source->digest, app(StartingPointRegistry::class)->snapshot('pattern:standard.'.$kind, 1, 'de')->digest);
        }
        $this->assertTrue(app(ComponentRegistry::class)->resolve('standard.column')->allowedChildren()->allows('standard.navigation'));
    }

    private function navigation(array $items): Document
    {
        return new Document([new Node('nav', 'standard.navigation', props: new JsonObject(['orientation' => 'horizontal']), translations: new JsonObject(['en' => (object) ['label' => 'Main menu', 'items' => $items]]))]);
    }
}

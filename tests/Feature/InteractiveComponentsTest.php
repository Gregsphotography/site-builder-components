<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use SiteBuilder\Components\Definitions\Form;
use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Components\Forms\FormRuntime;
use SiteBuilder\Components\Forms\NotificationProfile;
use SiteBuilder\Components\Rendering\ContentLists;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Composition\CompositionData;
use SiteBuilder\Core\Composition\CompositionResolver;
use SiteBuilder\Core\Composition\DocumentCopies;
use SiteBuilder\Core\Composition\OverlaySettings;
use SiteBuilder\Core\Composition\PublishedPartReference;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Discovery\ContentIndex;
use SiteBuilder\Core\Discovery\ContentQuery;
use SiteBuilder\Core\Discovery\DiscoveryMetadata;
use SiteBuilder\Core\Exceptions\InvalidDocument;
use SiteBuilder\Core\Exchange\Exchange;
use SiteBuilder\Core\Exchange\ExchangeSchema;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\ContentOccurrences;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\PageResponse;
use SiteBuilder\Core\Rendering\PublicQuery;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\SiteParts\NativeGraphMapper;
use SiteBuilder\Core\SiteParts\SitePart;
use SiteBuilder\Core\SiteParts\SitePartCommands;
use SiteBuilder\Core\SiteParts\SitePartLocks;
use SiteBuilder\Core\SiteParts\SitePartPayload;
use SiteBuilder\Core\SiteParts\SitePartRevision;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InteractiveComponentsTest extends DatabaseTestCase
{
    private const INSTALLATION = '72b0a6b2-87da-4cf5-881b-b541ce96ed41';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('site-builder-forms.enabled', true);
        $app['config']->set('site-builder-forms.installation_id', self::INSTALLATION);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('i', 32)), 'site-builder.site_parts.preview_layout' => 'components.business']);
        foreach (['discovery-migrations', 'site-parts-migrations'] as $path) {
            $this->artisan('migrate', ['--path' => realpath(dirname((new \ReflectionClass(\SiteBuilder\Core\SiteBuilderServiceProvider::class))->getFileName(), 2).'/database/'.$path), '--realpath' => true, '--force' => true])->assertExitCode(0);
        }
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
        Gate::before(fn () => true);
    }

    public function test_media_carousel_and_timeline_examples_publish_copy_and_render_in_all_presets_and_previews(): void
    {
        $examples = app(StandardExamples::class);
        foreach (['standard.image-comparison', 'standard.image-hotspots', 'standard.carousel', 'standard.timeline'] as $type) {
            foreach (array_keys($examples->entries()[$type]['variants']) ?: [null] as $variant) {
                $document = $examples->document($type, 'de', $variant);
                $this->assertSame([], app(DocumentValidator::class)->validate($document, true, ['de']));
                $copy = app(DocumentCopies::class)->copy($document);
                $this->assertNotSame($document->nodes[0]->id, $copy->nodes[0]->id);
                foreach (['minimal', 'business', 'editorial', 'host'] as $preset) {
                    $page = $this->page($copy, 'components.'.$preset);
                    $response = app(PageResponse::class)->render($page, 'de');
                    $this->assertSame(200, $response->status(), $response->getContent());
                    $this->assertStringContainsString('data-site-builder-node=', $response->getContent());
                    $preview = app(PageResponse::class)->render($page, 'de', true);
                    $this->assertStringNotContainsString('src="/vendor/site-builder-components/discovery.js', $preview->getContent());
                }
            }
        }
    }

    public function test_listing_instances_keep_their_own_filters_and_search_results_are_locale_aware_and_private(): void
    {
        $alpha = $this->page(new Document([new Node('alpha', 'standard.text', translations: new JsonObject(['en' => ['body' => '<p>Unique alpine story</p>']]))]));
        $alpha->title = 'Alpine entry';
        $alpha->save();
        $beta = $this->page(new Document([new Node('beta', 'standard.text', translations: new JsonObject(['en' => ['body' => '<p>Unique valley story</p>']]))]));
        $beta->title = 'Valley entry';
        $beta->save();
        $definition = app(ComponentRegistry::class)->resolve('standard.content-listing');
        $defaults = $definition->defaults();
        $first = new Node('first', $definition->type(), props: $defaults->props, translations: $defaults->translations);
        $second = new Node('second', $definition->type(), props: $defaults->props, translations: $defaults->translations);
        $prefix = app(ContentLists::class)->key('first');
        $page = $this->page(new Document([$first, $second]));
        $response = app(PageResponse::class)->render($page, 'en', query: new PublicQuery([$prefix.'q' => 'alpine']));
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $html = $response->getContent();
        $firstHtml = explode('data-site-builder-node="second"', $html)[0];
        $this->assertStringContainsString('Alpine entry', $firstHtml);
        $this->assertStringNotContainsString('Valley entry', $firstHtml);
        $this->assertStringContainsString('Valley entry', $html);
        $results = app(ComponentRegistry::class)->resolve('standard.search-results')->defaults();
        $page->published_content = new Document([new Node('results', 'standard.search-results', props: $results->props, translations: $results->translations)]);
        $page->save();
        $html = app(PageResponse::class)->render($page, 'en', query: new PublicQuery(['q' => 'alpine']))->getContent();
        $this->assertStringContainsString('Alpine entry', $html);
        $this->assertStringNotContainsString('Valley entry', $html);
    }

    public function test_new_content_widgets_can_be_nested_in_standard_layouts_and_disclosures(): void
    {
        $children = [];
        foreach (['standard.carousel', 'standard.timeline', 'standard.image-comparison', 'standard.image-hotspots'] as $type) {
            $children = [...$children, ...app(StandardExamples::class)->document($type, 'en')->nodes];
        }
        foreach (['standard.section', 'standard.container', 'standard.column', 'standard.tab-panel', 'standard.accordion-item'] as $type) {
            $definition = app(ComponentRegistry::class)->resolve($type);
            $defaults = $definition->defaults();
            $node = new Node('wrapper', $type, props: $defaults->props, translations: $defaults->translations, children: $children);
            if (in_array($type, ['standard.column', 'standard.tab-panel', 'standard.accordion-item'], true)) {
                $parent = ['standard.column' => 'standard.columns', 'standard.tab-panel' => 'standard.tabs', 'standard.accordion-item' => 'standard.accordion'][$type];
                $values = app(ComponentRegistry::class)->resolve($parent)->defaults();
                $node = new Node('parent', $parent, props: $values->props, translations: $values->translations, children: [$node]);
            }
            $document = new Document([$node]);
            $this->assertSame([], app(DocumentValidator::class)->validate($document, true), $type);
            $html = app(DocumentRenderer::class)->render($document, new RenderContext('en', 'preview'))->html->toHtml();
            $this->assertStringContainsString('sb-comparison', $html);
            $this->assertStringContainsString('sb-timeline', $html);
        }
    }

    public function test_native_exchange_recognizes_overlay_graphs_and_document_level_discovery_media(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->markTestSkipped('Native exchange retains its documented SQLite/MySQL support boundary.');
        }
        config(['site-builder.site_parts.exchange' => true]);
        app()->forgetInstance(ExchangeSchema::class);
        $reference = $this->part('overlay', OverlaySettings::with(new Document, 'en', 'Portable details'));
        $document = DiscoveryMetadata::with(CompositionData::promote(new Document([$this->button('trigger', $reference)])), 'en', ['image' => '/thumbnail.jpg', 'tags' => ['Portable']]);
        $this->page($document);
        $snapshot = app(Exchange::class)->export(DB::connection(), 'interactive-graph');
        $this->assertSame(3, json_decode($snapshot->encode())->version);
        $description = app(NativeGraphMapper::class)->describe($snapshot);
        $this->assertContains('overlay', array_column($snapshot->tables()['site_parts'], 'kind'));
        $this->assertNotEmpty(array_filter($description['occurrences'], fn ($entry) => $entry['kind'] === 'revision'));
        $this->assertNotEmpty(array_filter($description['occurrences'], fn ($entry) => $entry['kind'] === 'media' && $entry['nodeId'] === '@document'));
    }

    public function test_dynamic_carousels_and_timelines_share_sources_and_do_not_invent_dates(): void
    {
        $page = $this->page(new Document);
        $page->title = 'A source record';
        $page->published_at = null;
        $page->save();
        $nodes = [];
        foreach (['carousel', 'timeline'] as $type) {
            $defaults = app(ComponentRegistry::class)->resolve('standard.'.$type)->defaults();
            $nodes[] = new Node($type, 'standard.'.$type, props: new JsonObject([...get_object_vars($defaults->props->toObject()), 'content_mode' => 'dynamic']), translations: $defaults->translations);
        }
        $document = new Document($nodes);
        $this->assertSame([], app(DocumentValidator::class)->validate($document, true));
        $html = app(PageResponse::class)->render($this->page($document), 'en')->getContent();
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'A source record'));
        $this->assertStringContainsString('Undated', $html);
        $this->assertStringNotContainsString('data-autoplay="true"', $html);
    }

    public function test_reused_popup_is_once_has_stable_occurrences_and_is_excluded_from_outline_and_search_text(): void
    {
        $inside = new Document([new Node('popup-heading', 'standard.heading', props: new JsonObject(['level' => 'h2']), translations: new JsonObject(['en' => ['text' => 'Popup exclusive otter']])), new Node('popup-copy', 'standard.text', translations: new JsonObject(['en' => ['body' => '<p>Overlay exclusive walrus</p>']]))]);
        $reference = $this->part('overlay', OverlaySettings::with($inside, 'en', 'Details'));
        $document = CompositionData::promote(new Document([$this->button('first', $reference), $this->button('second', $reference), new Node('outline', 'standard.table-of-contents')]));
        $page = $this->page($document);
        $response = app(PageResponse::class)->render($page, 'en');
        $this->assertSame(200, $response->status(), $response->getContent());
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'data-sb-overlay '));
        $this->assertSame(1, substr_count($html, 'src="/vendor/site-builder/overlays.js'));
        $this->assertSame(2, substr_count($html, 'href="#sb-overlay-'));
        $body = explode('<section id="sb-overlay-', $html)[0];
        $this->assertStringNotContainsString('Popup exclusive otter', $body);
        $this->assertSame([], app(ContentIndex::class)->query(new ContentQuery(text: 'walrus', search: true), 'en')->items);
        $context = new RenderContext('en', pageId: $page->id, layout: $page->layout);
        $occurrences = app(ContentOccurrences::class)->visible($document, $context, true);
        $this->assertCount(1, array_filter($occurrences, fn ($entry) => $entry['node']->id === 'popup-copy'));
        $this->assertSame(array_column($occurrences, 'address'), array_column(app(ContentOccurrences::class)->visible($document, $context, true), 'address'));
    }

    public function test_hidden_or_other_locale_popup_triggers_do_not_attach_content(): void
    {
        $reference = $this->part('overlay', OverlaySettings::with(new Document, 'en', 'Invisible popup'));
        $button = $this->button('hidden', $reference);
        $hidden = new Node($button->id, $button->type, translations: $button->translations, visibility: 'hidden');
        $document = CompositionData::promote(new Document([$hidden]));
        $this->assertSame([], app(CompositionResolver::class)->resolve($document, new RenderContext('en'))->overlays);
        $translated = new Node('translated', 'standard.buttons', translations: new JsonObject(['en' => ['label' => 'English actions', 'items' => []], 'de' => ['label' => 'Deutsche Aktionen', 'items' => [['label' => 'Details', 'overlay' => $reference->jsonSerialize()]]]]));
        $document = CompositionData::promote(new Document([$translated]));
        $this->assertSame([], app(CompositionResolver::class)->resolve($document, new RenderContext('en'))->overlays);
        $this->assertCount(1, app(CompositionResolver::class)->resolve($document, new RenderContext('de'))->overlays);
    }

    public function test_overlay_actions_enforce_resource_kind_and_withdrawn_pins_fail_closed(): void
    {
        $section = $this->part('section', new Document);
        $document = CompositionData::promote(new Document([$this->button('wrong', $section)]));
        try {
            app(DocumentValidator::class)->validate($document, true);
            $this->fail('A section was accepted as an overlay.');
        } catch (InvalidDocument) {
            $this->addToAssertionCount(1);
        }
        $reference = $this->part('overlay', OverlaySettings::with(new Document, 'en', 'Withdrawn popup'));
        $page = $this->page(CompositionData::promote(new Document([$this->button('trigger', $reference)])));
        SitePartRevision::query()->whereKey($reference->revisionId)->update(['withdrawn_at' => now()]);
        $this->assertSame(503, app(PageResponse::class)->render($page, 'en')->status());
    }

    public function test_archiving_a_popup_preserves_existing_reuse_but_cannot_add_an_extra_trigger_inside_the_same_node(): void
    {
        $reference = $this->part('overlay', OverlaySettings::with(new Document, 'en', 'Archived popup'));
        $before = CompositionData::promote(new Document([$this->button('actions', $reference)]));
        SitePart::query()->whereKey($reference->resourceId)->update(['archived_at' => now()]);
        $actor = new GenericUser(['id' => 'interactive-editor']);
        app(SitePartCommands::class)->validateNewPins($actor, $before, $before);
        $translations = $before->nodes[0]->translations->toObject();
        $translations->en->items[] = $translations->en->items[0];
        $after = CompositionData::promote(new Document([new Node('actions', 'standard.buttons', translations: new JsonObject($translations))]));
        $this->expectException(ValidationException::class);
        app(SitePartCommands::class)->validateNewPins($actor, $before, $after);
    }

    public function test_form_context_inside_shared_popup_resolves_once_and_is_invalid_after_removal(): void
    {
        $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/forms-migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
        $profile = new NotificationProfile;
        $profile->forceFill(['name' => 'Popup enquiries', 'recipients' => ['team@example.test'], 'active' => true])->save();
        $form = new Node('enquiry', 'standard.form', props: new JsonObject(['profile' => $profile->id, 'installation' => self::INSTALLATION]), translations: (new Form)->defaults()->translations);
        $reference = $this->part('overlay', OverlaySettings::with(new Document([$form]), 'en', 'Ask a question'));
        $page = $this->page(CompositionData::promote(new Document([$this->button('a', $reference), $this->button('b', $reference)])));
        $html = app(PageResponse::class)->render($page, 'en')->getContent();
        preg_match_all('/name="context" value="([^"]+)"/', $html, $matches);
        $this->assertCount(1, $matches[1], $html);
        $token = html_entity_decode($matches[1][0]);
        $this->assertSame($profile->id, app(FormRuntime::class)->resolve($token)['profile']->id);
        $page->published_content = new Document;
        $page->save();
        $this->expectException(HttpException::class);
        app(FormRuntime::class)->resolve($token);
    }

    private function button(string $id, PublishedPartReference $reference): Node
    {
        return new Node($id, 'standard.buttons', translations: new JsonObject(['en' => ['label' => 'Actions', 'items' => [['label' => 'Open details', 'overlay' => $reference->jsonSerialize()]]]]));
    }

    private function part(string $kind, Document $document): PublishedPartReference
    {
        $actor = new GenericUser(['id' => 'interactive-editor']);
        $part = app(SitePartCommands::class)->create($actor, $kind, ['name' => 'Example '.$kind], SitePartPayload::document($kind, $document))->part;
        $lease = app(SitePartLocks::class)->acquire($actor, $part->id);
        $part = app(SitePartCommands::class)->publish($actor, $part->id, $lease->token, 0, app(CanonicalJson::class)->digest($part->draft_payload))->part;

        return $part->revisions()->findOrFail($part->current_revision_id)->reference();
    }

    private function page(Document $document, string $layout = 'components.business'): Page
    {
        $page = Page::factory()->create(['title' => 'Interactive examples', 'layout' => $layout, 'status' => 'published', 'published_at' => now(), 'published_content' => $document, 'draft_content' => $document]);
        $page->slugs()->create(['locale' => 'en', 'slug' => 'interactive-'.$page->id]);

        return $page->fresh();
    }
}

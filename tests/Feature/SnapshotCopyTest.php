<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Components\Reference;
use SiteBuilder\Core\Composition\CopyContext;
use SiteBuilder\Core\Composition\PageCompositionCommands;
use SiteBuilder\Core\Composition\PublishedPartReference;
use SiteBuilder\Core\Composition\ReferenceCheck;
use SiteBuilder\Core\Composition\ReferenceMap;
use SiteBuilder\Core\Composition\SourceSnapshot;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentCodec;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Contracts\CopyReferenceInspector;
use SiteBuilder\Core\Contracts\NavigationProvider;
use SiteBuilder\Core\Contracts\SiteMetadataProvider;
use SiteBuilder\Core\Contracts\SnapshotSourceProvider;
use SiteBuilder\Core\DraftHistory\DraftHistory;
use SiteBuilder\Core\Exceptions\WriteConflict;
use SiteBuilder\Core\Exchange\Exchange;
use SiteBuilder\Core\Exchange\Snapshot;
use SiteBuilder\Core\Persistence\PageCommands;
use SiteBuilder\Core\Persistence\PageLocks;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\SiteBuilderServiceProvider;
use SiteBuilder\Core\SiteParts\NativeGraphImportCommands;
use SiteBuilder\Core\SiteParts\NativeGraphImportPolicy;
use SiteBuilder\Core\SiteParts\NativeGraphMapper;
use SiteBuilder\Core\SiteParts\SitePartCommands;
use SiteBuilder\Core\SiteParts\SitePartGraphMap;
use SiteBuilder\Core\SiteParts\SitePartLocks;
use SiteBuilder\Core\SiteParts\SitePartPayload;
use SiteBuilder\Core\Snapshots\SnapshotChoice;
use SiteBuilder\Core\Snapshots\SnapshotCopyCommands;
use SiteBuilder\Core\Snapshots\SnapshotDestination;
use SiteBuilder\Core\Snapshots\SnapshotSelection;
use SiteBuilder\Core\Snapshots\SnapshotSourceRegistry;

class SnapshotCopyTest extends DatabaseTestCase
{
    private GenericUser $actor;

    private array $denied = [];

    private object $menu;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['history-migrations', 'site-parts-migrations'] as $path) {
            $this->artisan('migrate', ['--path' => realpath(dirname((new \ReflectionClass(SiteBuilderServiceProvider::class))->getFileName(), 2).'/database/'.$path), '--realpath' => true, '--force' => true])->assertExitCode(0);
        }
        config(['site-builder.site_parts.preview_layout' => 'components.minimal', 'site-builder-components.snapshots.menus' => ['primary' => 'Main menu']]);
        $this->actor = new GenericUser(['id' => (string) Str::uuid()]);
        Gate::before(fn ($actor, $ability): bool => ! in_array($ability, $this->denied, true));
        $this->menu = new class implements NavigationProvider
        {
            public array $entries = [['label' => 'New home', 'url' => '/new-home']];

            public function items(string $location, string $locale): array
            {
                return $this->entries;
            }
        };
        app()->instance(NavigationProvider::class, $this->menu);
    }

    public function test_page_copy_preserves_other_locales_unknown_shapes_and_history_has_one_transition_with_noop_staying_noop(): void
    {
        [$page,$lease,$destination] = $this->page();
        $history = app(DraftHistory::class)->open($this->actor, $page->id, $lease->token, (string) Str::uuid());
        $service = app(SnapshotCopyCommands::class);
        $selection = $service->selection($this->actor, $destination, 'standard.menus', 'primary', 'en', $lease->token, 0);
        $this->assertSame($selection->jsonSerialize(), SnapshotSelection::fromArray($selection->jsonSerialize())->jsonSerialize());
        $review = $service->review($this->actor, $selection, $lease->token);
        $this->assertSame(0, $page->fresh()->write_version);
        $result = $service->apply($this->actor, $selection, $lease->token, $review->candidateDigest, history: $history->context);
        $node = $result->page->draft_content->nodes[0];
        $this->assertSame('New home', $node->translations->get('en')->items[0]->label);
        $this->assertNull($node->translations->get('en')->items[0]->page);
        $this->assertSame('preserved', $node->translations->get('en')->items[0]->future->value);
        $this->assertSame([], $node->translations->get('de')->items);
        $this->assertSame('stacked', $node->props->get('orientation'));
        $this->assertSame('{}', json_encode($node->extensions->get('future')->object));
        $status = app(DraftHistory::class)->status($this->actor, $page->id, $lease->token, $history->context);
        $this->assertSame(1, $status->status['undoCount']);
        $again = $service->selection($this->actor, $destination, 'standard.menus', 'primary', 'en', $lease->token, 1);
        $noop = $service->review($this->actor, $again, $lease->token);
        $this->assertSame([], $noop->changes);
        $same = $service->apply($this->actor, $again, $lease->token, $noop->candidateDigest, history: $status->context);
        $this->assertSame(1, $same->page->write_version);
        $this->assertSame(1, app(DraftHistory::class)->status($this->actor, $page->id, $lease->token, $status->context)->status['undoCount']);
    }

    public function test_source_change_then_denial_and_wrong_intent_cannot_write_the_draft(): void
    {
        [$page,$lease,$destination] = $this->page();
        $service = app(SnapshotCopyCommands::class);
        $selection = $service->selection($this->actor, $destination, 'standard.menus', 'primary', 'en', $lease->token, 0);
        $review = $service->review($this->actor, $selection, $lease->token);
        $this->menu->entries[0]['label'] = 'Changed after review';
        try {
            $service->apply($this->actor, $selection, $lease->token, $review->candidateDigest);
            $this->fail('Stale source accepted.');
        } catch (WriteConflict $exception) {
            $this->assertStringContainsString('source_changed', $exception->getMessage());
        }
        $selection = $service->selection($this->actor, $destination, 'standard.menus', 'primary', 'en', $lease->token, 0);
        try {
            $service->apply($this->actor, $selection, $lease->token, str_repeat('a', 64));
            $this->fail('Unreviewed candidate accepted.');
        } catch (WriteConflict $exception) {
            $this->assertStringContainsString('snapshot_review_changed', $exception->getMessage());
        }
        $this->denied[] = 'copySiteBuilderMenu';
        $this->assertSame([], $service->choices($this->actor, $destination, 'en', $lease->token, 0));
        try {
            $service->review($this->actor, $selection, $lease->token);
            $this->fail('Denied source accepted.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $page->fresh()->write_version);
        }
        $this->assertSame('Old', $page->fresh()->draft_content->nodes[0]->translations->get('en')->items[0]->label);
    }

    public function test_owner_access_lease_and_cas_are_enforced_before_source_discovery(): void
    {
        [$page,$lease,$destination] = $this->page();
        $service = app(SnapshotCopyCommands::class);
        foreach ([[(string) Str::uuid(), 0], [$lease->token, 99]] as [$token,$version]) {
            try {
                $service->choices($this->actor, $destination, 'en', $token, $version);
                $this->fail('Owner guard bypassed.');
            } catch (WriteConflict) {
                $this->assertSame(0, $page->fresh()->write_version);
            }
        }
        $this->denied[] = 'update';
        $this->expectException(AuthorizationException::class);
        $service->choices($this->actor, $destination, 'en', $lease->token, 0);
    }

    public function test_metadata_maps_only_explicit_public_fields_and_shared_dimensions_are_reviewed(): void
    {
        config(['site-builder-components.snapshots.metadata' => ['name' => 'publicName', 'width' => 'logoWidth']]);
        app()->instance(SiteMetadataProvider::class, new class implements SiteMetadataProvider
        {
            public function metadata(string $locale): JsonObject
            {
                return new JsonObject(['publicName' => 'Copied site', 'logoWidth' => 160, 'secret' => 'must never copy']);
            }
        });
        $page = app(PageCommands::class)->create($this->actor, ['title' => 'Brand', 'layout' => 'components.minimal'], [], new Document([new Node('brand', 'standard.brand', props: new JsonObject(['variant' => 'text']), translations: new JsonObject(['en' => (object) ['name' => 'Old'], 'de' => (object) ['name' => 'Deutsch']]))]))->page;
        $lease = app(PageLocks::class)->acquire($this->actor, $page->id);
        $service = app(SnapshotCopyCommands::class);
        $destination = new SnapshotDestination('page', $page->id, 'brand');
        $selection = $service->selection($this->actor, $destination, 'standard.metadata', 'site', 'en', $lease->token, 0);
        $review = $service->review($this->actor, $selection, $lease->token);
        $this->assertContains('/props/width', array_column($review->changes, 'path'));
        $result = $service->apply($this->actor, $selection, $lease->token, $review->candidateDigest);
        $this->assertStringNotContainsString('secret', json_encode($result->page->draft_content));
        $this->assertSame('Deutsch', $result->page->draft_content->nodes[0]->translations->get('de')->name);
    }

    public function test_header_refresh_and_republication_leave_published_pages_pinned_until_explicit_draft_adoption(): void
    {
        $commands = app(SitePartCommands::class);
        $part = $commands->create($this->actor, 'header', ['name' => 'Snapshot header'], SitePartPayload::document('header', $this->document()))->part;
        $lease = app(SitePartLocks::class)->acquire($this->actor, $part->id);
        $a = $commands->publish($this->actor, $part->id, $lease->token, 0, app(CanonicalJson::class)->digest($part->draft_payload))->part;
        $revisionA = $a->revisions()->findOrFail($a->current_revision_id);
        $refA = new PublishedPartReference($a->id, $revisionA->id, $revisionA->digest);
        $page = app(PageCommands::class)->create($this->actor, ['title' => 'Pinned', 'layout' => 'components.minimal'], ['en' => 'pinned'])->page;
        $pageLease = app(PageLocks::class)->acquire($this->actor, $page->id);
        $bound = app(PageCompositionCommands::class)->bind($this->actor, $page->id, $pageLease->token, 0, 'header', $refA)->page;
        $published = app(PageCommands::class)->publish($this->actor, $page->id, $pageLease->token, $bound->write_version, app(CanonicalJson::class)->digest($bound->draft_content))->page;
        $service = app(SnapshotCopyCommands::class);
        $destination = new SnapshotDestination('site-part', $part->id, 'nav');
        $selection = $service->selection($this->actor, $destination, 'standard.menus', 'primary', 'en', $lease->token, $a->write_version);
        $review = $service->review($this->actor, $selection, $lease->token);
        $draft = $service->apply($this->actor, $selection, $lease->token, $review->candidateDigest)->part;
        $b = $commands->publish($this->actor, $part->id, $lease->token, $draft->write_version, app(CanonicalJson::class)->digest($draft->draft_payload))->part;
        $renderer = app(DocumentRenderer::class);
        $context = new RenderContext('en', pageId: $page->id, layout: 'components.minimal');
        $this->assertStringContainsString('Old', (string) $renderer->render($published->published_content, $context)->headerSlot);
        $this->assertStringNotContainsString('New home', (string) $renderer->render($published->published_content, $context)->headerSlot);
        $revisionB = $b->revisions()->findOrFail($b->current_revision_id);
        $new = app(PageCompositionCommands::class)->bind($this->actor, $page->id, $pageLease->token, $published->write_version, 'header', new PublishedPartReference($b->id, $revisionB->id, $revisionB->digest))->page;
        $this->assertStringContainsString('New home', (string) $renderer->render($new->draft_content, $context)->headerSlot);
        $this->assertStringContainsString('Old', (string) $renderer->render($new->published_content, $context)->headerSlot);
        $this->assertSame($refA->digest, $revisionA->fresh()->digest);
    }

    public function test_nested_typed_reference_permissions_are_rechecked_and_exact_maps_can_clear_a_denied_reference(): void
    {
        [$page,$lease,$destination] = $this->page();
        $this->registerSource(new Document([new Node('typed-source', 'standard.navigation', translations: new JsonObject(['en' => (object) ['label' => 'Typed menu', 'items' => [(object) ['label' => 'Group', 'children' => [(object) ['label' => 'Nested page', 'page' => $page->id, 'url' => null, 'children' => []]]]]]]))]));
        $service = app(SnapshotCopyCommands::class);
        $selection = $service->selection($this->actor, $destination, 'test.menu', 'source', 'en', $lease->token, 0);
        $this->assertFalse($service->review($this->actor, $selection, $lease->token)->references->blocking);
        $this->denied[] = 'view';
        $review = $service->review($this->actor, $selection, $lease->token);
        $this->assertTrue($review->references->blocking);
        $entry = $review->references->occurrences[0];
        $this->assertSame('/translations/en/items/0/children/0/page', $entry['path']);
        $this->assertSame('unauthorized', $entry['status']);
        $map = ReferenceMap::fromArray([['nodeId' => $entry['nodeId'], 'path' => $entry['path'], 'kind' => 'page', 'original' => $page->id, 'value' => null]]);
        $mapped = $service->review($this->actor, $selection, $lease->token, $map);
        $result = $service->apply($this->actor, $selection, $lease->token, $mapped->candidateDigest, $map);
        $this->assertNull($result->page->draft_content->nodes[0]->translations->get('en')->items[0]->children[0]->page);
    }

    public function test_legacy_page_inspectors_cannot_authorize_site_part_references_via_page_zero(): void
    {
        [$page] = $this->page();
        $this->registerSource(new Document([new Node('typed-source', 'standard.navigation', translations: new JsonObject(['en' => (object) ['items' => [(object) ['label' => 'Target', 'page' => $page->id, 'url' => null]]]]))]));
        $legacy = new class implements CopyReferenceInspector
        {
            public bool $called = false;

            public function inspect(Authenticatable $actor, Reference $reference, CopyContext $destination): ?ReferenceCheck
            {
                $this->called = true;

                return ReferenceCheck::valid('Legacy page-only approval');
            }
        };
        app()->instance('test.legacy-inspector', $legacy);
        app()->tag('test.legacy-inspector', CopyReferenceInspector::class);
        $part = app(SitePartCommands::class)->create($this->actor, 'header', ['name' => 'Typed owner'], SitePartPayload::document('header', $this->document()))->part;
        $lease = app(SitePartLocks::class)->acquire($this->actor, $part->id);
        $destination = new SnapshotDestination('site-part', $part->id, 'nav');
        $this->denied[] = 'view';
        $service = app(SnapshotCopyCommands::class);
        $selection = $service->selection($this->actor, $destination, 'test.menu', 'source', 'en', $lease->token, 0);
        $review = $service->review($this->actor, $selection, $lease->token);
        $this->assertTrue($review->references->blocking);
        $this->assertFalse($legacy->called);
        $this->assertSame(0, $part->fresh()->write_version);
    }

    public function test_oversized_legacy_sources_reject_instead_of_silently_truncating_and_unexpected_selection_fields_reject(): void
    {
        [$page,$lease,$destination] = $this->page();
        $this->menu->entries = array_fill(0, 101, ['label' => 'Too many', 'url' => '/many']);
        try {
            app(SnapshotCopyCommands::class)->selection($this->actor, $destination, 'standard.menus', 'primary', 'en', $lease->token, 0);
            $this->fail('Oversized source accepted.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, $page->fresh()->write_version);
        }
        $this->menu->entries = [['label' => 'Valid source', 'url' => '/valid']];
        $fixture = app(SnapshotCopyCommands::class)->selection($this->actor, $destination, 'standard.menus', 'primary', 'en', $lease->token, 0)->jsonSerialize();
        $fixture['leaseToken'] = 'not-a-selection-capability';
        $this->expectException(\InvalidArgumentException::class);
        SnapshotSelection::fromArray($fixture);
    }

    private function registerSource(Document $document): void
    {
        app(SnapshotSourceRegistry::class)->register('test.menu', new class($document) implements SnapshotSourceProvider
        {
            public function __construct(private Document $document) {}

            public function choices(Authenticatable $actor, SnapshotDestination $destination, string $locale): array
            {
                return [new SnapshotChoice('source', 'Typed source', 'standard.navigation')];
            }

            public function snapshot(Authenticatable $actor, SnapshotDestination $destination, string $sourceId, string $locale, bool $lock): SourceSnapshot
            {
                return SourceSnapshot::fromDocument('host-snapshot', 'test.menu:source', '1', $this->document);
            }
        });
    }

    private function page(): array
    {
        $page = app(PageCommands::class)->create($this->actor, ['title' => 'Snapshot', 'layout' => 'components.minimal'], [], $this->document())->page;

        return [$page, app(PageLocks::class)->acquire($this->actor, $page->id), new SnapshotDestination('page', $page->id, 'nav')];
    }

    public function test_explicit_null_projection_keeps_its_shape_then_repeated_copy_is_a_noop(): void
    {
        config(['site-builder-components.snapshots.metadata' => ['url' => 'url']]);
        app()->instance(SiteMetadataProvider::class, new class implements SiteMetadataProvider
        {
            public function metadata(string $locale): JsonObject
            {
                return new JsonObject(['url' => null]);
            }
        });
        $page = app(PageCommands::class)->create($this->actor, ['title' => 'Null projection', 'layout' => 'components.minimal'], [], new Document([new Node('brand', 'standard.brand', props: new JsonObject(['variant' => 'text']), translations: new JsonObject(['en' => (object) ['name' => 'Example']]))]))->page;
        $lease = app(PageLocks::class)->acquire($this->actor, $page->id);
        $destination = new SnapshotDestination('page', $page->id, 'brand');
        $service = app(SnapshotCopyCommands::class);
        $selection = $service->selection($this->actor, $destination, 'standard.metadata', 'site', 'en', $lease->token, 0);
        $review = $service->review($this->actor, $selection, $lease->token);
        $this->assertFalse($review->changes[0]['beforePresent']);
        $result = $service->apply($this->actor, $selection, $lease->token, $review->candidateDigest);
        $values = $result->page->draft_content->nodes[0]->translations->get('en');
        $this->assertTrue(property_exists($values, 'url'));
        $this->assertNull($values->url);
        $again = $service->selection($this->actor, $destination, 'standard.metadata', 'site', 'en', $lease->token, 1);
        $same = $service->review($this->actor, $again, $lease->token);
        $this->assertSame([], $same->changes);
        $this->assertSame(1, $service->apply($this->actor, $again, $lease->token, $same->candidateDigest)->page->write_version);
    }

    public function test_native_v3_graph_mapping_rewrites_nested_navigation_and_brand_refs_without_touching_opaque_values(): void
    {
        config(['site-builder.site_parts.exchange' => true]);
        [$page, $lease] = $this->page();
        $document = new Document([
            new Node('nav', 'standard.navigation', translations: new JsonObject(['en' => (object) ['label' => 'Main', 'items' => [(object) ['label' => 'Group', 'children' => [(object) ['label' => 'Typed page', 'page' => $page->id, 'url' => null, 'children' => []]], 'opaquePage' => $page->id]]], 'de' => (object) ['items' => []]])),
            new Node('brand', 'standard.brand', props: new JsonObject(['variant' => 'text']), translations: new JsonObject(['en' => (object) ['name' => 'Example', 'page' => $page->id, 'logo' => '/example-logo.svg']]), extensions: new JsonObject(['opaque' => (object) ['page' => $page->id, 'object' => (object) [], 'list' => [], 'nullable' => null]])),
        ]);
        $document = new Document([...$document->nodes,
            new Node('gallery', 'standard.gallery', props: new JsonObject(['lightbox' => true]), translations: new JsonObject(['en' => (object) ['label' => 'Mapped gallery', 'items' => [(object) ['image' => '/original.jpg', 'full_image' => '/full.jpg', 'sources' => [(object) ['image' => '/small.jpg', 'width' => 600]], 'alt' => 'Example', 'link_label' => 'Read the example', 'page' => $page->id, 'opaque' => (object) ['page' => $page->id, 'object' => (object) [], 'list' => [], 'nullable' => null]]]], 'de' => (object) ['items' => []]])),
            new Node('testimonials', 'standard.testimonials', props: new JsonObject(['variant' => 'single']), translations: new JsonObject(['en' => (object) ['items' => [(object) ['quote' => 'First', 'name' => 'Avery'], (object) ['quote' => 'Hidden second', 'name' => 'Robin', 'portrait' => '/portrait.jpg', 'page' => $page->id]]]])),
        ]);
        app(PageCommands::class)->saveDraft($this->actor, $page->id, $lease->token, 0, $document);
        $snapshot = app(Exchange::class)->export(DB::connection(), 'n-component-graph');
        $this->assertSame(3, json_decode($snapshot->encode())->version);
        $mapper = app(NativeGraphMapper::class);
        $description = $mapper->describe($snapshot);
        $identities = [];
        foreach ($description['identities'] as $kind => $ids) {
            $identities[$kind] = array_map(fn ($id) => ['source' => $id, 'target' => (string) ((int) $id + 1000)], $ids);
        }
        $choices = $description['occurrences'];
        foreach ($choices as &$choice) {
            $choice['value'] = $choice['kind'] === 'page' ? (int) $choice['original'] + 1000 : '/mapped-example.svg';
        }
        unset($choice);
        $policy = new class implements NativeGraphImportPolicy
        {
            public function authorize(Authenticatable $actor, Snapshot $source, SitePartGraphMap $map, Connection $target): void {}

            public function reference(Authenticatable $actor, string $document, Reference $source, mixed $mapped, Connection $target): ReferenceCheck
            {
                return ReferenceCheck::valid('Explicit synthetic domain mapping.');
            }

            public function allowOpaque(Authenticatable $actor, string $document, Document $source): bool
            {
                return true;
            }
        };
        $connectionName = 'n_component_exchange_target';
        $configuration = DB::connection()->getConfig();
        if ($configuration['driver'] === 'mysql') {
            $database = getenv('SITE_BUILDER_EXCHANGE_TARGET_DATABASE') ?: '';
            if (! str_starts_with($database, 'site_builder_') || ! str_ends_with($database, '_test') || $database === DB::connection()->getDatabaseName()) {
                throw new \RuntimeException('Select a distinct disposable site_builder_*_test graph target.');
            }
            $configuration['database'] = $database;
        } else {
            $configuration = ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true];
        }
        unset($configuration['name']);
        config(['database.connections.'.$connectionName => $configuration]);
        $target = DB::connection($connectionName);
        $target->getSchemaBuilder()->dropAllTables();
        try {
            foreach (['migrations', 'history-migrations', 'site-parts-migrations'] as $path) {
                $this->artisan('migrate', ['--database' => $connectionName, '--path' => realpath(dirname((new \ReflectionClass(SiteBuilderServiceProvider::class))->getFileName(), 2).'/database/'.$path), '--realpath' => true, '--force' => true])->assertExitCode(0);
            }
            $map = SitePartGraphMap::fromArray($identities, $choices);
            $mapped = $mapper->map($this->actor, $snapshot, $map, $target, $policy);
            app(NativeGraphImportCommands::class)->import($this->actor, $snapshot, $map, $target, $policy);
            $imported = $target->table('site_builder_pages')->where('id', $page->id + 1000)->value('draft_content');
            $this->assertNotNull($imported);
            $this->assertTrue(app(CanonicalJson::class)->equals(json_decode($mapped->tables()['pages'][0]['draft_content'], flags: JSON_THROW_ON_ERROR), json_decode($imported, flags: JSON_THROW_ON_ERROR)));
        } finally {
            $target->getSchemaBuilder()->dropAllTables();
            DB::purge($connectionName);
        }
        $row = $mapped->tables()['pages'][0];
        $after = app(DocumentCodec::class)->decode($row['draft_content']);
        $this->assertSame($page->id + 1000, $after->nodes[0]->translations->get('en')->items[0]->children[0]->page);
        $this->assertSame($page->id, $after->nodes[0]->translations->get('en')->items[0]->opaquePage);
        $this->assertSame($page->id + 1000, $after->nodes[1]->translations->get('en')->page);
        $this->assertSame('/mapped-example.svg', $after->nodes[1]->translations->get('en')->logo);
        $this->assertSame($page->id, $after->nodes[1]->extensions->get('opaque')->page);
        $this->assertSame([], $after->nodes[1]->extensions->get('opaque')->list);
        $image = $after->nodes[2]->translations->get('en')->items[0];
        $this->assertSame($page->id + 1000, $image->page);
        $this->assertSame('/mapped-example.svg', $image->image);
        $this->assertSame('/mapped-example.svg', $image->full_image);
        $this->assertSame('/mapped-example.svg', $image->sources[0]->image);
        $this->assertSame($page->id, $image->opaque->page);
        $this->assertSame('{}', json_encode($image->opaque->object));
        $this->assertSame([], $image->opaque->list);
        $this->assertNull($image->opaque->nullable);
        $this->assertSame([], $after->nodes[2]->translations->get('de')->items);
        $this->assertSame($page->id + 1000, $after->nodes[3]->translations->get('en')->items[1]->page);
        $this->assertSame('/mapped-example.svg', $after->nodes[3]->translations->get('en')->items[1]->portrait);

    }

    private function document(): Document
    {
        return new Document([new Node('nav', 'standard.navigation', props: new JsonObject(['orientation' => 'stacked']), translations: new JsonObject(['en' => (object) ['label' => 'Old menu', 'items' => [(object) ['label' => 'Old', 'url' => '/old', 'children' => [], 'future' => (object) ['value' => 'preserved']]]], 'de' => (object) ['label' => 'Deutsch', 'items' => []]]), extensions: new JsonObject(['future' => (object) ['object' => (object) [], 'list' => [], 'nullable' => null]]))]);
    }
}

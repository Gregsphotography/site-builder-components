<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Http\Request;
use SiteBuilder\Components\Definitions\LeafDefinition;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Components\ReferenceScanner;
use SiteBuilder\Core\Composition\CopyContext;
use SiteBuilder\Core\Composition\DocumentCopies;
use SiteBuilder\Core\Composition\ReferenceMap;
use SiteBuilder\Core\Composition\SourceSnapshot;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Discovery\ContentContext;
use SiteBuilder\Core\Discovery\ContentIndex;
use SiteBuilder\Core\Discovery\ContentItem;
use SiteBuilder\Core\Discovery\ContentQuery;
use SiteBuilder\Core\Discovery\ContentSourceProvider;
use SiteBuilder\Core\Discovery\ContentSources;
use SiteBuilder\Core\Discovery\ContentTextRegistry;
use SiteBuilder\Core\Discovery\DiscoveryMetadata;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\PublicQuery;

class DiscoveryTest extends DatabaseTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('site-builder.discovery.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('d', 32))]);
        $this->artisan('migrate', ['--path' => realpath(dirname((new \ReflectionClass(\SiteBuilder\Core\SiteBuilderServiceProvider::class))->getFileName(), 2).'/database/discovery-migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
    }

    public function test_only_published_addressable_indexable_locale_content_is_searchable_and_updates_after_commit(): void
    {
        $public = $this->page('Café Zurich', 'Public elephant services');
        $this->page('Unpublished elephant', 'Draft secrets', 'draft');
        $hidden = $this->page('Hidden elephant', 'Private pages');
        $hidden->seo = (object) ['en' => (object) ['noindex' => true]];
        $hidden->save();
        $index = app(ContentIndex::class);
        $this->assertSame(['Café Zurich'], array_column($index->query(new ContentQuery(text: 'CAFE elephant', search: true), 'en')->items, 'title'));
        $this->assertSame([], $index->query(new ContentQuery(text: 'elephant', search: true), 'de')->items);
        $public->draft_content = $this->text('unpublished giraffe');
        $public->save();
        $this->assertSame([], $index->query(new ContentQuery(text: 'giraffe', search: true), 'en')->items);
        $public->status = 'draft';
        $public->save();
        $this->assertSame([], $index->query(new ContentQuery(text: 'elephant', search: true), 'en')->items);
        $this->assertSame(0, $index->query(new ContentQuery, 'en')->total);
    }

    public function test_index_never_extracts_hidden_nodes_form_configuration_or_arbitrary_document_metadata(): void
    {
        $document = new Document([
            new Node('body', 'standard.text', translations: new JsonObject(['en' => ['body' => '<p>Visible mountain</p>']])),
            new Node('hidden', 'standard.text', translations: new JsonObject(['en' => ['body' => '<p>hidden zebra</p>']]), visibility: 'hidden'),
            new Node('form', 'standard.form', props: new JsonObject(['profile' => 'private hippo'])),
        ], new JsonObject(['opaque' => ['secret' => 'metadata otter']]));
        $page = $this->page('Text projection', 'old');
        $page->published_content = $document;
        $page->save();
        foreach (['zebra', 'hippo', 'otter'] as $word) {
            $this->assertSame([], app(ContentIndex::class)->query(new ContentQuery(text: $word, search: true), 'en')->items);
        }
        $this->assertCount(1, app(ContentIndex::class)->query(new ContentQuery(text: 'mountain', search: true), 'en')->items);
    }

    public function test_facets_pagination_literal_like_tokens_and_rebuild_are_portable(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $page = $this->page('Record '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'A 100% useful _value');
            $page->published_content = DiscoveryMetadata::with($page->published_content, 'en', ['tags' => [$i % 2 === 0 ? 'Even' : 'Odd'], 'summary' => 'An editorial summary']);
            $page->type = 'article';
            $page->save();
        }
        $index = app(ContentIndex::class);
        $this->assertSame(15, $index->rebuild('en'));
        $result = $index->query(new ContentQuery(type: 'article', tag: 'Even', sort: 'title', perPage: 3, page: 2), 'en');
        $this->assertSame(8, $result->total);
        $this->assertSame(['Record 06', 'Record 08', 'Record 10'], array_column($result->items, 'title'));
        $this->assertSame(['Even', 'Odd'], $result->tags);
        $this->assertSame([], $index->query(new ContentQuery(text: 'missing%', search: true), 'en')->items);
        $this->assertSame(15, $index->query(new ContentQuery(text: '100% _value', search: true), 'en')->total);
    }

    public function test_adapter_hydration_rechecks_scope_visibility_and_freshness_without_disclosing_stale_records(): void
    {
        $provider = new class implements ContentSourceProvider
        {
            public bool $visible = true;

            public int $hydrateCalls = 0;

            public function label(): string
            {
                return 'Projects';
            }

            public function items(ContentContext $context): iterable
            {
                return $this->hydrate(['one', 'two'], $context);
            }

            public function hydrate(array $ids, ContentContext $context): array
            {
                $this->hydrateCalls++;
                $result = [];
                foreach ($this->visible && $context->scope === 'tenant-a' ? $ids : [] as $id) {
                    $result[$id] = new ContentItem($id, $context->locale, 'Project '.$id, '/projects/'.$id);
                }

                return $result;
            }
        };
        $sources = app(ContentSources::class);
        $sources->register('host.projects', $provider);
        $sources->scopeUsing(fn () => 'tenant-a');
        $index = app(ContentIndex::class);
        $index->rebuild('en');
        $provider->hydrateCalls = 0;
        $this->assertCount(2, $index->query(new ContentQuery(source: 'host.projects'), 'en')->items);
        $this->assertSame(1, $provider->hydrateCalls);
        $provider->visible = false;
        $this->assertSame([], $index->query(new ContentQuery(source: 'host.projects'), 'en')->items);
        $sources->scopeUsing(fn () => 'tenant-b');
        $this->assertSame(0, $index->query(new ContentQuery(source: 'host.projects'), 'en')->total);
        $this->assertFalse($index->query(new ContentQuery(source: 'missing.source'), 'en')->available);
    }

    public function test_discovery_media_are_declared_and_remapped_in_whole_document_copies(): void
    {
        $document = DiscoveryMetadata::with($this->text('A story'), 'en', ['image' => '/before.jpg', 'tags' => ['Story']]);
        $this->assertSame([], app(DocumentValidator::class)->validate($document));
        $reference = app(ReferenceScanner::class)->scan($document)[0];
        $this->assertSame('@document', $reference->nodeId);
        $copy = app(DocumentCopies::class)->instantiate(SourceSnapshot::fromDocument('trusted-copy', 'local', '1', $document), new CopyContext(0, ''), ReferenceMap::fromArray([['nodeId' => '@document', 'path' => $reference->path, 'kind' => 'media', 'original' => '/before.jpg', 'value' => '/after.jpg']]));
        $this->assertSame('/after.jpg', DiscoveryMetadata::values($copy->document, 'en')['image']);
        $this->assertSame('/before.jpg', DiscoveryMetadata::values($document, 'en')['image']);
    }

    public function test_host_component_configuration_is_not_indexed_without_an_explicit_public_text_extractor(): void
    {
        $definition = new class extends LeafDefinition
        {
            public function type(): string
            {
                return 'host.configured';
            }

            public function view(): string
            {
                return 'site-builder-components::blocks.text';
            }

            public function defaults(): PropertyValues
            {
                return new PropertyValues;
            }

            public function properties(): array
            {
                return ['heading' => PropertyDefinition::text(), 'api_key' => PropertyDefinition::text()];
            }
        };
        app(ComponentRegistry::class)->register($definition);
        $page = $this->page('A configured component', 'Initial content');
        $page->published_content = new Document([new Node('configured', $definition->type(), props: new JsonObject(['heading' => 'Public capybara', 'api_key' => 'private pangolin']))]);
        $page->save();
        $index = app(ContentIndex::class);
        $this->assertSame([], $index->query(new ContentQuery(text: 'pangolin', search: true), 'en')->items);
        $this->assertSame([], $index->query(new ContentQuery(text: 'capybara', search: true), 'en')->items);
        app(ContentTextRegistry::class)->register($definition->type(), fn ($node, $props) => $props['heading']);
        $index->refresh('builder.pages', [(string) $page->id], 'en');
        $this->assertCount(1, $index->query(new ContentQuery(text: 'capybara', search: true), 'en')->items);
        $this->assertSame([], $index->query(new ContentQuery(text: 'pangolin', search: true), 'en')->items);
    }

    public function test_relevance_prefers_title_words_and_manual_timeline_text_is_searchable_without_configuration(): void
    {
        $title = $this->page('Studio in Zurich', 'Useful information');
        $this->page('A different place', 'Studio Zurich in the body');
        $results = app(ContentIndex::class)->query(new ContentQuery(text: 'Zurich Studio', sort: 'relevance', search: true), 'en');
        $this->assertSame((string) $title->id, $results->items[0]->id);
        $manual = $this->page('Our milestones', 'Initial');
        $manual->published_content = new Document([new Node('timeline', 'standard.timeline', props: new JsonObject(['content_mode' => 'manual', 'source' => 'builder.pages']), translations: new JsonObject(['en' => ['items' => [['title' => 'The marmot milestone', 'body' => '<p>A memorable beginning.</p>']]]]))]);
        $manual->save();
        $this->assertCount(1, app(ContentIndex::class)->query(new ContentQuery(text: 'marmot', search: true), 'en')->items);
        $this->assertSame([], app(ContentIndex::class)->query(new ContentQuery(text: 'builder.pages', search: true), 'en')->items);
    }

    public function test_suggestions_are_public_only_bounded_and_private_and_query_state_is_allowlisted(): void
    {
        $this->page('Searchable café', 'A useful answer');
        $this->getJson('/_site-builder/search/suggest?q=cafe&locale=en')->assertOk()->assertJsonPath('items.0.title', 'Searchable café')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/_site-builder/search/suggest?q=c&locale=en')->assertUnprocessable();
        $this->getJson('/_site-builder/search/suggest?q=cafe&locale=xx')->assertNotFound();
        $request = Request::create('/?q=hello&scope=tenant-b&source[]=bad&page=2');
        $state = PublicQuery::fromRequest($request);
        $this->assertSame(['q' => 'hello', 'page' => '2'], $state->all());
    }

    private function text(string $text): Document
    {
        return new Document([new Node('text', 'standard.text', translations: new JsonObject(['en' => ['body' => '<p>'.$text.'</p>']]))]);
    }

    private function page(string $title, string $text, string $status = 'published'): Page
    {
        $page = Page::factory()->create(['title' => $title, 'status' => $status, 'published_at' => now(), 'published_content' => $this->text($text)]);
        $page->slugs()->create(['locale' => 'en', 'slug' => 'record-'.$page->id]);

        return $page->fresh();
    }
}

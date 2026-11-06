<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use ReflectionClass;
use SiteBuilder\Components\Definitions\Column;
use SiteBuilder\Components\Definitions\Columns;
use SiteBuilder\Components\Definitions\Container;
use SiteBuilder\Components\Definitions\Section;
use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentCodec;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Design\DesignCatalog;
use SiteBuilder\Core\Design\DesignData;
use SiteBuilder\Core\Design\DesignOperations;
use SiteBuilder\Core\Design\DesignResolver;
use SiteBuilder\Core\Design\DesignTarget;
use SiteBuilder\Core\Design\ThemeIdentity;
use SiteBuilder\Core\Exceptions\InvalidDocument;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\LayoutRegistry;
use SiteBuilder\Core\Rendering\PageResponse;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\Rendering\ResolvedNode;
use SiteBuilder\Core\SiteBuilderServiceProvider;
use SiteBuilder\Core\SiteParts\SitePart;
use SiteBuilder\Core\SiteParts\SitePartPayload;
use SiteBuilder\Core\SiteParts\SitePartRevision;

class LayoutBlocksTest extends DatabaseTestCase
{
    private string $layoutPublicPath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->layoutPublicPath = sys_get_temp_dir().'/site-builder-layout-public-'.Str::uuid();
        $app->usePublicPath($this->layoutPublicPath);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutPublicPath);
        parent::tearDown();
    }

    private function text(string $id, string $heading, string $visibility = 'visible'): Node
    {
        return new Node($id, 'standard.text', props: new JsonObject(['variant' => 'default']), translations: new JsonObject(['en' => (object) ['heading' => 'English '.$heading], 'de' => (object) ['heading' => $heading, 'body' => '<p>Gut verständlicher Inhalt <strong>klar</strong>.</p><script>bad()</script>']]), visibility: $visibility);
    }

    private function columns(int $count, string $variant = 'equal'): Node
    {
        $children = [];
        for ($index = 0; $index < $count; $index++) {
            $children[] = new Node('column-'.$index, 'standard.column', children: [$this->text('text-'.$index, 'Spalte '.$index)]);
        }

        return new Node('columns', 'standard.columns', props: new JsonObject(['variant' => $variant]), children: $children);
    }

    private function dom(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?><!doctype html><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($dom);
    }

    public function test_layouts_declare_bounded_children_root_placement_and_fresh_usable_default_subtrees(): void
    {
        $registry = app(ComponentRegistry::class);
        foreach ([Section::class, Container::class, Columns::class, Column::class] as $class) {
            $definition = app($class);
            $this->assertSame($definition->type(), $registry->resolve($definition->type())->type());
            $this->assertTrue($definition->placement()->structural);
            $this->assertTrue($definition->placement()->childAllowed);
            $this->assertSame($class !== Column::class, $definition->placement()->rootAllowed);
            $one = new Document($definition->defaultChildren());
            $two = new Document($definition->defaultChildren());
            $ids = fn (Document $document): array => array_keys(DesignData::nodes($document));
            $this->assertSame([], array_intersect($ids($one), $ids($two)));
            $this->assertNotEmpty($one->nodes);
        }
        $columns = app(Columns::class)->allowedChildren();
        $this->assertSame(['standard.column'], $columns->allowedTypes);
        $this->assertSame([1, 4], [$columns->minimum, $columns->maximum]);
        $this->assertFalse($columns->allowsCount(0));
        $this->assertTrue($columns->allowsCount(4));
        $this->assertFalse($columns->allowsCount(5));
        config(['site-builder-components.additional_layout_children' => ['host.approved']]);
        $this->assertTrue(app(Container::class)->allowedChildren()->allows('host.approved'));
        $this->assertFalse(app(Container::class)->allowedChildren()->allows('standard.column'));
        $this->assertFalse(app(Column::class)->allowedChildren()->allows('standard.columns'));
    }

    public function test_actual_nested_views_keep_translated_reading_order_sanitization_and_all_supported_arrangements_in_every_theme(): void
    {
        foreach (['minimal', 'business', 'editorial', 'host'] as $preset) {
            foreach (['equal', 'sidebar-start', 'sidebar-end'] as $variant) {
                foreach ([1, 2, 3, 4] as $count) {
                    $document = new Document([new Node('section', 'standard.section', props: new JsonObject(['heading_level' => 'h2']), translations: new JsonObject(['de' => (object) ['heading' => 'Deutsche Gruppe']]), children: [new Node('container', 'standard.container', children: [$this->columns($count, $variant)])])]);
                    $result = app(DocumentRenderer::class)->render($document, new RenderContext('de', 'public', layout: 'components.'.$preset));
                    $this->assertSame([], $result->diagnostics);
                    $html = view('site-builder-components::layouts.'.$preset, ['locale' => 'de', 'title' => 'Layout', 'slot' => $result->html])->render();
                    $xpath = $this->dom($html);
                    $this->assertCount(1, $xpath->query('//section[@data-site-builder-node="section" and @aria-labelledby]'));
                    $this->assertCount(1, $xpath->query('//*[@data-site-builder-node="container"]/*[@data-site-builder-node="columns"]'));
                    $this->assertCount($count, $xpath->query('//*[@data-site-builder-node="columns"]/*[contains(@class,"sb-column ") or contains(@class,"sb-column")]'));
                    $this->assertStringContainsString('sb-columns--'.$count, $html);
                    $this->assertStringContainsString('sb-columns--'.$variant, $html);
                    $this->assertStringContainsString('Deutsche Gruppe', $html);
                    $this->assertStringNotContainsString('English Spalte', $html);
                    $this->assertStringNotContainsString('<script', $html);
                    $this->assertStringNotContainsString('bad()', $html);
                    $positions = array_map(fn (int $index): int => strpos($html, 'Spalte '.$index), range(0, $count - 1));
                    $sorted = $positions;
                    sort($sorted);
                    $this->assertSame($positions, $sorted);
                    $this->assertSame([], $result->structuredData);
                }
            }
        }
    }

    public function test_semantic_sections_require_a_real_heading_and_preserve_zero_while_render_addresses_avoid_repeated_mount_ids(): void
    {
        $render = fn (string $heading): string => (string) app(DocumentRenderer::class)->render(new Document([new Node('section', 'standard.section', props: new JsonObject(['heading_level' => 'h3']), translations: new JsonObject(['en' => (object) ['heading' => $heading]]), children: [$this->text('text', 'Content')])]), new RenderContext('en'))->html;
        $zero = $this->dom($render('0'));
        $this->assertCount(1, $zero->query('//section/h3[text()="0"]'));
        $this->assertCount(0, $this->dom($render(''))->query('//section[@data-site-builder-node="section"]'));
        $this->assertCount(1, $this->dom($render(''))->query('//div[@data-site-builder-node="section"]'));
        $unsafe = $render('<img onerror="bad()">');
        $this->assertStringNotContainsString('<img onerror', $unsafe);
        $this->assertStringContainsString('&lt;img', $unsafe);
        $ids = [];
        foreach (['first-mount', 'second-mount'] as $address) {
            $view = view('site-builder-components::blocks.section', ['node' => new ResolvedNode(new Node('repeated-source', 'standard.section'), new JsonObject), 'props' => ['heading' => 'Heading', 'heading_level' => 'h2'], 'attributes' => new ComponentAttributeBag(['data-site-builder-render-address' => $address]), 'children' => new HtmlString('')])->render();
            $node = $this->dom($view)->query('//section')->item(0);
            $ids[] = $node->getAttribute('aria-labelledby');
        }
        $this->assertNotSame($ids[0], $ids[1]);
    }

    public function test_root_child_cardinality_structural_and_physical_limits_reject_invalid_complete_trees(): void
    {
        $invalid = [
            new Document([new Node('root-column', 'standard.column')]),
            new Document([new Node('empty-columns', 'standard.columns')]),
            new Document([$this->columns(5)]),
            new Document([new Node('wrong-child', 'standard.columns', children: [$this->text('text', 'Wrong parent')])]),
        ];
        $nested = $this->text('leaf', 'Deep content');
        for ($depth = 0; $depth < 4; $depth++) {
            $nested = new Node('container-'.$depth, 'standard.container', children: [$nested]);
        }
        $this->assertSame([], app(DocumentValidator::class)->validate(new Document([$nested])));
        $invalid[] = new Document([new Node('too-deep', 'standard.container', children: [$nested])]);
        foreach ($invalid as $document) {
            try {
                app(DocumentValidator::class)->validate($document);
                $this->fail('The complete nested tree must obey placement, count and depth policies.');
            } catch (InvalidDocument $exception) {
                $this->assertNotEmpty($exception->diagnostics);
            }
        }
        try {
            (new DocumentCodec(maxDepth: 2))->encode(new Document([$nested]));
            $this->fail('Structural layout does not bypass physical document limits.');
        } catch (InvalidDocument $exception) {
            $this->assertSame('tree_limit', $exception->diagnostics[0]->code);
        }
    }

    public function test_rootable_examples_are_deterministic_and_nested_nodes_have_registered_design_controls(): void
    {
        $examples = app(StandardExamples::class);
        foreach (['standard.section', 'standard.container', 'standard.columns'] as $type) {
            $json = app(CanonicalJson::class);
            $this->assertSame($json->digest($examples->document($type, 'de')), $json->digest($examples->document($type, 'de')));
            $this->assertNotEmpty($examples->document($type, 'de')->nodes[0]->children);
        }
        $this->assertArrayNotHasKey('standard.column', $examples->entries());
        $catalog = app(DesignCatalog::class);
        $theme = new ThemeIdentity('standard.base', 1, 'business');
        foreach (['standard.section', 'standard.container', 'standard.columns', 'standard.column'] as $type) {
            $this->assertNotEmpty($catalog->controls($theme, 'node', $type));
        }
    }

    public function test_region_capabilities_render_bound_slots_once_and_empty_bindings_do_not_restore_scaffolds(): void
    {
        foreach (['components.default', 'components.minimal', 'components.business', 'components.editorial', 'components.host'] as $layout) {
            foreach (['body', 'header', 'footer'] as $region) {
                $this->assertTrue(app(LayoutRegistry::class)->supports($layout, $region));
            }
        }
        $base = ['locale' => 'en', 'title' => 'Regions', 'slot' => new HtmlString('<p>BODY-UNIQUE</p>')];
        $html = view('site-builder-components::layouts.site', $base + ['headerSlot' => new HtmlString('<p>HEADER-UNIQUE</p>'), 'footerSlot' => new HtmlString('<p>FOOTER-UNIQUE</p>')])->render();
        foreach (['HEADER-UNIQUE', 'BODY-UNIQUE', 'FOOTER-UNIQUE'] as $text) {
            $this->assertSame(1, substr_count($html, $text));
        }
        $xpath = $this->dom($html);
        $this->assertCount(1, $xpath->query('//header[contains(@class,"sb-region--header")]'));
        $this->assertCount(0, $xpath->query('//*[contains(@class,"sb-brand")]'));
        $empty = $this->dom(view('site-builder-components::layouts.site', $base + ['headerSlot' => new HtmlString(''), 'footerSlot' => new HtmlString('')])->render());
        $this->assertCount(0, $empty->query('//*[contains(@class,"sb-brand")]'));
        $unbound = $this->dom(view('site-builder-components::layouts.site', $base + ['headerSlot' => null, 'footerSlot' => null])->render());
        $this->assertCount(1, $unbound->query('//*[contains(@class,"sb-brand")]'));
    }

    public function test_compiled_layout_rules_keep_bounded_tracks_logical_sidebar_order_and_narrow_column_reflow(): void
    {
        $css = file_get_contents(__DIR__.'/../../resources/css/components.css');
        $this->assertStringContainsString('.sb-columns { display: grid; grid-template-columns: minmax(0, 1fr);', $css);
        $this->assertStringContainsString('@media (min-width: 48rem)', $css);
        $this->assertStringContainsString('@container sb-column (max-width: 32rem)', $css);
        $this->assertStringContainsString('container-type: inline-size', $css);
        $this->assertStringContainsString('min-inline-size: 0', $css);
        foreach ([2, 3, 4] as $count) {
            $this->assertStringContainsString('.sb-columns--'.$count.' { grid-template-columns: repeat('.$count.', minmax(0, 1fr)); }', $css);
            foreach (['start', 'end'] as $edge) {
                preg_match('~\.sb-columns--sidebar-'.$edge.'\.sb-columns--'.$count.'\s*\{([^}]+)\}~', $css, $match);
                $this->assertNotEmpty($match);
                $small = 'minmax(0, 1fr)';
                $remaining = $count === 2 ? 'minmax(0, 2fr)' : 'repeat('.($count - 1).', minmax(0, 2fr))';
                $this->assertStringContainsString('grid-template-columns: '.($edge === 'start' ? $small.' '.$remaining : $remaining.' '.$small), $match[1]);
            }
        }
        preg_match('~@container sb-column \(max-width: 32rem\)\s*\{(.*?)\n\}~s', $css, $query);
        foreach (['.sb-hero--split', '.sb-image-text', '.sb-cards--two', '.sb-cards--three', '.sb-cards--four', 'grid-column: auto; grid-row: auto;'] as $responsive) {
            $this->assertStringContainsString($responsive, $query[1]);
        }
        $this->assertDoesNotMatchRegularExpression('/(?:\{|;)\s*order\s*:/', $css, 'Reading order is never rearranged visually.');
        $this->assertStringNotContainsString('grid-auto-flow: dense', $css);
    }

    public function test_parent_design_inherits_before_node_overrides_and_reset_restores_the_parent_at_each_breakpoint(): void
    {
        $source = new Document([new Node('container', 'standard.container', children: [$this->text('text', 'Inhalt')])]);
        $context = new RenderContext('de', 'preview', layout: 'components.business');
        $operations = app(DesignOperations::class);
        $document = $operations->set($source, DesignTarget::document(), 'align', 'start', $context);
        $document = $operations->set($document, DesignTarget::node('container'), 'align', 'center', $context);
        $document = $operations->set($document, DesignTarget::node('text', 'compact'), 'align', 'end', $context);
        $text = $document->nodes[0]->children[0];
        $presentation = app(DesignResolver::class)->resolve($document, $text, $context);
        $this->assertSame('center', $presentation->states['base']['values']['align']);
        $this->assertStringContainsString('Parent container', $presentation->states['base']['sources']['align']);
        $this->assertSame('end', $presentation->states['compact']['values']['align']);
        $this->assertSame('end', $presentation->states['wide']['values']['align']);
        $rendered = app(DocumentRenderer::class)->render($document, $context);
        $this->assertSame([], $rendered->diagnostics);
        $node = $this->dom((string) $rendered->html)->query('//*[@data-site-builder-node="text"]')->item(0);
        $this->assertStringContainsString('sb-d-align-center', $node->getAttribute('class'));
        $this->assertStringContainsString('sb-d-c-align-end', $node->getAttribute('class'));
        $reset = $operations->reset($document, DesignTarget::node('text', 'compact'), 'align', $context);
        $restored = app(DesignResolver::class)->resolve($reset, $reset->nodes[0]->children[0], $context);
        foreach (['base', 'compact', 'wide'] as $breakpoint) {
            $this->assertSame('center', $restored->states[$breakpoint]['values']['align']);
        }
        $this->assertSame(json_encode($source->nodes[0]->children[0]->translations), json_encode($reset->nodes[0]->children[0]->translations));
    }

    public function test_public_header_and_footer_node_design_matches_compiled_rules_without_editable_page_node_ids(): void
    {
        $root = dirname((new ReflectionClass(SiteBuilderServiceProvider::class))->getFileName(), 2);
        $this->artisan('migrate', ['--path' => $root.'/database/site-parts-migrations', '--realpath' => true, '--force' => true])->assertExitCode(0);
        $context = new RenderContext('en', 'preview', layout: 'components.business');
        $parts = [];
        foreach (['header', 'footer'] as $kind) {
            $document = new Document([new Node('repeated-source-node', 'standard.text', props: new JsonObject(['variant' => 'default']), translations: new JsonObject(['en' => (object) ['heading' => 'REGION '.strtoupper($kind), 'body' => '<p>A readable published region.</p>']]))]);
            $document = app(DesignOperations::class)->set($document, DesignTarget::node('repeated-source-node'), 'surface', 'accent', $context);
            $document = app(DesignOperations::class)->set($document, DesignTarget::node('repeated-source-node'), 'align', 'center', $context);
            $payload = SitePartPayload::document($kind, $document);
            $part = new SitePart;
            $part->forceFill(['id' => (string) Str::uuid(), 'kind' => $kind, 'name' => 'Region '.$kind, 'draft_payload' => $payload, 'write_version' => 1, 'created_by' => '7', 'created_by_type' => 'Host\\Actor', 'updated_by' => '7', 'updated_by_type' => 'Host\\Actor'])->save();
            $revision = new SitePartRevision;
            $revision->forceFill(['id' => (string) Str::uuid(), 'resource_id' => $part->id, 'kind' => $kind, 'sequence' => 1, 'payload' => $payload, 'digest' => app(CanonicalJson::class)->digest($payload), 'published_from_version' => 0, 'created_by' => '7', 'created_by_type' => 'Host\\Actor', 'published_at' => now()])->save();
            $part->forceFill(['current_revision_id' => $revision->id])->save();
            $parts[$kind] = $revision->reference()->jsonSerialize();
        }
        $document = new Document([$this->text('body', 'Body')], new JsonObject(['site-builder.render' => (object) ['version' => 1, 'composition' => (object) ['version' => 1, ...$parts]]]));
        $page = Page::factory()->create(['title' => 'Regions', 'layout' => 'components.business', 'status' => 'published', 'published_content' => $document]);
        $response = app(PageResponse::class)->render($page, 'en');
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $xpath = $this->dom($response->getContent());
        $addresses = [];
        foreach (['header', 'footer'] as $kind) {
            $nodes = $xpath->query('//'.$kind.'//*[contains(concat(" ",normalize-space(@class)," ")," sb-d-surface-accent ")]');
            $this->assertCount(1, $nodes);
            $node = $nodes->item(0);
            $this->assertTrue($node->hasAttribute('data-site-builder-render-address'));
            $this->assertFalse($node->hasAttribute('data-site-builder-node'), 'A published region must not invent page editing authority.');
            $this->assertStringContainsString('sb-d-align-center', $node->getAttribute('class'));
            $addresses[] = $node->getAttribute('data-site-builder-render-address');
        }
        $this->assertNotSame($addresses[0], $addresses[1]);
        $css = file_get_contents(public_path('vendor/site-builder-components/components.css'));
        $this->assertMatchesRegularExpression('/:is\(\[data-site-builder-node\],\s*\[data-site-builder-render-address\]\)\.sb-design\.sb-d-surface-accent/', $css);
        $this->assertStringNotContainsString('type="module"', $response->getContent());
    }
}

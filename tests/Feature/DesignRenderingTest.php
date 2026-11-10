<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SiteBuilder\Components\Design\StandardDesignTheme;
use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Design\DesignCatalog;
use SiteBuilder\Core\Design\DesignOperations;
use SiteBuilder\Core\Design\DesignResolver;
use SiteBuilder\Core\Design\DesignTarget;
use SiteBuilder\Core\Design\ThemeIdentity;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Models\PageSlug;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\PageResponse;
use SiteBuilder\Core\Rendering\RenderContext;

class DesignRenderingTest extends DatabaseTestCase
{
    private string $designPublicPath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->designPublicPath = sys_get_temp_dir().'/site-builder-design-render-'.Str::uuid();
        $app->usePublicPath($this->designPublicPath);
        $app['config']->set('site-builder.layout', 'components.business');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->designPublicPath);
        parent::tearDown();
    }

    private function examples(): Document
    {
        $examples = app(StandardExamples::class);

        return new Document(array_map(fn (string $type): Node => $examples->document($type, 'de')->nodes[0], array_keys($examples->entries())));
    }

    public function test_document_preset_styles_only_the_canvas_and_keeps_the_host_header_footer_and_rtl_direction(): void
    {
        config(['site-builder.locales.ar' => ['label' => 'العربية', 'dir' => 'rtl']]);
        $context = new RenderContext('ar', layout: 'components.business');
        $document = new Document([new Node('rtl', 'standard.text', translations: new JsonObject(['ar' => (object) ['heading' => 'العنوان', 'body' => '<p>النص</p>']]))]);
        $operations = app(DesignOperations::class);
        $document = $operations->setTheme($document, new ThemeIdentity('standard.base', 1, 'editorial'), $context);
        $document = $operations->set($document, DesignTarget::node('rtl'), 'align', 'end', $context);
        $page = Page::factory()->create(['title' => 'RTL design', 'layout' => 'components.business', 'status' => 'published', 'published_content' => $document]);
        PageSlug::factory()->create(['page_id' => $page->id, 'locale' => 'ar', 'slug' => 'home']);
        $html = app(PageResponse::class)->render($page, 'ar')->getContent();
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $this->assertSame('rtl', $xpath->query('/html')->item(0)->getAttribute('dir'));
        $this->assertCount(1, $xpath->query('//div[@data-site-builder-theme="business"]/header'));
        $this->assertCount(1, $xpath->query('//div[@data-site-builder-theme="business"]/footer'));
        $this->assertCount(1, $xpath->query('//div[@data-site-builder-design-theme="standard.base" and @data-site-builder-theme="editorial"]'));
        $this->assertCount(0, $xpath->query('//header/ancestor::*[@data-site-builder-design-theme] | //footer/ancestor::*[@data-site-builder-design-theme]'));
        $node = $xpath->query('//*[@data-site-builder-node="rtl"]')->item(0);
        $this->assertStringContainsString('sb-d-align-end', $node->getAttribute('class'));
        $this->assertStringContainsString('العنوان', $node->textContent);
        $this->assertCount(0, $xpath->query('//*[@data-site-builder-design-theme or @data-site-builder-node]/@style'));
        $this->assertStringNotContainsString('type="module"', $html);
    }

    public function test_every_control_choice_and_breakpoint_renders_all_standard_blocks_in_each_preset(): void
    {
        $theme = app(StandardDesignTheme::class);
        $operations = app(DesignOperations::class);
        $renderer = app(DocumentRenderer::class);
        $context = new RenderContext('de', 'preview', layout: 'components.business');
        $source = $this->examples();
        foreach (array_keys($theme->presets()) as $preset) {
            $identity = new ThemeIdentity('standard.base', 1, $preset);
            foreach (['base', 'compact', 'wide'] as $breakpoint) {
                foreach ($theme->controls($identity, 'document') as $control) {
                    foreach (array_keys($control->options) as $option) {
                        $document = $operations->setTheme($source, $identity, $context);
                        if ($control->key === 'tone' && $option === 'on-accent') {
                            $document = $operations->set($document, DesignTarget::document(), 'surface', 'accent', $context);
                        }
                        $document = $operations->set($document, DesignTarget::document($breakpoint), $control->key, $option, $context);
                        $result = $renderer->render($document, $context);
                        $this->assertSame([], $result->diagnostics, $preset.' '.$control->key.' '.$option.' '.$breakpoint);
                        $dom = new \DOMDocument;
                        $dom->loadHTML('<!doctype html><html><body>'.$result->html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                        $xpath = new \DOMXPath($dom);
                        $this->assertCount(1, $xpath->query('//*[@data-site-builder-design-theme="standard.base" and @data-site-builder-theme="'.$preset.'"]'));
                        $elements = $xpath->query('//*[@data-site-builder-node]');
                        $this->assertCount(47, $elements);
                        $expected = $theme->classes($control->key, $option, $breakpoint)[1];
                        foreach ($elements as $element) {
                            $this->assertContains($expected, preg_split('/\s+/', $element->getAttribute('class')));
                        }
                    }
                }
            }
        }
    }

    public function test_inheritance_reset_and_sparse_classes_preserve_other_languages_and_variant_defaults(): void
    {
        $opaque = new JsonObject(['object' => (object) [], 'list' => [], 'null' => null]);
        $source = new Document([
            new Node('hero', 'standard.hero', props: new JsonObject(['variant' => 'centred']), translations: new JsonObject(['en' => (object) ['heading' => 'English'], 'de' => (object) ['heading' => 'Deutsch']]), extensions: $opaque),
            new Node('text', 'standard.text', props: new JsonObject(['variant' => 'narrow']), extensions: $opaque),
        ], $opaque);
        $operations = app(DesignOperations::class);
        $resolver = app(DesignResolver::class);
        $context = new RenderContext('de', layout: 'components.editorial');
        $document = $operations->set($source, DesignTarget::document(), 'width', 'narrow', $context);
        $document = $operations->set($document, DesignTarget::document('compact'), 'width', 'wide', $context);
        $document = $operations->set($document, DesignTarget::node('hero'), 'width', 'content', $context);
        $resolved = $resolver->resolve($document, $document->nodes[0], $context);
        foreach (['base', 'compact', 'wide'] as $breakpoint) {
            $this->assertSame('content', $resolved->states[$breakpoint]['values']['width']);
            $this->assertStringContainsString('Section', $resolved->states[$breakpoint]['sources']['width']);
            $this->assertStringContainsString(app(StandardDesignTheme::class)->classes('width', 'content', $breakpoint)[1], $resolved->attributes['class']);
        }
        $this->assertStringNotContainsString('sb-d-align-', $resolved->attributes['class']);
        $this->assertStringNotContainsString('sb-d-surface-', $resolved->attributes['class']);
        $reset = $operations->reset($document, DesignTarget::node('hero'), 'width', $context);
        $inherited = $resolver->resolve($reset, $reset->nodes[0], $context);
        $this->assertSame('wide', $inherited->states['compact']['values']['width']);
        $this->assertStringContainsString('Page', $inherited->states['compact']['sources']['width']);
        $this->assertSame(json_encode($source->nodes[0]->translations), json_encode($reset->nodes[0]->translations));
        $this->assertSame(json_encode($opaque), json_encode($reset->nodes[0]->extensions));
        $this->assertSame('center', $inherited->states['base']['values']['align']);
        $this->assertSame('narrow', $resolver->resolve($source, $source->nodes[1], $context)->states['base']['values']['width']);
    }

    public function test_unavailable_versions_layouts_and_incompatible_treatments_are_reported_without_restyling(): void
    {
        $source = $this->examples();
        $operations = app(DesignOperations::class);
        $context = new RenderContext('de', layout: 'components.business');
        $identity = new ThemeIdentity('standard.base', 1, 'editorial');
        $document = $operations->setTheme($source, $identity, $context);
        $this->assertTrue($operations->report($document, new ThemeIdentity('standard.base', 1, 'minimal'), $context)->compatible);
        $this->assertFalse($operations->report($document, new ThemeIdentity('standard.base', 999, 'minimal'), $context)->compatible);
        $missing = $operations->report($document, $identity, new RenderContext('de', layout: 'host.unmapped'));
        $this->assertFalse($missing->compatible);
        $rendered = app(DocumentRenderer::class)->render($document, new RenderContext('de', layout: 'host.unmapped'));
        $this->assertSame('', (string) $rendered->html);
        $this->assertContains('unavailable_design_theme', array_map(fn ($diagnostic) => $diagnostic->code, $rendered->diagnostics));
        $before = json_encode($document);
        try {
            $operations->set($document, DesignTarget::document(), 'tone', 'on-accent', $context);
            $this->fail('Unsafe semantic surface/text combinations cannot be applied.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, json_encode($document));
        }
        $this->assertCount(3, app(DesignCatalog::class)->themes($context));
    }
}

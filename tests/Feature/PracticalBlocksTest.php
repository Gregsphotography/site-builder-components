<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use DOMDocument;
use DOMXPath;
use SiteBuilder\Components\Definitions\Tabs;
use SiteBuilder\Components\Tests\TestCase;
use SiteBuilder\Core\Composition\DocumentCopies;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Exceptions\InvalidDocument;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\PublicAssets;
use SiteBuilder\Core\Rendering\RenderContext;

class PracticalBlocksTest extends TestCase
{
    public function test_contents_before_headings_uses_stable_unique_ids_and_ignores_hidden_nodes_and_tab_labels(): void
    {
        $document = new Document([new Node('contents', 'standard.table-of-contents', translations: new JsonObject(['en' => (object) ['heading' => 'On this page']])), new Node('first', 'standard.heading', props: new JsonObject(['level' => 'h2']), translations: new JsonObject(['en' => (object) ['text' => 'First café']])), new Node('second', 'standard.heading', props: new JsonObject(['level' => 'h3']), translations: new JsonObject(['en' => (object) ['text' => 'Second']])), new Node('hidden', 'standard.heading', translations: new JsonObject(['en' => (object) ['text' => 'Hidden']]), visibility: 'hidden')]);
        $renderer = app(DocumentRenderer::class);
        $html = $renderer->render($document, new RenderContext('en'))->html->toHtml();
        $this->assertSame($html, $renderer->render($document, new RenderContext('en'))->html->toHtml());
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $ids = [];
        foreach ($xpath->query('//h2|//h3') as $heading) {
            $ids[] = $heading->getAttribute('id');
        }
        $this->assertCount(2, array_unique($ids));
        foreach ($ids as $id) {
            $this->assertStringContainsString('href="#'.$id.'"', $html);
        }
        $this->assertStringNotContainsString('Hidden', $html);
        $copy = app(DocumentCopies::class)->copy($document);
        $copiedHtml = $renderer->render($copy, new RenderContext('en'))->html->toHtml();
        foreach ($ids as $id) {
            $this->assertStringNotContainsString('id="'.$id.'"', $copiedHtml);
        }
    }

    public function test_duplicate_explicit_anchors_block_publication_but_remain_editable_in_drafts(): void
    {
        $document = new Document([new Node('one', 'standard.anchor', props: new JsonObject(['name' => 'contact'])), new Node('two', 'standard.anchor', props: new JsonObject(['name' => 'contact']))]);
        $this->assertSame([], app(DocumentValidator::class)->validate($document));
        $this->expectException(InvalidDocument::class);
        app(DocumentValidator::class)->validate($document, true);
    }

    public function test_panel_defaults_preserve_nested_content_and_reject_ninth_panel(): void
    {
        $tabs = new Tabs;
        $document = new Document([new Node('tabs', $tabs->type(), translations: $tabs->defaults()->translations, children: $tabs->defaultChildren())]);
        $this->assertSame([], app(DocumentValidator::class)->validate($document, true));
        $html = app(DocumentRenderer::class)->render($document, new RenderContext('en'))->html->toHtml();
        $this->assertStringContainsString('data-sb-tab-panel', $html);
        $this->assertStringContainsString('Overview', $html);
        $this->assertStringNotContainsString(' hidden', $html);
        $panels = [];
        for ($i = 0; $i < 9; $i++) {
            $panels[] = new Node('panel-'.$i, 'standard.tab-panel', translations: new JsonObject(['en' => (object) ['title' => 'Panel '.$i]]));
        }
        $this->expectException(InvalidDocument::class);
        app(DocumentValidator::class)->validate(new Document([new Node('tabs', 'standard.tabs', children: $panels)]));
    }

    public function test_filters_are_opt_in_and_emit_no_active_controls_or_modules_for_existing_collections(): void
    {
        $items = [(object) ['heading' => 'Café', 'body' => '<p>Thoughtful work</p>', 'tags' => [(object) ['label' => 'Places']]]];
        $render = fn (bool $enabled): string => app(PublicAssets::class)->scope(function () use ($enabled, $items): string {
            $document = new Document([new Node('cards', 'standard.cards', props: new JsonObject(['searchable' => $enabled, 'filterable' => $enabled]), translations: new JsonObject(['en' => (object) ['items' => $items]]))]);
            $html = app(DocumentRenderer::class)->render($document, new RenderContext('en'))->html->toHtml();

            return $html.app(PublicAssets::class)->html()->toHtml();
        });
        $this->assertStringNotContainsString('data-sb-collection-controls', $render(false));
        $this->assertStringNotContainsString('collections.js', $render(false));
        $this->assertStringContainsString('data-sb-collection-controls hidden', $render(true));
        $this->assertStringContainsString('collections.js', $render(true));
        $this->assertStringContainsString('Café', $render(true));
    }
}

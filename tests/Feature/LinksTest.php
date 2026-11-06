<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Components\ReferenceScanner;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Models\PageSlug;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\RenderContext;

class LinksTest extends DatabaseTestCase
{
    public function test_typed_page_reference_uses_only_available_published_locale_without_stale_url_fallback(): void
    {
        $target = Page::factory()->create(['status' => 'published', 'published_content' => new Document]);
        PageSlug::factory()->create(['page_id' => $target->id, 'locale' => 'en', 'slug' => 'target']);
        $document = new Document([
            new Node('cta', 'standard.cta', translations: new JsonObject(['en' => (object) ['heading' => 'Get started', 'label' => 'Visit target', 'url' => '/stale-url', 'page' => $target->id]])),
            new Node('cards', 'standard.cards', translations: new JsonObject(['en' => (object) ['items' => [(object) ['heading' => 'Useful card', 'label' => 'Card target', 'page' => $target->id, 'image' => '/local.webp', 'sources' => [(object) ['image' => '/local-small.webp', 'width' => 600]]]]]])),
        ]);
        $renderer = app(DocumentRenderer::class);
        $en = (string) $renderer->render($document, new RenderContext('en'))->html;
        $this->assertStringContainsString('href="http://localhost/target"', $en);
        $this->assertStringNotContainsString('/stale-url', $en);
        $de = (string) $renderer->render($document, new RenderContext('de'))->html;
        $this->assertStringNotContainsString('href=', $de);
        $target->forceFill(['status' => 'draft'])->save();
        $this->assertStringNotContainsString('href=', (string) $renderer->render($document, new RenderContext('en'))->html);
        $references = app(ReferenceScanner::class)->scan($document);
        $this->assertCount(4, $references);
        $this->assertSame(['page', 'media', 'media', 'page'], array_map(fn ($reference) => $reference->kind, $references));
        $this->assertSame('/translations/en/items/0/sources/0/image', $references[2]->path);
    }

    public function test_faq_structured_data_is_derived_from_sanitized_public_content_only(): void
    {
        $document = new Document([new Node('faq', 'standard.faq', translations: new JsonObject(['en' => (object) ['items' => [(object) ['question' => 'How do I start?', 'answer' => '<script>bad()</script><p>Say <strong>hello</strong>.</p>']]]]))]);
        $public = app(DocumentRenderer::class)->render($document, new RenderContext('en'));
        $this->assertSame([['@type' => 'FAQPage', 'inLanguage' => 'en', 'mainEntity' => [['@type' => 'Question', 'name' => 'How do I start?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Say hello.']]]]], $public->structuredData);
        $this->assertSame([], app(DocumentRenderer::class)->render($document, new RenderContext('en', 'preview'))->structuredData);
    }
}

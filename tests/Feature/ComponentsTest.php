<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Support\Str;
use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Components\Tests\TestCase;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Contracts\HasDefaultChildren;
use SiteBuilder\Core\Contracts\HasPlacementPolicy;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\RenderContext;

class ComponentsTest extends TestCase
{
    public function test_literal_zero_content_survives_as_visible_semantic_text_and_labels(): void
    {
        $translated = fn (array $values): JsonObject => new JsonObject(['en' => (object) $values]);
        $zero = ['heading' => '0', 'body' => '0'];
        $document = new Document([
            new Node('hero', 'standard.hero', translations: $translated([...$zero, 'eyebrow' => '0', 'label' => '0', 'url' => '/hero', 'secondary_label' => '0', 'secondary_url' => '/secondary'])),
            new Node('text', 'standard.text', translations: $translated([...$zero, 'items' => [(object) ['label' => '0', 'url' => '/text']]])),
            new Node('image', 'standard.image', props: new JsonObject(['image' => '/image.webp']), translations: $translated(['alt' => '0', 'caption' => '0'])),
            new Node('image-text', 'standard.image-text', translations: $translated([...$zero, 'label' => '0', 'url' => '/image-text'])),
            new Node('cards', 'standard.cards', translations: $translated([...$zero, 'items' => [(object) [...$zero, 'label' => '0', 'url' => '/card']]])),
            new Node('cta', 'standard.cta', translations: $translated([...$zero, 'label' => '0', 'url' => '/cta'])),
            new Node('faq', 'standard.faq', translations: $translated(['heading' => '0', 'items' => [(object) ['question' => '0', 'answer' => '0'], (object) ['question' => '', 'answer' => 'An empty question is still omitted']]])),
            new Node('contact', 'standard.contact-details', translations: $translated([...$zero, 'items' => [(object) ['label' => '0', 'value' => '0', 'url' => '/contact']]])),
            new Node('empty', 'standard.text', translations: $translated(['heading' => '', 'body' => ''])),
        ]);
        $this->assertSame([], app(DocumentValidator::class)->validate($document, true));
        $html = (string) app(DocumentRenderer::class)->render($document, new RenderContext('en'))->html;
        $dom = new \DOMDocument;
        $dom->loadHTML('<!doctype html><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        foreach ([
            '//h1 | //h2 | //h3' => 8,
            '//*[contains(concat(" ", normalize-space(@class), " "), " sb-prose ")]' => 8,
            '//a' => 7,
            '//figcaption' => 1,
            '//summary' => 1,
            '//dt | //dd' => 2,
            '//*[contains(concat(" ", normalize-space(@class), " "), " sb-eyebrow ")]' => 1,
        ] as $query => $count) {
            $elements = $xpath->query($query);
            $this->assertCount($count, $elements, $query);
            foreach ($elements as $element) {
                $this->assertSame('0', trim($element->textContent), $query);
            }
        }
        $this->assertSame('0', $xpath->query('//img')->item(0)->getAttribute('alt'));
        $this->assertCount(0, $xpath->query('//*[@data-site-builder-node="empty"]/h2 | //*[@data-site-builder-node="empty"]/div'));
        $this->assertStringNotContainsString('An empty question is still omitted', $html);
    }

    public function test_every_standard_definition_and_variant_renders_without_editor_providers(): void
    {
        foreach (app(ComponentRegistry::class)->all() as $definition) {
            if ((! str_starts_with($definition->type(), 'standard.') && ! str_starts_with($definition->type(), 'demo.')) || ($definition instanceof HasPlacementPolicy && ! $definition->placement()->rootAllowed)) {
                continue;
            }
            $values = $definition->defaults();
            $variants = $definition->properties()['variant']->options ?? [null];
            foreach ($variants as $variant) {
                $props = (array) $values->props->toObject();
                if ($variant !== null) {
                    $props['variant'] = $variant;
                }
                $translations = $values->translations;
                if ($definition->type() === 'standard.brand' && $variant === 'logo') {
                    $translated = $translations->toObject();
                    $translated->en->logo = StandardExamples::asset('northstar');
                    $translations = new JsonObject($translated);
                }
                $node = new Node((string) Str::uuid(), $definition->type(), props: new JsonObject($props), translations: $translations, children: $definition instanceof HasDefaultChildren ? $definition->defaultChildren() : []);
                $doc = new Document([$node]);
                $this->assertSame([], app(DocumentValidator::class)->validate($doc, $definition->type() !== 'standard.form'));
                $render = app(DocumentRenderer::class)->render($doc, new RenderContext('en'));
                $this->assertStringContainsString('data-site-builder-node="'.$node->id.'"', (string) $render->html);
                $this->assertSame([], $render->diagnostics);
            }
        }
        $this->assertCount(33, array_filter(array_keys(app(ComponentRegistry::class)->all()), fn (string $type): bool => str_starts_with($type, 'standard.')));
        $this->assertCount(4, array_filter(array_keys(app(ComponentRegistry::class)->all()), fn (string $type): bool => str_starts_with($type, 'demo.')));
        $this->assertFalse(array_any(array_keys(app()->getLoadedProviders()), fn (string $provider): bool => str_starts_with($provider, 'Filament\\') || str_starts_with($provider, 'Livewire\\') || str_starts_with($provider, 'SiteBuilder\\Filament\\')));
    }

    public function test_translations_fallback_empty_repeated_lists_and_sanitizer_are_shared_by_all_themes(): void
    {
        $doc = new Document([
            new Node('hero', 'standard.hero', props: new JsonObject(['variant' => 'centred']), translations: new JsonObject(['en' => (object) ['heading' => 'English', 'body' => '<p>Fallback <strong>body</strong></p>'], 'de' => (object) ['heading' => 'Deutsch']])),
            new Node('image', 'standard.image', props: new JsonObject(['image' => '/safe/image.webp']), translations: new JsonObject(['en' => (object) ['alt' => 'English alt', 'caption' => 'English caption'], 'de' => (object) ['alt' => 'Deutscher Alternativtext', 'caption' => 'Deutsche Bildlegende']])),
            new Node('cards', 'standard.cards', translations: new JsonObject(['en' => (object) ['items' => [(object) ['heading' => 'English card']]], 'de' => (object) ['items' => [(object) ['heading' => 'Deutsche Karte', 'body' => '<script>evil()</script><p onclick="evil()">Safe <a href="javascript:evil()">link</a></p>', 'url' => 'javascript:evil()']]], 'fr' => (object) ['items' => []]])),
            new Node('faq', 'standard.faq', translations: new JsonObject(['en' => (object) ['items' => [(object) ['question' => '<img src=x onerror=evil()>', 'answer' => '<p>Answer</p><iframe src="https://evil.test"></iframe>']]]])),
        ]);
        $before = app(CanonicalJson::class)->digest($doc);
        $html = (string) app(DocumentRenderer::class)->render($doc, new RenderContext('de'))->html;
        foreach (['Deutsch', '<strong>body</strong>', 'alt="Deutscher Alternativtext"', 'Deutsche Bildlegende', 'Deutsche Karte', '&lt;img', '<summary>', '<p>Answer</p>'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach (['English card', '<script', '<iframe', 'onclick=', 'javascript:', '<img src=x'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
        $fr = (string) app(DocumentRenderer::class)->render($doc, new RenderContext('fr', 'preview'))->html;
        $this->assertStringNotContainsString('English card', $fr);
        $this->assertSame($before, app(CanonicalJson::class)->digest($doc));
    }

    public function test_empty_and_long_content_remains_semantic_and_missing_owned_media_does_not_emit_broken_images(): void
    {
        $doc = new Document([
            new Node('image', 'standard.image', props: new JsonObject(['image' => 'site-builder-media/missing.webp', 'decorative' => true])),
            new Node('faq', 'standard.faq'),
            new Node('text', 'standard.text', translations: new JsonObject(['en' => (object) ['heading' => str_repeat('LongWord', 80), 'body' => '<p>'.str_repeat('Long content. ', 200).'</p>']])),
            new Node('contact', 'standard.contact-details'),
        ]);
        $html = (string) app(DocumentRenderer::class)->render($doc, new RenderContext('en'))->html;
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString(str_repeat('LongWord', 80), $html);
        $this->assertStringContainsString('<dl>', $html);
        $this->assertStringNotContainsString('<summary>', $html);
    }

    public function test_library_owned_demo_compatibility_preserves_stored_identity_and_view_contract(): void
    {
        $nodes = [];
        foreach (['demo.hero', 'demo.text', 'demo.image-text', 'demo.cta'] as $type) {
            $definition = app(ComponentRegistry::class)->resolve($type);
            $defaults = $definition->defaults();
            $nodes[] = new Node($type, $type, props: $defaults->props, translations: $defaults->translations);
        }
        $html = (string) app(DocumentRenderer::class)->render(new Document($nodes), new RenderContext('en'))->html;
        foreach (['content-hero tone-brand', 'A PLACE TO BEGIN', 'content-text', 'content-image-text', 'An illustrated green landscape', 'content-cta', '/admin/component-library'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach ($nodes as $node) {
            $this->assertSame(1, $node->version);
        }
    }
}

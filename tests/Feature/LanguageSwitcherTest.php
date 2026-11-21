<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use DOMDocument;
use DOMXPath;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Models\PageSlug;
use SiteBuilder\Core\Rendering\PageResponse;

class LanguageSwitcherTest extends DatabaseTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', str_repeat('k', 32));
    }

    public function test_visitors_can_follow_the_published_translation_in_each_stock_layout(): void
    {
        config(['site-builder.routes.prefix' => 'pages']);
        $page = Page::factory()->create(['status' => 'published', 'published_content' => new Document, 'seo' => (object) ['de' => (object) ['noindex' => true]]]);
        PageSlug::factory()->create(['page_id' => $page->id, 'locale' => 'en', 'slug' => 'services']);
        PageSlug::factory()->create(['page_id' => $page->id, 'locale' => 'de', 'slug' => 'über-uns/angebote']);

        foreach (['components.business', 'components.editorial', 'components.minimal', 'components.host', null] as $layout) {
            $page->update(['layout' => $layout]);
            $html = $this->get('/pages/services')->assertOk()->getContent();
            $dom = $this->dom($html);
            $selector = '//nav[@aria-label="Languages"]/a';
            $this->assertSame(2, $dom->query($selector)->length);
            $this->assertSame('English', $dom->evaluate('string('.$selector.'[@aria-current="page"])'));
            $link = $dom->evaluate('string('.$selector.'[@lang="de" and @hreflang="de"]/@href)');
            $this->assertSame(url('/pages/de/%C3%BCber-uns/angebote'), $link);
            $translated = $this->dom($this->get($link)->assertOk()->getContent());
            $this->assertSame('Deutsch', $translated->evaluate('string('.$selector.'[@aria-current="page"])'));
        }

        config(['site-builder.language_switcher' => false]);
        $this->get('/pages/services')->assertOk()->assertDontSee('aria-label="Languages"', false)->assertSee('rel="alternate"', false);
    }

    public function test_one_published_language_has_no_switcher_and_draft_languages_only_appear_in_private_preview(): void
    {
        $page = Page::factory()->create(['layout' => 'components.default', 'published_content' => new Document, 'status' => 'published']);
        PageSlug::factory()->create(['page_id' => $page->id, 'locale' => 'en', 'slug' => 'home']);
        $draft = $page->publishedSettings();
        $draft->slugs->de = 'entwurf';
        $page->forceFill(['draft_settings' => $draft])->save();

        $this->get('/')->assertOk()->assertDontSee('aria-label="Languages"', false)->assertDontSee('entwurf');
        $html = app(PageResponse::class)->render($page->fresh(), 'de', true)->getContent();
        $dom = $this->dom($html);
        $this->assertSame('Deutsch', $dom->evaluate('string(//nav[@aria-label="Languages"]/span[@aria-current="page"])'));
        $this->assertSame(0, $dom->query('//nav[@aria-label="Languages"]/a')->length);
    }

    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}

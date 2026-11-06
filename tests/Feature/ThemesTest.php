<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use SiteBuilder\Components\Rendering\SiteScaffold;
use SiteBuilder\Components\Templates\TemplateCatalog;
use SiteBuilder\Components\Tests\DatabaseTestCase;
use SiteBuilder\Components\Themes\ThemeCatalog;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Contracts\HasDefaultChildren;
use SiteBuilder\Core\Contracts\HasPlacementPolicy;
use SiteBuilder\Core\Contracts\NavigationProvider;
use SiteBuilder\Core\Contracts\SiteMetadataProvider;
use SiteBuilder\Core\Models\Menu;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Models\PageSlug;
use SiteBuilder\Core\Models\Setting;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\LayoutRegistry;
use SiteBuilder\Core\Rendering\PageResponse;
use SiteBuilder\Core\Rendering\RenderContext;

class ThemesTest extends DatabaseTestCase
{
    public function test_every_block_variant_in_every_preset_handles_translated_empty_long_missing_and_unsafe_content(): void
    {
        $renderer = app(DocumentRenderer::class);
        foreach (app(ComponentRegistry::class)->all() as $definition) {
            if (! str_starts_with($definition->type(), 'standard.')) {
                continue;
            }
            if ($definition instanceof HasPlacementPolicy && ! $definition->placement()->rootAllowed) {
                continue;
            }
            foreach ($definition->properties()['variant']->options ?? [null] as $variant) {
                foreach (['translated', 'empty', 'long', 'missing', 'unsafe'] as $case) {
                    $props = [];
                    $translated = [];
                    foreach ($definition->properties() as $key => $property) {
                        $value = $this->sample($property, $case);
                        if ($property->translated) {
                            $translated[$key] = $value;
                        } else {
                            $props[$key] = $key === 'variant' ? $variant : $value;
                        }
                    }
                    $node = new Node('matrix', $definition->type(), props: new JsonObject($props), translations: new JsonObject(['de' => (object) $translated]), children: $definition instanceof HasDefaultChildren ? $definition->defaultChildren() : []);
                    $content = $renderer->render(new Document([$node]), new RenderContext('de'))->html;
                    foreach (['minimal', 'business', 'editorial', 'host'] as $preset) {
                        $html = view('site-builder-components::layouts.'.$preset, ['locale' => 'de', 'title' => 'Matrix', 'slot' => $content, 'seo' => ['preview' => true]])->render();
                        $this->assertStringContainsString('data-site-builder-node="matrix"', $html);
                        $this->assertStringContainsString('data-site-builder-theme="'.$preset.'"', $html);
                        $this->assertStringNotContainsString('<script', $html);
                        $this->assertStringNotContainsString('<img onerror', $html);
                        $this->assertStringNotContainsString('href="javascript:', $html);
                        $this->assertStringNotContainsString('src="javascript:', $html);
                        if ($case === 'missing') {
                            $this->assertStringNotContainsString('<img', $html);
                        }
                    }
                }
            }
        }
    }

    private function sample(PropertyDefinition $property, string $case): mixed
    {
        return match ($property->kind) {
            'text' => match ($case) {
                'empty' => '', 'long' => str_repeat('Langer deutscher Inhalt ', 40), 'unsafe' => '<img onerror="bad()">', default => 'Deutscher Inhalt'
            },
            'html' => $case === 'empty' ? '' : '<p>Deutscher Inhalt <strong>klar</strong></p>'.($case === 'unsafe' ? '<script>bad()</script><a href="javascript:bad()">unsafe</a>' : ''),
            'media' => match ($case) {
                'empty' => null, 'missing' => 'site-builder-media/missing.webp', 'unsafe' => 'javascript:bad()', default => '/images/local.webp'
            },
            'url' => $case === 'unsafe' ? 'javascript:bad()' : '/safe',
            'page' => null,
            'boolean' => false,
            'number' => 1200,
            'enum' => $property->options[0],
            'list' => $case === 'empty' ? [] : [$this->sample($property->items, $case)],
            'object' => (object) array_map(fn (PropertyDefinition $child): mixed => $this->sample($child, $case), $property->properties),
            default => null,
        };
    }

    public function test_presets_and_host_mode_share_document_markup_and_use_only_local_opt_in_assets(): void
    {
        $doc = app(TemplateCatalog::class)->instantiate('standard.home');
        $content = (string) app(DocumentRenderer::class)->render($doc, new RenderContext('en'))->html;
        $page = Page::factory()->create(['title' => 'A useful home', 'published_content' => $doc, 'status' => 'published']);
        PageSlug::factory()->create(['page_id' => $page->id, 'locale' => 'en', 'slug' => 'home']);
        foreach (['minimal', 'business', 'editorial', 'host'] as $preset) {
            $page->layout = 'components.'.$preset;
            $html = app(PageResponse::class)->render($page, 'en')->getContent();
            $this->assertStringContainsString('data-site-builder-theme="'.$preset.'"', $html);
            $this->assertStringContainsString($content, $html);
            $this->assertStringContainsString('href="#site-builder-content"', $html);
            $this->assertStringContainsString('id="site-builder-content"', $html);
            $this->assertStringNotContainsString('http://localhost:5173', $html);
            $this->assertStringNotContainsString('fonts.googleapis', $html);
            if ($preset === 'host') {
                $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
            } else {
                $this->assertStringContainsString('/vendor/site-builder-components/components.css?v=', $html);
            }
        }
        config(['site-builder-components.theme' => 'business', 'site-builder-components.assets' => false]);
        $page->layout = 'components.default';
        $html = app(PageResponse::class)->render($page, 'en')->getContent();
        $this->assertStringContainsString('data-site-builder-theme="business"', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
        $this->assertSame('site-builder-components::layouts.site', app(LayoutRegistry::class)->view('components.default'));
    }

    public function test_default_scaffold_reads_neutral_stores_and_filters_unsafe_navigation(): void
    {
        Setting::query()->create(['namespace' => 'site', 'data' => (object) ['name' => 'A local business', 'footer' => 'Useful work, thoughtfully made.']]);
        Menu::query()->create(['location' => 'primary', 'items' => (object) ['en' => [(object) ['label' => 'About', 'url' => '/about'], (object) ['label' => 'Unsafe', 'url' => 'javascript:bad()']], 'de' => [(object) ['label' => 'Über uns', 'url' => '/de/uber-uns']]]]);
        Menu::query()->create(['location' => 'footer', 'items' => (object) ['en' => [(object) ['label' => 'Privacy', 'url' => '/privacy']]]]);
        $data = app(SiteScaffold::class)->data('en');
        $this->assertSame('A local business', $data['name']);
        $this->assertSame('Useful work, thoughtfully made.', $data['footer']);
        $this->assertSame([['label' => 'About', 'url' => '/about', 'children' => []]], $data['header']);
        $this->assertSame('Privacy', $data['footer_links'][0]['label']);
        $this->assertSame('Über uns', app(SiteScaffold::class)->data('de')['header'][0]['label']);
    }

    public function test_hosts_can_replace_navigation_and_metadata_without_replacing_theme_views(): void
    {
        app()->instance(NavigationProvider::class, new class implements NavigationProvider
        {
            public function items(string $location, string $locale): array
            {
                return [['label' => $locale.' '.$location, 'url' => '/host']];
            }
        });
        app()->instance(SiteMetadataProvider::class, new class implements SiteMetadataProvider
        {
            public function metadata(string $locale): JsonObject
            {
                return new JsonObject(['name' => 'Host brand']);
            }
        });
        $html = view('site-builder-components::layouts.site', ['locale' => 'de', 'title' => 'Host title', 'slot' => new HtmlString('<p>Host content</p>')])->render();
        foreach (['Host brand', 'de primary', 'de footer', '<p>Host content</p>', 'aria-label="Main navigation"', 'aria-label="Footer navigation"'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_unknown_theme_fails_explicitly_instead_of_selecting_a_stored_view_path(): void
    {
        config(['site-builder-components.theme' => '../../anything']);
        $this->expectException(InvalidArgumentException::class);
        app(ThemeCatalog::class)->preset();
    }

    public function test_compiled_theme_is_scoped_has_no_remote_assets_and_respects_motion_preferences(): void
    {
        $css = file_get_contents(__DIR__.'/../../resources/dist/components.css');
        $this->assertStringContainsString('[data-site-builder-theme]', file_get_contents(__DIR__.'/../../resources/css/components.css'));
        $withoutComments = preg_replace('~/\*.*?\*/~s', '', $css);
        $this->assertDoesNotMatchRegularExpression('/(?:^|})\s*(?::root|html|body|\*)\s*[{,]/m', $withoutComments);
        $this->assertStringNotContainsString('@import', $css);
        $this->assertStringNotContainsString('url(http', $css);
        $this->assertStringContainsString('[data-site-builder-theme]', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertMatchesRegularExpression('/max-width:\s*48rem/', $css);
        $this->assertMatchesRegularExpression('/overflow-wrap:\s*anywhere/', $css);
        foreach ([['#172b36', '#ffffff'], ['#465d69', '#ffffff'], ['#ffffff', '#174e65'], ['#17372f', '#ffffff'], ['#46645b', '#ffffff'], ['#ffffff', '#215442'], ['#302b27', '#fffdf8'], ['#62564c', '#fffdf8'], ['#ffffff', '#783b2b']] as [$foreground, $background]) {
            $luminance = function (string $hex): float {
                $channels = array_map(fn (string $channel): float => hexdec($channel) / 255, str_split(substr($hex, 1), 2));
                $linear = array_map(fn (float $channel): float => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4, $channels);

                return $linear[0] * .2126 + $linear[1] * .7152 + $linear[2] * .0722;
            };
            $levels = [$luminance($foreground), $luminance($background)];
            $this->assertGreaterThanOrEqual(4.5, (max($levels) + .05) / (min($levels) + .05), $foreground.' on '.$background);
        }
    }
}

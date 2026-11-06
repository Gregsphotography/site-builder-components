<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use InvalidArgumentException;
use SiteBuilder\Components\Templates\TemplateCatalog;
use SiteBuilder\Components\Tests\TestCase;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\DocumentCodec;
use SiteBuilder\Core\Content\DocumentValidator;
use SiteBuilder\Core\Rendering\DocumentRenderer;
use SiteBuilder\Core\Rendering\LocaleCatalog;
use SiteBuilder\Core\Rendering\RenderContext;

class TemplatesTest extends TestCase
{
    public function test_template_source_snapshot_is_deterministic_and_independent_from_randomized_instantiations(): void
    {
        $catalog = app(TemplateCatalog::class);
        foreach ($catalog->all() as $template) {
            foreach (['en', 'de'] as $locale) {
                $source = $catalog->snapshot($template->id, $locale);
                $digest = app(CanonicalJson::class)->digest($source);
                $this->assertSame($digest, app(CanonicalJson::class)->digest($catalog->snapshot($template->id, $locale)));
                $copy = $catalog->instantiate($template->id, $locale);
                $second = $catalog->instantiate($template->id, $locale);
                foreach ($source->nodes as $index => $node) {
                    $this->assertNotSame($node->id, $copy->nodes[$index]->id);
                    $this->assertNotSame($copy->nodes[$index]->id, $second->nodes[$index]->id);
                    $this->assertSame(json_encode($node->props), json_encode($copy->nodes[$index]->props));
                    $this->assertSame(json_encode($node->translations), json_encode($copy->nodes[$index]->translations));
                }
                $this->assertSame($digest, app(CanonicalJson::class)->digest($source));
            }
        }
    }

    public function test_every_versioned_template_is_a_valid_independent_copy_with_fresh_node_ids(): void
    {
        $catalog = app(TemplateCatalog::class);
        $this->assertSame(['standard.home', 'standard.about', 'standard.services', 'standard.contact', 'standard.informational'], array_keys($catalog->options('en')));
        $seen = [];
        foreach ($catalog->all() as $template) {
            $this->assertSame(1, $template->version);
            foreach (['en', 'de'] as $locale) {
                $first = $catalog->instantiate($template->id, $locale);
                $second = $catalog->instantiate($template->id, $locale);
                $this->assertSame($template->id, $first->extensions->get('site-builder.template')->id);
                $this->assertSame(1, $first->extensions->get('site-builder.template')->version);
                $this->assertSame([], app(DocumentValidator::class)->validate($first, true, [$locale]));
                $this->assertSame(app(DocumentCodec::class)->encode($first), app(DocumentCodec::class)->encode(app(DocumentCodec::class)->decode(app(DocumentCodec::class)->encode($first))));
                foreach ([...$first->nodes, ...$second->nodes] as $node) {
                    $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $node->id);
                    $this->assertNotContains($node->id, $seen);
                    $this->assertNotNull($node->translations->get($locale));
                    $seen[] = $node->id;
                }
                $html = (string) app(DocumentRenderer::class)->render($first, new RenderContext($locale))->html;
                $this->assertStringContainsString('<h1>', $html);
                $this->assertStringNotContainsString('<form', $html);
                $this->assertStringNotContainsString('src="http', $html);
            }
        }
    }

    public function test_unknown_template_is_an_explicit_error_without_fallback_or_mutation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(TemplateCatalog::class)->instantiate('unknown.template');
    }

    public function test_unconfigured_locale_is_an_explicit_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(TemplateCatalog::class)->instantiate('standard.home', 'unknown');
    }

    public function test_template_requires_available_registered_components(): void
    {
        $catalog = new TemplateCatalog(new ComponentRegistry(app()), app(LocaleCatalog::class));
        $this->expectException(InvalidArgumentException::class);
        $catalog->instantiate('standard.home');
    }
}

<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SiteBuilder\Components\Design\StandardDesignTheme;
use SiteBuilder\Components\Tests\TestCase;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Design\DesignCatalog;
use SiteBuilder\Core\Design\ThemeIdentity;
use SiteBuilder\Core\Rendering\RenderContext;

class DesignThemeTest extends TestCase
{
    private string $designPublicPath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->designPublicPath = sys_get_temp_dir().'/site-builder-design-public-'.Str::uuid();
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

    public function test_every_declared_design_choice_maps_to_compiled_scoped_classes_at_each_breakpoint(): void
    {
        $theme = app(StandardDesignTheme::class);
        $css = file_get_contents(public_path('vendor/site-builder-components/components.css'));
        $this->assertSame(['minimal', 'business', 'editorial'], array_keys($theme->presets()));
        $this->assertSame(['compact' => '48rem+', 'wide' => '72rem+'], $theme->breakpoints());
        foreach (array_keys($theme->presets()) as $preset) {
            $identity = new ThemeIdentity('standard.base', 1, $preset);
            $controls = $theme->controls($identity, 'document');
            $this->assertCount(9, $controls);
            foreach ($controls as $control) {
                foreach (array_keys($control->options) as $option) {
                    foreach (['base', 'compact', 'wide'] as $breakpoint) {
                        $classes = $theme->classes($control->key, $option, $breakpoint);
                        $this->assertSame('sb-design', $classes[0]);
                        $this->assertMatchesRegularExpression('/^sb-d-(?:c-|w-)?[a-z-]+$/D', $classes[1]);
                        $this->assertStringContainsString('.'.$classes[1], $css);
                    }
                }
            }
        }
        $this->assertSame([], $theme->controls(new ThemeIdentity('standard.base', 1, 'business'), 'node', 'host.special'));
        $this->assertFalse($theme->supports('demo.hero', 1, null));
        $this->assertFalse($theme->supports('standard.hero', 2, 'centred'));
        $this->assertFalse($theme->supports('standard.hero', 1, 'forged'));
        $this->expectException(InvalidArgumentException::class);
        $theme->classes('surface', 'url(javascript:bad())', 'base');
    }

    public function test_availability_requires_an_explicit_compatible_layout_and_current_published_mappings(): void
    {
        $theme = app(StandardDesignTheme::class);
        $catalog = app(DesignCatalog::class);
        $this->assertSame($theme, $catalog->definition(new ThemeIdentity('standard.base', 1, 'business')));
        $this->assertSame('business', $catalog->defaultTheme(new RenderContext('en'))?->preset);
        $this->assertSame('editorial', $catalog->defaultTheme(new RenderContext('en', layout: 'components.editorial'))?->preset);
        $this->assertFalse($theme->available(new RenderContext('en', layout: 'components.host')));
        $this->assertFalse($theme->available(new RenderContext('en', layout: 'host.unknown')));
        $this->assertSame($theme, $theme->forLayout('studio', 'editorial'));
        $this->assertSame('editorial', $catalog->defaultTheme(new RenderContext('en', layout: 'studio'))?->preset);
        config(['site-builder-components.assets' => false]);
        $this->assertFalse($theme->available(new RenderContext('en', layout: 'components.business')));
        $theme->forLayout('host.pipeline', 'minimal', fn (): bool => true);
        $this->assertTrue($theme->available(new RenderContext('en', layout: 'host.pipeline')));
        config(['site-builder-components.assets' => true]);
        File::put(public_path('vendor/site-builder-components/components.css'), '/* Stale public mappings */');
        $this->assertFalse($theme->available(new RenderContext('en', layout: 'components.business')));
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
        $this->assertTrue($theme->available(new RenderContext('en', layout: 'components.business')));
    }

    public function test_asset_availability_is_one_context_snapshot_and_new_contexts_recheck_publication(): void
    {
        $theme = app(StandardDesignTheme::class);
        $context = new RenderContext('en', layout: 'components.business');
        $this->assertTrue($theme->available($context));
        File::delete(public_path('vendor/site-builder-components/components.css'));
        for ($index = 0; $index < 1000; $index++) {
            $this->assertTrue($theme->available($context));
        }
        $this->assertFalse($theme->available(new RenderContext('en', layout: 'components.business')));
        $this->artisan('vendor:publish', ['--tag' => 'site-builder-components-assets', '--force' => true])->assertExitCode(0);
        $this->assertTrue($theme->available(new RenderContext('en', layout: 'components.business')));
    }

    public function test_theme_defaults_describe_existing_variants_without_emitting_unrelated_overrides(): void
    {
        $theme = app(StandardDesignTheme::class);
        $identity = new ThemeIdentity('standard.base', 1, 'editorial');
        $context = new RenderContext('en', layout: 'components.editorial');
        $this->assertSame('center', $theme->defaults($identity, new Node('hero', 'standard.hero'), $context)['align']);
        $this->assertSame('narrow', $theme->defaults($identity, new Node('text', 'standard.text', props: new JsonObject(['variant' => 'narrow'])), $context)['width']);
        $this->assertSame('subtle', $theme->defaults($identity, new Node('hero', 'standard.hero', props: new JsonObject(['variant' => 'background'])), $context)['surface']);
        $cta = $theme->defaults($identity, new Node('cta', 'standard.cta'), $context);
        $this->assertSame(['center', 'subtle', 'strong'], [$cta['align'], $cta['surface'], $cta['border']]);
        $this->assertSame([], $theme->combinationErrors($identity, ['surface' => 'accent', 'tone' => 'default'], null));
        $this->assertNotEmpty($theme->combinationErrors($identity, ['surface' => 'accent', 'tone' => 'muted'], null));
        $this->assertNotEmpty($theme->combinationErrors($identity, ['surface' => 'default', 'tone' => 'on-accent'], null));
    }

    public function test_actual_fixed_css_color_mappings_meet_contrast_for_every_offered_surface_and_text_pair(): void
    {
        $theme = app(StandardDesignTheme::class);
        $baseCss = file_get_contents(__DIR__.'/../../resources/css/components.css');
        $designCss = file_get_contents(__DIR__.'/../../resources/css/design.css');
        preg_match('~\[data-site-builder-theme\]\s*\{([^}]+)\}~', $baseCss, $base);
        $defaultTokens = $this->declarations($base[1]);
        $allowed = 0;
        foreach (array_keys($theme->presets()) as $preset) {
            $identity = new ThemeIdentity('standard.base', 1, $preset);
            $variables = $defaultTokens;
            if ($preset !== 'minimal') {
                preg_match('~\[data-site-builder-theme="'.$preset.'"\]\s*\{([^}]+)\}~', $baseCss, $overrides);
                $variables = array_replace($variables, $this->declarations($overrides[1]));
            }
            foreach ($theme->palette($preset) as $token => $hex) {
                $this->assertSame($hex, $this->resolve($variables['--sb-'.$token], $variables));
            }
            foreach (['default', 'subtle', 'accent'] as $surface) {
                foreach (['default', 'muted', 'on-accent'] as $tone) {
                    if ($theme->combinationErrors($identity, compact('surface', 'tone'), null) !== []) {
                        continue;
                    }
                    $allowed++;
                    $surfaceRule = $this->classDeclarations($designCss, $theme->classes('surface', $surface, 'base')[1]);
                    $toneRule = $this->classDeclarations($designCss, $theme->classes('tone', $tone, 'base')[1]);
                    $resolved = array_replace($variables, $surfaceRule, $toneRule);
                    $background = $this->resolve($resolved['background-color'], $resolved);
                    $foreground = $this->resolve($resolved['color'], $resolved);
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($foreground, $background), $preset.' '.$surface.' '.$tone);
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->resolve('var(--sb-d-link)', $resolved), $background));
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->resolve('var(--sb-d-button-ink)', $resolved), $this->resolve('var(--sb-d-button-bg)', $resolved)));
                    $this->assertGreaterThanOrEqual(3.0, $this->contrast($this->resolve('var(--sb-d-focus)', $resolved), $background));
                    foreach (['.sb-eyebrow', 'figcaption'] as $descendant) {
                        preg_match('~\.sb-design '.preg_quote($descendant, '~').'\s*\{([^}]+)\}~', $designCss, $descendantRule);
                        $this->assertNotEmpty($descendantRule, $descendant.' needs a foreground mapping');
                        $descendantColor = $this->resolve($this->declarations($descendantRule[1])['color'], $resolved);
                        $this->assertGreaterThanOrEqual(4.5, $this->contrast($descendantColor, $background), $preset.' '.$surface.' '.$tone.' '.$descendant);
                    }
                    preg_match('~\.sb-design\.sb-cards--logos \.sb-card\s*\{([^}]+)\}~', $designCss, $logoRule);
                    $this->assertNotEmpty($logoRule);
                    $card = $this->declarations($logoRule[1]);
                    $cardBackground = $this->resolve($card['background-color'], $resolved);
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->resolve($card['color'], $resolved), $cardBackground), $preset.' '.$surface.' '.$tone.' logo card text');
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->resolve('var(--sb-d-link)', $resolved), $cardBackground), $preset.' '.$surface.' logo card link');
                }
            }
        }
        $this->assertSame(18, $allowed);
    }

    public function test_responsive_mappings_keep_equal_specificity_logical_alignment_and_no_motion_or_global_styles(): void
    {
        $css = file_get_contents(__DIR__.'/../../resources/css/design.css');
        $this->assertStringContainsString('@media (min-width: 48rem)', $css);
        $this->assertStringContainsString('@media (min-width: 72rem)', $css);
        foreach (['base', 'compact', 'wide'] as $breakpoint) {
            foreach (['start', 'center', 'end'] as $alignment) {
                $class = app(StandardDesignTheme::class)->classes('align', $alignment, $breakpoint)[1];
                $this->assertSame($alignment, $this->classDeclarations($css, $class)['text-align']);
                $this->assertStringContainsString('[data-site-builder-theme] :is([data-site-builder-node], [data-site-builder-render-address]).sb-design.'.$class, $css);
            }
        }
        foreach ([':root', '!important', 'animation:', 'transition:', 'text-align:left', 'text-align:right'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $css);
        }
        $this->assertStringContainsString('margin-inline:auto', $css);
        $this->assertStringContainsString('padding-block:', $css);
        $this->assertStringContainsString('prefers-reduced-motion', file_get_contents(public_path('vendor/site-builder-components/components.css')));
    }

    public function test_surface_and_frame_gutters_follow_the_responsive_cascade_without_changing_alignment_only_geometry(): void
    {
        $css = file_get_contents(__DIR__.'/../../resources/css/design.css');
        $componentCss = file_get_contents(__DIR__.'/../../resources/css/components.css');
        preg_match('~\[data-site-builder-render-address\]\)\.sb-design\s*\{([^}]+)\}~', $css, $common);
        $base = $this->declarations($common[1]);
        $this->assertArrayNotHasKey('padding-inline', $base, 'Design activation alone must preserve existing component geometry.');
        preg_match('~\.sb-cta\s*\{([^}]+)\}~', $componentCss, $cta);
        $ctaInset = $this->declarations($cta[1])['--sb-component-inset'];

        foreach ([390, 768, 820, 1024, 1280] as $width) {
            $gutter = max(16, min($width * .03, 32));
            $componentGutter = max(24, min($width * .05, 64));
            $cases = [
                [['align-start', 'c-align-center', 'w-align-end'], null, null],
                [['align-start', 'c-align-center', 'w-align-end'], $ctaInset, $componentGutter],
                [['surface-accent', 'c-surface-subtle', 'w-surface-default'], null, $gutter],
                [['border-strong', 'c-border-none', 'w-border-subtle'], null, $width < 768 || $width >= 1152 ? $gutter : 0],
                [['surface-accent', 'border-strong', 'c-border-none'], null, $gutter],
                [['border-strong', 'c-border-none'], $ctaInset, $componentGutter],
                [['surface-accent', 'space-none', 'c-space-none', 'w-space-none'], null, $gutter],
            ];
            foreach ($cases as [$classes, $componentInset, $expected]) {
                $values = $base;
                if ($componentInset !== null) {
                    $values['--sb-component-inset'] = $componentInset;
                    $values['padding-inline'] = 'var(--sb-component-inset)';
                }
                $sections = preg_split('~@media \(min-width: (48|72)rem\) \{~', $css);
                foreach ($sections as $index => $section) {
                    if ($width < [0, 768, 1152][$index]) {
                        continue;
                    }
                    preg_match_all('~\[data-site-builder-theme\] :is\(\[data-site-builder-node\], \[data-site-builder-render-address\]\)\.sb-design\.(sb-d-[a-z-]+)\s*\{([^}]+)\}~', $section, $rules, PREG_SET_ORDER);
                    foreach ($rules as $rule) {
                        if (in_array(substr($rule[1], 5), $classes, true)) {
                            $values = array_replace($values, $this->declarations($rule[2]));
                        }
                    }
                }
                if ($expected === null) {
                    $this->assertArrayNotHasKey('padding-inline', $values);
                } else {
                    $this->assertEqualsWithDelta($expected, $this->inlinePixels($values['padding-inline'], $values, $width), .001, $width.'px '.implode(' ', $classes));
                }
                if (in_array('space-none', $classes, true)) {
                    $this->assertSame('0', $values['padding-block'], 'None removes vertical spacing while retaining the explicit surface gutter.');
                }
            }
        }
    }

    private function inlinePixels(string $value, array $variables, int $width): float
    {
        if (str_starts_with($value, 'var(')) {
            [$key, $fallback] = array_pad(explode(',', substr($value, 4, -1), 2), 2, '0px');

            return $this->inlinePixels($variables[trim($key)] ?? trim($fallback), $variables, $width);
        }
        if (str_starts_with($value, 'max(')) {
            preg_match_all('/var\(--[a-z-]+(?:,[^)]*)?\)/', $value, $parts);

            return max(array_map(fn (string $part): float => $this->inlinePixels($part, $variables, $width), $parts[0]));
        }
        if (str_starts_with($value, 'clamp(')) {
            $parts = array_map(fn (string $part): float => $this->inlinePixels(trim($part), $variables, $width), explode(',', substr($value, 6, -1)));

            return max($parts[0], min($parts[1], $parts[2]));
        }
        $this->assertMatchesRegularExpression('/^[0-9.]+(?:px|rem|vw)$/D', $value);

        return (float) $value * (str_ends_with($value, 'rem') ? 16 : (str_ends_with($value, 'vw') ? $width / 100 : 1));
    }

    private function classDeclarations(string $css, string $class): array
    {
        preg_match('~\.sb-design\.('.preg_quote($class, '~').')\s*\{([^}]+)\}~', $css, $match);
        $this->assertNotEmpty($match, $class);

        return $this->declarations($match[2]);
    }

    private function declarations(string $css): array
    {
        $result = [];
        foreach (explode(';', $css) as $declaration) {
            if (str_contains($declaration, ':')) {
                [$key, $value] = explode(':', $declaration, 2);
                $result[trim($key)] = trim($value);
            }
        }

        return $result;
    }

    private function resolve(string $value, array $variables, int $depth = 0): string
    {
        $this->assertLessThan(12, $depth, 'Semantic CSS variables must resolve without cycles.');
        if (str_starts_with($value, 'var(')) {
            [$key, $fallback] = array_pad(explode(',', substr($value, 4, -1), 2), 2, null);

            return $this->resolve($variables[trim($key)] ?? trim($fallback ?? ''), $variables, $depth + 1);
        }

        return $value;
    }

    private function contrast(string $foreground, string $background): float
    {
        $luminance = function (string $hex): float {
            $this->assertMatchesRegularExpression('/^#[a-f0-9]{6}$/D', $hex);
            $channels = array_map(fn (string $channel): float => hexdec($channel) / 255, str_split(substr($hex, 1), 2));
            $linear = array_map(fn (float $channel): float => $channel <= .04045 ? $channel / 12.92 : (($channel + .055) / 1.055) ** 2.4, $channels);

            return $linear[0] * .2126 + $linear[1] * .7152 + $linear[2] * .0722;
        };
        $levels = [$luminance($foreground), $luminance($background)];

        return (max($levels) + .05) / (min($levels) + .05);
    }
}

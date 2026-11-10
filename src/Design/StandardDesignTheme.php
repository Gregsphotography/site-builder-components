<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Design;

use Closure;
use InvalidArgumentException;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Contracts\DesignTheme;
use SiteBuilder\Core\Design\DesignControl;
use SiteBuilder\Core\Design\ThemeIdentity;
use SiteBuilder\Core\Rendering\RenderContext;
use WeakMap;

/** Fixed code-owned mappings. A document never supplies CSS, a class name or a view. */
final class StandardDesignTheme implements DesignTheme
{
    private const COMPONENTS = [
        'standard.form' => ['default'],
        'standard.content-listing' => ['cards', 'images', 'list'], 'standard.search-box' => ['default'], 'standard.search-results' => ['cards', 'images', 'list'],
        'standard.carousel' => ['images', 'logos', 'cards', 'testimonials', 'team'], 'standard.timeline' => ['vertical', 'alternating'], 'standard.image-comparison' => ['default'], 'standard.image-hotspots' => ['default'],
        'standard.video' => ['default'], 'standard.location' => ['default'],
        'standard.tabs' => ['default'], 'standard.tab-panel' => ['default'], 'standard.accordion' => ['default'], 'standard.accordion-item' => ['default'],
        'standard.table' => ['default'], 'standard.table-of-contents' => ['default'], 'standard.breadcrumbs' => ['default'],
        'standard.heading' => ['default'], 'standard.buttons' => ['default'], 'standard.icon' => ['default'],
        'standard.icon-list' => ['default'], 'standard.social-links' => ['default'], 'standard.divider' => ['line', 'dots'], 'standard.anchor' => ['default'],
        'standard.hero' => ['centred', 'split', 'background'],
        'standard.text' => ['default', 'narrow'],
        'standard.image' => ['default', 'wide'],
        'standard.image-text' => ['image-left', 'image-right'],
        'standard.cards' => ['cards', 'logos'],
        'standard.cta' => ['brand', 'neutral'],
        'standard.faq' => ['default'],
        'standard.contact-details' => ['default'],
        'standard.brand' => ['text', 'logo', 'both'],
        'standard.navigation' => ['default'], 'standard.gallery' => ['default'], 'standard.testimonials' => ['single', 'grid'],
        'standard.section' => ['default'],
        'standard.container' => ['default'],
        'standard.columns' => ['equal', 'sidebar-start', 'sidebar-end'],
        'standard.column' => ['default'],
    ];

    private const OPTIONS = [
        'role' => ['theme' => 'Theme typography', 'body' => 'Body family', 'heading' => 'Heading family'],
        'scale' => ['small' => 'Small', 'normal' => 'Normal', 'large' => 'Large'],
        'align' => ['start' => 'Start', 'center' => 'Center', 'end' => 'End'],
        'space' => ['none' => 'None', 'xs' => 'Extra small', 'sm' => 'Small', 'md' => 'Theme spacing', 'lg' => 'Large', 'xl' => 'Extra large'],
        'width' => ['narrow' => 'Narrow', 'content' => 'Content', 'wide' => 'Wide', 'full' => 'Full available width'],
        'surface' => ['default' => 'Default surface', 'subtle' => 'Subtle surface', 'accent' => 'Accent surface'],
        'tone' => ['default' => 'Readable default', 'muted' => 'Muted', 'on-accent' => 'On accent'],
        'border' => ['none' => 'None', 'subtle' => 'Subtle', 'strong' => 'Strong'],
        'radius' => ['none' => 'None', 'small' => 'Small', 'medium' => 'Theme radius', 'large' => 'Large'],
    ];

    private const LABELS = ['role' => 'Typography family', 'scale' => 'Text scale', 'align' => 'Text alignment', 'space' => 'Vertical section spacing', 'width' => 'Section width', 'surface' => 'Surface', 'tone' => 'Text treatment', 'border' => 'Section frame', 'radius' => 'Corner radius'];

    /** @var array<string, array{preset:string,assets:?Closure}> */
    private array $hostLayouts = [];

    /** @var WeakMap<RenderContext, bool> Request/render snapshots, never a cross-request asset cache. */
    private WeakMap $availability;

    public function __construct()
    {
        $this->availability = new WeakMap;
    }

    public function id(): string
    {
        return 'standard.base';
    }

    public function version(): int
    {
        return 1;
    }

    public function label(): string
    {
        return 'Standard base';
    }

    public static function componentVariants(): array
    {
        return self::COMPONENTS;
    }

    public function presets(): array
    {
        return ['minimal' => 'Minimal', 'business' => 'Business', 'editorial' => 'Editorial'];
    }

    public function breakpoints(): array
    {
        return ['compact' => '48rem+', 'wide' => '72rem+'];
    }

    /** Declare that trusted host code loads these mappings in a custom wrapper/pipeline. */
    public function forLayout(string $key, string $preset = 'minimal', ?Closure $assetsAvailable = null): self
    {
        if ($key === '' || strlen($key) > 255 || preg_match('/[\x00-\x1f\x7f]/', $key) || ! array_key_exists($preset, $this->presets())) {
            throw new InvalidArgumentException('A standard design adapter requires an explicit layout and supported preset.');
        }
        $this->hostLayouts[$key] = ['preset' => $preset, 'assets' => $assetsAvailable];
        $this->availability = new WeakMap;

        return $this;
    }

    public function defaultTheme(RenderContext $context): ?ThemeIdentity
    {
        $preset = $this->layout($context)['preset'] ?? null;

        return $preset !== null && $this->available($context) ? new ThemeIdentity($this->id(), $this->version(), $preset) : null;
    }

    public function controls(ThemeIdentity $theme, string $scope, ?string $componentType = null): array
    {
        if (! $this->identity($theme) || ! in_array($scope, ['page', 'document', 'node'], true) || ($scope === 'node' && ! isset(self::COMPONENTS[$componentType ?? '']))) {
            return [];
        }

        return array_combine(array_keys(self::OPTIONS), array_map(fn (string $key): DesignControl => new DesignControl($key, self::LABELS[$key], self::OPTIONS[$key]), array_keys(self::OPTIONS)));
    }

    public function supports(string $type, int $version, ?string $variant): bool
    {
        return $version === 1 && isset(self::COMPONENTS[$type]) && ($variant === null || in_array($variant, self::COMPONENTS[$type], true));
    }

    public function defaults(ThemeIdentity $theme, ?Node $node, RenderContext $context): array
    {
        $values = ['role' => 'theme', 'scale' => 'normal', 'align' => 'start', 'space' => 'md', 'width' => 'content', 'surface' => 'default', 'tone' => 'default', 'border' => 'none', 'radius' => 'medium'];
        $variant = $node?->props->get('variant');
        if ($node?->type === 'standard.hero' && ($variant ?? 'centred') === 'centred') {
            $values['align'] = 'center';
        }
        if ($node?->type === 'standard.hero' && $variant === 'background') {
            $values['surface'] = 'subtle';
        }
        if ($node?->type === 'standard.text' && $variant === 'narrow') {
            $values['width'] = 'narrow';
        }
        if ($node?->type === 'standard.image' && $variant === 'wide') {
            $values['width'] = 'full';
        }
        if ($node?->type === 'standard.cta') {
            $values['align'] = 'center';
            $values['surface'] = 'subtle';
            $values['border'] = ($variant ?? 'brand') === 'brand' ? 'strong' : 'none';
        }
        if ($node?->type === 'standard.column') {
            $values['space'] = 'none';
            $values['width'] = 'full';
        }

        return $values;
    }

    public function classes(string $key, string $option, string $breakpoint): array|string
    {
        if (! isset(self::OPTIONS[$key][$option]) || ! in_array($breakpoint, ['base', 'compact', 'wide'], true)) {
            throw new InvalidArgumentException('Only registered standard design choices have class mappings.');
        }
        $prefix = match ($breakpoint) {
            'base' => 'sb-d-', 'compact' => 'sb-d-c-', 'wide' => 'sb-d-w-'
        };

        return ['sb-design', $prefix.$key.'-'.$option];
    }

    public function canvasAttributes(ThemeIdentity $theme): array
    {
        if (! $this->identity($theme)) {
            throw new InvalidArgumentException('The document design theme is unavailable.');
        }

        return ['data-site-builder-theme' => $theme->preset, 'data-site-builder-theme-version' => '1', 'data-site-builder-design-theme' => $this->id(), 'class' => 'sb-design-canvas'];
    }

    public function available(RenderContext $context): bool
    {
        if (isset($this->availability[$context])) {
            return $this->availability[$context];
        }
        $layout = $this->layout($context);
        if ($layout === null) {
            return $this->availability[$context] = false;
        }
        if ($layout['assets'] !== null) {
            return $this->availability[$context] = (bool) ($layout['assets'])($context);
        }

        return $this->availability[$context] = $this->publishedMappingsAvailable();
    }

    public function combinationErrors(ThemeIdentity $theme, array $values, ?Node $node): array
    {
        $surface = $values['surface'] ?? 'default';
        $tone = $values['tone'] ?? 'default';
        if (($surface === 'accent' && $tone === 'muted') || ($surface !== 'accent' && $tone === 'on-accent')) {
            return ['Choose a readable text treatment for the selected surface. Accent uses readable default or on-accent text; other surfaces use readable default or muted text.'];
        }

        return [];
    }

    /** Actual preset RGB values used by the scoped base stylesheet and contrast checks. */
    public function palette(string $preset): array
    {
        $base = ['text' => '#172b36', 'muted' => '#465d69', 'surface' => '#ffffff', 'subtle' => '#f2f6f8', 'accent' => '#174e65', 'accent-text' => '#ffffff', 'border' => '#c9d6dd', 'focus' => '#8c3c00'];

        return match ($preset) {
            'minimal' => $base,
            'business' => [...$base, 'text' => '#17372f', 'muted' => '#46645b', 'subtle' => '#edf3ed', 'accent' => '#215442', 'border' => '#c6d7cc', 'focus' => '#8b3c00'],
            'editorial' => [...$base, 'text' => '#302b27', 'muted' => '#62564c', 'surface' => '#fffdf8', 'subtle' => '#f2ede3', 'accent' => '#783b2b', 'border' => '#d9cfc1', 'focus' => '#005976'],
            default => throw new InvalidArgumentException('Unknown standard design palette.'),
        };
    }

    private function identity(ThemeIdentity $theme): bool
    {
        return $theme->id === $this->id() && $theme->version === $this->version() && array_key_exists($theme->preset, $this->presets());
    }

    private function layout(RenderContext $context): ?array
    {
        $key = $context->layout ?? config('site-builder.layout');
        if (isset($this->hostLayouts[$key ?? ''])) {
            return $this->hostLayouts[$key];
        }
        if (! config('site-builder-components.assets', true)) {
            return null;
        }
        $presets = ['components.minimal' => 'minimal', 'components.business' => 'business', 'components.editorial' => 'editorial', 'site-builder-components::layouts.minimal' => 'minimal', 'site-builder-components::layouts.business' => 'business', 'site-builder-components::layouts.editorial' => 'editorial'];
        if (in_array($key, ['components.default', 'site-builder-components::layouts.site'], true)) {
            $presets[$key] = config('site-builder-components.theme', 'minimal');
        }
        $preset = $presets[$key ?? ''] ?? null;

        return is_string($preset) && array_key_exists($preset, $this->presets()) ? ['preset' => $preset, 'assets' => null] : null;
    }

    private function publishedMappingsAvailable(): bool
    {
        $package = __DIR__.'/../../resources/dist/components.css';
        $published = public_path('vendor/site-builder-components/components.css');
        $packageDigest = is_file($package) && is_readable($package) ? @hash_file('sha256', $package) : false;
        $publishedDigest = is_file($published) && is_readable($published) ? @hash_file('sha256', $published) : false;
        if (! is_string($packageDigest) || ! is_string($publishedDigest) || ! hash_equals($packageDigest, $publishedDigest)) {
            return false;
        }
        $css = file_get_contents($published);
        foreach (self::OPTIONS as $key => $options) {
            foreach (array_keys($options) as $option) {
                foreach (['base', 'compact', 'wide'] as $breakpoint) {
                    if (! str_contains($css, '.'.$this->classes($key, $option, $breakpoint)[1])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Themes;

use InvalidArgumentException;
use SiteBuilder\Components\Design\StandardDesignTheme;

final class ThemeCatalog
{
    /** Trusted, code-owned theme contract. Content never selects arbitrary views or CSS. */
    public function descriptor(): array
    {
        return [
            'id' => 'standard.base', 'version' => 1,
            'presets' => ['minimal', 'business', 'editorial', 'host'],
            'layouts' => ['default' => 'components.default', 'minimal' => 'components.minimal', 'business' => 'components.business', 'editorial' => 'components.editorial', 'host' => 'components.host'],
            'components' => array_fill_keys(array_keys(StandardDesignTheme::componentVariants()), 1),
            'breakpoints' => ['compact' => '48rem', 'wide' => '72rem'],
            'tokens' => ['text', 'muted', 'surface', 'subtle', 'accent', 'accent-text', 'border', 'focus', 'radius', 'width', 'section-space', 'font-body', 'font-heading'],
            'token_defaults' => ['text' => '#172b36', 'muted' => '#465d69', 'surface' => '#ffffff', 'subtle' => '#f2f6f8', 'accent' => '#174e65', 'accent-text' => '#ffffff', 'border' => '#c9d6dd', 'focus' => '#8c3c00', 'radius' => '.6rem', 'width' => '72rem', 'section-space' => 'clamp(2.5rem, 7vw, 5.5rem)', 'font-body' => 'system-ui', 'font-heading' => 'system-ui'],
            'variants' => StandardDesignTheme::componentVariants(),
            'assets' => ['vendor/site-builder-components/components.css'],
        ];
    }

    public function preset(?string $preset = null): string
    {
        $preset ??= config('site-builder-components.theme', 'minimal');
        if (! in_array($preset, $this->descriptor()['presets'], true)) {
            throw new InvalidArgumentException('Unknown standard theme preset ['.$preset.']. Restore a supported preset in host configuration.');
        }

        return $preset;
    }

    public function assetUrl(): string
    {
        $version = substr(hash_file('sha256', __DIR__.'/../../resources/dist/components.css'), 0, 16);

        return asset('vendor/site-builder-components/components.css').'?v='.$version;
    }
}

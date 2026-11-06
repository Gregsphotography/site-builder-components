<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Themes;

use InvalidArgumentException;

final class ThemeCatalog
{
    /** Trusted, code-owned theme contract. Content never selects arbitrary views or CSS. */
    public function descriptor(): array
    {
        return [
            'id' => 'standard.base', 'version' => 1,
            'presets' => ['minimal', 'business', 'editorial', 'host'],
            'layouts' => ['default' => 'components.default', 'minimal' => 'components.minimal', 'business' => 'components.business', 'editorial' => 'components.editorial', 'host' => 'components.host'],
            'components' => ['standard.hero' => 1, 'standard.text' => 1, 'standard.image' => 1, 'standard.image-text' => 1, 'standard.cards' => 1, 'standard.cta' => 1, 'standard.faq' => 1, 'standard.contact-details' => 1, 'standard.brand' => 1, 'standard.navigation' => 1, 'standard.gallery' => 1, 'standard.testimonials' => 1],
            'breakpoints' => ['compact' => '48rem', 'wide' => '72rem'],
            'tokens' => ['text', 'muted', 'surface', 'subtle', 'accent', 'accent-text', 'border', 'focus', 'radius', 'width', 'section-space', 'font-body', 'font-heading'],
            'token_defaults' => ['text' => '#172b36', 'muted' => '#465d69', 'surface' => '#ffffff', 'subtle' => '#f2f6f8', 'accent' => '#174e65', 'accent-text' => '#ffffff', 'border' => '#c9d6dd', 'focus' => '#8c3c00', 'radius' => '.6rem', 'width' => '72rem', 'section-space' => 'clamp(2.5rem, 7vw, 5.5rem)', 'font-body' => 'system-ui', 'font-heading' => 'system-ui'],
            'variants' => ['standard.hero' => ['centred', 'split', 'background'], 'standard.text' => ['default', 'narrow'], 'standard.image' => ['default', 'wide'], 'standard.image-text' => ['image-left', 'image-right'], 'standard.cards' => ['cards', 'logos'], 'standard.cta' => ['brand', 'neutral'], 'standard.faq' => ['default'], 'standard.contact-details' => ['default'], 'standard.brand' => ['text', 'logo', 'both'], 'standard.navigation' => ['default'], 'standard.gallery' => ['default'], 'standard.testimonials' => ['single', 'grid']],
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

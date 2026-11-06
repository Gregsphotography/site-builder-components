<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Images;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Brand extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.brand';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.brand';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'both', 'width' => null, 'height' => null], translations: ['en' => ['name' => 'Your site', 'logo' => null, 'alt' => '', 'url' => '/', 'page' => null]]);
    }

    public function properties(): array
    {
        return [
            'variant' => PropertyDefinition::enum(['text', 'logo', 'both']),
            'width' => Images::dimensions(), 'height' => Images::dimensions(),
            'name' => PropertyDefinition::text(translated: true, rules: ['max:255']),
            'logo' => new PropertyDefinition('media', translated: true, nullable: true, rules: ['max:2048']),
            'alt' => PropertyDefinition::text(translated: true, rules: ['max:2000']),
            'url' => new PropertyDefinition('url', translated: true, nullable: true, rules: ['max:2048']),
            'page' => new PropertyDefinition('page', translated: true, nullable: true, rules: ['max:9007199254740991']),
        ];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        $errors = [];
        if ($publishing && (! is_string($values->get('name')) || trim($values->get('name')) === '')) {
            $errors[] = new Diagnostic('required_for_publish', '/name', 'Give the brand an accessible site name.');
        }
        if ($publishing && $values->get('variant') === 'logo' && (! is_string($values->get('logo')) || trim($values->get('logo')) === '')) {
            $errors[] = new Diagnostic('required_for_publish', '/logo', 'Choose a logo or use the text/both presentation.');
        }

        return $errors;
    }
}

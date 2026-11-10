<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\PropertyValues;

final class ImageComparison extends LeafDefinition
{
    public function type(): string
    {
        return 'standard.image-comparison';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.image-comparison';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['initial' => 50, 'ratio' => 'landscape'], translations: ['en' => ['heading' => 'Compare the details', 'label' => 'Image comparison', 'before_label' => 'Before', 'after_label' => 'After']]);
    }

    public function properties(): array
    {
        return ['before_image' => new P('media', requiredForPublish: true), 'after_image' => new P('media', requiredForPublish: true), 'initial' => new P('number', rules: ['integer', 'min:0', 'max:100']), 'ratio' => P::enum(['landscape', 'square', 'portrait']), 'heading' => P::text(translated: true), 'label' => P::text(translated: true, requiredForPublish: true), 'before_label' => P::text(translated: true, requiredForPublish: true), 'after_label' => P::text(translated: true, requiredForPublish: true), 'before_alt' => P::text(translated: true, requiredForPublish: true), 'after_alt' => P::text(translated: true, requiredForPublish: true)];
    }
}

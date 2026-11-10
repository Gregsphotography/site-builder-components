<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Icons;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Icon extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.icon';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.icon';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['icon' => 'sparkles', 'decorative' => true], translations: ['en' => ['label' => '']]);
    }

    public function properties(): array
    {
        return ['icon' => P::enum(Icons::GENERAL), 'decorative' => new P('boolean'), 'label' => P::text(translated: true, rules: ['max:255'])];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        return $publishing && $values->get('decorative') === false && ! filled($values->get('label')) ? [new Diagnostic('required_for_publish', '/label', 'Give this informative icon a label or mark it decorative.')] : [];
    }
}

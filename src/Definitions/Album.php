<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Albums\AlbumLibrary;
use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Album extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.album';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.album';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues;
    }

    public function properties(): array
    {
        return ['album' => PropertyDefinition::text(rules: ['uuid'], nullable: true, requiredForPublish: true)];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        $id = $values->get('album');
        if ($publishing && is_string($id) && app(AlbumLibrary::class)->publishedById($id) === null) {
            return [new Diagnostic('unavailable_album', '/album', 'Choose a published album.')];
        }

        return [];
    }
}

<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;

final class CollectionFilters
{
    public static function properties(): array
    {
        return ['searchable' => new P('boolean', nullable: true), 'filterable' => new P('boolean', nullable: true), 'search_label' => P::text(translated: true), 'filter_label' => P::text(translated: true), 'all_label' => P::text(translated: true), 'reset_label' => P::text(translated: true), 'empty_message' => P::text(translated: true), 'result_label' => P::text(translated: true)];
    }

    public static function tags(): P
    {
        return new P('list', rules: ['max:16'], items: P::object(['label' => P::text(rules: ['max:80'], requiredForPublish: true)]));
    }
}

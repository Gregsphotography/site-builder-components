<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use SiteBuilder\Core\Contracts\NavigationProvider;
use SiteBuilder\Core\Models\Menu;

/** Neutral core menus use an explicit locale-keyed list; hosts may replace this adapter. */
final class StoredNavigation implements NavigationProvider
{
    public function items(string $location, string $locale): array
    {
        $menu = Menu::query()->where('location', $location)->first();
        $items = data_get($menu?->items, $locale, []);

        return is_array($items) ? $items : [];
    }
}

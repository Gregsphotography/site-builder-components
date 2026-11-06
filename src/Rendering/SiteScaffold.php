<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use SiteBuilder\Core\Contracts\MediaResolver;
use SiteBuilder\Core\Contracts\NavigationProvider;
use SiteBuilder\Core\Contracts\SiteMetadataProvider;
use SiteBuilder\Core\Rendering\SafeUrl;

final class SiteScaffold
{
    public function __construct(private NavigationProvider $navigation, private SiteMetadataProvider $metadata, private MediaResolver $media, private SafeUrl $safe) {}

    public function data(string $locale): array
    {
        $site = $this->metadata->metadata($locale);
        $logo = $site->get('logo');
        $logo = is_string($logo) ? $this->media->url($logo) : null;
        $name = is_string($site->get('name')) ? $site->get('name') : (string) config('app.name');

        return [
            'name' => $name,
            'description' => is_string($site->get('description')) ? $site->get('description') : '',
            'footer' => is_string($site->get('footer')) ? $site->get('footer') : $name,
            'logo' => is_string($logo) && $this->safe->valid($logo, true) ? $logo : null,
            'header' => $this->items($this->navigation->items(config('site-builder-components.navigation.header', 'primary'), $locale)),
            'footer_links' => $this->items($this->navigation->items(config('site-builder-components.navigation.footer', 'footer'), $locale)),
        ];
    }

    private function items(array $items, int $depth = 0): array
    {
        if ($depth > 2) {
            return [];
        }
        $result = [];
        foreach ($items as $item) {
            $label = data_get($item, 'label');
            $url = data_get($item, 'url');
            if (is_string($label) && $label !== '' && is_string($url) && $url !== '' && $this->safe->valid($url)) {
                $result[] = ['label' => $label, 'url' => $url, 'children' => $this->items((array) data_get($item, 'children', []), $depth + 1)];
            }
        }

        return $result;
    }
}

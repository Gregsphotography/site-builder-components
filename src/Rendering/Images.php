<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Rendering\SafeUrl;

final class Images
{
    public function __construct(private SafeUrl $safe) {}

    public static function dimensions(): PropertyDefinition
    {
        return new PropertyDefinition('number', nullable: true, rules: ['integer', 'min:1', 'max:20000']);
    }

    public static function sources(): PropertyDefinition
    {
        return PropertyDefinition::list(PropertyDefinition::object(['image' => PropertyDefinition::media(), 'width' => self::dimensions()]));
    }

    public function srcset(?array $sources): ?string
    {
        $result = [];
        foreach ($sources ?? [] as $source) {
            $image = data_get($source, 'image');
            $width = data_get($source, 'width');
            if (is_string($image) && $image !== '' && ! str_contains($image, ',') && $this->safe->valid($image, true) && is_int($width) && $width > 0 && $width <= 20000) {
                $result[$width] = $image.' '.$width.'w';
            }
        }
        ksort($result, SORT_NUMERIC);

        return $result === [] ? null : implode(', ', $result);
    }
}

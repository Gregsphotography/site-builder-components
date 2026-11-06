<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Rendering\Images;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Gallery extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.gallery';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.gallery';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['columns' => 'three', 'lightbox' => false], translations: ['en' => ['heading' => '', 'label' => 'Image gallery', 'items' => []]]);
    }

    public function properties(): array
    {
        return ['columns' => P::enum(['one', 'two', 'three', 'four']), 'lightbox' => new P('boolean'),
            'heading' => P::text(translated: true, rules: ['max:255']), 'label' => P::text(translated: true, rules: ['max:255']),
            'items' => new P('list', translated: true, rules: ['max:48'], items: P::object([
                'image' => new P('media', nullable: true, rules: ['max:2048']), 'full_image' => new P('media', nullable: true, rules: ['max:2048']),
                'sources' => self::sources(), 'width' => Images::dimensions(), 'height' => Images::dimensions(),
                'alt' => P::text(rules: ['max:2000']), 'decorative' => new P('boolean'), 'caption' => P::text(rules: ['max:2000']),
                'url' => new P('url', nullable: true, rules: ['max:2048']), 'page' => new P('page', nullable: true, rules: ['max:9007199254740991']), 'link_label' => P::text(rules: ['max:255']),
            ]))];
    }

    public static function sources(): P
    {
        return new P('list', rules: ['max:8'], items: P::object(['image' => new P('media', nullable: true, rules: ['max:2048']), 'width' => Images::dimensions()]));
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        if (! $publishing || ! is_array($values->get('items'))) {
            return [];
        }
        $errors = [];
        if ($values->get('items') !== [] && ! self::filled($values->get('label'))) {
            $errors[] = new Diagnostic('required_for_publish', '/label', 'Give this gallery an accessible label.');
        }
        foreach ($values->get('items') as $i => $item) {
            if (! $item instanceof \stdClass) {
                continue;
            }
            foreach (['image' => 'Choose an image.', ...(($item->decorative ?? false) ? [] : ['alt' => 'Describe the image or mark it decorative.']), ...(($item->page ?? null) !== null || self::filled($item->url ?? null) ? ['link_label' => 'Label the separate destination link.'] : [])] as $field => $message) {
                if (! self::filled($item->{$field} ?? null)) {
                    $errors[] = new Diagnostic('required_for_publish', '/items/'.$i.'/'.$field, $message);
                }
            }
        }

        return $errors;
    }

    public static function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}

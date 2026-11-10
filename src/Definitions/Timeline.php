<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Timeline extends LeafDefinition implements ValidatesComponentValues
{
    use QueriesContent { diagnostics as private sourceDiagnostics; }

    public function type(): string
    {
        return 'standard.timeline';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.timeline';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['content_mode' => 'manual', 'source' => 'builder.pages', 'sort' => 'oldest', 'limit' => 12, 'variant' => 'vertical', 'show_image' => true, 'show_excerpt' => true, 'show_date' => true], translations: ['en' => ['heading' => 'How the work unfolds', 'label' => 'Our process', 'items' => [['label' => 'First', 'title' => 'Listen and understand', 'body' => '<p>Agree what matters and where to begin.</p>'], ['label' => 'Next', 'title' => 'Make something useful', 'body' => '<p>Build, review and refine the idea together.</p>'], ['label' => 'Then', 'title' => 'Keep improving', 'body' => '<p>Learn from the results and take the next step.</p>']]]]);
    }

    public function properties(): array
    {
        return [...$this->queryProperties(), 'content_mode' => P::enum(['manual', 'dynamic']), 'variant' => P::enum(['vertical', 'alternating']), 'heading' => P::text(translated: true), 'label' => P::text(translated: true, requiredForPublish: true), 'items' => new P('list', translated: true, rules: ['max:100'], items: P::object(['label' => P::text(rules: ['max:255']), 'date' => P::text(nullable: true, rules: ['date_format:Y-m-d']), 'title' => P::text(rules: ['max:255']), 'body' => P::html(), 'image' => P::media(), 'alt' => P::text(rules: ['max:2000']), 'url' => P::url(), 'page' => P::page(), 'link_label' => P::text(rules: ['max:255'])]))];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        $errors = $this->sourceDiagnostics($values, $context, $publishing);
        if ($publishing && $values->get('content_mode', 'manual') !== 'dynamic') {
            foreach ($values->get('items', []) ?? [] as $index => $item) {
                if ($item instanceof \stdClass && (! filled($item->title ?? null) || (filled($item->image ?? null) && ! filled($item->alt ?? null)) || ((filled($item->url ?? null) || ($item->page ?? null) !== null) && ! filled($item->link_label ?? null)))) {
                    $errors[] = new Diagnostic('required_for_publish', '/items/'.$index, 'Give each event a title, image description and destination label where applicable.');
                }
            }
        }

        return $errors;
    }
}

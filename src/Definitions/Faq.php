<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ContributesStructuredData;
use SiteBuilder\Core\Rendering\RenderContext;
use SiteBuilder\Core\Rendering\ResolvedNode;

final class Faq extends LeafDefinition implements ContributesStructuredData
{
    public function type(): string
    {
        return 'standard.faq';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.faq';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['variant' => 'default'], translations: ['en' => ['heading' => 'Frequently asked questions', 'items' => [['question' => 'Where should I start?', 'answer' => '<p>Get in touch and tell us what you would like to achieve.</p>']]]]);
    }

    public function properties(): array
    {
        return [
            'variant' => PropertyDefinition::enum(['default']),
            'heading' => PropertyDefinition::text(translated: true),
            'items' => PropertyDefinition::list(PropertyDefinition::object(['question' => PropertyDefinition::text(), 'answer' => PropertyDefinition::html()]), translated: true),
        ];
    }

    public function structuredData(ResolvedNode $node, RenderContext $context): array
    {
        $items = [];
        foreach ($node->props->get('items', []) ?? [] as $item) {
            $question = trim(strip_tags($item->question ?? ''));
            $answer = trim(strip_tags($item->answer ?? ''));
            if ($question !== '' && $answer !== '') {
                $items[] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
            }
        }

        return $items === [] ? [] : [['@type' => 'FAQPage', 'inLanguage' => $context->locale, 'mainEntity' => $items]];
    }
}

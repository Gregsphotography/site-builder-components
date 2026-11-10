<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Templates;

use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Core\Composition\StartingPointDescriptor;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;

final class PracticalPatterns
{
    public function __construct(private StandardExamples $examples) {}

    public function all(): array
    {
        $entries = ['features' => ['Features and benefits', 'A concise list of useful benefits with icons.'], 'services' => ['Service cards', 'Clear service choices with icons and useful next steps.'], 'contact-strip' => ['Contact strip', 'A compact invitation with telephone and email links.'], 'price-list' => ['Service price list', 'An illustrative service menu with readable table headings.'], 'comparison' => ['Comparison table', 'Compare example service levels in a structured table.'], 'link-in-bio' => ['Link-in-bio page', 'An editable introduction and a short set of useful links.'], 'filtered-services' => ['Searchable service cards', 'Independent text search and category filtering on service cards.'], 'portfolio-gallery' => ['Filterable portfolio gallery', 'An image gallery with local search, categories and lightbox.']];

        return array_map(fn ($key, $value): StartingPointDescriptor => new StartingPointDescriptor('pattern:standard.'.$key, 1, $value[0], 'Practical patterns', $value[1], explode('-', $key), sourceKind: 'code-template'), array_keys($entries), array_values($entries));
    }

    public function snapshot(string $id, string $locale): Document
    {
        $kind = substr($id, strlen('pattern:standard.'));
        if (! in_array($id, array_map(fn ($entry) => $entry->id, $this->all()), true)) {
            throw new \InvalidArgumentException('Unknown practical pattern.');
        }
        $make = fn ($type, $props = [], $values = [], $children = []) => new Node('source', 'standard.'.$type, props: new JsonObject($props), translations: new JsonObject([$locale => (object) $values]), children: $children);
        if (in_array($kind, ['services', 'filtered-services', 'portfolio-gallery'], true)) {
            $type = $kind === 'portfolio-gallery' ? 'standard.gallery' : 'standard.cards';
            $source = $this->examples->document($type, $locale, $type === 'standard.gallery' ? 'lightbox' : 'cards')->nodes[0];
            $props = (array) $source->props->toObject();
            $values = (array) $source->translations->get($locale);
            if ($kind !== 'services') {
                $props = [...$props, 'searchable' => true, 'filterable' => true];
            }
            foreach ($values['items'] as $index => $item) {
                $item->tags = [(object) ['label' => $index === 1 ? 'Workspaces' : 'Ideas']];
                if ($kind !== 'portfolio-gallery') {
                    $item->icon = ['light-bulb', 'sparkles', 'arrow-right'][$index % 3];
                }
            }
            $body = [new Node('collection', $type, props: new JsonObject($props), translations: new JsonObject([$locale => (object) $values]))];
        } elseif (in_array($kind, ['price-list', 'comparison'], true)) {
            $columns = $kind === 'price-list' ? ['Service', 'Description', 'Illustrative price'] : ['Included', 'Start', 'Partner'];
            $rows = $kind === 'price-list' ? [['First conversation', 'A focused hour to understand your next step', 'CHF 90'], ['Working session', 'Practical support and a written action plan', 'CHF 240']] : [['Initial conversation', 'Included', 'Included'], ['Working sessions', 'One', 'Four'], ['Written follow-up', 'Summary', 'Detailed plan']];
            $body = [$make('table', ['row_headers' => true], ['caption' => $kind === 'price-list' ? 'Illustrative services — editable example, not an offer' : 'Example service comparison', 'columns' => array_map(fn ($label) => (object) ['label' => $label], $columns), 'rows' => array_map(fn ($cells) => (object) ['cells' => array_map(fn ($text) => (object) ['body' => '<p>'.e($text).'</p>'], $cells)], $rows)])];
        } elseif ($kind === 'features') {
            $body = [$make('icon-list', [], ['heading' => 'Useful help, clearly explained', 'items' => [(object) ['icon' => 'light-bulb', 'label' => 'Understand the next step'], (object) ['icon' => 'user-group', 'label' => 'Work with people who listen'], (object) ['icon' => 'check-circle', 'label' => 'Leave with a practical plan']]])];
        } elseif ($kind === 'contact-strip') {
            $body = [$make('heading', ['level' => 'h2'], ['text' => 'Let’s start a conversation']), $make('icon-list', [], ['heading' => '', 'items' => [(object) ['icon' => 'envelope', 'label' => 'Email the example studio', 'url' => 'mailto:hello@example.test'], (object) ['icon' => 'phone', 'label' => 'Call the example studio', 'url' => 'tel:+41000000000']]])];
        } else {
            $body = [$make('heading', ['level' => 'h1'], ['text' => 'Hello, I’m Avery']), $make('text', ['variant' => 'narrow'], ['heading' => '', 'body' => '<p>A fictional creative making thoughtful, useful things. Find a few places to begin below.</p>']), $make('buttons', [], ['label' => 'Explore my work', 'items' => [(object) ['label' => 'My work', 'url' => '/work', 'variant' => 'primary', 'icon' => 'camera'], (object) ['label' => 'About me', 'url' => '/about', 'variant' => 'secondary', 'icon' => 'user-group'], (object) ['label' => 'Say hello', 'url' => '/contact', 'variant' => 'secondary', 'icon' => 'envelope']]])];
        }
        $root = $make('container', [], [], $body);
        $normalize = function (Node $node, string $path) use (&$normalize, $id, $locale): Node {
            return new Node('pattern-'.substr(hash('sha256', $id.':1:'.$locale.':'.$path), 0, 32), $node->type, $node->version, $node->props, $node->translations, $node->visibility, array_map(fn ($child, $index) => $normalize($child, $path.'/'.$index), $node->children, array_keys($node->children)), $node->extensions);
        };

        return new Document([$normalize($root, '0')], new JsonObject(['site-builder.pattern' => (object) ['id' => $id, 'version' => 1]]));
    }
}

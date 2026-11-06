<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Templates;

use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Core\Composition\StartingPointDescriptor;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;

/** Nested code-owned starting points; mutable sources are never consulted. */
final class PatternCatalog
{
    public function __construct(private StandardExamples $examples) {}

    public function all(): array
    {
        return [
            new StartingPointDescriptor('pattern:standard.header', 1, 'Editable header', 'Site patterns', 'Independent branding and navigation, ready for an authored header.', ['header', 'logo', 'menu'], sourceKind: 'code-template'),
            new StartingPointDescriptor('pattern:standard.footer', 1, 'Editable footer', 'Site patterns', 'Independent brand, stacked navigation and fictional contact details.', ['footer', 'contact', 'menu'], sourceKind: 'code-template'),
            new StartingPointDescriptor('pattern:standard.team', 1, 'Meet the team', 'Site patterns', 'Fictional profiles with portraits, roles and biographies.', ['team', 'people', 'profiles'], ['two' => 'Two people', 'three' => 'Three people'], 'code-template'),
            new StartingPointDescriptor('pattern:standard.process', 1, 'A clear process', 'Site patterns', 'Three ordered steps with useful next actions.', ['process', 'steps', 'approach'], ['three' => 'Three steps'], 'code-template'),
            new StartingPointDescriptor('pattern:standard.pricing', 1, 'Illustrative pricing', 'Site patterns', 'Editable example tiers and features; no checkout integration.', ['pricing', 'plans', 'tiers'], ['two' => 'Two tiers', 'three' => 'Three tiers'], 'code-template'),
        ];
    }

    public function snapshot(string $id, string $locale, ?string $variant = null): Document
    {
        if (! in_array($id, array_map(fn ($entry) => $entry->id, $this->all()), true)) {
            throw new \InvalidArgumentException('Unknown installed pattern.');
        }
        $descriptor = array_values(array_filter($this->all(), fn ($entry) => $entry->id === $id))[0];
        $variant ??= $descriptor->variants === [] ? null : 'three';
        if ($variant !== null && ! in_array($variant, $descriptor->variantIds(), true)) {
            throw new \InvalidArgumentException('Unknown installed pattern variant.');
        }
        if (! in_array($id, ['pattern:standard.header', 'pattern:standard.footer'], true)) {
            return $this->collection($id, $locale, $variant);
        }
        $footer = $id === 'pattern:standard.footer';
        $brand = $this->examples->document('standard.brand', $locale)->nodes[0];
        $navigation = $this->examples->document('standard.navigation', $locale)->nodes[0];
        if ($footer) {
            $navigation = new Node($navigation->id, $navigation->type, props: new JsonObject(['orientation' => 'stacked']), translations: $navigation->translations);
        }
        $children = [$brand, $navigation, ...($footer ? [$this->examples->document('standard.contact-details', $locale)->nodes[0]] : [])];
        $normalize = function (Node $node, string $path) use (&$normalize, $id, $locale): Node {
            return new Node('pattern-'.substr(hash('sha256', $id.':1:'.$locale.':'.$path), 0, 32), $node->type, $node->version, $node->props, $node->translations, $node->visibility, array_map(fn ($child, $index) => $normalize($child, $path.'/'.$index), $node->children, array_keys($node->children)), $node->extensions);
        };
        $root = new Node('root', $footer ? 'standard.section' : 'standard.container', props: new JsonObject($footer ? ['heading_level' => 'h2'] : []), children: $children);

        return new Document([$normalize($root, '0')], new JsonObject(['site-builder.pattern' => (object) ['id' => $id, 'version' => 1]]));
    }

    private function collection(string $id, string $locale, string $variant): Document
    {
        $make = fn (string $type, array $props = [], array $translated = [], array $children = []): Node => new Node('source', 'standard.'.$type, props: new JsonObject($props), translations: new JsonObject([$locale => (object) $translated]), children: $children);
        $count = $variant === 'two' ? 2 : 3;
        $children = [];
        if ($id === 'pattern:standard.team') {
            foreach (array_slice([['Avery Quinn', 'Strategy and research', 'avery'], ['Robin Lee', 'Design and delivery', 'robin'], ['Sam Rivera', 'Care and operations', 'sam']], 0, $count) as [$name, $role, $portrait]) {
                $children[] = $make('column', children: [
                    $make('image', ['variant' => 'default', 'image' => StandardExamples::asset($portrait), 'sources' => [], 'width' => 600, 'height' => 600, 'decorative' => false], ['alt' => 'Illustrated fictional portrait of '.$name, 'caption' => '']),
                    $make('text', ['variant' => 'default'], ['heading' => '', 'body' => '<h3>'.$name.'</h3><p><strong>'.$role.'</strong></p><p>A fictional colleague who brings thoughtful questions, practical experience and care to every project.</p>', 'items' => [(object) ['label' => 'Meet '.$name, 'url' => '/about', 'page' => null]]]),
                ]);
            }
            $body = [$make('columns', ['variant' => 'equal'], children: $children)];
            $heading = 'People who care about the work';
        } elseif ($id === 'pattern:standard.process') {
            $items = [];
            foreach ([['01 — Listen', 'Understand the people, purpose and constraints before choosing a direction.'], ['02 — Make', 'Build a useful first version together, with clear decisions along the way.'], ['03 — Improve', 'Learn from real use and make thoughtful, steady improvements.']] as [$title, $description]) {
                $items[] = (object) ['heading' => $title, 'body' => '<p>'.$description.'</p>', 'label' => 'Our approach', 'url' => '/services', 'page' => null];
            }
            $heading = 'A clear path from idea to outcome';
            $body = [$make('cards', ['variant' => 'cards', 'columns' => 'three'], ['heading' => '', 'body' => '', 'items' => $items])];
        } else {
            foreach (array_slice([['Start', 'CHF 90', 'One focused conversation', 'A written next step'], ['Grow', 'CHF 240', 'Two working sessions', 'A practical action plan'], ['Partner', 'CHF 480', 'Four working sessions', 'Ongoing progress review']], 0, $count) as [$tier, $price, $first, $second]) {
                $children[] = $make('column', children: [
                    $make('text', ['variant' => 'default'], ['heading' => '', 'body' => '<h3>'.$tier.'</h3><p><strong>'.$price.'</strong> / month</p><p>Illustrative example pricing, not an offer.</p><ul><li>'.$first.'</li><li>'.$second.'</li></ul>', 'items' => []]),
                    $make('cta', ['variant' => 'neutral'], ['heading' => '', 'body' => '<p>Let’s find a useful next step.</p>', 'label' => 'Ask about '.$tier, 'url' => '/contact', 'page' => null]),
                ]);
            }
            $heading = 'Choose a useful starting point';
            $body = [$make('columns', ['variant' => 'equal'], children: $children)];
        }
        $root = $make('section', ['heading_level' => 'h2'], ['heading' => $heading], $body);
        $normalize = function (Node $node, string $path) use (&$normalize, $id, $locale, $variant): Node {
            return new Node('pattern-'.substr(hash('sha256', $id.':1:'.$locale.':'.$variant.':'.$path), 0, 32), $node->type, $node->version, $node->props, $node->translations, $node->visibility, array_map(fn ($child, $index) => $normalize($child, $path.'/'.$index), $node->children, array_keys($node->children)), $node->extensions);
        };

        return new Document([$normalize($root, '0')], new JsonObject(['site-builder.pattern' => (object) ['id' => $id, 'version' => 1, 'variant' => $variant]]));
    }
}

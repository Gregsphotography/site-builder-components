<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Examples;

use Illuminate\Support\Str;
use InvalidArgumentException;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Contracts\HasDefaultChildren;
use SiteBuilder\Core\Rendering\LocaleCatalog;

/** Code-owned examples render the actual installed definitions; no screenshot or view override. */
final class StandardExamples
{
    private const ENTRIES = [
        'standard.image-comparison' => ['label' => 'Image comparison', 'category' => 'Media', 'description' => 'Compare two images with a keyboard and touch control.', 'keywords' => ['before', 'after', 'compare', 'photos']],
        'standard.image-hotspots' => ['label' => 'Image hotspots', 'category' => 'Media', 'description' => 'Add numbered explanations to parts of an image.', 'keywords' => ['image', 'hotspot', 'details', 'annotations']],
        'standard.carousel' => ['label' => 'Carousel', 'category' => 'Collections', 'description' => 'Show images, logos, cards, team members or quotations with one accessible carousel.', 'keywords' => ['slides', 'carousel', 'logos', 'team', 'testimonials']],
        'standard.timeline' => ['label' => 'Timeline', 'category' => 'Information', 'description' => 'Show a sequence of milestones or published events.', 'keywords' => ['events', 'history', 'milestones', 'process']],
        'standard.video' => ['label' => 'Video', 'category' => 'Media', 'description' => 'Uploaded video or a supported video service with captions and transcript.', 'keywords' => ['video', 'youtube', 'vimeo', 'captions']],
        'standard.location' => ['label' => 'Location and map', 'category' => 'Information', 'description' => 'An address, directions and optional click-to-load Google Maps.', 'keywords' => ['map', 'address', 'location', 'directions']],
        'standard.tabs' => ['label' => 'Tabs', 'category' => 'Layout', 'description' => 'Related panels containing normal editable components.', 'keywords' => ['tabs', 'panels', 'details']],
        'standard.accordion' => ['label' => 'Accordion', 'category' => 'Layout', 'description' => 'Native disclosures containing normal editable components.', 'keywords' => ['accordion', 'toggle', 'disclosure']],
        'standard.table' => ['label' => 'Table', 'category' => 'Information', 'description' => 'A structured table with headers and a caption.', 'keywords' => ['table', 'comparison', 'prices']],
        'standard.table-of-contents' => ['label' => 'Table of contents', 'category' => 'Navigation', 'description' => 'A generated list of body headings and links.', 'keywords' => ['contents', 'headings', 'navigation']],
        'standard.breadcrumbs' => ['label' => 'Breadcrumbs', 'category' => 'Navigation', 'description' => 'Published ancestor links and the current page.', 'keywords' => ['breadcrumb', 'ancestors', 'navigation']],
        'standard.heading' => ['label' => 'Heading', 'category' => 'Essentials', 'description' => 'A heading with an explicit semantic level and optional highlighted words.', 'keywords' => ['title', 'heading', 'highlight']],
        'standard.buttons' => ['label' => 'Button or button group', 'category' => 'Essentials', 'description' => 'One or more useful links with optional icons.', 'keywords' => ['button', 'action', 'links']],
        'standard.icon' => ['label' => 'Icon', 'category' => 'Essentials', 'description' => 'A labelled or decorative icon from the bundled catalogue.', 'keywords' => ['symbol', 'icon']],
        'standard.icon-list' => ['label' => 'Icon list', 'category' => 'Essentials', 'description' => 'Readable features and useful links with consistent icons.', 'keywords' => ['features', 'list', 'benefits']],
        'standard.social-links' => ['label' => 'Social links', 'category' => 'Essentials', 'description' => 'Labelled social, website, email and telephone links.', 'keywords' => ['social', 'contact', 'icons']],
        'standard.divider' => ['label' => 'Divider', 'category' => 'Essentials', 'description' => 'A simple visual separator.', 'keywords' => ['divider', 'line', 'separator']],
        'standard.anchor' => ['label' => 'Named anchor', 'category' => 'Navigation', 'description' => 'A named destination for a link on this page.', 'keywords' => ['anchor', 'section', 'jump']],
        'standard.gallery' => ['label' => 'Image gallery', 'category' => 'Collections', 'description' => 'Ordered images and captions with an optional accessible lightbox.', 'keywords' => ['gallery', 'images', 'photos', 'lightbox']],
        'standard.testimonials' => ['label' => 'Testimonials', 'category' => 'Collections', 'description' => 'Fictional attributed quotations in a static single or grid presentation.', 'keywords' => ['quote', 'testimonial', 'attribution']],
        'standard.brand' => ['label' => 'Site branding', 'category' => 'Site identity', 'description' => 'An editable site name, logo and home link stored in this document.', 'keywords' => ['brand', 'logo', 'name', 'home', 'header']],
        'standard.navigation' => ['label' => 'Navigation', 'category' => 'Site identity', 'description' => 'An ordered, accessible menu with nested native disclosures.', 'keywords' => ['menu', 'navigation', 'links', 'header', 'footer']],
        'standard.hero' => ['label' => 'Hero', 'category' => 'Introduction', 'description' => 'Introduce a page with a clear heading, image and useful next steps.', 'keywords' => ['introduction', 'welcome', 'banner', 'heading', 'actions']],
        'standard.text' => ['label' => 'Rich text', 'category' => 'Content', 'description' => 'Explain an idea with readable paragraphs, lists and useful links.', 'keywords' => ['paragraphs', 'story', 'information', 'prose', 'links']],
        'standard.image' => ['label' => 'Image with caption', 'category' => 'Content', 'description' => 'Show an image with translated alternative text and an optional caption.', 'keywords' => ['photo', 'picture', 'illustration', 'caption', 'media']],
        'standard.image-text' => ['label' => 'Image and text', 'category' => 'Content', 'description' => 'Pair an image with a story, service or explanation.', 'keywords' => ['split', 'story', 'about', 'service', 'picture']],
        'standard.cards' => ['label' => 'Cards and logos', 'category' => 'Collections', 'description' => 'Arrange services, features or linked logos in a responsive grid.', 'keywords' => ['features', 'services', 'benefits', 'grid', 'logos', 'team']],
        'standard.cta' => ['label' => 'Call to action', 'category' => 'Actions', 'description' => 'Give visitors a clear next step with a safe, labelled link.', 'keywords' => ['button', 'contact', 'next step', 'action', 'link']],
        'standard.faq' => ['label' => 'Questions and answers', 'category' => 'Information', 'description' => 'Group useful answers in native keyboard-accessible disclosures.', 'keywords' => ['faq', 'accordion', 'questions', 'answers', 'help']],
        'standard.contact-details' => ['label' => 'Contact details', 'category' => 'Information', 'description' => 'Display practical contact information and links without a disconnected form.', 'keywords' => ['address', 'email', 'telephone', 'opening hours', 'contact']],
        'standard.section' => ['label' => 'Section', 'category' => 'Layout', 'description' => 'Group related content, with an optional accessible section heading.', 'keywords' => ['group', 'section', 'heading', 'structure', 'layout']],
        'standard.container' => ['label' => 'Container', 'category' => 'Layout', 'description' => 'Keep related content together with shared width, spacing and design choices.', 'keywords' => ['group', 'width', 'container', 'structure', 'layout']],
        'standard.columns' => ['label' => 'Columns', 'category' => 'Layout', 'description' => 'Arrange one to four columns in reading order, stacking on small screens.', 'keywords' => ['columns', 'grid', 'sidebar', 'structure', 'layout']],
    ];

    public function __construct(private ComponentRegistry $components, private LocaleCatalog $locales) {}

    /** @return array<string, array{label:string,category:string,description:string,keywords:list<string>,variants:array<string,string>}> */
    public function entries(): array
    {
        $entries = [];
        foreach (self::ENTRIES as $type => $entry) {
            $definition = $this->components->resolve($type);
            if ($definition !== null) {
                $variants = $definition->properties()['variant']->options ?? [];
                if ($type === 'standard.gallery') {
                    $variants = ['static', 'lightbox'];
                }
                $entries[$type] = [...$entry, 'variants' => array_combine($variants, array_map(Str::headline(...), $variants))];
            }
        }

        return $entries;
    }

    public function document(string $type, string $locale, ?string $variant = null): Document
    {
        $entry = $this->entries()[$type] ?? throw new InvalidArgumentException('No installed standard example is registered for ['.$type.'].');
        if (! $this->locales->has($locale)) {
            throw new InvalidArgumentException('A component example requires a configured locale.');
        }
        $definition = $this->components->resolve($type);
        $defaults = $definition->defaults();
        $props = (array) $defaults->props->toObject();
        $translated = (array) $defaults->translations->get('en', new \stdClass);
        $variant ??= $props['variant'] ?? array_key_first($entry['variants']);
        if ($variant !== null && ! array_key_exists($variant, $entry['variants'])) {
            throw new InvalidArgumentException('Choose an installed standard example variant.');
        }
        if (isset($definition->properties()['variant'])) {
            $props['variant'] = $variant;
        }
        if (in_array($type, ['standard.hero', 'standard.image', 'standard.image-text'], true)) {
            $props = [...$props, 'image' => self::asset('landscape'), 'width' => 1200, 'height' => 800, 'sources' => [(object) ['image' => self::asset('landscape-small'), 'width' => 600], (object) ['image' => self::asset('landscape'), 'width' => 1200]]];
            $translated['alt'] = 'An illustrated mountain landscape with green hills';
        }
        if ($type === 'standard.gallery') {
            $props['lightbox'] = $variant === 'lightbox';
            $translated = ['heading' => 'A few moments worth sharing', 'label' => 'Illustrations from the example studio', 'items' => array_map(fn ($asset, $caption) => (object) ['image' => self::asset($asset), 'full_image' => null, 'sources' => [], 'width' => 1200, 'height' => 800, 'alt' => $caption, 'decorative' => false, 'caption' => $caption, 'url' => null, 'page' => null, 'link_label' => ''], ['landscape', 'workspace', 'landscape-small'], ['A quiet illustrated landscape', 'A calm place to begin the work', 'A different view of the hills'])];
        } elseif ($type === 'standard.testimonials') {
            $translated = ['heading' => 'A few kind words — fictional examples', 'items' => array_map(fn ($name, $portrait, $quote) => (object) ['quote' => $quote, 'name' => $name, 'role' => 'Example collaborator', 'organisation' => 'Fictional Studio', 'portrait' => self::asset($portrait), 'sources' => [], 'width' => 600, 'height' => 600, 'alt' => '', 'url' => null, 'page' => null], ['Avery Quinn', 'Robin Lee', 'Sam Rivera'], ['avery', 'robin', 'sam'], ['A thoughtful process helped us turn a difficult question into a clear next step.', 'We always knew what mattered, what came next and where we could help.', 'Careful listening and practical ideas made the work feel clear and manageable.'])];
        } elseif ($type === 'standard.brand') {
            $props = [...$props, 'width' => 160, 'height' => 80];
            $translated = [...$translated, 'name' => 'Northstar Example Studio', 'logo' => self::asset('northstar'), 'alt' => 'Northstar Example Studio', 'url' => '/', 'page' => null];
        } elseif ($type === 'standard.navigation') {
            $translated = ['label' => 'Example navigation', 'items' => [(object) ['label' => 'Home', 'url' => '/', 'page' => null, 'children' => []], (object) ['label' => 'Services', 'url' => '/services', 'page' => null, 'children' => [(object) ['label' => 'Our approach', 'url' => '/services/approach', 'page' => null, 'children' => []]]], (object) ['label' => 'Contact', 'url' => '/contact', 'page' => null, 'children' => []]]];
        } elseif ($type === 'standard.hero') {
            $translated = [...$translated, 'eyebrow' => 'A thoughtful place to begin', 'heading' => 'Make room for your next idea', 'body' => '<p>Bring a clear purpose, a useful story and a welcoming introduction to your website.</p>', 'label' => 'Explore our work', 'url' => '/services', 'secondary_label' => 'About us', 'secondary_url' => '/about'];
        } elseif ($type === 'standard.text') {
            $translated = [...$translated, 'heading' => 'A little clarity goes a long way', 'body' => '<p>Good content helps people understand what matters and decide what to do next.</p><p>Use short paragraphs, meaningful headings and <strong>useful details</strong> to make your story easy to follow.</p>', 'items' => [(object) ['label' => 'Learn about our approach', 'url' => '/about'], (object) ['label' => 'Explore our services', 'url' => '/services']]];
        } elseif ($type === 'standard.image') {
            $translated['caption'] = 'A quiet landscape, illustrated locally for this example.';
        } elseif ($type === 'standard.image-text') {
            $props = [...$props, 'image' => self::asset('workspace'), 'sources' => []];
            $translated = [...$translated, 'heading' => 'Good work starts with a conversation', 'body' => '<p>Pair your story with an image that adds useful context. Explain your approach and invite people to take the next step.</p>', 'alt' => 'An illustration of a calm workspace with a laptop, plant and mug', 'label' => 'Meet our team', 'url' => '/about'];
        } elseif ($type === 'standard.cards') {
            $translated = $variant === 'logos'
                ? [...$translated, 'heading' => 'A few example partners', 'body' => '<p>Use a simple grid to introduce the people you work with.</p>', 'items' => [
                    (object) ['heading' => 'Northstar', 'image' => self::asset('northstar'), 'alt' => 'Northstar sample star mark', 'width' => 600, 'height' => 300, 'url' => '/partners/northstar'],
                    (object) ['heading' => 'Arc Studio', 'image' => self::asset('arc'), 'alt' => 'Arc Studio sample arch mark', 'width' => 600, 'height' => 300, 'url' => '/partners/arc'],
                ]]
                : [...$translated, 'heading' => 'Practical help for your next step', 'body' => '<p>Make your services easy to compare and explore.</p>', 'items' => [
                    (object) ['heading' => 'Find your direction', 'body' => '<p>Understand the challenge and choose a clear next step.</p>', 'image' => self::asset('landscape'), 'alt' => 'Illustrated mountain landscape', 'width' => 1200, 'height' => 800, 'label' => 'Our approach', 'url' => '/services'],
                    (object) ['heading' => 'Create something useful', 'body' => '<p>Turn thoughtful ideas into practical work that serves people.</p>', 'image' => self::asset('workspace'), 'alt' => 'Illustrated desk and laptop', 'width' => 1200, 'height' => 800, 'label' => 'Our work', 'url' => '/services'],
                    (object) ['heading' => 'Keep moving forward', 'body' => '<p>Learn from the results and make steady improvements.</p>', 'label' => 'Talk to us', 'url' => '/contact'],
                ]];
        } elseif ($type === 'standard.faq') {
            $translated = [...$translated, 'heading' => 'A few useful answers', 'items' => [
                (object) ['question' => 'Where should I start?', 'answer' => '<p>Tell us what you would like to achieve. A short conversation is often the best first step.</p>'],
                (object) ['question' => 'Can we work together?', 'answer' => '<p>We welcome thoughtful questions and practical ideas. Get in touch to explore the possibilities.</p>'],
            ]];
        }
        if ($type === 'standard.image-comparison') {
            $props = [...$props, 'before_image' => self::asset('landscape'), 'after_image' => self::asset('workspace')];
            $translated = [...$translated, 'before_alt' => 'An illustrated mountain landscape', 'after_alt' => 'An illustrated studio workspace'];
        } elseif ($type === 'standard.image-hotspots') {
            $props['image'] = self::asset('workspace');
            $translated = [...$translated, 'alt' => 'An illustrated desk with a laptop, plant and mug', 'items' => [(object) ['x' => 4000, 'y' => 5000, 'title' => 'A place to work', 'body' => '<p>Keep the tools you need close at hand.</p>'], (object) ['x' => 8000, 'y' => 4000, 'title' => 'Room for a pause', 'body' => '<p>Make a little space for the next idea.</p>']]];
        } elseif ($type === 'standard.carousel') {
            $translated['items'] = array_map(fn ($title, $image, $excerpt) => (object) ['title' => $title, 'image' => self::asset($image), 'alt' => $title.' — fictional example', 'subtitle' => $variant === 'testimonials' ? 'Example collaborator' : '', 'excerpt' => $excerpt, 'url' => '/about'], ['Avery Quinn', 'Robin Lee', 'Sam Rivera'], $variant === 'logos' ? ['northstar', 'arc', 'northstar'] : ($variant === 'team' || $variant === 'testimonials' ? ['avery', 'robin', 'sam'] : ['landscape', 'workspace', 'landscape-small']), ['A thoughtful beginning makes the next step clear.', 'Good work grows from useful conversations.', 'Keep learning and improving together.']);
        }
        $id = 'example-'.substr(hash('sha256', $type.':'.$definition->version().':'.$locale.':'.$variant), 0, 32);
        $children = [];
        if ($definition instanceof HasDefaultChildren) {
            $normalize = function (array $nodes, string $path) use (&$normalize, $id, $locale): array {
                return array_map(function (Node $node, int $index) use (&$normalize, $id, $locale, $path): Node {
                    $address = $path.'/'.$index;
                    $translations = $node->translations->toObject();
                    if (property_exists($translations, 'en') && ! property_exists($translations, $locale)) {
                        $translations->{$locale} = $translations->en;
                    }

                    return new Node('example-'.substr(hash('sha256', $id.$address), 0, 32), $node->type, $node->version, $node->props, new JsonObject($translations), $node->visibility, $normalize($node->children, $address), $node->extensions);
                }, $nodes, array_keys($nodes));
            };
            $children = $normalize($definition->defaultChildren(), 'children');
        }

        return new Document([new Node($id, $type, $definition->version(), new JsonObject($props), new JsonObject([$locale => (object) $translated]), children: $children)]);
    }

    /** Local code-owned illustration paths; these are not user-uploaded SVGs. */
    public static function asset(string $name): string
    {
        if (! in_array($name, ['landscape', 'landscape-small', 'workspace', 'northstar', 'arc', 'avery', 'robin', 'sam'], true)) {
            throw new InvalidArgumentException('Unknown local standard example asset.');
        }

        return '/vendor/site-builder-components/examples/'.$name.'.svg';
    }
}

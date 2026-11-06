<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Templates;

use InvalidArgumentException;
use SiteBuilder\Core\Components\ComponentRegistry;
use SiteBuilder\Core\Composition\DocumentCopies;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Rendering\LocaleCatalog;

final class TemplateCatalog
{
    public function __construct(private ComponentRegistry $components, private LocaleCatalog $locales) {}

    /** @return array<string, TemplateDefinition> */
    public function all(): array
    {
        $definitions = [
            new TemplateDefinition('standard.home', 1, 'Home', 'A clear introduction, services, story and next step.', [
                ['type' => 'standard.hero', 'values' => ['eyebrow' => 'Welcome', 'heading' => 'Good work starts with a clear purpose', 'body' => '<p>We help people turn thoughtful ideas into practical results.</p>', 'label' => 'Explore our services', 'url' => '/services', 'secondary_label' => 'About us', 'secondary_url' => '/about']],
                ['type' => 'standard.cards'],
                ['type' => 'standard.image-text', 'values' => ['heading' => 'People, purpose and a useful perspective', 'label' => 'Meet our team', 'url' => '/about']],
                ['type' => 'standard.cta'],
            ]),
            new TemplateDefinition('standard.about', 1, 'About', 'Introduce your people, approach and values.', [
                ['type' => 'standard.hero', 'values' => ['heading' => 'A team with a shared purpose', 'body' => '<p>We bring care, experience and curiosity to every project.</p>', 'label' => 'Work with us', 'url' => '/contact']],
                ['type' => 'standard.image-text', 'values' => ['heading' => 'What matters to us', 'body' => '<p>Clear communication, thoughtful choices and work we can stand behind.</p>']],
                ['type' => 'standard.text', 'values' => ['heading' => 'Our approach', 'body' => '<p>We listen first, make a practical plan and keep you involved along the way.</p>', 'variant' => 'narrow']],
                ['type' => 'standard.cta'],
            ]),
            new TemplateDefinition('standard.services', 1, 'Services', 'Describe your services and answer common questions.', [
                ['type' => 'standard.hero', 'values' => ['heading' => 'Practical help for your next step', 'body' => '<p>Explore the ways we can support your goals.</p>', 'label' => 'Talk to us', 'url' => '/contact']],
                ['type' => 'standard.cards', 'values' => ['items' => [
                    ['heading' => 'Understand', 'body' => '<p>Make sense of the challenge and choose a clear direction.</p>'],
                    ['heading' => 'Create', 'body' => '<p>Turn the plan into useful work with care and attention.</p>'],
                    ['heading' => 'Improve', 'body' => '<p>Learn from the results and keep making progress.</p>'],
                ]]],
                ['type' => 'standard.faq'],
                ['type' => 'standard.cta'],
            ]),
            new TemplateDefinition('standard.contact', 1, 'Contact', 'Contact details and useful links; no unconnected form.', [
                ['type' => 'standard.hero', 'values' => ['heading' => 'Let’s start a conversation', 'body' => '<p>Tell us a little about what you have in mind.</p>', 'label' => '', 'url' => null]],
                ['type' => 'standard.contact-details'],
                ['type' => 'standard.text', 'values' => ['heading' => 'Before you get in touch', 'body' => '<p>A few details about your goals and timing will help us point you in the right direction.</p>']],
            ]),
            new TemplateDefinition('standard.informational', 1, 'Informational', 'A readable starting point for a guide or useful information.', [
                ['type' => 'standard.hero', 'values' => ['heading' => 'Useful information, clearly explained', 'body' => '<p>Start with the main point and help readers find what they need.</p>', 'label' => '', 'url' => null]],
                ['type' => 'standard.text', 'values' => ['variant' => 'narrow', 'heading' => 'What you need to know', 'body' => '<p>Use clear headings and short paragraphs. Explain the details in the order readers need them.</p>']],
                ['type' => 'standard.faq'],
            ]),
        ];

        return array_combine(array_map(fn (TemplateDefinition $template): string => $template->id, $definitions), $definitions);
    }

    /** @return array<string, string> */
    public function options(string $locale): array
    {
        return array_map(fn (TemplateDefinition $template): string => __($template->label, [], $locale), $this->all());
    }

    public function instantiate(string $id, string $locale = 'en'): Document
    {
        return app(DocumentCopies::class)->copy($this->snapshot($id, $locale));
    }

    /** Deterministic source data; only instantiate() produces independent editable node IDs. */
    public function snapshot(string $id, string $locale = 'en'): Document
    {
        $template = $this->all()[$id] ?? throw new InvalidArgumentException('Unknown standard page template ['.$id.'].');
        if (! $this->locales->has($locale)) {
            throw new InvalidArgumentException('A starter page requires a configured locale.');
        }
        $nodes = [];
        foreach ($template->blocks as $index => $block) {
            $definition = $this->components->resolve($block['type']) ?? throw new InvalidArgumentException('The template requires an unavailable component ['.$block['type'].'].');
            $defaults = $definition->defaults();
            $props = (array) $defaults->props->toObject();
            $translated = (array) $defaults->translations->get('en', new \stdClass);
            foreach ($block['values'] ?? [] as $key => $value) {
                if (($definition->properties()[$key] ?? null)?->translated) {
                    $translated[$key] = $value;
                } else {
                    $props[$key] = $value;
                }
            }
            $sourceId = 'template-'.substr(hash('sha256', $template->id.':'.$template->version.':'.$locale.':'.$index), 0, 32);
            $nodes[] = new Node($sourceId, $definition->type(), $definition->version(), new JsonObject($props), new JsonObject([$locale => (object) $translated]));
        }

        return new Document($nodes, new JsonObject(['site-builder.template' => (object) ['id' => $template->id, 'version' => $template->version]]));
    }
}

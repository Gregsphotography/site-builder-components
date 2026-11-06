<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Templates;

/** A code-owned copy recipe, never a linked global section. */
final readonly class TemplateDefinition
{
    /** @param list<array{type: string, values?: array<string, mixed>}> $blocks */
    public function __construct(public string $id, public int $version, public string $label, public string $description, public array $blocks) {}
}

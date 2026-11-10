<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Contracts\DocumentConstraint;
use SiteBuilder\Core\Rendering\ContentOccurrences;
use SiteBuilder\Core\Rendering\RenderContext;

final class AnchorConstraint implements DocumentConstraint
{
    public function __construct(private ContentOccurrences $occurrences) {}

    public function diagnostics(Document $document, RenderContext $context, bool $publishing): array
    {
        if (! $publishing) {
            return [];
        }
        $seen = $diagnostics = [];
        foreach ($this->occurrences->visible($document, $context, true) as $entry) {
            if ($entry['node']->type !== 'standard.anchor') {
                continue;
            }
            $name = $entry['props']['name'] ?? '';
            if (isset($seen[$name])) {
                $diagnostics[] = new Diagnostic('duplicate_anchor', '/nodes', 'The named anchor “'.$name.'” appears more than once. Choose a unique name.');
            }
            $seen[$name] = true;
        }

        return $diagnostics;
    }
}

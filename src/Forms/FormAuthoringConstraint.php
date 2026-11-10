<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Contracts\DocumentAuthoringConstraint;
use SiteBuilder\Core\Rendering\ContentOccurrences;
use SiteBuilder\Core\Rendering\RenderContext;

final class FormAuthoringConstraint implements DocumentAuthoringConstraint
{
    public function authorize(Authenticatable $actor, Document $document, RenderContext $context): void
    {
        if (! app(FormRuntime::class)->available()) {
            return;
        }
        foreach (app(ContentOccurrences::class)->visible($document, $context, true) as $entry) {
            if ($entry['node']->type === 'standard.form' && ($profile = app(FormRuntime::class)->profile($entry['props'])) !== null) {
                Gate::forUser($actor)->authorize('use', $profile);
            }
        }
    }
}

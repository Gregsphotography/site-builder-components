<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Rendering;

use Illuminate\Support\HtmlString;
use SiteBuilder\Core\Discovery\ContentItem;

/** Hosts can register trusted card presentation callbacks without storing template paths. */
final class ContentPresentations
{
    private array $renderers = [];

    public function register(string $id, \Closure $renderer): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{0,79}$/D', $id) || isset($this->renderers[$id])) {
            throw new \InvalidArgumentException('Choose a unique trusted content presentation identifier.');
        }
        $this->renderers[$id] = $renderer;
    }

    public function render(string $id, ContentItem $item, array $options): ?HtmlString
    {
        if (! isset($this->renderers[$id])) {
            return null;
        }
        $html = ($this->renderers[$id])($item, $options);
        if (! $html instanceof HtmlString) {
            throw new \LogicException('Trusted content presentations must return HtmlString.');
        }

        return $html;
    }
}

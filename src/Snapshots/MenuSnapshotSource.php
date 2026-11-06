<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Snapshots;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use SiteBuilder\Core\Composition\SourceSnapshot;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\Node;
use SiteBuilder\Core\Contracts\NavigationProvider;
use SiteBuilder\Core\Contracts\SnapshotSourceProvider;
use SiteBuilder\Core\Snapshots\SnapshotChoice;
use SiteBuilder\Core\Snapshots\SnapshotDestination;

/** Legacy provider bridge: it never guesses core Page IDs from URL strings. */
final class MenuSnapshotSource implements SnapshotSourceProvider
{
    public function __construct(private NavigationProvider $navigation) {}

    public function choices(Authenticatable $actor, SnapshotDestination $destination, string $locale): array
    {
        $locations = config('site-builder-components.snapshots.menus', []);
        if (! is_array($locations) || count($locations) > 100) {
            throw new \InvalidArgumentException('Configure a bounded trusted menu catalog.');
        }
        $choices = [];
        foreach ($locations as $id => $label) {
            if (! is_string($id) || ! is_string($label)) {
                throw new \InvalidArgumentException('Menu catalog keys and labels must be strings.');
            }
            $choice = new SnapshotChoice($id, $label, 'standard.navigation');
            if (Gate::forUser($actor)->allows('copySiteBuilderMenu', [$id, $destination, $locale])) {
                $choices[] = $choice;
            }
        }

        return $choices;
    }

    public function snapshot(Authenticatable $actor, SnapshotDestination $destination, string $sourceId, string $locale, bool $lock): SourceSnapshot
    {
        $choice = array_find($this->choices($actor, $destination, $locale), fn (SnapshotChoice $choice): bool => $choice->id === $sourceId);
        if ($choice === null) {
            throw new AuthorizationException('This source is unavailable.');
        }
        $count = 0;
        $convert = function (array $entries, int $depth) use (&$convert, &$count): array {
            if (! array_is_list($entries) || ($depth > 3 && $entries !== []) || count($entries) > 100) {
                throw new \InvalidArgumentException('The source menu exceeds three levels or 100 entries.');
            }
            $result = [];
            foreach ($entries as $entry) {
                if (++$count > 100 || ! is_array($entry)) {
                    throw new \InvalidArgumentException('The source menu is invalid or oversized.');
                }
                $children = $entry['children'] ?? [];
                if (! is_array($children)) {
                    throw new \InvalidArgumentException('Invalid source children.');
                }
                $result[] = (object) ['label' => $entry['label'] ?? '', 'url' => $entry['url'] ?? null, 'page' => null, 'children' => $convert($children, $depth + 1)];
            }

            return $result;
        };
        // One materialized provider value; version/digest derive from those exact bytes.
        $items = $convert($this->navigation->items($sourceId, $locale), 1);
        $document = new Document([new Node('snapshot-menu-'.substr(hash('sha256', $sourceId.':'.$locale), 0, 24), 'standard.navigation', translations: new JsonObject([$locale => (object) ['label' => $choice->label, 'items' => $items]]))]);

        return SourceSnapshot::fromDocument('host-snapshot', 'standard.menus:'.$sourceId, 'v'.app(CanonicalJson::class)->digest($document), $document);
    }
}

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
use SiteBuilder\Core\Contracts\SiteMetadataProvider;
use SiteBuilder\Core\Contracts\SnapshotSourceProvider;
use SiteBuilder\Core\Snapshots\SnapshotChoice;
use SiteBuilder\Core\Snapshots\SnapshotDestination;

/** Only explicitly mapped public keys can leave the host metadata provider. */
final class MetadataSnapshotSource implements SnapshotSourceProvider
{
    public function __construct(private SiteMetadataProvider $metadata) {}

    public function choices(Authenticatable $actor, SnapshotDestination $destination, string $locale): array
    {
        return $this->mapping() !== [] && Gate::forUser($actor)->allows('copySiteBuilderMetadata', [$destination, $locale])
            ? [new SnapshotChoice('site', 'Site metadata', 'standard.brand')] : [];
    }

    public function snapshot(Authenticatable $actor, SnapshotDestination $destination, string $sourceId, string $locale, bool $lock): SourceSnapshot
    {
        if ($sourceId !== 'site' || $this->choices($actor, $destination, $locale) === []) {
            throw new AuthorizationException('This source is unavailable.');
        }
        $metadata = $this->metadata->metadata($locale)->toObject();
        $props = [];
        $translated = [];
        foreach ($this->mapping() as $field => $key) {
            if (property_exists($metadata, $key)) {
                if (in_array($field, ['width', 'height'], true)) {
                    $props[$field] = $metadata->{$key};
                } else {
                    $translated[$field] = $metadata->{$key};
                }
            }
        }
        $document = new Document([new Node('snapshot-metadata-'.substr(hash('sha256', $locale), 0, 16), 'standard.brand', props: new JsonObject($props), translations: new JsonObject([$locale => (object) $translated]))]);

        return SourceSnapshot::fromDocument('host-snapshot', 'standard.metadata:site', 'v'.app(CanonicalJson::class)->digest($document), $document);
    }

    private function mapping(): array
    {
        $mapping = config('site-builder-components.snapshots.metadata', []);
        if (! is_array($mapping) || array_diff(array_keys($mapping), ['name', 'logo', 'alt', 'url', 'page', 'width', 'height']) !== [] || array_any($mapping, fn ($key): bool => ! is_string($key) || $key === '' || strlen($key) > 100)) {
            throw new \InvalidArgumentException('Metadata snapshots require an explicit public-field key map.');
        }

        return $mapping;
    }
}

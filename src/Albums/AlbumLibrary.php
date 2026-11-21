<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Albums;

use Closure;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use SiteBuilder\Components\Media\MediaAsset;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Exceptions\WriteConflict;
use SiteBuilder\Core\Rendering\LocaleCatalog;

/** Album administration is authorized by the caller. Public reads expose only published snapshots. */
final class AlbumLibrary
{
    public function __construct(
        private MediaLibrary $media,
        private Validation $validation,
        private LocaleCatalog $locales,
        private CanonicalJson $json,
    ) {}

    public function available(): bool
    {
        $album = new Album;

        return $this->media->available() && $album->getConnection()->getSchemaBuilder()->hasTable($album->getTable());
    }

    public function publishedById(string $id): ?array
    {
        if (! Str::isUuid($id) || ! $this->available()) {
            return null;
        }

        return Album::query()->whereKey($id)->value('published');
    }

    public function publishedBySlug(string $slug): ?array
    {
        if (! $this->available()) {
            return null;
        }

        return Album::query()->where('published_slug', $slug)->value('published');
    }

    /** @return array<string, string> Published album IDs mapped to their display titles. */
    public function publishedOptions(string $search = ''): array
    {
        if (! $this->available()) {
            return [];
        }
        $query = Album::query()->whereNotNull('published_title');
        if (trim($search) !== '') {
            $query->whereLike('published_title', '%'.trim($search).'%');
        }

        return $query->orderBy('published_title')->orderBy('id')->limit(100)->pluck('published_title', 'id')->all();
    }

    public function create(array $data): Album
    {
        $this->assertAvailable();
        $album = new Album;
        $album->draft = $this->snapshot($data);
        $album->write_version = 0;
        $this->persist($album);

        return $album;
    }

    public function saveDraft(Album|string $album, array $data, int $expectedVersion): Album
    {
        return $this->change($album, $expectedVersion, function (Album $locked) use ($data): void {
            $draft = $this->snapshot($data);
            if (! $this->json->equals($locked->draft, $draft)) {
                $locked->draft = $draft;
            }
        });
    }

    public function publish(Album|string $album, int $expectedVersion): Album
    {
        try {
            return $this->change($album, $expectedVersion, function (Album $locked): void {
                $snapshot = $this->snapshot($locked->draft, publishing: true);
                $locked->published = $snapshot;
                $locked->published_slug = $snapshot['slug'];
                $locked->published_title = $snapshot['title'];
                $locked->published_at = now();
                $locked->published_version = $locked->write_version + 1;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Another published album already uses this URL. Choose a different slug.']);
        }
    }

    public function unpublish(Album|string $album, int $expectedVersion): Album
    {
        return $this->change($album, $expectedVersion, function (Album $locked): void {
            $locked->published = null;
            $locked->published_slug = null;
            $locked->published_title = null;
            $locked->published_at = null;
            $locked->published_version = null;
        });
    }

    private function change(Album|string $album, int $expectedVersion, Closure $apply): Album
    {
        $this->assertAvailable();
        $id = $album instanceof Album ? $album->getKey() : $album;

        return (new Album)->getConnection()->transaction(function () use ($id, $expectedVersion, $apply): Album {
            $locked = Album::query()->lockForUpdate()->findOrFail($id);
            if ($locked->write_version !== $expectedVersion) {
                throw new WriteConflict('stale_album_version', $locked->write_version);
            }
            $apply($locked);
            if ($locked->isDirty()) {
                $locked->write_version++;
                $this->persist($locked);
            }

            return $locked;
        });
    }

    private function snapshot(array $data, bool $publishing = false): array
    {
        $data = $this->validation->make($data + ['items' => [], 'columns' => 'three', 'lightbox' => true], [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'cover' => ['nullable', 'string', 'max:512'],
            'columns' => ['required', 'in:one,two,three,four'],
            'lightbox' => ['required', 'boolean'],
            'items' => ['array', 'list', 'max:48', ...($publishing ? ['required', 'min:1'] : [])],
            'items.*' => ['required', 'array:image,alt,caption,decorative'],
            'items.*.image' => ['required', 'string', 'max:512'],
            'items.*.alt' => ['nullable', 'string', 'max:2000'],
            'items.*.caption' => ['nullable', 'string', 'max:2000'],
            'items.*.decorative' => ['nullable', 'boolean'],
        ])->validate();

        $cover = ($data['cover'] ?? '') === '' ? null : $data['cover'];
        $references = [];
        if ($cover !== null) {
            $references['cover'] = $cover;
        }
        foreach ($data['items'] as $index => $item) {
            $references["items.{$index}.image"] = $item['image'];
        }
        $assets = MediaAsset::query()->where('mime', 'like', 'image/%')->whereIn('path', array_unique($references))->get()->keyBy('path');
        $unavailable = [];
        foreach (array_unique($references) as $path) {
            if (! $assets->has($path) || ($publishing && $this->media->url($path) === null)) {
                $unavailable[$path] = true;
            }
        }
        $errors = [];
        foreach ($references as $field => $path) {
            if (isset($unavailable[$path])) {
                $errors[$field] = 'Choose an available photo from the Media library.';
            }
        }
        $items = [];
        foreach ($data['items'] as $index => $item) {
            $image = $item['image'];
            $decorative = (bool) ($item['decorative'] ?? false);
            $alt = trim($item['alt'] ?? '');
            if ($publishing && ! $decorative && $alt === '') {
                $alt = trim($assets->get($image)?->alt[$this->locales->default()] ?? '');
                if ($alt === '') {
                    $errors["items.{$index}.alt"] = 'Describe this photo or mark it decorative.';
                }
            }
            $items[] = ['image' => $image, 'alt' => $alt, 'caption' => trim($item['caption'] ?? ''), 'decorative' => $decorative];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'title' => trim($data['title']), 'slug' => $data['slug'],
            'cover' => $cover ?? ($publishing ? $items[0]['image'] : null),
            'columns' => $data['columns'], 'lightbox' => (bool) $data['lightbox'], 'items' => $items,
        ];
    }

    private function persist(Album $album): void
    {
        if (! $album->save()) {
            throw new RuntimeException('The album could not be saved.');
        }
    }

    private function assertAvailable(): void
    {
        if (! $this->available()) {
            throw new LogicException('Publish and run the local media and album migrations and configure the public media disk.');
        }
    }
}

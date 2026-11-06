<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Media;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use LogicException;
use SiteBuilder\Core\Exceptions\WriteConflict;
use SiteBuilder\Core\Rendering\LocaleCatalog;
use Throwable;

/** Optional local media ownership; host authorization belongs to the caller. */
final class MediaLibrary
{
    public function __construct(private Filesystems $files, private Validation $validation, private LocaleCatalog $locales) {}

    public function available(): bool
    {
        $disk = config('site-builder-components.media.disk', 'public');

        return $this->publicLocalDisk($disk)
            && (new MediaAsset)->getConnection()->getSchemaBuilder()->hasTable('site_builder_media_assets');
    }

    /** @return Collection<int, MediaAsset> */
    public function entries(): Collection
    {
        $this->assertAvailable();

        return MediaAsset::query()->latest()->orderBy('id')->get();
    }

    /** @return array<string, string> */
    public function options(string $locale): array
    {
        if (! $this->available()) {
            return [];
        }

        return $this->entries()->mapWithKeys(fn (MediaAsset $asset): array => [$asset->path => $asset->name.' — '.($asset->alt[$locale] ?? $asset->alt[$this->locales->default()] ?? $asset->mime)])->all();
    }

    public function store(UploadedFile $file, array $alt = [], ?string $name = null): MediaAsset
    {
        $this->assertAvailable();
        $this->validation->make(['file' => $file, 'name' => $name], [
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:'.config('site-builder-components.media.max_kilobytes', 10240)],
            'name' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $alt = $this->validatedAlt($alt);
        $diskName = config('site-builder-components.media.disk', 'public');
        $directory = trim(config('site-builder-components.media.directory', 'site-builder-media'), '/');
        if (! preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $directory)) {
            throw new LogicException('The owned media directory must be a safe relative path.');
        }
        $id = (string) Str::uuid();
        $path = $directory.'/'.$id.'.'.$file->extension();
        $dimensions = @getimagesize($file->getRealPath());
        $disk = $this->files->disk($diskName);
        if ($disk->putFileAs($directory, $file, basename($path), ['visibility' => 'public']) !== $path) {
            throw new LogicException('The media file could not be stored.');
        }
        try {
            $asset = new MediaAsset([
                'disk' => $diskName, 'path' => $path,
                'name' => $name ?: Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 255, ''),
                'mime' => $file->getMimeType(), 'size' => $file->getSize(),
                'width' => $dimensions ? $dimensions[0] : null, 'height' => $dimensions ? $dimensions[1] : null,
                'alt' => $alt,
            ]);
            $asset->id = $id;
            $asset->save();

            return $asset->refresh();
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
    }

    public function updateAlt(MediaAsset|string $asset, array $alt, int $expectedVersion): MediaAsset
    {
        $this->assertAvailable();
        $id = is_string($asset) ? $asset : $asset->getKey();
        $asset = MediaAsset::query()->findOrFail($id);
        $merged = array_replace($asset->alt ?? [], $this->validatedAlt($alt));
        $updated = MediaAsset::query()->whereKey($id)->where('write_version', $expectedVersion)->update([
            'alt' => json_encode($merged, JSON_THROW_ON_ERROR),
            'write_version' => $expectedVersion + 1,
            'updated_at' => now(),
        ]);
        if ($updated !== 1) {
            throw new WriteConflict('stale_media_version', MediaAsset::query()->find($id)?->write_version);
        }

        return $asset->fresh();
    }

    public function url(string $path): ?string
    {
        if (! $this->available()) {
            return null;
        }
        $asset = MediaAsset::query()->where('path', $path)->first();
        if ($asset === null || ! $this->publicLocalDisk($asset->disk)) {
            return null;
        }
        $disk = $this->files->disk($asset->disk);

        return $disk->exists($asset->path) ? $disk->url($asset->path) : null;
    }

    private function validatedAlt(array $alt): array
    {
        $this->validation->make(['alt' => $alt], ['alt' => ['array'], 'alt.*' => ['nullable', 'string', 'max:2000']])->validate();
        foreach (array_keys($alt) as $locale) {
            if (! is_string($locale) || ! $this->locales->has($locale)) {
                throw new LogicException('Media alt text requires a configured locale key.');
            }
        }

        return array_map(fn ($value): string => $value ?? '', $alt);
    }

    private function publicLocalDisk(string $disk): bool
    {
        return config('filesystems.disks.'.$disk.'.driver') === 'local'
            && config('filesystems.disks.'.$disk.'.visibility') === 'public';
    }

    private function assertAvailable(): void
    {
        if (! $this->available()) {
            throw new LogicException('Install the optional local media migration with site-builder:components-install --media, run migrate, and configure a local public disk.');
        }
    }
}

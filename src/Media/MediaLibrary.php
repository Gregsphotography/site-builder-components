<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Media;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use SiteBuilder\Core\Exceptions\WriteConflict;
use SiteBuilder\Core\Rendering\LocaleCatalog;
use Throwable;

/** Optional local media ownership; host authorization belongs to the caller. */
final class MediaLibrary
{
    /** @var array<string, bool> */
    private array $tableAvailability = [];

    public function __construct(private Filesystems $files, private Validation $validation, private LocaleCatalog $locales) {}

    public function available(): bool
    {
        $disk = config('site-builder-components.media.disk', 'public');

        if (! $this->publicLocalDisk($disk)) {
            return false;
        }
        $model = new MediaAsset;
        $connection = $model->getConnection();
        $key = json_encode([spl_object_id($connection), $connection->getName(), $connection->getDatabaseName(), $connection->getTablePrefix(), $model->getTable()], JSON_THROW_ON_ERROR);

        return $this->tableAvailability[$key] ??= $connection->getSchemaBuilder()->hasTable($model->getTable());
    }

    public function clearAvailabilityCache(): void
    {
        $this->tableAvailability = [];
    }

    /** @return Collection<int, MediaAsset> */
    public function entries(string $kind = 'image'): Collection
    {
        $this->assertAvailable();

        return MediaAsset::query()->when($kind !== 'all', fn ($query) => match ($kind) {
            'image' => $query->where('mime', 'like', 'image/%'), 'video' => $query->whereIn('mime', ['video/mp4', 'video/webm']), 'track' => $query->where('mime', 'text/vtt'), default => throw new LogicException('Unknown media kind.'),
        })->latest()->orderBy('id')->get();
    }

    /** @return array<string, string> */
    public function options(string $locale, string $kind = 'image'): array
    {
        if (! $this->available()) {
            return [];
        }

        return $this->entries($kind)->mapWithKeys(fn (MediaAsset $asset): array => [$asset->path => $asset->name.' — '.($asset->alt[$locale] ?? $asset->alt[$this->locales->default()] ?? $asset->mime)])->all();
    }

    public function store(UploadedFile $file, array $alt = [], ?string $name = null): MediaAsset
    {
        return $this->storeAsset($file, 'image', $alt, $name);
    }

    public function storeVideo(UploadedFile $file, ?string $name = null): MediaAsset
    {
        return $this->storeAsset($file, 'video', [], $name);
    }

    public function storeTrack(UploadedFile $file, ?string $name = null): MediaAsset
    {
        return $this->storeAsset($file, 'track', [], $name);
    }

    private function storeAsset(UploadedFile $file, string $kind, array $alt, ?string $name): MediaAsset
    {
        $this->assertAvailable();
        $this->validation->make(['file' => $file, 'name' => $name], [
            'file' => match ($kind) {
                'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:'.config('site-builder-components.media.max_kilobytes', 10240)],
                'video' => ['required', 'file', 'mimetypes:video/mp4,video/webm', 'extensions:mp4,webm', 'max:'.config('site-builder-components.media.video_max_kilobytes', 102400)],
                'track' => ['required', 'file', 'extensions:vtt', 'max:'.config('site-builder-components.media.track_max_kilobytes', 1024)],
            },
            'name' => ['nullable', 'string', 'max:255'],
        ])->validate();
        if ($kind === 'track') {
            $text = file_get_contents($file->getRealPath());
            if (! mb_check_encoding($text, 'UTF-8') || ! preg_match('/^(?:\xEF\xBB\xBF)?WEBVTT(?:[ \t].*)?(?:\r?\n|$)/', $text) || str_contains($text, "\0")) {
                throw ValidationException::withMessages(['file' => 'Choose a UTF-8 WebVTT caption file.']);
            }
        }
        $alt = $this->validatedAlt($alt);
        $diskName = config('site-builder-components.media.disk', 'public');
        $directory = trim(config('site-builder-components.media.directory', 'site-builder-media'), '/');
        if (! preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $directory)) {
            throw new LogicException('The owned media directory must be a safe relative path.');
        }
        $id = (string) Str::uuid();
        $path = $directory.'/'.$id.'.'.($kind === 'track' ? 'vtt' : $file->extension());
        $dimensions = $kind === 'image' ? @getimagesize($file->getRealPath()) : false;
        $disk = $this->files->disk($diskName);
        if ($disk->putFileAs($directory, $file, basename($path), ['visibility' => 'public']) !== $path) {
            throw new LogicException('The media file could not be stored.');
        }
        try {
            $asset = new MediaAsset([
                'disk' => $diskName, 'path' => $path,
                'name' => $name ?: Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 255, ''),
                'mime' => $kind === 'track' ? 'text/vtt' : $file->getMimeType(), 'size' => $file->getSize(),
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

    public function kind(string $path): ?string
    {
        if (! $this->available()) {
            return null;
        }
        $mime = MediaAsset::query()->where('path', $path)->value('mime');

        return is_string($mime) ? (str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : ($mime === 'text/vtt' ? 'track' : null))) : null;
    }

    public function uploadReadiness(string $kind): array
    {
        $configured = (int) match ($kind) {
            'image' => config('site-builder-components.media.max_kilobytes', 10240),
            'video' => config('site-builder-components.media.video_max_kilobytes', 102400),
            'track' => config('site-builder-components.media.track_max_kilobytes', 1024),
            default => throw new LogicException('Unknown media kind.'),
        };
        $php = array_map(fn ($key): int => (($bytes = ini_parse_quantity((string) ini_get($key))) > 0 ? (int) floor($bytes / 1024) : PHP_INT_MAX), ['upload_max_filesize', 'post_max_size']);
        $temporary = null;
        if (config()->has('livewire')) {
            $rules = config('livewire.temporary_file_upload.rules') ?? ['required', 'file', 'max:12288'];
            foreach (is_array($rules) ? $rules : explode('|', $rules) as $rule) {
                if (is_string($rule) && preg_match('/^max:([0-9]+)$/D', $rule, $match)) {
                    $temporary = (int) $match[1];
                }
            }
        }

        return ['configured_kilobytes' => $configured, 'php_kilobytes' => min($php), 'temporary_kilobytes' => $temporary, 'effective_kilobytes' => min([$configured, ...$php, $temporary ?? PHP_INT_MAX])];
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

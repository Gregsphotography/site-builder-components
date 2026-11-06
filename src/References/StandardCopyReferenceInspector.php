<?php

declare(strict_types=1);

namespace SiteBuilder\Components\References;

use Illuminate\Contracts\Auth\Authenticatable;
use SiteBuilder\Components\Examples\StandardExamples;
use SiteBuilder\Components\Media\LibraryMediaResolver;
use SiteBuilder\Components\Media\MediaLibrary;
use SiteBuilder\Core\Components\Reference;
use SiteBuilder\Core\Composition\CopyContext;
use SiteBuilder\Core\Composition\CopyOwner;
use SiteBuilder\Core\Composition\ReferenceCheck;
use SiteBuilder\Core\Contracts\MediaResolver;
use SiteBuilder\Core\Contracts\OwnerAwareCopyReferenceInspector;
use SiteBuilder\Core\Rendering\SafeUrl;

final class StandardCopyReferenceInspector implements OwnerAwareCopyReferenceInspector
{
    public function __construct(private MediaResolver $media, private MediaLibrary $library, private SafeUrl $safe) {}

    public function supportsOwner(CopyOwner $owner): bool
    {
        return in_array($owner->kind, ['page', 'site-part'], true);
    }

    public function inspect(Authenticatable $actor, Reference $reference, CopyContext $destination): ?ReferenceCheck
    {
        if ($reference->kind !== 'media' || ! is_string($reference->value) || ! $this->media instanceof LibraryMediaResolver) {
            return null;
        }
        $path = ltrim($reference->value, '/');
        $prefix = 'vendor/site-builder-components/examples/';
        if (str_starts_with($path, $prefix)) {
            if (! $this->safe->valid($reference->value, true)) {
                return ReferenceCheck::missing('The standard example reference is unsafe.');
            }
            $name = substr($path, strlen($prefix));
            foreach (['landscape', 'landscape-small', 'workspace', 'northstar', 'arc', 'avery', 'robin', 'sam'] as $asset) {
                if ('/'.$path === StandardExamples::asset($asset)) {
                    $package = __DIR__.'/../../resources/dist/examples/'.$name;
                    $published = public_path($path);
                    $packageDigest = is_file($package) && is_readable($package) ? @hash_file('sha256', $package) : false;
                    $publishedDigest = is_file($published) && is_readable($published) ? @hash_file('sha256', $published) : false;
                    if (is_string($packageDigest) && is_string($publishedDigest) && hash_equals($packageDigest, $publishedDigest)) {
                        return ReferenceCheck::valid('Matching published local standard example asset.');
                    }

                    return ReferenceCheck::missing('Publish current standard example assets with site-builder:components-install before using this example.');
                }
            }

            return ReferenceCheck::missing('The standard example asset is unavailable.');
        }
        $directory = trim(config('site-builder-components.media.directory', 'site-builder-media'), '/').'/';
        if (str_starts_with($path, $directory)) {
            if (! $this->safe->valid($reference->value, true)) {
                return ReferenceCheck::missing('The owned media reference is unsafe.');
            }
            $url = $this->library->url($path);

            return is_string($url) && $url !== '' && $this->safe->valid($url, true)
                ? ReferenceCheck::valid('Existing owned media on a public local disk.')
                : ReferenceCheck::missing('Owned media is missing, private or unavailable.');
        }

        return null;
    }
}

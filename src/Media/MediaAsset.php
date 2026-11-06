<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Media;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class MediaAsset extends Model
{
    use HasUuids;

    protected $table = 'site_builder_media_assets';

    protected $fillable = ['disk', 'path', 'name', 'mime', 'size', 'width', 'height', 'alt'];

    protected function casts(): array
    {
        return ['alt' => 'array', 'size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'write_version' => 'integer'];
    }
}

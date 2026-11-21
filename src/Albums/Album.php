<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Albums;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Album extends Model
{
    use HasUuids;

    protected $table = 'site_builder_albums';

    protected $hidden = ['draft'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'published' => 'array', 'published_at' => 'immutable_datetime', 'published_version' => 'integer', 'write_version' => 'integer'];
    }
}

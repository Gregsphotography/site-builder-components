<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class NotificationProfile extends Model
{
    use HasUuids;

    protected $table = 'site_builder_form_profiles';

    protected $fillable = ['name', 'recipients', 'active'];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'active' => 'boolean', 'write_version' => 'integer'];
    }
}

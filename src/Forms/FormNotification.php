<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class FormNotification extends Model
{
    use HasUuids;

    protected $table = 'site_builder_form_notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'retry_at' => 'immutable_datetime', 'locked_until' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}

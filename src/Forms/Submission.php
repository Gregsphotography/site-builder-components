<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Submission extends Model
{
    use HasUuids;

    protected $table = 'site_builder_form_submissions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array', 'read_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime'];
    }
}

<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use SiteBuilder\Core\Exceptions\WriteConflict;

final class Profiles
{
    public function options(Authenticatable $actor): array
    {
        if (! app(FormRuntime::class)->available()) {
            return [];
        }

        return NotificationProfile::query()->where('active', true)->orderBy('name')->get()->filter(fn ($profile): bool => Gate::forUser($actor)->allows('use', $profile))->mapWithKeys(fn ($profile): array => [$profile->id => $profile->name])->all();
    }

    public function create(Authenticatable $actor, array $values): NotificationProfile
    {
        Gate::forUser($actor)->authorize('create', NotificationProfile::class);
        $values = $this->validated($values);

        return NotificationProfile::query()->create($values);
    }

    public function update(Authenticatable $actor, string $id, int $version, array $values): NotificationProfile
    {
        return DB::transaction(function () use ($actor, $id, $version, $values): NotificationProfile {
            $profile = NotificationProfile::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('update', $profile);
            if ($profile->write_version !== $version) {
                throw new WriteConflict('stale_profile_version', $profile->write_version);
            }
            $profile->fill($this->validated($values));
            $profile->write_version++;
            $profile->save();

            return $profile->fresh();
        });
    }

    private function validated(array $values): array
    {
        return Validator::make($values, ['name' => ['required', 'string', 'max:255'], 'recipients' => ['present', 'array', 'max:10'], 'recipients.*' => ['required', 'email:rfc', 'max:254', 'distinct'], 'active' => ['required', 'boolean']])->validate();
    }
}

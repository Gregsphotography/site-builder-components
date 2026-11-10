<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Content\Document;
use SiteBuilder\Core\Models\Page;
use SiteBuilder\Core\Rendering\ContentOccurrences;
use SiteBuilder\Core\Rendering\RenderContext;

/** Form schemas are resolved from current publication, never accepted from a browser. */
final class FormRuntime
{
    private ?bool $schemaAvailable = null;

    public function clearAvailabilityCache(): void
    {
        $this->schemaAvailable = null;
    }

    public function installation(): ?string
    {
        $id = config('site-builder-forms.installation_id');

        return is_string($id) && Str::isUuid($id) ? $id : null;
    }

    public function available(): bool
    {
        if (! config('site-builder-forms.enabled', false) || $this->installation() === null) {
            return false;
        }

        return $this->schemaAvailable ??= array_all(['site_builder_form_profiles', 'site_builder_form_submissions', 'site_builder_form_notifications'], fn ($table): bool => (new NotificationProfile)->getConnection()->getSchemaBuilder()->hasTable($table));
    }

    public function profile(array $props): ?NotificationProfile
    {
        if (! $this->available() || ($props['installation'] ?? null) !== $this->installation() || ! is_string($props['profile'] ?? null)) {
            return null;
        }

        return NotificationProfile::query()->whereKey($props['profile'])->where('active', true)->first();
    }

    public function context(RenderContext $context, string $address, array $props): ?string
    {
        if ($context->mode !== 'public' || $context->pageId === null || $this->profile($props) === null) {
            return null;
        }

        return Crypt::encryptString(json_encode(['page' => $context->pageId, 'locale' => $context->locale, 'address' => $address, 'digest' => app(CanonicalJson::class)->digest($props), 'expires' => now()->addMinutes((int) config('site-builder-forms.context_minutes', 120))->timestamp, 'nonce' => (string) Str::uuid()], JSON_THROW_ON_ERROR));
    }

    /** @return array{page:Page,locale:string,address:string,digest:string,nonce:string,props:array,profile:NotificationProfile} */
    public function resolve(string $encrypted, bool $lock = false): array
    {
        try {
            $context = json_decode(Crypt::decryptString($encrypted), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(422, 'This form context is invalid. Reload the page and try again.');
        }
        abort_unless(is_array($context) && is_int($context['page'] ?? null) && is_string($context['locale'] ?? null) && is_string($context['address'] ?? null) && is_string($context['digest'] ?? null) && is_int($context['expires'] ?? null) && $context['expires'] >= now()->timestamp && Str::isUuid($context['nonce'] ?? ''), 422, 'This form has expired. Reload the page and try again.');
        $page = Page::query()->whereKey($context['page'])->where('status', 'published')->when($lock, fn ($query) => $query->lockForUpdate())->first();
        abort_unless($page !== null && $page->slugs()->where('locale', $context['locale'])->exists(), 409, 'This page is no longer published in this language.');
        $render = new RenderContext($context['locale'], 'public', $page->id, $page->layout);
        foreach (app(ContentOccurrences::class)->visible($page->published_content ?? new Document, $render, true) as $entry) {
            if ($entry['address'] !== $context['address'] || $entry['node']->type !== 'standard.form') {
                continue;
            }
            abort_unless(hash_equals($context['digest'], app(CanonicalJson::class)->digest($entry['props'])), 409, 'This form has changed. Reload the page before submitting.');
            $profile = $this->profile($entry['props']);
            abort_unless($profile !== null, 409, 'This form is temporarily unavailable.');

            return ['page' => $page, 'locale' => $context['locale'], 'address' => $context['address'], 'digest' => $context['digest'], 'nonce' => $context['nonce'], 'props' => $entry['props'], 'profile' => $profile];
        }
        abort(409, 'This form is no longer available. Reload the page.');
    }
}

<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SiteBuilder\Core\Content\CanonicalJson;
use SiteBuilder\Core\Routing\PageUrlResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SubmissionController
{
    public function __invoke(Request $request, FormRuntime $runtime): JsonResponse|RedirectResponse
    {
        abort_unless($runtime->available(), 503, 'The enquiry service is temporarily unavailable.');
        $request->validate(['context' => ['required', 'string', 'max:20000'], 'website' => ['nullable', 'string', 'max:1000']]);
        try {
            $resolved = $runtime->resolve($request->string('context')->toString());
        } catch (HttpException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }
            throw ValidationException::withMessages(['_form' => $exception->getMessage()]);
        }
        if ($request->filled('website')) {
            return $this->accepted($request, $resolved);
        }
        $fields = $resolved['props']['fields'] ?? [];
        $rules = ['fields' => ['required', 'array:'.implode(',', array_map(fn ($field) => $field->key, $fields))]];
        foreach ($fields as $field) {
            $rules['fields.'.$field->key] = [($field->required ?? false) ? ($field->type === 'checkbox' ? 'accepted' : 'required') : 'nullable', ...match ($field->type) {
                'email' => ['string', 'email:rfc', 'max:254'],
                'checkbox' => ['boolean'],
                'select' => ['string', Rule::in(array_map(fn ($option) => $option->value, $field->options ?? []))],
                'textarea' => ['string', 'max:10000'],
                default => ['string', 'max:1000'],
            }];
        }
        $validated = $request->validate($rules);
        $values = [];
        foreach ($fields as $field) {
            $value = $validated['fields'][$field->key] ?? null;
            $values[$field->key] = ['label' => $field->label, 'type' => $field->type, 'value' => $field->type === 'checkbox' ? (bool) $value : ($value ?? '')];
        }
        $notification = DB::transaction(function () use ($request, $runtime, $values): ?FormNotification {
            $current = $runtime->resolve($request->string('context')->toString(), true);
            $existing = Submission::query()->where('request_token', $current['nonce'])->first();
            if ($existing !== null) {
                if (! app(CanonicalJson::class)->equals($existing->fields, $values)) {
                    throw ValidationException::withMessages(['_form' => 'This enquiry was already received. Reload the page to send another enquiry.']);
                }

                return null;
            }
            $profile = NotificationProfile::query()->whereKey($current['profile']->id)->where('active', true)->lockForUpdate()->first();
            abort_unless($profile !== null, 409, 'This form is temporarily unavailable.');
            $submission = Submission::query()->create(['profile_id' => $profile->id, 'page_id' => $current['page']->id, 'locale' => $current['locale'], 'form_address' => $current['address'], 'form_title' => $current['props']['heading'] ?: 'Website enquiry', 'form_digest' => $current['digest'], 'request_token' => $current['nonce'], 'fields' => $values]);

            return config('site-builder-forms.notifications_enabled', true) && $profile->recipients !== [] ? FormNotification::query()->create(['submission_id' => $submission->id, 'recipients' => $profile->recipients, 'status' => 'pending']) : null;
        }, 3);
        if ($notification !== null) {
            app(NotificationOutbox::class)->enqueue($notification->id);
        }

        return $this->accepted($request, $resolved);
    }

    private function accepted(Request $request, array $resolved): JsonResponse|RedirectResponse
    {
        $message = $resolved['props']['success_message'] ?: 'Thank you. Your enquiry has been received.';
        if ($request->expectsJson()) {
            return response()->json(['accepted' => true, 'message' => $message]);
        }

        return redirect()->to(app(PageUrlResolver::class)->path($resolved['page'], $resolved['locale']))->with('site_builder_form_success', $resolved['page']->id.':'.$resolved['address']);
    }
}

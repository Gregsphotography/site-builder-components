<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Components\Forms\FormRuntime;
use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Form extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.form';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.form';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['profile' => null, 'installation' => null], translations: ['en' => ['heading' => 'Get in touch', 'submit_label' => 'Send enquiry', 'success_message' => 'Thank you. Your enquiry has been received.', 'fields' => [['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'help' => '', 'options' => []], ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'help' => '', 'options' => []], ['key' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true, 'help' => '', 'options' => []]]]]);
    }

    public function properties(): array
    {
        return ['profile' => P::text(nullable: true, rules: ['uuid']), 'installation' => P::text(nullable: true, rules: ['uuid']), 'heading' => P::text(translated: true), 'submit_label' => P::text(translated: true, requiredForPublish: true), 'success_message' => P::text(translated: true, requiredForPublish: true), 'fields' => new P('list', translated: true, rules: ['min:1', 'max:15'], requiredForPublish: true, items: P::object(['key' => P::text(rules: ['regex:/^[a-z][a-z0-9_]{0,39}$/D'], requiredForPublish: true), 'type' => P::enum(['text', 'email', 'tel', 'textarea', 'select', 'checkbox']), 'label' => P::text(rules: ['max:255'], requiredForPublish: true), 'help' => P::text(rules: ['max:1000']), 'required' => new P('boolean'), 'options' => new P('list', rules: ['max:50'], items: P::object(['label' => P::text(requiredForPublish: true), 'value' => P::text(rules: ['max:255'], requiredForPublish: true)]))]))];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        $errors = [];
        $keys = [];
        $extra = 0;
        foreach ($values->get('fields', []) ?? [] as $index => $field) {
            if (! $field instanceof \stdClass) {
                continue;
            }
            $key = $field->key ?? '';
            if (isset($keys[$key])) {
                $errors[] = new Diagnostic('duplicate_form_field', '/fields/'.$index.'/key', 'Each form field needs a unique key.');
            }
            $keys[$key] = true;
            $extra += in_array($key, ['name', 'email', 'message', 'phone', 'subject'], true) ? 0 : 1;
            if ($publishing && ($field->type ?? '') === 'select' && ($field->options ?? []) === []) {
                $errors[] = new Diagnostic('required_for_publish', '/fields/'.$index.'/options', 'A select field needs options.');
            }
        }
        if ($extra > 10) {
            $errors[] = new Diagnostic('too_many_form_fields', '/fields', 'Use at most ten additional fields.');
        }
        if ($publishing && app(FormRuntime::class)->profile((array) $values->toObject()) === null) {
            $errors[] = new Diagnostic('unavailable_form_profile', '/profile', 'Enable the forms runtime and explicitly choose an active notification profile for this installation.');
        }

        return $errors;
    }
}

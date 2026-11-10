@php
    $runtime = app(\SiteBuilder\Components\Forms\FormRuntime::class);
    $address = $attributes->get('data-site-builder-render-address') ?: 'body/'.$node->node->id;
    $formKey = ($context->pageId ?? 'preview').':'.$address;
    $identity = 'sb-form-'.substr(hash('sha256', $formKey), 0, 20);
    $token = $runtime->context($context, $address, $props);
    $oldForm = session()->getOldInput('_sb_form_address') === $formKey;
    $success = session('site_builder_form_success') === $formKey;
    if ($token !== null) {
        app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.forms');
        app(\SiteBuilder\Core\Rendering\PublicAssets::class)->requirePrivateResponse();
    }
@endphp
<section {{ $attributes->class(['sb-block', 'sb-enquiry']) }}>
    @if(filled($props['heading']))
        <h2>{{ $props['heading'] }}</h2>
    @endif
    @if($success)
        <p role="status">{{ $props['success_message'] }}</p>
    @else
        @if($token === null)
            <p class="sb-form-unavailable">{{ $context->mode === 'preview' ? __('Enquiry preview — choose an approved notification profile and publish the page to accept enquiries.') : __('This enquiry form is temporarily unavailable. Please use the contact details on this website.') }}</p>
        @endif
        <form method="post" @if($token) action="{{ route('site-builder.forms.submit') }}" data-sb-enquiry-form @else inert @endif aria-label="{{ $props['heading'] ?: __('Enquiry') }}" data-form-id="{{ $identity }}">
            @if($token)
                @csrf
                <input type="hidden" name="context" value="{{ $token }}">
                <input type="hidden" name="_sb_form_address" value="{{ $formKey }}">
                <div class="sb-honeypot" aria-hidden="true"><label>Website<input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
            @endif
            <div role="alert" data-sb-form-errors @if(!$oldForm || !$errors->any()) hidden @endif>
                @if($oldForm)
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                @endif
            </div>
            @foreach($props['fields'] ?? [] as $field)
                @php
                    $fieldId = $identity.'-'.$field->key;
                    $value = $oldForm ? old('fields.'.$field->key, '') : '';
                @endphp
                <div class="sb-form-field">
                    <label for="{{ $fieldId }}">{{ $field->label }} @if($field->required) <span aria-hidden="true">*</span> @endif</label>
                    @if($field->type === 'textarea')
                        <textarea id="{{ $fieldId }}" name="fields[{{ $field->key }}]" rows="6" maxlength="10000" @required($field->required) aria-describedby="{{ $fieldId }}-help">{{ $value }}</textarea>
                    @elseif($field->type === 'select')
                        <select id="{{ $fieldId }}" name="fields[{{ $field->key }}]" @required($field->required) aria-describedby="{{ $fieldId }}-help"><option value="">{{ __('Choose an option') }}</option>
                            @foreach($field->options ?? [] as $option)
                                <option value="{{ $option->value }}" @selected($value === $option->value)>{{ $option->label }}</option>
                            @endforeach
                        </select>
                    @elseif($field->type === 'checkbox')
                        <input type="checkbox" id="{{ $fieldId }}" name="fields[{{ $field->key }}]" value="1" @checked((bool)$value) @required($field->required) aria-describedby="{{ $fieldId }}-help">
                    @else
                        <input type="{{ $field->type }}" id="{{ $fieldId }}" name="fields[{{ $field->key }}]" value="{{ $value }}" maxlength="{{ $field->type === 'email' ? 254 : 1000 }}" @required($field->required) aria-describedby="{{ $fieldId }}-help">
                    @endif
                    <small id="{{ $fieldId }}-help">{{ $field->help }}</small>
                </div>
            @endforeach
            <button class="sb-button" type="submit" @disabled($token === null)>{{ $props['submit_label'] }}</button>
        </form>
    @endif
</section>

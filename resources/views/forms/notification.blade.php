<h1>{{ $submission->form_title }}</h1>
<p>{{ __('Received from your website in :language.', ['language' => $submission->locale]) }}</p>
<dl>
@foreach($submission->fields as $field)
    <dt><strong>{{ $field['label'] }}</strong></dt>
    <dd style="white-space:pre-wrap">{{ is_bool($field['value']) ? ($field['value'] ? __('Yes') : __('No')) : $field['value'] }}</dd>
@endforeach
</dl>
<p>{{ __('Submission reference') }}: {{ $submission->id }}</p>

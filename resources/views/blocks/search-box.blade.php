@php
    $pageId = $props['results_page'] ?? config('site-builder.discovery.results_page');
    $destination = app(\SiteBuilder\Components\Rendering\SearchDestination::class)->resolve($pageId, $context->locale);
    $url = $destination['url'] ?? null;
    $id = 'sb-search-'.substr(hash('sha256', $attributes->get('data-site-builder-render-address') ?? $node->node->id), 0, 20);
    $live = $context->mode === 'public' && ($props['live'] ?? true) && \Illuminate\Support\Facades\Route::has('site-builder.search.suggest');
    if ($live && $url) app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.discovery');
@endphp
<div {{ $attributes->class(['sb-block', 'sb-search']) }}>
    @if($url)
        <form method="get" action="{{ $url }}" role="search" @if($live) data-source="{{ $destination['source'] }}" data-content-type="{{ $destination['type'] }}" data-content-tag="{{ $destination['tag'] }}" data-singular-label="{{ __('1 suggestion') }}" data-count-label="{{ __(':count suggestions') }}" data-unavailable-label="{{ __('Suggestions unavailable. Submit to search.') }}" data-sb-search data-endpoint="{{ route('site-builder.search.suggest') }}" data-locale="{{ $context->locale }}" @endif>
            @foreach(['source', 'type', 'tag'] as $key)@if($destination[$key] !== '' && $destination[$key] !== '*')<input type="hidden" name="{{ $key }}" value="{{ $destination[$key] }}">@endif @endforeach
            <label for="{{ $id }}">{{ $props['label'] }}</label>
            <div class="sb-search-input"><input id="{{ $id }}" name="q" type="search" maxlength="200" placeholder="{{ $props['placeholder'] ?? '' }}" value="{{ $context->mode === 'public' ? $context->query?->get('q') : '' }}" autocomplete="off"><button class="sb-button" type="submit" @disabled($context->mode === 'preview')>{{ $props['button_label'] }}</button></div>
            <ul id="{{ $id }}-suggestions" class="sb-search-suggestions" data-suggestions hidden></ul><p class="sb-sr-only" data-search-status role="status" aria-live="polite"></p>
        </form>
    @elseif($context->mode === 'preview')<p>{{ __('Choose a published search results page.') }}</p>@endif
</div>

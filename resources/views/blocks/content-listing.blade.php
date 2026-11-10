@php
    $search = $node->node->type === 'standard.search-results';
    $address = $attributes->get('data-site-builder-render-address') ?? $node->node->id;
    $prefix = $search ? '' : app(\SiteBuilder\Components\Rendering\ContentLists::class)->key($address);
    $results = app(\SiteBuilder\Components\Rendering\ContentLists::class)->results($props, $context, $prefix, $search);
    $query = $context->mode === 'public' ? $context->query : null;
@endphp
<section {{ $attributes->class(['sb-block', 'sb-content-listing', 'sb-content-listing--'.($props['variant'] ?? 'cards')]) }} aria-label="{{ $props['label'] ?? '' }}">
    @if(filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @if($search || ($props['visitor_filters'] ?? false))
        <form method="get" class="sb-content-filters" role="search">
            @foreach($query?->all() ?? [] as $key => $value)
                @if(!in_array($key, [$prefix.'q', $prefix.'type', $prefix.'tag', $prefix.'page', ...($search ? ['source'] : [])], true))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
            @endforeach
            @if($search && ($props['source'] ?? '*') === '*')<label>{{ __('Source') }}<select name="source"><option value="">{{ __('All sources') }}</option>@foreach(app(\SiteBuilder\Core\Discovery\ContentSources::class)->options() as $key => $label)<option value="{{ $key }}" @selected($query?->get('source') === $key)>{{ $label }}</option>@endforeach</select></label>@endif
            <label>{{ __('Search') }}<input type="search" name="{{ $prefix }}q" maxlength="200" value="{{ $query?->get($prefix.'q') ?? '' }}"></label>
            @if(!filled($props['content_type'] ?? null) && count($results->types) > 1)
                <label>{{ __('Content type') }}<select name="{{ $prefix }}type"><option value="">{{ __('All types') }}</option>@foreach($results->types as $type)<option value="{{ $type }}" @selected($query?->get($prefix.'type') === $type)>{{ $type }}</option>@endforeach</select></label>
            @endif
            @if(!filled($props['content_tag'] ?? null) && count($results->tags))
                <label>{{ __('Topic') }}<select name="{{ $prefix }}tag"><option value="">{{ __('All topics') }}</option>@foreach($results->tags as $tag)<option value="{{ $tag }}" @selected($query?->get($prefix.'tag') === $tag)>{{ $tag }}</option>@endforeach</select></label>
            @endif
            <button class="sb-button" type="submit" @disabled($context->mode === 'preview')>{{ __('Apply') }}</button>
            <a href="{{ app(\SiteBuilder\Components\Rendering\ContentLists::class)->pageUrl(new \SiteBuilder\Core\Rendering\RenderContext($context->locale, query: new \SiteBuilder\Core\Rendering\PublicQuery(array_diff_key($query?->all() ?? [], array_flip([$prefix.'q', $prefix.'type', $prefix.'tag', $prefix.'page', ...($search ? ['source'] : [])])))), $prefix, 1) }}">{{ __('Clear filters') }}</a>
        </form>
    @endif
    @if(!$results->available)<p role="status">{{ __('Content is currently unavailable.') }}</p>
    @elseif($results->items === [])<p role="status">{{ $props['empty_message'] ?? __('No matching content.') }}</p>
    @else
        <p class="sb-content-count">{{ trans_choice(':count result|:count results', $results->total, ['count' => $results->total]) }}</p>
        <ul class="sb-content-items">
            @foreach($results->items as $item)<li>@include('site-builder-components::partials.content-item', ['item' => $item, 'options' => $props])</li>@endforeach
        </ul>
        @if($results->pages() > 1)
            <nav class="sb-content-pagination" aria-label="{{ __('Content pages') }}">
                @if($results->page > 1)<a rel="prev" href="{{ app(\SiteBuilder\Components\Rendering\ContentLists::class)->pageUrl($context, $prefix, $results->page - 1) }}">{{ __('Previous') }}</a>@endif
                <span>{{ __('Page :page of :pages', ['page' => $results->page, 'pages' => $results->pages()]) }}</span>
                @if($results->page < $results->pages())<a rel="next" href="{{ app(\SiteBuilder\Components\Rendering\ContentLists::class)->pageUrl($context, $prefix, $results->page + 1) }}">{{ __('Next') }}</a>@endif
            </nav>
        @endif
    @endif
</section>

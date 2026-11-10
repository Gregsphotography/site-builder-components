@php
    $dynamic = ($props['content_mode'] ?? 'manual') === 'dynamic';
    $prefix = app(\SiteBuilder\Components\Rendering\ContentLists::class)->key($attributes->get('data-site-builder-render-address') ?? $node->node->id);
    $items = $props['items'] ?? [];
    $results = null;
    if ($dynamic) {
        $results = app(\SiteBuilder\Components\Rendering\ContentLists::class)->results($props, $context, $prefix);
        $items = array_map(fn ($item) => (object) ['title' => $item->title, 'date' => $item->date, 'label' => '', 'body' => e($item->excerpt), 'image' => $item->image, 'alt' => $item->alt, 'url' => $item->url, 'link_label' => $item->title, 'page' => null], $results->items);
    }
@endphp
<section {{ $attributes->class(['sb-block', 'sb-timeline', 'sb-timeline--'.($props['variant'] ?? 'vertical')]) }} aria-label="{{ $props['label'] ?? '' }}">
    @if(filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @if($results && !$results->available)<p role="status">{{ __('Content is currently unavailable.') }}</p>@elseif($items === [])<p>{{ __('No events to show yet.') }}</p>@endif
    <ol class="sb-timeline-events">
        @foreach($items as $item)
            @php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($item->page ?? null, $item->url ?? null, $context->locale))
            <li class="sb-timeline-event">
                @if(($props['show_date'] ?? true) && filled($item->date ?? null))<time datetime="{{ $item->date }}">{{ $item->date }}</time>@elseif(filled($item->label ?? null))<p class="sb-eyebrow">{{ $item->label }}</p>@elseif($dynamic)<p class="sb-eyebrow">{{ __('Undated') }}</p>@endif
                <h3>{{ $item->title ?? '' }}</h3>
                @if(($props['show_image'] ?? true) && ($item->image ?? null))<img src="{{ $item->image }}" alt="{{ $item->alt ?? '' }}" loading="lazy" decoding="async">@endif
                @if(($props['show_excerpt'] ?? true) && filled($item->body ?? null))<div class="sb-prose">{!! $item->body !!}</div>@endif
                @if($url)<a href="{{ $url }}">{{ ($item->link_label ?? '') ?: ($item->title ?? '') }}</a>@endif
            </li>
        @endforeach
    </ol>
    @if($results && $results->pages() > 1)
        <nav class="sb-content-pagination" aria-label="{{ __('Timeline pages') }}">
            @if($results->page > 1)<a rel="prev" href="{{ app(\SiteBuilder\Components\Rendering\ContentLists::class)->pageUrl($context, $prefix, $results->page - 1) }}">{{ __('Previous') }}</a>@endif
            <span>{{ __('Page :page of :pages', ['page' => $results->page, 'pages' => $results->pages()]) }}</span>
            @if($results->page < $results->pages())<a rel="next" href="{{ app(\SiteBuilder\Components\Rendering\ContentLists::class)->pageUrl($context, $prefix, $results->page + 1) }}">{{ __('Next') }}</a>@endif
        </nav>
    @endif
</section>

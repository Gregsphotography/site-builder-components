@php
    $id = 'sb-hotspots-'.substr(hash('sha256', ($context->pageId ?? 'fragment').':'.($attributes->get('data-site-builder-render-address') ?? $node->node->id)), 0, 20);
    $items = $props['items'] ?? [];
    $enhanced = $context->mode === 'public' && $props['image'] && count($items);
    if ($enhanced) app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.discovery');
@endphp
<section {{ $attributes->class(['sb-block', 'sb-hotspots']) }} aria-label="{{ $props['label'] ?? '' }}" @if($enhanced) data-sb-hotspots @endif>
    @if(filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    <div class="sb-hotspot-image">@if($props['image'])<img src="{{ $props['image'] }}" alt="{{ $props['alt'] ?? '' }}" loading="lazy" decoding="async">@endif
        @foreach($items as $item)
            <button class="sb-hotspot-marker" type="button" hidden data-hotspot-trigger data-x="{{ max(0, min(10000, (int) ($item->x ?? 5000))) }}" data-y="{{ max(0, min(10000, (int) ($item->y ?? 5000))) }}" aria-expanded="false" aria-controls="{{ $id }}-panel-{{ $loop->index }}" aria-label="{{ $loop->iteration }}. {{ $item->title ?? '' }}">{{ $loop->iteration }}</button>
        @endforeach
    </div>
    <ol class="sb-hotspot-details">
        @foreach($items as $item)
            @php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($item->page ?? null, $item->url ?? null, $context->locale))
            <li data-hotspot-detail><h3>{{ $item->title ?? '' }}</h3><div class="sb-prose">{!! $item->body ?? '' !!}</div>@if($url)<a href="{{ $url }}">{{ ($item->link_label ?? '') ?: ($item->title ?? '') }}</a>@endif</li>
        @endforeach
    </ol>
    @foreach($items as $item)<div id="{{ $id }}-panel-{{ $loop->index }}" class="sb-hotspot-popover" data-hotspot-panel hidden role="region" aria-label="{{ $item->title ?? '' }}"><button type="button" data-hotspot-close aria-label="{{ __('Close detail') }}">×</button><div data-hotspot-copy></div></div>@endforeach
</section>

@php
    $items = array_map(fn ($item) => (array) $item, $props['items'] ?? []);
    if (($props['variant'] ?? 'grid') === 'single') { $items = array_slice($items, 0, 1); }
    $links = app(\SiteBuilder\Components\Rendering\ContentLinks::class);
    $images = app(\SiteBuilder\Components\Rendering\Images::class);
@endphp
<section {{ $attributes->class(['sb-block', 'sb-testimonials', 'sb-testimonials--'.($props['variant'] ?? 'grid')]) }}>
    @if(!empty($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    <div class="sb-testimonials-grid">
    @foreach($items as $item)
        <figure>
            <blockquote><p>{{ $item['quote'] ?? '' }}</p></blockquote>
            <figcaption>
                @if(!empty($item['portrait']))<img src="{{ $item['portrait'] }}" alt="{{ $item['alt'] ?? '' }}" @if($srcset = $images->srcset($item['sources'] ?? [])) srcset="{{ $srcset }}" sizes="5rem" @endif @if(!empty($item['width'])) width="{{ $item['width'] }}" @endif @if(!empty($item['height'])) height="{{ $item['height'] }}" @endif loading="lazy">@endif
                <span><strong>{{ $item['name'] ?? '' }}</strong>@if(!empty($item['role']))<span>{{ $item['role'] }}</span>@endif @if(!empty($item['organisation']))<span>{{ $item['organisation'] }}</span>@endif
                @if($url = $links->url($item['page'] ?? null, $item['url'] ?? null, $context->locale))<a href="{{ $url }}">{{ __('Source: :name', ['name' => $item['name'] ?? '']) }}</a>@endif</span>
            </figcaption>
        </figure>
    @endforeach
    </div>
</section>

@php
    $dynamic = ($props['content_mode'] ?? 'manual') === 'dynamic';
    $address = $attributes->get('data-site-builder-render-address') ?? $node->node->id;
    $id = 'sb-carousel-'.substr(hash('sha256', ($context->pageId ?? 'fragment').':'.$address), 0, 20);
    $items = $props['items'] ?? [];
    $available = true;
    if ($dynamic) {
        $results = app(\SiteBuilder\Components\Rendering\ContentLists::class)->results($props, new \SiteBuilder\Core\Rendering\RenderContext($context->locale, $context->mode, $context->pageId, $context->layout), '');
        $available = $results->available;
        $items = array_map(fn ($item) => (object) ['title' => $item->title, 'subtitle' => $item->subtitle, 'excerpt' => $item->excerpt, 'image' => $item->image, 'alt' => $item->alt, 'url' => $item->url, 'page' => null], $results->items);
    }
    $enhanced = $context->mode === 'public' && count($items) > 1;
    if ($enhanced) app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.discovery');
    $all = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($props['view_all_page'] ?? null, $props['view_all_url'] ?? null, $context->locale);
@endphp
<section {{ $attributes->class(['sb-block', 'sb-carousel', 'sb-carousel--'.($props['variant'] ?? 'cards')]) }} id="{{ $id }}" aria-label="{{ $props['label'] ?? '' }}" @if($enhanced) data-carousel-label="{{ __('carousel') }}" data-position-label="{{ __(':start–:end of :count') }}" data-sb-carousel data-per-view="{{ ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4][$props['per_view'] ?? 'three'] ?? 3 }}" data-autoplay="{{ ($props['autoplay'] ?? false) ? 'true' : 'false' }}" data-interval="{{ max(3, min(30, (int) ($props['interval'] ?? 5))) }}" @endif>
    @if(filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @if(!$available)<p role="status">{{ __('Content is currently unavailable.') }}</p>@elseif($items === [])<p>{{ __('No content to show yet.') }}</p>@endif
    <ul class="sb-carousel-track" id="{{ $id }}-track" data-carousel-track>
        @foreach($items as $item)
            @php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($item->page ?? null, $item->url ?? null, $context->locale))
            <li class="sb-carousel-slide" data-carousel-slide>
                @if($item->image ?? null)<img src="{{ $item->image }}" alt="{{ $item->alt ?? '' }}" loading="lazy" decoding="async">@endif
                @if(($props['variant'] ?? 'cards') === 'testimonials')<blockquote><p>{{ $item->excerpt ?? '' }}</p><footer><strong>{{ $item->title ?? '' }}</strong>@if(filled($item->subtitle ?? null))<span>{{ $item->subtitle }}</span>@endif</footer></blockquote>
                @else<div class="sb-carousel-copy"><h3>@if($url)<a href="{{ $url }}">{{ $item->title ?? '' }}</a>@else{{ $item->title ?? '' }}@endif</h3>@if(filled($item->subtitle ?? null))<p>{{ $item->subtitle }}</p>@endif @if(($props['show_excerpt'] ?? true) && filled($item->excerpt ?? null))<p>{{ $item->excerpt }}</p>@endif</div>@endif
            </li>
        @endforeach
    </ul>
    <div class="sb-carousel-controls" data-carousel-controls hidden>
        <button type="button" data-carousel-prev aria-controls="{{ $id }}-track" aria-label="{{ __('Previous slides') }}">←</button>
        <span data-carousel-position role="status" aria-live="polite"></span>
        <button type="button" data-carousel-next aria-controls="{{ $id }}-track" aria-label="{{ __('Next slides') }}">→</button>
        @if($props['autoplay'] ?? false)<button type="button" data-carousel-pause data-pause-label="{{ __('Pause slideshow') }}" data-play-label="{{ __('Play slideshow') }}">{{ __('Pause slideshow') }}</button>@endif
    </div>
    @if($props['autoplay'] ?? false)<p data-carousel-motion-note hidden>{{ __('Automatic playback is off while reduced motion is enabled.') }}</p>@endif
    @if($all)<a href="{{ $all }}">{{ $props['view_all_label'] ?? __('View all') }}</a>@endif
</section>

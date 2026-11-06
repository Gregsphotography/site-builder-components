@php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($props['page'], $props['url'], $context->locale))
<section {{ $attributes->class(['sb-block', 'sb-cta', 'sb-cta--'.($props['variant'] ?? 'brand')]) }}>
    @if (filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @if (filled($props['body']))<div class="sb-prose">{!! $props['body'] !!}</div>@endif
    @if (filled($url) && filled($props['label']))<a class="sb-button" href="{{ $url }}">{{ $props['label'] }}</a>@endif
</section>

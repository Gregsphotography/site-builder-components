@php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($props['page'], $props['url'], $context->locale))
@php($srcset = app(\SiteBuilder\Components\Rendering\Images::class)->srcset($props['sources']))
<section {{ $attributes->class(['sb-block', 'sb-image-text', 'sb-image-text--'.($props['variant'] ?? 'image-left')]) }}>
    @if (filled($props['image']))<img class="sb-image-text__image" src="{{ $props['image'] }}" alt="{{ $props['alt'] ?? '' }}" loading="lazy" @if ($props['width'] > 0 && $props['height'] > 0) width="{{ (int) $props['width'] }}" height="{{ (int) $props['height'] }}" @endif @if ($srcset) srcset="{{ $srcset }}" sizes="(max-width: 48rem) 100vw, 36rem" @endif>@endif
    <div>
        @if (filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
        @if (filled($props['body']))<div class="sb-prose">{!! $props['body'] !!}</div>@endif
        @if (filled($url) && filled($props['label']))<a class="sb-button" href="{{ $url }}">{{ $props['label'] }}</a>@endif
    </div>
</section>

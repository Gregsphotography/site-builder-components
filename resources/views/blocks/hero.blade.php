@php
    $variant = $props['variant'] ?? 'centred';
    $links = app(\SiteBuilder\Components\Rendering\ContentLinks::class);
    $primary = $links->url($props['page'], $props['url'], $context->locale);
    $secondary = $links->url($props['secondary_page'], $props['secondary_url'], $context->locale);
    $srcset = app(\SiteBuilder\Components\Rendering\Images::class)->srcset($props['sources']);
@endphp
<section {{ $attributes->class(['sb-block', 'sb-hero', 'sb-hero--'.$variant]) }}>
    @if (filled($props['image']))<img class="sb-hero__image" src="{{ $props['image'] }}" alt="{{ $props['alt'] ?? '' }}" fetchpriority="high" @if ($props['width'] > 0 && $props['height'] > 0) width="{{ (int) $props['width'] }}" height="{{ (int) $props['height'] }}" @endif @if ($srcset) srcset="{{ $srcset }}" sizes="(max-width: 48rem) 100vw, 72rem" @endif>@endif
    <div class="sb-hero__content">
        @if (filled($props['eyebrow']))<p class="sb-eyebrow">{{ $props['eyebrow'] }}</p>@endif
        @if (filled($props['heading']))<h1>{{ $props['heading'] }}</h1>@endif
        @if (filled($props['body']))<div class="sb-prose">{!! $props['body'] !!}</div>@endif
        @if (filled($primary) || filled($secondary))<div class="sb-actions">
            @if (filled($primary) && filled($props['label']))<a class="sb-button" href="{{ $primary }}">{{ $props['label'] }}</a>@endif
            @if (filled($secondary) && filled($props['secondary_label']))<a class="sb-button sb-button--secondary" href="{{ $secondary }}">{{ $props['secondary_label'] }}</a>@endif
        </div>@endif
    </div>
</section>

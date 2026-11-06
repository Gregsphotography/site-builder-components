@php($srcset = app(\SiteBuilder\Components\Rendering\Images::class)->srcset($props['sources']))
<figure {{ $attributes->class(['sb-block', 'sb-image', 'sb-image--'.($props['variant'] ?? 'default')]) }}>
    @if (filled($props['image']))<img src="{{ $props['image'] }}" alt="{{ ($props['decorative'] ?? false) ? '' : ($props['alt'] ?? '') }}" loading="lazy" @if ($props['width'] > 0 && $props['height'] > 0) width="{{ (int) $props['width'] }}" height="{{ (int) $props['height'] }}" @endif @if ($srcset) srcset="{{ $srcset }}" sizes="(max-width: 48rem) 100vw, 72rem" @endif @if ($props['decorative'] ?? false) role="presentation" @endif>@endif
    @if (filled($props['caption']))<figcaption>{{ $props['caption'] }}</figcaption>@endif
</figure>

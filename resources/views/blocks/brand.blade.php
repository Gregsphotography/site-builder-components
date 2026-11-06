@php
    $name = $props['name'] ?? '';
    $variant = $props['variant'] ?? 'both';
    $logo = $variant !== 'text' ? ($props['logo'] ?? null) : null;
    $showName = $variant !== 'logo' || !$logo;
    $url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($props['page'] ?? null, $props['url'] ?? null, $context->locale);
@endphp
<div {{ $attributes->class(['sb-block', 'sb-brand-block']) }}>
    @if($url)<a class="sb-brand" href="{{ $url }}">@else<span class="sb-brand">@endif
        @if($logo)<img src="{{ $logo }}" alt="{{ $showName ? '' : ($props['alt'] ?: $name) }}" @if(($props['width'] ?? 0) > 0 && ($props['height'] ?? 0) > 0) width="{{ (int)$props['width'] }}" height="{{ (int)$props['height'] }}" @endif>@endif
        @if($showName)<span>{{ $name }}</span>@endif
    @if($url)</a>@else</span>@endif
</div>

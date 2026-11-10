@php
    $map = app(\SiteBuilder\Components\Rendering\EmbedUrls::class)->map($props['embed_url']);
    $directions = $props['directions_url'] ?: 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($props['address'] ?? '');
    if ($map && $context->mode === 'public') { app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.embeds'); }
@endphp
<section {{ $attributes->class(['sb-block', 'sb-location']) }}>
    @if(filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    <address>{{ $props['address'] }}</address>
    <p><a href="{{ $directions }}">{{ $props['directions_label'] ?: __('Get directions') }}</a></p>
    @if($map && $context->mode === 'public')<div class="sb-map" data-sb-embed data-src="{{ $map }}" data-title="{{ $props['heading'] ?: $props['address'] }}" data-kind="map"><button type="button" data-sb-load-embed>{{ $props['load_label'] ?: __('Load Google Maps') }}</button></div>@elseif($map)<p>{{ __('Map preview — available on the published page.') }}</p>@endif
</section>

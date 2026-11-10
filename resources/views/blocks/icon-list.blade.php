<section {{ $attributes->class(['sb-block', 'sb-icon-list']) }}>
    @if(filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    <ul>@foreach($props['items'] ?? [] as $item)
        @php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($item->page, $item->url, $context->locale))
        <li>{{ app(\SiteBuilder\Components\Rendering\Icons::class)->html($item->icon ?? 'check-circle') }}@if($url)<a href="{{ $url }}">{{ $item->label }}</a>@else<span>{{ $item->label }}</span>@endif</li>
    @endforeach</ul>
</section>

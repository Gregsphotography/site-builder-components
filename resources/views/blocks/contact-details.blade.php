<section {{ $attributes->class(['sb-block', 'sb-contact-details']) }}>
    @if (filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @if (filled($props['body']))<div class="sb-prose">{!! $props['body'] !!}</div>@endif
    <dl>@foreach ($props['items'] ?? [] as $item)
        @php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($item->page, $item->url, $context->locale))
        <div><dt>{{ $item->label ?? '' }}</dt><dd>@if (filled($url))<a href="{{ $url }}">{{ $item->value ?? $item->label }}</a>@else{{ $item->value ?? '' }}@endif</dd></div>
    @endforeach</dl>
</section>

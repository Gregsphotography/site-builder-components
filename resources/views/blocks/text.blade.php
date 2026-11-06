<section {{ $attributes->class(['sb-block', 'sb-text', 'sb-text--'.($props['variant'] ?? 'default')]) }}>
    @if (filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @if (filled($props['body']))<div class="sb-prose">{!! $props['body'] !!}</div>@endif
    @if ($props['items'])<ul class="sb-link-list">@foreach ($props['items'] as $item)
        @php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($item->page, $item->url, $context->locale))
        <li>@if (filled($url))<a href="{{ $url }}">{{ $item->label }}</a>@else{{ $item->label }}@endif</li>
    @endforeach</ul>@endif
</section>

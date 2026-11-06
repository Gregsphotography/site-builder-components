<section {{ $attributes->class(['sb-block', 'sb-cards', 'sb-cards--'.($props['variant'] ?? 'cards'), 'sb-cards--'.($props['columns'] ?? 'three')]) }}>
    @if (filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @if (filled($props['body']))<div class="sb-prose">{!! $props['body'] !!}</div>@endif
    @if ($props['items'])<ul class="sb-cards__list">@foreach ($props['items'] as $item)
        @php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->url($item->page, $item->url, $context->locale))
        @php($srcset = app(\SiteBuilder\Components\Rendering\Images::class)->srcset($item->sources))
        <li class="sb-card">
            @if (filled($item->image))<img src="{{ $item->image }}" alt="{{ $item->alt ?? '' }}" loading="lazy" @if ($item->width > 0 && $item->height > 0) width="{{ (int) $item->width }}" height="{{ (int) $item->height }}" @endif @if ($srcset) srcset="{{ $srcset }}" sizes="(max-width: 48rem) 100vw, 24rem" @endif>@endif
            @if (filled($item->heading))<h3>@if (($props['variant'] ?? '') === 'logos' && filled($url))<a href="{{ $url }}">{{ $item->heading }}</a>@else{{ $item->heading }}@endif</h3>@endif
            @if (filled($item->body))<div class="sb-prose">{!! $item->body !!}</div>@endif
            @if (filled($url) && filled($item->label))<a href="{{ $url }}">{{ $item->label }}</a>@endif
        </li>
    @endforeach</ul>@endif
</section>

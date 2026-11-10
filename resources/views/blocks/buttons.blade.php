<div {{ $attributes->class(['sb-block', 'sb-buttons']) }} role="group" @if(filled($props['label'])) aria-label="{{ $props['label'] }}" @endif>
    @foreach($props['items'] ?? [] as $item)
        @php($url = app(\SiteBuilder\Components\Rendering\ContentLinks::class)->action($item->overlay ?? null, $item->page, $item->url, $context))
        @if($url)<a class="sb-button sb-button--{{ $item->variant ?? 'primary' }}" href="{{ $url }}">{{ app(\SiteBuilder\Components\Rendering\Icons::class)->html($item->icon) }}<span>{{ $item->label }}</span></a>@else<span class="sb-button-label">{{ $item->label }}</span>@endif
    @endforeach
</div>

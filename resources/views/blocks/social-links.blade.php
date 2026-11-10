<nav {{ $attributes->class(['sb-block', 'sb-social-links']) }} aria-label="{{ $props['label'] ?? __('Social links') }}">
    @foreach($props['items'] ?? [] as $item)
        @if($item->url)<a href="{{ $item->url }}">{{ app(\SiteBuilder\Components\Rendering\Icons::class)->html(match($item->service) { 'email' => 'envelope', 'telephone' => 'phone', 'website' => 'globe-alt', default => $item->service }, !in_array($item->service, ['email', 'telephone', 'website'], true)) }}<span>{{ $item->label }}</span></a>@endif
    @endforeach
</nav>

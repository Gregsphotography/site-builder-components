@php(app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.interactions'))
<section {{ $attributes->class(['sb-block', 'sb-accordion']) }} data-sb-accordion data-multiple="{{ ($props['multiple'] ?? true) ? 'true' : 'false' }}">@if(filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif{{ $children }}</section>

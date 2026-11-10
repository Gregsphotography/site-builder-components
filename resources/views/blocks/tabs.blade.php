@php(app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.interactions'))
<div {{ $attributes->class(['sb-block', 'sb-tabs']) }} data-sb-tabs aria-label="{{ $props['label'] }}">{{ $children }}</div>

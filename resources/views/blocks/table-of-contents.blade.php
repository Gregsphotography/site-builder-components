@php(app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.interactions'))
<nav {{ $attributes->class(['sb-block', 'sb-contents']) }} data-sb-outline data-include-h4="{{ ($props['include_h4'] ?? false) ? 'true' : 'false' }}" aria-label="{{ $props['heading'] }}"><p class="sb-contents-title">{{ $props['heading'] }}</p></nav>

<div {{ $attributes->class(['sb-block', 'sb-navigation-block']) }}>
@if(!empty($props['items']))
<nav class="sb-navigation sb-navigation--{{ $props['orientation'] ?? 'horizontal' }}" aria-label="{{ $props['label'] ?? '' }}">
    @include('site-builder-components::partials.navigation-items', ['items' => $props['items'], 'depth' => 1])
</nav>
@endif
</div>

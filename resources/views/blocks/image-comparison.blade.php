@php
    $id = 'sb-comparison-'.substr(hash('sha256', ($context->pageId ?? 'fragment').':'.($attributes->get('data-site-builder-render-address') ?? $node->node->id)), 0, 20);
    $enhanced = $context->mode === 'public' && $props['before_image'] && $props['after_image'];
    if ($enhanced) app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.discovery');
@endphp
<section {{ $attributes->class(['sb-block', 'sb-comparison', 'sb-comparison--'.($props['ratio'] ?? 'landscape')]) }} aria-label="{{ $props['label'] ?? '' }}" @if($enhanced) data-sb-comparison data-after-label="{{ $props['after_label'] ?? '' }}" @endif>
    @if(filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    <div class="sb-comparison-images">
        <figure class="sb-comparison-before">@if($props['before_image'])<img src="{{ $props['before_image'] }}" alt="{{ $props['before_alt'] ?? '' }}" loading="lazy" decoding="async">@endif<figcaption>{{ $props['before_label'] ?? '' }}</figcaption></figure>
        <figure class="sb-comparison-after">@if($props['after_image'])<img src="{{ $props['after_image'] }}" alt="{{ $props['after_alt'] ?? '' }}" loading="lazy" decoding="async">@endif<figcaption>{{ $props['after_label'] ?? '' }}</figcaption></figure>
    </div>
    <div class="sb-comparison-control" data-comparison-control hidden><label for="{{ $id }}">{{ __('Reveal :label', ['label' => $props['after_label'] ?? __('After')]) }}</label><input id="{{ $id }}" type="range" min="0" max="100" step="1" value="{{ max(0, min(100, (int) ($props['initial'] ?? 50))) }}" data-comparison-range></div>
</section>

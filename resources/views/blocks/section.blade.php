@php
    $heading = $props['heading'];
    $headingLevel = in_array($props['heading_level'], ['h2', 'h3'], true) ? $props['heading_level'] : 'h2';
    $address = $attributes->get('data-site-builder-render-address', $attributes->get('data-site-builder-node', $node->node->id));
    $headingId = 'sb-section-heading-'.substr(hash('sha256', (string) $address), 0, 24);
@endphp
@if (filled($heading))
<section {{ $attributes->class(['sb-block', 'sb-section'])->merge(['aria-labelledby' => $headingId]) }}>
    <{{ $headingLevel }} id="{{ $headingId }}">{{ $heading }}</{{ $headingLevel }}>
    {{ $children }}
</section>
@else
<div {{ $attributes->class(['sb-block', 'sb-section']) }}>{{ $children }}</div>
@endif
